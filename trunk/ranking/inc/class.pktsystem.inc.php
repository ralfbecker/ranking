<?php
	/**************************************************************************\
	* eGroupWare - digital ROCK Rankings - PktSystem Object                    *
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
@class pkt
@abstract pktsystem object
*/
class pktsystem extends so_sql
{
	/* var $public_functions = array(
		'init'	=> True,
		'read'	=> True,
		'save'	=> True,
		'delete'	=> True,
		'search'	=> True,
	) */	// set in so_sql
/* set by so_sql('ranking','rang.PktSysteme'):
	var $table_name = 'rang.PktSysteme';
	var $autoinc_id = 'PktId';
	var $db_key_cols = array('PktId' => 'PktId');
	var $db_data_cols = array(
		'rkey' => 'rkey', 'name' => 'name', 'anz_pkt' => 'anz_pkt'
	);
*/
/* not needed so far
	var $db_name_pkte = 'rang.PktSystemPkte';
	var $db_data_cols_pkte = array(
		'platz' => 'platz','pkt' => 'pkt'
	);
	var $pkte;
*/

	/*!
	@function pktsytem
	@abstract pktsystem of the competition class
	*/
	function pktsystem($key=0)
	{
		$this->so_sql('ranking','rang.PktSysteme');	// call constructor of derived class
		$this->public_functions += array(	// init,read,save,delete,search are already set by so_sql
			'names' => True
		);

/*    not needed so far
		$this->pkte = new so_sql;
		$this->pkte->db_name = $this->db_name_pkte;
		$this->pkte->db_key_cols = $this->db_key_cols;
		$this->pkte->db_data_cols = $this->db_data_cols_pkte;
		$this->pkte->so_sql(); // call constructor again manually after setting up fields
*/
		if ($key)
			$this->read($key);
	}

	/*!
	@function names
	@returns array with all PktSystems of form PktId => name
	*/
	function names()
	{
		$all = $this->search(array(),False,'rkey');

		if (!$all)
			return array();

		$arr = array();
		while (list($key,$data) = each($all))
		{
			$arr[$data['PktId']] = $data['rkey'].': '.$data['name'];
		}
		return $arr;
	}
};