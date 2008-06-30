<?php
/**
 * eGroupWare digital ROCK Rankings - ranking business object/logic
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2006-8 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$
 */

require_once(EGW_INCLUDE_ROOT.'/ranking/inc/class.soranking.inc.php');

define('EGW_ACL_REGISTER',EGW_ACL_CUSTOM_1);
define('EGW_ACL_RESULT',EGW_ACL_EDIT|EGW_ACL_REGISTER);

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
	var $disciplines = array(
		'lead' => 'lead',
		'boulder' => 'boulder',
		'speed' => 'speed',
	);
	var $pkt_names;
	var $cat_names;
	var $rls_names;
	/**
	 * Nations allowed to create rankings and competitions
	 *
	 * @var array
	 */
	var $ranking_nations=array();
	/**
	 * nation if there's only one ranking-nation
	 *
	 * @var string
	 */
	var $only_nation='';
	/**
	 * nation if there's only one nation the user has edit-rights to
	 *
	 * @var string
	 */
	var $only_nation_edit='';
	/**
	 * nation if there's only one nation the user has athlet-rights to
	 *
	 * @var string
	 */
	var $only_nation_athlete='';
	/**
	 * nation if there's only one nation the user has register-rights to
	 *
	 * @var string
	 */
	var $only_nation_register='';
	/**
	 * nations the user is allowed to see
	 *
	 * @var array
	 */
	var $read_rights = array();
	/**
	 * nations the user is allowed to edit
	 *
	 * @var array
	 */
	var $edit_rights = array();
	/**
	 * nations the user is allowed to edit athlets
	 *
	 * @var array
	 */
	var $athlete_rights = array();
	/**
	 * nations the user is allowed to register athlets for competitions
	 */
	var $register_rights = array();
	/**
	 * true if user is an administrator, implies all read- and edit-rights
	 *
	 * @var boolean
	 */
	var $is_admin = false;
	/**
	 * account_id of user
	 *
	 * @var int
	 */
	var $user;

	var $maxmatches = 12;
	/**
	 * @var array $european_nations 3-digit nation codes of nation in europe
	 */
	var $european_nations = array(
		'ALB','AND','ARM','AUT','AZE','BLR','BEL','BIH','BUL',
		'CRO','CYP','CZE','DEN','EST','ESP','FIN','FRA','GBR',
		'GEO','GER','GRE','HUN','IRL','ISL','ISR','ITA','LAT',
		'LIE','LTU','LUX','MDA','MKD','MLT','MON','NED','NOR',
		'POL','POR','ROU','RUS','SRB','SLO','SMR','SUI','SVK',
		'SWE','TUR','UKR'
	);
	/**
	 * year we check the license for
	 *
	 * @var int
	 */
	var $license_year;
	/**
	 * Various license states
	 *
	 * @var array
	 */
	var $license_labels = array(
		'n' => 'none',
		'a' => 'applied',
		'c' => 'confirmed',
		's' => 'suspended',		// no registration for competitions possible
	);
	/**
	 * How many days before and after a competition a judge has rights on the competition and
	 * to create new athletes for the competition
	 *
	 * @var int
	 */
	var $judge_right_days = 7;

	/**
	 * Constructor
	 *
	 * @param array $extra_classes=array()
	 * @return boranking
	 */
	function __construct(array $extra_classes=array())
	{
		// hack to give the ranking translation of 'Top' to 'Top' precedence over the etemplate one 'Oben'
		if ($GLOBALS['egw_info']['user']['preferences']['common']['lang'] == 'de') $GLOBALS['egw']->translation->lang_arr['top'] = 'Top';
		if ($GLOBALS['egw_info']['user']['preferences']['common']['lang'] == 'de') $GLOBALS['egw']->translation->lang_arr['Time'] = 'Zeit';

		parent::__construct($extra_classes);	// calling the parent constructor

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
		$this->user = $GLOBALS['egw_info']['user']['account_id'];

		$this->athlete_rights = array_merge($this->athlete_rights,$this->judge_athlete_rights());
		if (in_array('NULL',$this->athlete_rights) || $this->is_admin)
		{
			$this->athlete_rights = array_merge($this->athlete_rights,array_values($this->athlete->distinct_list('nation')));
		}
		$this->athlete_rights = array_unique($this->athlete_rights);

		if (in_array('NULL',$this->register_rights) || $this->is_admin)
		{
			$this->register_rights = array_merge($this->register_rights,array_values($this->athlete->distinct_list('nation')));
		}

		// setup list with nations we rank and intersect it with the read_rights
		$this->ranking_nations = array('NULL'=>lang('international'))+$this->comp->nations();
		if (!$this->is_admin)
		{
			foreach($this->ranking_nations as $key => $label)
			{
				if (!in_array($key,$this->read_rights)) unset($this->ranking_nations[$key]);
			}
			if (count($this->read_rights) == 1)
			{
				$this->only_nation = $this->read_rights[0];
			}
			if (count($this->edit_rights) == 1)
			{
				$this->only_nation_edit = $this->edit_rights[0];
			}
			// international ahtlete rights are for all nation's athletes
			if (!in_array('NULL',$this->athlete_rights) && count($this->athlete_rights) == 1)
			{
				$this->only_nation_athlete = $this->athlete_rights[0];
			}
			if (count($this->register_rights) == 1)
			{
				$this->only_nation_register = $this->register_rights[0];
			}
			//echo "<p>read_rights=".print_r($this->read_rights,true).", edit_rights=".print_r($this->edit_rights,true).", only_nation_edit='$this->only_nation_edit', only_nation='$this->only_nation', only_nation_athlete='$this->only_nation_athlete', athlete_rights=".print_r($this->athlete_rights,true)."</p>\n";
		}
		$this->license_year = (int) date('Y');

		// makeing the boranking object availible for other objects
		$GLOBALS['boranking'] = $this;
	}

	/**
	 * php4 constructor
	 *
	 * @deprecated use __construct()
	 * @param array $extra_classes=array()
	 * @return boranking
	 */
	function boranking(array $extra_classes=array())
	{
		self::__construct($extra_classes);
	}

	/**
	 * Checks if the user is admin or has ACL-settings for a required right and a nation
	 *
	 * Editing athletes data is mapped to EGW_ACL_ADD.
	 * Having EGW_ACL_ADD or EGW_ACL_REGISTER for NULL=international, is equivalent to having that right for ANY nation.
	 * EGW_ACL_RESULT requires _both_ EGW_ACL_EDIT or EGW_ACL_REGISTER (for the nation of the calendar/competition)
	 *
	 * @param string $nation iso 3-char nation-code or 'NULL'=international
	 * @param int $required EGW_ACL_{READ|EDIT|ADD|REGISTER|RESULT}
	 * @param array/int $comp=null competition array or id, default null
	 * @return boolean true if access is granted, false otherwise
	 */
	function acl_check($nation,$required,$comp=null)
	{
		static $acl_cache = array();

		if ($this->is_admin) return true;

		if (!isset($acl_cache[$nation][$required]))
		{
			// Result ACL requires _both_ EDIT AND REGISTER rights, acl::check cant check both at once!
			if($required == EGW_ACL_RESULT)
			{
				$acl_cache[$nation][$required] = $this->acl_check($nation,EGW_ACL_EDIT) &&
					$this->acl_check($nation,EGW_ACL_REGISTER|1024);	// |1024 prevents int. registrations rights to be sufficent for national calendars
			}
			else
			{
				$acl_cache[$nation][$required] = $GLOBALS['egw']->acl->check($nation ? $nation : 'NULL',$required,'ranking') ||
					($required == EGW_ACL_ADD || $required == EGW_ACL_REGISTER) && $GLOBALS['egw']->acl->check('NULL',$required,'ranking');
			}
		}
		// check competition specific judges rights for REGISTER and RESULT too
		return $acl_cache[$nation][$required] || $comp && in_array($required,array(EGW_ACL_REGISTER,EGW_ACL_RESULT)) && $this->is_judge($comp);
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
	 * checks if user is a judge of a given competition, this counts only 2 weeks before and after the competition!!!
	 *
	 * @param array/int $comp competitiion array or id
	 * @return boolean
	 */
	function is_judge($comp)
	{
		if (!is_array($comp) && !($comp = $this->comp->read($comp)))
		{
			return false;
		}
		list($y,$m,$d) = explode('-',$comp['datum']);
		$distance = abs(mktime(0,0,0,$m,$d,$y)-time()) / (24*60*60);

		return $comp && is_array($comp['judges']) && in_array($this->user,$comp['judges']) && $distance <= $this->judge_right_days;
	}

	/**
	 * Get the nations of all competitions for which the current user has NOW judge-rights and therefor can add athletes
	 * nation='NULL' means international --> all nations
	 *
	 * @return array with nations
	 */
	function judge_athlete_rights()
	{
		if (!($comps = $this->comp->search(array(),'nation','nation','','',false,'AND',false,array(
			$this->db->concat("','",'judges',"','").' LIKE '.$this->db->quote('%,'.$this->user.',%'),
			"datum <= '".date('Y-m-d',time()+$this->judge_right_days*24*3600)."'",
			"datum >= '".date('Y-m-d',time()-$this->judge_right_days*24*3600)."'",
		))))
		{
			return array();
		}
		$nations = array();
		foreach($comps as $comp)
		{
			$nation = $comp['nation'] ? $comp['nation'] : 'NULL';
			if (!in_array($nation,$nations)) $nations[] = $nation;
		}
		return $nations;
	}

	/**
	 * Check if user is allowed to register athlets for $comp and $nation
	 *
	 * @param int/array $comp WetId or complete competition array
	 * @param string $nation='' nation of the athlets to register, if empty do a general check independent of nation
	 * @param int GrpId=null if set check only for a given cat
	 */
	function registration_check($comp,$nation='',$cat=null)
	{
		if (!is_array($comp) && !($comp = $this->comp->read($comp)))
		{
			return false;	// comp not found
		}
		$ret = (!$cat || !$this->comp->has_results($comp,$cat)) &&	// comp NOT already has a result for cat AND
			($this->is_admin || $this->is_judge($comp) ||			// { user is an admin OR a judge of the comp OR
			((!$nation || $this->acl_check($nation,EGW_ACL_REGISTER)) && 	// ( user has the necessary registration rights for $nation AND
			(!$this->date_over($comp['deadline'] ? $comp['deadline'] : $comp['datum']) ||	// [ deadline (else comp-date) is NOT over OR
			 $this->acl_check($comp['nation'],EGW_ACL_RESULT))));							//   user has result-rights for that calendar ] ) }

		//echo "<p>boranking::registration_check(".print_r($comp,true).",'$nation') = $ret</p>\n";

		return $ret;
	}

	/**
	 * calculates a ranking of type $rls->window_type:
	 *  monat = $rls->window_anz Monate zaehlen faer Rangl.
	 *  wettk = $rls->window_anz Wettkaempfe ---- " -----
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
	 *             - $cup->faktor, dh. Faktor faer Serienpunkte
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
		$ret_pers = $pers;
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

					foreach((array)$ranking as $athlet)
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
		return $do_cat ? $prequalified[$cat_id] : $prequalified;
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

		if (count($all_cats))
		{
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
		}
		return $nat_prequals;
	}

	/**
	 * (de-)register an athlete for a competition and category
	 *
	 * start/registration-numbers are saved as points in a result with place=0, the points contain:
	 * - registration number in the last 6 bit (< 32 prequalified, >= 32 quota or supplimentary) ($pkt & 63)
	 * - startnumber in the next 7 bits (($pkt >> 6) & 255))
	 * - route in the other bits ($pkt >> 14)
	 *
	 * @param int $comp WetId
	 * @param int $cat GrpId
	 * @param int/array $athlete PerId or complete athlete array
	 * @param int $mode=0  0: register (quota or supplimentary), 1: register prequalified, 2: remove registration
	 * @return boolean true of everythings ok, false on error
	 */
	function register($comp,$cat,$athlete,$mode=0)
	{
		//echo "<p>boranking::register($comp,$cat,$athlete$athlete[PerId],$mode)</p>\n";
		if (!$comp || !$cat || !$athlete) return false;

		if ((int)$mode == 2)	// de-register
		{
			return !!$this->result->delete(array(
				'WetId' => $comp,
				'GrpId' => $cat,
				'PerId' => is_array($athlete) ? $athlete['PerId'] : $athlete,
				'platz = 0',	// precausion to not delete a result
			));
		}
		if (!is_array($athlete)) $athlete = $this->athlete->read($athlete);

		// get next registration-number, to have registered athletes ordered
		// registration number are from 1 to 63 in that 6 lowest bit (&63) of the points
		$num = $this->result->read(array(
			'GrpId'  => $cat,
			'WetId'  => $comp,
			'nation' => $athlete['nation'],
			'(pkt & 63) '.($mode ? '< 32' : '>= 32'),	// prequalified athlets get a number < 32
		),'MAX(pkt & 63) AS pkt');

		if ($num && ($num = $num[0]['pkt']))
		{
			if ($num != 31 && $num != 63)	// prefent overflow => use highest number
			{
				$num++;
			}
		}
		else
		{
			$num = $mode ? 1 : 32;
		}
		return !$this->result->save(array(
			'PerId' => $athlete['PerId'],
			'WetId' => $comp,
			'GrpId' => $cat,
			'platz' => 0,
			'pkt'  => $num,
			'datum' => date('Y-m-d'),
		));
	}

	/**
	 * Generate a startlist for the given competition and category
	 *
	 * start/registration-numbers are saved as points in a result with place=0, the points contain:
	 * - registration number in the last 6 bit (< 32 prequalified, >= 32 quota or supplimentary) ($pkt & 63)
	 * - startnumber in the next 8 bits (($pkt >> 6) & 255))
	 * - route in the other bits ($pkt >> 14)
	 *
	 * @param int/array $comp WetId or complete comp array
	 * @param int/array $cat GrpId or complete cat array
	 * @param int $num_routes=1 number of routes, default 1
	 * @param int $max_compl=999 maximum number of climbers from the complimentary list
	 * @param int $use_ranking=0 0: randomize all athlets, 1: use reversed ranking, 2: use reversed cup ranking first,
	 * 	new random order but distribution on multiple routes by 3: ranking or 4: cup
	 * @param boolean $stagger=false insert starters of other route behind
	 * @param array $old_startlist=null old start order which should be preserved PerId => array (with start_number,route_order) pairs in start_order
	 * @return boolean/array true or array with starters (if is_array($old_startlist)) if the startlist has been successful generated AND saved, false otherwise
	 */
	function generate_startlist($comp,$cat,$num_routes=1,$max_compl=999,$use_ranking=0,$stagger=false,$old_startlist=null)
	{
		if (!is_array($comp)) $comp = $this->comp->read($comp);
		if (!is_array($cat)) $cat = $this->cats->read($cat);
		if (!$comp || !$cat) return false;

		if ($this->debug) echo "<p>boranking::generate_startlist($comp[rkey],$cat[rkey],$num_routes,$max_compl,$use_ranking,$stagger)</p>\n";

		$filter = array(
			'WetId'  => $comp['WetId'],
			'GrpId'  => $cat['GrpId'],
		);
		if (!is_array($old_startlist)) $filter[] = 'platz = 0';		// savegard against an already exsiting result

		$starters = $this->result->read($filter,'',true,'nation,reg_nr');

		if (!is_array($starters) || !count($starters)) return false;	// no starters, or eg. already a result

		for ($route = 1; $route <= $num_routes; ++$route)
		{
			$startlist[$route] = array();
		}
		$prequalified = $this->prequalified($comp,$cat);

		// first we need to remove all not-prequalified starters which are over quota+max_complimentary
		if ($max_compl < 999)
		{
			$nations = array();
			foreach($starters as $k => $athlete)
			{
				$nation = $athlete['nation'];
				if (!isset($nations[$nation]))
				{
					$nations[$nation] = array(
						'quota'        => $comp['host_quota'] && $comp['host_nation'] == $nation ? $comp['host_quota'] : $comp['quota'],
						'num'          => 0,
					);
				}
				$nat_data =& $nations[$nation];
				//echo "<p>$athlete[nachname]: nat=$athlete[nation], num=$nat_data[num], quota=$nat_data[quota]</p>\n";
				if (!in_array($athlete['PerId'],$prequalified) && ++$nat_data['num'] > $nat_data['quota'] + $max_compl)
				{
					if ($athlete['pkt'] > 64)	// athlet already has startingnumber, eg. not first run
					{
						$this->result->save(array(
							'PerId' => $athlete['PerId'],
							'WetId' => $athlete['WetId'],
							'GrpId' => $athlete['GrpId'],
							'platz' => 0,
							'pkt'   => $athlete['pkt'] & 63,	// leave only the registration number
							'datum' => $athlete['datum'] ? $athlete['datum'] : date('Y-m-d'),
						));
					}
					unset($starters[$k]);
				}
			}
		}
		// index starters with their PerId
		$starters2 = array();
		foreach($starters as $k => $athlete)
		{
			$starters2[$athlete['PerId']] =& $starters[$k];
		}
		$starters =& $starters2; unset($starters2);

		$reset_data = 1;
		$ranked = array();
		// do we use a ranking, if yes calculate it and place the ranked competitors at the end of the startlist
		if ($use_ranking)
		{
			$stand = $comp['datum'];
		 	$ranking =& $this->ranking($cat,$stand,$nul,$nul,$nul,$nul,$nul,$nul,$use_ranking == 2 || $use_ranking == 4 ? $comp['serie'] : '');

			// we generate the startlist starting from the end = first of the ranking
			foreach((array) $ranking as $athlete)
			{
				if (isset($starters[$athlete['PerId']]))
				{
					$this->move_to_startlist($starters,$athlete['PerId'],$startlist,$num_routes,$reset_data);
					$reset_data = false;
					$ranked[$athlete['PerId']] = true;
				}
			}
			if ($cat['discipline'] != 'speed')
			{
				// new modus, not for speed(!): unranked starters at the END of the startlist
				// reverse the startlists now, to have the first in the ranking at the end of the list
				for ($route = 1; $route <= $num_routes; ++$route)
				{
					$startlist[$route] = array_reverse($startlist[$route]);
				}
			}
		}
		// if we have a startorder to preserv, we use these competitior first
		if ($old_startlist && in_array($use_ranking,array(1,2)))
		{
			if ($cat['discipline'] == 'speed' && $use_ranking)	// reversed order
			{
				$old_startlist = array_reverse($old_startlist);
			}
			foreach(array_diff_key($old_startlist,$ranked) as $PerId => $starter)
			{
				if(isset($starters[$PerId])) $this->move_to_startlist($starters,$PerId,$startlist,$num_routes,1+$starter['route_order']);
			}
			// make sure both routes get equaly filled up
			if ($num_routes == 2) $reset_data = 1+(int)(count($startlist[1]) > count($startlist[2]));
		}
		// now we randomly pick starters and devide them on the routes
		while(count($starters))
		{
			$this->move_to_startlist($starters,array_rand($starters),$startlist,$num_routes,$reset_data);
			$reset_data = false;
		}
		// we have an old startlist --> try to keep the position
		if ($old_startlist && !in_array($use_ranking,array(1,2)))
		{
			// reindex startlist's by PerId in $starters
			foreach($startlist as $num => $startlist_num)
			{
				foreach($startlist_num as $starter)
				{
					$starters[$num][$starter['PerId']] = $starter;
				}
			}
			// move (reindexed) starters in their old order into the new routes
			$startlist[2] = $startlist[1] = array();
			foreach($old_startlist as $PerId => $starter)
			{
				if (isset($starters[1][$PerId]))
				{
					$this->move_to_startlist($starters[1],$PerId,$startlist,$num_routes,1);
				}
				elseif (isset($starters[2][$PerId]))
				{
					$this->move_to_startlist($starters[2],$PerId,$startlist,$num_routes,2);
				}
			}
			// add the new athlets (not in old_startlist) after the existing ones
			$startlist[1] = array_merge($startlist[1],$starters[1]);
			$startlist[2] = array_merge($startlist[2],$starters[2]);
			unset($starters);
		}
		elseif ($use_ranking >= 3)
		{
			// randomize after seeding (distribution in 2 routes) by ranking
			shuffle($startlist[1]);
			shuffle($startlist[2]);
		}
		if ($stagger)
		{
			// 2. half of each route is the other list
			$x = $startlist[1];
			$startlist[1] = array_merge($startlist[1],$startlist[2]);
			$startlist[2] = array_merge($startlist[2],$x);
			unset($x);
		}
		// reverse the startlist if neccessary
		for ($route = 1; $route <= $num_routes; ++$route)
		{
			if ($cat['discipline'] == 'speed' && $use_ranking)
			{
				// if we used a ranking, we have to reverse the startlist
				// old modus & speed: unranked startes at the beginning
				$startlist[$route] = array_reverse($startlist[$route]);
			}
			foreach($startlist[$route] as $n => $athlete)
			{
				$startlist[$route][$n]['start_order'] = 1+$n;
				// preserv the start_number if given
				if ($old_startlist) $startlist[$route][$n]['start_number'] = $old_startlist[$athlete['PerId']]['start_number'];
				// we preserve the registration number in the last 6 bit's
				$startlist[$route][$n]['pkt'] = (($route-1) << 14) + ((1+$n) << 6) + ($athlete['pkt'] & 63);
			}
		}
		if (is_array($old_startlist))
		{
			return $startlist;
		}
		// store the startlist in the database
		for ($route = 1; $route <= $num_routes; ++$route)
		{
			foreach($startlist[$route] as $n => $athlete)
			{
				if ($this->debug) echo ($n+1).": $athlete[nachname], $athlete[vorname] ($athlete[nation]) $num=".($num >> 6).":".($num % 64)."<br>\n";

				if($this->result->save(array(
					'PerId' => $athlete['PerId'],
					'WetId' => $athlete['WetId'],
					'GrpId' => $athlete['GrpId'],
					'platz' => 0,
					'pkt'   => $athlete['pkt'],
					'datum' => $athlete['datum'] ? $athlete['datum'] : date('Y-m-d'),
				)) != 0)
				{
					return false;
				}
			}
		}
		return true;
	}

	/**
	 * Get the start- and route-number from the pkt field of a registration startlist
	 *
	 * @param int $pkt
	 * @param int $only_route 1, 2, ... if only a certain route-number should be returned (returns false if no match)
	 * @return string/ startnumber or false, if it's the wrong route
	 */
	function pkt2start($pkt,$only_route=null)
	{
		$route = 1+($pkt >> 14);

		$start = ($pkt >> 6) & 255;

		if (!$only_route)
		{
			return $route > 1 ? $route.': '.$start : $start;
		}
		return $route == $only_route ? $start : false;
	}

	/**
	 * move an athlete to the startlist and diveds them on $num_routes routes
	 *
	 * @internal
	 */
	function move_to_startlist(&$starters,$k,&$startlist,$num_routes,$reset_data=null)
	{
		static $route = 1;
		static $last_route = 1;
		if ($reset_data)  $route = $reset_data;
		//echo "<p>boranking::check_move_to_startlist(,$k,,$num_routes,$reset_data) route=$route, athlete=".$starters[$k]['nachname'].', '.$starters[$k]['vorname']."</p>\n";

		$athlete =& $starters[$k];

		$startlist[$route][] =& $starters[$k];
		unset($starters[$k]);

		// previous mode: simply alternating
		//if (++$route > $num_routes) $route = 1;

		// new mode: 1, 2, 2, 1, 1, 2, 2, ...
		$last = $last_route;
		$last_route = $route;
		if ($last == $route && $num_routes == 2) $route = $route == 1 ? 2 : 1;
	}

	/**
	 * Set the state (selected competition & cat) of the UI
	 *
	 * @param string $calendar
	 * @param int $comp
	 * @param int $cat=null
	 */
	function set_ui_state($calendar=null,$comp=null,$cat=null)
	{
		//echo "<p>boranking::set_ui_state(calendar='$calendar',comp=$comp,cat=$cat) menuaction=$_GET[menuaction]</p>\n";
		foreach(array('registration','result','import') as $type)
		{
			$data = $GLOBALS['egw']->session->appsession($type,'ranking');
			foreach(array('calendar','comp','cat') as $name)
			{
				if (!is_null($$name)) $data[$name] = $$name;
			}
			$GLOBALS['egw']->session->appsession($type,'ranking',$data);
		}
		if ($_GET['menuaction']) $GLOBALS['egw']->session->appsession('menuaction','ranking',$_GET['menuaction']);
	}

	/**
	 * Calculate the feldfactor of a competition
	 *
	 * For the fieldfactor the ranking of the day before the competition starts has to be used!
	 *
	 * @param int/array $comp competition id or array
	 * @param int/array $cat category id or array
	 * @param array $starters with athltets (PerId's)
	 * @return double 1.0 for no feldfactor!
	 */
	function feldfactor($comp,$cat,$starters)
	{
		if (!is_array($comp) && !($comp = $this->comp->read($comp))) return false;
		if (!is_array($cat) && !($cat = $this->cats->read($cat))) return false;

		$rls = $cat['vor'] && $comp['datum'] < $cat['vor'] ? $cat['vor_rls'] : $cat['rls'];
		$has_feldfakt = $rls && $comp['feld_pkte'] && $comp['faktor'] && $cat['rkey'] != "OMEN";

		if (!$has_feldfakt) return 'no fieldfactor';//1.0;

		// we have to use the ranking of the day before the competition starts
		$stand = explode('-',$comp['datum']);
		$stand = date('Y-m-d',mktime(0,0,0,$stand[1],$stand[2]-1,$stand[0]));
		if (!$this->ranking($cat,$stand,$start,$nul,$ranglist,$rls,$nul,$nul))
		{
			return 'no ranking';//1.0;
		}
		$max_pkte = $this->pkte->get_pkte($comp['feld_pkte'],$pkte);

		$feldfakt = 0.0;

		foreach($starters as $PerId)
		{
			if (isset($ranglist[$PerId]))
			{
				$feldfakt += $pkte[$ranglist[$PerId]['platz']];
			}
		}
		$feldfakt = round($feldfakt / $max_pkte,2);
		//echo "<p>boresult::feldfactor('$comp[rkey]','$cat[rkey]')==$feldfakt</p>\n";
		return $feldfakt;
	}

	/**
	 * Import a result of a competition into the ranking
	 *
	 * @param array $keys WetId, GrpId
	 * @param array $result PerId => platz pairs
	 * @return string message
	 */
	function import_ranking($keys,$result)
	{
		if (!$keys['WetId'] || !$keys['GrpId'] ||
			!($comp = $this->comp->read($keys['WetId'])) ||
			!$this->acl_check($comp['nation'],EGW_ACL_RESULT,$comp)) // permission denied
		{
			return false;
		}
		//_debug_array($result);
		$feldfactor = $this->feldfactor($comp,$keys['GrpId'],array_keys($result));

		$this->result->delete(array(
			'WetId' => $keys['WetId'],
			'GrpId' => $keys['GrpId'],
		));
		$this->result->init(array(
			'WetId' => $keys['WetId'],
			'GrpId' => $keys['GrpId'],
			'datum' => $comp['datum'],	// has to be the date of the competition, NOT the actual date!
		));
		$this->pkte->get_pkte($comp['pkte'],$pkte);

		foreach($result as $PerId => $place)
		{
			if (is_array($place)) $place = $place['result_rank'];

			$this->result->save(array(
				'PerId' => $PerId,
				'platz' => $place,
				'pkt'   => round(100.0 * $feldfactor * $comp['faktor'] * $pkte[$place]),
			));
		}
		// not sure if the new code still uses that, but it does not hurt ;-)
		$this->result->save_feldfactor($keys['WetId'],$keys['GrpId'],$feldfactor);

		return lang('results of %1 participants imported into the ranking, feldfactor: %2',count($result),sprintf('%4.2lf',$feldfactor));
	}
	/**
	 * Parse a csv file
	 *
	 * @param array $keys WetId, GrpId, route_order
	 * @param string|FILE $file uploaded file name or handle
	 * @param boolean $result_only true = only results allowed, false = startlists too
	 * @param boolean $add_athletes=false add not existing athletes, default bail out with an error
	 * @return string/array error message or result lines
	 */
	function parse_csv($keys,$file,$result_only=false,$add_athletes=false)
	{
		if (!$keys || !$keys['WetId'] || !$keys['GrpId'] ||
			!($comp = $this->comp->read($keys['WetId'])) ||
			!($cat = $this->cats->read($keys['GrpId'])) ||
			!$this->acl_check($comp['nation'],EGW_ACL_RESULT,$comp)) // permission denied
		{
			return lang('Permission denied !!!');
		}
		$discipline = $comp['discipline'] ? $comp['discipline'] : $cat['discipline'];

		if (!($fp = is_resource($file) ? $file : fopen($file,'rb')) || !($labels = fgetcsv($fp,null,';')) || count($labels) <= 1)
		{
			return lang('Error: no line with column names, eg. delemiter is not ;');
		}
		if (!in_array('athlete',$labels))
		{
			return lang('Error: required column %1 not found !!!',"'athlete'");
		}
		if (!in_array('place',$labels) && (!in_array('startorder',$labels) || $result_only))
		{
			return lang('Error: required column %1 not found !!!',$result_only ? "'place'" : "'place' ".lang('or')." 'startorder'");
		}

		$n = 1;
		while (($line = fgetcsv($fp,null,';')))
		{
			if (count($line) != count($labels))
			{
				return lang('Error: dataline %1 contains %2 instead of %3 columns !!!',$n,count($line),count($labels));
			}
			$line = array_combine($labels,$line);

			if (in_array('comp',$labels) && $keys['WetId'] != $line['comp'])
			{
				return lang('Error: dataline %1 contains wrong %2 id #%3 instead of #%4 !!!',
					$n,lang('competition'),$line['comp'],$keys['WetId']);
			}
			if (in_array('cat',$labels) && $keys['GrpId'] != $line['cat'])
			{
				// we ignore lines of the wrong category and give only a warning if no line has the right category
				if (!$cat_warning)
				{
					$cat_warning = lang('Error: dataline %1 contains wrong %2 id #%3 instead of #%4 !!!',
						$n,lang('category'),$line['cat'],$keys['GrpId']);
				}
				continue;
			}
			if (in_array('heat',$labels) && isset($keys['route_order']) && $keys['route_order'] != $line['heat'])
			{
				return lang('Error: dataline %1 contains wrong %2 id #%3 instead of #%4 !!!',
				$n,lang('heat'),$line['heat'],$keys['route_order']);
			}
			if ($discipline == 'speed' && $keys['route_order'] == 2 && $line['athlete'] < 0)
			{
				// speed final uses neg. id's for wildcards!
			}
			else
			{
				if (!($athlete = $this->athlete->read($line['athlete'])))
				{
					// code to create not existing athletes, eg. as offline backup solution
					if ($add_athletes)
					{
						$this->athlete->init(array(
							'PerId' => $line['athlete'],
							'vorname' => $line['firstname'],
							'nachname' => $line['lastname'],
							'nation' => $line['nation'],
							'sex' => $cat['sex'],
							'geb_date' => $line['birthyear'] ? $line['birthyear'].'-01-01' : null,
						));
						$this->athlete->generate_rkey();
						$this->athlete->save();
						$athlete = $this->athlete->data;
					}
					else
					{
						return lang('Error: dataline %1 contains not existing athlete id #%2 !!!',$n,$line['athlete']);
					}
				}
				if ($athlete['sex'] != $cat['sex'])
				{
					return lang("Error: dataline %1, athlete %2 has wrong gender '%3' !!!",$n,
						$athlete['nachname'].' '.$athlete['vorname'].' ('.$athlete['nation'].') #'.$line['athlete'],$athlete['sex']);
				}
			}
			$lines[$line['athlete']] = array(
				'WetId'    => $keys['WetId'],
				'GrpId'    => $keys['GrpId'],
				'route_order' => $keys['route_order'],
				'PerId'    => $line['athlete'],
				'result_rank'    => $line['place'] ? $line['place'] : null,
				'start_order' => $line['startorder'] ? $line['startorder'] : $n,
				'start_number' => $line['startnumber'] ? $line['startnumber'] : null,
			)+$this->_csv2result($line,$discipline);

			++$n;
		}
		if (!$lines && $cat_warning)
		{
			return $cat_warning;
		}
		return $lines;
	}

	/**
	 * Convert a result-string into array values, as used in our results
	 *
	 * @internal
	 * @param array $arr result, boulder1, ..., boulderN
	 * @param string $discipline lead, speed or boulder
	 * @return array
	 */
	function _csv2result($arr,$discipline)
	{
		$result = array();

		$str = trim(str_replace(',','.',$arr['result']));		// remove space and allow to use comma instead of dot as decimal del.

		if ($str === '' || is_null($str)) return $result;	// no result, eg. not climbed so far

		switch($discipline)
		{
			case 'lead':
				if (strstr(strtoupper($str),'TOP'))
				{
					$result['result_plus'] = TOP_PLUS;
					$result['result_height'] = TOP_HEIGHT;
				}
				else
				{
					// try fixing broken EYC results from ifsc-climbing.org containing place and points without space inbetween
					if (preg_match('/^([0-9.]+[+-]?)'.(int)$arr['place'].'\.([0-9]+\.[0-9]{2})?$/',$str,$matches))
					{
						$str = $matches[1];
					}
					else
					{
						list($str) = explode(' ',$str);		// cut of space separated extra values of EYC
					}
					$result['result_height'] = (double) $str;
					switch(substr($str,-1))
					{
						case '+': $result['result_plus'] = 1; break;
						case '-': $result['result_plus'] = -1; break;
						default:  $result['result_plus'] = 0; break;
					}
				}
				break;

			case 'speed':
				foreach(isset($arr['time-left']) ? array('' => 'time-left','_r' => 'time-right') : array('' => 'result') as $postfix => $name)
				if (is_numeric($arr[$name]))
				{
					$result['result_time'.$postfix] = (double) $arr[$name];
					$result['eliminated'.$postfix] = '';
				}
				else
				{
					$result['result_time'.$postfix] = '';
					$result['eliminated'.$postfix] = (int) in_array($arr[$name],array('eliminiert','eliminated'));
				}
				break;

			case 'boulder':	// #t# #b#
				if (($bonus_pos = strpos($str,'b')) !== false)	// we split the string on the position of 'b' minus one char, as num of bonus is always 1 digit
				{
					list($top,$top_tries) = explode('t',substr($str,0,$bonus_pos-1));
					list($bonus,$bonus_tries) = explode('b',trim(substr($str,$bonus_pos-1)));
					$result['result_top'] = $top ? 100 * $top - $top_tries : null;
					$result['result_zone'] = 100 * $bonus - $bonus_tries;
					for($i = 1; $i <= 6 && array_key_exists('boulder'.$i,$arr); ++$i)
					{
						$result['top'.$i] = '';
						$result['zone'.$i] = strpos($str,'0b') !== false ? '0' : '';	// we need to differ between 0b and not climbed!
						if (!($boulder = $arr['boulder'.$i])) continue;
						if ($boulder[0] == 't')
						{
							$result['top'.$i] = (int) substr($boulder,1);
							$boulder = strstr($boulder,'b');
						}
						$result['zone'.$i] = (int) substr($boulder,1);
					}
				}
				else
				{
					$result = array();
				}
				break;
		}
		return $result;
	}

	/**
	 * Merge all data of one entry into an other one
	 *
	 * @param int|array $from entry to merge
	 * @param int|array $to entry to merge into
	 * @return number of merged results
	 */
	function merge_athlete($from,$to)
	{
		//echo "<p>".__METHOD__."($from,$to)</p>\n";
		if (!is_array($from) && !($from = $this->athlete->read($id=$from)))
		{
			throw new Exception(lang('Athlete %1 not found!',$id));
		}
		if (!is_array($to) && !($to = $this->athlete->read($id=$to)))
		{
			throw new Exception(lang('Athlete %1 not found!',$id));
		}
		if ($from['PerId'] == $to['PerId'])
		{
			return 0;	// nothing to merge
		}
		if ($from['nation'] != $to['nation'] || $from['sex'] != $to['sex'] ||
			$from['geb_date'] && $to['geb_date'] && (int)$from['geb_date'] != (int)$to['geb_date'] ||
			!$this->is_admin && !self::similar_names($from['vorname'].' '.$from['nachname'],$to['vorname'].' '.$to['nachname']))
		{
			throw new Exception(lang('Nation, birthyear and gender have to be identical to merge, the names have to be similar!'));
		}
		if (!$this->is_admin && !in_array($from['nation'],$this->athlete_rights))
		{
			throw new Exception(lang('Permission denied !!!'));
		}
		// merge athlete data
		foreach($from as $name => $value)
		{
			if ($value && !$to[$name] ||	// to value is empty
				$name == 'geb_date' && (int)$value.'-01-01' == $to[$name] ||	// is just a birthyear, but no date
				$name == 'verband' && substr($value,0,strlen($to[$name])) == $to[$name])	// federation from to is parent fed of the one from from
			{
				$to[$name] = $value;	// --> overwrite to value with the one from from
				if ($name == 'verband') $to['fed_id'] = $from['fed_id'];
			}
		}
		$this->athlete->init($to);
		if ($this->athlete->save() != 0)
		{
			throw new Exception(lang('Error: while saving !!!'));
		}
		// merge licenses
		$this->athlete->merge_licenses($from['PerId'],$to['PerId']);
		// merge result service data
		$this->route_result->merge($from['PerId'],$to['PerId']);
		// merge result data
		$merged = $this->result->merge($from['PerId'],$to['PerId']);

		$this->athlete->delete(array('PerId' => $from['PerId']));

		return $merged;
	}

	/**
	 * Check if two names are similar
	 *
	 * @param string $first
	 * @param string $second
	 * @param double $min minimum similarity to return true
	 * @return boolean true if names are similar
	 */
	static function similar_names($first,$second,$min=0.8)
	{
		$length = (strlen($first)+strlen($second)) / 2;

		return similar_text($first,$second)/$length >= $min;
	}
}
