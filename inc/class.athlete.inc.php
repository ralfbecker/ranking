<?php
/**************************************************************************\
* eGroupWare - digital ROCK Rankings - athlete Object                       *
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

define('ACL_DENY_BIRTHDAY',1);
define('ACL_DENY_EMAIL',2);
define('ACL_DENY_PHONE',4);
define('ACL_DENY_FAX',8);
define('ACL_DENY_CELLPHONE',16);
define('ACL_DENY_STREET',32);
define('ACL_DENY_CITY',64);
define('ACL_DENY_PROFILE',128);

/**
 * Athlete object
 */
class athlete extends so_sql
{
	var $charset,$source_charset;
	var $result_table = 'Results';
	
	var $cats;

	/**
	 * constructor of the athlete class
	 */
	function athlete($source_charset='',$db=null)
	{
		$this->so_sql('ranking','Personen',$db);	// call constructor of derived class

		if ($source_charset) $this->source_charset = $source_charset;
		
		$this->charset = $GLOBALS['phpgw']->translation->charset();
		
		foreach(array(
				'cats'  => 'category',
			) as $var => $class)
		{
			$egw_name = /*'ranking_'.*/$class;
			if (!is_object($GLOBALS['egw']->$egw_name))
			{
				$GLOBALS['egw']->$egw_name =& CreateObject('ranking.'.$class,$source_charset,$this->db,$vfs_pdf_dir);
			}
			$this->$var =& $GLOBALS['egw']->$egw_name;
		}
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
		if ($data['acl'])
		{
			$acl = $data['acl'];
			$data['acl'] = array();
			for($i = $n = 1; $i <= 16; ++$i, $n <<= 1)
			{
				if ($acl & $n) $data['acl'][] = $n;
			}
		}
		if ($data['geb_date']) $data['geb_year'] = (int) $data['geb_date'];

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
		if ($data['rkey']) $data['rkey'] = strtoupper($data['rkey']);
		if ($data['nation'] && !is_array($data['nation'])) 
		{
			$data['nation'] = $data['nation'] == 'NULL' ? '' : strtoupper($data['nation']);
		}
		if (isset($data['acl']))
		{
			$acl = is_array($data['acl']) ? $data['acl'] : explode(',',$data['acl']);
			$data['acl'] = 0;
			foreach($acl as $n)
			{
				$data['acl'] |= $n;
			}			
		}
		if (count($data) && $this->source_charset)
		{
			$data = $GLOBALS['phpgw']->translation->convert($data,$this->charset,$this->source_charset);
		}
		return $data;
	}

	/**
	 * Search for athletes, joins by default with the Result table to show the last competition and to filter by a category
	 *
	 * '*' and '?' are replaced with sql-wildcards '%' and '_'
	 *
	 * @param array/string $criteria array of key and data cols, OR a SQL query (content for WHERE), fully quoted (!)
	 * @param boolean $only_keys=true True returns only keys, False returns all cols
	 * @param string $order_by='' fieldnames + {ASC|DESC} separated by colons ',', can also contain a GROUP BY (if it contains ORDER BY)
	 * @param string/array $extra_cols='' string or array of strings to be added to the SELECT, eg. "count(*) as num"
	 * @param string $wildcard='' appended befor and after each criteria
	 * @param boolean $empty=false False=empty criteria are ignored in query, True=empty have to be empty in row
	 * @param string $op='AND' defaults to 'AND', can be set to 'OR' too, then criteria's are OR'ed together
	 * @param mixed $start=false if != false, return only maxmatch rows begining with start, or array($start,$num)
	 * @param array $filter=null if set (!=null) col-data pairs, to be and-ed (!) into the query without wildcards
	 * @param mixed $join=true sql to do a join (as so_sql::search), default true = add join for latest result
	 *	if numeric a special join is added to only return athlets competed in the given category (GrpId).
	 * @return array of matching rows (the row is an array of the cols) or False
	 */
	function &search($criteria,$only_keys=True,$order_by='',$extra_cols='',$wildcard='',$empty=False,$op='AND',$start=false,$filter=null,$join=true)
	{
		//echo "<p>athlete::search(".print_r($criteria,true).",'$only_keys','$order_by','$extra_cols','$wildcard','$empty','$op','$start',".print_r($filter,true).",'$join')</p>\n";
		
		foreach(array('nation','sex') as $name)
		{
			if ($filter[$name] == 'NULL')
			{
				$filter[$name] = null;
			}
			elseif (!isset($filter[$name]) && !isset($criteria[$name]) && $op == 'AND')
			{
				// by default only show real athlets (nation and sex set)
				$filter[] = $name . ' IS NOT NULL';
			}
		}
		if ($join === true || is_numeric($join))
		{
			if (is_array($criteria) && $criteria['PerId'])
			{
				$join = '';
			}
			else
			{
				if (is_numeric($join))	// add join to filter for a category
				{
					$join = ", $this->result_table WHERE GrpId=".(int)$join." AND $this->table_name.PerId=$this->result_table.PerId AND platz > 0";
				}
				else	// LEFT join to get latest competition 
				{
					$join = "LEFT JOIN $this->result_table ON ($this->table_name.PerId=$this->result_table.PerId AND platz > 0)";
				}
				if ($extra_cols) $extra_cols = explode(',',$extra_cols);
				$extra_cols[] = 'MAX(datum) AS last_comp';
				$order_by = "GROUP BY $this->table_name.PerId ".($order_by ? 'ORDER BY '.$order_by : 'ORDER BY nachname,vorname');
			}
		}
		return so_sql::search($criteria,$only_keys,$order_by,$extra_cols,$wildcard,$empty,$op,$start,$filter,$join);
	}
	
	/**
	 * Gets a distinct list of all values of a given column for a given nation (or all)
	 *
	 * @param string $column column-name, eg. 'nation', 'nachname', ...
	 * @param array/string $keys array with column-value pairs or string with nation to search, defaults to '' = all
	 * @return array with name as key and value
	 */
	function distinct_list($column,$keys='')
	{
		//echo "<p>athlete::distinct_list('$column',".print_r($keys,true),")</p>\n";
		if (!($column = array_search($column,$this->db_cols))) return false;

		if ($keys && !is_array($keys)) $keys = array('nation' => $keys);
		
		$values = array();
		foreach((array)$this->search($keys,'DISTINCT '.$column,$column,'','',true,'AND',false,null,'') as $data)
		{
			$val = $data[$column];
			$values[$val] = $val;
		}
		return $values;
	}
	
	/**
	 * Generates a rkey for an athlete by using one letter of the first name, 2 from the last name the year and the sex
	 */
	function generate_rkey($data='')
	{
		if (!is_array($data)) $data =& $this->data;
		
		if ($data['rkey']) return $data['rkey'];

		$data['rkey'] = substr($data['vorname'],0,1).substr($data['nachname'],0,2).date('y').substr($data['sex'],0,1);
		
		// we convert some chars to 7bit ascii, the rest is removed by mb_convert_encoding(...,'7bit',...)
		static $to_ascii = array(
			'Ä' => 'A', 'ä' => 'a', 'á' => 'a', 'à' => 'a', 'À' => 'A', 'Á' => 'A',
			'Ö' => 'O', 'ö' => 'o',
			'Ü' => 'U', 'ü' => 'u',
			'ß' => 's',
			'é' => 'e', 'É' => 'E', 'è' => 'e', 'È' => 'E', 'ê' => 'e', 'Ê' => 'E',
			'ć' => 'c', 'Ć' => 'C', 'ĉ' => 'c', 'Ĉ' => 'C', 
		);
		$data['rkey'] = mb_convert_encoding(strtoupper(str_replace(array_keys($to_ascii),array_values($to_ascii),$data['rkey'])),
			'7bit',$GLOBALS['egw']->translation->charset());

		$rkey = $data['rkey'];
		$n = 0;
		while ($this->not_unique() != 0)
		{
			$data['rkey'] = $rkey.$n++;
		}		
		return $data['rkey'];
	}

	/**
	 * checks if an athlete already has results recorded
	 *
	 * @param int/array $keys PerId or array with keys of the athlete to check, default null = use keys in data
	 * @return boolean 
	 */
	function has_results($keys=null)
	{
		if (is_array($keys))
		{
			$data_backup = $this->data;
			if (!$this->read($keys))
			{
				$this->data = $data_backup;
				return false;
			}
		}
		$PerId = is_numeric($keys) ? $keys : $this->data['PerId'];
		if ($data_backup) $this->data = $data_backup;
		
		$this->db->select('Results','count(*)',array('PerId' => $PerId,'platz > 0'),__LINE__,__FILE__);
		
		return $this->db->next_record() && $this->db->f(0);
	}
}
