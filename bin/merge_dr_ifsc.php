#!/usr/bin/php -qC
<?php
/**
 * Ranking - Merge digitalROCK and IFSC database into one again
 *
 * @link http://www.digitalrock.de
 * @link http://www.egroupware.org
 * @package ranking
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2015 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

if (php_sapi_name() !== 'cli')	// security precaution: forbit calling admin-cli as web-page
{
	die('<h1>Must NOT be called as web-page!</h1>');
}

// mysql-host
$host = 'localhost';
$user = 'root';
$password = '*******';
$port = 3306;

// database names for merge
$dr_db   = 'egw_www_digitalr';	// www.digitalrock.de
$ifsc_db = 'egw_ifsc_egroupw';	// ifsc.egroupware.net
$target  = 'egw_digitalrock_';	// digitalrock.egroupware.de

// directories for pictures
$dr_dir = '/var/www/servers/digitalrock.de/jpgs';
$ifsc_dir = '/var/www/servers/ifsc-climbing.org/jpgs';
$target_dir = '/var/www/servers/digitalrock.de/new-jpgs';
if (!file_exists($target_dir)) mkdir($target_dir, 0777);

// commands
$mysqldump = '/usr/bin/mysqldump --no-create-db';
$mysql = '/usr/bin/mysql';

// add credentials to mysql commands
foreach(array('mysql', 'mysqldump') as $cmd)
{
	$$cmd .= ' -h'.$host.' -u'.escapeshellarg($user).' -p'.escapeshellarg($password);
}

// mysqli objects indexed with db-name
$dbs = array();

$phpgw_baseline = array();
require_once(__DIR__.'/../setup/tables_current.inc.php');
$athlete_def = $phpgw_baseline['Personen']['fd'];

// In general we made sure, that Ids do not clash, by setting higher next-id for dR database.
foreach(array(
	// competitions, cups and all results: ifsc does NOT contain GER and SUI data, but
	// dR partly contains international data --> overwrite dR with ifsc data
	'Wettkaempfe' => array('overwrite', 'dr', 'ifsc'),
	'Serien' => array('overwrite', 'dr', 'ifsc'),
	'Results' => array('overwrite', 'dr', 'ifsc'),
	'Feldfaktoren' => array('overwrite', 'dr', 'ifsc'),
	'Routes' => array('overwrite', 'dr', 'ifsc'),
	'RouteResults' => array('overwrite', 'dr', 'ifsc'),
	'RelayResults' => array('overwrite', 'dr', 'ifsc'),
	'RouteHolds' => array('overwrite', 'dr', 'ifsc'),
	// same is true for groups/categories and federations: GER and SUI: state, regionalzentrum and sektions are not in ifsc
	'Gruppen' => array('overwrite', 'dr', 'ifsc'),
	'Federations' => array('overwrite', 'dr', 'ifsc'),
	// licenses: international licenses were removed from dR, before REPLACE INTO ifsc data
	'Licenses' => array('overwrite', 'dr', 'ifsc'),
	// next 5 are identical on dR and ifsc
	'RangListenSysteme' => array('overwrite', 'ifsc', 'dr'),
	'PktSysteme' => array('overwrite', 'ifsc', 'dr'),
	'PktSystemPkte' => array('overwrite', 'ifsc', 'dr'),
	'Displays' => array('overwrite', 'ifsc', 'dr'),
	'DisplayFormats' => array('overwrite', 'ifsc', 'dr'),
	// athletes is most tricky, therefore we use an own method doing real merging
	'Personen' => array('merge_athletes'),
	'Gruppen2Personen' => array('overwrite', 'dr', 'ifsc'),
	// federations athlets are belonging to need to come with priority from dr database, to keep sektions!
	'Athlete2Fed' => array('overwrite', 'ifsc', 'dr'),
) as $table => $params)
{
	$func = $params[0];
	$params[0] = $table;

	call_user_func_array($func, $params);
}

/**
 * Overwrites data of $table from $initial db with data from $overwrite_with database
 *
 * @global string $mysqldump
 * @global string $mysql
 * @param string $table
 * @param string $inital
 * @param string $overwrite_with
 */
function overwrite($table, $inital='dr', $overwrite_with='ifsc')
{
	error_log(__METHOD__."('$table', '$inital', '$overwrite_with')");
	global $mysqldump, $mysql, $target;

	foreach(array(
		$mysqldump.' '.$GLOBALS[$inital.'_db'].' '.$table.'|'.$mysql.' '.$target,
		$mysqldump.' --no-create-info --replace '.$GLOBALS[$overwrite_with.'_db'].' '.$table.'|'.$mysql.' '.$target,
	) as $cmd)
	{
		error_log($cmd);
		$error = null;
		passthru($cmd, $error);
		if ($error) die('Error running: '.$cmd);
		sleep(1);	// give Galera cluster time to sync, to fix deadlock
	}
}

/**
 * Merge athletes tables:
 * - PerId is unique, both contain athletes not in the other one, but also athletes which are in both
 * - coalesc data with priority on dR for GER and SUI and ifsc for all other
 * - make rkeys unique, they are not neccessarly AND copy pictures accordingly
 *
 * @param string $table
 */
function merge_athletes($table='Personen')
{
	global $dr_db, $ifsc_db, $target, $athlete_def;
	error_log(__METHOD__);
	$dr_athletes = athlete_generator($dr_db, $table);
	$ifsc_athletes = athlete_generator($ifsc_db, $table);
	$dr_athlete = $ifsc_athlete = null;

	$target_db = db($target);
	$target_db->exec('TRUNCATE '.$table);

	$athlete = array();
	$columns = array_keys($athlete_def);
	$stmt = $target_db->prepare('INSERT INTO '.$table.' ('.implode(',', $columns).
		') VALUES (:'.implode(',:', $columns).')');

	// iterate over all dr and ifsc athletes sorted by PerId ascending
	while((($dr_athlete = $dr_athletes->current()) || true) &&
		(($ifsc_athlete = $ifsc_athletes->current()) || true) &&
		(!empty($dr_athlete) || !empty($ifsc_athlete)))
	{
		$athlete = empty($dr_athlete) ? $ifsc_athlete :
			(empty($ifsc_athlete) ? $dr_athlete :
				($dr_athlete['PerId'] < $ifsc_athlete['PerId'] ? $dr_athlete : $ifsc_athlete));

		// do we need to do a real merge from dr & ifsc athlete
		if (!empty($dr_athlete) && $dr_athlete['PerId'] == $athlete['PerId'] &&
			!empty($ifsc_athlete) && $ifsc_athlete['PerId'] == $athlete['PerId'])
		{
			foreach($columns as $col)
			{
				switch($col)
				{
					case 'rkey':
						if ($dr_athlete['rkey'] != $ifsc_athlete['rkey'])
						{
							if ((int)$ifsc_athlete['rkey'] <= (int)$dr_athlete['rkey'])
							{
								$athlete['rkey'] = $ifsc_athlete['rkey'];	// prever ifsc rkey
							}
							else
							{
								$athlete['rkey'] = check_rkey($athlete['PerId'], $dr_athlete['rkey']);
							}
						}
						break;

					case 'modified':
						if ($dr_athlete['modified'] > $ifsc_athlete['modified'])
						{
							$athlete['modified'] = $dr_athlete['modified'];
							$athlete['modifier'] = $dr_athlete['modifier'];
						}
						else
						{
							$athlete['modified'] = $ifsc_athlete['modified'];
							$athlete['modifier'] = $ifsc_athlete['modifier'];
						}
					case 'modifier':
						break;	// already copied above

					default:
						if (empty($dr_athlete[$col]))
						{
							$athlete[$col] = $ifsc_athlete[$col];
						}
						// if both contain data: prever dr data for GER or SUI athletes, ifsc data for others
						elseif (!empty($ifsc_athlete[$col]))
						{
							$athlete[$col] = in_array($ifsc_athlete['nation'], array('GER','SUI')) ?
								$dr_athlete[$col] : $ifsc_athlete[$col];
						}
						else
						{
							$athlete[$col] = $dr_athlete[$col];
						}
				}
			}
		}
		// make sure rkey does not clash
		elseif(!empty($dr_athlete) && $dr_athlete['PerId'] == $athlete['PerId'])
		{
			$athlete['rkey'] = check_rkey($athlete['PerId'], $athlete['rkey']);
		}

		// athlete now merged in $athlete and can be inserted into $target db
		unset($athlete['nation']);	// not part of Personen table
		$stmt->execute($athlete);

		// copy athlete pictures to $target_dir
		copy_pictures($athlete['rkey'], !empty($dr_athlete) && $dr_athlete['PerId'] == $athlete['PerId'] ? $dr_athlete['rkey'] : null,
			!empty($ifsc_athlete) && $ifsc_athlete['PerId'] == $athlete['PerId'] ? $ifsc_athlete['rkey'] : null);

		// if used advance dr_ and ifsc_athlete
		if (!empty($dr_athlete) && $dr_athlete['PerId'] == $athlete['PerId']) $dr_athletes->next();
		if (!empty($ifsc_athlete) && $ifsc_athlete['PerId'] == $athlete['PerId']) $ifsc_athletes->next();
	}
}

/**
 * Copy pictures to new target dir and rkey, prevering newer pictures
 *
 * @global string $target_dir
 * @global string $dr_dir
 * @global string $ifsc_dir
 * @param string $rkey
 * @param string $dr_rkey
 * @param string $ifsc_rkey
 */
function copy_pictures($rkey, $dr_rkey, $ifsc_rkey)
{
	global $target_dir, $dr_dir, $ifsc_dir;

	foreach(array('', '-2') as $postfix)
	{
		$dr_path = $dr_dir.'/'.$dr_rkey.$postfix.'.jpg';
		$ifsc_path = $ifsc_dir.'/'.$ifsc_rkey.$postfix.'.jpg';
		$target_path = $target_dir.'/'.$rkey.$postfix.'.jpg';
		if ((empty($dr_rkey) || !file_exists($dr_path)) && (empty($ifsc_rkey) || !file_exists($ifsc_path)))
		{
			//error_log(__METHOD__."(target='$rkey', dr='$dr_rkey', ifsc='$ifsc_rkey') no picture found in $dr_path or $ifsc_path!");
			continue;
		}
		elseif (empty($ifsc_rkey) || !file_exists($ifsc_path) || file_exists($dr_path) && filemtime($dr_path) > filemtime($ifsc_path))
		{
			error_log(__METHOD__."(target='$rkey', dr='$dr_rkey', ifsc='$ifsc_rkey') copy($dr_path, $target_path)!");
			copy($dr_path, $target_path);
		}
		else
		{
			error_log(__METHOD__."(target='$rkey', dr='$dr_rkey', ifsc='$ifsc_rkey') copy($ifsc_path, $target_path)!");
			copy($ifsc_path, $target_path);
		}
	}
}

/**
 * Check if given $rkey does not exist (for a different PerId) in ifsc or target db, otherwise generate a new unique one
 *
 * @param int $PerId
 * @param string $rkey
 */
function check_rkey($PerId, $rkey)
{
	global $ifsc_db, $target;
	static $stmt = null;
	if (!isset($stmt))
	{
		$stmt = db($ifsc_db)->prepare('SELECT COUNT(*)+(SELECT COUNT(*)'.
			' FROM '.$target.'.Personen WHERE rkey=:rkey AND PerId!=:PerId)'.
			' FROM Personen WHERE rkey=:rkey AND PerId!=:PerId');
	}
	// $rkey exists
	if ($stmt->execute(array(
		'rkey'  => $rkey,
		'PerId' => $PerId,
	)) && $stmt->fetchColumn())
	{
		for($i=2; $i < 999; ++$i)
		{
			$rkey = substr($rkey, 0, 6).$i;
			if ($stmt->execute(array(
				'rkey'  => $rkey,
				'PerId' => $PerId,
			)) && !$stmt->fetchColumn())
			{
				error_log(__METHOD__."($PerId, ...) changed rkey to '$rkey'");
				break;
			}
		}
	}
	//else error_log(__METHOD__."($PerId, '$rkey') rkey is unique :-)");

	return $rkey;
}

/**
 * Return mysqli object for given $db
 *
 * @global string $host
 * @global string $user
 * @global string $password
 * @global int $port
 * @staticvar array $dbs
 * @param type $db
 * @return \PDO
 */
function db($db)
{
	global $host, $user, $password, $port;
	static $dbs = array();
	if (!isset($dbs[$db]))
	{
		$dbs[$db] = new \PDO("mysql:host=$host;port=$port;dbname=$db;charset=UTF8", $user, $password, array(
				\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION,
		));
	}
	return $dbs[$db];
}

/**
 * Generator returning all athlets of a given $db
 *
 * @param string $db
 * @param string $table
 * @return Generator<array>
 */
function athlete_generator($db, $table='Personen')
{
	$min = 0;
	$db_obj = db($db);
	$stmt = $db_obj->prepare("SELECT SQL_CALC_FOUND_ROWS $table.*,Federations.nation FROM ".$table.
		" JOIN Athlete2Fed ON $table.PerId=Athlete2Fed.PerId AND Athlete2Fed.a2f_end=9999".
		' JOIN Federations USING(fed_id)'.
		" WHERE $table.PerId > :PerId ORDER BY $table.PerId ASC LIMIT 100");
	$stmt->setFetchMode(\PDO::FETCH_ASSOC);

	while($stmt->execute(array(
		'PerId' => $min,
	)) && $db_obj->query('SELECT FOUND_ROWS()')->fetchColumn())
	{
		foreach($stmt as $row)
		{
			$min = (int)$row['PerId'];
			yield $row;
		}
	}
}
