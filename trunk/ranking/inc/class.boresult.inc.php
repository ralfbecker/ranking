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
	 * @var unknown_type
	 */
	var $stati = array(
		0 => 'unpublished',
		1 => 'startlist',
		2 => 'provisional result',
		3 => 'official result',
	);
/*	var $plus = array(
		0  => '',
		1  => '+',
		'-1' => '-',
	);*/
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
	 * Generate a startlist for the given competition and category
	 *
	 * start/registration-numbers are saved as points in a result with place=0, the points contain:
	 * - registration number in the last 6 bit (< 32 prequalified, >= 32 quota or supplimentary) ($pkt & 63)
	 * - startnumber in the next 8 bits (($pkt >> 6) & 255))
	 * - route in the other bits ($pkt >> 14)
	 *
	 * @param int/array $comp WetId or complete comp array
	 * @param int/array $cat GrpId or complete cat array
	 * @param int $which_route=1 route to use, default 1, can be 2 if quali is done on 2 routes
	 * @return boolean true if the startlist has been successful generated AND saved, false otherwise
	 */
	function startlist_from_registration($comp,$cat,$which_route=1)
	{
		if (!is_array($comp)) $comp = $this->comp->read($comp);
		if (!is_array($cat)) $cat = $this->cats->read($cat);
		if (!$comp || !$cat) return false;
		
		$keys = array(
			'WetId'  => $comp['WetId'],
			'GrpId'  => $cat['GrpId'],
		);
		if (!$this->result->has_startlist($keys)) return false;	// no startlist yet
		
		$starters =& $this->result->read($keys+array('platz=0 AND pkt > 64'),'',true,'GrpId,pkt,nachname,vorname');
		
		if (!$starters) return false;
		
		// delete existing starters
		$this->route_result->delete($keys+array('route_order' => $which_route-1));
		
		foreach($starters as $starter)
		{
			if (!($start_order = $this->pkt2start($starter['pkt'],$which_route)))
			{
				continue;	// wrong route
			}
			$this->route_result->data = array(
				'WetId' => $starter['WetId'],
				'GrpId' => $starter['GrpId'],
				'route_order' => $which_route-1,
				'PerId' => $starter['PerId'],
				'start_order' => $start_order,
				'start_number' => $start_order,
			);
			$this->route_result->save();
		}
		return true;
	}
	
	/**
	 * Updates the result of the route specified in $keys
	 *
	 * @param array $keys WetId, GrpId, route_order
	 * @param aray $results PerId => data pairs
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
		return $modified;
	}
}
