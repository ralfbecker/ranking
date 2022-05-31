<?php
/**
 * EGroupware digital ROCK Rankings - ranking business object/logic
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2006-19 by Ralf Becker <RalfBecker@digitalrock.de>
 */

namespace EGroupware\Ranking;

use EGroupware\Api;
use EGroupware\Api\Acl;
use \ranking_result_bo;

/**
 * ranking business object/logic
 */
class Base extends So
{
	const ACL_READ = Acl::READ;
	const ACL_EDIT = Acl::EDIT;
	/**
	 * ACL to edit athletes
	 *
	 * @var int 2
	 */
	const ACL_ATHLETE = Acl::ADD;
	/**
	 * ACL to register athletes
	 *
	 * @var int 64
	 */
	const ACL_REGISTER = Acl::CUSTOM1;
	/**
	 * ACL to edit results
	 *
	 * @var int 4|64=68
	 */
	const ACL_RESULT = self::ACL_EDIT|Acl::CUSTOM1;

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
	/**
	 * Disciplines to select for competitions or categories
	 *
	 * @var array
	 */
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
	 * nations the user is allowed to edit athletes
	 *
	 * @var array
	 */
	var $athlete_rights = array();
	/**
	 * nations the user is allowed to edit athletes
	 *
	 * @var array
	 */
	var $athlete_rights_no_judge = array();
	/**
	 * nations the user is allowed to register athletes for competitions
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
		'r' => 'Athlete applied',
		'e' => 'Local federation confirmed',
		'a' => 'Local & state federation confirmed',
		'c' => 'confirmed',
		's' => 'suspended',		// no registration for competitions possible
	);
	/**
	 * key => label pairs for which this instance maintains licenses
	 *
	 * (the string 'NULL' is the key for international!)
	 *
	 * @var array
	 */
	var $license_nations = array();
	/**
	 * How many days before and after a competition a judge has rights on the competition and
	 * to create new athletes for the competition
	 *
	 * @var int
	 */
	var $judge_right_days = 10;
	/**
	 * What extra column(s) to display for an athlete (beside name)
	 *
	 * @var array
	 */
	var $display_athlete_types = array(
		'' => 'Default',
		Competition::NATION => 'Nation',
		Competition::FEDERATION => 'Federation',
		Competition::CITY => 'City',
		Competition::PC_CITY => 'PC City',
		Competition::NATION_PC_CITY => 'Nation PC City',
		Competition::PARENT_FEDERATION => 'Parent federation',
		Competition::FED_AND_PARENT => 'Federation and Parent',
		Competition::NONE => 'None',
	);

	/**
	 * @var self
	 */
	private static $instance;

	/**
	 * Constructor
	 */
	function __construct()
	{
		// hack to give the ranking translation of 'Top' to 'Top' precedence over the etemplate one 'Oben'
		if ($GLOBALS['egw_info']['user']['preferences']['common']['lang'] == 'de')
		{
			Api\Translation::$lang_arr['top'] = 'Top';
			Api\Translation::$lang_arr['Time'] = 'Zeit';
		}
		parent::__construct();	// calling the parent constructor

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
			if ($data['appname'] != 'ranking' || $data['location'] == 'run')
			{
				continue;
			}
			$location = $data['location'];
			if ($location[0] == Federation::ACL_LOCATION_PREFIX) $location = substr($location,1);

			foreach(array(
				'read_rights'     => self::ACL_READ,
				'edit_rights'     => self::ACL_EDIT,
				'athlete_rights'  => self::ACL_ATHLETE,
				'register_rights' => self::ACL_REGISTER,
			) as $var => $right)
			{
				if (($data['rights'] & $right) && !in_array($location,$this->$var) && (!is_numeric($location) || $right == self::ACL_EDIT))
				{
					$this->{$var}[] = $location;
				}
			}
			//error_log(__METHOD__."() account_lid={$GLOBALS['egw_info']['user']['account_lid']}, acl=".array2string($data)." --> read_rights=".array2string($this->read_rights).", edit_rights=".array2string($this->edit_rights).", athlete_rights=".array2string($this->athlete_rights).", register_rights=".array2string($this->register_rights));
		}

		$this->is_admin = isset($GLOBALS['egw_info']['user']['apps']['admin']);
		$this->user = $GLOBALS['egw_info']['user']['account_id'];

		$this->athlete_rights_no_judge = $this->athlete_rights;
		$this->athlete_rights = array_merge($this->athlete_rights, $jar=$this->judge_athlete_rights());
		//error_log(__METHOD__."() account_lid={$GLOBALS['egw_info']['user']['account_lid']}, judge_athlete_rights()=".array2string($jar)." --> athlete_rights=".array2string($this->athlete_rights));
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
		// fix situation of single user has edit rights for more than one LV
		if (count($this->edit_rights) > 1 && !$this->read_rights &&
			is_numeric($this->edit_rights[0]) && ($fed0 = $this->federation->read(['fed_id' => $this->edit_rights[0]])))
		{
			$this->read_rights[] = $fed0['nation'] ?? 'GER';
		}
		if (!$this->is_admin)
		{
			foreach(array_keys($this->ranking_nations) as $key)
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
			// international athlete rights are for all nation's athletes
			if (!in_array('NULL',$this->athlete_rights) && count($this->athlete_rights) == 1 && $this->athlete_rights[0] !== 'XYZ')
			{
				$this->only_nation_athlete = $this->athlete_rights[0];
			}
			if (count($this->register_rights) == 1)
			{
				$this->only_nation_register = $this->register_rights[0];
			}
			//error_log(__METHOD__."() account_lid={$GLOBALS['egw_info']['user']['account_lid']}, read_rights=".array2string($this->read_rights).", edit_rights=".array2string($this->edit_rights).", only_nation_edit='$this->only_nation_edit', only_nation='$this->only_nation', only_nation_athlete='$this->only_nation_athlete', athlete_rights=".array2string($this->athlete_rights));
		}
		$this->license_year = (int) date('Y');
		$this->license_nations = $this->ranking_nations;
		// fix license nations to not contain international for digital ROCK site and no other for IFSC
		switch ($_SERVER['HTTP_HOST'])
		{
			case 'www.digitalrock.de':
				unset($this->license_nations['NULL']);
				break;
			case 'ifsc.egroupware.net':
				$this->license_nations = array('NULL' => $this->ranking_nations['NULL']);
				break;
		}
		if (!isset(self::$instance))
		{
			self::$instance = $this;
		}
	}

	/**
	 * Singleton to get a Base instance
	 *
	 * @return self
	 */
	static public function getInstance()
	{
		if (!isset(self::$instance))
		{
			self::$instance = new self;
		}
		return self::$instance;
	}

	/**
	 * Checks if the user is admin or has ACL-settings for a required right and a nation
	 *
	 * Having self::ACL_ATHLETE or self::ACL_REGISTER for NULL=international, is equivalent to having that right for ANY nation.
	 * self::ACL_RESULT requires _both_ ACL::EDIT or self::ACL_REGISTER (for the nation of the calendar/competition)
	 *
	 * Competition specific rights are checked for self::ACL_(REGISTER|RESULT), if $comp given, and
	 * route specific ones, if $route given.
	 *
	 * @param string $nation iso 3-char nation-code or 'NULL'=international
	 * @param int $required self::ACL_{READ|EDIT|ATHLETE|REGISTER|RESULT}
	 * @param array|int $comp =null competition array or id, default null
	 * @param boolean $allow_before =false grant judge-rights unlimited time before the competition
	 * @param array $route =null array with values for keys 'WetId', 'GrpId', 'route_order' and optional 'route_judges',
	 * 	if route judges should be checked too, default null=no route judges
	 * @param boolean $use_no_judge =false true: do NOT use judge-rights for athletes eg. confirming licenses
	 * @param int $problem =null for which boulder problem to check (1, 2, 3, ...) or null for all route-judges
	 * @return boolean true if access is granted, false otherwise
	 */
	function acl_check($nation,$required,$comp=null,$allow_before=false,$route=null,$use_no_judge=false,$problem=null)
	{
		static $acl_cache = array();

		if ($this->is_admin) return true;

		// for ATHLETE rights check $this->athlete_rights, as they also contain rights gained as judge
		if ($required == self::ACL_ATHLETE && in_array($nation, $use_no_judge ? $this->athlete_rights_no_judge : $this->athlete_rights))
		{
			return true;
		}
		if (!isset($acl_cache[$nation][$required]))
		{
			// Result ACL requires _both_ EDIT AND REGISTER rights, Acl::check cant check both at once!
			if($required == self::ACL_RESULT)
			{
				$acl_cache[$nation][$required] = $this->acl_check($nation,self::ACL_EDIT) &&
					$this->acl_check($nation,self::ACL_REGISTER|1024);	// |1024 prevents int. registrations rights to be sufficent for national calendars
			}
			else
			{
				$location = is_numeric($nation) ? Federation::ACL_LOCATION_PREFIX.$nation :
					($nation ? $nation : 'NULL');
				$acl_cache[$nation][$required] = $GLOBALS['egw']->acl->check($location,$required,'ranking') ||
					($required == self::ACL_ATHLETE || $required == self::ACL_REGISTER) && $GLOBALS['egw']->acl->check('NULL',$required,'ranking');
			}
		}
		$granted = $acl_cache[$nation][$required] ||
			// check competition specific judges rights for REGISTER and RESULT too
			$comp && in_array($required, array(self::ACL_REGISTER, self::ACL_RESULT)) &&
				$this->is_judge($comp, $allow_before, $route, $problem);
		//error_log(__METHOD__."('$nation', $required, ".array2string($comp).') returning '.array2string($granted));
		return $granted;
	}

	/**
	 * Check athlete ACL, which uses the nation ACL above, or the federation (and it's parents)
	 *
	 * @param array|int $athlete athlete id or data, or array with values for keys 'nation' and 'fed_id'
	 * @param int $required =self::ACL_ATHLETE self::ACL_{ATHLETE|REGISTER|RESULT}
	 * @param array|int $comp =null competition array or id, default null
	 * @param string $license =null nation for which a license should be applied for, default null=no license (NULL for international)
	 * @return boolean true if access is granted, false otherwise
	 */
	function acl_check_athlete($athlete,$required=self::ACL_ATHLETE,$comp=null,$license=null)
	{
		static $fed_grants = null;

		if ($this->is_admin)
		{
			$check = true;	// admin has always access
			$which = 'user is admin';
		}
		elseif ($comp && !is_array($comp) && !($comp = $this->comp->read($comp)))
		{
			$check = false;	// competition not found
			$which = 'competition NOT found!';
		}
		elseif (!is_array($athlete) && !($athlete = $this->athlete->read($athlete)))
		{
			$check = false;	// athlete not found
			$which = 'athlete NOT found!';
		}
		// first check the nation ACL (do not use judge rights for licenses!)
		elseif ($this->acl_check($athlete['nation'], $required, !$license ? $comp : null, false, null, (bool)$license))
		{
			$check = true;
			$which = 'national ACL grants access';
		}
		// national edit rights allow to register foreing athlets for national competition
		elseif($comp['nation'] && $required == self::ACL_REGISTER && in_array($comp['nation'],$this->edit_rights))
		{
			$check = true;
			$which = 'national edit rights allow to register foreign athlets for national competitions';
		}
		// if competition given, we only check the federation if it's a national competition of that athlete!
		elseif ($comp && !is_numeric($athlete['nation']) && $comp['nation'] != $athlete['nation'])
		{
			$check = false;
			$which = 'WRONG nation of competition (to check federation)';
		}
		elseif($license == 'NULL')
		{
			$check = false;
			$which = 'national sub-federation rights allow NOT to apply for an international license!';
		}
		elseif(is_null($license) && $athlete['PerId'] && $this->is_selfservice() == $athlete['PerId'])
		{
			$check = true;
			$which = 'athlete selfservice';
		}
		// now check if user has rights for the athletes federation (or a parent fed of it)
		else
		{
			if (is_null($fed_grants))
			{
				$fed_grants = $this->federation->get_user_grants();
				// add federation specific judge-rights eg. from a German LV competition
				$grants = array();
				foreach($this->judge_athlete_rights() as $fed_id)
				{
					if (is_numeric($fed_id) && !isset($fed_grants[$fed_id]))
					{
						$fed_grants[$fed_id] |= self::ACL_ATHLETE;
						$grants[] = $fed_id;
					}
				}
				// now include the direkt children (eg. sektionen from the landesverbände)
				if ($grants && ($children = $this->federation->search(array('fed_parent' => $grants),'fed_id,fed_parent')))
				{
					foreach($children as $child)
					{
						$fed_grants[$child['fed_id']] |= self::ACL_ATHLETE;
					}
				}
			}
			$fed_rights = (int)$fed_grants[$athlete['fed_id']] | (int)$fed_grants[$athlete['acl_fed_id']];

			$check = !!($fed_rights & $required);
			$which = "fed_id=$athlete[fed_id], acl_fed_id=$athlete[acl_fed_id] --> rights=$fed_rights";
		}
		// do we have to check for a license, jury rights do NOT allow to apply for licenses
		if ($check && !$this->is_admin && $license)
		{
			if (!($check =  $license != 'NULL' && $fed_rights || in_array($athlete['nation'],$this->athlete_rights_no_judge)))
			{
				$which = 'jury rights do NOT allow to apply for a license!'." license='$license', athlete_rights_no_judge=".implode(', ',$this->athlete_rights_no_judge);
			}
		}
		//error_log(__METHOD__."(".array2string($athlete).",$required,".array2string($comp).",$license) ".($check?'TRUE':'FALSE')." ($which)");
		return $check;
	}

	/**
	 * Check or set if we do athlete selfservice
	 *
	 * @param int $set =null
	 * @return int PerId of logged in athlete
	 */
	function is_selfservice($set=null)
	{
		$ret = !is_null($set) ? Api\Cache::setSession('ranking', 'selfservice', $set) :
			($GLOBALS['egw']->session->session_flags == 'A' ? Api\Cache::getSession('ranking', 'selfservice') : null);
		//error_log(__METHOD__.'('.array2string($set).') returning '.array2string($ret)." (Api\Cache::getSession('ranking', 'selfservice')=".array2string(Api\Cache::getSession('ranking', 'selfservice')).')');
		return $ret;
	}

	/**
	 * Check if current user has required rights for a competition
	 *
	 * @parm array $comp competition or cup data
	 * @param int $required=self::ACL_EDIT
	 * @return boolean
	 */
	function acl_check_comp(array $comp, int $required=self::ACL_EDIT)
	{
		return $this->is_admin || $this->acl_check($comp['nation'], $required, $comp) ||
			$comp['fed_id'] && $this->acl_check($comp['fed_id'], $required, $comp);
	}

	/**
	 * Check and set nation&fed_id depending on $this->edit_rights
	 *
	 * Only works for a single edit-right or one national right plus federation rights of same nation
	 *
	 * @parm array $comp=null default $this->comp->data
	 */
	function check_set_nation_fed_id(array &$comp)
	{
		foreach($this->edit_rights as $nat_fed_id)
		{
			if (is_numeric($nat_fed_id) && ($fed = $this->federation->read($nat_fed_id)))
			{
				$comp['fed_id'] = $nat_fed_id;
				$comp['nation'] = $fed['nation'];
			}
			elseif ($comp['nation'] !== $nat_fed_id)
			{
				$comp['nation'] = $nat_fed_id;
				unset($comp['fed_id']);
			}
		}
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
		//echo "<p>".__METHOD__."($date) returning ".array2string($over)."</p>\n";
		return $over;	// same date ==> not over
	}

	/**
	 * Checks if user is a judge of a given competition, this counts only 1 week (see judge_right_days) before and after the competition!!!
	 *
	 * Allways returns true for admins or if user has result rights for competition federation!
	 *
	 * @param array|int $comp competitiion array or id
	 * @param boolean $allow_before =false grant judge-rights unlimited time before the competition
	 * @param array $route =null array with values for keys 'WetId', 'GrpId', 'route_order' and optional 'route_judges',
	 * 	if route judges should be checked too, default null=no route judges
	 * @param int $problem =null to check for judges of a certain problem (1, 2, ...) or default null to allow route-judges of all boulders
	 * @return boolean
	 */
	function is_judge($comp, $allow_before=false, $route=null, $problem=null)
	{
		if (!is_array($comp) && !($comp = $this->comp->read($comp)))
		{
			return false;
		}
		if ($this->is_admin) return true;

		list($y,$m,$d) = explode('-',$comp['datum']);
		$distance = (mktime(0,0,0,$m,$d,$y)-time()) / (24*60*60);
		//echo "<p>".__METHOD__."($comp[rkey]: $comp[name] ($comp[datum])) distance=$distance</p>\n";

		$is_judge = $comp && is_array($comp['judges']) && in_array($this->user,$comp['judges']) &&
			// days before competition-start <= judge_right_days or $allow_before for infinit before
			($distance >= 0 && ($allow_before || $distance <= $this->judge_right_days) ||
			// days after competition-end (start+duration) <= judge_right_days
			$distance < 0 && abs($distance) <= $this->judge_right_days+$comp['duration']) ||
			// treat national result-rights like being a judge of every competition
			$this->acl_check($comp['nation'], self::ACL_RESULT);

		// check for route judge rights, if not already a judge
		if (!$is_judge && $route &&
			(is_array($route) && isset($route['route_judges']) || ($route = $this->route->read($route))) &&
			isset($route['route_judges']) && is_array($route['route_judges']) &&
			$route['route_status'] != STATUS_RESULT_OFFICIAL &&			// only 'til result is offical
			$distance < 1 && abs($distance) <= $this->judge_right_days+$comp['duration'])	// and one day before competition started
		{
			$is_judge = in_array($this->user, isset($problem) && count($route['route_judges']) > 1 ?
				$route['route_judges'][$problem-1] :	// allow only judges from a specific boulder
				array_merge(...array_filter($route['route_judges'])));	// allow judges from all boulders, ignoring not set ones
		}
		//if (!$is_judge) error_log(__METHOD__."(#$comp[WetId]=$comp[rkey], $allow_before, ".array2string($route).") distance=$distance, route_judges=$route[route_judges] returning ".array2string($is_judge).' '.function_backtrace());
		return $is_judge;
	}

	/**
	 * Check if user is allowed to register an athlete without a license
	 *
	 * This function does NOT check if the athelte has a license or not!
	 *
	 * @param array|int $comp competitiion array or id
	 * @return string|boolean string for confirmation message or boolean true or false to allow or deny it
	 */
	function allow_no_license_registration($comp)
	{
		if (!is_array($comp) && !($comp = $this->comp->read($comp)))
		{
			return false;
		}
		// check if competition requires no license
		if ($comp['no_license'])
		{
			return true;
		}
		// SUI does NOT allow exceptions for judges or user with national edit permission
		if ($comp['nation'] == 'SUI' && !$this->is_admin)
		{
			/* SUI stoped using day licenses in 2018
			return lang('This athlete has NO license!').' '.lang('Do you want to use a day-license?'); */
		}
		elseif ($this->is_admin || $this->is_judge($comp,true) ||	// judges are allowed to register unlimited time before competition
			$this->acl_check_comp($comp))	// allow people with edit rights to a competition to grant exceptions from licenses
		{
			return lang('This athlete has NO license!').' '.lang('Are you sure you want to make an EXCEPTION?');
		}
		return false;
	}

	/**
	 * Get the nations of all competitions for which the current user has NOW judge-rights and therefor can add athletes
	 * nation='NULL' means international --> all nations
	 *
	 * @return array with nations
	 */
	function judge_athlete_rights()
	{
		if (!($comps = $this->comp->search(array(),'nation,fed_id','nation','','',false,'AND',false,array(
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
			$nation = $comp['fed_id'] ? $comp['fed_id'] : ($comp['nation'] ? $comp['nation'] : 'NULL');
			if (!in_array($nation,$nations)) $nations[] = $nation;
		}
		return $nations;
	}

	/**
	 * Check if user is allowed to register athletes for $comp and $nation
	 *
	 * @param int|array $comp WetId or complete competition array
	 * @param int|string|array $athlete ='' whole athlete array, nation of the athlets to register or (acl_)fed_id, if empty do a general check independent of nation
	 * @param int GrpId =null if set check only for a given cat
	 * @param bool& $replace =null on return: true, if name-replace is allowed
	 * @return bool true if registration is allowed, false if not
	 */
	function registration_check($comp, $athlete=null, $cat=null, &$replace=null)
	{
		if (!is_array($comp) && !($comp = $this->comp->read($comp)))
		{
			return false;	// comp not found
		}
		if (!is_array($athlete))
		{
			$athlete = array('nation' => $athlete, 'fed_id' => $athlete, 'acl_fed_id' => $athlete);
		}
		$ret = (!$cat || !$this->comp->has_results($comp,$cat)) &&  // comp NOT already has a result for cat AND
			($this->is_admin || $this->is_judge($comp, true) ||     // user is an admin OR a judge of the comp OR
				$this->acl_check_comp($comp) ||                     // user has edit rights for the competition
				in_array($comp['nation'],$this->register_rights) || // user has national registration rights OR
				(($this->acl_check_athlete($athlete, self::ACL_REGISTER)) &&	// ( user has the necessary registration rights for $nation AND
					(!$this->date_over($comp['deadline'] ? $comp['deadline'] : $comp['datum']) ||	// [ deadline (else comp-date) is NOT over OR
						 $this->acl_check($comp['nation'],self::ACL_RESULT))));						//   user has result-rights for that calendar ] ) }

		// if we have (no more) rights, but a replace-only deadline --> check that too
		if (!($replace = $ret) && !empty($comp['replace_deadline']))
		{
			$replace = $this->acl_check_athlete($athlete, self::ACL_REGISTER) &&
				!$this->date_over($comp['replace_deadline']);
		}
		//error_log(__METHOD__."(".array2string($comp).", ".array2string($athlete).", $cat) returning ".array2string($ret));
		return $ret;
	}

	/**
	 * Calculates a ranking
	 *
	 * @deprecated use Calculation::ranking
	 */
	function &ranking (&$cat,&$stand,&$start,&$comp,&$ret_pers,&$rls,&$ret_ex_aquo,&$not_counting,$cup='',
		array &$comps=null, &$max_comp=null)
	{
		return $this->calc->ranking($cat, $stand, $start, $comp, $ret_pers, $rls, $ret_ex_aquo,
			$not_counting, $cup, $comps, $max_comp);
	}

	/**
	 * Calculate the prequalified athlets for a given competition
	 *
	 * For performance reasons the result is cached in the session.
	 *
	 * Category/GrpId is the one the athlete had it's results, not necessary the one
	 * he currently allowed to compete in!
	 * Use prequalified with no nation, to get athlets by their current age group.
	 *
	 * @param mixed $comp complete competition array or WetId/rkey
	 * @param int $do_cat complete category array or GrpId/rkey, or 0 for all cat's of $comp
	 * @return boolean|array with GrpId => array(PerId => prequal-reason) or false if comp not found
	 */
	function prequalified_in($comp,$do_cat=0)
	{
		if (!is_array($comp) && !($comp = $this->comp->read($comp)))
		{
			return false;
		}
		if (!$do_cat && !$comp['gruppen'] || !$comp['prequal_ranking'] && !$comp['prequal_comp'])
		{
			return array();	// no category, or no prequalified used --> noone prequalified
		}
		$prequalified = null;
		foreach($do_cat ? array($do_cat) : $comp['gruppen'] as $cat)
		{
			//echo __METHOD__."($comp[rkey],$do_cat) cat='$cat($cat[rkey])'<br>\n";
			if (!is_array($cat) && !($cat = $this->cats->read($cat)))
			{
				return false;
			}
			$cat_id = $cat['GrpId'];

			if (!is_array($prequalified))
			{
				list($prequal_comp,$prequalified) = Api\Cache::getSession('ranking', 'prequalified');

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
				if (($prequal_ranking = $this->comp->prequal_ranking($cat_id,$comp)))
				{
					switch ($comp['prequal_type'])
					{
						case 1:		// prequalified are from ranking 1. Jan of current year
							$stand = ($comp['datum']-1).'-12-31';
							break;

						default:	// prequalified are from ranking of competition date
							$stand = $comp['datum'];
							break;
					}
					// german youth does NOT use ranking, but cup result since 2008!
					if (in_array($cat_id,array(48,49,50,11,12,13)))
					{
						$nul = null;
						if (!($ranking = $this->ranking($cat,$stand,$nul,$nul,$nul,$nul,$nul,$nul,
							$comp['serie'] ? $comp['serie'] : sprintf('%02d_JC',date('y')))))
						{
							$ranking = $this->ranking($cat,$stand,$nul,$nul,$nul,$nul,$nul,$nul,
								sprintf('%02d_JC',date('y')-1));	// previous year
						}
					}
					else
					{
						$ranking = $this->ranking($cat,$stand,$nul,$nul,$nul,$nul,$nul,$nul);
					}
					if ($ranking)
					foreach($ranking as $athlet)
					{
						if ($athlet['platz'] > $prequal_ranking) break;

						if (!in_array($athlet['PerId'],$prequalified[$cat_id]))
						{
							if (isset($prequalified[$cat_id][$athlet['PerId']])) $prequalified[$cat_id][$athlet['PerId']] .= "\n";
							$prequalified[$cat_id][$athlet['PerId']] .= $athlet['platz'].'. ranking';
						}
					}
				}
				Api\Cache::setSession('ranking', 'prequalified', array($comp,$prequalified));
			}
		}
		//echo "prequalifed($comp[rkey],$do_cat$do_cat[rkey]) ="; _debug_array($prequalified);
		return $do_cat ? $prequalified[$cat_id] : $prequalified;
	}

	/**
	 * Get all prequalifed athlets for a competition by their current age group, optional filtered by nation or federation
	 *
	 * @param mixed $comp complete competition array or WetId/rkey
	 * @param string|int $nation =null 3-digit nat-code or integer fed_parent of athletes
	 * @return boolean|array with GrpId => array(PerId => prequal-reason) or false if comp not found
	 */
	function prequalified($comp,$nation=null)
	{
		//echo "<p>".__METHOD__."(".array2string($comp).",$nation)</p>\n";
		if (!($prequalified = $this->prequalified_in($comp))) return false;

		$all_cats = $nat_prequals = array();
		foreach($prequalified as $cat => $prequals)
		{
			$all_cats = array_unique(array_merge($all_cats, array_keys($prequals)));
			$nat_prequals[$cat] = array();
		}

		if (count($all_cats))
		{
			$filter = array(
				'PerId'  => $all_cats,
			);
			if (is_numeric($nation) && (is_array($comp) || ($comp = $this->comp->read($comp))) && $comp['nation'])
			{
				$filter[] = 'fed_parent='.(int)$nation;
				$filter['license_nation'] = $comp['nation'];	// otherwise we return international licenses
			}
			elseif($nation)
			{
				$filter['nation'] = $nation;
			}
			foreach((array)$this->athlete->search(array(),'geb_date,PerId','nachname,vorname','','',false,'AND',false,$filter,false) as $athlete)
			{
				foreach($prequalified as $cat => $prequals)
				{
					if (isset($prequals[$athlete['PerId']]) && $this->in_agegroup($athlete['geb_date'],$cat,$comp))
					{
						$nat_prequals[$cat][$athlete['PerId']] = $prequals[$athlete['PerId']];
					}
				}
			}
		}
		//echo "nat_prequals="; _debug_array($nat_prequals);
		return $nat_prequals;
	}

	/**
	 * Check if given athlete can register for a category and (optional) competition
	 *
	 * @param int|array $athlete
	 * @param int|array $cat
	 * @param int|array $comp
	 * @param string $mode =Registration::REGISTERED or eg. "replace"
	 * @throws Api\Exception\WrongParameter if $athlete, $cat or $comp are not found
	 * @return string translated error-message or null
	 */
	function error_register($athlete, $cat, $comp, $mode=Registration::REGISTERED)
	{
		if (!is_array($athlete) && !($athlete = $this->athlete->read($athlete)))
		{
			throw new Api\Exception\WrongParameter(lang('Athlete NOT found !!!'));
		}
		if (!is_array($cat) && !($cat = $this->cats->read($cat)))
		{
			throw new Api\Exception\WrongParameter(lang('Category NOT found !!!'));
		}
		if (!is_array($comp) && !($comp = $this->comp->read($comp)))
		{
			throw new Api\Exception\WrongParameter(lang('Competition NOT found !!!'));
		}
		$error = $replace = null;
		if ($comp && ($this->comp->has_results($comp,$cat) || !($this->is_admin || $this->is_judge($comp)) &&
			($this->date_over($comp['deadline'] ? $comp['deadline'] : $comp['datum'])) &&
			($mode !== 'replace' || empty($comp['replace_deadline']) || $this->date_over($comp['replace_deadline']))))
		{
			$error = lang('Registration for this competition is over!');
		}
		elseif (!$this->registration_check($comp, $athlete, $cat['GrpId'], $replace) &&
			($mode !== 'replace' || !$replace))
		{
			$error = lang('Missing registration rights!');
		}
		elseif ($comp && $comp['nation'] && (
			in_array((int)$comp['open_comp'], array(Competition::OPEN_NOT, Competition::OPEN_NATION)) && $athlete['nation'] != $comp['nation'] ||
			$comp['open_comp'] == Competition::OPEN_DACH && !in_array($athlete['nation'], array('GER','SUI','AUT'))))
		{
			$error = lang('Wrong nationality');
		}
		elseif ($cat['sex'] && $cat['sex'] != $athlete['sex'])
		{
			$error = lang('Wrong gender');
		}
		elseif(!$this->in_agegroup($athlete['geb_date'], $cat, $comp))
		{
			$error = lang('Invalid age for age-group of category');
		}
		elseif (in_array($mode, [Registration::REGISTERED, 'replace']))
		{
			if ($athlete['license'] == 'n' && !$this->allow_no_license_registration($comp))
			{
				$error = lang('This athlete has NO license!');
			}
			elseif ($athlete['license'] == 's')
			{
				$error = lang('Athlete is suspended !!!');
			}
		}
		return $error;
	}

	/**
	 * (de-)register an athlete for a competition and category
	 *
	 * @param int $comp WetId
	 * @param int $cat GrpId
	 * @param int|array $athlete PerId or complete athlete array
	 * @param int $mode =Registration::REGISTERED ::DELETED, ::PREQUALIFIED, ::CONFIRMED
	 * @param string& $msg =null on return over quota message for admins or jury
	 * @param string $prequal_reason =null reason why athlete is prequalified
	 * @param int $replace PerId of athlete to replace or null
	 * @throws Api\Exception\WrongUserinput with error message for not matching agegroup or over quota
	 * @throws Api\Exception\WrongParameter for other errors
	 * @return boolean true of everything is ok, false on error
	 */
	function register($comp, $cat, $athlete, $mode=Registration::REGISTERED, &$msg=null, $prequal_reason=null, $replace=null)
	{
		if (!is_array($athlete)) $athlete = $this->athlete->read($athlete);
		if (!is_array($comp)) $comp = $this->comp->read($comp);
		if (!$comp || !$cat || !$athlete) return false;

		$keys = array(
			'WetId' => $comp['WetId'],
			'GrpId' => is_array($cat) ? $cat['GrpId'] : $cat,
			'PerId' => $athlete['PerId'],
			'reg_deleted IS NULL',
		);
		// check for an active (not deleted) registration
		list($data) = $this->registration->search(array(), false, '', '', '*', false, 'AND', false, $keys+array('reg_deleted IS NULL'));
		$this->registration->init($data ? $data : array());
		unset($keys[0]);	// reg_deleted IS NULL

		// prequalify and confirm needs competition rights
		if (in_array($mode, array('prequalify', 'confirm')) && !$this->acl_check_comp($comp))
		{
			throw new Api\Exception\WrongUserinput(lang('Permission denied !!!'));
		}

		// check if all conditions for registration are met
		if (($error = $this->error_register($athlete, $cat, $comp, $replace ? 'replace' : $mode)))
		{
			throw new Api\Exception\WrongUserinput(strtoupper($athlete['nachname']).', '.$athlete['vorname'].': '.$error);
		}

		// check athlete to replace is registed
		if ($replace)
		{
			list($replace) = $this->registration->search(array(), false, '', '', '*', false, 'AND', false, [
					'PerId' => $r=$replace,
				]+$keys+array('reg_deleted IS NULL'));

			if (!$replace)
			{
				throw new Api\Exception\WrongParameter("Athlete to replace (#$r) not registered!");
			}
			// do not allow to replace with an other registered athlete, as that would implicitly just delete the one to replace
			if ($data && isset($data[Registration::PREFIX.Registration::REGISTERED]))
			{
				throw new Api\Exception\WrongUserinput(lang('Athlete to replace with is already registered!'));
			}
		}

		switch($mode)
		{
			case Registration::DELETED:
				// if athlete is registed and was prequalified
				if ($data && $data[Registration::PREFIX.Registration::REGISTERED] &&
					$data[Registration::PREFIX.Registration::PREQUALIFIED])
				{
					// store current registration as just prequalified, but no longer registered or confirmed
					$this->registration->save(array_merge($data, array(
						Registration::PREFIX.Registration::REGISTERED => null,
						Registration::PREFIX.Registration::REGISTERED.Registration::ACCOUNT_POSTFIX => null,
						Registration::PREFIX.Registration::CONFIRMED => null,
						Registration::PREFIX.Registration::CONFIRMED.Registration::ACCOUNT_POSTFIX => null,
					)));
					// remove id to create new deleted entry
					unset($data['reg_id']);
					$this->registration->init($data);
				}
				// fall through
			case Registration::CONFIRMED:
				if (!$data) throw new Api\Exception\WrongParameter("Athlete is not registered!");
				break;

			case Registration::REGISTERED:
				$nat_fed = !$comp['nation'] || $athlete['nation'] != $comp['nation'] ||
					!$athlete['fed_parent'] && !$athlete['acl_fed_id'] ?	// only use nation, if no RGZ set!
					$athlete['nation'] : ($athlete['acl_fed_id'] ? $athlete['acl_fed_id'] : $athlete['fed_parent']);
				$prequalified = $this->prequalified($comp, $nat_fed);
				if (!$data)
				{
					$data = $keys;
					// if not explicit prequalified, set prequalified timestamp, but no account
					if (isset($prequalified[is_array($cat) ? $cat['GrpId'] : $cat][$athlete['PerId']]))
					{
						$data[Registration::PREFIX.Registration::PREQUALIFIED] = $this->registration->now;
					}
				}
				// check if athlete to replace was not prequalified or new one is too
				if ($replace && (!isset($replace[Registration::PREFIX.Registration::PREQUALIFIED]) ||
					isset($data[Registration::PREFIX.Registration::PREQUALIFIED])))
				{
					break;	// --> no quota check necessary (might fail, as to replace one is not yet deleted!)
				}
				// check quota, if athlete is not prequalified and no complimentary list
				if ($comp['no_complimentary'] &&
					!isset($prequalified[is_array($cat) ? $cat['GrpId'] : $cat][$athlete['PerId']]) &&
					!isset($data[Registration::PREFIX.Registration::PREQUALIFIED]))
				{
					unset($keys['PerId']);
					$keys[] = Registration::PREFIX.Registration::PREQUALIFIED.' IS NULL';
					if (!is_numeric($nat_fed))
					{
						$keys['nation'] = $nat_fed;
					}
					else
					{
						$keys[!$athlete['acl_fed_id'] ? 'fed_parent' : 'acl_fed_id'] = $nat_fed;
					}
					$this->registration->search(array(), true, 'reg_id', '', '', false, 'AND', array(0, 1), $keys);
					$max_quota = $this->comp->quota($nat_fed, $keys['GrpId'], $comp, $multi_cat);
					if ($max_quota && $max_quota <= $this->registration->total)
					{
						$msg = lang('No complimentary list (over quota)').' quota='.(int)$max_quota.'!';

						if ($replace && isset($replace[Registration::PREFIX.Registration::PREQUALIFIED]))
						{
							$msg .= "\n".lang('Athlete to replace was prequalied!');
						}
					}
					// check multi-category quota(s), if not just replacing athletes
					if (!$replace && $multi_cat && ($msgs = $this->check_multi_cat_quota($keys, $multi_cat)))
					{
						$msg .= ($msg ? "\n\n" : '').lang('No complimentary list (over quota)').': '.implode("\n", $msgs);
					}
					// check total quota for combined plus single discipline registration, if one is set
					$info = null;
					if ($comp['total_per_discipline'] &&
						($err = $this->check_combined_registration($comp, $cat, $keys, $athlete, $info)))
					{
						$msg .= ($msg?"\n\n":'').$err;

						if (!$this->is_admin && !$this->is_judge($comp))
						{
							throw new Api\Exception\WrongUserinput($msg);
						}
					}
					// only judges or admins are allowed to ignore set quota
					if (!empty($msg) && !$this->is_admin && !$this->is_judge($comp))
					{
						throw new Api\Exception\WrongUserinput($msg);
					}
					if ($info) $msg .= ($msg?"\n\n":'').$info;
				}
				break;

			case Registration::PREQUALIFIED:
				if (!$data) $data = $keys;
				if ($prequal_reason) $data['reg_prequal_reason'] = $prequal_reason;
				break;

			default:
				throw new Api\Exception\WrongParameter(__METHOD__."($comp, $cat, , '$mode') unknown mode '$mode'!");
		}

		$this->registration->init($data);
		$ret = !$this->registration->save(array(
			Registration::PREFIX.$mode => $this->registration->now,
			Registration::PREFIX.$mode.Registration::ACCOUNT_POSTFIX => $this->user,
		));

		// if successful registered and we replace --> delete to replace one now
		if ($ret && $replace)
		{
			$this->registration->init($replace);
			$this->registration->save(array(
				Registration::PREFIX.Registration::DELETED => $this->registration->now,
				Registration::PREFIX.Registration::DELETED.Registration::ACCOUNT_POSTFIX => $this->user,
			));
		}
		return $ret;
	}

	/**
	 * Check if current registration would violate any multi-category quota
	 *
	 * @param array $keys values for WetId, GrpId and nation or federation
	 * @param array $multi_cat
	 * @return array of error messages
	 */
	private function check_multi_cat_quota(array $keys, array $multi_cat)
	{
		$msgs = [];
		foreach($multi_cat as $data)
		{
			$keys['GrpId'] = $data['cat'];
			if (empty($data['fed']))
			{
				unset($keys['nation'], $keys['fed_parent'], $keys['acl_fed_id']);
			}
			elseif (!is_numeric($data['fed']))
			{
				$keys['nations'] = $data['fed'];
			}
			elseif(isset($keys['fed_parent']))
			{
				$keys['fed_parent'] = $data['fed'];
			}
			elseif(isset($keys['fed_parent']))
			{
				$keys['acl_fed_id'] = $data['fed'];
			}
			$this->registration->search(array(), true, 'reg_id', '', '', false, 'AND', array(0, 1), $keys);
			if ($this->registration->total >= $data['num'])
			{
				$msgs[] = lang('For categories %1 together only a total of %2 registrations allowed!',
					implode(', ', $this->cats->names(['GrpId' => $data['cat']], 0)), $data['num']);
			}
		}
		return $msgs;
	}

	/**
	 * Registration checks for comeptions with combined categories
	 *
	 * 1) Do not allow registraition in single category, if already registered for combined
	 *
	 * 2) Check if total quota for combined plus single discipline is exceeded
	 * For a single discipline registration we only need to check together with combined category.
	 * For a combined registration we need to check all 3 disciplines!
	 *
	 * 3) For registration in combined, if already registered in one or more single categories,
	 * modify single cat registrations in favor of the combined registration
	 * (last "check" as it removes the single category registration!)
	 *
	 * @param array $comp
	 * @param array $cat
	 * @param array $keys
	 * @param array $athlete
	 * @param string& $info on return information message eg. registration changed to a combined one
	 * @return string error-message or null if registration is ok
	 * @throws Api\Exception\WrongUserinput for things even admin or judges are not allowed
	 *	eg. register in single cat when already registered in combined
	 */
	function check_combined_registration(array $comp, array $cat, array $keys, array $athlete, &$info)
	{
		$cats_to_check = array();
		// registration is in combined category
		if ($cat['mgroups'])
		{
			foreach(array_keys($cat['mgroups']) as $id)
			{
				$cats_to_check[] = array($cat['GrpId'], $id);
			}
		}
		// single category registration --> search combined category including $cat
		elseif (($combined = $this->cats->get_combined($cat['GrpId'], $comp['gruppen'])))
		{
			// check if already registered in combined category
			if ($this->registration->read(array(
				'WetId' => $comp['WetId'],
				'GrpId' => $combined,
				'PerId' => $athlete['PerId'],
				'reg_registered IS NOT NULL AND reg_deleted IS NULL'
			)))
			{
				throw new Api\Exception\WrongUserinput(lang('Already registered for combined, no single category registration allowed!'));
			}
			$cats_to_check[] = array($combined, $cat['GrpId']);
		}
		else
		{
			return null;	// not a combined cat, eg. TOF
		}
		// checks need to take into account that athletes might be registered in single Api\Categories
		$exceeding = array();
		foreach($cats_to_check as $cats)
		{
			$this->registration->search(array(), 'DISTINCT '.Registration::TABLE.'.PerId',
				'nachname', '', '', false, 'AND', array(0, 1), array(
					'GrpId' => $cats,
					Registration::TABLE.'.PerId!='.(int)$athlete['PerId'],	// do not return athlete itself
				)+$keys);
			//error_log(__METHOD__."() total_per_discipline=$comp[total_per_discipline], cats=".array2string($cats).", keys=".array2string($keys)." --> total-registered=".$this->registration->total);

			// check if athlete is prequalifed in combined or single discipline, and therefore not counting for quota
			if ($comp['total_per_discipline'] == $this->registration->total &&
				$this->registration->read(array(
					'WetId' => $comp['WetId'],
					'PerId' => $athlete['PerId'],
					'GrpId' => $cats,
					'reg_deleted IS NULL AND reg_prequalified IS NOT NULL'
				)))
			{
				continue;
			}
			if ($comp['total_per_discipline'] <= $this->registration->total &&
				($ex_cat = $this->cats->read($cats[1])))
			{
				$exceeding[] = $ex_cat['discipline'];
			}
		}
		// if exceeding and no admin/judge fail now (for admins we first delete single cats, as this is no error!)
		if ($exceeding && !$this->is_admin && !$this->is_judge($comp))
		{
			return lang('Total quota of %1 exceeded in %2 incl. combined starters!',
				$comp['total_per_discipline'], implode(' '.lang('and').' ', array_map('lang', $exceeding)));
		}
		// check for combined registration, if there are already registrations in one or more single cats
		// --> delete single category registrations, but preserve prequalified(!)
		$deleted = 0;
		foreach(array_keys($cat['mgroups']) as $GrpId)
		{
			if (($reg = $this->registration->read(array(
				'WetId' => $comp['WetId'],
				'GrpId' => $GrpId,
				'PerId' => $athlete['PerId'],
				'reg_registered IS NOT NULL AND reg_deleted IS NULL'
			))))
			{
				$msg = null;
				$this->register($comp, $GrpId, $athlete, Registration::DELETED, $msg);

				if ($msg) return $msg;

				++$deleted;
			}
		}
		if ($deleted)
		{
			// inform user about removed registrations
			$info = lang('Current registration in %1 category(s) were deleted.', $deleted);
		}
		if ($exceeding)
		{
			return lang('Total quota of %1 exceeded in %2 incl. combined starters!',
				$comp['total_per_discipline'], implode(' '.lang('and').' ', array_map('lang', $exceeding)));
		}
		return null;
	}

	/**
	 * Check if a given birthdate is in the age-group of a category
	 *
	 * @param int|string $birthdate birthdate Y-m-d or birthyear
	 * @param int|array $cat GrpId or category array
	 * @param int|array $comp =null competition which date to use or default NULL to use the current year
	 * @return boolean true if $birthdate is in the agegroup or category does NOT use age-groups
	 */
	function in_agegroup($birthdate,$cat,$comp=null)
	{
		static $comps = null;	// some caching

		if (is_null($comp))
		{
			$year = null;
		}
		else
		{
			if (!is_array($comp))
			{
				if (!isset($comps[$comp]))
				{
					$comps[$comp] = $this->comp->read($comp);
				}
				$comp = $comps[$comp];
			}
			$year = (int)$comp['datum'];
		}
		return $this->cats->in_agegroup($birthdate,$cat,$year);
	}

	/**
	 * Return cats of a competition an athlete is allowed to register for
	 *
	 * @param array $comp
	 * @param array $athlete
	 * @return array GrpId => name pairs
	 */
	function matching_cats(array $comp, array $athlete)
	{
		$cats = array();
		foreach($comp['gruppen'] as $rkey)
		{
			if (!($cat = $this->cats->read($rkey))) continue;
			if ($cat['sex'] && $cat['sex'] != $athlete['sex']) continue;
			if (!$this->cats->in_agegroup($athlete['geb_date'], $cat, (int)$comp['datum'])) continue;
			if ($this->comp->has_results($comp['WetId'],$cat['GrpId'])) continue;

			$cats[$cat['GrpId']] = $cat['name'];
		}
		return $cats;
	}

	/**
	 * Generate a startlist for the given competition and category
	 *
	 * start/registration-numbers are saved as points in a result with place=0, the points contain:
	 * - registration number in the last 6 bit (< 32 prequalified, >= 32 quota or supplimentary) ($pkt & 63)
	 * - startnumber in the next 8 bits (($pkt >> 6) & 255))
	 * - route in the other bits ($pkt >> 14)
	 *
	 * @param int|array $comp WetId or complete comp array
	 * @param int|array $cat GrpId or complete cat array
	 * @param int $num_routes =1 number of routes, default 1
	 * @param int $max_compl =999 maximum number of climbers from the complimentary list
	 * @param int $order =0 int with bitfield of, default random
	 * 	&1  use ranking for order, unranked are random behind last ranked
	 *  &2  use cup for order, unranked are random behind last ranked
	 *  &4  reverse ranking or cup (--> unranked first)
	 *  &8  use ranking/cup for distribution only, order is random
	 * @param int $use_ranking =0 0: randomize all athlets, 1: use reversed ranking, 2: use reversed cup ranking first,
	 * 	new random order but distribution on multiple routes by 3: ranking or 4: cup
	 * @param boolean $stagger =false insert starters of other route behind
	 * @param array $old_startlist =null old start order which should be preserved PerId => array (with start_number,route_order) pairs in start_order
	 * @param int $quali_preselected =null number of preselected from cup or ranking, returned last, rest is randomized
	 * @param int $add_cat =null additional category to add registered atheletes from
	 * @return boolean|array true or array with starters (if is_array($old_startlist)) if the startlist has been successful generated AND saved, false otherwise
	 */
	function generate_startlist($comp,$cat,$num_routes=1,$max_compl=999,$order=0,$stagger=false,$old_startlist=null,$quali_preselected=null,$add_cat=null)
	{
		// order bitfields to booleans
		$use_ranking = (boolean)($order & 3);	// cup OR ranking
		$use_cup = (boolean)($order & 2);
		$reverse_ranking = ($order & 4) || $quali_preselected;	// preselected are displayed last
		$distribution_only = ($order & 8) && !$quali_preselected;
		//echo "<p>".__METHOD__."($comp,$cat,num_routes=$num_routes,max_compl=$max_compl,order=$order,stagger=$stagger,) use_ranking=$use_ranking,use_cup=$use_cup, reverse_ranking=$reverse_ranking, distribution_only=$distribution_only</p>\n";

		// $num_routes == 4 is first distributed on 2 routes (like num_routes == 2) and then staggered to 4
		$distribute_to = $num_routes == 4 ? 2 : $num_routes;

		if (!is_array($comp)) $comp = $this->comp->read($comp);
		if (!is_array($cat)) $cat = $this->cats->read($cat);
		if (!$comp || !$cat) return false;

		if ($this->debug) echo '<p>'.__METHOD__."($comp[rkey],$cat[rkey],$num_routes,$max_compl,$order,$stagger) use_ranking=$use_ranking, use_cup=$use_cup, reverse_ranking=$reverse_ranking, distribution_only=$distribution_only</p>\n";

		$filter = array(
			'WetId'  => $comp['WetId'],
			'GrpId'  => $add_cat ? array($add_cat, $cat['GrpId']) : $cat['GrpId'],
			// only use confirmed registrations or all
			'state'  => (int)$comp['selfregister'] == 1 ? Registration::CONFIRMED : Registration::REGISTERED,
		);
		$starters = $this->registration->read($filter,'',true,'nation,reg_id');

		if (!is_array($starters) || !count($starters)) return false;	// no starters, or eg. already a result

		for ($route = 1; $route <= $distribute_to; ++$route)
		{
			$startlist[$route] = array();
		}
		$prequalified = $this->result->prequalified($comp,$cat);

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
							'GrpId' => $cat['GrpId'],
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
		$starters_by_id = [];
		foreach($starters as $k => $athlete)
		{
			$athlete['GrpId'] = $cat['GrpId'];	// could be $add_cat
			$starters_by_id[$athlete['PerId']] = $athlete;
		}
		if (true) $starters = $starters_by_id;

		$reset_data = 1;
		$ranked = array();
		// do we use a ranking, if yes calculate it and place the ranked competitors at the end of the startlist
		if ($use_ranking)
		{
			$stand = $comp['datum'];
			$nul = null;
		 	$ranking =& $this->ranking($cat,$stand,$nul,$nul,$nul,$nul,$nul,$nul,$use_cup ? $comp['serie'] : '');

			// check if it might be the first comp of the year
			if ($use_cup && (!$ranking || count($ranking) <= 1) && ($cup = $this->cup->read($comp['serie'])))
			{
				$stand = sprintf('%04d-12-31',date('Y')-1);
				$cup_rkey = substr($stand,2,2).substr($cup['rkey'],2);
				if (!($cup = $this->cup->read($cup_rkey)))
				{
					// not nice but better then no warning
					echo "<p>".lang('Previous years cup "%1" not found!',$cup_rkey)."</p>\n";
				}
				else
				{
					//echo "<p>using cup ranking of $stand from cup $cup_rkey instead!</p>\n";
		 			$ranking =& $this->ranking($cat,$stand,$nul,$nul,$nul,$nul,$nul,$nul,$cup);
				}
			}
			// we generate the startlist starting from the end = first of the ranking
			foreach((array) $ranking as $athlete)
			{
				if (isset($starters[$athlete['PerId']]) && (!$quali_preselected || $athlete['platz'] <= $quali_preselected))
				{
					$starters[$athlete['PerId']]['ranking'] = $athlete['platz'];
					$this->move_to_startlist($starters, $athlete['PerId'], $startlist, $distribute_to, $reset_data, __LINE__);
					$reset_data = false;
					$ranked[$athlete['PerId']] = true;
				}
			}
/* old 2007 rules: reverse ranking, but unranked last (not longer in use)
			if ($cat['discipline'] != 'speed')
			{
				// new modus, not for speed(!): unranked starters at the END of the startlist
				// reverse the startlists now, to have the first in the ranking at the end of the list
				for ($route = 1; $route <= $distribute_to; ++$route)
				{
					$startlist[$route] = array_reverse($startlist[$route]);
				}
			}
*/
		}
		// if we have a startorder to preserv, we use these competitior first
		if ($old_startlist && $use_ranking && !$distribution_only)
		{
			if ($reverse_ranking)	// reversed order
			{
				$old_startlist = array_reverse($old_startlist);
			}
			foreach(array_diff_key($old_startlist,$ranked) as $PerId => $starter)
			{
				if(isset($starters[$PerId]))
				{
					$this->move_to_startlist($starters, $PerId, $startlist, $distribute_to, 1+$starter['route_order'], __LINE__);
				}
			}
			// make sure both routes get equaly filled up
			if (!($num_routes&1)) $reset_data = 1+(int)(count($startlist[1]) > count($startlist[2]));
		}
		// now we randomly pick starters and devide them on the routes
		while(count($starters))
		{
			$this->move_to_startlist($starters, array_rand($starters), $startlist, $distribute_to, $reset_data, __LINE__);
			$reset_data = false;
		}
		// we have an old startlist --> try to keep the position (unless we randomize everything / distribution only)
		if ($old_startlist && !$distribution_only)
		{
			// reindex startlist's by PerId in $starters
			$starters = array();
			foreach($startlist as $num => $startlist_num)
			{
				foreach($startlist_num as $starter)
				{
					$starters[$num][$starter['PerId']] = $starter;
				}
			}
			// move (reindexed) starters in their old order into the new routes
			$startlist[2] = $startlist[1] = array();
			foreach($old_startlist as $data)
			{
				$PerId = $data['PerId'];
				if (isset($starters[1][$PerId]))
				{
					$this->move_to_startlist($starters[1], $PerId, $startlist, $distribute_to, 1, __LINE__);
				}
				elseif (isset($starters[2][$PerId]))
				{
					$this->move_to_startlist($starters[2], $PerId, $startlist, $distribute_to, 2, __LINE__);
				}
			}
			// add the new athlets (not in old_startlist) after the existing ones
			$startlist[1] = array_merge($startlist[1],$starters[1]);
			if (is_array($starters[2])) $startlist[2] = array_merge($startlist[2],$starters[2]);
			unset($starters);
		}
		elseif ($distribution_only)
		{
			// randomize after seeding (distribution in 2 routes) by ranking
			foreach($startlist as &$to_shuffle)
			{
				shuffle($to_shuffle);
			}
		}
		if ($stagger)
		{
			if ($num_routes == 4)	// TWO_QUALI_GROUPS eg. lead world championship
			{
				$startlist[3] = $startlist[4] = $startlist[2];
				$startlist[2] = $startlist[1];
			}
			else
			{
				$startlist[1] = $startlist[2] = array_merge($startlist[1], $startlist[2]);
			}
		}
		// reverse the startlist if neccessary
		for ($route = 1; $route <= $num_routes; ++$route)
		{
			if ($reverse_ranking)
			{
				// if we use a reverse ranking, we have to reverse the startlist
				$startlist[$route] = array_reverse($startlist[$route]);
			}
			foreach($startlist[$route] as $n => $athlete)
			{
				$startlist[$route][$n]['start_order'] = !$stagger || $route & 1 ? 1+$n : self::stagger(1+$n, count($startlist[$route]));
				// preserv the start_number if given
				if ($old_startlist) $startlist[$route][$n]['start_number'] = $old_startlist[$athlete['PerId']]['start_number'];
				// we preserve the registration number in the last 6 bit's
				$startlist[$route][$n]['pkt'] = (($route-1) << 14) + (($startlist[$route][$n]['start_order']) << 6) + ($athlete['pkt'] & 63);
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
	 * Get staggered startorder for $anz participants
	 *
	 * Change from 2020 on: first group is bigger than second (for an odd number)
	 * Example for 21 starters: 1. in Lane A will be 12. in Lane B
	 *
	 * @param int $start_order on lane A
	 * @param int $anz
	 * @return int start_order on lane B
	 */
	static function stagger($start_order, $anz)
	{
		//error_log(__METHOD__."($start_order, $anz) returning ".(1+((floor($anz/2)+$start_order-1) % $anz)));
		return 1+((floor(($anz+1)/2)+$start_order-1) % $anz);
	}

	/**
	 * Get the start- and route-number from the pkt field of a registration startlist
	 *
	 * @param int $pkt
	 * @param int $only_route 1, 2, ... if only a certain route-number should be returned (returns false if no match)
	 * @return string/ startnumber or false, if it's the wrong route
	 */
	function  pkt2start($pkt,$only_route=null)
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
	 * @param array &$starters single source liste
	 * @param string $k key into source list of athlete to move
	 * @param array &$startlist destination lists, indexed by route=1|2, athletes added at the end
	 * @param int $num_routes number of routes to use 1, 2, ...
	 * @param int $reset_data =null set internal counter $route to $reset_data, if given and > 0
	 * @param int $line =0 line number of caller for debug purpose
	 */
	function move_to_startlist(&$starters,$k,&$startlist,$num_routes,$reset_data=null,$line=0)
	{
		static $route = 1;
		static $last_route = 1;
		if ($reset_data)  $route = $reset_data;
		//echo "<p>$line: ".__METHOD__."(,$k,,$num_routes,$reset_data,$line) route=$route, athlete=".$starters[$k]['nachname'].', '.$starters[$k]['vorname']."</p>\n";
		unset($line);

		$startlist[$route][] =& $starters[$k];
		unset($starters[$k]);

		// previous mode: simply alternating
		//if (++$route > $num_routes) $route = 1;

		// new mode: 1, 2, 2, 1, 1, 2, 2, ...
		$last = $last_route;
		if (true) $last_route = $route;
		if ($last == $route && $num_routes == 2) $route = $route == 1 ? 2 : 1;
	}

	/**
	 * Set the state (selected competition & cat) of the UI
	 *
	 * @param string $calendar
	 * @param int $comp
	 * @param int $cat =null
	 */
	function set_ui_state($calendar=null,$comp=null,$cat=null)
	{
		//echo "<p>".__METHOD__."(calendar='$calendar',comp=$comp,cat=$cat) menuaction=$_GET[menuaction]</p>\n";
		foreach(array('registration','result','import') as $type)
		{
			$data = Api\Cache::getSession('ranking', $type);
			foreach(array('calendar','comp','cat') as $name)
			{
				if (!is_null($$name)) $data[$name] = $$name;
			}
			Api\Cache::setSession('ranking', $type, $data);
		}
		// only store our menuaction, specially not eTemplate2 home.etemplate_new.ajax_process_exec.etemplate!
		if (strpos($_GET['menuaction'], 'ranking.') === 0 && strpos($_GET['menuaction'], '.ajax_') === false)
		{
			Api\Cache::setSession('ranking', 'menuaction', $_GET['menuaction']);
		}
		unset($calendar, $comp, $cat);	// used as $$name above, quitens IDE warnings
	}

	/**
	 * Calculate the feldfactor of a competition
	 *
	 * For the fieldfactor the ranking of the day before the competition starts has to be used!
	 *
	 * @param int|array $comp competition id or array
	 * @param int|array $cat category id or array
	 * @param array $starters with athltets (PerId's)
	 * @return double 1.0 for no feldfactor!
	 */
	function feldfactor($comp,$cat,$starters)
	{
		if (!is_array($comp) && !($comp = $this->comp->read($comp))) return false;
		if (!is_array($cat) && !($cat = $this->cats->read($cat))) return false;

		$rls = $cat['vor'] && $comp['datum'] < $cat['vor'] ? $cat['vor_rls'] : $cat['rls'];
		$has_feldfakt = $rls && $comp['feld_pkte'] && (double)$comp['faktor'] && $cat['rkey'] != "OMEN";

		if (!$has_feldfakt) return (double)$comp['faktor'];

		// we have to use the ranking of the day before the competition starts
		$stands = explode('-',$comp['datum']);
		$stand = date('Y-m-d',mktime(0,0,0,$stands[1],$stands[2]-1,$stands[0]));
		$start = $nul = $ranglist = $pkte = null;
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
		$ret = round($feldfakt / $max_pkte,2);
		//echo "<p>".__METHOD__."('$comp[rkey]','$cat[rkey]')==$ret (has_feldfakt=$has_feldfakt)</p>\n";
		return $ret;
	}

	/**
	 * Import a result of a competition into the ranking
	 *
	 * @param array $keys WetId, GrpId
	 * @param array $result PerId => platz pairs
	 * @return string|false success message or false on error
	 */
	function import_ranking($keys,$result)
	{
		if (!$keys['WetId'] || !$keys['GrpId'] ||
			!($comp = $this->comp->read($keys['WetId'])) ||
			!$this->acl_check($comp['nation'],self::ACL_RESULT,$comp) || // permission denied
			$comp['serie'] && !($cup = $this->cup->read($comp['serie'])))
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
		$pkte = $cup_pkte = null;
		$this->pkte->get_pkte($comp['pkte'],$pkte);

		if ($cup)
		{
			// 2006+ EYS counts only european nations
			if (empty($comp['nation']) && (int)$comp['datum'] >= 2006 && preg_match('/_(EYC|EYS)$/',$cup['rkey']))
			{
				$allowed_nations = $this->federation->continent_nations(Federation::EUROPE);
			}
			$this->pkte->get_pkte($cup['pkte'],$cup_pkte);
		}
		// only import given continet's nations (only international competitions)
		if (empty($comp['nation']) && ($cup && $cup['continent'] || $comp['continent']))
		{
			$allowed_nations = $this->federation->continent_nations($comp['continent'] ?
				$comp['continent'] : $cup['continent']);
		}
		// 2009+ int. competitions use only average points for ex aquos (now an explicit attribute!)
		if ($comp['average_ex_aquo'] || empty($comp['nation']) && (int)$comp['datum'] >= 2009 || $cup && $cup['presets']['average_ex_aquo'])
		{
			$ex_aquos = $cup_ex_aquos = array();
			$abs_place = $ex_place = $last_place = 1;
			foreach($result as $PerId => $place)
			{
				if ($allowed_nations && (!is_array($place) || empty($place['nation'])) &&
					($athlete = $this->athlete->read($PerId)))
				{
					$place = $result[$PerId] = array_merge($athlete,
						is_array($place) ? $place : array('result_rank' => $place));
				}
				$nation = '';
				if (is_array($place))
				{
					$nation = $place['nation'];
					$place = $place['result_rank'];
				}
				++$ex_aquos[$place];

				// 2006+ EYS counts only european nations
				if ($allowed_nations)
				{
					if (!in_array($nation,$allowed_nations))
					{
						continue;	// ignore wrong nation
					}
					$result[$PerId]['cup_place'] = $ex_place = $last_place == $place ? $ex_place : $abs_place;
					++$cup_ex_aquos[$ex_place];
					$last_place = $place;
					$abs_place++;
				}
				elseif($cup)
				{
					// if we already have a cup_place set, use it
					if (isset($result[$PerId]['cup_place'])) $place = $result[$PerId]['cup_place'];

					++$cup_ex_aquos[$place];
				}
			}
		}
		// reverse array to store 1. place last, as it is used to determine in calc_rang if competition has a result
		foreach(array_reverse($result, true) as $PerId => $place)
		{
			$nation = $cup_place = $cup_pkt = null;
			if (is_array($place))
			{
				$nation = $place['nation'];
				$cup_place = $place['cup_place'];
				$place = $place['result_rank'];
			}
			if (isset($ex_aquos))
			{
				for($n = $pkt = 0; $n < $ex_aquos[$place]; $n++)
				{
					$pkt += $pkte[$place+$n];
				}
				$pkt /= $ex_aquos[$place];
				$pkt = (int)floor($pkt);	// round down!
			}
			else
			{
				$pkt = $pkte[$place];
			}
			if ($cup && (!$allowed_nations || in_array($nation,$allowed_nations)))
			{
				$pl = isset($cup_place) ? $cup_place : $place;

				if (isset($cup_ex_aquos))
				{
					for($n = $cup_pkt = 0; $n < $cup_ex_aquos[$pl]; $n++)
					{
						$cup_pkt += $cup_pkte[$pl+$n];
					}
					$cup_pkt /= $cup_ex_aquos[$pl];
					$cup_pkt = (int)floor($cup_pkt);	// round down!
				}
				else
				{
					$cup_pkt = $cup_pkte[$pl];
				}
				$cup_pkt = round(100.0 * $cup['faktor'] * $cup_pkt);
			}
			if (!$cup && $allowed_nations && !in_array($nation, $allowed_nations))
			{
				unset($result[$PerId]);
				continue;
			}
			$this->result->save(array(
				'PerId' => $PerId,
				'platz' => isset($cup_place) && $allowed_nations && !$cup ? $cup_place : $place,
				'pkt'   => round(100.0 * $feldfactor * $pkt),
				'cup_platz' => $cup_place,
				'cup_pkt'   => $cup_pkt,
			));
		}
		// not sure if the new code still uses that, but it does not hurt ;-)
		$this->result->save_feldfactor($keys['WetId'],$keys['GrpId'],$feldfactor * $comp['faktor']);

		// invalidate results and ranking feeds
		Export::delete_results_cache($comp, $keys['GrpId']);

		return lang('results of %1 participants imported into the ranking, feldfactor: %2',count($result),sprintf('%4.2f',$feldfactor));
	}
	/**
	 * Parse a csv file
	 *
	 * @param array $keys WetId, GrpId, route_order
	 * @param string|FILE $file uploaded file name or handle
	 * @param boolean $result_only true = only results allowed, false = startlists too
	 * @param boolean $add_athletes =false add not existing athletes, default bail out with an error
	 * @param boolean|int $ignore_comp_heat =false ignore WetId and route_order, default do NOT, or integer WetId to check against
	 * @return string/array error message or result lines
	 */
	function parse_csv($keys,$file,$result_only=false,$add_athletes=false,$ignore_comp_heat=false)
	{
		if (!$keys || !$keys['WetId'] || !$keys['GrpId'] ||
			!($comp = $this->comp->read($keys['WetId'])) ||
			!($cat = $this->cats->read($keys['GrpId'])) ||
			!$this->acl_check($comp['nation'],self::ACL_RESULT,$comp)) // permission denied
		{
			return lang('Permission denied !!!');
		}
		if (!empty($keys['discipline']))
		{
			$discipline = $keys['discipline'];
		}
		else
		{
			$discipline = $comp['discipline'] ? $comp['discipline'] : $cat['discipline'];
		}

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
		$cat_warning = null;
		while (($values = fgetcsv($fp,null,';')))
		{
			if (count($values) != count($labels))
			{
				return lang('Error: dataline %1 contains %2 instead of %3 columns !!!', $n, count($values), count($labels));
			}
			$line = array_combine($labels, $values);

			if ((!$ignore_comp_heat || !is_bool($ignore_comp_heat)) && in_array('comp',$labels) &&
				$line['comp'] != (!$ignore_comp_heat ? $keys['WetId'] : $ignore_comp_heat))
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
			if ($ignore_comp_heat !== true && in_array('heat',$labels) && isset($keys['route_order']) && $keys['route_order'] != $line['heat'])
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
					$matches = null;
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
				$false_start_preg = '/^(\d. )?(false start|'.preg_quote(lang('false start'), '$/').')/i';
				$fall_preg = '/^(fall|'.preg_quote(lang('fall'), '/').')$/i';
				//$wildcard_preg = '/^(wildcard|'.preg_quote(lang('wildcard'), '/').')$/i';
				foreach(isset($arr['time-left']) ? array('' => 'time-left','_r' => 'time-right') : array('' => 'result') as $postfix => $name)
				{
					if (is_numeric($arr[$name]))
					{
						$result['result_time'.$postfix] = (double) $arr[$name];
						$result['eliminated'.$postfix] = '';
					}
					else
					{
						$result['result_time'.$postfix] = '';
						if (preg_match($fall_preg, $arr[$name]))
						{
							$result['eliminated'.$postfix] = ranking_result_bo::FALL;
						}
						elseif (preg_match($false_start_preg))
						{
							$result['eliminated'.$postfix] = ranking_result_bo::ELIMINATED_FALSE_START;
						}
						else
						{
							$result['eliminated'.$postfix] = '';
						}
					}
				}
				if (preg_match($false_start_preg, $arr['result'], $matches))
				{
					$result['false_start'] = max(1, (int)$matches[1]);
				}
				break;

			case 'boulder2018': // NTMz n m
				if (preg_match('/^(\d+)T(\d+)z\s(\d+)\s(\d+)$/', $str, $matches))
				{
					list(, $top, $zone, $top_tries, $zone_tries) = $matches;
					$result['result_top'] = $top ? 100 * $top - $top_tries : null;
					$result['result_zone'] = 100 * $zone - $zone_tries;
					for($i = 1; $i <= 8 && array_key_exists('boulder'.$i, $arr); ++$i)
					{
						$result['top'.$i] = '';
						$result['zone'.$i] = strpos($str,'0z') !== false ? '0' : '';	// we need to differ between 0b and not climbed!
						if (!($boulder = $arr['boulder'.$i])) continue;
						if ($boulder[0] == 't')
						{
							$result['top'.$i] = (int) substr($boulder,1);
							$boulder = strstr($boulder,'z');
						}
						$result['zone'.$i] = (int) substr($boulder,1);
					}
				}
				else
				{
					$result = array();
				}
				break;

			case 'boulder':	// #t# #b#
				if (($bonus_pos = strpos($str,'b')) !== false)	// we split the string on the position of 'b' minus one char, as num of bonus is always 1 digit
				{
					list($top,$top_tries) = explode('t',substr($str,0,$bonus_pos-1));
					list($bonus,$bonus_tries) = explode('b',trim(substr($str,$bonus_pos-1)));
					$result['result_top'] = $top ? 100 * $top - $top_tries : null;
					$result['result_zone'] = 100 * $bonus - $bonus_tries;
					for($i = 1; $i <= 8 && array_key_exists('boulder'.$i,$arr); ++$i)
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
	 * @return int number of merged results
	 * @throws Exeception with error message
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
		// merge result service data
		$this->route_result->merge($from['PerId'],$to['PerId']);
		// merge result data
		$merged = $this->result->merge($from['PerId'],$to['PerId']);
		// merge licenses
		$this->athlete->merge_licenses($from['PerId'],$to['PerId']);

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

	/**
	 * Set athlete nation and (acl_)fed_id from users rights
	 *
	 * @param array &$athlete on return nation, (acl_)fed_id
	 * @param array &$nations =null on return nations user has rights to
	 * @param array &$feds_with_grants =null on return federations user has rights to
	 * @throws Api\Exception\AssertionFailed
	 */
	public function presetFederation(array &$athlete, array &$nations=null, array &$feds_with_grants = null)
	{
		// if user is judge on a LV competition, give him just here rights for whole nation
		if (is_numeric($this->only_nation_athlete) && ($fed = $this->federation->read($this->only_nation_athlete)))
		{
			$this->only_nation_athlete = $fed['nation'];
		}
		if ($this->only_nation_athlete) $athlete['nation'] = $this->only_nation_athlete;
		if (!in_array('NULL', $this->athlete_rights)) $nations = array_intersect_key($nations, array_flip($this->athlete_rights));
		//using athlete_rights_no_judge (and NOT athlete_rights) to check if we should look for federation rights, as otherwise judges loose their regular federation rights
		if (!$this->is_admin && !$this->athlete_rights_no_judge &&
			($grants = $this->federation->get_user_grants()))
		{
			$feds_with_grants = [];
			foreach ($grants as $fed_id => $rights)
			{
				if ($rights & self::ACL_ATHLETE)
				{
					$feds_with_grants[] = $fed_id;
				}
			}
			if ($feds_with_grants)
			{
				// if we have a/some feds the user is responsible for get the first (and only) nation
				$nations = $this->federation->query_list('nation', 'nation', array('fed_id' => $feds_with_grants));
				if (count($nations) != 1) throw new Api\Exception\AssertionFailed('Fed grants only implemented for a single nation!');
				$this->only_nation_athlete = key($nations);
				$athlete['nation'] = $this->only_nation_athlete;
				// SUI Regionalzentren
				if ($this->only_nation_athlete == 'SUI' && count($feds_with_grants) == 1)
				{
					list($athlete['acl_fed_id']) = $feds_with_grants;
					$athlete['fed_id'] = key($this->athlete->federations($this->only_nation_athlete, true));    // set the national federation
					unset($feds_with_grants);
				}
				// everyone else (eg. GER Landesverbände)
				else
				{
					list($athlete['fed_id']) = $feds_with_grants;
				}
			}
		}
	}
}

/**
 * Some deprecated defines
 */
define('EGW_ACL_ATHLETE', Base::ACL_ATHLETE);
define('EGW_ACL_REGISTER', Base::ACL_REGISTER);
define('EGW_ACL_RESULT', Base::ACL_RESULT);