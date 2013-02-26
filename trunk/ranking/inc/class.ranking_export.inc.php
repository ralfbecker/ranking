<?php
/**
 * EGroupware digital ROCK Rankings - XML/JSON export logic
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2011-13 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$
 */

require_once(EGW_INCLUDE_ROOT.'/ranking/inc/class.boresult.inc.php');

class ranking_export extends boresult
{
	/**
	 * Disable caching for following development systems
	 *
	 * @var array
	 */
	public static $ignore_caching_hosts = array(
		'boulder.outdoor-training.de', 'ralfsmacbook.local', 'localhost'
	);

	/**
	 * Get result for export specified via URL
	 *
	 * Used for JSON and XML alike, as it returns raw data.
	 *
	 * @param string &$root_tag=null on return root tag for xml export
	 * @return array
	 */
	public static function export(&$root_tag=null)
	{
		try
		{
			if(isset($_GET['cat']) && !isset($_GET['comp']))
			{
				$export = new ranking_export();
				$result = $export->export_ranking($_GET['cat'], $_GET['date'], $_GET['cup']);
				$root_tag = 'ranking';
			}
			elseif (isset($_GET['nation']) || !isset($_GET['comp']))
			{
				$export = new ranking_export();
				$result = $export->export_calendar($_GET['nation'], $_GET['year'], $_GET['filter']);
				$root_tag = 'calendar';
			}
			elseif (isset($_GET['comp']) && $_GET['type'] == 'starters')
			{
				$export = new ranking_export();
				$result = $export->export_starters($_GET['comp']);
				$root_tag = 'starters';
			}
			elseif (isset($_GET['comp']) && !isset($_GET['cat']) || isset($_GET['filter']))
			{
				$export = new ranking_export();
				$result = $export->export_results($_GET['comp'], $_GET['num'], $_GET['all'], $_GET['filter']);
				$root_tag = 'results';
			}
			else
			{
				$result = self::export_route($_GET['comp'],$_GET['cat'],$_GET['route']);
				$root_tag = 'route';
			}
		}
		catch(Exception $e)
		{
			header("HTTP/1.1 404 Not Found");
			echo "<html>\n<head>\n\t<title>Error ".$e->getMessage()."</title>\n</head>\n";
			echo "<body>\n\t<h1>".$e->getMessage()."</h1>\n";
			echo "<p>The requested ressource was not found on this server.<br>\n<br>\n";
			echo 'URI: ' . $_SERVER['REQUEST_URI'] . "</p>\n";
			echo "</body></html>\n";
			exit;
		}
		return $result;
	}

	/**
	 * Get URL for athlete profile
	 *
	 * Currently /pstambl.php is used for every HTTP_HOST not www.ifsc-climbing.org,
	 * for which /index.php?page_name=pstambl is used.
	 *
	 * @param array $athlete
	 * @param int $cat=null
	 * @return string
	 */
	static function profile_url($athlete,$cat=null)
	{
		static $base;
		if (is_null($base))
		{
			//$base = 'http://'.$_SERVER['HTTP_HOST'];	// disabled base to have domain independent links
			if (in_array($_SERVER['HTTP_HOST'], array('www.ifsc-climbing.org', 'ifsc.egroupware.net')))
			{
				$base .= '/index.php?page_name=pstambl&person=';
			}
			else
			{
				$base .= '/pstambl.php?person=';
			}
		}
		return $base.$athlete['PerId'].($cat ? '&cat='.$cat : '');
	}

	/**
	 * Get URL for a result
	 *
	 * Currently /result.php is used for every HTTP_HOST not www.ifsc-climbing.org,
	 * for which /index.php?page_name=result is used.
	 *
	 * @param int $comp
	 * @param int $cat
	 * @return string
	 */
	static function result_url($comp, $cat)
	{
		static $base;
		if (is_null($base))
		{
			//$base = 'http://'.$_SERVER['HTTP_HOST'];	// disabled base to have domain independent links
			if (in_array($_SERVER['HTTP_HOST'], array('www.ifsc-climbing.org', 'ifsc.egroupware.net')))
			{
				$base .= '/index.php?page_name=result&comp=';
			}
			else
			{
				$base .= '/result.php?comp=';
			}
		}
		return $base.$comp.'&cat='.$cat;
	}

	/**
	 * Livetime of cache entries
	 *
	 * Can be fairly high, as cache is kept consistent, by calls from boresult::save_result to immediatly update the cache
	 * and calls to boresult::delete_export_route_cache() to invalidate it from route and route_result classes for deletes or updates.
	 *
	 * @var int
	 */
	const EXPORT_ROUTE_TTL = 86400;
	/**
	 * Expires header time for proxys/cdn on a running (not offical result) heat
	 *
	 * We cant invalidate proxys
	 *
	 * @var int
	 */
	const EXPORT_ROUTE_RUNNING_EXPIRES = 10;
	/**
	 * Expires header time for proxys/cdn on a recently offical result
	 *
	 * @var int
	 */
	const EXPORT_ROUTE_RECENT_OFFICAL_EXPIRES = 3600;
	/**
	 * Timeout for recently official
	 *
	 * @var int
	 */
	const EXPORT_ROUTE_RECENT_TIMEOUT = 14440;	// 4h
	/**
	 * Expires header time for proxys/cdn on an older offical result
	 *
	 * @var int
	 */
	const EXPORT_ROUTE_OFFICAL_EXPIRES = 86400;

	/**
	 * Export route for xml or json access, cached access
	 *
	 * Get's called from save_result with $update_cache===true, to keep the cache updated
	 *
	 * @param int $comp
	 * @param int|string $cat
	 * @param int $heat=-1
	 * @param boolean $update_cache=false false: read result from cache, false: update cache, before using it
	 * @return array
	 */
	public static function export_route($comp,$cat,$heat=-1,$update_cache=false)
	{
		static $instance;

		// normalise cat, we only want to cache one - the numeric - id
		if (!is_numeric($cat))
		{
			$cat = strtolower($cat);
			$cat_rkey2id = egw_cache::getInstance('ranking', 'cat_rkey2id');
			if (!isset($cat_rkey2id[$cat]))
			{
				if (!isset($instance)) $instance = new ranking_export();
				if (!($cat_arr = $instance->cats->read($cat)))
				{
					throw new Exception(lang('Category NOT found !!!'));
				}
				$cat_rkey2id[$cat] = $cat_arr['GrpId'];
				egw_cache::setInstance('ranking', 'cat_rkey2id', $cat_rkey2id);
			}
			$cat = $cat_rkey2id[$cat];
		}
		// can we use the cached data und do we have it?
		$location = 'export_route:'.$comp.':'.$cat.':'.$heat;
		// switch caching off for speed-cli.php, as it can not (un)set the cache,
		// because of permissions of /tmp/egw_cache only writable by webserver-user
		// for all other purposes caching is ok and should be enabled
		if (in_array($_SERVER['HTTP_HOST'], self::$ignore_caching_hosts) ||
			$update_cache || !($data = egw_cache::getInstance('ranking', $location)))
		{
			if (!isset($instance)) $instance = new ranking_export();

			$data = $instance->_export_route($comp, $cat, $heat);
			// setting expires depending on result offical and how long it is offical
			$data['expires'] = !isset($data['route_result']) ? self::EXPORT_ROUTE_RUNNING_EXPIRES :
				(time()-$data['last_modified'] > self::EXPORT_ROUTE_RECENT_TIMEOUT ?
					self::EXPORT_ROUTE_OFFICAL_EXPIRES : self::EXPORT_ROUTE_RECENT_OFFICAL_EXPIRES);

			egw_cache::setInstance('ranking', $location, $data, self::EXPORT_ROUTE_TTL);

			// update general result too?
			if ($update_cache && $heat > 0)
			{
				egw_cache::setInstance('ranking', 'export_route:'.$comp.':'.$cat.':-1',
					self::$instance->_export_route($comp, $cat, -1), self::EXPORT_ROUTE_TTL);
			}
		}
		return $data;
	}

	/**
	 * Export route for xml or json access, cache-free version
	 *
	 * @param int $comp
	 * @param int|string|array $cat
	 * @param int $heat=-1
	 * @return array
	 */
	protected  function _export_route($comp,$cat,$heat=-1)
	{
		$start = microtime(true);

		if (!is_array($cat) && !($cat = $this->cats->read($cat)))
		{
			throw new Exception(lang('Category NOT found !!!'));
		}

		//echo "<pre>".print_r($cat,true)."</pre>\n";
		if (!($comp = $this->comp->read($comp)))
		{
			throw new Exception(lang('Competition NOT found !!!'));
		}
		if (!($discipline = $comp['discipline']))
		{
			$discipline = $cat['discipline'];
		}
		if (!isset($heat) || !is_numeric($heat)) $heat = -1;	// General result

		if (!($route = $this->route->read(array(
			'WetId' => $comp['WetId'],
			'GrpId' => $cat['GrpId'],
			'route_order' => $heat,
		))))
		{
			throw new Exception(lang('Route NOT found !!!'));
		}
		if (!empty($route['discipline'])) $discipline = $route['discipline'];

		//printf("<p>reading route+result took %4.2lf s</p>\n",microtime(true)-$start);

		//echo "<pre>".print_r($route,true)."</pre>\n";

		// append category name to route name
		$route['route_name'] .= ' '.$cat['name'];
		$route['comp_name'] = $comp['name'];
		$route['nation'] = $comp['nation'];

		if ($this->route_result->isRelay != ($discipline == 'speedrelay'))
		{
			$this->route_result->__construct($this->config['ranking_db_charset'],$this->db,null,
					$discipline == 'speedrelay');
		}
		$query = array(
			'order' => 'result_rank',
			'sort'  => 'ASC',
			'route' => $heat,
			'discipline' => $discipline,
		);
		self::process_sort($query,$this->route_result->isRelay);

		if (!($result = $this->route_result->search(array(),false,$query['order'].' '.$query['sort'],'','',false,'AND',false,$filter=array(
			'WetId' => $comp['WetId'],
			'GrpId' => $cat['GrpId'],
			'route_order' => $heat,
			'discipline'  => $discipline,
			'route_type'  => $route['route_type'],
			'quali_preselected' => $this->comp->quali_preselected($cat['GrpId'], $comp['quali_preselected']),
		)))) $result = array();
		//_debug_array($filter); _debug_array($result);

		// return route_names as part of route, not as participant
		if (isset($result['route_names']))
		{
			$route['route_names'] = $result['route_names'];
			unset($result['route_names']);
		}
		else
		{
			$route['route_names'] = $this->route->query_list('route_name','route_order',array(
				'WetId' => $comp['WetId'],
				'GrpId' => $cat['GrpId'],
			),'route_order');
		}

		switch($discipline)
		{
			default:
			case 'lead':
				$num_current = 1;
				break;
			case 'boulder':
				$num_current = $route['route_num_problems'];
				break;
			case 'speed':
			case 'speedrelay':
				$num_current = 2;
				break;
		}
		for($i = 1; $i <= $num_current; ++$i)
		{
			$route['current'][] = $route['current_'.$i] ? $route['current_'.$i] : null;
			unset($route['current_'.$i]);
		}
		// remove empty/null values from route
		foreach($route as $name => $value)
		{
			if ((string)$value === '') unset($route[$name]);
		}
		$last_modified = (int)$route['route_modified'];

		// make sure route_result is set, if result is offical (and not if not)
		if ($route['route_status'] == STATUS_RESULT_OFFICIAL)
		{
			if (empty($route['route_result'])) $route['route_result'] = 'official';
		}
		else
		{
			unset($route['route_result']);
		}

		// remove not needed route attributes
		$route = array_diff_key($route,array_flip(array(
			'route_type',
			'dsp_id','frm_id','dsp_id2','frm_id2',
			'user_timezone_read',
			'route_modified','route_modifier',	// we have single last_modified
			'route_time_host','route_time_port',
			'route_status',	// 'route_result' is set if result is official
			'slist_order',
			'next_1','next_2','next_3','next_4','next_5','next_6','next_7','next_8',
			'RelayResults.*',	// not needed and gives warning in xml export
		)));
		if ($discipline != 'boulder') unset($route['route_num_problems']);

		if ($discipline == 'speedrelay')	// fetch athlete names
		{
			foreach($result as $key => $row)
			{
				$ids[] = $row['PerId_1'];
				$ids[] = $row['PerId_2'];
				if (!empty($row['PerId_3'])) $ids[] = $row['PerId_3'];
			}
			foreach($this->athlete->search(array('PerId' => $ids),false) as $athlete)
			{
				$athletes[$athlete['PerId']] = self::athlete_attributes($athlete, $comp['nation'], $cat['GrpId']);
			}
			//echo "<pre>".print_r($athletes,true)."</pre>\n";die('Stop');
		}

		$tn = $unranked = array();
		foreach($result as $key => $row)
		{
			if (isset($row['quali_points']) && $row['quali_points'])
			{
				$row['quali_points'] = number_format($row['quali_points'],2);
			}
			if ($row['result_modified'] > $last_modified) $last_modified = $row['result_modified'];

			if ($heat == -1)	// rename result to result0 for general result
			{
				$row['result0'] = $row['result'];
				unset($row['result']);
			}
			// use english names
			$row += self::athlete_attributes($row, $comp['nation'], $cat['GrpId']);

			// remove &nbsp; in boulderheight results
			if ($discipline == 'boulderheight')
			{
				$row = str_replace('&nbsp;', ' ', $row);
			}

			// remove &nbsp; in lead results
			if ($discipline == 'lead')
			{
				if (isset($row['quali_points']))
				{
					for($i = 0; $i <= 1; ++$i)
					{
						if(isset($row['result'.$i]))
						{
							$row['result'.$i] = str_replace('&nbsp;',' ',$row['result'.$i]);
						}
					}
				}
				if(isset($row['result']))
				{
					list($row['result']) = explode('&nbsp;',$row['result']);
					$row['time'] = $row['result_time_l'];
				}
				for($i = 0; $i <= 5; ++$i)
				{
					if(isset($row['result'.$i]))
					{
						list($row['result'.$i]) = explode('&nbsp;',$row['result'.$i]);
					}
				}
			}
			// remove single boulder meaningless in general result, or not existing boulder
			if ($discipline == 'boulder')
			{
				for($i = 1; $i <= 8; ++$i)
				{
					if ($heat == -1 || $i > $route['route_num_problems'])
					{
						unset($row['boulder'.$i]);
					}
				}
			}
			// for speed show time_sum as result, plus result_l and result_r
			if (isset($row['time_sum']))
			{
				$row['result_l'] = $row['result'];
				$row['result'] = $row['time_sum'];
				unset($row['time_sum']);	// identical to result
			}
			if ($discipline == 'speedrelay')
			{
				unset($row['lastname']);
				unset($row['firstname']);
				unset($row['federation']);
				$athletes[$row['PerId_1']]['start_number'] = $row['start_number_1'];
				if ($heat > -1) $athletes[$row['PerId_1']]['result_time'] = $row['result_time_1'];
				$athletes[$row['PerId_2']]['start_number'] = $row['start_number_2'];
				if ($heat > -1) $athletes[$row['PerId_2']]['result_time'] = $row['result_time_2'];
				$row['athletes'] = array(
					$athletes[$row['PerId_1']],
					$athletes[$row['PerId_2']],
				);
				if (!empty($row['PerId_3']))
				{
					$athletes[$row['PerId_3']]['start_number'] = $row['start_number_3'];
					if ($heat > -1) $athletes[$row['PerId_3']]['result_time'] = $row['result_time_3'];
					$row['athletes'][] = $athletes[$row['PerId_3']];
				}
				unset($row['time_sum']);	// identical to result
			}
			// always return result attribute
			if ($heat != -1 && !isset($row['result'])) $row['result'] = '';

			// remove not needed attributes
			$row = array_diff_key($row,array_flip(array(
				// remove keys, they are already in route
				'GrpId','WetId','route_order','route_type','discipline','acl_fed',
				'geb_date',	// we still have birthyear
				// remove renamed values
				'vorname','nachname','verband','plz','ort',
				'general_result','org_rank','result_modifier',
				'RouteResults.*','result_detail',
				// speed single route use: result, result_l, result_r
				'result_time','result_time_l','result_time_r',
				// speed general result use: result*, result_rank*
				'result_time2','result_time3','result_time4','result_time5','result_time6',
				'start_order2','start_order3','start_order4','start_order5','start_order6',
				// lead general result
				'result_height','result_height1','result_height2','result_height3','result_height4','result_height5',
				'result_plus','result_plus1','result_plus2','result_plus3','result_plus4','result_plus5',
				// boulder general result
				'top','top1','top2','top3','top4','top5','top6','top7','top8',
				'zone','zone1','zone2','zone3','zone4','zone5','zone6','zone7','zone8',
				'result_top','result_top1','result_top2','result_top3','result_top4','result_top5','result_top6','result_top7','result_top8',
				'result_zone','result_zone1','result_zone2','result_zone3','result_zone4','result_zone5','result_zone6','result_zone7','result_zone8',
				// teamrelay
				'PerId_1','PerId_2','PerId_3',
				'start_number_1','start_number_2','start_number_3',
				'result_time_1','result_time_2','result_time_3',
				'RelayResults.*',	// not needed and gives warning in xml export
			)));

			ksort($row);
			if ($row['result_rank'])
			{
				$tn[$row[$this->route_result->id_col]] = $row;
			}
			else
			{
				$unranked[$row[$this->route_result->id_col]] = $row;
			}
			$last_rank = $row['result_rank'];
		}
		$tn = array_merge($tn,$unranked);

		$ret = $route+array(
			'discipline'    => $discipline,
			'participants'  => $tn,
			'last_modified' => $last_modified,
		);
		$ret['etag'] = md5(serialize($ret));

		return $ret;
	}

	/**
	 * Livetime of cache entries of calendar data
	 *
	 * We use a fairly low time, as we cant invalidate the cache,
	 * because of filter value
	 *
	 * @var int
	 */
	const EXPORT_CALENDAR_TTL = 300;
	/**
	 * Livetime of cache entries of calendar data of previous years
	 *
	 * @var int
	 */
	const EXPORT_CALENDAR_OLD_TTL = 86400;

	/**
	 * colum --> export name conversation (or false to suppress in export)
	 *
	 * @var array
	 */
	public static $rename_comp = array(
		'gruppen' => 'cats',
		'dru_bez' => 'short',
		'datum'   => 'date',
		'serie' => 'cup',
		'pflicht' => false, 'ex_pkte' => false,	'open' => false,	// currently not used
		'pkte' => false,	// not interesting for calendar
		'pkt_bis' => false,
		'feld_pkte' => false,
		'feld_bis' => false,
		'faktor' => false, // 'factor'
		'quota' => false,
		'host_quota' => false,
		'prequal_ranking' => false,
		'prequal_comp' => false,
		'prequal_comps' => false,
		'judges' => false,
		'no_complimentary' => false,
		'modifier' => false,
		'quota_extra' => false, //'extra_quotas',
		'prequal_extra' => false, //'extra_prequals',
		'user_timezone_read' => false,
	);

	/**
	 * Export a competition calendar for the given year and nation(s)
	 *
	 * @param array|string $nations
	 * @param int $year=null default current year
	 * @param array $filter=null eg. array('fed_id' => 123)
	 * @return array or competitions
	 * @todo turn rename_key call in explicit column-list with from AS to
	 */
	public function export_calendar($nations,$year=null,array $filter=null)
	{
		if (!(int)$year) $year = (int)date('Y');
		$location = 'calendar:'.json_encode(array(
			'nation' => $nations,
			'year' => $year,
			'filter' => $filter,
		));
		if (!in_array($_SERVER['HTTP_HOST'], self::$ignore_caching_hosts) &&
			($data = egw_cache::getInstance('ranking', $location)))
		{
			return $data;
		}

		$where = self::process_filter($filter);
		$where[] = 'datum LIKE '.$this->db->quote((int)$year.'%');

		if (!is_array($nations)) $nations = explode(',',$nations);
		if (($key = array_search('',$nations)) !== false)
		{
			unset($nations[$key]);
			$sql = 'nation IS NULL';
		}
		if ($nations)
		{
			$sql = '('.($sql ? $sql.' OR ' : '').$this->db->expression($this->comp->table_name,array('nation'=>$nations)).')';
		}
		$where[] = $sql;

		//error_log(__METHOD__."('$nation', $year, ".array2string($filter).') --> where='.array2string($where));
		$competitions = $this->comp->search(null,false,'datum ASC','','','','AND',false,$where);
		$cats = $cups = $ids = $rkey2cat = $id2cup = array();
		foreach($competitions as &$comp)
		{
			$comp = self::rename_key($comp, self::$rename_comp);
			if ($comp['cats'] && ($d = array_diff($comp['cats'], $cats))) $cats = array_merge($cats, $d);
			if ($comp['cup'] && !in_array($comp['cup'],$cups)) $cups[] = $comp['cup'];
			$ids[] = $comp['WetId'];
		}
		//_debug_array($competitions); die('STOP');

		if ($cats && ($cats = $this->cats->search(null,false,'rkey','','','','AND',false,array('rkey' => $cats))))
		{
			static $rename_cat = array(
				'serien_pat' => false,	// not interesting
				'vor_rls' => false,
				'vor' => false,
				'rls' => false,
				'GrpIds' => false,	// only intersting for combined, so not for calendar
				'extra' => false,	// what's that anyway?
			);
			foreach($cats as &$cat)
			{
				$cat = self::rename_key($cat, $rename_cat);
				$rkey2cat[$cat['rkey']] =& $cat;
			}
			//_debug_array($cats); die('STOP');
		}
		if ($cups && ($cups = $this->cup->search(null,false,'rkey','','','','AND',false,array('SerId' => $cups))))
		{
			static $rename_cup = array(
				'gruppen' => 'cats',
				'faktor' => false,	// currently not used
				'split_by_places' => false,	// not interesting for calendar
				'pkte' => false,
				'max_rang' => false,
				'max_serie' => false,
				'presets' => false,
			);
			foreach($cups as &$cup)
			{
				$cup = self::rename_key($cup, $rename_cup);
				$id2cup[$cup['SerId']] =& $cup;
			}
			//_debug_array($cups); die('STOP');
		}
		// query status (existence for start list or result)
		$status = $this->result->result_status($ids);
		$status = $this->route_result->result_status($ids,$status);
		//_debug_array($status); die('Stop');
		foreach($competitions as &$comp)
		{
			// add cat id, name, status and url
			if (isset($comp['cats']) && is_array($comp['cats']))
			{
				foreach($comp['cats'] as &$cat)
				{
					$c = $rkey2cat[$cat];
					$cat = array(
						'GrpId' => $c['GrpId'],
						'name' => $c['name'],
						'status' => $status[$comp['WetId']][$c['GrpId']],
					);
					if (isset($cat['status']))
					{
						if ($cat['status'])
						{
							$cat['url'] = egw::link('/ranking/result.php',array(
								'comp' => $comp['WetId'],
								'cat' => $c['GrpId'],
							));
						}
						else
						{
							$cat['url'] = '/result.php?comp='.$comp['WetId'].'&cat='.$c['GrpId'];
						}
					}
				}
			}
			// add cup name
			if ($comp['cup'])
			{
				$comp['cup'] = array(
					'SerId' => $comp['cup'],
					'name'  => $id2cup[$comp['cup']]['name'],
				);
			}
			if (($attachments = $this->comp->attachments($comp,$return_link=true,$only_pdf=true)))
			{
				$comp += $attachments;
			}
		}

		$data = array(
			'competitions' => $competitions,
			'cats' => $cats,
			'cups' => $cups,
		);
		$data['expires'] = $year < date('Y') ? self::EXPORT_CALENDAR_OLD_TTL : self::EXPORT_CALENDAR_TTL;
		$data['etag'] = md5(serialize($data));

		egw_cache::setInstance('ranking', $location, $data, $data['expires']);

		return $data;
	}

	/**
	 * Process a competition filter into an array to pass into select query
	 *
	 * - multiple values are comma-separated
	 * - leading exclemation mark negates expression
	 *
	 * @param array $filter allowed keys are all column-names and alias from self::$rename_comp
	 * @return array
	 */
	private function process_filter(array $filter=null)
	{
		$where = array();
		if ($filter)
		{
			foreach($filter as $name => $val)
			{
				if (($n = array_search($name, self::$rename_comp))) $name = $n;

				if (isset($this->comp->db_cols[$name]))
				{
					if ((string)$val === '' && $this->comp->table_def['fd'][$name]['nullable'] !== false)
					{
						$where[] = $name.' IS NULL';
					}
					elseif ($val[0] == '!' || strpos($val, ',') !== false)
					{
						$not = '';
						if ($val[0] == '!')
						{
							$not = 'NOT ';
							$val = substr($val, 1);
						}
						$val = explode(',', $val);
						if ($this->comp->table_def['fd'][$name]['type'] !== 'varchar')
						{
							$val = array_diff($val, array(''));	// remove empty vales as they would give SQL error
						}
						if (!$val) $val = $this->comp->table_def['fd'][$name]['nullable'] !== false ? null : '';
						$where[] = $this->db->expression($this->comp->table_name, $not, array($name => $val));
					}
					else
					{
						$where[$name] = $val;
					}
				}
			}
		}
		return $where;
	}

	/**
	 * Livetime of cache entries of recent ranking data
	 *
	 * We use a fairly low time, as we cant invalidate the cache easyly.
	 *
	 * @var int
	 */
	const EXPORT_RANKING_TTL = 300;
	/**
	 * Livetime of cache entries of ranking data longer then duration of last-competition in the past
	 *
	 * @var int
	 */
	const EXPORT_RANKING_OLD_TTL = 86400;

	/**
	 * Export a (cup) ranking
	 *
	 * @param int|string $cat id or rkey of category
	 * @param string $date=null date of ranking, default today
	 * @param int|string $cup id or rkey of cup to generate a cup-ranking
	 * @return array or athletes
	 */
	public function export_ranking($cat,$date=null,$cup=null)
	{
		if (empty($date)) $date = '.';
		$location = 'calendar:'.json_encode(array(
			'cat' => $cat,
			'date' => $date,
			'cup' => $cup,
		));
		if (!in_array($_SERVER['HTTP_HOST'], self::$ignore_caching_hosts) &&
			($data = egw_cache::getInstance('ranking', $location)))
		{
			return $data;
		}
		if ($cup && !($cup = $this->cup->read($cup)))
		{
			throw new Exception(lang('Cup not found!!!'));
		}
		if (!($cat = $this->cats->read($cat)))
		{
			throw new Exception(lang('Category not found!!!'));
		}
		$comps = array();
		if (!($ranking = $this->ranking($cat, $date, $start, $comp, $pers, $rls, $ex_aquo, $not_counting, $cup, $comps, $max_comp)))
		{
			throw new Exception(lang('No ranking defined or no results yet for category %1 !!!',$cat['name']));
		}
		$rows = array();
		foreach($ranking as $athlete)
		{
			$data = self::athlete_attributes($athlete, $cat['nation'], $cat['GrpId']) + array(
				'result_rank' => $athlete['platz'],
				'points' => $athlete['pkt'],
			);
			foreach($athlete['results'] as $WetId => $result)
			{
				$data['result'.$WetId] = $result;
			}
			$rows[] = $data;
		}
		// sort competitions by date
		uasort($comps, function($a,$b){return strcmp($a['datum'],$b['datum']);});
		unset($data);
		$route_names = array();
		foreach($comps as $WetId => &$data)
		{
			if (empty($data['dru_bez']))
			{
				$parts = preg_split('/ ?- ?/', $data['name']);
				list($data['dru_bez']) = explode('/', array_pop($parts));
			}
			$route_names[$WetId] = $data['dru_bez']."\n".implode('.', array_reverse(explode('-', $data['datum'])));
		}
		unset($data);
		$data = array(
			'cat' => array(
				'GrpId' => $cat['GrpId'],
				'name' => $cat['name'],
			),
			'cup' => $cup ? array(
				'SerId' => $cup['SerId'],
				'name' => $cup['name'],
			) : null,
			'start' => $start,
			'end' => $date,
			'max_comp' => $max_comp,
			'comp' => array(
				'WetId' => $comp['WetId'],
				'name' => $comp['name'],
				'date' => $comp['datum'],
			),
			'nation' => $cat['nation'],
			'participants' => $rows,
			'route_name' => $comp['name'].' ('.implode('.', array_reverse(explode('-', $comp['datum']))).')',
			'route_names' => $route_names,
			'route_result' => implode('.', array_reverse(explode('-', $date))),
			'route_order' => -1,
			'discipline' => 'ranking',
		);
		if ($cup)
		{
			$data['comp_name'] = $cup['name'];
		}
		else
		{
			$data['comp_name'] = 'Ranglist';
		}
		$data['comp_name'] .= ': '.$cat['name'];

		// calculate expiration date based on date of ranking and duration of last competition
		list($y,$m,$d) = explode('-', $date);
		$date = mktime(0,0,0,$m,$d,$y);
		if (substr($data['end'], -6) == '-12-31' || (time()-$date)/86400 > $comp['duration'])
		{
			$data['expires'] = self::EXPORT_RANKING_OLD_TTL;
		}
		else
		{
			$data['expires'] = self::EXPORT_RANKING_TTL;
		}
		$data['etag'] = md5(serialize($data));

		egw_cache::setInstance('ranking', $location, $data, $data['expires']);

		return $data;
	}

	/**
	 * Cache and expires time for results from a running competition
	 */
	const EXPORT_RESULTS_RUNNING_EXPIRES = 900;
	/**
	 * Cache and expires time for results from a finished/historic competition
	 */
	const EXPORT_RESULTS_HISTORIC_EXPIRES = 86400;

	/**
	 * Export results from all categories of a competition
	 *
	 * @param string|int $comp WetId or rkey of competition or '.' or 3-char calendar nation for latest result
	 * @param int $num=null how many results to return, default 8 for 2 categories (or less), otherwise 3
	 * @param string $nation=null if set, return all results of that nation
	 * @param array $filter=null eg. array('fed_id' => 123)
	 * @return array
	 */
	public function export_results($comp, $num=null, $nation=null, array $filter=null)
	{
		$location = 'results:'.json_encode(func_get_args());
		if (!in_array($_SERVER['HTTP_HOST'], self::$ignore_caching_hosts) &&
			($data = egw_cache::getInstance('ranking', $location)))
		{
			return $data;
		}

		if ($comp == '.' || !(int)$comp && strlen($comp) == 3)
		{
			$calendar = $comp == '.' ? null : $comp;
			unset($comp);
		}
		else
		{
			if (!($comp = $this->comp->read($comp)))
			{
				throw new Exception(lang('Competition NOT found !!!'));
			}
			$calendar = $comp['nation'];
		}
		$filter = self::process_filter($filter);
		$filter['nation'] = $calendar;
		$filter[] = $this->comp->table_name.'.datum <= '.$this->db->quote(time(), 'date');
		$filter[] = 'platz > 0';
		$join = 'JOIN '.$this->result->result_table.' USING(WetId)';

		$comps = array();
		foreach($this->comp->search(null, 'DISTINCT WetId,name,'.$this->comp->table_name.'.datum AS datum,gruppen,nation', 'datum DESC', '', '', '', 'AND', array(0, 20), $filter, $join) as $c)
		{
			if (!isset($comp) || $comp['WetId'] == $c['WetId'])
			{
				$comp = $c;
			}
			else
			{
				unset($c['gruppen']);	// not needed/wanted
				unset($c['duration']);
				unset($c['date_end']);
				$comps[] = self::rename_key($c, self::$rename_comp);
			}
		}
		//_debug_array($comps);
		if (!($cats = $this->result->read(array(
			'WetId' => $comp['WetId'],
			'platz > 0',
		))))
		{
			throw new Exception(lang('No result yet !!!'));
		}
		$cats_by_id = array();
		foreach($cats as $cat)
		{
			$cat['url'] = self::result_url($comp['WetId'], $cat['GrpId']);
			$cats_by_id[$cat['GrpId']] = $cat;
		}
		//_debug_array($comp);
		if (!isset($num) || !($num > 0))
		{
			$num = count($cats_by_id) <= 2 ? 8 : 3;
		}
		$result_filter = 'platz <= '.(int)$num;
		if ($nation)
		{
			$result_filter = '('.$result_filter.' OR nation='.$this->db->quote($nation).')';
		}
		$results = array();
		foreach($this->result->read(array(
			'WetId' => $comp['WetId'],
			'GrpId' => array_keys($cats_by_id),
			$result_filter,
		)) as $result)
		{
			$modified = egw_time::createFromFormat(egw_time::DATABASE, $result['modified'], egw_time::$server_timezone);
			if (($ts = $modified->format('ts')) > $last_modified) $last_modified = $ts;
			$cats_by_id[$result['GrpId']]['results'][] = array(
				'result_rank' => $result['platz'],
			)+self::athlete_attributes($result, $comp['nation'], $result['GrpId']);
		}
		unset($comp['gruppen']);

		$data = self::rename_key($comp, self::$rename_comp)+array(
			'nation' => $comp['nation'],
			'categorys' => array_values($cats_by_id),
			'competitions' => $comps,
			'last_modified' => $last_modified,
		);
		list($y,$m,$d) = explode('-', $comp['datum']);
		$comp_ts = mktime(0, 0, 0, $m, $d, $y);
		$data['expires'] = (time()-$comp_ts)/86400 > $comp['duration'] ?
			self::EXPORT_RESULTS_HISTORIC_EXPIRES : self::EXPORT_RESULTS_RUNNING_EXPIRES;

		$data['etag'] = md5(serialize($data));

		egw_cache::setInstance('ranking', $location, $data, $data['expires']);

		//_debug_array($ret); exit;
		return $data;
	}

	/**
	 * Cache and expires time for starters / registration
	 */
	const EXPORT_STARTERS_EXPIRES = 900;

	/**
	 * Export starters / registration from all categories of a competition
	 *
	 * @param string|int $comp WetId or rkey of competition
	 * @return array
	 */
	public function export_starters($comp)
	{
		$location = 'starters:'.$comp;
		if (!in_array($_SERVER['HTTP_HOST'], self::$ignore_caching_hosts) && is_numeric($comp) &&
			($data = egw_cache::getInstance('ranking', $location)))
		{
			return $data;
		}

		if (!($comp = $this->comp->read($comp)))
		{
			throw new Exception(lang('Competition NOT found !!!'));
		}
		// try again with numeric id
		$location = 'starters:'.$comp['WetId'];
		if (!in_array($_SERVER['HTTP_HOST'], self::$ignore_caching_hosts) &&
			($data = egw_cache::getInstance('ranking', $location)))
		{
			return $data;
		}
		//_debug_array($comps);
		if (!($cats = $this->result->read(array('WetId' => $comp['WetId']), '', true, $this->result->table_name.'.GrpId')))
		{
			throw new Exception(lang('No starters yet !!!'));
		}
		$athletes = $federations = array();
		foreach($this->result->read(array(
			'WetId' => $comp['WetId'],
			'GrpId' => -1,
		), '', true, $results ? '' : ($comp['nation'] ? 'acl_fed_id,fed_parent' : 'nation').',GrpId,pkt') as $result)
		{
			$modified = egw_time::createFromFormat(egw_time::DATABASE, $result['modified'], egw_time::$server_timezone);
			if (($ts = $modified->format('ts')) > $last_modified) $last_modified = $ts;

			$fed_id = !$comp['nation'] ? $result['nation'] :
				($result['acl_fed_id'] ? $result['acl_fed_id'] :
				($result['fed_parent'] ? $result['fed_parent'] : $result['fed_id']));
			$federations[$fed_id] = $fed_id;

			$athletes[] = self::athlete_attributes($result, $comp['nation'], $result['GrpId'])+array(
				'cat' => $result['GrpId'],
				'reg_fed_id' => $fed_id,
				'order' => $result['pkt'],
			);
		}
		unset($comp['gruppen']);

		$data = self::rename_key($comp, self::$rename_comp)+array(
			'nation' => $comp['nation'],
			'categorys' => $cats,
			'athletes' => $athletes,
			'last_modified' => $last_modified,
		);
		if ($comp['nation'])
		{
			$data['federations'] = array_values($this->federation->query_list(array(
				'fed_id' => 'fed_id',
				'shortcut' => 'fed_shortcut',
				'name' => 'verband',
			), 'fed_id', array(
				'fed_id' => array_values($federations)
			), 'verband'));
		}
		$data['expires'] = self::EXPORT_STARTERS_EXPIRES;

		$data['etag'] = md5(serialize($data));

		egw_cache::setInstance('ranking', $location, $data, $data['expires']);

		//_debug_array($ret); exit;
		return $data;
	}

	/**
	 * Rename array keys or remove value if no new key given
	 *
	 * @param array $arr
	 * @param array $rename array with from => to or from => false pairs
	 * @return array
	 */
	public static function rename_key(array $arr, array $rename)
	{
		foreach($rename as $from => $to)
		{
			if ($to && isset($arr[$from])) $arr[$to] =& $arr[$from];
			unset($arr[$from]);
		}
		return $arr;
	}

	/**
	 * Set athlete specific attributes taking into account calendar nation
	 *
	 * @param array $athlete
	 * @param string $nation
	 * @param int $cat GrpId for profile url
	 * @return array
	 */
	protected static function athlete_attributes($athlete, $nation, $cat)
	{
		$data = array(
			'PerId' => $athlete['PerId'],
			'firstname' => $athlete['vorname'],
			'lastname' => $athlete['nachname'],
			'birthyear' => substr($athlete['geb_date'], 0, 4),
			'nation' => $athlete['nation'],
			'federation' => $athlete['verband'],
			'fed_url' => $athlete['fed_url'],
			'url' => self::profile_url($athlete, $cat),
		);
		switch ($nation)
		{
			case 'GER':
				$data['federation'] = preg_replace("/^(DAV|Sektion|Deutscher|Dt|Alpenverein|[:., ])*/i", '', $athlete['verband']);
				break;
			case 'SUI':
				$data['city'] = $athlete['ort'];
				break;
		}
		return $data;
	}
}
