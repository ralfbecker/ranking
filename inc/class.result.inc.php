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

require_once(PHPGW_INCLUDE_ROOT . '/etemplate/inc/class.so_sql.inc.php');

/*!
@class result
@abstract result object
*/
class competition extends so_sql
{
	/* var $public_functions; /* = array(
		'init'	=> True,
		'read'	=> True,
		'save'	=> True,
		'delete'	=> True,
		'search'	=> True,
	) */	// set in so_sql
/* set by so_sql('ranking','rang.Wettkaempfe'):
	var $table_name = 'rang.Wettkaempfe';
	var $autoinc_id = 'WetId';
	var $db_key_cols = array('WetId' => 'WetId');
	var $db_data_cols = array(
		'rkey' => 'rkey', 'name' => 'name', 'dru_bez' => 'dru_bez', 'datum' => 'datum',
		'pkte' => 'pkte', 'pkt_bis' => 'pkt_bis', 'feld_pkte' => 'feld_pkte', 'feld_bis' => 'feld_bis',
		'faktor' => 'faktor', 'serie' => 'serie', 'open' => 'open','nation' => 'nation',
		'gruppen' => 'gruppen', 'homepage' => 'homepage'
	);
*/
	var $non_db_cols = array(	// fields in data, not (direct) saved to the db
	);
	var $per_table_name,$grp_table_name,$comp_table_name,$ff_table_name;

	/*!
	@function result
	@abstract result of the competition class
	@param $WetId,$GrpId,$PerId as for read
	*/
	function result($WetId=0,$GrpId=0,$PerId=0)
	{
		//$this->debug = 1;
		$this->so_sql('ranking','rang.Results');	// call constructor of extending class

		$this->per_table_name  = 'rang.Personen';
		$this->cat_table_name  = 'rang.Gruppen';
		$this->comp_table_name = 'rang.Wettkaempfe';
		$this->ff_table_name   = 'rang.Feldfaktoren';

		//$this->public_functions += array();

		if ($WetId || $PerId)
			$this->read($WetId,$GrpId,$PerId);
	}

	/*!
	@function db2data
	@abstract changes the data from the db-format to our work-format
	@param $data if given works on that array and returns result, else works on internal data-array
	*/
	function db2data($data=0)
	{
		if ($intern = !is_array($data))
			$data = $this->data;


		if ($intern)
			$this->data = $data;

		return $data;
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


		if ($intern)
			$this->data = $data;

		return $data;
	}

	/*!
	@function read($WetId,$GrpId=0,$PerId=0)
	@abstract read result-data from DB
	@param $WetId,$GrpId,$PerId following combinations are supported
	@param $WetId,   0  ,   0  : result-data is list of cats which have an result for $WetId
	@param $WetId,$GrpId,   0  : result-data is result for the cat $GrpId
	@param $WetId,$GrpId,$PerId: result of one single Person
	@param    0  ,   0  ,$PerId: all the results of one person
	*/
	function read($WetId,$GrpId=0,$PerId=0)
	{
		$this->WetId = $WetId;
		$this->GrpId = $GrpId;
		$this->PerId = $PerId;

		if ($WetId > 0 && $GrpId > 0 && !$PerId)	// read complete result of comp. for cat
		{
			$sql = "SELECT r.*,p.* FROM $this->table_name r,$this->per_table_name p
						WHERE r.GrpId=$GrpId AND r.PerId=p.PerId
						ORDER BY r.platz,p.nachname,p.vorname";
		}
		elseif (!$WetId && !$GrpId && $PerId > 0)	// read list comps of one athlet
		{
			$sql = "SELECT r.*,w.*,g.rkey as gkey,g.name as gname
						FROM $this->table_name r,$this->comp_table_name w,$this->cat_table_name g
						WHERE r.PerId=$PerId AND r.WetId=w.WetId AND r.GrpId=g.GrpId
						ORDER BY w.datum,gkey";
		}
		elseif ($WetId > 0 && !$GrpId)	// read cat-list of competition
		{

		}
		else	// result of single person
		{
			return so_sql::read(array('WetId' => $WetId,'GrpId' => $GrpId,'PerId' => $PerId));
		}
	}

	/*!
	@function search
	@abstract reimplmented from so_sql to be able to call data2db before save and db2data after
	*/
	function search($criteria,$only_keys=True,$order_by='',$extra_cols='',$wildcard='',$empty=False)
	{
		unset($criteria['pkte']);	// is allwas set
		if (!$criteria['feld_pkte'])
			unset($criteria['feld_pkte']);
		unset($criteria['open']);
		if (!$criteria['serie'])
			unset($criteria['serie']);
		$criteria['rkey'] = strtoupper($criteria['rkey']);

		//$this->debug = 1;
		return $this->so_sql_search($criteria,$only_keys,$order_by,$extra_cols,$wildcard,$empty);
	}

	/*!
	@function names
	@param $keys likes to limit name-list, like for so_sql.search
	@returns array with all Competitions of form WetId => name
	*/
	function names($keys=array())
	{
		$all = $this->search($keys,False,'datum');

		if (!$all)
			return array();

		while (list($key,$data) = each($all))
			$arr[$data['WetId']] = $data['rkey'].': '.$data['name'];

		return $arr;
	}
};