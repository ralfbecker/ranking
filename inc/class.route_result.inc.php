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

	var $rank_lead = "CASE WHEN result_height IS NULL THEN NULL ELSE (SELECT 1+COUNT(*) FROM RouteResults r WHERE RouteResults.WetId=r.WetId AND RouteResults.GrpId=r.GrpId AND RouteResults.route_order=r.route_order AND (RouteResults.result_height < r.result_height OR RouteResults.result_height = r.result_height AND RouteResults.result_plus < r.result_plus)) END";
	
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
		if (!$only_keys && !$join) 
		{
			$join = $this->athlete_join;
			if (!is_array($extra_cols)) $extra_cols = $extra_cols ? explode(',',$extra_cols) : array();
			$extra_cols += array('vorname','nachname','nation','geb_date','verband','ort');
			
//			$extra_cols[] = $this->rank_lead.' AS result_rank';
		}
		return parent::search($criteria,$only_keys,$order_by,$extra_cols,$wildcard,$empty,$op,$start,$filter,$join,$need_full_no_count);
	}
	
	/**
	 * changes the data from the db-format to our work-format
	 * 
	 * @param array $data if given works on that array and returns result, else works on internal data-array
	 * @return array
	 */
	function db2data($data=0)
	{
		if (!is_array($data))
		{
			$data =& $this->data;
		}
		if ($data['result_height'] == TOP_HEIGHT)
		{
			$data['result_height'] = '';
			$data['result_plus']   = TOP_PLUS;
		}
		elseif ($data['result_height'])
		{
			$data['result_height'] *= 0.001;
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
	 * @param string $mode=null ranking-mode / sql to calculate the rank
	 * @return int/boolean updated rows or false on error (no route specified in $keys)
	 */
	function update_ranking($keys,$mode=null)
	{
		//echo "<p>update_ranking(".print_r($keys,true),",'$mode')</p>\n";
		if (!$keys['WetId'] || !$keys['GrpId'] || !is_numeric($keys['route_order'])) return false;
		
		$keys = array_intersect_key($keys,array('WetId'=>0,'GrpId'=>0,'route_order'=>0));	// remove other content

		if (!$mode) $mode = $this->rank_lead;
		
		// the following sql does not work, as MySQL does not allow to use the target table in the subquery
		// "UPDATE $this->table_name SET result_rank=($mode) WHERE ".$this->db->expression($this->table_name,$keys)
		
		$modified = 0;
		foreach($this->search($keys,'PerId,result_rank','',$mode.' AS new_rank') as $data)
		{
			if ($data['result_rank'] != $data['new_rank'] &&
				$this->db->update($this->table_name,array('result_rank'=>$data['new_rank']),$keys+array('PerId'=>$data['PerId']),__LINE__,__FILE__))
			{
				++$modified;	
			}
		}
		return $modified;
	}
}