<?php
/**************************************************************************\
* eGroupWare - digital ROCK Rankings - base class for UI                   *
* http://www.egroupware.org, http://www.digitalROCK.de                     *
* Written and (c) by Ralf Becker <RalfBecker@outdoor-training.de>          *
* --------------------------------------------                             *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

class soranking
{
	/**
	 * @var array $config configuration
	 */
	var $config=array();	
	/**
	 * @var db-object $db db-object with connection to ranking database, might be different from eGW database
	 */
	var $db;
	/**
	 * @var pktsystem-object $pkte
	 */
	var $pkte;
	/**
	 * @var rls_system-object $rls
	 */
	var $rls;
	/**
	 * @var category-object $cats
	 */
	var $cats;
	/**
	 * @var cup-object $cup
	 */
	var $cup;
	/**
	 * @var competition-object $comp
	 */
	var $comp;
	/**
	 * @var athlete-object $athlet
	 */
	var $athlete;
	/**
	 * @var result-object $athlet
	 */
	var $result;
	
	/**
	 * Constructor
	 */
	function soranking()
	{
		$c =& CreateObject('phpgwapi.config','ranking');
		$c->read_repository();
		$this->config = $c->config_data;
		unset($c);
		
		if ($this->config['ranking_db_host'] || $this->config['ranking_db_name'])
		{
			foreach(array('host','port','name','user','pass') as $var)
			{
				if (!$this->config['ranking_db_'.$var]) $this->config['ranking_db_'.$var] = $GLOBALS['egw_info']['server']['db_'.$var];
			}
			$this->db =& new egw_db();
			$this->db->connect($this->config['ranking_db_name'],$this->config['ranking_db_host'],
				$this->config['ranking_db_port'],$this->config['ranking_db_user'],$this->config['ranking_db_pass']);
			
			if (!$this->config['ranking_db_charset']) $this->db->Link_ID->SetCharSet($GLOBALS['egw_info']['server']['system_charset']);

		}
		else 
		{
			$this->db =& $GLOBALS['egw']->db;
		}
		foreach(array(
				'pkte'    => 'pktsystem',
				'rls'     => 'rls_system',
				'cats'    => 'category',
				'cup'     => 'cup',
				'comp'    => 'competition',
				'athlete' => 'athlete',
				'result'  => 'result',
			) as $var => $class)
		{
			$egw_name = /*'ranking_'.*/$class;
			if (!is_object($GLOBALS['egw']->$egw_name))
			{
				$GLOBALS['egw']->$egw_name =& CreateObject('ranking.'.$class,$this->config['ranking_db_charset'],$this->db,$this->config['vfs_pdf_dir']);
			}
			$this->$var =& $GLOBALS['egw']->$egw_name;
		}
	}
}
