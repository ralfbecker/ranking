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

	var $public_functions = array(
		'writeLangFile' => True
	);
	var $maxmatches = 12;
	/**
	 * @var array $european_nations 3-digit nation codes of nation in europe
	 */
	var $european_nations = array(
		'AUT','BEL','BLS','BUL','CRO','CZE','DEN','ESP',
		'FIN','FRA','GBR','GER','GRE','HUN','IRL','ITA',
		'KAZ','LAT','LUX','MKD','NED','NOR','POL','POR',
		'ROM','RUS','SCG','SLO','SUI','SVK','SWE','UKR',
	);

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
	
	/**
	 * Checks if a given date is "over": today > $date
	 *
	 * @param string $date as 'Y-m-d'
	 * @return boolean
	 */
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
	 * calculates a ranking of type $rls->window_type:
	 *  monat = $rls->window_anz Monate z�hlen f�r Rangl.
	 *  wettk = $rls->window_anz Wettk�mpfe ---- " -----
	 * It uses, if defined, only the $rls->best_wettk best resutls.
	 *
	 * @param mixed &$cat GrpId, rkey or cat as array, on return: cat-array
	 * @param string &$stand  rkey or WetId of a comp, Date YYYY-MM-DD or '.'=todays date, 
	 *	on return: date of last comp. of the rankin
	 * @param string &$start on return: start-date of the ranking = date of the oldest comp. in the ranking
	 * @param array &$comp on return: comp. as array to whichs date the ranking is calculated ($stand)
	 * @param array &$pers on return: ranking as array with PerId as key
	 * @param array &$rls on return: RankingSystem used for the calculation of the ranking
	 * @param array &$ex_aquo on return: array with place => number of ex_aqous per place pairs
	 * @param array &$not_counting on return: array PerId => string off all not valued WetId's pairs
	 * @param mixed $cup='' rkey,SerId or array of cup or '' for a ranking
	 * @return array sorted by ranking place
	 *
	 * Achtung:   Nicht berücksichtigt sind die folgenden Parameter:
	 *             - $rls->window_type=="wettk_athlet", dh. alte Schweizer Rangl.
	 *             - $rls->min_wettk, dh. min. Anzahl Wettk. um gewertet zu werden
	 *             - $comp->open, dh. nur bessere Erg. von. Wettk. und Open verw.
	 *             - $cup->max_rang, dh. max. Anz. Wettk. der Serie in Rangliste
	 *             - $cup->faktor, dh. Faktor f�r Serienpunkte
	 *            Diese Parameter werden im Moment von keiner Rangliste mehr verw.
	 *
	 * 01.05.2001:	Jahrgänge berücksichtigen, dh. wenn in Gruppe from_year und
	 *		to_year angegeben ist und rls->window_type != "wettk_athlet" &&
	 *		rls->end_pflich_tol (!= 0 | I_EMPTY | nul) dann werden nur
	 *		solche Athleten in die Rangliste aufgenommen, die zum Datum
	 *		der Rangliste innerhalb der Jahrgangsgrenzen liegen
	 */
	function &ranking (&$cat,&$stand,&$start,&$comp,&$ret_pers,&$rls,&$ret_ex_aquo,&$not_counting,$cup='')
	{
		if ($cup && !is_array($cup))
		{
			$cup = $this->cup->read($cup);
		}
		if (!is_array($cat))
		{
			$cat = $this->cats->read($cat);
		}
		if ($this->debug) echo "<p>boranking::ranking(cat='$cat[rkey]',stand='$stand',...,cup='$cup[rkey]')</p>\n";

		if (!$stand || $stand == '.')	// last comp. before today
		{
			$stand = date ('Y-m-d',time());
			
			$comp = $this->comp->last_comp($stand,$cat['GrpIds'],$cat['nation'],$cup['SerId']);
		}
		elseif (!preg_match('/^[0-9]{4}-[0-9]{1,2}-[0-9]{1,2}/',$stand))
		{
			if (!is_array($stand))
			{
				$comp = $this->comp->read($stand);
			}
			else
			{
				$comp = $stand;
			}
		}
		else
		{
			$comp = false;
		}
		if ($comp) 
		{
			$stand = $comp['datum'];
	
			$cats = array($cat['rkey']);
			if ($this->cats->cat2old[$cat['rkey']]) $cats[] = $this->cats->cat2old[$cat['rkey']];
			
			if (!$this->comp->next_comp_this_year($comp['datum'],$cats,$cat['nation'],$cup['SerId']))
			{
				$stand = (int)$comp['datum'] . '-12-31';	// no further comp. -> stand 31.12.
			}
		}
		if ($this->debug) echo "<p>boranking::ranking: stand='$stand', comp='$comp[rkey]'</p>\n";
		
		if ($cup)
		{
			$max_comp = $this->cup->get_max_comps($cat['rkey'],$cup);

			if ((int) $stand >= 2000 && !in_array($cat['rkey'],$cup['gruppen']))
			{
				return false;			// no cup (ranking) defined for that group
			}
		} 
		else 
		{ 				
			// $rls = used ranking-system
			$rls = $cat['vor'] && $stand < $cat['vor'] ? $cat['vor_rls'] : $cat['rls'];
	
			if (!$rls || !($rls = $this->rls->read(array('RlsId' => $rls))))
			{
				return false; 		// no (valid) ranking definiert
			}			
			$max_comp = $rls['best_wettk'];
	
			switch ($rls['window_type']) 
			{
				case 'monat':			// ranking using a given number of month
					list($year,$month,$day) = explode('-',$stand);
					$start = date('Y-m-d',mktime(0,0,0,$month-$rls['window_anz'],$day+1,$year));
					break;
				case 'wettk_athlet':
					die( "boranking::ranking: Windowtype 'wettk_athlet' is no longer supported !!!" );
					break;
				case 'wettk':			// ranking using a given number of competitions in the category
				case 'wettk_nat':		// ------------------ " ----------------------- in any category
					$cats = $rls['window_type'] == 'wettk' ? $cat['GrpIds'] : false;
					if (!($first_comp = $this->comp->last_comp($stand,$cats,$cat['nation'],0,$rls['window_anz'])))
					{
						return false;	// not enough competitions
					}					
					$start = $first_comp['datum'];
					unset($first_comp);
					break;
			}
		}
		if ($this->debug) echo "<p>boranking::ranking: start='$start'</p>\n";

		if ($cup) 
		{
			$results =& $this->result->cup_results($cup['SerId'],$cup['pkte'],$cat['GrpIds'],$stand,
				stristr($cup['rkey'],'EYC') ? $this->european_nations : false);
		} 
		else 
		{
			if (!($rls['window_type'] != 'wettk_athlet' && $rls['end_pflicht_tol'] &&
				$this->cats->age_group($cat,$stand,$from_year,$to_year)))
			{
				$from_year = $to_year = 0;
			}
			$results =& $this->result->ranking_results($cat['GrpIds'],$stand,$start,$from_year,$to_year);
		}
		$pers = false;
		$pkte = $anz = $platz = array();
		foreach($results as $result) 
		{
			$id = $result['PerId'];
			if (!isset($pers[$id]))		// Person neu --> anlegen
			{
				$pers[$id] = $result;
				$pkte[$id] = sprintf('%04.2f',$result['pkt']);
				$anz[$id] = 1;
				++$platz[$result['platz']][$id];
			}
			elseif (!$max_comp || $anz[$id] < $max_comp)
			{
				$pkte[$id] = sprintf('%04.2f',$pkte[$id] + $result['pkt']);
				$anz[$id]++;
				++$platz[$result['platz']][$id];
			}
			else 
			{
				$not_counting[$id][$result['WetId']][$result['GrpId']] = $result['pkt'];
				if ($cup['split_by_places'] != 'only_counting')
				{
					++$platz[$result['platz']][$id];
				}
			}
		}
		if (!$pers)
		{
			return ($pers);
		}
		arsort ($pkte);
	
		if ($cup['SerId'] == 60)	// EYC 2003. not sure what this is for, why only 2003?
		{
			switch($cup['split_by_places'])
			{
				case 'first':
					$max_pkte = current($pkte);
					if (next($pkte) != $max_pkte)
					{
						break;	// kein exAquo of 1. platz ==> fertig
					}
				case 'all':
				case 'only_counting':
					$max_platz = 0;
					foreach($platz as $pl => $ids)
					{
						if ($pl > $max_platz)
						{
							$max_platz = $pl;
						}
					}
					for($pl=1; $pl <= $max_platz; ++$pl)
					{
						reset($pkte);
						do
						{
							$id = key($pkte);
							$pkte[$id] .= sprintf('.%02d',intval($platz[$pl][$id]));
						}
						while(next($pkte) && (!isset($max_pkte) || substr(current($pkte),0,7) == $max_pkte));
					}
					arsort ($pkte);
					break;
			}
			reset($pkte);
		}
		$abs_pl = 1;
		$last_pkte = $last_platz = 0;
		foreach($pkte as $id => $pkt)
		{
			$pers[$id]['platz'] = $abs_pl > 1 && $pkt == $last_pkte ? $last_platz : ($last_platz = $abs_pl);
			$ex_aquo[$last_platz] = 1+$abs_pl-$last_platz;
			$abs_pl++;
			$last_pkte = $pers[$id]['pkt'] = $pkt;
			$rang[sprintf("%04d%s%s",$pers[$id]['platz'],$pers[$id]['nachname'],$pers[$id]['vorname'])] =& $pers[$id];
		} 
		ksort ($rang);			// array $rang contains now the ranking, sorted by points, lastname, firstname
	
		$ret_ex_aquo =& $ex_aquo;
		$ret_pers =& $pers;
		$not_counting =& $not_counting;
	
		return $rang;
	}
	
	/**
	 * Calculate the prequalified athlets for a given competition
	 *
	 * for performance reasons the result is cached in the session
	 *
	 * @param mixed $comp complete competition array or WetId/rkey
	 * @param mixed $do_cat complete category array or GrpId/rkey, or 0 for all cat's of $comp
	 * @return array/boolean array of PerId's or false if $comp or $cat not found
	 */
	function prequalified($comp,$do_cat=0)
	{
		if (!is_array($comp) && !($comp = $this->comp->read($comp)))
		{
			return false;
		}
		foreach($do_cat ? array($do_cat) : $comp['gruppen'] as $cat)
		{
			//echo "boranking::prequalified($comp[rkey],$do_cat) cat='$cat($cat[rkey])'<br>\n"; 
			if (!is_array($cat) && !($cat = $this->cats->read($cat)))
			{
				return false;
			}
			$cat_id = $cat['GrpId'];

			if (!is_array($prequalified))
			{
				list($prequal_comp,$prequalified) = $GLOBALS['egw']->session->appsession('prequalified','ranking');

				if (!$prequal_comp || $prequal_comp !== $comp)	// no cached object or $comp changed
				{
					$prequalified = array();
				}
				unset($prequal_comp);
			}
			if (!isset($prequalified[$cat_id]))	// no cached object or $comp changed
			{
				$prequalified[$cat_id] = array();
				// get athlets prequalified by result
				if ($comp['prequal_comp'] && count($comp['prequal_comps']))
				{
					$prequalified[$cat_id] = $this->result->prequalified($comp,$cat_id);
				}
				// get athlets prequalified by ranking
				if ($comp['prequal_ranking'])	
				{
					$stand = $comp['datum'];
					$ranking = $this->ranking($cat,$stand,$nul,$nul,$nul,$nul,$nul,$nul);
					
					foreach($ranking as $athlet)
					{
						if ($athlet['platz'] > $comp['prequal_ranking']) break;
						
						if (!in_array($athlet['PerId'],$prequalified[$cat_id]))
						{
							$prequalified[$cat_id][] = $athlet['PerId'];
						}
					}
				}
				$GLOBALS['egw']->session->appsession('prequalified','ranking',array($comp,$prequalified));
			}
		}
		//echo "prequalifed($comp[rkey],$do_cat$do_cat[rkey]) ="; _debug_array($prequalified);
		return $do_cat ? $prequalified[$cat] : $prequalified;
	}
	
	/**
	 * get all prequalifed athlets of one nation for a given competition in all categories
	 *
	 * @param mixed $comp complete competition array or WetId/rkey
	 * @param string $nation 3-digit nat-code
	 * @return array with GrpId => array(PerId => athlete-array)
	 */
	function national_prequalified($comp,$nation)
	{
		if (!($prequalified = $this->prequalified($comp))) return false;
		
		$all_cats = $nat_prequals = array();
		foreach($prequalified as $cat => $prequals)
		{
			$all_cats = array_merge($all_cats,$prequals);
			$nat_prequals[$cat] = array();
		}
		$all_cats = array_unique($all_cats);
		
		foreach((array)$this->athlete->search(array(),false,'','','',false,'AND',false,array(
			'nation' => $nation,
			'PerId'  => $all_cats,
		),false) as $athlete)
		{
			foreach($prequalified as $cat => $prequals)
			{
				if (in_array($athlete['PerId'],$prequalified[$cat]))
				{
					$nat_prequals[$cat][$athlete['PerId']] = $athlete;
				}
			}					
		}
		return $nat_prequals;
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
