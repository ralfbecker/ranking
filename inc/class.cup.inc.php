<?php
	/**************************************************************************\
	* eGroupWare - digital ROCK Rankings - Cup Object                          *
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
 * cup object
 */
class cup extends so_sql
{
	var $charset,$source_charset;

	/**
	 * constructor of the cup class
	 */
	function cup($source_charset='',$db=null)
	{
		$this->so_sql('ranking','Serien',$db);	// call constructor of derived class

		if ($source_charset) $this->source_charset = $source_charset;
		
		$this->charset = $GLOBALS['phpgw']->translation->charset();
		
		foreach(array(
				'cats'  => 'category',
			) as $var => $class)
		{
			$egw_name = /*'ranking_'.*/$class;
			if (!is_object($GLOBALS['egw']->$egw_name))
			{
				$GLOBALS['egw']->$egw_name =& CreateObject('ranking.'.$class,$this->source_charset,$this->db);
			}
			$this->$var =& $GLOBALS['egw']->$egw_name;
		}
	}

	/**
	 * changes the data from the db-format to our work-format
	 * @param array $data if given works on that array and returns result, else works on internal data-array
	 * @return array
	 */
	function db2data($data=0)
	{
		if (!is_array($data))
		{
			$data =& $this->data;
		}
		if (count($data) && $this->source_charset)
		{
			$data = $GLOBALS['phpgw']->translation->convert($data,$this->source_charset);
		}
		if ($data['gruppen'])
		{
			$data['gruppen'] = $this->cats->cat_rexp2rkeys($data['gruppen']);
		}
		if ($data['presets']) $data['presets'] = (array) @unserialize($data['presets']);

		return $data;
	}

	/**
	 * changes the data from our work-format to the db-format
	 * @param array $data if given works on that array and returns result, else works on internal data-array
	 * @return array
	 */
	function data2db($data=0)
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
		if (is_array($data['gruppen']))
		{
			$data['gruppen'] = implode(',',$data['gruppen']);
		}
		if (is_array($data['presets'])) $data['presets'] = serialize($data['presets']);

		if (count($data) && $this->source_charset)
		{
			$data = $GLOBALS['phpgw']->translation->convert($data,$this->charset,$this->source_charset);
		}
		return $data;
	}

	/**
	 * Search for cups
	 *
	 * reimplmented from so_sql to exclude some cols from search and to calc. year from rkey
	 */
	function search($criteria,$only_keys=True,$order_by='',$extra_cols='',$wildcard='',$empty=False,$op='AND',$start=false,$filter=null)
	{
		unset($criteria['pkte']);	// is always set
		unset($criteria['split_by_places']);
		if ($criteria['nation'] == 'NULL') $criteria['nation'] = null;
		if ($filter['nation'] == 'NULL') $filter['nation'] = null;

		if ($extra_cols && !is_array($extra_cols)) $extra_cols = array($extra_cols);
		$extra_cols[] = 'IF(LEFT(rkey,2)>80,1900,2000)+LEFT(rkey,2) AS year';

		return so_sql::search($criteria,$only_keys,$order_by,$extra_cols,$wildcard,$empty,$op,$start,$filter);
	}

	/**
	 * get the names of all or certain cups, eg. to use in a selectbox
	 *
	 * @param array/string $keys array with col => value pairs to limit name-list, like for so_sql.search
	 *	or string with nation
	 * @return array with all Cups of form SerId => name
	 */
	function names($keys=array(),$rkey_only=false)
	{
		if (!is_array($keys)) $keys = $keys ? array('nation' => ($keys == 'NULL' ? null : $keys)) : array();
		
		$names = array();
		foreach((array)$this->search(array(),False,'year DESC','','',true,'AND',false,$keys) as $data)
		{
			$names[$data['SerId']] = $data['rkey'].($rkey_only ? '' : ': '.$data['name']);
		}
		return $names;
	}
}