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
		0 => 'unpublished',
		1 => 'startlist',
		2 => 'provisional result',
		3 => 'official result',
	);
	/**
	 * values and labels for route_plus
	 *
	 * @var array
	 */
	var $plus = array(
		0  => '',
		1  => '+ plus',
		'-1' => '- minus',
		TOP_PLUS => 'Top'
	);
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
		for($i = 2; $i <=5; ++$i)
		{
			$this->order_nums[$i] = lang('%1. Heat',$i);
		}
	}
		
	/**
	 * Generate a startlist for the given competition, category and heat (route_order)
	 * 
	 * reimplented from boranking to support startlist from further heats and to store the startlist via route_result
	 *
	 * @param int/array $comp WetId or complete comp array
	 * @param int/array $cat GrpId or complete cat array
	 * @param int $route_order 0/1 for qualification, 2, 3, ... for further heats
	 * @return boolean true if the startlist has been successful generated AND saved, false otherwise
	 */
	function generate_startlist($comp,$cat,$route_order)
	{
		$keys = array(
			'WetId' => is_array($comp) ? $comp['WetId'] : $comp,
			'GrpId' => is_array($cat) ? $cat['GrpId'] : $cat,
			'route_order' => $route_order,
		);
		if (!$comp || !$cat || !is_numeric($route_order) ||
			!$this->route->read($keys) ||	// route does not exist
			$this->has_results($keys))		// route already has a result
		{
			_debug_array($keys);
			return false;
		}
		// delete existing starters
		$this->route_result->delete($keys);
		
		if ($route_order >= 2)	// further heat --> startlist from result or previous heat
		{
			return $this->_startlist_from_previous_heat($keys);
		}
		if (!is_array($comp)) $comp = $this->comp->read($comp);
		if (!is_array($cat)) $cat = $this->cats->read($cat);
		if (!$comp || !$cat) return false;
		
		// read startlist from result-store or generate it with standard values
		unset($keys['route_order']);
		if (!$this->result->has_startlist($keys) || !parent::generate_startlist($comp,$cat,1+$route_order))
		{
			return false;
		}
		$starters =& $this->result->read($keys+array('platz=0 AND pkt > 64'),'',true,'GrpId,pkt,nachname,vorname');
		
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
				'start_number' => $start_order,
			));
			$this->route_result->save();
		}
		return true;
	}
	
	/**
	 * Generate a startlist from the result of a previous heat
	 * 
	 * @internal use generate_startlist
	 * @param array $keys values for WetId, GrpId and route_order
	 * @return boolean true if the startlist has been successful generated AND saved, false otherwise
	 */
	function _startlist_from_previous_heat($keys)
	{
		$prev_keys = array(
			'WetId' => $keys['WetId'],
			'GrpId' => $keys['GrpId'],
			'route_order' => $keys['route_order'] > 2 ? $keys['route_order']-1 : 0,
		);
		if (!($prev_route = $this->route->read($prev_keys)) || !$this->has_results($prev_keys))
		{
			return false;	// prev. route not found or no result
		}
		if ($prev_route['route_quota']) $prev_keys[] = 'result_rank <= '.(int)$prev_route['route_quota'];

		if ($prev_route['route_quota'] == 1)	// superfinal
		{
			$order_by = 'start_order';			// --> same starting order as before !
		}
		else
		{
			$order_by = 'result_rank DESC';		// --> reversed result
			// $order_by .= ',...';				// --> reverse of the ranking  ******** TO DO **********
			$order_by .= ',RAND()';				// --> randomized
		}
		$start_order = 1;
		foreach($this->route_result->search($prev_keys,'PerId,start_number',$order_by) as $data)
		{
			$this->route_result->init($keys);
			$data['start_order'] = $start_order++;
			$this->route_result->save($data);
		}
		return true;
	}

	/**
	 * Updates the result of the route specified in $keys
	 *
	 * @param array $keys WetId, GrpId, route_order
	 * @param array $results PerId => data pairs
	 * @return boolean/int number of changed results or false on error
	 */
	function save_result($keys,$results)
	{
		if (!$keys || !$keys['WetId'] || !$keys['GrpId'] || !is_numeric($keys['route_order'])) return false;
		
		//echo "<p>boresult::save_result(".print_r($keys,true).")</p>\n"; _debug_array($results);
		$keys['PerId'] = array_keys($results);
		$old_values = $this->route_result->search($keys,'*');

		$modified = 0;
		foreach($results as $PerId => $data)
		{
			$keys['PerId'] = $PerId;
			
			foreach($old_values as $old) if ($old['PerId'] == $PerId) break;
			if ($old['PerId'] != $PerId) unset($old);

			foreach($data as $key => $val)
			{
				// something changed?
				if ((!$old && (string)$val !== '' || (string)$old[$key] != (string)$val) && 
					($key != 'result_plus' || $data['result_height'] || $val == TOP_PLUS || $old['result_plus'] == TOP_PLUS))
				{
					echo "<p>--> saving $PerId because $key='$val' changed, was '{$old[$key]}'</p>\n";
					$data['result_modified'] = time();
					$data['result_modifier'] = $this->user;

					$this->route_result->init($old ? $old : $keys);
					$this->route_result->save($data);
					++$modified;
					break;
				}
			}
		}
		if ($modified)	// update the ranking only if there are modifications
		{
			unset($keys['PerId']);
			$n = $this->route_result->update_ranking($keys);
			echo '<p>--> '.($n !== false ? $n : 'error, no')." places changed</p>\n";
		}
		return $modified;
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
		
		if (count($keys) > 3) $keys = array_intersect_key($keys,array('WetId'=>0,'GrpId'=>0,'route_order'=>0));

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
		return $this->has_results($keys,true);
	}
}
