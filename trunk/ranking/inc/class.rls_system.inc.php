<?php
/**************************************************************************\
* eGroupWare - digital ROCK Rankings - RlsSystems Object                   *
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
 * rls_system object, a rls defines how the ranking is calculated
 */
class rls_system extends so_sql
{
	var $charset,$source_charset;

	/**
	 * constructor of the rls_system class
	 */
	function rls_system($source_charset='',$db=null)
	{
		$this->so_sql('ranking','RangListenSysteme',$db);	// call constructor of derived class

		if ($source_charset) $this->source_charset = $source_charset;
		
		$this->charset = $GLOBALS['egw']->translation->charset();
	}

	/**
	 * changes the data from the db-format to our work-format
	 *
	 * @param array $data=null if given works on that array and returns result, else works on internal data-array
	 */
	function db2data($data=null)
	{
		if (!is_array($data))
		{
			$data =& $this->data;
		}
		if (count($data) && $this->source_charset)
		{
			$data = $GLOBALS['egw']->translation->convert($data,$this->source_charset);
		}
		return $data;
	}

	/**
	 * changes the data from our work-format to the db-format
	 *
	 * @param array $data=null if given works on that array and returns result, else works on internal data-array
	 */
	function data2db($data=null)
	{
		if (!is_array($data))
		{
			$data =& $this->data;
		}
		if (count($data) && $this->source_charset)
		{
			$data = $GLOBALS['egw']->translation->convert($data,$this->charset,$this->source_charset);
		}
		return $data;
	}

	/**
	 * returns array with all RlsSystems as RlsId => name pairs
	 *
	 * @return array
	 */
	function names()
	{
		$all = $this->search(array(),False,'rkey');

		if (!$all)
			return array();

		$arr = array();
		while (list($key,$data) = each($all))
		{
			$arr[$data['RlsId']] = $data['rkey'].': '.$data['name'];
		}
		return $arr;
	}
}