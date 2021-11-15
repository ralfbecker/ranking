<?php
/**
 * EGroupware digital ROCK Rankings - import UI
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2008-19 by Ralf Becker <RalfBecker@digitalrock.de>
 */

use EGroupware\Api;
use EGroupware\Ranking\Base;

class ranking_import extends ranking_result_bo
{
	/**
	 * @var array $public_functions functions callable via menuaction
	 */
	var $public_functions = [
		'index' => true,
	];

	/**
	 * Import columns, first array value is the label
	 *
	 * @var array
	 */
	static $import_columns = array(
		'rank'     => ['rank','platz','rang','place'],
		'nachname' => ['name','nachname','lastname'],
		'vorname'  => ['firstname','vorname','surname'],
		'nation'   => ['nation','land','match' => 'exact'],
		'strasse'  => ['street','strasse'],
		'ort'      => ['city','ort','stadt','label'],
		'plz'      => ['postalcode','zip','postcode','plz'],
		'sex'      => ['gender','geschlecht','sex'],
		'geb_date' => ['birthdate','geburtstag','jahrgang','agegroup'],
		'verband'  => ['federation','verband','sektion'],
		'PerId'    => ['id','athlete'],
		'tel'      => ['tel', 'telefon', 'telephone', 'phone'],
		'mobil'    => ['mobile', 'mobilephone', 'cellphone'],
		'fax'      => ['fax', 'telefax'],
		'email'    => ['email', 'e-mail', 'mail'],
		'homepage' => ['homepage', 'www', 'url'],
		'anrede'   => ['anrede', 'title'],
		// no need as only ranking import 'result'   => ['result','ergebnis'],
		'coach'    => ['coach', 'betreuer'],
		'coach-email' => ['coach-email', 'coach email', 'betreuer-email'],
	);

	/**
	 * Coach GrpId by 3-char nation
	 * @var int[]
	 */
	static $nat_coach_cats = ['GER' => 213];

	/**
	 * Import a csv file
	 *
	 * @param array $content =null
	 * @param string $msg =''
	 */
	function index($content=null,$msg='')
	{
		// fail hard if no rights at all / just URL called
		if (empty($this->edit_rights) && empty($this->athlete_rights) && empty($this->register_rights))
		{
			throw new EGroupware\Api\Exception\NoPermission();
		}
		$tmpl = new Api\Etemplate('ranking.import');

		$config = Api\Config::read('ranking');
		$import_url = $config['import_url'];

		//_debug_array($content);
		if (!is_array($content))
		{
			$content = array('keys' => Api\Cache::getSession('ranking', 'import'));
			if ($_GET['calendar']) $content['keys']['calendar'] = $_GET['calendar'];
			if ($_GET['comp']) $content['keys']['comp'] = $_GET['comp'];
			if ($_GET['cat']) $content['keys']['cat'] = $_GET['cat'];
			if (is_numeric($_GET['route'])) $content['keys']['route'] = $_GET['route'];
			if ($_GET['msg']) $msg = $_GET['msg'];
			$content['import'] = Api\Cache::getSession('ranking', 'pending_import');

			if ($_GET['row'] && is_array($_GET['row']))
			{
				foreach($_GET['row'] as $n => $id)
				{
					if(isset($content['import'][$n]))
					{
						$content['import']['athlete'][$n] = $id;
						$content['button'] = ['detect' => true];
					}
				}
			}
		}
		if (is_array($content['import']['as']))
		{
			array_walk($content['import']['as'], static function(&$val)
			{
				$val = $val['as'];
			});
		}
		if($content['keys']['comp']) $comp = $this->comp->read($content['keys']['comp']);
		//echo "<p>calendar='$calendar', comp={$content['keys']['comp']}=$comp[rkey]: $comp[name]</p>\n";
		if ($this->only_nation)
		{
			$calendar = $this->only_nation;
			$tmpl->disableElement('keys[calendar]');
		}
		elseif ($comp && !$content['keys']['calendar'])
		{
			$calendar = $comp['nation'] ? $comp['nation'] : 'NULL';
		}
		elseif ($content['keys']['calendar'])
		{
			$calendar = $content['keys']['calendar'];
		}
		else
		{
			$calendar = key($this->ranking_nations);
		}
		if (!$comp || ($comp['nation'] ?: 'NULL') !== $calendar)
		{
			//echo "<p>calendar changed to '$calendar', comp is '$comp[nation]' not fitting --> reset </p>\n";
			$comp = $cat = false;
			$content['keys']['route'] = '';	// dont show route-selection
		}
		if ($comp && (!($cat = $content['keys']['cat']) || (!($cat = $this->cats->read($cat)) || !in_array($cat['rkey'],$comp['gruppen']))))
		{
			$cat = false;
			$content['keys']['route'] = '';	// dont show route-selection
		}
		$keys = [
			'WetId' => $comp['WetId'],
			'GrpId' => $cat['GrpId'],
			'route_order' => $content['keys']['route'] < 0 ? -1 : $content['keys']['route'],
		];
		if ($comp && ($content['keys']['old_comp'] != $comp['WetId'] ||		// comp changed or
			$cat && ($content['keys']['old_cat'] != $cat['GrpId'] || 			// cat changed or
			!($route = $this->route->read($keys)))))	// route not found and no general result
		{
			if (is_numeric($keys['route_order'])) $route = $this->route->read($keys);
		}
		$this->set_ui_state($calendar,$comp['WetId'],$cat['GrpId']);

		if ($content['button'] || is_array($content['file']))
		{
			$button = key($content['button']) ?: 'upload';
			unset($content['button']);
			try {
				switch($button)
				{
					case 'upload':
						$content['import'] = $this->do_upload($content['file']['tmp_name'],
							$content['charset'], $content['delimiter'], $calendar, $cat);
						$msg = lang('%1 lines read from file.',count($content['import'])-4);	// -1 because of 'as' key
						break;
					case 'cancel':
						unset($content['import']);
						break;
					case 'import':
						$msg = lang('%1 athletes imported.',
							$this->do_import($content['import'], $content['keys'], $cat, $content['add_missing'],
								$content['license'], $this->license_year));
						break;
					case 'apply':
						$this->detect_athletes($content['import'], $content['import']['as'], $calendar, $cat, $content['license'], $this->license_year);
						break;
					case 'url':
						$msg = $this->from_url($comp, $content['keys']['cat'] ? $cat['rkey'] : null, $content['quali_type'],
							$content['comp2import'], $import_url);
						break;
				}
			}
			catch (Exception $e) {
				Api\Framework::message($e->getMessage(), 'error');
			}
		}
		// make some per default disabled ui elements visible:
		if ($content['import'])
		foreach($content['import'] as $n => &$row)
		{
			if ($n < 2) continue;
			foreach($row as $c => $col)
			{
				if ($content['import']['detection'][$n][$c] == 'conflictData')	// replace checkbox for conflict data
				{
					$readonlys['import']['replace'][$n][$c] = false;
				}
			}
			if ($content['import']['detection'][$n]['row'] == 'noAthlete')	// add button for new athletes
			{
				$readonlys['import']["add[$n]"] = false;
				$readonlys['import']["edit[$n]"] = true;
				$row['presets'] = '';
				foreach($content['import']['as'] as $c => $name)
				{
					if ($name && !in_array($name, ['PerId','rank','result']) && $row[$c])
					{
						$row['presets'] .= '&preset['.$name.']='.
							$row[$c].($name === 'geb_date' && is_numeric($row[$c]) ? '-01-01' : '');
					}
				}
				if ($calendar && $calendar != 'NULL' && !in_array('nation',$content['import']['as']))
				{
					$row['presets'] .= '&preset[nation]='.$calendar;
				}
				if ($cat['sex'])
				{
					$row['presets'] .= '&preset[sex]='.$cat['sex'];
				}
				$row['presets'] .= '&row='.$n;
			}
		}
		// get federation grants
		$athlete = $nations = $feds_with_grants = [];
		try {
			$this->presetFederation($athlete, $nations, $feds_with_grants);
		}
		catch (\Exception $e) {
			$feds_with_grants = [];
		}
		$sel_options = array(
			'delimiter' => [';' => ';',',' => ',','\t' => 'Tab'],
			'charset' => ['utf-8' => 'UTF-8','iso-8859-1' => 'ISO-8859-1'],
			'calendar' => $this->ranking_nations,
			'comp'     => $this->comp->names(array(
				'nation' => $calendar,
				'datum < '.$this->db->quote(date('Y-m-d',time()+100*24*3600)),	// starting days from now
				'datum > '.$this->db->quote(date('Y-m-d',time()-365*24*3600)),	// until one year back
				'gruppen IS NOT NULL',
			),3,'datum DESC'),
			'cat'      => $this->cats->names(['rkey' => $comp['gruppen']],0),
			'route'    => ($import_url ? [
				'url' => 'Import URL',
			] : []
			)+[
				'athletes' => 'Athletes',
			]+($comp && $cat ? [
				'registration' => 'Registration',
				'ranking'  => 'Ranking',
			]/* disabling result-service import for now
 			+(($routes = $this->route->query_list('route_name','route_order',[
				'WetId' => $comp['WetId'],
				'GrpId' => $cat['GrpId'],
			],'route_order DESC')) ? $routes : [])*/ : []),
			'quali_type' => $this->quali_types,
			'license' => $this->license_labels,
			'fed_id' => !$calendar ? array(lang('Select a nation first')) :
				$this->athlete->federations($calendar,false,$feds_with_grants ? ['fed_id' => $feds_with_grants] : [])
		);
		// show coach* columns only for registration
		if ($content['route'] !== 'registration')
		{
			unset($sel_options['route']['coach'], $sel_options['route']['coach-email']);
		}
		if (count($sel_options['fed_id']) === 1)
		{
			$keys['fed_id'] = key($sel_options['fed_id']);
		}
		// do not allow to unset, confirm or suspend licenses, unless for admins
		if (empty($GLOBALS['egw_info']['user']['apps']['admin']))
		{
			// apply for license only if athlete rights
			if (empty($this->athlete_rights))
			{
				$sel_options['license'] = [];
				$readonlys['license'] = true;
			}
			else
			{
				$sel_options['license'] = ['a' => $sel_options['license']['a']];
			}
		}
		foreach(self::$import_columns as $col => $lables)
		{
			$sel_options['as'][$col] = $lables[0];
		}
		if (is_array($content['import']['as']))
		{
			array_walk($content['import']['as'], static function(&$val)
			{
				$val = ['as' => $val];
			});
		}
		if ($comp && !isset($sel_options['comp'][$comp['WetId']])) $sel_options['comp'][$comp['WetId']] = $comp['name'];

		if (is_array($route)) $content += $route;
		if ($comp && (!isset($content['keys']['old_comp']) || $content['keys']['old_comp'] != $comp['WetId']))
		{
			$content['comp2import'] = $comp['WetId'];
		}
		$content['keys']['calendar'] = $calendar;
		$content['keys']['comp']     = $content['keys']['old_comp']= $comp ? $comp['WetId'] : null;
		$content['keys']['cat']      = $content['keys']['old_cat'] = $cat ? $cat['GrpId'] : null;
		$this->set_ui_state($calendar,$comp['WetId'],$cat['GrpId']);

		// make competition and category data availible for print
		$content['comp'] = $comp;
		$content['cat']  = $cat;

		$content['import_url'] = $import_url;

		if (!empty($msg)) Api\Framework::message($msg);

		Api\Cache::setSession('ranking', 'import', $content['keys']);
		Api\Cache::setSession('ranking', 'pending_import', $content['import']);
		// create a nice header
		$GLOBALS['egw_info']['flags']['app_header'] = /*lang('Ranking').' - '.*/lang('Import').' '.
			($cat ? (isset($sel_options['route'][$content['keys']['route']]) ? $sel_options['route'][$content['keys']['route']].' ' : '').$cat['name'] : '');
		//_debug_array($content);
		return $tmpl->exec('ranking.ranking_import.index', $content, $sel_options, $readonlys, $content);
	}

	/**
	 * Handle the file upload
	 *
	 * @param string $fname
	 * @param string $charset
	 * @param string $delimiter
	 * @param string $nation =null nation to use if not set in imported data
	 * @param ?array $cat =null category to use
	 * @return string success message
	 * @throws Api\Exception\WrongUserinput
	 */
	protected function do_upload($fname, $charset, $delimiter, $nation=null, array $cat=null)
	{
		// do the import
		foreach(array_values(array_unique([$delimiter, ';', ',', "\t"])) as $n => $delimiter)
		{
			try {
				$raw_import = $this->csv_import($fname, $charset, $delimiter);
				break;
			}
			catch (\Exception $e) {
				// try other delimiter
				if ($n === 2 || $e->getCode() === 999) throw $e;
			}
		}
		//_debug_array($raw_import);

		// try detecting column names
		$import = ['as' => $this->detect_columns($raw_import[0],1)];

		foreach($raw_import as $r => &$row)
		{
			foreach($row as $c => &$col)
			{
				if (!$r)
				{
					$import['header'][1+$c] = $col;
				}
				else
				{
					$import[1+$r][1+$c] = $col;
				}
			}
		}
		$this->detect_athletes($import, $import['as'], $nation, $cat);
		//_debug_array($import);
		return $import;
	}

	/**
	 * Import a csv file into an array
	 *
	 * @param string $fname name of uploaded file
	 * @param string $charset ='iso-8859-1' or eg. 'utf-8'
	 * @param string $delimiter =','
	 * @param string $enclosure ='"'
	 * @return array with lines and columns or string with error message
	 * @throw Api\Exception\WrongUserinput with $code 999 for one data-line wrong
	 */
	protected function csv_import($fname,$charset='iso-8859-1',$delimiter=',',$enclosure='"')
	{
		if (!$fname || !file_exists($fname))
		{
			throw new Api\Exception\WrongUserinput(lang('You need to select a file first!'));
		}
		if ($delimiter === '\t') $delimiter = "\t";

		$n = 0;
		$lines = [];
		if (!($fp = fopen($fname,'rb')) || !($labels = fgetcsv($fp,null,$delimiter,$enclosure)) || count($labels) <= 1)
		{
			throw new Api\Exception\WrongUserinput(lang('Error: no line with column names, eg. wrong delemiter'));
		}
		$lines[$n++] = Api\Translation::convert($labels,$charset);

		while (($line = fgetcsv($fp,null,$delimiter)))
		{
			if (count($line) != count($labels))
			{
				//_debug_array($labels); _debug_array($line);
				throw new Api\Exception\WrongUserinput(lang('Dataline %1 has a different number of columns (%2) then labels (%3)!',$n,count($line),count($labels)), 999);
			}
			$lines[$n++] = Api\Translation::convert($line,$charset);
		}
		fclose($fp);

		return $lines;
	}

	/**
	 * detect the columns
	 *
	 * @param array $headers
	 * @param int $first =1 number of first column
	 * @return array column number => name pairs
	 */
	protected function detect_columns(array $headers,$first=1)
	{
		$columns = [];
		foreach($headers as $c => $header)
		{
			foreach(self::$import_columns as $col => $labels)
			{
				foreach($labels as $label)
				{
					if (!strcasecmp($header,$label) || !strcasecmp($header,lang($label)))
					{
						$columns[$first+$c] = $col;
						//echo "found: $col for $header<br />\n";
					}
				}
			}
		}
		//_debug_array($columns);
		return $columns;
	}

	/**
	 * detect athletes from current columns
	 *
	 * @param array &$import result in $import['athlete']
	 * @param array $col2name column number => name pairs
	 * @param string $nation =null nation to use if not set in imported data
	 * @param ?array $cat =null should we only search for a certain category
	 * @param string $license =null 'a'=applied, 'c'=confirmed, 's'=suspended license status to set for not empty license field
	 * @param int $license_year =null default this->license_year
	 */
	protected function detect_athletes(array &$import, array $col2name, $nation=null, array $cat=null, $license=null, $license_year=null)
	{
		if ($nation == 'NULL') $nation = null;
		if (is_null($license_year) || !$license_year) $license_year = $this->license_year;

		$firstname_col = array_search('vorname', $col2name);
		$lastname_col  = array_search('nachname',$col2name);
		$nation_col    = array_search('nation',  $col2name);
		$id_col        = array_search('PerId',   $col2name);
		$sex_col       = array_search('sex',     $col2name);
		$birth_col     = array_search('geb_date',$col2name);
		$coach_col     = array_search('coach',   $col2name);
		$coach_email_col= array_search('coach-email', $col2name);
		//error_log(__METHOD__."(,".array2string($col2name).",$nation,$cat[sex]) firstname_col=$firstname_col, lastname_col=$lastname_col, nation_col=$nation_col, id_col=$id_col");

		$import['detection'] = [];
		$detection =& $import['detection'];
		foreach($import as $n => &$data)
		{
			if ($n < 2 ) continue;	// not an athlete col

			$nat = $nation_col && $data[$nation_col] ? $data[$nation_col] : $nation;

			// do some field-specific conversation
			foreach($col2name as $c => $name)
			{
				if (!$name || !$data[$c]) continue;

				switch($name)
				{
					case 'geb_date':
						if (!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/',$data[$c]))
						{
							if (is_numeric($data[$c]))
							{
								$data[$c] .= '-01-01';
							}
							else
							{
								$data[$c] = preg_replace(array(
									'/^([0-9]{2}).([0-9]{2}).([0-9]{4})$/',		// DD.MM.YYYY
									'/^([0-9]{2})\/([0-9]{2})\/([0-9]{4})$/',	// MM/DD/YYYY
									'/^([0-9]{2})-([0-9]{2})-([0-9]{4})$/',		// DD-MM-YYYY
								),[
									'\\3-\\2-\\1',
									'\\3-\\1-\\2',
									'\\3-\\2-\\1',
								],$data[$c]);
							}
						}
						break;
					case 'verband':
						switch($nat)
						{
							case 'GER':
								$data[$c] = preg_replace(array('/e\. ?V\. ?/i','/^(Deutscher Alpenverein|Alpenverein|DAV|Sektion|Se.) ?(.*)$/i'),
									['','Deutscher Alpenverein \\2'],$data[$c]);
								break;
							case 'SUI':
								$data[$c] = preg_replace('/^(Schweizer Alpen[ -]*Club|SAC|Club Alpin Suisse|CAS|Sektion|Se.) ?(.*)$/i','Schweizer Alpen Club \\2',$data[$c]);
								break;
						}
						break;
					case 'license':
						if ($license)
						{
							$data[$c] = $license.': '.lang($this->license_labels[$license]);
						}
						break;
					case 'sex':
						$data[$c] = strtolower($data[$c][0]) === 'm' ? 'male' : 'female';
						break;
				}
			}
			if (!empty($cat['sex']) && !empty($sex_col) && $cat['sex'] !== $data[$sex_col] ||
				!empty($birth_col) && $this->in_agegroup($data[$birth_col], $cat))
			{
				$detection[$n]['row'] = 'ignore';
			}

			if ($import['athlete'][$n] && !is_array($import['athlete'][$n]) && $id_col)
			{
				$data[$id_col] = $import['athlete'][$n];
			}
			if ($id_col && $data[$id_col])
			{
				$import['athlete'][$n] = $data[$id_col];
			}
			$firstname = $data[$firstname_col];
			$lastname = $data[$lastname_col];

			// allow to only have a (last)name column, with "last, first" or "first last"
			if (!$firstname_col)
			{
				if (strpos($lastname,',') !== false)
				{
					list($lastname,$firstname) = preg_split('/, ?/',$lastname);
				}
				else
				{
					$parts = explode(' ',$lastname);
					$lastname = array_pop($parts);
					$firstname = implode(' ',$parts);
				}
			}
			$criteria = [
				'vorname'  => $firstname,
				'nachname' => $lastname,
				'nation'   => $nation_col && $data[$nation_col] ? $data[$nation_col] : $nation,
			];
			if (!empty($cat['sex'])) $criteria['sex'] = $cat['sex'];

			if ($lastname_col && $data[$firstname_col] && $data[$lastname_col] &&
				($import['athlete'][$n] && !is_array($import['athlete'][$n]) &&
					($athlete = $this->athlete->read($import['athlete'][$n], '', $license_year, $nation)) ||
				($athletes = $this->athlete->search($criteria,false,'','','',false,'AND',false,[
					'license_nation' => $nation,
					'license_year' => $license_year,
					'license_year' => $license_year,
				])) && count($athletes) == 1 && ($athlete=$athletes[0])))	// dont autodetect multiple matches!
			{
				$import['athlete'][$n] = $athlete['PerId'];
				foreach($col2name as $c => $name)
				{
					if (!$name || $data[$c] == $athlete[$name] ||	// ignored column or existing data equal
						$name == 'license' && $data[$c][0] == $athlete[$name])	// compare only first char for license
					{
						continue;
					}
					// a not existing federation always need explicit confirmation, before it get's created!
					if ($name == 'verband' && !($this->federation->get_federation($data[$c],$nation_col?$data[$nation_col]:$nation)))
					{
						$detection[$n][$c] = 'conflictData';
						$detection[$n]['help-'.$c] = ($athlete[$name] ? lang('Replace: %1',$athlete[$name]).', ' : '').
							(lang('Create new federation: %1 (%2)',$data[$c],$nation_col&&$data[$nation_col]?$data[$nation_col]:$nation));
					}
					elseif ($name == 'rank')
					{
						// exclude rang, it's not stored in athlete
					}
					elseif (substr($name, 0, 5) === 'coach' && !empty($data[$c]))
					{

					}
					elseif ($data[$c] && (!$athlete[$name] || // no data stored for athlete
						$name == 'geb_date' && $athlete[$name] == (int)$data[$c].'-01-01' ||	// birthday replaces birthyear
						$name == 'verband' && substr($data[$c],0,strlen($athlete[$name])) == $athlete[$name]))	// fed name starts identical
					{
						$detection[$n][$c] = 'newData';
					}
					elseif($athlete[$name] && $data[$c] && $athlete[$name] != $data[$c])
					{
						$detection[$n][$c] = 'conflictData';
						$detection[$n]['help-'.$c] = lang('replace: %1',$athlete[$name]);
					}
					//echo "$n: data[$c]={$data[$c]}, athlete[$name]='{$athlete[$name]}': {$detection[$n][$c]}<br />\n";
				}
			}
			else
			{
				if ($lastname_col)
				{
					$import['athlete'][$n] = array('query' => (!empty($cat['sex']) ? strtoupper($cat['sex'][0]).': ' : '').($criteria['nation'] ? $criteria['nation'].': ' : '').
						($firstname_col ? $data[$firstname_col].' ' : '').$data[$lastname_col]);	// otherwise row does not show (autorepeat)
				}
				elseif (substr($name, 0, 5) === 'coach' && !empty($data[$c]))
				{

				}
				else
				{
					$import['athlete'][$n] = '';	// otherwise row does not show (autorepeat)
				}
				if (empty($detection[$n]['row']))	// do NOT overwrite ignore
				{
					$detection[$n]['row'] = 'noAthlete';
				}
			}
			if ($id_col) $data[$id_col] = $import['athlete'][$n];

			// do we need to import coaches too
			if ($coach_col !== false && !empty($data[$coach_col]) || $coach_email_col !== false && !empty($data[$coach_email_col]))
			{
				if ($detection[$n]['row'] === 'ignore')
				{
					unset($detection[$n][$coach_col ?: $coach_email_col]);
				}
				elseif (!($coach = $this->find_coach($data[$coach_col], $data[$coach_email_col], $athlete[$n])))
				{
					$detection[$n][$coach_col ?: $coach_email_col] = 'conflictData';
					$detection[$n]['help-'.($coach_col ?: $coach_email_col)] = lang('add coach: %1', $data[$coach_col ?: $coach_email_col]);
				}
				else
				{
					$data['coach-id'] = $coach;
				}
			}
		}
		//_debug_array($import['athlete']);
		//_debug_array($detection);
	}

	/**
	 * Detect and split "Name <Email>"
	 */
	const RFC822_EMAIL_PREG = '/^(.*?)\s+<([a-z0-9._-]+@[a-z0-9._-]+)>$/i';

	/**
	 * Find coach for registration
	 *
	 * @param ?string $coach coach-name or rfc822 address "Name <email>"
	 * @param ?string $coach_email coach-email or rfc822 address "Name <email>"
	 * @param ?int $athlete PerId of athlete, to not pick athlete, in case they share the email
	 * @return ?int PerId of coach, if existing, or NULL
	 */
	protected function find_coach($coach=null, $coach_email=null, int $athlete=null)
	{
		if (!empty($coach) && !empty($coach_email))
		{

		}
		// only an RFC822 address in $coach
		elseif (empty($coach_email) && preg_match(self::RFC822_EMAIL_PREG, $coach, $matches) ||
			preg_match(self::RFC822_EMAIL_PREG, $coach_email, $matches))
		{
			$coach_email = $matches[2];
		}
		if (!empty($coach_email))
		{
			$where = ['email' => $coach_email, 'sex' => ''];
		}
		else
		{
			$where = ["CONCAT(vorname,' ',nachname)=".$this->db->quote($coach), 'sex' => ''];
		}
		if (!empty($athlete))
		{
			$where[] = 'PerId<>'.(int)$athlete;
		}
		// prefer no birthdate or older ones for coaches, as the email might be used multiple times
		if (($coach = $this->athlete->search('', true, 'ORDER BY geb_date IS NULL DESC,geb_date ASC',
			'', '', '', 'AND', [0, 1], $where)))
		{
			return (int)$coach[0]['PerId'];
		}
		return null;
	}

	/**
	 * Import the data in $import into a competition, category and route specified in keys or just the athletes
	 *
	 * @param array $import athlete rows with numerical id's starting with 2, plus values for 'as' and 'athlete' keys
	 * @param array $keys values for keys 'comp', 'cat', 'route' (0..N, 'athlete', 'registration' or 'ranking')
	 * @param array $cat =null should we only search for a certain category
	 * @param bool $add_missing =false true: add missing athletes
	 * @param string $license =null 'a'=applied, 'c'=confirmed, 's'=suspended license status to set for not empty license field
	 * @param string $license_nation =null nation of the license
	 * @param int $license_year =null default this->license_year
	 * @return string|int success-message of result import and/or number of imported athletes
	 * @throw \Exception on error
	 */
	protected function do_import(array &$import, array $keys, array $cat=null, bool $add_missing=false, $license=null, $license_nation=null, $license_year=null)
	{
		if (empty($license_year)) $license_year = $this->license_year;

		$imported = 0;
		$col2name =& $import['as'];
		$nation_col = array_search('nation',$col2name);
		$athletes =& $import['athlete'];
		$detection =& $import['detection'];
		$update =& $import['update'];
		$rank_col = array_search('rank', $col2name);
		// result import selected
		if (!empty($keys['comp']) && !empty($keys['cat']) && (is_numeric($keys['route']) || $keys['route'] === 'ranking'))
		{
			if (empty($rank_col))
			{
				throw new Api\Exception\WrongUserinput(lang('You need to select a rank column to import a result!'));
			}
			$do_result_import = true;
		}
		$result = [];
		foreach($import as $n => &$row)
		{
			if ($n < 2 || $detection[$n]['row'] === 'ignore') continue;	// not an athlete row or to ignore

			$this->athlete->init();

			if (!$athletes[$n] || is_array($athletes[$n]) || !($athlete = $this->athlete->read($athletes[$n])))
			{
				if ($add_missing)
				{
					$athlete = ['sex' => $cat['sex']];
				}
				elseif ($do_result_import)
				{
					throw new Api\Exception\WrongUserinput(lang('No result imported, need to select ALL athletes first!'));
				}
				else
				{
					continue;
				}
			}
			$need_update = !empty($license);
			foreach($col2name as $c => $name)
			{
				if ($add_missing && !isset($athletes[$n]) && $name !== 'PerId' ||
					$detection[$n][$c] && (!isset($update[$n][$c]) || $update[$n][$c]))
				{
					$need_update = true;
					// extra handling for federations, athletes store only fed_id, they get only created, if they are explicitly marked for update!
					switch ($name)
					{
						case 'verband':
							if (!($athlete['fed_id'] = $this->federation->get_federation($row[$c],$nation_col?$row[$nation_col]:null,true)) &&
								// only allow admins to create new federations
								$this->is_admin && $update[$n][$c] && $row[$nation_col])
							{
								$this->federation->init(['verband' => $row[$c]]);
								if ($this->federation->save($nation_col && $row[$nation_col] ? ['nation' => $row[$nation_col]] : null) == 0)
								{
									$athlete['fed_id'] = $this->federation->data['fed_id'];
								}
							}
							break;
						default:
							$athlete[$name] = $row[$c];
							break;
					}
				}
			}
			if ($need_update)
			{
				// set a default nation & federation otherwise the athlete is not visible
				if (empty($athlete['fed_id']) && !empty($keys['fed_id']))
				{
					$athlete['fed_id'] = $keys['fed_id'];
				}
				// check missing federation or no rights to create/update athlete --> abort
				if (empty($athlete['fed_id']) || !$this->acl_check_athlete($athlete))
				{
					// show already imported athletes
					if ($n > 2) $this->detect_athletes($import, $col2name, $keys['calendar'], $cat, $license, $license_year);
					throw new Api\Exception\WrongUserinput(lang('No athlete federation or missing rights to create or update the athlete!'));
				}
				if (empty($athlete['sex']))
				{
					throw new Api\Exception\WrongUserinput(lang('Athlete require a gender, either by selecting a category or having a gender-column!'));
				}
				if ($this->athlete->save($athlete) === 0)
				{
					unset($update[$n]);	// unmark as now updated
					$imported++;
					// new created athletes need to set PerId
					if (empty($athlete['PerId']))
					{
						$athletes[$n] = $this->athlete->data['PerId'];
					}
					// set license if column has a non-empty value
					if (!empty($license) &&
						$this->athlete->set_license($license_year, $license, null, $license_nation) === false)
					{
						throw new Api\Exception\WrongUserinput(lang('Failed to set license for %1 (maybe wrong age-group)!',
							strtoupper($athlete['nachname']).', '.$athlete['vorname']));
					}
				}
			}
			if ($do_result_import)
			{
				$result[$athletes[$n]] = [
					'result_rank' => $row[$rank_col],
					'nation' => $athlete['nation'],
				];
			}

		}
		// run a new detection as that's easier and more consistent
		$this->detect_athletes($import, $col2name, $keys['calendar'], $cat, $license, $license_year);

		if (($keys['route'] === 'registration' || $do_result_import) &&
			!$this->acl_check($keys['calendar'], $keys['route'] === 'registration' ? self::ACL_REGISTER : self::ACL_RESULT, $keys['WetId']))
		{
			throw new Api\Exception\WrongUserinput(lang('Missing rights to import into the selected competition!'));
		}
		// import into registration
		if ($keys['route'] === 'registration')
		{
			$registered = 0;
			foreach($athletes as $n => $PerId)
			{
				if (!empty($PerId) && $import['detection'][$n]['row'] !== 'ignore' &&
					$this->register($keys['comp'], $keys['cat'], $PerId))
				{
					++$registered;
				}
			}
			if (($coaches = $this->import_coaches($import, $keys['comp'], self::$nat_coach_cats[$keys['calendar']], $keys['fed_id'], $add_missing)))
			{
				$import_result = lang('%1 athletes and %2 coaches registerted', $registered, $coaches);
				$this->detect_athletes($import, $col2name, $keys['calendar'], $cat, $license, $license_year);
			}
			else
			{
				$import_result = lang('%1 athletes successful registered', $registered);
			}
		}
		elseif ($do_result_import && is_numeric($keys['route']))
		{
			throw new \Exception('Resultservice import not yet implemented :(');
		}
		// import result into ranking (not result-service!)
		elseif ($do_result_import)
		{
			if (!($import_result = Base::import_ranking([	// $this->import_ranking is from ranking_result_bo!
				'WetId' => $keys['comp'],
				'GrpId' => $keys['cat'],
			], $result)))
			{
				throw new EGroupware\Api\Exception\NoPermission();
			}
		}
		return isset($import_result) ? $import_result.', '.$imported : $imported;
	}

	/**
	 * Register coaches
	 *
	 * @param array $import
	 * @param int $comp comp to register coach for
	 * @param int $cat cat to register coach for
	 * @param int $fed_id federation to register coach for
	 * @param bool $add_all true: add all not-existing coaches
	 * @return int number of registered coaches
	 * @throws Api\Exception\WrongParameter
	 * @throws Api\Exception\WrongUserinput
	 */
	protected function import_coaches(array $import, int $comp, int $cat, int $fed_id, bool $add_all=false)
	{
		$detection =& $import['detection'];
		$update =& $import['update'];

		$coach_col = array_search('coach', $import['as']);
		$coach_email_col = array_search('coach-email', $import['as']);
		if ($coach_col === false && $coach_email_col === false || $fed_id < 1)
		{
			return 0;
		}

		$registered = 0;
		$added_coaches = [];
		foreach($import as $n => &$data)
		{
			if ($n < 2 || $detection[$n]['row'] === 'ignore') continue;	// not an athlete row or to ignore

			// do we need to add the coach first
			if (empty($data['coach-id']) && ($add_all || !isset($update[$n][$coach_col ?: $coach_email_col]) || $update[$n][$coach_col ?: $coach_email_col]))
			{
				if (empty($data[$coach_col]) && empty($data[$coach_email_col])) continue;

				if (empty($data[$coach_col]) && preg_match(self::RFC822_EMAIL_PREG, $data[$coach_email_col], $matches) ||
					preg_match(self::RFC822_EMAIL_PREG, $data[$coach_col], $matches))
				{
					list($firstname, $lastname) = explode(' ', $matches[1], 2);
					$email = $matches[2];
				}
				else
				{
					list($firstname, $lastname) = explode(' ', $data[$coach_col], 2);
					$email = $data[$coach_email_col];
				}
				// make sure to not add coaches multiple times
				if (isset($added_coaches[$email]))
				{
					$data['coach-id'] = $added_coaches[$email];
					++$registered;
					continue;
				}
				$coach = [
					'vorname' => $firstname,
					'nachname' => $lastname,
					'email' => $email,
					'fed_id' => $fed_id,
					'sex' => $this->athlete->gender($firstname, ($federation ?? $federation=$this->federation->read($fed_id))['nation']),
				];
				if (empty($fed_id) || !$this->acl_check_athlete($coach))
				{
					throw new Api\Exception\WrongUserinput(lang('No athlete federation or missing rights to create or update the athlete!'));
				}
				$this->athlete->init($coach);
				if ($this->athlete->save() === 0)
				{
					$data['coach-id'] = $added_coaches[$email] = $this->athlete->data['PerId'];
				}
			}
			if (!empty($data['coach-id']) && $this->register($comp, $cat, $data['coach-id']))
			{
				++$registered;
			}
		}
		return $registered;
	}

	/**
	 * Import results from an other ranking instance via their SiteMgr module
	 *
	 * @param int|string|array $comp WetId, rkey or array of competition to import into
	 * @param array|string $cats =null default all categories
	 * @param int $route_type =null
	 * @param int $comp2import =null default $WetId
	 * @param string $baseurl =null from ranking config "import_url"
	 * @param boolean $add_athletes true=import missing athletes
	 * @param int $set_status =STATUS_RESULT_OFFICAL or STATUS_STARTLIST or STATUS_UNPUBLISHED
	 * @param boolean $import_ranking =true true=import into ranking
	 * @param boolean $only_download =false true: stop after downloading files
	 * @param int $debug =0 debug level: 0: echo some messages while import is running, 2, 3, 4 ... more verbose messages
	 * @param string $charset =null
	 * @return string messages, if run by webserver
	 * @throws Exception on error
	 */
	public function from_url($comp, $cats=null, $route_type=null, $comp2import=null, $baseurl=null,
		$add_athletes=true, $set_status=STATUS_RESULT_OFFICIAL, $import_ranking=true, $only_download=false, $debug=0, $charset='iso-8859-1')
	{
		if (isset($_SERVER['HTTP_HOST']))
		{
			//echo "<pre>\n";
			ob_start();
		}
		$arg = $comp;
		if (!is_array($comp) && !($comp = $this->comp->read(is_numeric($comp) ? ['WetId'=>$comp] : ['rkey'=>$comp])))
		{
			throw new Exception("Competition '$arg' not found!",4);
		}
		if (!$cats)
		{
			$cats = $comp['gruppen'];
		}
		elseif(!is_array($cats))
		{
			$cats = [$cats];
		}
		if (!is_null($debug)) echo $comp['rkey'].': '.$comp['name']."\n";

		if (!$comp2import) $comp2import = $comp['WetId'];

		//echo "comp=".array2string($comp).", cats=".array2string($cats).", route_type=$route_type, comp2import=$comp2import, baseurl=$baseurl\n";
		$detect_route_type = !is_numeric($route_type);

		$cat = $cats[0];

		if (!($ch = curl_init($url=$baseurl.'comp='.$comp2import.'&cat='.$cat.'&route=0')))
		{
			throw new Exception("Error: opening URL '$url'!",5);
		}
		$cookiefile = tempnam('/tmp','importcookies');
		curl_setopt($ch, CURLOPT_COOKIEFILE,$cookiefile);
		curl_setopt($ch, CURLOPT_COOKIEJAR,$cookiefile); # SAME cookiefile
		curl_setopt($ch,CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
		curl_setopt($ch,CURLOPT_HEADER,true);

		// getting the page of the competition and category --> get's us to the general result
		if ($debug > 2) echo "\nGETting $url\n";
		$get = curl_exec($ch);
		//echo substr($get,0,500)."\n\n";
		$exec_id = self::get_exec_id($get, $debug);

		curl_setopt($ch,CURLOPT_POST,true);		// from now on only posts
		foreach($cats as $n => $cat_name)
		{
			if (!($cat = $this->cats->read($cat_name)))
			{
				throw new Exception("Error: Cat '$cat_name' not found!",7);
			}
			echo $cat['rkey'].': '.$cat['name']."\n";

			if ($n)	// changing the cat via a post --> get's us to the general result
			{
				// setting route=0 qualification with a post
				curl_setopt($ch,CURLOPT_URL,$url=$baseurl.'comp='.$comp2import.'&cat='.$cat['GrpId']);
				curl_setopt($ch,CURLOPT_POSTFIELDS,$post=http_build_query(array(
					'etemplate_exec_id' => $exec_id,
					'exec' => array(
						'nm' => [
							'cat' => $cat['GrpId'],
							'show_result' => 1,
							'route' => 0,
							'num_rows' => '999',
						],
					)
				)));
				if ($debug > 2) echo "POSTing $url with $post\n";
				$exec_id = self::get_exec_id($download=curl_exec($ch), $debug);	// switch to route=0 and get new exec-id
				//if ($n) echo $download."\n\n";
			}
			// setting route=0 qualification with a post
			curl_setopt($ch,CURLOPT_URL,$url=$baseurl.'comp='.$comp2import.'&cat='.$cat['GrpId']);
			curl_setopt($ch,CURLOPT_POSTFIELDS,$post=http_build_query(array(
				'etemplate_exec_id' => $exec_id,
				'exec' => array(
					'nm' => [
						'cat' => $cat['GrpId'],
						'show_result' => 1,
						'route' => 0,
						'num_rows' => '999',
					],
				)
			)));
			if ($debug > 2) echo "POSTing $url with $post\n";
			$exec_id = self::get_exec_id($download=curl_exec($ch), $debug);	// switch to route=0 and get new exec-id
			if ($debug > 4) echo $download."\n\n";

			$downloads = $fnames = [];
			for($route=0; $route <= 6; ++$route)
			{
				// download each heat
				curl_setopt($ch,CURLOPT_URL,$url=$baseurl.'comp='.$comp2import.'&cat='.$cat['GrpId']);
				curl_setopt($ch,CURLOPT_POSTFIELDS,$post=http_build_query(array(
					'etemplate_exec_id' => $exec_id,
					'submit_button' => 'exec[button][download]',
					'exec' => array(
						'nm' => [
							'cat' => $cat['GrpId'],
							'show_result' => 1,
							'route' => $route,
							'num_rows' => '999',
						],
					)
				)));
				if ($debug > 2) echo "\nPOSTing $url with $post\n";
				$download = curl_exec($ch);

				$headers = '';
				while (empty($headers) || $headers == 'HTTP/1.1 100 Continue')
				{
					list($headers,$download) = explode("\r\n\r\n",$download,2);
					if ($debug > 3) echo "Headers ".__LINE__.":\n".$headers."\n";
				}
				$matches = null;
				if (!preg_match('/attachment; filename="([^"]+)"/m',$headers,$matches))
				{
					if ($route == 1) continue;	// me might not have a 2. quali
					break;	// no further heat
				}
				$fnames[$route] = $fname = str_replace('/','-',$matches[1]);
				// convert from the given charset to eGW's
				$downloads[$route] = Api\Translation::convert($download, $charset);
				if ($debug > 1) echo "$fname:\n".implode("\n",array_slice(explode("\n",$downloads[$route]),0,4))."\n\n";

				if ($only_download)
				{
					file_put_contents($fname,$downloads[$route]);
				}
			}
			global $download;	// as we use global stream-wrapper
			if (!$only_download) // import
			{
				require_once(EGW_API_INC.'/class.global_stream_wrapper.inc.php');
				// autodetect route_type
				if ($detect_route_type)
				{
					if ($cat['discipline'] == 'speed')
					{
						// no real detection, but currently only bestof is used
						$route_type = TWO_QUALI_BESTOF;
					}
					elseif (!isset($downloads[1]))
					{
						$route_type = ONE_QUALI;
					}
					else
					{
						$content = [
							'WetId' => $comp['WetId'],
							'GrpId' => $cat['GrpId'],
							'route_order' => 0,
						];
						$download = $downloads[0];
						$quali1 = $this->parse_csv($content,fopen('global://download','r'),false,$add_athletes,(int)$comp2import);
						if (!is_array($quali1)) die($quali1."\n");
						$content['route_order'] = 1;
						if (true) $download = $downloads[1];
						$quali2 = $this->parse_csv($content,fopen('global://download','r'),false,$add_athletes,(int)$comp2import);
						if (!is_array($quali2)) die($quali2."\n");
						foreach($quali1 as $n => $athlete)
						{
							foreach($quali2 as $a)
							{
								if ($athlete['PerId'] == $a['PerId']) break 2;
							}
							if ($n > 10) break;
						}
						$route_type = $athlete['PerId'] == $a['PerId'] ? TWO_QUALI_ALL : TWO_QUALI_HALF;
					}
					echo "detected quali-type: $route_type: ".(isset($this->quali_types[$route_type]) ?
						$this->quali_types[$route_type] : $this->quali_types_speed[$route_type])."\n";
				}
				foreach($downloads as $route => $download)
				{
					$content = [
						'WetId' => $comp['WetId'],
						'GrpId' => $cat['GrpId'],
						'route_order' => $route,
					];
					$discipline = null;
					if (!$this->init_route($content,$comp,$cat,$discipline))
					{
						throw new Exception(lang('Permission denied !!!'),9);
					}
					$num_imported = $this->upload($content,fopen('global://download','r'),$add_athletes,(int)$comp2import);

					if (is_numeric($num_imported))
					{
						$need_save = $content['new_route'];
						if (!$route && $route_type)
						{
							$content['route_type'] = $route_type;
							$need_save = true;
						}
						// set number of problems from csv file
						if ($content['route_num_problems'])
						{
							list($line1) = explode("\n",$download);
							for($n = 3; $n <= 8; $n++)
							{
								if (strpos($line1,'boulder'.$n)) $num_problems = $n;
							}
							if ($num_problems && $num_problems != $content['route_num_problems'])
							{
								$content['route_num_problems'] = $num_problems;
								$need_save = true;
							}
						}
						// set the name from the csv file
						$fname = $fnames[$route];
						if (substr($fname,0,strlen($cat['name'])+3) == $cat['name'].' - ' &&
							($name_from_file = str_replace('.csv','',substr($fname,strlen($cat['name'])+3))) != $content['route_name'])
						{
							$content['route_name'] = $name_from_file;
							$need_save = true;
						}
						if ($set_status != $content['route_status'])
						{
							$content['route_status'] = $set_status;
							$need_save = true;
						}
						// save the route, if we set something above
						if ($need_save && $this->route->save($content) != 0)
						{
							throw new Exception(lang('Error: saving the heat!!!'),8);
						}
						echo $fname.': '.lang('%1 participants imported',$num_imported)."\n";
					}
					else
					{
						throw new Exception($num_imported,9);
					}
				}
			}
			if ($import_ranking && $route >= 2)
			{
				$content = [
					'WetId' => $comp['WetId'],
					'GrpId' => $cat['GrpId'],
					'route_order' => -1,
				];
				if (!$this->init_route($content,$comp,$cat,$discipline))
				{
					throw new Exception(lang('Permission denied !!!'),9);
				}
				$content['route_status'] = STATUS_RESULT_OFFICIAL;
				if ($this->route->save($content) != 0)
				{
					throw new Exception(lang('Error: saving the heat!!!'),8);
				}
				echo $this->import_ranking($content)."\n";
			}
		}
		if (isset($_SERVER['HTTP_HOST']))
		{
			return ob_get_clean();
		}
	}

	private static function get_exec_id($html, $debug=null)
	{
		$matches = null;
		if (!preg_match('/name="etemplate_exec_id" value="([^"]+)"/m',$html,$matches))
		{
			throw new Exception("Error: etemplate_exec_id not found!",6);
		}
		if ($debug > 2) echo "etemplate_exec_id='$matches[1]'\n";
		return $matches[1];
	}
}