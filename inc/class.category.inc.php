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

require_once(PHPGW_INCLUDE_ROOT . '/etemplate/inc/class.so_sql.inc.php');

/*!
@class category
@abstract category object
*/
class category extends so_sql
{
	/* var $public_functions = array(
		'init'	=> True,
		'read'	=> True,
		'save'	=> True,
		'delete'	=> True,
		'search'	=> True,
	) */	// set in so_sql
/* set by so_sql('ranking','rang.Gruppen')
	var $table_name = 'rang.Gruppen';
	var $autoinc_id = 'GrpId';
	var $db_key_cols = array('GrpId' => 'GrpId');
	var $db_data_cols = array(
		'rkey' => 'rkey', 'name' => 'name', 'nation' => 'nation', 'serien_pat' => 'serien_pat',
		'sex' => 'sex', 'from_year' => 'from_year','to_year' => 'to_year','rls' => 'rls',
		'vor_rls' => 'vor_rls', 'vor' => 'vor',
	);
*/
	/*!
	@function category
	@abstract constructor of the category class
	*/
	function category($key=0)
	{
		$this->so_sql('ranking','rang.Gruppen');	// call constructor of derived class

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
			$data = $this->data;

		$data['rkey'] = strtoupper($data['rkey']);
		$data['nation'] = strtoupper($data['nation']);

		if ($intern)
			$this->data = $data;

		return $data;
	}

	/*!
	@function search
	@abstract reimplmented from so_sql to exclude some cols from search
	*/
	function search($criteria,$only_keys=True,$order_by='',$extra_cols='',$wildcard='',$empty=False)
	{
		unset($criteria['rls']);	// is always set
		unset($criteria['vor_rls']);

		return so_sql::search($criteria,$only_keys,$order_by,$extra_cols,$wildcard,$empty);
	}

	/*!
	@function names
	@param $keys array with col => value pairs to limit name-list, like for so_sql.search
	@returns array with all Cups of form SerId => name
	*/
	function names($keys=array())
	{
		$all = $this->search($keys,False,'rkey');

		if (!$all)
			return array();

		while (list($key,$data) = each($all))
			$arr[$data['rkey']] = $data['rkey'] . ': ' . $data['name'];

		return $arr;
	}
};