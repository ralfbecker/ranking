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

/**
 * route object
 */
class route_result extends so_sql
{
	var $non_db_cols = array(	// fields in data, not (direct) saved to the db
	);
	var $charset,$source_charset;
	
	var $athlete_join = 'JOIN Personen ON RouteResults.PerId=Personen.PerId';

	var $rank_lead = 'CASE WHEN result_height IS NULL THEN NULL ELSE (SELECT 1+COUNT(*) FROM RouteResults r WHERE RouteResults.WetId=r.WetId AND RouteResults.GrpId=r.GrpId AND RouteResults.route_order=r.route_order AND (RouteResults.result_height < r.result_height OR RouteResults.result_height = r.result_height AND RouteResults.result_plus < r.result_plus)) END';
	
	/**
	 * constructor of the competition class
	 */
	function route_result($source_charset='',$db=null,$vfs_pdf_dir='')
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
		if (isset($filter['route_order']))
		{
			$route_order =& $filter['route_order'];
		}
		else
		{
			$route_order =& $criteria['route_order'];
		}
		if ($route_order === 0) $route_order = '0';		// otherwise it get's ignored by so_sql;

		if (!$only_keys && !$join || $route_order == -1) 
		{
			$join = $this->athlete_join;
			if (!is_array($extra_cols)) $extra_cols = $extra_cols ? explode(',',$extra_cols) : array();
			$extra_cols += array('vorname','nachname','nation','geb_date','verband','ort');

			if ($route_order > 2 || $route_order == 2 && $route_type != TWO_QUALI_HALF)
			{
				$extra_cols[] = '('.$this->_sql_rank_prev_heat($route_order,$route_type).') AS rank_prev_heat';
			}
			elseif ($route_order == -1)			// general result
			{
				$route_order = $route_type == TWO_QUALI_HALF ? array(0,1) : 0;		// use users from the qualification(s)

				$result_cols = array('result_rank','result_height','result_plus');

				$order_by_parts = split('[ ,]',$order_by);

				$join .= $this->_general_result_join(array(
					'WetId' => $filter['WetId'] ? $filter['WetId'] : $criteria['WetId'],
					'GrpId' => $filter['GrpId'] ? $filter['GrpId'] : $criteria['GrpId'],
				),$extra_cols,$order_by,$route_names,$route_type,$result_cols);
				
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
	 * @param array $result_cols=array() result relevant col
	 * @return string join
	 */
	function _general_result_join($keys,&$extra_cols,&$order_by,&$route_names,$route_type,$result_cols=array())
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
		return $data;
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
		return $data;
	}
	
	/**
	 * Update the ranking of a given route
	 *
	 * @param array $keys values for keys WetId, GrpId and route_order
	 * @param boolean $do_countback=true should we do a countback on further heats
	 * @param int $route_type=ONE_QUALI ONE_QUALI, TWO_QUALI_HALF, TWO_QUALI_ALL
	 * @param string $mode=null ranking-mode / sql to calculate the rank
	 * @return int/boolean updated rows or false on error (no route specified in $keys)
	 */
	function update_ranking($keys,$route_type=ONE_QUALI,$mode=null)
	{
		//echo "<p>update_ranking(".print_r($keys,true),",'$mode')</p>\n";
		if (!$keys['WetId'] || !$keys['GrpId'] || !is_numeric($keys['route_order'])) return false;
		
		$keys = array_intersect_key($keys,array('WetId'=>0,'GrpId'=>0,'route_order'=>0));	// remove other content

		if (!$mode) $mode = $this->rank_lead;
		
		$extra_cols = array($mode.' AS new_rank');
		$order_by = 'result_height IS NULL,new_rank ASC';

		// do we have a countback
		if ($keys['route_order'] > 2 || $keys['route_order'] == 2 && $route_type != TWO_QUALI_HALF)
		{
			$extra_cols[] = '('.$this->_sql_rank_prev_heat($keys['route_order'],$route_type).') AS rank_prev_heat';
			$order_by .= ',rank_prev_heat ASC';
		}
		
		// the following sql does not work, as MySQL does not allow to use the target table in the subquery
		// "UPDATE $this->table_name SET result_rank=($mode) WHERE ".$this->db->expression($this->table_name,$keys)
		$result = $this->search($keys,'PerId,result_rank','',$extra_cols);
		
		$modified = 0;
		$old_prev_rank = null;
		foreach($this->search($keys,'PerId,result_rank',$order_by,$extra_cols) as $i => $data)
		{
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
}