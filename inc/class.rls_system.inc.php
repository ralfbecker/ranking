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

require_once(PHPGW_INCLUDE_ROOT . '/etemplate/inc/class.so_sql.inc.php');

/*!
@class rls_system
@abstract rls_sytem object, a rls defines how the ranking is calculated
*/
class rls_system extends so_sql
{
	/* var $public_functions = array(
		'init'	=> True,
		'read'	=> True,
		'save'	=> True,
		'delete'	=> True,
		'search'	=> True,
	) */	// set in so_sql
/* set by so_sql('ranking','rang.RanglistenSysteme'):
	var $table_name = 'rang.RangListenSysteme';
	var $autoinc_id = 'RlsId';
	var $db_key_cols = array('RlsId' => 'RlsId');
	var $db_data_cols = array(
		'rkey' => 'rkey', 'name' => 'name', 'window_type' => 'window_type',
		'window_anz' => 'window_anz', 'min_wettk' => 'min_wettk','best_wettk' => 'best_wettk',
		'end_pflicht_tol' => 'end_pflicht_tol', 'anz_digits' => 'anz_digits'
	);
*/
	/*!
	@function rls_sytem
	@abstract constructor of the rls_system class
	*/
	function rls_system($key=0)
	{
		$this->so_sql('ranking','rang.RangListenSysteme');	// call constructor of derived class
		$this->public_functions += array(	// init,read,save,delete,search are already set by so_sql
			'names' => True
		);
		if ($key)
			$this->read($key);
	}

	/*!
	@function names
	@returns array with all RlsSystems of form RlsId => name
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
};