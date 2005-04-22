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

include_once(EGW_INCLUDE_ROOT.'/ranking/inc/class.soranking.inc.php');

define('EGW_ACL_REGISTER',EGW_ACL_CUSTOM_1);

class boranking extends soranking 
{
	var $split_by_places = array(
		'no' => 'No never',
		'only_counting' => 'Only if competition is counting',
		'all' => 'Allways'
	);
	var $genders = array(
		'female' => 'female',
		'male' => 'male',
		'' => 'none'
	);
	var $pkt_names;
	var $cat_names;
	var $rls_names;
	/**
	 * @var array $ranking_nations Nations allowed to create rankings and competitions
	 */
	var $ranking_nations=array();
	/**
	 * @var string $only_nation nation if there's only one ranking-nation
	 */
	var $only_nation='';
	/**
	 * @var string $only_nation_edit nation if there's only one nation the user has edit-rights to
	 */
	var $only_nation_edit='';
	/**
	 * @var string $only_nation_athlete nation if there's only one nation the user has athlet-rights to
	 */
	var $only_nation_athlete='';
	/**
	 * @var string $only_nation_register nation if there's only one nation the user has register-rights to
	 */
	var $only_nation_register='';
	var $tmpl;
	var $akt_grp; // selected cat to work on
	/**
	 * @var array $read_rights nations the user is allowed to see
	 */
	var $read_rights = array();
	/**
	 * @var array $edit_rights nations the user is allowed to edit
	 */
	var $edit_rights = array();
	/**
	 * @var array $athlete_rights nations the user is allowed to edit athlets
	 */
	var $athlete_rights = array();
	/**
	 * @var array $register_rights nations the user is allowed to register athlets for competitions
	 */
	var $register_rights = array();
	/**
	 * @var boolean $is_admin true if user is an administrator, implies all read- and edit-rights
	 */
	var $is_admin = false;

	var $public_functions = array
	(
		'writeLangFile' => True
	);
	var $maxmatches = 12;

	function boranking()
	{
		$this->soranking();	// calling the parent constructor

		$this->pkt_names = $this->pkte->names();
		$this->cat_names = $this->cats->names();
		$this->rls_names = $this->rls->names();
		
		if ((int) $GLOBALS['egw_info']['user']['preferences']['common']['maxmatchs']) 
		{
			$this->maxmatches = (int) $GLOBALS['egw_info']['user']['preferences']['common']['maxmatchs'];
		}

		// read the nation ACL
		foreach($GLOBALS['egw']->acl->read() as $data)	// uses the users account and it's memberships
		{
			if ($data['appname'] != 'ranking' || $data['location'] == 'run') continue;

			foreach(array(
				'read_rights'     => EGW_ACL_READ,
				'edit_rights'     => EGW_ACL_EDIT,
				'athlete_rights'  => EGW_ACL_ADD,
				'register_rights' => EGW_ACL_REGISTER,
			) as $var => $right)
			{
				if (($data['rights'] & $right) && !in_array($data['location'],$this->$var))
				{
					$this->{$var}[] = $data['location'];
				}
			}
		}
		//foreach(array('read_rights','edit_rights','athlete_rights') as $right) echo "$right: ".print_r($this->$right,true)."<br>\n";

		$this->is_admin = isset($GLOBALS['egw_info']['user']['apps']['admin']);

		// setup list with nations we rank and intersect it with the read_rights
		$this->ranking_nations = array('NULL'=>lang('international'))+$this->comp->nations();
		if (!$this->is_admin)
		{
			foreach($this->ranking_nations as $key => $label)
			{
				if (!in_array($key,$this->read_rights)) unset($this->ranking_nations[$key]);
			}
			if (count($this->ranking_nations) == 1)
			{
				$this->only_nation = $this->ranking_nations[0];
			}
			if (count($this->edit_rights) == 1)
			{
				$this->only_nation_edit = $this->edit_rights[0];
			}
			if (count($this->athlete_rights) == 1)
			{
				$this->only_nation_athlete = $this->athlete_rights[0];
			}
			if (count($this->register_rights) == 1)
			{
				$this->only_nation_register = $this->register_rights[0];
			}
			//echo "<p>read_rights=".print_r($this->read_rights,true).", edit_rights=".print_r($this->edit_rights,true).", only_nation_edit='$this->only_nation_edit', only_nation='$this->only_nation'</p>\n";
		}
	}
	
	/**
	 * Checks if the user is admin or has ACL-settings for a required right and a nation
	 *
	 * Editing athletes data is mapped to EGW_ACL_ADD.
	 * Having EGW_ACL_ADD or EGW_ACL_REGISTER for NULL=international, is equivalent to having that right for ANY nation.
	 *
	 * @param string $nation iso 3-char nation-code or 'NULL'=international
	 * @param int $required EGW_ACL_{READ|EDIT|ADD|REGISTER}
	 */
	function acl_check($nation,$required)
	{
		static $acl_cache = array();
		
		if ($this->is_admin) return true;
		
		if (isset($acl_cache[$nation][$required])) return $acl_cache[$nation][$required];
		
		return $acl_cache[$nation][$required] = $GLOBALS['egw']->acl->check($nation ? $nation : 'NULL',$required,'ranking') ||
			($required == EGW_ACL_ADD || $required == EGW_ACL_REGISTER) && $GLOBALS['egw']->acl->check('NULL',$required,'ranking');
	}
	
	function date_over($date)
	{
		$now = explode('-',date('Y-m-d'));
		
		$over = false;	// same date ==> not over
		foreach(explode('-',$date) as $i => $n) 
		{
			if ((int) $n != (int) $now[$i]) 
			{
				$over = $n < $now[$i];
				break;
			}
		}
		return $over;	// same date ==> not over
	}

	/**
	 * Check if user is allowed to register athlets for $comp and $nation
	 *
	 * ToDo taking a deadline and competition judges into account !!!
	 *
	 * @param int/array $comp WetId or complete competition array
	 * @param string $nation='' nation of the athlets to register, if empty do a general check independent of nation
	 */
	function registration_check($comp,$nation='')
	{
		// check for registration rights of given nation, if given
		$ret = (!$nation || $this->acl_check($nation,EGW_ACL_REGISTER)) && 
			// check if comp already has a result
			!$this->comp->has_results($comp) &&
			// check if comp already happend or registration deadline over
			(is_array($comp) || ($comp = $this->comp->read($comp))) && 
			!$this->date_over($comp['deadline'] ? $comp['deadline'] : $comp['datum']);

		//echo "<p>boranking::registration_check(".print_r($comp,true).",'$nation') = $ret</p>\n";
		
		return $ret;
	}

	/**
	 * writes langfile with all templates and messages registered here
	 *
	 * can be called via http://domain/egroupware/index.php?ranking.ranking.writeLangFile
	 */
	function writeLangFile()
	{
		$this->tmpl->writeLangFile('ranking','en',array_merge($this->split_by_places,$this->genders));
	}
}
