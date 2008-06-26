<?php
/**
 * eGroupWare digital ROCK Rankings - import UI
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2008 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $$
 */

require_once(EGW_INCLUDE_ROOT.'/ranking/inc/class.boresult.inc.php');
require_once(EGW_INCLUDE_ROOT.'/etemplate/inc/class.uietemplate.inc.php');

class ranking_import extends boresult
{
	/**
	 * @var array $public_functions functions callable via menuaction
	 */
	var $public_functions = array(
		'index' => true,
	);

	/**
	 * Import columns, first array value is the label
	 *
	 * @var array
	 */
	static $import_columns = array(
		'nachname' => array('name','nachname','lastname'),
		'vorname'  => array('firstname','vorname','surname'),
		'nation'   => array('nation','land','match' => 'exact'),
		'strasse'  => array('street','strasse'),
		'ort'      => array('city','ort','stadt','label'),
		'plz'      => array('postalcode','zip','postcode','plz'),
		'sex'      => array('gender','geschlecht','sex'),
		'geb_date' => array('birthdate','geburtstag','jahrgang','agegroup'),
		'verband'  => array('federation','verband','sektion'),
		'PerId'    => array('id','athlete'),
		'license'  => array('license','lizenz'),
		'rank'     => array('rank','platz','rang','place'),
		'result'   => array('result','ergebnis'),
	);

	/**
	 * Import a csv file
	 *
	 * @param array $content=null
	 * @param string $msg=''
	 */
	function index($content=null,$msg='')
	{
		if (!$GLOBALS['egw_info']['user']['apps']['admin'])
		{
			// for eGW 1.4
			$GLOBALS['egw']->framework->render(lang('Admin rights required!'));
			$GLOBALS['egw']->exit();
			// for eGW 1.5+
			//throw new egw_exception_no_permission_admin();
		}
		$tmpl =& new etemplate('ranking.import');

		if ($tmpl->sitemgr && !count($this->ranking_nations))
		{
			return lang('No rights to any nations, admin needs to give read-rights for the competitions of at least one nation!');
		}
		//_debug_array($content);
		if (!is_array($content))
		{
			$content = array('keys' => $GLOBALS['egw']->session->appsession('import','ranking'));
			if ($_GET['calendar']) $content['keys']['calendar'] = $_GET['calendar'];
			if ($_GET['comp']) $content['keys']['comp'] = $_GET['comp'];
			if ($_GET['cat']) $content['keys']['cat'] = $_GET['cat'];
			if (is_numeric($_GET['route'])) $content['keys']['route'] = $_GET['route'];
			if ($_GET['msg']) $msg = $_GET['msg'];
			$content['import'] = $GLOBALS['egw']->session->appsession('pending_import','ranking');

			if ($_GET['row'] && is_array($_GET['row']))
			{
				foreach($_GET['row'] as $n => $id)
				{
					if(isset($content['import'][$n]))
					{
						$content['import']['athlete'][$n] = $id;
						$content['button'] = array('detect' => true);
					}
				}
			}
		}
		if($content['keys']['comp']) $comp = $this->comp->read($content['keys']['comp']);
		//echo "<p>calendar='$calendar', comp={$content['keys']['comp']}=$comp[rkey]: $comp[name]</p>\n";
		if ($this->only_nation)
		{
			$calendar = $this->only_nation;
			$tmpl->disable_cells('keys[calendar]');
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
			list($calendar) = each($this->ranking_nations);
		}
		if (!$comp || ($comp['nation'] ? $comp['nation'] : 'NULL') != $calendar)
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
		$keys = array(
			'WetId' => $comp['WetId'],
			'GrpId' => $cat['GrpId'],
			'route_order' => $content['keys']['route'] < 0 ? -1 : $content['keys']['route'],
		);
		if ($comp && ($content['keys']['old_comp'] != $comp['WetId'] ||		// comp changed or
			$cat && ($content['keys']['old_cat'] != $cat['GrpId'] || 			// cat changed or
			!($route = $this->route->read($keys)))))	// route not found and no general result
		{
			if (is_numeric($keys['route_order'])) $route = $this->route->read($keys);
		}
		$this->set_ui_state($calendar,$comp['WetId'],$cat['GrpId']);

		if($content['button'])
		{
			list($button) = @each($content['button']);
			unset($content['button']);
			try {
				switch($button)
				{
					case 'upload':
						$content['import'] = self::do_upload($content['file']['tmp_name'],$content['charset'],
							$content['delimiter'],$calendar,$cat['sex']);
						$msg = lang('%1 lines read from file.',count($content['import'])-1);	// -1 because of 'as' key
						break;
					case 'cancel':
						unset($content['import']);
						break;
					case 'import':
						$msg = lang('%1 athletes imported.',
							self::do_import($content['import'],$content['keys'],$content['license'],$calendar,$this->license_year));
						break;
					case 'detect':
						self::detect_athletes($content['import'],$content['import']['as'],$calendar,$cat['sex'],$content['license'],$this->license_year);
						break;
				}
			}
			catch (Exception $e) {
				$msg = $e->getMessage();
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
					$readonlys["replace[$n][$c]"] = false;
				}
			}
			if ($content['import']['detection'][$n]['row'] == 'noAthlete')	// add button for new athletes
			{
				$readonlys["add[$n]"] = false;
				$readonlys["edit[$n]"] = true;
				$row['presets'] = '';
				foreach($content['import']['as'] as $c => $name)
				{
					if ($name && $row[$c]) $row['presets'] .= '&preset['.$name.']='.$row[$c];
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
		//echo "<p>calendar='$calendar', comp={$content['keys']['comp']}=$comp[rkey]: $comp[name], cat=$cat[rkey]: $cat[name], route={$content['keys']['route']}</p>\n";
		// create new view
		$sel_options = array(
			'delimiter' => array(',' => ',',';' => ';','\t' => 'Tab'),
			'charset' => array('utf-8' => 'UTF-8','iso-8859-1' => 'ISO-8859-1'),
			'calendar' => $this->ranking_nations,
			'comp'     => $this->comp->names(array(
				'nation' => $calendar,
				'datum < '.$this->db->quote(date('Y-m-d',time()+7*24*3600)),	// starting 5 days from now
				'datum > '.$this->db->quote(date('Y-m-d',time()-365*24*3600)),	// until one year back
				'gruppen IS NOT NULL',
			),0,'datum DESC'),
			'cat'      => $this->cats->names(array('rkey' => $comp['gruppen']),0),
			'route'    => array(
				'athletes' => 'Athletes',
			)+($comp && $cat ? array(
				'ranking'  => 'General result into ranking',
			)+(($routes = $this->route->query_list('route_name','route_order',array(
				'WetId' => $comp['WetId'],
				'GrpId' => $cat['GrpId'],
			),'route_order DESC')) ? $routes : array()): array()),
			'license' => $this->license_labels,
		);
		foreach(self::$import_columns as $col => $lables)
		{
			$sel_options['as'][$col] = $lables[0];
		}
		if ($comp && !isset($sel_options['comp'][$comp['WetId']])) $sel_options['comp'][$comp['WetId']] = $comp['name'];

		if (is_array($route)) $content += $route;
		$content['keys']['calendar'] = $calendar;
		$content['keys']['comp']     = $content['keys']['old_comp']= $comp ? $comp['WetId'] : null;
		$content['keys']['cat']      = $content['keys']['old_cat'] = $cat ? $cat['GrpId'] : null;
		$this->set_ui_state($calendar,$comp['WetId'],$cat['GrpId']);

		// make competition and category data availible for print
		$content['comp'] = $comp;
		$content['cat']  = $cat;

		$content['msg'] = $msg;

		$GLOBALS['egw']->session->appsession('pending_import','ranking',$content['import']);
		// create a nice header
		$GLOBALS['egw_info']['flags']['app_header'] = /*lang('Ranking').' - '.*/lang('Import').' '.
			($cat ? (isset($sel_options['route'][$content['keys']['route']]) ? $sel_options['route'][$content['keys']['route']].' ' : '').$cat['name'] : '');
		//_debug_array($content);
		return $tmpl->exec('ranking.ranking_import.index',$content,$sel_options,$readonlys,$content);
	}

	/**
	 * Handle the file upload
	 *
	 * @param string $fname
	 * @param string $charset='iso-8859-1'
	 * @param string $delimiter=','
	 * @param string $nation=null nation to use if not set in imported data
	 * @param string $sex=null gender to use if not set in imported data: 'male' or 'female'
	 * @return string success message
	 */
	private function do_upload($fname,$charset,$delimiter,$nation=null,$sex=null)
	{
		// do the import
		$raw_import = self::csv_import($fname,$charset,$delimiter);
		//_debug_array($raw_import);

		// try detecting column names
		$import = array('as' => self::detect_columns($raw_import[0],1));

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
		self::detect_athletes($import,$import['as'],$nation,$sex);
		//_debug_array($import);
		return $import;
	}

	/**
	 * Import a csv file into an array
	 *
	 * @param string $fname name of uploaded file
	 * @param string $charset='iso-8859-1' or eg. 'utf-8'
	 * @param string $delimiter=','
	 * @param string $enclosure='"'
	 * @return array with lines and columns or string with error message
	 */
	private function csv_import($fname,$charset='iso-8859-1',$delimiter=',',$enclosure='"')
	{
		if (!$fname || !file_exists($fname) || !is_uploaded_file($fname))
		{
			throw new egw_exception_wrong_userinput(lang('You need to select a file first!'));
		}
		if ($delimiter == '\t') $delimiter = "\t";

		$n = 0;
		$lines = array();
		if (!($fp = fopen($fname,'rb')) || !($labels = fgetcsv($fp,null,$delimiter,$enclosure)) || count($labels) <= 1)
		{
			throw new egw_exception_wrong_userinput(lang('Error: no line with column names, eg. wrong delemiter'));
		}
		$lines[$n++] = $GLOBALS['egw']->translation->convert($labels,$charset);

		while (($line = fgetcsv($fp,null,$delimiter)))
		{
			if (count($line) != count($labels))
			{
				//_debug_array($labels); _debug_array($line);
				throw new egw_exception_wrong_userinput(lang('Dataline %1 has a different number of columns (%2) then labels (%3)!',$n,count($line),count($labels)));
			}
			$lines[$n++] = $GLOBALS['egw']->translation->convert($line,$charset);
		}
		fclose($fp);

		return $lines;
	}

	/**
	 * detect the columns
	 *
	 * @param array $headers
	 * @param int $first=1 number of first column
	 * @return array column number => name pairs
	 */
	private function detect_columns(array $headers,$first=1)
	{
		$columns = array();
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
	 * @param string $nation=null nation to use if not set in imported data
	 * @param string $sex=null should we only search for a certain gender
	 * @param string $license=null 'a'=applied, 'c'=confirmed, 's'=suspended license status to set for not empty license field
	 * @param int $license_year=null default this->license_year
	 */
	private function detect_athletes(array &$import,array $col2name,$nation=null,$sex=null,$license=null,$license_year=null)
	{
		if ($nation == 'NULL') $nation = null;
		if (is_null($license_year) || !$license_year) $license_year = $this->license_year;

		$firstname_col = array_search('vorname',$col2name);
		$lastname_col  = array_search('nachname',$col2name);
		$nation_col    = array_search('nation',$col2name);
		$id_col        = array_search('PerId',$col2name);
		//error_log(__METHOD__."(,".array2string($col2name).",$nation,$sex) firstname_col=$firstname_col, lastname_col=$lastname_col, nation_col=$nation_col, id_col=$id_col");

		$detection =& $import['detection'];
		$detection = array();
		foreach($import as $n => &$data)
		{
			if ($n < 2 ) continue;	// not an athelete col

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
								),array(
									'\\3-\\2-\\1',
									'\\3-\\1-\\2',
									'\\3-\\2-\\1',
								),$data[$c]);
							}
						}
						break;
					case 'verband':
						switch($nat)
						{
							case 'GER':
								$data[$c] = preg_replace(array('/e\. ?V\. ?/i','/^(Deutscher Alpenverein|Alpenverein|DAV|Sektion|Se.) ?(.*)$/i'),
									array('','Deutscher Alpenverein \\2'),$data[$c]);
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
				}
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
					list($lastname,$firstname) = split(', ?',$lastname);
				}
				else
				{
					$parts = explode(' ',$lastname);
					$lastname = array_pop($parts);
					$firstname = implode(' ',$parts);
				}
			}
			$criteria = array(
				'vorname'  => $firstname,
				'nachname' => $lastname,
				'nation'   => $nation_col && $data[$nation_col] ? $data[$nation_col] : $nation,
			);
			if ($sex) $criteria['sex'] = $sex;

			if ($lastname_col && $data[$firstname_col] && $data[$lastname_col] &&
				($import['athlete'][$n] && !is_array($import['athlete'][$n]) &&
					($athlete = $this->athlete->read($import['athlete'][$n],'',$license_year,$nation)) ||
				($athletes = $this->athlete->search($criteria,false,'','','',false,'AND',false,array(
					'license_nation' => $nation,
					'license_year' => $license_year,
					'license_year' => $license_year,
				))) && count($athletes) == 1 && ($athlete=$athletes[0])))	// dont autodetect multiple matches!
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
					$import['athlete'][$n] = array('query' => ($sex ? strtoupper($sex[0]).': ' : '').($criteria['nation'] ? $criteria['nation'].': ' : '').
						($firstname_col ? $data[$firstname_col].' ' : '').$data[$lastname_col]);	// otherwise row does not show (autorepeat)
				}
				else
				{
					$import['athlete'][$n] = '';	// otherwise row does not show (autorepeat)
				}
				$detection[$n]['row'] = 'noAthlete';
			}
			if ($id_col) $data[$id_col] = $import['athlete'][$n];
		}
		//_debug_array($import['athlete']);
		//_debug_array($detection);
	}

	/**
	 * Import the data in $import into a competition, category and route specified in keys or just the athletes
	 *
	 * @param array $import athlete rows with numerical id's starting with 2, plus values for 'as' and 'athlete' keys
	 * @param array $keys values for keys 'comp', 'cat', 'route' (0..N, 'athlete' or 'ranking'
	 * @param string $license=null 'a'=applied, 'c'=confirmed, 's'=suspended license status to set for not empty license field
	 * @param string $license_nation=null nation of the license
	 * @param int $license_year=null default this->license_year
	 * @return int number of imported athletes
	 */
	private function do_import(array &$import,array $keys,$license=null,$license_nation=null,$license_year=null)
	{
		if (is_null($license_year) || !$license_year) $license_year = $this->license_year;

		$imported = 0;
		$col2name =& $import['as'];
		$nation_col = array_search('nation',$col2name);
		$athletes =& $import['athlete'];
		$detection =& $import['detection'];
		$update =& $import['update'];
		foreach($import as $n => &$row)
		{
			if ($n < 2 || !$athletes[$n] || !($athlete = $this->athlete->read($athletes[$n])))
			{
				continue;	// no athlete line or no (valid) athlete selected
			}
			$need_update = false;
			foreach($col2name as $c => $name)
			{
				if ($detection[$n][$c] && (!isset($update[$n][$c]) || $update[$n][$c]))
				{
					$athlete[$name] = $row[$c];
					$need_update = true;
					// extra handling for federations, athletes store only fed_id, they get only created, if they are explicitly marked for update!
					if ($name == 'verband')
					{
						if (!($athlete['fed_id'] = $this->federation->get_federation($row[$c],$nation_col?$row[$nation_col]:null,true)) &&
							$update[$n][$c] && $row[$nation_col])
						{
							$this->federation->init(array('verband' => $row[$c]));
							if ($this->federation->save($nation_col && $row[$nation_col] ? array('nation' => $row[$nation_col]) : null) == 0)
							{
								$athlete['fed_id'] = $this->federation->data['fed_id'];
							}
						}
					}
				}
			}
			if ($need_update && $this->athlete->save($athlete) == 0)
			{
				// set license if column has a non-empty value
				if (($lic_col = array_search('license',$col2name)) !== false && $row[$lic_col])
				{
					$this->athlete->set_license($license_year,$row[$lic_col][0],null,$license_nation);
				}
				unset($detection[$n]);	// unmark as now updated
				unset($update[$n]);
				$imported++;
			}
		}
		// run a new detection as that's easier and more consistent
		self::detect_athletes($import,$col2name,$keys['calendar'],null,$license,$license_year);

		return $imported;
	}
}