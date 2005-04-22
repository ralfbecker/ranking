<?php
	/**************************************************************************\
	* eGroupWare - digital ROCK Rankings - Category Object                     *
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
 * category object
 */
class category extends so_sql
{
	/**
	 * @var array $data['rkey']2old maps new to old category-names
	 */
	var $cat2old = array(
		// int. Cats
		'ICC_F'   => 'WOMEN',
		'ICC_FB'  => 'BWOMEN',
		'ICC_FS'  => 'SWOMEN',
		'ICC_FX'  => 'AWOMEN',
		'ICC_M'   => 'MEN',
		'ICC_MB'  => 'BMEN',
		'ICC_MS'  => 'SMEN',
		'ICC_MO'  => 'OMEN',
		'ICC_MX'  => 'AMEN',
		'ICC_F_J' => 'W_JUNIOR',
		'ICC_F_A' => 'W_JUG_A',
		'ICC_F_B' => 'W_JUG_B',
		'ICC_M_J' => 'M_JUNIOR',
		'ICC_M_A' => 'M_JUG_A',
		'ICC_M_B' => 'M_JUG_B',
		// german Cats
		'GER_F'   => 'DAMEN',
		'GER_FB'  => 'BDAMEN',
		'GER_FS'  => 'SDAMEN',
		'GER_M'   => 'HERREN',
		'GER_MB'  => 'BHERREN',
		'GER_MS'  => 'SHERREN',
		'GER_F_X' => 'MAEDELS',
		'GER_M_A' => 'JUGEND_A',
		'GER_M_B' => 'JUGEND_B',
		'GER_M_J' => 'JUNIOR',
		// swiss Cats
		'SUI_F'   => 'SUI_D_1',
		'SUI_F_2' => 'SUI_D_2',
		'SUI_F_3' => 'SUI_D_3',
		'SUI_M'   => 'SUI_H_1',
		'SUI_M_2' => 'SUI_H_2',
		'SUI_M_3' => 'SUI_H_3',
		'SUI_F_J' => 'SUI_D_J',
		'SUI_F_A' => 'SUI_D_A',
		'SUI_F_B' => 'SUI_D_B',
		'SUI_F_X' => 'SUI_D_AB',
		'SUI_F_M' => 'SUI_D_M',
		'SUI_M_J' => 'SUI_H_J',
		'SUI_M_A' => 'SUI_H_A',
		'SUI_M_B' => 'SUI_H_B',
		'SUI_M_M' => 'SUI_H_M',
	);
	/**
	 * @var array $cache result from search() without params = all cats
	 */
	var $cache = null;
	var $charset,$source_charset;

	/**
	 * constructor of the category class
	 */
	function category($source_charset='',$db=null)
	{
		$this->so_sql('ranking','Gruppen',$db);	// call constructor of derived class
		
		if ($source_charset) $this->source_charset = $source_charset;
		
		$this->charset = $GLOBALS['phpgw']->translation->charset();
	}

	/**
	 * changes the data from the db-format to our work-format
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
	 * @param array $data if given works on that array and returns result, else works on internal data-array
	 * @return array
	 */
	function data2db($data=null)
	{
		if (!is_array($data))
		{
			$data =& $this->data;
		}
		$data['rkey'] = strtoupper($data['rkey']);
		if ($data['nation'] && !is_array($data['nation']))   $data['nation'] = $data['nation'] == 'NULL' ? '' : strtoupper($data['nation']);

		if (count($data) && $this->source_charset)
		{
			$data = $GLOBALS['phpgw']->translation->convert($data,$this->charset,$this->source_charset);
		}
		return $data;
	}

	/**
	 * Search for cups
	 *
	 * reimplmented from so_sql to exclude some cols from search and do some caching
	 */
	function search($criteria,$only_keys=True,$order_by='',$extra_cols='',$wildcard='',$empty=False,$op='AND',$start=false,$filter=null)
	{
		unset($criteria['rls']);	// is always set
		unset($criteria['vor_rls']);
		
		if ($this->cache)
		{
			switch (count($criteria)+count($filter))
			{
				case 0:
					return $this->cache;
					
				case 1:
					$ret = false;
					list($key,$val) = count($criteria) ? each($criteria) : each($filter);
					foreach($this->cache as $cat)
					{
						if (is_array($val) && in_array($cat[$key],$val) ||
							!is_array($val) && $cat[$key] == $val)
						{
							$ret[] = $cat;
						}
					}
					return $ret;
			}
		}
		$ret =& parent::search($criteria,$only_keys,$order_by,$extra_cols,$wildcard,$empty,$op,$start,$filter);
		
		if (!$this->cache && count($criteria)+count($filter) == 0)
		{
			$this->cache =& $ret;
		}
		return $ret; 
	}
	
	/**
	 * read a category, reimplemented to use the cache
	 */
	function read($keys)
	{
		if (!is_array($keys))
		{
			$keys = is_numeric($keys) ? array('GrpId' => (int) $keys) : array('rkey' => $keys);
		}
		if ($cache && count($keys) == 1 && ($keys['GrpId'] || $keys['rkey']))
		{
			list($key,$val) = each($keys);
			foreach($this->cache as $cat)
			{
				if ($cat[$key] == $val)
				{
					return $this->data = $cat;
				}
			}
			return false;
		}
		return parent::read($keys);
	}

	/**
	 * get the names of all or certain categories, eg. to use in a selectbox
	 * @param array $keys array with col => value pairs to limit name-list, like for so_sql.search
	 * @param int $rkeys 0: GrpId=>name, 1: rkey=>name, 2: rkey=>rkey:name, default 2
	 * @returns array with all Cups in the from specified in $rkeys
	 */
	function &names($keys=array(),$rkeys=2)
	{
		if ($keys['nation'] == 'NULL') $keys['nation'] = null;

		$names = array();
		foreach((array)$this->search($keys,False,'rkey'/*,'','',false,'AND',false,$keys*/) as $data)
		{
			switch($rkeys)
			{
				case 0:
					$names[$data['GrpId']] = $data['name'];
					break;
				case 1:
					$names[$data['rkey']] = $data['name'];
					break;
				default:
					$names[$data['rkey']] = $data['rkey'] . ': ' . $data['name'];
					break;
			}
		}
		//echo "<p>category::names(".print_r($keys,true).") = <pre>".print_r($names,true)."</pre>\n";
		return $names;
	}
	
	/**
	 * converts a regular expression or comma-separated rkey-list, into an array of rkeys
	 *
	 * @param string $rexp eg. 'SUI_M,SUI_F', 'SUI_[MF]', ...
	 * @param string/boolean $nation nation to limit the categories too
	 * @return array of rkey's
	 */
	function cat_rexp2rkeys($rexp)
	{
		if (empty($rexp)) return array();

		$rexp = ereg_replace('=[^,]*','',$rexp);	// removes cat specific counts
	
		if (!$this->all_names)
		{
			$this->all_names = $this->names();
		}
		$cats = array();
		foreach((array) $this->all_names as $rkey => $name)
		{
			if (stristr( ",".$rexp.",",",".$rkey."," ) || $rexp && eregi( '^'.$rexp.'$',$rkey ) ||
	       		(isset($this->cat2old[$rkey]) && (stristr( ",".$rexp.",",",".$this->cat2old[$rkey]."," ) || 
	       		$rexp && eregi( '^'.$rexp.'$',$this->cat2old[$rkey] ))))
	        {
	        	$cats[] = $rkey;
	        }
		}
		//echo "<p>category::cat_rexp2rkeys('$rexp')=".print_r($cats,true)."</p>\n";
		return $cats;
	}
}