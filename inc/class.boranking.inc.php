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
	var $comp;
	var $cup;
	var $pkte,$pkt_names;
	var $cat,$cat_names;
	var $rls,$rls_names;
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
	var $nations=array();
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
		
		if ((int) $GLOBALS['phpgw_info']['user']['preferences']['common']['maxmatchs']) 
		{
			$this->maxmatches = (int) $GLOBALS['phpgw_info']['user']['preferences']['common']['maxmatchs'];
		}
		
		// read the nation ACL
		foreach(array('read_rights' => PHPGW_ACL_READ,'edit_rights' => PHPGW_ACL_EDIT) as $var => $right)
		{
			$ids = (array) $GLOBALS['phpgw']->acl->get_location_list_for_id('ranking',$right);
			foreach($ids as $n => $val)
			{
				if ($val == 'run') unset($ids[$n]);
			}
			$this->$var = array_values($ids);
			//echo $var; _debug_array($this->$var);
		}
		//$this->is_admin = $GLOBALS['phpgw_info']['user']['apps']['admin'];

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
			//echo "<p>read_rights=".print_r($this->read_rights,true).", edit_rights=".print_r($this->edit_rights,true).", only_nation_edit='$this->only_nation_edit', only_nation='$this->only_nation'</p>\n";
		}
	}

	/**
	 * writes langfile with all templates and messages registered here
	 *
	 * can be called via http://domain/phpgroupware/index.php?ranking.ranking.writeLangFile
	 */
	function writeLangFile()
	{
		$this->tmpl->writeLangFile('ranking','en',array_merge($this->split_by_places,$this->genders));
	}
}
