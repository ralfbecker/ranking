<?php
/**
 * eGroupWare digital ROCK Rankings - result business object/logic
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2007 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$ 
 */

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
	/**
	 * Logfile for the bridge to the rock programms running via async service
	 * Set to null to switch it of.
	 *
	 * @var string
	 */
	var $rock_bridge_log = '/tmp/rock_bridge.log';

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
	 * @param int $max_compl=999 maximum number of climbers from the complimentary list
	 * @return int/boolean number of starters, if startlist has been successful generated AND saved, false otherwise
	 */
	function generate_startlist($comp,$cat,$route_order,$route_type=ONE_QUALI,$discipline='lead',$max_compl=999)
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
		if ($route_order >= 2 || 	// further heat --> startlist from reverse result of previous heat
			$route_order == 1 && $route_type == TWO_QUALI_ALL)	// 2. Quali uses same start-order
		{
			// delete existing starters
			$this->route_result->delete($keys);
			return $this->_startlist_from_previous_heat($keys,
				$discipline == 'boulder' && $route_order == 2 ? 'result' :	// boulder 1/2-final --> by quali result
				($route_order >= 2 ? 'reverse' : 'previous'),	// after quali reversed result, otherwise as previous heat
				$discipline == 'speed',$discipline);						// speed --> use ko-system for the final
		}
		// from now on only quali startlist from registration
		if (!is_array($comp)) $comp = $this->comp->read($comp);
		if (!is_array($cat)) $cat = $this->cats->read($cat);
		if (!$comp || !$cat) return false;

		// depricated startlist stored in the result
		if ($this->result->has_startlist(array(
			'WetId' => $keys['WetId'],
			'GrpId' => $keys['Grpid'],
		)))
		{
			// delete existing starters
			$this->route_result->delete($keys);

			$starters =& $this->result->read(array(
				'WetId' => $keys['WetId'],
				'GrpId' => $keys['Grpid'],
				'platz=0 AND pkt > 64'
			),'',true,'GrpId,pkt,nachname,vorname');
			
			return $this->_store_startlist($starters,$route_order);
		}
		// preserv an existing quali-startorder (not ranked competitiors)
		$old_startlist = array();
		if ($route_type == TWO_QUALI_HALF) $keys['route_order'] = array(0,1);
		foreach((array)$this->route_result->search($keys,'PerId,start_order,start_number,route_order','start_order ASC,route_order ASC') as $starter)
		{
			if ($starter['PerId']) $old_startlist[$starter['PerId']] = $starter;
		}
		// delete existing starters
		$this->route_result->delete($keys);

		// generate a startlist, without storing it in the result store
		// ToDo: make the number of athletes from the complementary list configurable
		$starters =& parent::generate_startlist($comp,$cat,$route_type == TWO_QUALI_HALF ? 2 : 1,$max_compl,1,$old_startlist);

		$num = $this->_store_startlist($starters[1],$route_type == TWO_QUALI_HALF ? 0 : $route_order);
			
		if ($route_type == TWO_QUALI_HALF)	// automatically generate 2. quali
		{
			$keys['route_order'] = 1;
			if (!$this->route->read($keys))
			{
				$keys['route_order'] = 0;
				$route = $this->route->read($keys);
				$this->route->save(array(
					'route_name'   => '2. '.$route['route_name'],
					'route_order'  => 1,
					'route_status' => STATUS_STARTLIST,
				));
			}
			$this->_store_startlist($starters[2],1);
		}
		return $num;
	}
	
	/**
	 * Store a startlist in route_result table
	 *
	 * @internal 
	 * @param array $starters
	 * @param int $route_order if set only these starters get stored
	 * @return int num starters stored
	 */
	function _store_startlist($starters,$route_order)
	{
		if (!$starters || !is_array($starters))
		{
			return false;
		}
		$num = 0;
		foreach($starters as $starter)
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
			)+(isset($starter['start_number']) ? array(
				'start_number' => $starter['start_number'],
			) : array()));
			if ($this->route_result->save() == 0) $num++;
		}
		return $num;
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
	 * @param string $start_order='reverse' 'reverse' result, like 'previous' heat, as the 'result'
	 * @param boolean $ko_system=false use ko-system
	 * @param string $discipline
	 * @return int/boolean number of starters, if the startlist has been successful generated AND saved, false otherwise
	 */
	function _startlist_from_previous_heat($keys,$start_order='reverse',$ko_system=false,$discipline='lead')
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
		if ($prev_route['route_quota'] == 1 || 				// superfinal
			$start_order == 'previous' && !$ko_system || 	// 2. Quali uses same startorder
			$ko_system && $keys['route_order'] > 2)			// speed-final
		{
			$order_by = 'start_order';						// --> same starting order as previous heat!
		}
		else
		{
			if ($ko_system || $start_order == 'result')		// first speed final or start_order by result (eg. boulder 1/2-f)
			{
				$order_by = 'result_rank';					// --> use result of previous heat
			}
			// quali on two routes with multiplied ranking
			elseif($prev_route['route_type'] == TWO_QUALI_ALL && $keys['route_order'] == 2)
			{
				$cols = array();
				$prev_keys['route_order'] = 0;
				$join = $this->route_result->_general_result_join(array(
					'WetId' => $keys['WetId'],
					'GrpId' => $keys['GrpId'],
				),$cols,$order_by,$route_names,$prev_route['route_type'],$discipline,array());
				
				$order_by = str_replace(',nachname ASC,vorname ASC','',$order_by);	// we dont want to order alphabetical, we have to add RAND()
				$order_by .= ' DESC';	// we need reverse order

				// just the col-name is ambigues
				foreach($prev_keys as $col => $val)
				{
					$prev_keys[] = $this->route_result->table_name.'.'.
						$this->db->expression($this->route_result->table_name,array($col => $val));
					unset($prev_keys[$col]);
				}
				foreach($cols as $key => $col)
				{
					if (strpos($col,'quali_points')===false) unset($cols[$key]);	// remove all cols but the quali_points
				}
				$cols[] = $this->route_result->table_name.'.PerId AS PerId';
				$cols[] = $this->route_result->table_name.'.start_number AS start_number';
			}
			else
			{
				$order_by = 'result_rank DESC';		// --> reversed result
			}
			if (($comp = $this->comp->read($keys['WetId'])) && 
				($ranking_sql = $this->_ranking_sql($keys['GrpId'],$comp['datum'],$this->route_result->table_name.'.PerId')))
			{
				$order_by .= ','.$ranking_sql.($start_order != 'result' ? ' DESC' : '');	// --> use the (reversed) ranking
			}
			$order_by .= ',RAND()';					// --> randomized
		}
		$starters =& $this->route_result->search('',$cols,$order_by,'','',false,'AND',false,$prev_keys,$join);

		// ko-system: ex aquos on last place are NOT qualified, instead we use wildcards
		if ($ko_system && $keys['route_order'] == 2 && count($starters) > $prev_route['route_quota'])
		{
			$max_rank = $starters[count($starters)-1]['result_rank']-1;
		}
		$start_order = 1;
		$half_starters = count($starters)/2;
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
			// EYS non-onsight modus: 2. half first
			elseif($prev_route['route_type'] == TWO_QUALI_ALL && $keys['route_order'] == 1)
			{
				if ($start_order <= floor($half_starters))
				{
					$data['start_order'] = $start_order+ceil($half_starters);
				}
				else
				{
					$data['start_order'] = $start_order-floor($half_starters);
				}
				++$start_order;
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
		return $start_order-1;
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
						case 'result':
							$val = str_replace(array('&nbsp;',' '),'',$athlete['result']);
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
		foreach($csv as $line)
		{
			$this->route_result->init($line);
			$this->route_result->save(array(
				'result_modifier' => $this->user,
				'result_modified' => time(),
			));
		}
		return count($csv);
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
	
	/**
	 * Gets called via the async service if an automatic import from the rock programms is configured
	 *
	 * @param array $hook_data
	 */
	function import_from_rock($hook_data)
	{
		//echo "import_from_rock"; _debug_array($this->config);
		$this->_bridge_log("**** bridge run started");
		foreach($this->config as $name => $value) if (substr($name,0,11)=='rock_import') $this->_bridge_log("config[$name]='$value'");
		
		if (!$this->config['rock_import_comp'] || !($comp = $this->comp->read($this->config['rock_import_comp'])))
		{
			$this->_bridge_log("no competition configured or competition ({$this->config['rock_import_comp']}) not found!");
			return;
		}
		
		for ($n = 1; $n <= 2; ++$n)
		{
			if (!($rroute = $this->config['rock_import'.$n]))
			{
				$this->_bridge_log("$n: No route configured!");
				continue;
			}
			
			list(,$rcomp) = explode('.',$rroute);
			$year = 2000 + (int) $rcomp;
			$file = $this->config['rock_import_path'].'/'.$year.'/'.$rcomp.'/'.$rroute.'.php';
			
			unset($route); unset($tn);
			if (!file_exists($file))
			{
				$this->_bridge_log("$n: File '$file' not found!");
				continue;
			}
			include($file);

			if (!is_array($route) || !$route['teilnehmer'])
			{
				$this->_bridge_log("$n: File '$file' does not include a rock route or participants!");
				continue;
			}
			
			if (!$this->config['rock_import_cat'.$n] || !($cat = $this->cats->read($this->config['rock_import_cat'.$n])) ||
				!in_array($cat['rkey'],$comp['gruppen']) || $route['GrpId'] != $cat['GrpId'])
			{
				//_debug_array($cat);
				//_debug_array($comp);
				//$route['teilnehmer'] = 'not shown'; _debug_array($route);
				$this->_bridge_log("$n: Category not configured, not belonging to the competition or not found!\n");
				continue;
			}
			$discipline = $comp['discipline'] ? $comp['discipline'] : $cat['discipline'];
			$route_imported = $this->_rock2route($route);

			if (!$this->route->read($keys = array(
				'WetId' => $comp['WetId'],
				'GrpId' => $cat['GrpId'],
				'route_order' => (int)$this->config['rock_import_route'.$n],
			)))
			{
				// create a new route
				$this->route->init($keys);
				$this->route->save($route_imported);
				//_debug_array($this->route->data);
			}
			elseif($this->route->data['route_status'] == STATUS_RESULT_OFFICIAL)
			{
				$error .= "$n: Result already offical!\n";
				continue;	// we dont change the result if it's offical!
			}
			else
			{
				// incorporate changes, not sure if we should do that automatic ???
				unset($route_imported['route_type']);
				unset($route_imported['route_name']);
				$this->route->save($route_imported);
				//_debug_array($this->route->data);
			}
			$this->route_result->delete($keys);
			foreach($route['teilnehmer'] as $PerId => $data)
			{
				$keys['PerId'] = $PerId;
				$this->route_result->init($keys);
				$this->route_result->save($this->_rock2result($data,$discipline));
			}
			$this->_bridge_log("$n: number of participants imported: ".count($route['teilnehmer']));
		}
		$this->_bridge_log("**** bridge run finished");
	}
	
	/**
	 * translate a rock participant into a result-service result
	 *
	 * @param array $rdata rock participant data
	 * @param string $discipline lead, speed or boulder
	 * @return array
	 */
	function _rock2result($rdata,$discipline)
	{
		list($PerId,$GrpId) = explode('+',$rdata['key']);
		
		$data = array(
			'PerId' => $PerId,
			'GrpId' => $GrpId,
			'start_order' => $rdata['startfolgenr'],
			'start_number' => $rdata['startfolgenr'] != $rdata['startnummer'] ? $rdata['startnummer'] : null,
			'result_rank' =>  $rdata['platz'] ? (int)$rdata['platz'] : null,
			'result_height' => $discipline == 'lead' ? (strstr($rdata['hoehe'],'Top') ? TOP_HEIGHT : 100*substr($rdata['hoehe'],0,-1)) : null,
			'result_plus' => $discipline == 'lead' ? (strstr($rdata['hoehe'],'Top') ? TOP_PLUS : (int)(substr($rdata['hoehe'],-1).'1')) : null,
			'result_time' => $discipline == 'speed' && $rdata['time'][0] ? 100*$rdata['time'][0] : null,
		);
		
		if ($discipline == 'boulder')
		{
			if ($rdata['boulder'][0])
			{
				$data['top1'] = '';		// otherwise the result is not recogniced as a boulder result!
				for($i = 1; $i <= 6; ++$i)
				{
					$result = trim($rdata['boulder'][$i]);
					if ($result{0} == 't')
					{
						list(,$data['top'.$i],,$data['zone'.$i]) = split('[tzb ]',$result);
					}
					else
					{
						$data['zone'.$i] = (string)(int)substr($result,1);
					}
				}
			}
			else
			{
				unset($data['result_rank']);	// otherwise not climbed athlets are already ranked
			}
		}
		return $data;
	}
	
	/**
	 * translate a rock route into a result-service route
	 *
	 * @param array $route rock route-data
	 * @return array
	 */
	function _rock2route($route)
	{
		list($iso_open,$iso_close) = preg_split('/ ?- ?/',$route['isolation'],2);

		return array(
			'route_name' => $route['bezeichnung'],
			'route_judge' => $route['jury'][0],
			'route_status' => $route['frei_str'] ? STATUS_RESULT_OFFICIAL : STATUS_STARTLIST,
			'route_type' => ONE_QUALI,	// ToDo: set it from the erge_modus
			'route_iso_open' => $iso_open,
			'route_iso_open' => $iso_close,
			'route_start' => $route['start'],
			'route_result' => $route['frei_str'],
			'route_quota' => $route['quote'],
			'route_num_problems' => substr($route['erge_modus'],0,9) == 'BoulderZ:' ?
				count(explode('+',substr($route['erge_modus'],9))) : null,
		);
	}
	
	function _bridge_log($str)
	{
		if ($this->rock_bridge_log && ($f = @fopen($this->rock_bridge_log,'a+')))
		{
			fwrite ($f,date('Y-m-d H:i:s: ').$str."\n");
			fclose($f);
		}
	}
	
	/**
	 * Get the default quota for a given disciplin, route_order and optional quali_type or participants number
	 *
	 * @param string $discipline 'speed', 'lead' or 'boulder'
	 * @param int $route_order
	 * @param int $quali_type=null TWO_QUALI_ALL, TWO_QUALI_HALF, ONE_QUALI
	 * @param int $num_participants=null
	 * @return int
	 */
	function default_quota($discipline,$route_order,$quali_type=null,$num_participants=null)
	{
		$quota = null;

		switch($discipline)
		{
			case 'speed':
				if (!is_numeric($num_participants)) break;
				for($n = 16; $n > 1; $n /= 2) if ($num_participants > $n || !$route_order && $num_participants >= $n)
				{
					$quota = $n;
					break;
				}
				break;
				
			case 'lead':
				switch($route_order)
				{
					case 0: $quota = $quali_type == TWO_QUALI_HALF ? 13 : 26; break;	// quali
					case 1: $quota = 13; break;		// 2. quali
					case 2: $quota = 8;  break;		// 1/2-final
				}
				break;
				
			case 'boulder':
				switch($route_order)
				{
					case 0: $quota = $quali_type == TWO_QUALI_HALF ? 10 : 20; break;	// quali
					case 1: $quota = 10; break;		// 2. quali
					case 2: $quota = 6;  break;		// 1/2-final
				}
				break;				
		}
		//echo "<p>boresult::default_quota($discipline,$route_order,$quali_type,$num_participants)=$quota</p>\n";
		return $quota;
	}
	
	/**
	 * Singleton to get a boresult instance
	 *
	 * @return boresult
	 */
	static public function getInstance()
	{
		if (!is_object($GLOBALS['boresult']))
		{
			$GLOBALS['boresult'] = new boresult;
		}
		return $GLOBALS['boresult'];
	}
}