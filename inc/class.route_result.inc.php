<?php
/**************************************************************************\
* eGroupWare - digital ROCK Rankings - Route Result Object                 *
* http://www.egroupware.org, http://www.digitalROCK.de                     *
* Written and (c) by Ralf Becker <RalfBecker@outdoor-training.de>          *
* --------------------------------------------                             *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

require_once(EGW_INCLUDE_ROOT . '/etemplate/inc/class.so_sql.inc.php');
require_once(EGW_INCLUDE_ROOT . '/ranking/inc/class.route.inc.php');

define('TOP_PLUS',9999);
define('TOP_HEIGHT',99999999);
define('ELIMINATED_TIME',999999);
define('WILDCARD_TIME',1);

/**
 * route object
 */
class route_result extends so_sql
{
	var $non_db_cols = array(	// fields in data, not (direct) saved to the db
	);
	var $charset,$source_charset;
	
	var $athlete_join = 'LEFT JOIN Personen ON RouteResults.PerId=Personen.PerId';

	var $rank_lead = 'CASE WHEN result_height IS NULL THEN NULL ELSE (SELECT 1+COUNT(*) FROM RouteResults r WHERE RouteResults.WetId=r.WetId AND RouteResults.GrpId=r.GrpId AND RouteResults.route_order=r.route_order AND (RouteResults.result_height < r.result_height OR RouteResults.result_height = r.result_height AND RouteResults.result_plus < r.result_plus)) END';
	var $rank_boulder = 'CASE WHEN result_top IS NULL AND result_zone IS NULL THEN NULL ELSE (SELECT 1+COUNT(*) FROM RouteResults r WHERE RouteResults.WetId=r.WetId AND RouteResults.GrpId=r.GrpId AND RouteResults.route_order=r.route_order AND (RouteResults.result_top < r.result_top OR RouteResults.result_top = r.result_top AND RouteResults.result_zone < r.result_zone OR RouteResults.result_top IS NULL AND r.result_top IS NULL AND RouteResults.result_zone < r.result_zone OR RouteResults.result_top IS NULL AND r.result_top IS NOT NULL)) END';
	var $rank_speed_quali = 'CASE WHEN result_time IS NULL THEN NULL ELSE (SELECT 1+COUNT(*) FROM RouteResults r WHERE RouteResults.WetId=r.WetId AND RouteResults.GrpId=r.GrpId AND RouteResults.route_order=r.route_order AND RouteResults.result_time > r.result_time) END';
	var $rank_speed_final = 'CASE WHEN result_time IS NULL THEN NULL ELSE 1+(SELECT RouteResults.result_time > r.result_time FROM RouteResults r WHERE RouteResults.WetId=r.WetId AND RouteResults.GrpId=r.GrpId AND RouteResults.route_order=r.route_order AND RouteResults.start_order != r.start_order AND (RouteResults.start_order-1) DIV 2 = (r.start_order-1) DIV 2) END';

	/**
	 * constructor of the competition class
	 */
	function route_result($source_charset='',$db=null)
	{
		//$this->debug = 1;
		$this->so_sql('ranking','RouteResults',$db);	// call constructor of extended class
		
		if ($source_charset) $this->source_charset = $source_charset;
		
		$this->charset = $GLOBALS['egw']->translation->charset();
/*
		foreach(array(
				'athlete'  => 'athlete',
			) as $var => $class)
		{
			$egw_name = $class;
			if (!is_object($GLOBALS['egw']->$egw_name))
			{
				$GLOBALS['egw']->$egw_name =& CreateObject('ranking.'.$class,$source_charset,$this->db,$vfs_pdf_dir);
			}
			$this->$var =& $GLOBALS['egw']->$egw_name;
		}
*/
	}
	
	/**
	 * searches db for rows matching searchcriteria
	 *
	 * '*' and '?' are replaced with sql-wildcards '%' and '_'
	 *
	 * For a union-query you call search for each query with $start=='UNION' and one more with only $order_by and $start set to run the union-query.
	 *
	 * @param array/string $criteria array of key and data cols, OR a SQL query (content for WHERE), fully quoted (!)
	 * @param boolean/string/array $only_keys=true True returns only keys, False returns all cols. or 
	 *	comma seperated list or array of columns to return
	 * @param string $order_by='' fieldnames + {ASC|DESC} separated by colons ',', can also contain a GROUP BY (if it contains ORDER BY)
	 * @param string/array $extra_cols='' string or array of strings to be added to the SELECT, eg. "count(*) as num"
	 * @param string $wildcard='' appended befor and after each criteria
	 * @param boolean $empty=false False=empty criteria are ignored in query, True=empty have to be empty in row
	 * @param string $op='AND' defaults to 'AND', can be set to 'OR' too, then criteria's are OR'ed together
	 * @param mixed $start=false if != false, return only maxmatch rows begining with start, or array($start,$num), or 'UNION' for a part of a union query
	 * @param array $filter=null if set (!=null) col-data pairs, to be and-ed (!) into the query without wildcards
	 * @param string $join='' sql to do a join, added as is after the table-name, eg. "JOIN table2 ON x=y" or
	 *	"LEFT JOIN table2 ON (x=y AND z=o)", Note: there's no quoting done on $join, you are responsible for it!!!
	 * @return boolean/array of matching rows (the row is an array of the cols) or False
	 */
	function &search($criteria,$only_keys=True,$order_by='',$extra_cols='',$wildcard='',$empty=False,$op='AND',$start=false,$filter=null,$join='')
	{
		if (is_array($filter) && array_key_exists('route_type',$filter))	// pseudo-filter to transport the route_type
		{
			$route_type = $filter['route_type'];
			unset($filter['route_type']);
		}
		if (is_array($filter) && array_key_exists('discipline',$filter))	// pseudo-filter to transport the discipline
		{
			$discipline = $filter['discipline'];
			unset($filter['discipline']);
		}
		if (isset($filter['route_order']))
		{
			$route_order =& $filter['route_order'];
		}
		else
		{
			$route_order =& $criteria['route_order'];
		}
		if ($route_order === 0) $route_order = '0';		// otherwise it get's ignored by so_sql;

		if (!$only_keys && !$join || $route_order < 0) 
		{
			$join = $this->athlete_join;
			if (!is_array($extra_cols)) $extra_cols = $extra_cols ? explode(',',$extra_cols) : array();
			$extra_cols += array('vorname','nachname','nation','geb_date','verband','ort');

			if ($route_order > 2 || $route_order == 2 && $route_type != TWO_QUALI_HALF)
			{
				$extra_cols[] = '('.$this->_sql_rank_prev_heat($route_order,$route_type).') AS rank_prev_heat';
			}
			elseif ($route_order < 0)			// general result
			{
				$route_order = $route_type == TWO_QUALI_HALF ? array(0,1) : 0;		// use users from the qualification(s)

				$result_cols = array('result_rank');
				switch($discipline)
				{
					default:
					case 'lead':
						$result_cols[] = 'result_height';
						$result_cols[] = 'result_plus';
						break;
					case 'speed':
						$result_cols[] = 'result_time';
						$result_cols[] = 'start_order';
						break;
					case 'boulder':
						$result_cols[] = 'result_top';
						$result_cols[] = 'result_zone';
						break;
				}
				$order_by_parts = split('[ ,]',$order_by);

				$join .= $this->_general_result_join(array(
					'WetId' => $filter['WetId'] ? $filter['WetId'] : $criteria['WetId'],
					'GrpId' => $filter['GrpId'] ? $filter['GrpId'] : $criteria['GrpId'],
				),$extra_cols,$order_by,$route_names,$route_type,$discipline,$result_cols);
				
				foreach($filter as $col => $val)
				{
					if (!is_numeric($col))
					{
						$filter[] = $this->db->expression($this->table_name,$this->table_name.'.',array($col => $val));
						unset($filter[$col]);
					}
				}
				$filter[] = $this->table_name.'.result_rank IS NOT NULL';
				
				$rows =& parent::search($criteria,$only_keys,$order_by,$extra_cols,$wildcard,$empty,$op,$start,$filter,$join,$need_full_no_count);

				// the general result is always sorted by the overal rank (to get it)
				// now we need to store that rank in result_rank
				$old = null;
				foreach($rows as $n => &$row)
				{
					$row['result_rank0'] = $row['result_rank'];

					if ($row['route_order'] == 1 && $route_type == TWO_QUALI_HALF)	// result is on the 2. Quali
					{
						$row['result'.$row['route_order']] = $row['result'];		// --> move it there for the display
						unset($row['result']);
					}
					// check for ties
					$row['result_rank'] = $old['result_rank'];
					foreach(array_reverse(array_keys($route_names)) as $route_order)
					{
						if (!$old || !$row['result_rank'.$route_order] && $old['result_rank'.$route_order] ||	// 1. place or no result yet
							$old['result_rank'.$route_order] < $row['result_rank'.$route_order])	// or worse place then the previous
						{
							$row['result_rank'] = $n+1;						// --> set the place according to the position in the list
							break;
						}
						// for quali on two routes with half quota, there's no countback to the quali only if there's a result for the 2. heat
						if ($route_type == TWO_QUALI_HALF && $route_order == 2 && $row['result_rank2'])
						{
							break;	// --> not use countback
						}
					}
					$old = $row;
				}
				// now we need to check if user wants to sort by something else
				$order = array_shift($order_by_parts);
				$sort  = array_pop($order_by_parts);
				if ($order != 'result_rank')
				{
					// sort the rows now by the user's criteria
					usort($rows,create_function('$a,$b',$func='return '.($sort == 'DESC' ? '-' : '').
						($this->table_def['fd'][$order] == 'varchar' || in_array($order,array('nachname','vorname','nation','ort')) ? 
						"strcasecmp(\$a['$order'],\$b['$order'])" : 
						"(\$a['$order']-\$b['$order'])").';'));
						//"(\$a['$order'] ? \$a['$order']-\$b['$order'] : -99999999)").';'));
					//echo "<p>order='$order', sort='$sort', func=$func</p>\n";
				}
				elseif($sort == 'DESC')
				{
					$rows = array_reverse($rows);
				}
				$rows['route_names'] = $route_names;
				
				return $rows;
			}
		}
		return parent::search($criteria,$only_keys,$order_by,$extra_cols,$wildcard,$empty,$op,$start,$filter,$join,$need_full_no_count);
	}
	
	/**
	 * Subquery to get the rank in the previous heat
	 *
	 * @param int $route_order
	 * @param int $route_type ONE_QUALI, TWO_QUALI_HALF, TWO_QUALI_ALL
	 * @return string
	 */
	function _sql_rank_prev_heat($route_order,$route_type)
	{
		if ($route_order == 2 && $route_type == TWO_QUALI_ALL)
		{
			return "SELECT p.result_rank * p2.result_rank FROM $this->table_name p".
				" JOIN $this->table_name p2 ON p.WetId=p2.WetId AND p.GrpId=p2.GrpId AND p2.route_order=1 AND p.PerId=p2.PerId".
				" WHERE $this->table_name.WetId=p.WetId AND $this->table_name.GrpId=p.GrpId AND p.route_order=0".
				" AND $this->table_name.PerId=p.PerId";
		}
		return "SELECT result_rank FROM $this->table_name p WHERE $this->table_name.WetId=p.WetId AND $this->table_name.GrpId=p.GrpId AND ".
			'p.route_order '.($route_order == 2 ? 'IN (0,1)' : '='.(int)($route_order-1))." AND $this->table_name.PerId=p.PerId";
	}

	/**
	 * Return join and extra_cols for a general result
	 *
	 * @internal 
	 * @param array $keys values for WetId and GrpId
	 * @param array &$extra_cols
	 * @param string &$order_by
	 * @param array &$route_names route_order => route_name pairs
	 * @param int $route_type ONE_QUALI, TWO_QUALI_HALF or TWO_QUALI_ALL
	 * @param string $discipline 'lead', 'speed', 'boulder'
	 * @param array $result_cols result relevant col
	 * @return string join
	 */
	function _general_result_join($keys,&$extra_cols,&$order_by,&$route_names,$route_type,$discipline,$result_cols)
	{
		if (!is_object($GLOBALS['egw']->route))
		{
			$GLOBALS['egw']->route =& new route($this->source_charset,$this->db);
		}
		$route_names = $GLOBALS['egw']->route->query_list('route_name','route_order',$keys,'route_order');
		
		$order_by = array("$this->table_name.result_rank");	// Quali

		foreach($route_names as $route_order => $label)
		{
			if ($route_order < 2-(int)($route_type==TWO_QUALI_ALL)) continue;	// no need to join the qualification or the general result

			$join .= " LEFT JOIN $this->table_name r$route_order ON $this->table_name.WetId=r$route_order.WetId AND $this->table_name.GrpId=r$route_order.GrpId AND r$route_order.route_order=$route_order AND $this->table_name.PerId=r$route_order.PerId";
			foreach($result_cols as $col)
			{
				$extra_cols[] = "r$route_order.$col AS $col$route_order";
			}
			if ($route_order == 1)	// 2. Quali for route_type==TWO_QUALI_ALL
			{
				// only order is the product of the two quali. routes
				$order_by = array($product = "$this->table_name.result_rank * r$route_order.result_rank");
				$extra_cols[] = "$product AS quali_points";
			}
			else
			{
				$order_by[] = "r$route_order.result_rank";
				$order_by[] = "r$route_order.result_rank IS NULL";
			}
		}
		$order_by = implode(',',array_reverse($order_by)).',nachname ASC,vorname ASC';

		$extra_cols[] = $this->table_name.'.*';		// trick so_sql to return the cols from the quali as regular cols

		return $join;
	}
	
	/**
	 * changes the data from the db-format to our work-format
	 * 
	 * @param array $data if given works on that array and returns result, else works on internal data-array
	 * @return array
	 */
	function db2data($data=0)
	{
		// hack to give the ranking translation of 'Top' to 'Top' precedence over the etemplate one 'Oben'
		if ($GLOBALS['egw_info']['user']['preferences']['common']['lang'] == 'de') $GLOBALS['egw']->translation->lang_arr['top'] = 'Top';
		
		static $plus2string = array(
			-1 => ' -',
			0  => ' &nbsp;',
			1  => '+',
		);
		if (!is_array($data))
		{
			$data =& $this->data;
		}
		if ($data['result_height'] || $data['result_height1'])	// lead result
		{
			$suffix = '';	// general result can have route_order as suffix
			while (isset($data['result_height'.$suffix]) || $suffix < 2)
			{
				if ($data['result_height'.$suffix] == TOP_HEIGHT)
				{
					$data['result_height'.$suffix] = '';
					$data['result_plus'.$suffix]   = TOP_PLUS;
					$data['result'.$suffix]   = lang('Top').'&nbsp;&nbsp;';
				}
				elseif ($data['result_height'.$suffix])
				{
					$data['result_height'.$suffix] *= 0.001;
					$data['result'.$suffix] = sprintf('%4.2lf',$data['result_height'.$suffix]).
						$plus2string[$data['result_plus'.$suffix]];
				}
				++$suffix;
			}
			if (array_key_exists('result_height1',$data) && array_key_exists('result_height',$data))
			{
				// quali on two routes for all --> add rank to result
				foreach(array('',1) as $suffix)
				{
					$data['result'.$suffix] .= ($data['result_plus'.$suffix] == TOP_PLUS ? ' &nbsp;' : '').
						' &nbsp; '.$data['result_rank'.$suffix].'.';
				}
			}
		}
		if ($data['result_detail'])	// boulder result
		{
			$data += unserialize($data['result_detail']);
			unset($data['result_detail']);
			for($i=1; $i <= 6; ++$i)
			{
				$data['boulder'.$i] = ($data['top'.$i] ? 't'.$data['top'.$i].' ' : '').
					($data['zone'.$i] ? 'z'.$data['zone'.$i] : '');
			}
			$suffix = '';	// general result can have route_order as suffix
			while (isset($data['result_zone'.$suffix]) || $suffix < 2 || isset($data['result_zone'.(1+$suffix)]))
			{
				if (isset($data['result_zone'.$suffix]))
				{
					$tops = round($data['result_top'.$suffix] / 100);
					$top_tries = $tops ? $tops * 100 - $data['result_top'.$suffix] : '';
					$zones = round($data['result_zone'.$suffix] / 100);
					$zone_tries = $zones ? $zones * 100 - $data['result_zone'.$suffix] : '';
					$data['result'.$suffix] = $tops.'t'.$top_tries.' '.$zones.'z'.$zone_tries; 
				}
				++$suffix;
			}
		}
		if ($data['result_time'])	// speed result
		{
			$suffix = '';	// general result can have route_order as suffix
			while (isset($data['result_time'.$suffix]) || $suffix < 2 || isset($data['result_time'.(1+$suffix)]))
			{
				if ($data['result_time'.$suffix])
				{
					$data['result_time'.$suffix] *= 0.001;
					if ($data['result_time'.$suffix] == ELIMINATED_TIME)
					{
						$data['eliminated'] = 1;
						$data['result_time'] = null;
						$data['result'.$suffix] = lang('eliminated');
					}
					elseif ($data['result_time'.$suffix] == WILDCARD_TIME)
					{
						$data['eliminated'] = 0;
						$data['result_time'] = null;
						$data['result'.$suffix] = lang('Wildcard');	
					}
					else
					{
						$data['result'.$suffix] = sprintf('%4.2lf',$data['result_time'.$suffix]);
					}
				}
				++$suffix;
			}
		}
		if (!$data['PerId'])	// Wildcard
		{
			$data['PerId'] = -$data['start_order'];
			$data['nachname'] = '-- '.lang('Wildcard').' --';
		}
		$this->_shorten_name($data['nachname']);
		$this->_shorten_name($data['vorname']);
			
		if ($data['geb_date']) $data['birthyear'] = (int)$data['geb_date'];	

		return $data;
	}
	
	/**
	 * Appriviate the name to make it better printable
	 *
	 * @param string &$name
	 * @param int $max=12 maximum length
	 */
	function _shorten_name(&$name,$max=13)
	{
		if (strlen($name) <= $max) return;

		// add a space after each dash or comma, if there's none already
		$name = preg_replace('/([-,]+ *)/','\\1 ',$name);
		
		// check all space separated parts for their length
		$parts = explode(' ',$name);
		foreach($parts as &$part)
		{
			if (strlen($part) > $max)
			{
				$part = substr($part,0,$max-1).'.';
			}
		}
		$name = implode(' ',$parts);		
	}

	/**
	 * changes the data from our work-format to the db-format
	 * 
	 * @param array $data if given works on that array and returns result, else works on internal data-array
	 * @return array
	 */
	function data2db($data=0)
	{
		if (!is_array($data))
		{
			$data =& $this->data;
		}
		if ($data['result_plus'] == TOP_PLUS)	// top
		{
			$data['result_height'] = TOP_HEIGHT;
			$data['result_plus']   = 0;
		}
		elseif ($data['result_height'])
		{
			$data['result_height'] = round(1000 * $data['result_height']);
		}
		else
		{
			unset($data['result_plus']);	// no plus without height
		}
		if (isset($data['eliminated']))
		{
			switch($data['eliminated'])
			{
				case '1': $data['result_time'] = ELIMINATED_TIME; break;
				case '0': $data['result_time'] = WILDCARD_TIME; break;
			}
		}
		if ($data['result_time']) $data['result_time'] = round(1000 * $data['result_time']);

		// saving the boulder results, if there are any
		if (isset($data['top1']))
		{
			$data['result_top'] = $data['result_zone'] = $data['result_detail'] = null;
			for($i = 1; $i <= 6; ++$i)
			{
				if ($data['top'.$i])
				{
					$data['result_top'] += 100 - $data['top'.$i];
					$data['result_detail']['top'.$i] = $data['top'.$i];
					// cant have top without zone or more tries for the zone --> setting zone as top
					if (!$data['zone'.$i] || $data['zone'.$i] > $data['top'.$i]) $data['zone'.$i] = $data['top'.$i];
				}
				if (is_numeric($data['zone'.$i]))
				{
					if ($data['zone'.$i])
					{
						$data['result_zone'] += 100 - $data['zone'.$i];
					}
					elseif (is_null($zone))
					{
						$data['result_zone'] = 0;		// this is to recognice climbers with no zone at all
					}
					$data['result_detail']['zone'.$i] = $data['zone'.$i];
				}
			}
			if (is_array($data['result_detail'])) $data['result_detail'] = serialize($data['result_detail']);
		}
		return $data;
	}
	
	/**
	 * merges in new values from the given new data-array
	 * 
	 * Reimplemented to also merge top1-6 and zone1-6
	 *
	 * @param $new array in form col => new_value with values to set
	 */
	function data_merge($new)
	{
		parent::data_merge($new);
		
		for($i = 1; $i <= 6; ++$i)
		{
			if (isset($new['top'.$i])) $this->data['top'.$i] = $new['top'.$i];
			if (isset($new['zone'.$i])) $this->data['zone'.$i] = $new['zone'.$i];
		}
		if (isset($new['eliminated'])) $this->data['eliminated'] = $new['eliminated'];
	}

	/**
	 * Update the ranking of a given route
	 *
	 * @param array $keys values for keys WetId, GrpId and route_order
	 * @param boolean $do_countback=true should we do a countback on further heats
	 * @param int $route_type=ONE_QUALI ONE_QUALI, TWO_QUALI_HALF, TWO_QUALI_ALL
	 * @param string $discipline='lead' 'lead', 'speed', 'boulder'
	 * @return int/boolean updated rows or false on error (no route specified in $keys)
	 */
	function update_ranking($keys,$route_type=ONE_QUALI,$discipline='lead')
	{
		//echo "<p>update_ranking(".print_r($keys,true),",$route_type,'$discipline')</p>\n";
		if (!$keys['WetId'] || !$keys['GrpId'] || !is_numeric($keys['route_order'])) return false;
		
		$keys = array_intersect_key($keys,array('WetId'=>0,'GrpId'=>0,'route_order'=>0));	// remove other content

		$extra_cols = array();
		switch($discipline)
		{
			default:
			case 'lead':
				$mode = $this->rank_lead;
				$order_by = 'result_height IS NULL,new_rank ASC';
				break;
			case 'speed':
				$order_by = 'result_time IS NULL,new_rank ASC';	
				if ($keys['route_order'] < 2)
				{
					$mode = $this->rank_speed_quali;
				}
				else
				{
					$mode = $this->rank_speed_final;
					$order_by .= ',result_time ASC';
					$extra_cols[] = 'result_time';
				}
				break;
			case 'boulder':
				$mode = $this->rank_boulder;
				$order_by = 'result_top IS NULL,result_top DESC,result_zone IS NULL,result_zone DESC';
		}
		$extra_cols[] = $mode.' AS new_rank';

		// do we have a countback
		if ($discipline != 'speed' && ($keys['route_order'] > 2 || $keys['route_order'] == 2 && $route_type != TWO_QUALI_HALF))
		{
			$extra_cols[] = '('.$this->_sql_rank_prev_heat($keys['route_order'],$route_type).') AS rank_prev_heat';
			$order_by .= ',rank_prev_heat ASC';
		}
		
		$modified = 0;
		$old_time = $old_prev_rank = null;
		foreach($this->search($keys,'PerId,result_rank',$order_by,$extra_cols) as $i => $data)
		{
			// for ko-system of speed the rank is only 1 (winner) or 2 (looser)
			if ($discipline == 'speed' && $keys['route_order'] >= 2 && $data['new_rank'])
			{
				if ($data['eliminated']) $data['result_time'] = ELIMINATED_TIME;
				$new_speed_rank = $data['new_rank'];
				$data['new_rank'] = !$old_time || $old_time < $data['result_time'] ||
					 $old_speed_rank < $new_speed_rank ? $i+1 : $old_rank;
				//echo "<p>$i. $data[PerId]: time=$data[result_time], last=$old_time, $data[result_rank] --> $data[new_rank]</p>\n";
				$old_time = $data['result_time'];
				$old_speed_rank = $new_speed_rank;
			}
			//echo "<p>$i. $data[PerId]: prev=$data[rank_prev_heat], $data[result_rank] --> $data[new_rank]</p>\n";
			if ($data['new_rank'] && $data['new_rank'] != $i+1 && $old_prev_rank)	// do we have a tie and a prev. heat
			{
				// use the previous heat to break the tie
				$data['new_rank'] = $old_prev_rank < $data['rank_prev_heat'] ? $i+1 : $old_rank;
				//echo "<p>$i. $data[PerId]: prev=$data[rank_prev_heat], $data[result_rank] --> $data[new_rank]</p>\n";
			}
			if ($data['result_rank'] != $data['new_rank'] &&
				$this->db->update($this->table_name,array('result_rank'=>$data['new_rank']),$keys+array('PerId'=>$data['PerId']),__LINE__,__FILE__))
			{
				++$modified;	
			}
			$old_prev_rank = $data['rank_prev_heat'];
			$old_rank = $data['new_rank'];
		}
		return $modified;
	}

	/**
	 * Delete a participant from a route and renumber the starting-order of the following participants
	 *
	 * @param array $keys required 'WetId', 'PerId', possible 'GrpId', 'route_number'
	 * @return boolean true if participant was successful deleted, false otherwise
	 */
	function delete_participant($keys)
	{
		$to_delete = $this->search($keys,true,'','start_order');

		if (!$this->delete($keys)) return false;
		
		foreach($to_delete as $data)
		{
			$this->db->query("UPDATE $this->table_name SET start_order=start_order-1 WHERE ".
				$this->db->expression($this->table_name,array(
					'WetId' => $data['WetId'],
					'GrpId' => $data['GrpId'],
					'route_order' => $data['route_order'],
					'start_order > '.(int)$data['start_order'],			
				)),__LINE__,__FILE__);
		}
		return true;
	}
}