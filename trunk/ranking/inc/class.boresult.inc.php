<?php
/**************************************************************************\
* eGroupWare - digital ROCK Rankings - result BO                           *
* http://www.egroupware.org, http://www.digitalROCK.de                     *
* Written and (c) by Ralf Becker <RalfBecker@outdoor-training.de>          *
* --------------------------------------------                             *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

require_once(EGW_INCLUDE_ROOT.'/ranking/inc/class.boranking.inc.php');
require_once(EGW_INCLUDE_ROOT.'/ranking/inc/class.route.inc.php');
require_once(EGW_INCLUDE_ROOT.'/ranking/inc/class.route_result.inc.php');

define('ONE_QUALI',0);
define('TWO_QUALI_HALF',1);
define('TWO_QUALI_ALL',2);
define('LEAD',4);
define('BOULDER',8);
define('SPEED',16);
define('STATUS_UNPUBLISHED',0);
define('STATUS_STARTLIST',1);
define('STATUS_RESULT_OFFICIAL',2);

class boresult extends boranking 
{
	/**
	 * values and labels for route_order
	 *
	 * @var array
	 */
	var $order_nums;
	/**
	 * values and labels for route_status
	 *
	 * @var array
	 */
	var $stati = array(
		STATUS_UNPUBLISHED     => 'unpublished',
		STATUS_STARTLIST       => 'startlist',
		STATUS_RESULT_OFFICIAL => 'result official',
	);
	/**
	 * Different types of qualification
	 *
	 * @var array
	 */
	var $quali_types = array(
		ONE_QUALI      => 'one Qualification',
		TWO_QUALI_HALF => 'two Qualification, half quota',	// no countback
		TWO_QUALI_ALL  => 'two Qualification for all',		// multiply the rank
	);
	var $eliminated_labels = array(
		''=> '',
		1 => 'eliminated',
		0 => 'wildcard',
	);
	/**
	 * values and labels for route_plus
	 *
	 * @var array
	 */
	var $plus,$plus_labels;
	/**
	 * Instance of the route object
	 * 
	 * @var route
	 */
	var $route;
	/**
	 * Instance of the route-result object
	 * 
	 * @var route_result
	 */
	var $route_result;

	function boresult()
	{
		$this->boranking();
/* doch in soranking, da es sonst nicht tut ;-)		
		foreach(array(
				'route' => 'route',
				'route_result'  => 'route_result',
			) as $var => $class)
		{
			$egw_name = $class;
			if (!is_object($GLOBALS['egw']->$egw_name))
			{
				$GLOBALS['egw']->$egw_name =& new $class('ranking.'.$class,$this->config['ranking_db_charset'],$this->db,$this->config['vfs_pdf_dir']);
			}
			$this->$var =& $GLOBALS['egw']->$egw_name;
		}
*/
		$this->order_nums = array(
			0 => lang('Qualification'),
			1 => lang('2. Qualification'),
		);
		for($i = 2; $i <= 10; ++$i)
		{
			$this->order_nums[$i] = lang('%1. Heat',$i);
		}
		$this->order_nums[-1] = lang('General result');

		$this->plus_labels = array(
			0 =>    '',
			1 =>    '+ '.lang('plus'),
			'-1' => '- '.lang('minus'),
			TOP_PLUS  => lang('Top'),
		);
		$this->plus = array(
			0 =>    '',
			1 =>    '+',
			'-1' => '-',
			TOP_PLUS => lang('Top'),
		);
	}
		
	/**
	 * Generate a startlist for the given competition, category and heat (route_order)
	 * 
	 * reimplented from boranking to support startlist from further heats and to store the startlist via route_result
	 *
	 * @param int/array $comp WetId or complete comp array
	 * @param int/array $cat GrpId or complete cat array
	 * @param int $route_order 0/1 for qualification, 2, 3, ... for further heats
	 * @param int $route_type=ONE_QUAL ONE_QUALI, TWO_QUALI_HALF or TWO_QUALI_ALL
	 * @param int $discipline='lead' 'lead', 'speed', 'boulder'
	 * @return boolean true if the startlist has been successful generated AND saved, false otherwise
	 */
	function generate_startlist($comp,$cat,$route_order,$route_type=ONE_QUALI,$discipline='lead')
	{
		$keys = array(
			'WetId' => is_array($comp) ? $comp['WetId'] : $comp,
			'GrpId' => is_array($cat) ? $cat['GrpId'] : $cat,
			'route_order' => $route_order,
		);
		if (!$comp || !$cat || !is_numeric($route_order) ||
			!$this->acl_check($comp['nation'],EGW_ACL_RESULT,$comp) ||	// permission denied		
			!$this->route->read($keys) ||	// route does not exist
			$this->has_results($keys))		// route already has a result
		{
			//echo "failed to generate startlist"; _debug_array($keys);
			return false;
		}
		// delete existing starters
		$this->route_result->delete($keys);
		
		if ($route_order >= 2 || 	// further heat --> startlist from reverse result of previous heat
			$route_order == 1 && $route_type == TWO_QUALI_ALL)	// 2. Quali uses same start-order
		{
			return $this->_startlist_from_previous_heat($keys,$route_order >= 2,$discipline == 'speed');
		}
		if (!is_array($comp)) $comp = $this->comp->read($comp);
		if (!is_array($cat)) $cat = $this->cats->read($cat);
		if (!$comp || !$cat) return false;
		
		// read startlist from result-store or generate it with standard values
		unset($keys['route_order']);
		if (!$this->result->has_startlist($keys) && !parent::generate_startlist($comp,$cat,$route_type == TWO_QUALI_HALF ? 2 : 1))
		{
			return false;
		}
		$starters =& $this->result->read($keys+array('platz=0 AND pkt > 64'),'',true,'GrpId,pkt,nachname,vorname');
		
		foreach((array)$starters as $starter)
		{
			if (!($start_order = $this->pkt2start($starter['pkt'],1+$route_order)))
			{
				continue;	// wrong route
			}
			$this->route_result->init(array(
				'WetId' => $starter['WetId'],
				'GrpId' => $starter['GrpId'],
				'route_order' => $route_order,
				'PerId' => $starter['PerId'],
				'start_order' => $start_order,
			));
			$this->route_result->save();
		}
		return true;
	}
	
	/**
	 * Startorder for the ko-system, first key is the total number of starters,
	 * second key is the place with the startorder as value
	 *
	 * @var array
	 */
	var $ko_start_order=array(
		16 => array(
			1 => 1,  16 => 2,
			8 => 3,  9  => 4,
			4 => 5,  13 => 6,
			5 => 7,  12 => 8,
			2 => 9,  15 => 10,
			7 => 11, 10 => 12,
			3 => 13, 14 => 14,
			6 => 15, 11 => 16,
		),
		8 => array(
			1 => 1, 8 => 2,
			4 => 3, 5 => 4,
			2 => 5, 7 => 6,
			3 => 7, 6 => 8,
		),
		4 => array(
			1 => 1, 4 => 2,
			2 => 3, 3 => 4,
		),
	);
	/**
	 * Generate a startlist from the result of a previous heat
	 * 
	 * @internal use generate_startlist
	 * @param array $keys values for WetId, GrpId and route_order
	 * @param boolean $reverse=true reverse of the result or same as the previous heat
	 * @param boolean $ko_system=false use ko-system
	 * @return boolean true if the startlist has been successful generated AND saved, false otherwise
	 */
	function _startlist_from_previous_heat($keys,$reverse=true,$ko_system=false)
	{
		$prev_keys = array(
			'WetId' => $keys['WetId'],
			'GrpId' => $keys['GrpId'],
			'route_order' => $keys['route_order']-1,
		);
		if ($prev_keys['route_order'] == 1 && !$this->route->read($prev_keys))
		{
			$prev_keys['route_order'] = 0;
		}
		if (!($prev_route = $this->route->read($prev_keys)) || !$this->has_results($prev_keys) ||
			$ko_system && !$prev_route['route_quota'])
		{
			if (!$ko_system || !--$prev_keys['route_order'] || !($prev_route = $this->route->read($prev_keys)) ||
				!$this->has_results($prev_keys))
			{
				//echo "failed to generate startlist from"; _debug_array($prev_keys); _debug_array($prev_route);
				return false;	// prev. route not found or no result
			}
		}
		if ($prev_route['route_type'] == TWO_QUALI_HALF && $keys['route_order'] == 2)
		{
			$prev_keys['route_order'] = array(0,1);		// use both quali routes
		}
		if ($prev_route['route_quota'] && $prev_route['route_type'] != TWO_QUALI_ALL)
		{
			if (!$ko_system || $prev_route['route_quota'] != 2 || $prev_route['route_order']+2 == $keys['route_order'])
			{
				$prev_keys[] = 'result_rank <= '.(int)$prev_route['route_quota'];
			}
			else	// small final
			{
				$prev_keys[] = 'result_rank > '.(int)$prev_route['route_quota'];
			}
		}
		$cols = 'PerId,start_number,result_rank';
		if ($prev_route['route_quota'] == 1 || !$reverse && !$ko_system || 	// superfinal or 2. Quali
			$ko_system && $keys['route_order'] > 2)
		{
			$order_by = 'start_order';			// --> same starting order as before !
		}
		else
		{
			if ($ko_system)
			{
				$order_by = 'result_rank';
			}
			// quali on two routes with multiplied ranking
			elseif($prev_route['route_type'] == TWO_QUALI_ALL && $keys['route_order'] == 2)
			{
				$join = " JOIN {$this->route_result->table_name} r2 ON {$this->route_result->table_name}.WetId=r2.WetId".
					" AND {$this->route_result->table_name}.GrpId=r2.GrpId AND r2.route_order=0".
					" AND {$this->route_result->table_name}.PerId=r2.PerId";

				$order_by = $this->route_result->table_name.'.result_rank * r2.result_rank DESC';
				// just the col-name is ambigues
				foreach($prev_keys as $col => $val)
				{
					$prev_keys[] = $this->route_result->table_name.'.'.
						$this->db->expression($this->route_result->table_name,array($col => $val));
					unset($prev_keys[$col]);
				}
				$cols = array(
					$this->route_result->table_name.'.PerId AS PerId',
					$this->route_result->table_name.'.start_number AS start_number',
				);
				if ($prev_route['route_quota'])		// otherwise we can not limit the number of starters
				{
					$cols[] = $this->route_result->table_name.'.result_rank * r2.result_rank AS quali_points';
				}
			}
			else
			{
				$order_by = 'result_rank DESC';		// --> reversed result
			}
			if (($comp = $this->comp->read($keys['WetId'])) && 
				($ranking_sql = $this->_ranking_sql($keys['GrpId'],$comp['datum'],$this->route_result->table_name.'.PerId')))
			{
				$order_by .= ','.$ranking_sql.' DESC';	// --> reverse of the ranking
			}
			$order_by .= ',RAND()';				// --> randomized
		}
		$starters =& $this->route_result->search('',$cols,$order_by,'','',false,'AND',false,$prev_keys,$join);
		
		// ko-system: ex aquos on last place are NOT qualified, instead we use wildcards
		if ($ko_system && $keys['route_order'] == 2 && count($starters) > $prev_route['route_quota'])
		{
			$max_rank = $starters[count($starters)-1]['result_rank']-1;
		}
		$start_order = 1;
		foreach($starters as $n => $data)
		{
			// applying a quota for TWO_QUALI_ALL, taking ties into account!
			if (isset($data['quali_points']) && count($starters)-$n > $prev_route['route_quota'] && 
				$data['quali_points'] > $starters[count($starters)-$prev_route['route_quota']]['quali_points'])
			{
				//echo "<p>ignoring: n=$n, points={$data['quali_points']}, starters[".(count($starters)-$prev_route['route_quota'])."]['quali_points']=".$starters[count($starters)-$prev_route['route_quota']]['quali_points']."</p>\n";
				continue;
			}
			if ($ko_system && $keys['route_order'] == 2)	// first final round in ko-sytem
			{
				if (!isset($this->ko_start_order[$prev_route['route_quota']])) return false;
				if ($max_rank)
				{
					if ($data['result_rank'] > $max_rank) break;
					if ($start_order <= $prev_route['route_quota']-$max_rank)
					{
						$data['result_time'] = WILDCARD_TIME;
					}
				}
				$data['start_order'] = $this->ko_start_order[$prev_route['route_quota']][$start_order++];
			}
			else
			{
				$data['start_order'] = $start_order++;
			}
			$this->route_result->init($keys);
			unset($data['result_rank']);
			$this->route_result->save($data);
		}
		if ($max_rank)	// fill up with wildcards
		{
			while($start_order <= $prev_route['route_quota'])
			{
				$this->route_result->init($keys);
				$this->route_result->save(array(
					'PerId' => -$start_order,	// has to be set and unique (per route) for each wildcard
					'start_order' => $this->ko_start_order[$prev_route['route_quota']][$start_order++],
					'result_time' => ELIMINATED_TIME,
				));
			}
		}
		return true;
	}

	/**
	 * Get the ranking as an sql statement, to eg. order by it
	 *
	 * @param int/array $cat category
	 * @param string $stand date of the ranking as Y-m-d string
	 * @return string sql or null for no ranking
	 */
	function _ranking_sql($cat,$stand,$PerId='PerId')
	{
	 	$ranking =& $this->ranking($cat,$stand,$nul,$nul,$nul,$nul,$nul,$nul,$mode == 2 ? $comp['serie'] : '');
		if (!$ranking) return null;
		
		$sql = 'CASE '.$PerId;	 	
	 	foreach($ranking as $data)
	 	{
	 		$sql .= ' WHEN '.$data['PerId'].' THEN '.$data['platz'];
	 	}
	 	$sql .= ' ELSE 9999';	// unranked competitors should be behind the ranked ones
		$sql .= ' END';

	 	return $sql;
	}

	/**
	 * Updates the result of the route specified in $keys
	 *
	 * @param array $keys WetId, GrpId, route_order
	 * @param array $results PerId => data pairs
	 * @param int $route_type=null ONE_QUALI, TWO_QUALI_ALL, TWO_QUALI_HALF
	 * @param string $discipline='lead' 'lead', 'speed', 'boulder'
	 * @param array $old_values values at the time of display, to check if somethings changed
	 * 		default is null, which causes save_result to read the results now. 
	 * 		If multiple people are updating, you should provide the result of the time of display, 
	 * 		to not accidently overwrite results entered by someone else!
	 * @return boolean/int number of changed results or false on error
	 */
	function save_result($keys,$results,$route_type=null,$discipline='lead',$old_values=null)
	{
		$this->error = null;

		if (!$keys || !$keys['WetId'] || !$keys['GrpId'] || !is_numeric($keys['route_order']) ||		
			!($comp = $this->comp->read($keys['WetId'])) ||
			!$this->acl_check($comp['nation'],EGW_ACL_RESULT,$comp)) // permission denied
		{
			return $this->error = false;
		}
		//echo "<p>boresult::save_result(".print_r($keys,true).",,$route_type,'$discipline')</p>\n"; _debug_array($results);
		if (is_null($old_values))
		{
			$keys['PerId'] = array_keys($results);
			$old_values = $this->route_result->search($keys,'*');
		}
		$modified = 0;
		foreach($results as $PerId => $data)
		{
			$keys['PerId'] = $PerId;
			
			foreach($old_values as $old) if ($old['PerId'] == $PerId) break;
			if ($old['PerId'] != $PerId) unset($old);

			// to also check the result_details
			if ($data['result_details']) $data += $data['result_details'];
			if ($old && $old['result_details']) $old += $old['result_details'];
			
			if (isset($data['top1']))	// boulder result
			{
				for ($i=1; $i <= 6 && isset($data['top'.$i]); ++$i)
				{
					if ($data['top'.$i] && (int)$data['top'.$i] < (int)$data['zone'.$i])
					{
						$this->error[$PerId]['zone'.$i] = lang('Can NOT be higher as top!');
					}
				}
			}

			foreach($data as $key => $val)
			{
				// something changed?
				if ($key != 'result_details' && (!$old && (string)$val !== '' || (string)$old[$key] != (string)$val) && 
					($key != 'result_plus' || $data['result_height'] || $val == TOP_PLUS || $old['result_plus'] == TOP_PLUS))
				{
					if ($key == 'start_number' && strchr($val,'+') !== false)
					{
						$this->set_start_number($keys,$val);
						++$modified;
						continue;
					}
					//echo "<p>--> saving $PerId because $key='$val' changed, was '{$old[$key]}'</p>\n";
					$data['result_modified'] = time();
					$data['result_modifier'] = $this->user;

					$this->route_result->init($old ? $old : $keys);
					$this->route_result->save($data);
					++$modified;
					break;
				}
			}
		}
		// always trying the update, to be able to eg. incorporate changes in the prev. heat
		//if ($modified)	// update the ranking only if there are modifications
		{
			unset($keys['PerId']);
			
			if ($keys['route_order'] == 2 && is_null($route_type))	// check the route_type, to know if we have a countback to the quali
			{
				$route = $this->route->read($keys);
				$route_type = $route['route_type'];
			}
			$n = $this->route_result->update_ranking($keys,$route_type,$discipline);
			//echo '<p>--> '.($n !== false ? $n : 'error, no')." places changed</p>\n";
		}
		return $modified ? $modified : $n;
	}

	/**
	 * Set start-number of a given and the following participants
	 *
	 * @param array $keys 'WetId','GrpId', 'route_order', 'PerId'
	 * @param string $number [start]+increment
	 */
	function set_start_number($keys,$number)
	{
		$PerId = $keys['PerId'];
		unset($keys['PerId']);
		list($start,$increment) = explode('+',$number);
		foreach($this->route_result->search($keys,false,'start_order') as $data)
		{
			if (!$PerId || $data['PerId'] == $PerId)
			{
				if ($data['PerId'] == $PerId && $start)
				{
					$data['start_number'] = $start;
				}
				else
				{
					$data['start_number'] = is_numeric($increment) ? $last + $increment : $last;
				}
				$this->route_result->save($data);
				unset($PerId);
			}
			$last = $data['start_number'];
		}
	}

	/**
	 * Check if a route has a result or a startlist ($startlist_only == true)
	 *
	 * @param array $keys WetId, GrpId, route_order
	 * @param boolean $startlist_only=false check of startlist only (not result)
	 * @return boolean true if there's a at least particial result, false if thers none, null if $key is not valid
	 */
	function has_results($keys,$startlist_only=false)
	{
		if (!$keys || !$keys['WetId'] || !$keys['GrpId'] || !is_numeric($keys['route_order'])) return null;
		
		if (count($keys) > 3) $keys = array_intersect_key($keys,array('WetId'=>0,'GrpId'=>0,'route_order'=>0,'PerId'=>0));

		if (!$startlist_only) $keys[] = 'result_rank IS NOT NULL';
		
		return (boolean) $this->route_result->search($keys);
	}
	
	/**
	 * Check if a route has a startlist
	 *
	 * @param array $keys WetId, GrpId, route_order
	 * @param boolean $startlist_only=false check of startlist only (not result)
	 * @return boolean true if there's a at least particial result, false if thers none, null if $key is not valid
	 */
	function has_startlist($keys)
	{
		return $keys['route_order'] == -1 ? false : $this->has_results($keys,true);
	}
	
	/**
	 * Delete a participant from a route and renumber the starting-order of the following participants
	 *
	 * @param array $keys required 'WetId', 'PerId', possible 'GrpId', 'route_number'
	 * @return boolean true if participant was successful deleted, false otherwise
	 */
	function delete_participant($keys)
	{
		if (!$keys['WetId'] || !$keys['PerId'] ||
			!($comp = $this->comp->read($keys['WetId'])) ||
			!$this->acl_check($comp['nation'],EGW_ACL_RESULT,$comp) ||
			$this->has_results($keys))
		{ 
			return false; // permission denied
		}
		return $this->route_result->delete_participant($keys);
	}
	
	/**
	 * Download a route as csv file
	 *
	 * @param array $keys WetId, GrpId, route_order
	 */
	function download($keys)
	{
		if (($route = $this->route->read($keys)) &&
			($cat = $this->cats->read($keys['GrpId'])) &&
			($comp = $this->comp->read($keys['WetId'])))
		{
			$keys['route_type'] = $route['route_type'];
			$keys['discipline'] = $comp['discipline'] ? $comp['discipline'] : $cat['discipline'];
			$result = $this->has_results($keys);
			$athletes =& $this->route_result->search('',false,$result ? 'result_rank' : 'start_order','','',false,'AND',false,$keys);
			//_debug_array($athletes); return;
			
			$stand = $comp['datum'];
 			$this->ranking($cat,$stand,$nul,$test,$ranking,$nul,$nul,$nul);

			$browser =& CreateObject('phpgwapi.browser');
			$browser->content_header($cat['name'].' - '.$route['route_name'].'.csv','text/comma-separated-values');
			$name2csv = array(
				'WetId'    => 'comp',
				'GrpId'    => 'cat',
				'route_order' => 'heat',
				'PerId'    => 'athlete',
				'result_rank'    => 'place',
				'category',
				'route',	
				'start_order' => 'startorder',
				'nachname' => 'lastname',
				'vorname'  => 'firstname',
				'nation'   => 'nation',
				'birthyear' => 'birthyear',
				'ranking',
				'ranking-points',
				'start_number' => 'startnumber',
				'result' => 'result',
			);
			if ($route['route_num_problems'])	// results of each boulder
			{
				for ($i = 1; $i <= $route['route_num_problems']; ++$i)
				{
					$name2csv['boulder'.$i] = 'boulder'.$i;
				}
			}
			echo implode(';',$name2csv)."\n";
			$charset = $GLOBALS['egw']->translation->charset();
			foreach($athletes as $athlete)
			{
				$values = array();
				foreach($name2csv as $name => $csv)
				{
					switch($csv)
					{
						case 'category':
							$val = $cat['name'];
							break;
						case 'ranking':
							$val = $ranking[$athlete['PerId']]['platz'];
							break;
						case 'ranking-points':
							$val = isset($ranking[$athlete['PerId']]) ? sprintf('%1.2lf',$ranking[$athlete['PerId']]['pkt']) : '';
							break;
						case 'route':
							$val = $route['route_name'];
							break;
						default:
							$val = $athlete[$name];
					}
					if (strchr($val,';') !== false)
					{
						$val = '"'.str_replace('"','',$val).'"';
					}
					$values[$csv] = $val;
				}
				// convert by default to iso-8859-1, as this seems to be the default of excel
				echo $GLOBALS['egw']->translation->convert(implode(';',$values),$charset,
					$_GET['charset'] ? $_GET['charset'] : 'iso-8859-1')."\n";
			}
			$GLOBALS['egw']->common->egw_exit();
		}
	}

	/**
	 * Upload a route as csv file
	 *
	 * @param array $keys WetId, GrpId, route_order
	 * @param string $file uploaded file
	 * @param string/int error message or number of lines imported
	 */
	function upload($keys,$file)
	{
		if (!$keys || !$keys['WetId'] || !$keys['GrpId'] || !is_numeric($keys['route_order'])) // permission denied
		{
			return lang('Permission denied !!!');
		}
		$csv = $this->parse_csv($keys,$file);
		
		if (!is_array($csv)) return $csv;
		
		$this->route_result->delete(array(
			'WetId'    => $keys['WetId'],
			'GrpId'    => $keys['GrpId'],
			'route_order' => $keys['route_order'],
		));
		//_debug_array($lines);
		foreach($lines as $line)
		{
			$this->route_result->init($line);
			$this->route_result->save(array(
				'result_modifier' => $this->user,
				'result_modified' => time(),
			));
		}
		return count($lines);
	}
	
	/**
	 * Convert a result-string into array values, as used in our results
	 *
	 * @internal 
	 * @param array $arr result, boulder1, ..., boulderN
	 * @param string $discipline lead, speed or boulder
	 * @return array
	 */
	function _csv2result($arr,$discipline)
	{
		$result = array();
		
		$str = trim(str_replace(',','.',$arr['result']));		// remove space and allow to use comma instead of dot as decimal del.
		
		if ($str === '' || is_null($str)) return $result;	// no result, eg. not climed so far
		
		switch($discipline)
		{
			case 'lead':
				if (strstr(strtoupper($str),'TOP'))
				{
					$result['result_plus'] = TOP_PLUS;
					$result['result_height'] = TOP_HEIGHT;
				}
				else
				{
					$result['result_height'] = (double) $str;
					switch(substr($str,-1))
					{
						case '+': $result['result_plus'] = 1; break;
						case '-': $result['result_plus'] = -1; break;
						default:  $result['result_plus'] = 0; break;
					}
				}
				break;
			
			case 'speed':
				$result['result_time'] = is_numeric($str) ? (double) $str : ELIMINATED_TIME;
				break;
				
			case 'boulder':	// #t# #b#
				list($top,$bonus) = explode(' ',$str);
				list($top,$top_tries) = explode('t',$top);
				list($bonus,$bonus_tries) = explode('b',$bonus);
				$result['result_top'] = $top ? 100 * $top - $top_tries : null;
				$result['result_zone'] = 100 * $bonus - $bonus_tries;
				for($i = 1; $i <= 6 && array_key_exists('boulder'.$i,$arr); ++$i)
				{
					if (!($boulder = $arr['boulder'.$i])) continue;
					if ($boulder{0} == 't')
					{
						$result['top'.$i] = (int) substr($boulder,1);
						list(,$boulder) = explode(' ',$boulder);
					}
					$result['zone'.$i] = (int) substr($boulder,1);
				}
				break;
		}
		return $result;
	}
	
	/**
	 * Import the general result of a competition into the ranking
	 *
	 * @param array $keys WetId, GrpId, discipline, route_type, route_order=-1
	 * @return string message
	 */
	function import_ranking($keys)
	{
		if (!$keys['WetId'] || !$keys['GrpId'] || !is_numeric($keys['route_order']))
		{
			return false;
		}
		foreach($this->route_result->search('',false,'result_rank','','',false,'AMD',false,array(
			'WetId' => $keys['WetId'],
			'GrpId' => $keys['GrpId'],
			'route_order' => $keys['route_order'],
			'discipline' => $keys['discipline'],
			'route_type' => $keys['route_type'],
		)) as $row)
		{
			if ($row['result_rank']) $result[$row['PerId']] = $row['result_rank'];
		}
		//_debug_array($result);
		return parent::import_ranking($keys,$result);
	}
}