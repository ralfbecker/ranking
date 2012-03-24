<?php
/**
 * EGroupware digital ROCK Rankings - XML/JSON export logic
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2011 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$
 */

require_once(EGW_INCLUDE_ROOT.'/ranking/inc/class.boresult.inc.php');

class ranking_export extends boresult
{
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
			$base = 'http://'.$_SERVER['HTTP_HOST'];
			if ($_SERVER['HTTP_HOST'] == 'www.ifsc-climbing.org')
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
	 * Livetime of cache entries
	 *
	 * Can be fairly high, as cache is kept consistent, by calls from boresult::save_result to immediatly update the cache
	 * and calls to boresult::delete_export_route_cache() to invalidate it from route and route_result classes for deletes or updates.
	 *
	 * @var int
	 */
	const EXPORT_ROUTE_TTL = 86400;

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
		if ($update_cache || !($data = egw_cache::getInstance('ranking', $location)) !== false)
		{
			if (!isset($instance)) $instance = new ranking_export();

			$data = $instance->_export_route($comp, $cat, $heat);
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
		//printf("<p>reading route+result took %4.2lf s</p>\n",microtime(true)-$start);

		//echo "<pre>".print_r($route,true)."</pre>\n";

		// append category name to route name
		$route['route_name'] .= ' '.$cat['name'];
		$route['comp_name'] = $comp['name'];

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

		switch($discipline)
		{
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
				$athletes[$athlete['PerId']] = array(
					'PerId'     => $athlete['PerId'],
					'federation'=> $athlete['verband'],
					'firstname' => $athlete['vorname'],
					'lastname'  => $athlete['nachname'],
					'nation'    => $athlete['nation'],
					'url'       => self::profile_url($athlete,$cat['GrpId']),
				);
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
			$row['firstname'] = $row['vorname'];
			$row['lastname']  = $row['nachname'];
			$row['federation']= $row['verband'];
			if ($row['PerId']) $row['url'] = self::profile_url($row,$cat['GrpId']);

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
	 * Export an competition calendar for the given year and nation(s)
	 *
	 * @param array|string $nations
	 * @param int $year=null default current year
	 * @param array $filter=null eg. array('fed_id' => 123)
	 * @return array or competitions
	 * @todo turn rename_key call in explicit column-list with from AS to
	 */
	public function export_calendar($nations,$year=null,array $filter=null)
	{
		$where = array();

		if (!(int)$year) $year = (int)date('Y');
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

		static $rename_comp = array(
			'gruppen' => 'cats',
			'dru_bez' => 'short',
			'datum'   => 'date',
			'serie' => 'cup',
			'pflicht' => false, 'ex_pkte' => false,	'open' => false,	// currently not used
			'pkte' => false,	// not intersting for calendar
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
		);
		if ($filter)
		{
			foreach($filter as $name => $val)
			{
				if (($n = array_search($name,$rename_comp))) $name = $n;

				if (isset($this->comp->db_cols[$name]))
				{
					if ((string)$val === '' && $this->comp->table_def['fd'][$name]['nullable'] !== false)
					{
						$where[] = $name.' IS NULL';
					}
					else
					{
						$where[$name] = $val;
					}
				}
			}
		}
		//error_log(__METHOD__."('$nation', $year, ".array2string($filter).') --> where='.array2string($where));
		$competitions = $this->comp->search(null,false,'datum ASC','','','','AND',false,$where);
		$cats = $cups = $ids = $rkey2cat = $id2cup = array();
		foreach($competitions as &$comp)
		{
			$comp = self::rename_key($comp, $rename_comp);
			if ($comp['cats'] && ($d = array_diff($comp['cats'], $cats))) $cats = array_merge($cats, $d);
			if ($comp['cup'] && !in_array($comp['cup'],$cups)) $cups[] = $comp['cup'];
			$ids[] = $comp['WetId'];
		}
		//_debug_array($competitions); die('STOP');

		$cats = $this->cats->search(null,false,'rkey','','','','AND',false,array('rkey' => $cats));
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

		$cups = $this->cup->search(null,false,'rkey','','','','AND',false,array('SerId' => $cups));
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
		}

		return array(
			'competitions' => $competitions,
			'cats' => $cats,
			'cups' => $cups,
		);
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
}
