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

require_once(PHPGW_INCLUDE_ROOT . '/etemplate/inc/class.so_sql.inc.php');

/*!
@class cup
@abstract cup object
*/
class cup extends so_sql
{
	/* var $public_functions = array(
		'init'	=> True,
		'read'	=> True,
		'save'	=> True,
		'delete'	=> True,
		'search'	=> True,
	) */	// set in so_sql
/* set by so_sql('ranking','rang.Serien'):
	var $table_name = 'rang.Serien';
	var $autoinc_id = 'SerId';
	var $db_key_cols = array('SerId' => 'SerId');
	var $db_data_cols = array(
		'rkey' => 'rkey', 'name' => 'name', 'max_serie' => 'max_serie', 'faktor' => 'faktor',
		'serie' => 'serie', 'pkte' => 'pkte','split_by_places' => 'split_by_places',
		'nation' => 'nation', 'gruppen' => 'gruppen',
	);
*/

	/*!
	@function cup
	@abstract constructor of the cup class
	*/
	function cup($key=0)
	{
		$this->so_sql('ranking','rang.Serien');	// call constructor of derived class

		if ($key) $this->read($key);
	}

	/*!
	@function data2db
	@abstract changes the data from our work-format to the db-format
	@param $data if given works on that array and returns result, else works on internal data-array
	*/
	function data2db($data=0)
	{
		if ($intern = !is_array($data))
		{
			$data =& $this->data;
		}
		if ($data['rkey']) $data['rkey'] = strtoupper($data['rkey']);
		if ($data['nation']) $data['nation'] = strtoupper($data['nation']);

		return $data;
	}

	/*!
	@function search
	@abstract reimplmented from so_sql to exclude some cols from search and to calc. year from rkey
	*/
	function search($criteria,$only_keys=True,$order_by='',$extra_cols='',$wildcard='',$empty=False)
	{
		unset($criteria['pkte']);	// is always set
		unset($criteria['split_by_places']);

		$extra_cols .= ($extra_cols!=''?',':'').'IF(LEFT(rkey,2)>80,1900,2000)+LEFT(rkey,2) AS year';

		return so_sql::search($criteria,$only_keys,$order_by,$extra_cols,$wildcard,$empty);
	}

	/*!
	@function names
	@param $keys array with col => value pairs to limit name-list, like for so_sql.search
	@returns array with all Cups of form SerId => name
	*/
	function names($keys=array(),$rkey_only=false)
	{
		$all = $this->search($keys,False,'year DESC');

		if (!$all)
			return array();

		$names = array();
		while (list($key,$data) = each($all))
		{
			$names[$data['SerId']] = $data['rkey'].($rkey_only ? '' : ': '.$data['name']);
		}
		return $names;
	}
};