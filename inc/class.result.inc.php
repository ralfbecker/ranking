<?php
/**************************************************************************\
* eGroupWare - digital ROCK Rankings - Result Object                       *
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

/**
 * result object
 */
class result extends so_sql
{
	var $non_db_cols = array(	// fields in data, not (direct) saved to the db
	);
	var $result_table  = 'Results';
	var $athlete_table = 'Personen';
	var $cat_table     = 'Gruppen';
	var $comp_table    = 'Wettkaempfe';
	var $ff_table      = 'Feldfaktoren';
	var $charset,$source_charset;

	/**
	 * constructor of the competition class
	 */
	function result($source_charset='',$db=null,$vfs_pdf_dir='')
	{
		//$this->debug = 1;
		$this->so_sql('ranking',$this->result_table,$db);	// call constructor of extended class
		
		if ($source_charset) $this->source_charset = $source_charset;
		
		$this->charset = $GLOBALS['phpgw']->translation->charset();
	}

	/**
	 * reads row matched by keys and puts all cols in the data array
	 *
	 * a) only WetId: List of cats with results for the given comp.
	 * b) WetId and GrpId: List of Athlets = result or startlist
	 * c) WetId, GrpId and PerId: result of a single athlet
	 * d) only PerId: List of results of a person
	 *
	 * @param array $keys array with keys in form internalName => value, may be a scalar value if only one key
	 * @param string/array $extra_cols string or array of strings to be added to the SELECT, eg. "count(*) as num"
	 * @param string/boolean $join=true true for the default join or sql to do a join, added as is after the table-name, eg. ", table2 WHERE x=y" or 
	 * @param string $order='' order clause or '' for the default order depending on the keys given
	 * @return array/boolean data if row could be retrived else False
	 */
	function &read($keys,$extra_cols='',$join=true,$order='')
	{
		if ($order && !preg_match('/^[a-z_,. ]+$/i',$order)) $order = '';

		$filter = $keys;
		foreach(array('WetId','GrpId','PerId') as $key)
		{
			if ((int) $keys[$key])
			{
				$this->$key = (int) $keys[$key];
			}
			else
			{
				unset($keys[$key]);
			}
			unset($filter[$key]);
		}
		if ($this->WetId && $this->GrpId && !$this->PerId)	// read complete result of comp. WetId for cat GrpId
		{
			if ($join === true)
			{
				$join = ",$this->athlete_table WHERE $this->result_table.PerId=$this->athlete_table.PerId";
				$extra_cols = "nachname,vorname,nation AS nation,geb_date";
			}
			if ($this->GrpId < 0) unset($keys['GrpId']);	// return all cats

			return $this->search($keys,false,$order ? $order : 'platz,nachname,vorname',$extra_cols,'',false,'AND',false,$filter,$join);
		}
		elseif (!$this->WetId && !$this->GrpId && $this->PerId)	// read list comps of one athlet
		{
			if ($join === true)
			{
				$join = ",$this->cat_table,$this->comp_table WHERE $this->result_table.GrpId=$this->cat_table.GrpId AND $this->result_table.WetId=$this->comp_table.WetId";
				$extra_cols = "$this->comp_table.name AS name,$this->comp_table.rkey AS rkey,$this->cat_table.name AS cat_name,$this->cat_table.rkey AS cat_rkey";
			}
			return $this->search($keys,false,$order ? $order : $this->result_table.'.datum DESC,platz',$extra_cols,'',false,'AND',false,$filter,$join);
		}
		elseif ($this->WetId && !$this->GrpId)	// read cat-list of competition
		{
			if ($join === true)
			{
				$join = ",$this->cat_table WHERE $this->result_table.GrpId=$this->cat_table.GrpId";
				$extra_cols = 'name,rkey';
			}
			return $this->search($keys,'DISTINCT GrpId',$order ? $order : 'rkey,name',$extra_cols,'',false,'AND',false,$filter,$join);
		}
		// result of single person
		return parent::read($keys,$extra_cols,$join !== true ? $join : '');
	}

	/**
	 * changes the data from the db-format to our work-format
	 *
	 * @param array $data if given works on that array and returns result, else works on internal data-array
	 * @return array
	 */
	function db2data($data=null)
	{
		if (!is_array($data))
		{
			$data =& $this->data;
		}
		if (count($data) && $this->source_charset)
		{
			$data = $GLOBALS['phpgw']->translation->convert($data,$this->source_charset);
		}
		return $data;
	}

	/**
	 * changes the data from our work-format to the db-format
	 *
	 * @param array $data if given works on that array and returns result, else works on internal data-array
	 * @return array
	 */
	function data2db($data=null)
	{
		if (!is_array($data))
		{
			$data =& $this->data;
		}
		if (count($data) && $this->source_charset)
		{
			$data = $GLOBALS['phpgw']->translation->convert($data,$this->charset,$this->source_charset);
		}
		return $data;
	}
	
	/**
	 * Checks if there are any results (platz > 0) for the given keys
	 *
	 * @param array $keys with index WetId, PerId and/or GrpId
	 * @return boolean/int number of found results or false on error
	 */
	function has_results($keys)
	{
		$keys[] = 'platz > 0';

		$this->db->select($this->table_name,'count(*)',$keys,__LINE__,__FILE__);
		
		return $this->db->next_record() ? $this->db->f(0) : false;
	}

	/**
	 * searches db for rows matching searchcriteria
	 *
	 * '*' and '?' are replaced with sql-wildcards '%' and '_'
	 *
	 * @param array/string $criteria array of key and data cols, OR a SQL query (content for WHERE), fully quoted (!)
	 * @param boolean $only_keys True returns only keys, False returns all cols
	 * @param string $order_by fieldnames + {ASC|DESC} separated by colons ','
	 * @param string/array $extra_cols string or array of strings to be added to the SELECT, eg. "count(*) as num"
	 * @param string $wildcard appended befor and after each criteria
	 * @param boolean $empty False=empty criteria are ignored in query, True=empty have to be empty in row
	 * @param string $op defaults to 'AND', can be set to 'OR' too, then criteria's are OR'ed together
	 * @param int/boolean $start if != false, return only maxmatch rows begining with start
	 * @param array $filter if set (!=null) col-data pairs, to be and-ed (!) into the query without wildcards
	 * @param string $join='' sql to do a join, added as is after the table-name, eg. ", table2 WHERE x=y" or 
	 *	"LEFT JOIN table2 ON (x=y)", Note: there's no quoting done on $join!
	 * @return array of matching rows (the row is an array of the cols) or False
	 */
/* as it does nothing atm.
	function search($criteria,$only_keys=True,$order_by='',$extra_cols='',$wildcard='',$empty=False,$op='AND',$start=false,$filter=null,$join='')
	{
		//$this->debug = 1;
		return parent::search($criteria,$only_keys,$order_by,$extra_cols,$wildcard,$empty,$op,$start,$filter,$join);
	}*/
}