<?php
/**
 * EGroupware digital ROCK Rankings - result business object/logic
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2007-19 by Ralf Becker <RalfBecker@digitalrock.de>
 */

use EGroupware\Api;
use EGroupware\Ranking\Base;
use EGroupware\Ranking\Export;

/**
 * @deprecated use ranking_route_result::(TOP_(PLUS|HEIGHT)|(ELIMINATED|WILDCARD)_TIME)
 */
define('TOP_PLUS',9999);
define('TOP_HEIGHT',99999999);
define('ELIMINATED_TIME',999999);
define('WILDCARD_TIME',1);

define('ONE_QUALI',0);
define('TWO_QUALI_HALF',1);
define('TWO_QUALI_ALL',2);				// EYS (and all TWO_QUALI_ALL*, if route::read($keys,false) is used)
define('TWO_QUALI_SPEED',3);
define('TWOxTWO_QUALI',4);				// two quali rounds on two routes each
define('TWO_QUALI_ALL_SEED_STAGGER',5);	// lead on 2 routes for all on flash
define('TWO_QUALI_ALL_NO_STAGGER',6);	// lead on 2 routes for all on sight
define('TWO_QUALI_BESTOF',7);			// speed best of two (record format)
define('TWO_QUALI_ALL_SUM',8);			// lead on 2 routes with height sum
define('TWO_QUALI_ALL_NO_COUNTBACK',9);	// 2012 EYC, no countback, otherwise like TWO_QUALI_ALL
define('TWO_QUALI_GROUPS', 10);			// two quali groups with 2 flash routes each (different from TWOxTWO_QUALI!)
define('THREE_QUALI_ALL_NO_STAGGER',11);	// lead on 3 routes for all on flash for combined format

define('LEAD',4);
define('BOULDER',8);
define('SPEED',16);

define('STATUS_UNPUBLISHED',0);
define('STATUS_STARTLIST',1);
define('STATUS_RESULT_OFFICIAL',2);

class ranking_result_bo extends Base
{
	/**
	 * values and labels for route_order
	 *
	 * @var array
	 */
	var $order_nums;
	/**
	 * Disciplines / result-modi
	 *
	 * @var array
	 */
	var $rs_disciplines = array(
		'lead' => 'lead',
		'boulder2018' => 'boulder: 2018+ rules (tops, zones, top-tries, zone-tries)',
		'speed' => 'speed',
		'combined' => 'combined',	// new olympic fromat
		'speedrelay' => 'speedrelay',
		'boulder' => 'boulder: pre 2018 rules (tops, top-tries, bonus, bonus-tries)',
		'boulderheight' => 'boulder: height, tries',	// height and tries, as used in Arco
		'selfscore' => 'boulder: self-scoring',	// self-scoring honesty system
	);
	/**
	 * values and labels for route_status
	 *
	 * @var array
	 */
	var $stati = array(
		STATUS_UNPUBLISHED     => 'unpublished',
		STATUS_STARTLIST       => 'startlist',
		STATUS_RESULT_OFFICIAL => 'result official',
	);
	/**
	 * All qualification-type, some are historical and not offered for new routes
	 *
	 * @var array
	 */
	var $quali_types = array(
		ONE_QUALI      => 'one Qualification',
		TWO_QUALI_HALF => 'two Qualification, half quota',	// no countback
		TWO_QUALI_ALL_SEED_STAGGER => 'two Qualification for all, flash simultaniously',	// lead on 2 routes for all on flash
		TWO_QUALI_ALL  => 'two Qualification for all, flash one after the other',			// multiply the rank
		TWO_QUALI_ALL_NO_STAGGER   => 'two Qualification for all, identical startorder (SUI)',	// lead on 2 routes for all on sight
		THREE_QUALI_ALL_NO_STAGGER => 'three Qualification for all, identical startorder (SUI)',
		TWO_QUALI_GROUPS => 'two Qualification Starting groups with two staggered flash routes each',	// eg. world championships
		// speed only
		TWO_QUALI_BESTOF=> 'best of two (record format)',
		TWO_QUALI_SPEED => 'two Qualification',
		// historical
		TWO_QUALI_ALL_SUM => 'two Qualification with height sum',							// lead on 2 routes with height sum counting
		TWO_QUALI_ALL_NO_COUNTBACK => 'two Qualification for all, no countback',			// lead 2012 EYC
		TWOxTWO_QUALI  => 'two * two Qualification',		// multiply the rank of 2 quali rounds on two routes each
	);
	/**
	 * Different qualification types by discipline selectable for new routes
	 *
	 * @var array
	 */
	var $quali_types_dicipline = array(
		'lead' => array(
			TWO_QUALI_ALL_SEED_STAGGER => 'two Qualification for all, flash simultaniously',	// lead on 2 routes for all on flash
			TWO_QUALI_ALL  => 'two Qualification for all, flash one after the other',			// multiply the rank
			TWO_QUALI_ALL_NO_STAGGER   => 'two Qualification for all, identical startorder (SUI)',	// lead on 2 routes for all on sight
			THREE_QUALI_ALL_NO_STAGGER => 'three Qualification for all, identical startorder (SUI)',
			ONE_QUALI      => 'one Qualification',
			TWO_QUALI_HALF => 'two Qualification, half quota',	// no countback
			TWO_QUALI_GROUPS => 'two Qualification starting groups with two staggered flash routes each',	// eg. world championships
		),
		'boulder' => array(
			ONE_QUALI      => 'one Qualification',
			TWO_QUALI_HALF => 'two Qualification, half quota',	// no countback
		),
		'speed' => array(
			TWO_QUALI_BESTOF=> 'best of two (record format)',
			ONE_QUALI       => 'one Qualification',
			TWO_QUALI_SPEED => 'two Qualification',
		),
		'combined' => array(
			THREE_QUALI_ALL_NO_STAGGER => 'qualification in three disciplines',
		),
	);
	/**
	 * Translate route_type to constant name (used in templates)
	 *
	 * @var array
	 */
	static $route_type2const = array(
		0 => 'ONE_QUALI',
		1 => 'TWO_QUALI_HALF',
		2 => 'TWO_QUALI_ALL',
		3 => 'TWO_QUALI_SPEED',
		4 => 'TWOxTWO_QUALI',
		5 => 'TWO_QUALI_ALL_SEED_STAGGER',
		6 => 'TWO_QUALI_ALL_NO_STAGGER',
		7 => 'TWO_QUALI_BESTOF',
		8 => 'TWO_QUALI_ALL_SUM',
		9 => 'TWO_QUALI_ALL_NO_COUNTBACK',
		10 => 'TWO_QUALI_GROUPS',
		11 => 'THREE_QUALI_ALL_NO_STAGGER',
	);

	/**
	 * Eliminated value for fall in UI
	 */
	const FALL = 1;
	/**
	 * Eliminated value for false start in UI
	 */
	const ELIMINATED_FALSE_START = 2;

	var $eliminated_labels = array(
		''=> ' ',
		self::FALL => 'fall',
		self::ELIMINATED_FALSE_START => 'false start',
		0 => 'wildcard',
	);
	/**
	 * values and labels for route_plus
	 *
	 * @var array
	 */
	var $plus,$plus_labels;

	/**
	 * Instance of ranking_result_bo, if instancated
	 *
	 * @var ranking_result_bo
	 */
	public static $instance;

	function __construct()
	{
		parent::__construct();

		$this->order_nums = array(
			0 => lang('Qualification'),
			1 => lang('2. Qualification'),
		);
		for($i = 2; $i <= 10; ++$i)
		{
			$this->order_nums[$i] = lang('%1. Heat',$i);
		}
		$this->order_nums[-1] = lang('General result');

		$this->plus_labels = array(
			0 =>    ' ',	// eT2 ignores empty labels
			1 =>    '+ '.lang('plus'),
			'-1' => '- '.lang('minus'),
			TOP_PLUS  => lang('Top'),
		);
		$this->plus = array(
			0 =>    ' ',	// eT2 ignores empty labels
			1 =>    '+',
			'-1' => '-',
			TOP_PLUS => lang('Top'),
		);

		// makeing the ranking_result_bo object availible for other objects
		self::$instance = $this;
	}

	/**
	 * Fix allowed plus labels depending on year of competition and nation
	 *
	 * Also takes into account if we run on ifsc-climbing.org or digitalrock.de
	 *
	 * @param int $year year of competition
	 * @param string $nation nation of comp.
	 * @param string $discipline ='lead' can be 'boulderheight' to return labels for tries
	 * @return array
	 */
	function plus_labels($year, $nation, $discipline='lead')
	{
		unset($nation);	// currently not used

		if ($discipline == 'boulderheight')
		{
			$labels = array();
			for($n = 1; $n < 10; ++$n)
			{
				$labels[100-$n] = lang('%1. try', $n);
			}
			return $labels;
		}
		$labels = $this->plus_labels;

		$minus_allowed = $year < 2012;	// nothing to do

		// digitalrock.de
		if (isset($this->license_nations['GER']) || isset($this->license_nations['SUI']))
		{
			// SUI and international/Regio Cup still has minus in 2012
			if ($year == 2012) $minus_allowed = true;
		}
		if (!$minus_allowed)
		{
			unset($labels['-1']);
		}
		//error_log(__METHOD__."($year, '$nation', '$discipline') returning ".array2string($labels).' '.function_backtrace());
		return $labels;
	}

	/**
	 * Convert athlete array to a string
	 *
	 * @param array $athlete array with values for 'vorname', 'nachname', 'nation' and optional 'start_order', 'start_number', 'result_rank', 'result'
	 * @param boolean|string $show_result =true false: startnumber, null: only name, true: rank&result, 'rank': just rank
	 * @return string nachname, vorname (nation) prefixed with rank for start-number and postfixed with result
	 */
	public static function athlete2string(array $athlete, $show_result=true)
	{
		$str = strtoupper($athlete['nachname']).' '.$athlete['vorname'].' '.$athlete['nation'];

		if ($show_result && $athlete['result_rank'])
		{
			$str = $athlete['result_rank'].'. '.$str.($show_result !== 'rank' ? ' '.str_replace('&nbsp;',' ',$athlete['result']) : '');
		}
		elseif ($show_result == false && $athlete['start_order'])
		{
			$str = $athlete['start_order'].' '.($athlete['start_number'] ? '('.$athlete['start_number'].') ' : '').$str;
		}
		return $str;
	}

	/**
	 * Generate a startlist for the given competition, category and heat (route_order)
	 *
	 * reimplented from boranking to support startlist from further heats and to store the startlist via route_result
	 *
	 * @param int/array $comp WetId or complete comp array
	 * @param int/array $cat GrpId or complete cat array
	 * @param int $route_order 0/1 for qualification, 2, 3, ... for further heats
	 * @param int $route_type =ONE_QUAL ONE_QUALI, TWO_QUALI_HALF or TWO_QUALI_ALL*
	 * @param int $discipline ='lead' 'lead', 'speed', 'boulder'
	 * @param int $max_compl =999 maximum number of climbers from the complimentary list
	 * @param int $order =null 0=random, 1=reverse ranking, 2=reverse cup, 3=random(distribution ranking), 4=random(distrib. cup), 5=ranking, 6=cup
	 * @param int $order =null null = default order from self::quali_startlist_default(), int with bitfield of
	 * 	&1  use ranking for order, unranked are random behind last ranked
	 *  &2  use cup for order, unranked are random behind last ranked
	 *  &4  reverse ranking or cup (--> unranked first)
	 *  &8  use ranking/cup for distribution only, order is random
	 * @param int $add_cat =null additional category to add registered atheletes from
	 * @param int $comb_quali =null (additional) combined qualification competition (WetId) or '' to use registration
	 * @return int|boolean number of starters, if startlist has been successful generated AND saved, false otherwise
	 * @throws Api\Exception\WrongUserinput with translated error-message
	 */
	function generateStartlist($comp, $cat, $route_order, $route_type=ONE_QUALI, $discipline='lead', $max_compl=999, $order=null, $add_cat=null, $comb_quali=null)
	{
		$keys = array(
			'WetId' => is_array($comp) ? $comp['WetId'] : $comp,
			'GrpId' => is_array($cat) ? $cat['GrpId'] : $cat,
			'route_order' => $route_order,
		);
		if (!$comp || !$cat || !is_numeric($route_order) ||
			!$this->acl_check($comp['nation'],self::ACL_RESULT,$comp) ||	// permission denied
			!$this->route->read($keys))		// route already has a result
		{
			throw new Api\Exception\WrongUserinput(lang('entry not found !!!'));
		}
		if ($this->has_results($keys))
		{
			throw new Api\Exception\WrongUserinput(lang('Error: route already has a result!!!'));
		}

		// further heat --> startlist from reverse result of previous heat
		// but for combined we have 3 qualis
		if (!($discipline == 'combined' && $route_order < 3) && $route_order >= 2 ||
			$route_order == 1 && in_array($route_type,array(TWO_QUALI_ALL,TWO_QUALI_ALL_NO_STAGGER,TWO_QUALI_ALL_SEED_STAGGER,TWO_QUALI_ALL_NO_COUNTBACK)))	// 2. Quali uses same start-order
		{
			// delete existing starters
			$this->route_result->delete($keys);
			return $this->_startlist_from_previous_heat($keys,
				// after quali reversed result, otherwise as previous heat (always previous for boulderheight!)
				($route_order >= 2 && $discipline != 'boulderheight' ? 'reverse' : 'previous'),
				$discipline);
		}
		// hack for speedrelay, which currently does NOT use registration --> randomize teams
		if ($discipline == 'speedrelay')
		{
			return $this->_randomize_startlist($keys);
		}

		// from now on only quali startlist from registration
		if (!is_array($comp)) $comp = $this->comp->read($comp);
		if (!is_array($cat)) $cat = $this->cats->read($cat);
		if (!$comp || !$cat)
		{
			throw new Api\Exception\WrongUserinput(lang('entry not found !!!'));
		}

		// combined startlist from separate qualification competition
		if ($discipline == 'combined' && $route_order < 3 && $comb_quali !== '')
		{
			return $this->_combined_startlist($comp, $cat, $route_order, $comb_quali);
		}
		// deprecated startlist stored in the result
		if ($this->result->has_startlist(array(
			'WetId' => $keys['WetId'],
			'GrpId' => $keys['Grpid'],
		)))
		{
			// delete existing starters
			$this->route_result->delete($keys);

			$starters = $this->result->read(array(
				'WetId' => $keys['WetId'],
				'GrpId' => $keys['Grpid'],
				'platz=0 AND pkt > 64'
			),'',true,'GrpId,pkt,nachname,vorname');

			return $this->_store_startlist($starters,$route_order);
		}
		// preserv an existing quali-startorder (not ranked competitiors)
		$old_startlist = array();
		if ($route_type == TWO_QUALI_HALF) $keys['route_order'] = array(0,1);
		foreach((array)$this->route_result->search($keys,'PerId,start_order,start_number,route_order','start_order ASC,route_order ASC') as $starter)
		{
			if ($starter['PerId']) $old_startlist[$starter['PerId']] = $starter;
		}
		// generate a startlist, without storing it in the result store
		$starters = parent::generate_startlist($comp,$cat,
			in_array($route_type, array(ONE_QUALI,TWO_QUALI_ALL,TWO_QUALI_ALL_NO_STAGGER,THREE_QUALI_ALL_NO_STAGGER,TWO_QUALI_SPEED,TWO_QUALI_BESTOF,TWO_QUALI_ALL_NO_COUNTBACK)) ?
				1 : ($route_type == TWO_QUALI_GROUPS ? 4 : 2), $max_compl,
			(string)$order === '' ? self::quali_startlist_default($discipline,$route_type,$comp['nation']) : $order,// ordering of quali startlist
			in_array($route_type,array(TWO_QUALI_ALL_SEED_STAGGER,TWO_QUALI_ALL_SUM,TWO_QUALI_GROUPS)),		// true = stagger, false = no stagger
			$old_startlist, $this->comp->quali_preselected($cat['GrpId'], $comp['quali_preselected']), $add_cat);

		// set 2. lane for speed record format and combined speed qualification
		if ($discipline == 'speed' && $route_type == TWO_QUALI_BESTOF && !$route_order ||
			$discipline == 'combined' && !$route_order)	// combined speed qualification
		{
			unset($starter);
			foreach($starters[1] as &$starter)
			{
				// Example for 21 starters: 1. in Lane A will be 12. in LaneB
				$starter['start_order2n'] = self::stagger($starter['start_order'], count($starters[1]));
			}
		}

		// delete existing starters
		$this->route_result->delete($keys);

		$num = $this->_store_startlist($starters[1],$route_type == TWO_QUALI_HALF ? 0 : $route_order);

		// automatically generate further startlists
		if (!in_array($route_type,array(ONE_QUALI,TWO_QUALI_ALL,TWO_QUALI_ALL_NO_COUNTBACK)) && $discipline != 'speed')	// automatically generate 2. quali
		{
			$keys['route_order'] = 0;
			$route = $this->route->read($keys,true);
			$prefix = $route_type == TWO_QUALI_GROUPS ? lang('Group').' B ' : '';
			if ($discipline != 'combined')
			{
				$this->route->save(array(
					'route_name' => $prefix.'1. '.$route['route_name'],
				));
			}
			$mod = $num = 2;
			if ($route_type == TWO_QUALI_GROUPS)
			{
				$num = 4;
			}
			elseif ($route_type == THREE_QUALI_ALL_NO_STAGGER)
			{
				$num = $mod = 3;
			}
			for($r = 1; $r < $num; ++$r)
			{
				$keys['route_order'] = $r;
				if (!$this->route->read($keys))
				{
					if ($route_type == TWO_QUALI_GROUPS && $r >= 2)	 $prefix = lang('Group').' A ';

					$route['route_order'] = $r;
					$route['route_status'] = STATUS_STARTLIST;
					if ($discipline == 'combined')
					{
						$this->init_route($route, $comp, $cat, $discipline);
					}
					else
					{
						$route['route_name'] = $prefix.(($r%$mod)+1).'. '.$route['route_name'];
					}
					$this->route->init($route);
					$this->route->save();
				}
				$this->_store_startlist(isset($starters[1+$r]) ? $starters[1+$r] :
					(isset($starters[$r]) ? $starters[$r] : $starters[1]), $r, isset($starters[1+$r]));
			}
		}
		return $num;
	}

	/**
	 * Generate combined startlist for given competition, category and heat (route_order)
	 *
	 * Qualification:
	 * a) import general result from single discipline(s) of same competition incl. rank
	 * b) import from registration
	 *
	 * @param array $comp WetId or complete comp array
	 * @param array $cat GrpId or complete cat array
	 * @param int $route_order 0=speed, 2=boulder, 3=lead qualification
	 * @param int $comb_quali =null (additional) combined qualification competition (WetId)
	 * @return int number of starters, if startlist has been successful generated AND saved
	 * @throws Api\Exception\WrongUserinput with translated error-message
	 */
	private function _combined_startlist(array $comp, array $cat, $route_order, $comb_quali=null)
	{
		if (!in_array($route_order, array(0, 1, 2)))
		{
			throw new Api\Exception\WrongUserinput("Can only generate qualification startlists from single discipline results!");
		}

		// search single discipline routes for combined result of competion with single disciplines
		try {
			$discipline2route = $this->combined_quali_discipline2route($comp, $cat, $comb_quali);
		}
		catch (Api\Exception\WrongUserinput $e) {
			// check if $comb_quali has a result for our category
			if (($quali_route = $this->route->read(array(
				'WetId' => $comb_quali,
				'GrpId' => $cat['GrpId'],
				'route_order' => -1,
			))))
			{
				$discipline2route = array(
					'speed' => $quali_route,
					'boulder2018' => $quali_route,
					'lead' => $quali_route,
				);
			}
			else
			{
				throw $e;
			}
		}

		// now we have all single diciplines identified, so we can get their general results
		$r_order = $ret = 0;
		$result = null;
		foreach(array_keys($discipline2route) as $single_discipline)
		{
			if ($r_order < $route_order)
			{
				++$r_order;
				continue;	// we are not on route_order == 0, skip generating routes before
			}
			if ($r_order != $route_order)
			{
				$route = array(
					'WetId' => $comp['WetId'],
					'GrpId' => $cat['GrpId'],
					'route_order' => $r_order,
				);
				$d = null;
				if (!$this->init_route($route, $comp, $cat, $d) ||
					$this->route->save($route))
				{
					break;	// break, if we cant create the further routes
				}
			}
			// use identical order for starters from a qualification event
			if (!isset($quali_route) || !isset($result))
			{
				if (!($result = $this->_general_result_for_combined($discipline2route, $single_discipline)))
				{
					throw new Api\Exception\WrongUserinput(lang("No result for discipline %1 found!", lang($single_discipline)));
				}
				// startlist as reverse from a different qualification competition
				if ($quali_route)
				{
					uasort($result, function($a, $b)
					{
						if ($a == $b)
						{
							return rand(0, 1) ? -1 : 1;	// randomize ex aquo
						}
						return $a > $b ? -1 : 1;
					});
				}
				// for speed quali we need the number of starters to stagger the startlist
				if ($route_order == 0 && $quali_route)
				{
					$num_starters = 0;
					foreach($result as $PerId => $rank)
					{
						// check quota, if one is given
						if ($quali_route && $quali_route['route_quota'] > 0 && $rank > $quali_route['route_quota'])
						{
							continue;
						}
						$num_starters++;
					}
				}
			}
			$num = 0;
			foreach($result as $PerId => $rank)
			{
				// check quota, if one is given
				if ($quali_route && $quali_route['route_quota'] > 0 && $rank > $quali_route['route_quota'])
				{
					continue;
				}
				$this->route_result->init(array(
					'WetId' => $comp['WetId'],
					'GrpId' => $cat['GrpId'],
					'route_order' => $r_order,
					'result_modifier' => $this->user,
					'result_modified' => $this->route_result->now,
				));
				if (!$this->route_result->save(array(
					'PerId' => $PerId,
					'start_order' => 1+$num,
					'result_rank' => $quali_route ? null : $rank,
					'start_order2n' => $num_starters ? self::stagger(1+$num, $num_starters) : null,
					// ToDo set some result so rank persists re-ranking
				)))
				{
					++$num;
				}
			}
			// return number of starters in route request (not others generated too)
			if ($r_order == $route_order) $ret = $num;
			++$r_order;
		}
		return $ret;
	}

	/**
	 * Search single disciplines general result routes for a combined category
	 *
	 * @param array $comp competion to search in
	 * @param array $cat combined category
	 * @return array discipline ("speed","boulder","lead") => general result route or null
	 */
	private function _search_combined_qualis($comp, $cat, array $discipline2route=null)
	{
		if (!$discipline2route)
		{
			$discipline2route = array(
				'speed' => null,
				'boulder2018' => null,
				'lead' => null,
			);
		}
		foreach(array_keys($discipline2route) as $single_discipline)
		{
			foreach(array_keys($cat['mgroups'] ?? []) as $gid)
			{
				if (($c = $this->cats->read($gid)) &&
					in_array($c['rkey'], $comp['gruppen']) &&
					($route = $this->route->read($keys = array(
						'WetId' => $comp['WetId'],
						'GrpId' => $c['GrpId'],
						'route_order' => -1,
					))) && $route['discipline'] == $single_discipline)
				{
					$discipline2route[$single_discipline] = $route;
					break;
				}
			}
		}
		return $discipline2route;
	}

	/**
	 * Search single disciplines general result routes for a combined category
	 *
	 * $comb_quali is only taken into account, if at least on single discipline is found in $comp!
	 *
	 * @param array $comp competion to search in
	 * @param array $cat combined category
	 * @param int $comb_quali =null (additional) combined qualification competition (WetId)
	 * @return array discipline ("speed","boulder","lead") => general result route
	 * @throws Api\Exception\WrongUserinput if any single discipline is not found
	 */
	function combined_quali_discipline2route(array $comp, array $cat, $comb_quali=null)
	{
		$discipline2route = $this->_search_combined_qualis($comp, $cat);

		$dis_not_found = array_filter($discipline2route, function($route) { return is_null($route); });
		if (count($dis_not_found) < 3 && $comb_quali &&
			($c = $this->comp->read($comb_quali)))
		{
			$discipline2route = $this->_search_combined_qualis($c, $cat, $discipline2route);
			$dis_not_found = array_filter($discipline2route, function($route) { return is_null($route); });
		}
		if ($dis_not_found)
		{
			throw new Api\Exception\WrongUserinput(lang("No route with discipline %1 found!",
				implode(', ', array_map('lang', array_keys($dis_not_found)))));
		}
		return $discipline2route;
	}

	/**
	 * Search competitions with possible combined qualification for given category
	 *
	 * Only competions before or same date as given one and 2 month back are returned.
	 *
	 * @param array $comp
	 * @param array $cat
	 * @return array WetId => $name pairs with newest comp first
	 */
	function combined_quali_comps(array $comp, array $cat)
	{
		$date = new Api\DateTime($comp['datum']);
		$date->add('-2 month');
		$filter = array(
			'WetId != '.$comp['WetId'],
			'datum BETWEEN '.$this->db->quote($date->format('Y-m-d')).' AND '.$this->db->quote($comp['datum']),
			'GrpId' => ($cat['mgroups'] ? array_keys($cat['mgroups']) : array())+array($cat['GrpId']),
			'nation '.(empty($comp['nation']) ? 'IS NULL' : '= '.$this->db->quote($comp['nation'])),
		);
		$comps = array();
		foreach($this->route->search(array(), false, 'datum DESC', 'name,datum', '', false, 'AND', false, $filter,
			'JOIN '.$this->comp->table_name.' USING (WetId)') as $row)
		{
			$comps[$row['WetId']] = $row['name'];
		}
		return $comps;
	}

	/**
	 * Get general result of a dicipline for combined (only starters with all 3 disciplines!)
	 *
	 * @param array $disciplines $discipline => $route pairs
	 * @param string $discipline discipline to return result from
	 * @return array with general result PerId => rank pairs
	 */
	private function _general_result_for_combined(array $disciplines, $discipline)
	{
		$keys = $disciplines[$discipline];
		if (!isset($keys)) throw new Api\Exception\WrongParameter("Invalid discipline '$discipline'!");

		$all_discipline_join = array();
		foreach($disciplines as $dis => $route)
		{
			if ($dis != $discipline)
			{
				// what heats to look at: for 2 distinct groups, we need to join with both
				switch($route['route_type'])
				{
					case TWO_QUALI_HALF:
						$cond = ' IN (0,1)';
						break;
					case TWO_QUALI_GROUPS:
						$cond = ' IN (0,2)';
						break;
					default:
						$cond = ' = 0';
						break;
				}
				$all_discipline_join[] = 'JOIN '.ranking_route_result::RESULT_TABLE.' require_'.$dis.' ON '.
					'require_'.$dis.'.WetId='.(int)$route['WetId'].' AND '.
					'require_'.$dis.'.GrpId = '.(int)$route['GrpId'].' AND '.
					ranking_route_result::RESULT_TABLE.'.PerId = require_'.$dis.'.PerId AND '.
					'require_'.$dis.'.route_order '.$cond.' AND '.
					'require_'.$dis.'.result_rank IS NOT NULL';
			}
		}

		$rank = $last_rank = $ex_aquo = 0;
		$result = array();
		foreach($this->route_result->search('', false, 'result_rank', '', '', false, 'AMD', false, array(
			'WetId' => $keys['WetId'],
			'GrpId' => $keys['GrpId'],
			'route_order' => -1,
			'discipline' => $keys['discipline'],
			// TWO_QUALI_SPEED is handled like ONE_QUALI (sum is stored in the result, the 2 times in the extra array)
			'route_type' => $keys['route_type'] == TWO_QUALI_SPEED ? ONE_QUALI : $keys['route_type'],
		), implode("\n", $all_discipline_join)) as $row)
		{
			if (empty($row['PerId'])) continue;	// general result eg. returns key "route_names"

			// need to re-rank, as $all_discipline_join eliminated results from athletes not competing in all disciplines
			if ($row['result_rank'] == $last_rank)
			{
				++$ex_aquo;
			}
			else
			{
				$rank += $ex_aquo+1;
				$ex_aquo = 0;
			}
			$result[$row['PerId']] = $rank;

			$last_rank = $row['result_rank'];
		}
		return $result;
	}

	/**
	 * Get number of qualifications from $route_type
	 *
	 * @param int $route_type (ONE|TWO|THREE)*
	 * @return int
	 */
	public static function num_qualis($route_type)
	{
		if ($route_type == TWO_QUALI_GROUPS)
		{
			return 4;
		}
		if($route_type == ONE_QUALI)
		{
			return 1;
		}
		if ($route_type == THREE_QUALI_ALL_NO_STAGGER)
		{
			return 3;
		}
		return 2;
	}

	/**
	 * Randomize a startlist eg. for speedrelay qualification
	 *
	 * @param array $keys values for WetId, GrpId and route_order
	 * @return int|boolean number of starters, if the startlist has been successful generated AND saved, false otherwise
	 */
	private function _randomize_startlist(array $keys)
	{
		$start_order = null;
		if (($starter = $this->route_result->search('',true,'RAND()','','','','AND',false,$keys)))
		{
			foreach($starter as $data)
			{
				$this->route_result->init($data);
				$this->route_result->update(array('start_order' => ++$start_order));
			}
		}
		return $start_order;
	}

	/**
	 * Get registered athletes for given competition and category
	 *
	 * @param array $keys array with values for WetId and GrpId
	 * @param boolean $only_nations =false only return array with nations (as key and value)
	 * @return array
	 */
	function get_registered($keys,$only_nations=false)
	{
		static $stored_keys=null,$starters=null;
		if ($keys !== $stored_keys)
		{
			$starters = $this->result->read($keys,'',true,'nation,reg_nr');
			$stored_keys = $keys;
			//_debug_array($starters);
		}
		if ($only_nations)
		{
			$nations = array();
			foreach($starters as $starter)
			{
				if (!isset($nations[$starter['nation']]))
				{
					$nations[$starter['nation']] = $starter['nation'];
				}
			}
			return $nations;
		}
		return $starters;
	}

	/**
	 * Get the default ordering of the qualification startlist
	 *
	 * order bitfields:
	 * 	&1  use ranking for order, unranked are random behind last ranked
	 *  &2  use cup for order, unranked are random behind last ranked
	 *  &4  reverse ranking or cup (--> unranked first)
	 *  &8  use ranking/cup for distribution only, order is random
	 *
	 * @param string $discipline 'lead', 'speed', 'boulder'
	 * @param int $route_type {ONE|TWO|TWOxTWO}_QUALI(_{HALF|ALL|ALL_SEED_STAGGER})?
	 * @param string $nation =null nation of competition
	 * @return int 0=random, 1=reverse ranking, 2=reverse cup, 3=random(distribution ranking), 4=random(distrib. cup), 5=ranking, 6=cup
	 */
	static function quali_startlist_default($discipline,$route_type,$nation=null)
	{
		switch($nation)
		{
			case 'SUI':
				$order = 10;	// random, distribution by Cup(!), since 2012
				break;

			default:
				$order = $discipline == 'speed' ?
					// speed: 0 = random for bestof/record format, 5 = reverse of ranking
					($route_type == TWO_QUALI_BESTOF ? 0 : 1|4) :
					// 9 = distribution by ranking, 0 = random
					(in_array($route_type,array(TWO_QUALI_HALF,TWO_QUALI_ALL_SEED_STAGGER,TWOxTWO_QUALI)) ? 1|8 : 0);
				break;
		}
		//echo "<p>".__METHOD__."($discipline,$route_type,$nation) order=$order</p>\n";
		return $order;
	}

	/**
	 * Store a startlist in route_result table
	 *
	 * @internal
	 * @param array $starters
	 * @param int $route_order if set only these starters get stored
	 * @return int num starters stored
	 */
	private function _store_startlist($starters,$route_order,$use_order=true)
	{
		if (!$starters || !is_array($starters))
		{
			throw new Api\Exception\WrongUserinput('No starters!');
		}
		$num = 0;
		foreach($starters as $starter)
		{
			if (!($start_order = $this->pkt2start($starter['pkt'],!$use_order ? 1 : 1+$route_order)))
			{
				continue;	// wrong route
			}
			$this->route_result->init(array(
				'WetId' => $starter['WetId'],
				'GrpId' => $starter['GrpId'],
				'route_order' => $route_order,
				'PerId' => $starter['PerId'],
				'start_order' => $start_order,
				'ranking' => $starter['ranking'],	// place in cup or ranking responsible for start-order
			)+(isset($starter['start_number']) ? array(
				'start_number' => $starter['start_number'],
			) : array())+(isset($starter['start_order2n']) ? array(
				'start_order2n' => $starter['start_order2n']
			) : array()));

			if ($this->route_result->save() == 0) $num++;
		}
		return $num;
	}

	/**
	 * Startorder for the ko-system, first key is the total number of starters,
	 * second key is the place with the startorder as value
	 *
	 * @var array
	 */
	private static $ko_start_order=array(
		16 => array(
			1 => 1,  16 => 2,
			8 => 3,  9  => 4,
			4 => 5,  13 => 6,
			5 => 7,  12 => 8,
			2 => 9,  15 => 10,
			7 => 11, 10 => 12,
			3 => 13, 14 => 14,
			6 => 15, 11 => 16,
		),
		8 => array(
			1 => 1, 8 => 2,
			4 => 3, 5 => 4,
			2 => 5, 7 => 6,
			3 => 7, 6 => 8,
		),
		// 2018 combined format final with 6, but with reversed result!
		//6 => array(
		//	/*1*/6 => 1, /*6*/1 => 2,
		//	/*2*/5 => 3, /*5*/2 => 4,
		//	/*3*/4 => 5, /*4*/3 => 6,
		//),
		4 => array(
			1 => 1, 4 => 2,
			2 => 3, 3 => 4,
		),
	);

	/**
	 * New 2019 combined final quota
	 */
	const COMBINED_FINAL_QUOTA = 8;

	/**
	 * Generate a startlist from the result of a previous heat
	 *
	 * @internal use generate_startlist
	 * @param array $keys values for WetId, GrpId and route_order
	 * @param string $start_order_mode ='reverse' 'reverse' result, like 'previous' heat, as the 'result'
	 * @param string $discipline
	 * @return int number of starters, if the startlist has been successful generated AND saved
	 * @throws Api\Exception\WrongUserinput with translated error-message, eg. no quota set
	 */
	private function _startlist_from_previous_heat($keys,$start_order_mode='reverse',$discipline='lead')
	{
		$ko_system = substr($discipline,0,5) == 'speed';
		//error_log(__METHOD__."(".array2string($keys).",$start_order_mode,$discipline) ko_system=$ko_system");
		if ($ko_system && $keys['route_order'] > 2 ||
			$discipline == 'combined' && in_array($keys['route_order'], array(4,5)))
		{
			return $this->_startlist_from_ko_heat($keys, $discipline);
		}
		$prev_keys = array(
			'WetId' => $keys['WetId'],
			'GrpId' => $keys['GrpId'],
			'route_order' => $keys['route_order']-1,
		);
		// combined finals dont depend on direct previous route
		if ($discipline == 'combined')
		{
			switch($keys['route_order'])
			{
				case 6:	// boulder only speed final overal
					$prev_keys['route_order'] = -6;
					break;
				case 7:	// lead final on multiplication of boulder and speed rank
					$prev_keys['route_order'] = -1;
					break;
			}
		}
		if ($prev_keys['route_order'] == 1 && !$this->route->read($prev_keys))
		{
			$prev_keys['route_order'] = 0;
		}
		if (!($prev_route = $this->route->read($prev_keys,true)))
		{
			throw new Api\Exception\WrongUserinput(lang('Previous round not found!'));
		}
		// add discipline and route_type to $prev_keys, as it is needed by ranking_route_result::search
		$prev_keys['discipline'] = $prev_route['discipline'];
		$prev_keys['route_type'] = $prev_route['route_type'];
		// check if startorder depends on result
		if ($start_order_mode != 'previous' && $prev_keys['route_order'] != -1 && !$this->has_results($prev_keys) ||
			$ko_system && !$prev_route['route_quota'])
		{
			throw new Api\Exception\WrongUserinput(lang('Previous round has no result!'));
		}
		// read quota from previous heat or for new overall quali result from there
		$quota = $this->get_quota($keys, $prev_route['route_type'], $discipline);
		if ($prev_route['route_type'] == TWO_QUALI_HALF && $keys['route_order'] == 2)
		{
			$prev_keys['route_order'] = array(0,1);		// use both quali routes
		}
		// use new overal qualification result for generating startlist of heat after qualification
		if ($prev_route['route_type'] == TWO_QUALI_GROUPS && $keys['route_order'] == 4 ||
			$prev_route['route_type'] == TWO_QUALI_HALF  && $keys['route_order'] == 2)
		{
			// read quote from overal quali result, not prev. heat == last quali heat
			$prev_keys['route_order'] = -3;
			if (($prev = $this->route->read($prev_keys))) $prev_route = $prev;
			$prev_keys['route_type'] = $prev_route['route_type'];
			$prev_keys['discipline'] = $prev_route['discipline'];
			$quota /= 2;	// as we have 2 groups, with their own rank
		}
		if ($prev_route['route_type'] == TWOxTWO_QUALI && $keys['route_order'] == 4)
		{
			$prev_keys['route_order'] = array(2,3);		// use both quali groups
		}
		if ($quota && (!self::is_two_quali_all($prev_route['route_type']) && $prev_route['route_type'] != THREE_QUALI_ALL_NO_STAGGER ||
			$keys['route_order'] > 2+(int)($prev_route['route_type'] == THREE_QUALI_ALL_NO_STAGGER)))
		{
			$prev_keys[] = 'result_rank <= '.(int)$quota;
		}
		// which column get propagated to next heat
		$cols = $this->route_result->startlist_cols();

		// we need ranking from result_detail for 2. qualification for preselected participants
		if (!$prev_route['route_order'])
		{
			$cols[] = 'result_detail AS ranking';

			$comp = $this->comp->read($keys['WetId']);
		}
		if ($quota == 1 || 				// superfinal
			$start_order_mode == 'previous' && !$ko_system || 	// 2. Quali uses same startorder
			$ko_system && $keys['route_order'] > 2)			// speed-final
		{
			$order_by = 'start_order';						// --> same starting order as previous heat!
		}
		else
		{
			if ($ko_system || $start_order_mode == 'result')		// first speed final or start_order by result (eg. boulder 1/2-f)
			{
				$order_by = 'result_rank';					// --> use result of previous heat
			}
			// quali with two groups, use overal qualification result
			elseif($prev_keys['keep_order_by'])
			{
				$order_by = $this->route_result->table_name.'.result_rank DESC';		// --> reversed result
			}
			// quali on two or 2 routes with multiplied ranking or combined speed final
			elseif(self::is_two_quali_all($prev_route['route_type']) && $keys['route_order'] == 2 ||
				$prev_route['route_type'] == THREE_QUALI_ALL_NO_STAGGER && $keys['route_order'] == 3)
			{
				$cols = array();
				$prev_keys['route_order'] = 0;
				$prev_keys[] = $this->route_result->table_name.'.result_rank IS NOT NULL';	// otherwise not started athletes qualify too
				$route_names = null;
				$join = $this->route_result->general_result_join(array(
					'WetId' => $keys['WetId'],
					'GrpId' => $keys['GrpId'],
				),$cols,$order_by,$route_names,$prev_route['route_type'],$discipline,array());
				$order_by = str_replace(array('r2.result_rank IS NULL,r2.result_rank,r1.result_rank IS NULL,',
					'r3.result_rank IS NULL,r3.result_rank,',
					',nachname ASC,vorname ASC'),'',$order_by);	// we dont want to order alphabetical, we have to add RAND()
				$order_by .= ' DESC';	// we need reverse order

				// just the col-name is ambigues
				foreach($prev_keys as $col => $val)
				{
					if (!is_int($col) && !in_array($col, array('discipline', 'route_type')))
					{
						$prev_keys[] = $this->route_result->table_name.'.'.
							$this->db->expression($this->route_result->table_name,array($col => $val));
						unset($prev_keys[$col]);
					}
				}
				foreach($cols as $key => $col)
				{
					if (strpos($col,'quali_points')===false) unset($cols[$key]);	// remove all cols but the quali_points
				}
				$cols[] = $this->route_result->table_name.'.PerId AS PerId';
				$cols[] = $this->route_result->table_name.'.start_number AS start_number';

				// combined speed final needs qualification time
				if ($discipline == 'combined' && $keys['route_order'] == 3)
				{
					$cols[] = $this->route_result->table_name.'.result_time AS result_time';
					$cols[] = $this->route_result->table_name.'.result_detail AS detail';
				}
			}
			else
			{
				$order_by = 'result_rank DESC';		// --> reversed result
			}
			try {
				if (($comp = $this->comp->read($keys['WetId'])) &&
					($ranking_sql = $this->_ranking_sql($keys['GrpId'],$comp['datum'],$this->route_result->table_name.'.PerId')))
				{
					$order_by .= ','.$ranking_sql.($start_order_mode != 'result' ? ' DESC' : '');	// --> use the (reversed) ranking
					$prev_keys['keep_order_by'] = true;	// otherwise we use order of general result
				}
			}
			catch(Exception $e) {
				// ignore exception, if no ranking defined
				unset($e);
			}
			$order_by .= ',RAND()';					// --> randomized
		}
		//error_log(__METHOD__."('','$cols','$order_by','','',false,'AND',false,".array2string($prev_keys).",'$join')");
		$starters =& $this->route_result->search('',$cols,$order_by,'','',false,'AND',false,$prev_keys,$join);
		unset($starters['route_names']);

		// combined speed & lead final uses general result --> fixed quota of 8
		if ($discipline == 'combined' && in_array($keys['route_order'], array(7, 3)))
		{
			$starters = array_slice($starters, -($quota=self::COMBINED_FINAL_QUOTA));

			// sort speed final by speed (was overall qualification)
			if ($keys['route_order'] == 3)
			{
				self::sort_by_result_times($starters);
			}
		}

		// ko-system: ex aquos on last place are NOT qualified, instead we use wildcards
		if ($ko_system && $keys['route_order'] == 2 && count($starters) > $quota)
		{
			$max_rank = $starters[count($starters)-1]['result_rank']-1;
		}
		$start_order = 1;
		foreach($starters as $n => $data)
		{
			// get ranking value of prequalified
			if (!empty($data['ranking']) && ($data['ranking'] = ranking_route_result::unserialize($data['ranking'])))
			{
				$data['false_start'] = $data['ranking']['false_start'];
				$data['ranking'] = $data['ranking']['ranking'];
			}
			// applying a quota for TWO_QUALI_ALL, taking ties into account!
			if (!($discipline == 'combined' && $keys['route_order'] == 7) &&
				isset($data['quali_points']) && count($starters)-$n > $quota &&
				$data['quali_points'] > $starters[count($starters)-$quota]['quali_points'])
			{
				//echo "<p>ignoring: n=$n, points={$data['quali_points']}, starters[".(count($starters)-$quota)."]['quali_points']=".$starters[count($starters)-$quota]['quali_points']."</p>\n";
				continue;
			}
			// first final round in ko-sytem
			if ($ko_system && $keys['route_order'] == 2 ||
				$discipline == 'combined' && $keys['route_order'] == 3)
			{
				if (!isset(self::$ko_start_order[$quota]) ||
					$discipline == 'combined' && $quota !== self::COMBINED_FINAL_QUOTA)
				{
					throw new Api\Exception\WrongUserinput(lang('Wrong quota of %1 for co-system (use 16, 8 or 4 for speed or %2 for combined)!', $quota, self::COMBINED_FINAL_QUOTA));
				}
				if ($max_rank)
				{
					if ($data['result_rank'] > $max_rank) break;
					if ($start_order <= $quota-$max_rank)
					{
						$data['result_time'] = WILDCARD_TIME;
					}
				}
				$data['start_order'] = self::$ko_start_order[$quota][$start_order++];
			}
			// 2. quali is stagger'ed of 1. quali
			elseif(in_array($prev_route['route_type'],array(TWO_QUALI_ALL,TWO_QUALI_ALL_SEED_STAGGER)) && $keys['route_order'] == 1)
			{
				$data['start_order'] = self::stagger($start_order++, count($starters));
			}
			else
			{
				$data['start_order'] = $start_order++;
			}
			$this->route_result->init($keys);
			$this->route_result->save(array(
				'PerId' => $data['PerId'],
				'start_order' => $data['start_order'],
				'start_number' => $data['start_number'],
			));
		}
		// add prequalified to quali(s) and first final round
		if ($comp && ($preselected = $this->comp->quali_preselected($keys['GrpId'], $comp['quali_preselected'])) && $keys['route_order'] <= 2)
		{
			array_pop($prev_keys);	// remove: result_rank IS NULL
			// we need ranking from result_detail for 2. qualification for preselected participants
			$cols[] = $this->route_result->table_name.'.result_detail AS ranking';
			$cols[] = $this->route_result->table_name.'.result_rank AS result_rank';
			$order_by = $this->route_result->table_name.'.start_order ASC';	// are already in cup order
			$starters =& $this->route_result->search('',$cols,$order_by,'','',false,'AND',false,$prev_keys,$join);
			//_debug_array($starters);
			foreach($starters as $n => $data)
			{
				// get ranking value of prequalified
				if (!empty($data['ranking']) && ($data['ranking'] = ranking_route_result::unserialize($data['ranking'])))
				{
					$data['ranking'] = $data['ranking']['ranking'];
				}
				if (!(isset($data['ranking']) && $data['ranking'] <= $preselected))	// not prequalified
				{
					echo "<p>not prequalified</p>";
					continue;
				}
				$data['start_order'] = $start_order++;
				$this->route_result->init($keys);
				unset($data['result_rank']);
				$this->route_result->save($data);
			}
		}
		if ($ko_system && $keys['route_order'] == 2)	// first final round in ko-sytem --> fill up with wildcards
		{
			while($start_order <= $quota)
			{
				$this->_create_wildcard_co($keys,self::$ko_start_order[$quota][$start_order++]);
			}
		}
		return $start_order-1;
	}

	/**
	 * Sort given starters by result-times (using 2nd time to break ties in best time)
	 *
	 * Doublicates some code from ranking_route_result::speed_quali_tie_breaking()!
	 *
	 * Comparing string times in json (result_time_l/r) to float times in result_time
	 * does not always work, they are not always equal --> casting everything to int
	 *
	 * @param array $starters
	 */
	private static function sort_by_result_times(array &$starters)
	{
		usort($starters, function($a, $b)	// has to return int, NOT float!
		{
			$a_time = (int)(1000*$a['result_time']);
			$b_time = (int)(1000*$b['result_time']);
			if ($a_time != $b_time)
			{
				return $a_time - $b_time;
			}
			// breaking the tie with 2nd time
			$a['detail'] = ranking_route_result::unserialize($a['detail']);
			$a_time2 = $a_time == (int)(1000*$a['detail']['result_time_l']) ?
				(int)(1000*$a['detail']['result_time_r']) : (int)(1000*$a['detail']['result_time_l']);
			$b['detail'] = ranking_route_result::unserialize($b['detail']);
			$b_time2 = $b_time == (int)(1000*$b['detail']['result_time_l']) ?
				(int)(1000*$b['detail']['result_time_r']) : (int)(1000*$b['detail']['result_time_l']);
			// $a has better 2. time than $b
			if ($a_time2 && (!$b_time2 || $a_time2 < $b_time2))
			{
				return -1;
			}
			// $b has better 2. time than $a
			if ($b_time2 && (!$a_time2 || $b_time2 < $a_time2))
			{
				return 1;
			}
			return 0;
		});
	}

	/**
	 * Generate a startlist from the result of a previous heat
	 *
	 * @param array $keys values for WetId, GrpId and route_order
	 * @param string $discipline
	 * @return int number of starters, if the startlist has been successful generated AND saved
	 * @throws Api\Exception\WrongUserinput with translated error-message
	 */
	private function _startlist_from_ko_heat($keys, $discipline)
	{
		$prev_keys = array(
			'WetId' => $keys['WetId'],
			'GrpId' => $keys['GrpId'],
			'route_order' => $keys['route_order']-1,
		);
		if (!($prev_route = $this->route->read($prev_keys)))
		{
			throw new Api\Exception\WrongUserinput(lang('Previous round not found!'));
		}
		$order_by = 'start_order';
		// false start do NOT eliminate in combined speed final, nor in regular small final
		$max_false_starts = 999;
		// combined integrates fastest loosers in 1/2-final and small final in final
		if ($discipline == 'combined')
		{
			$cols[] = 'result_rank';
			/* 2018 combined speed final
			$prev_keys[] = 'result_rank <= 4';
			// treat fastest looser like winner of 4th pairing
			if ($keys['route_order'] == 4)
			{
				// we want winners by start_order first, then loosers by result_rank
				// to be able to stop after fastest looser, independent how many winners we have
				$order_by = 'result_rank,start_order';
			}
			// first loosers (small final) then winners, all by start_order
			else
			{
				$order_by = 'result_rank=1,start_order';
			}*/
			// 2019+ combined speed 1/2-final
			if ($keys['route_order'] == 4)
			{
				$order_by = 'result_rank=1,start_order';
			}
			// 2019+ combined speed final
			else
			{
				$order_by = 'result_rank<=4,result_rank IN (1,5),start_order';
			}
		}
		elseif ($prev_route['route_quota'] == 2)	// small final
		{
			$prev_keys[] = 'result_rank > 2';
		}
		else	// 1/2|4|8 Final
		{
			if (!$prev_route['route_quota'] && --$prev_keys['route_order'] &&	// final
				!($prev_route = $this->route->read($prev_keys)))
			{
				throw new Api\Exception\WrongUserinput(lang('Previous round not found!'));
			}
			$prev_keys[] = 'result_rank = 1';
			// before small final false start eliminates athlete
			if ($prev_route['route_quota']) $max_false_starts = ranking_route_result::MAX_FALSE_STARTS;
		}
		// which column get propagated to next heat
		$cols = $this->route_result->startlist_cols();
		$join = '';//'JOIN Personen USING(PerId)'; $cols[] = 'Nachname'; $cols[] = 'Vorname';
		$cols[] = 'start_order';
		$cols[] = 'result_detail AS detail';	// AS detail to not automatically unserialize (we only want false_start)
		$starters =& $this->route_result->search('', $cols,
			$order_by, '', '', false, 'AND', false, $prev_keys, $join);
		//error_log(__METHOD__."('','$cols','$order_by','','',false,'AND',false,".array2string($prev_keys).") starters = ".array2string($starters));

		// reindex by _new_ start_order
		$starters_by_startorder = array();
		foreach($starters as &$starter)
		{
			// copy number of false_start from previous heat
			$detail = ranking_route_result::unserialize($starter['detail']);
			unset($starter['detail']);
			$starter['false_start'] = $detail['false_start'];

			$new_start_order = (int)(($starter['start_order']+1)/2);

			if ($discipline == 'combined')
			{
				/* 2018 combined speed final
				switch ($keys['route_order'])
				{
					case 4: // startorder for 1/2-final
						if ($starter['result_rank'] > 1)
						{
							// fastest looser goes to start_order=2 against winner of first pairing (start_order=1)
							$starters_by_startorder[2] =& $starter;
							unset($starter['result_rank']);
							break 2; // we only want 1 looser, not more due to a pairing were both false start or fall
						}
						elseif ($new_start_order > 1)
						{
							// make space for fastest looser
							$new_start_order = $new_start_order+1;
						}
						break;
					case 5: // startorder for final: small final (loosers), then final
						if ($starter['result_rank'] == 1)
						{
							$new_start_order += 2;
						}
						break;
				}*/
				// 2019+ combined speed final
				switch ($keys['route_order'])
				{
					case 4: // startorder for 1/2-final: loosers first, then winners
						if ($starter['result_rank'] == 1)
						{
							$new_start_order += 4;
						}
						break;
					case 5: // startorder for final
						if ($starter['result_rank'] <= 6)
						{
							$new_start_order += $starter['result_rank'] == 1 ? 4 : 2;
						}
						break;
				}
				// setting it here, so judges dont need to
				$prev_route['route_quota'] = count($starters);
			}
			$starters_by_startorder[$new_start_order] =& $starter;
			unset($starter['result_rank']);
		}
		for($start_order=1; $start_order <= $prev_route['route_quota']; ++$start_order)
		{
			$data = $starters_by_startorder[$start_order];
			if (!isset($data) || $data[$this->route_result->id_col] <= 0 || $data['false_start'] > $max_false_starts)
			{
				// no starter --> wildcard for co
				$this->_create_wildcard_co($keys,$start_order,array('result_rank' => 2));
			}
			else	// regular starter
			{
				// check if our co is a regular starter, as we otherwise have a wildcard
				$co = $starters_by_startorder[$start_order & 1 ? $start_order+1 : $start_order-1];
				if (!isset($co) || $co[$this->route_result->id_col] <= 0 || $co['false_start'] > $max_false_starts)
				{
					$data['result_time'] = WILDCARD_TIME;
					$data['result_rank'] = 1;
				}
				$data['start_order'] = $start_order;
				unset($data['false_start']);

				$this->route_result->init($keys);
				$this->route_result->save($data);
			}
		}
		return $start_order-1;
	}

	/**
	 * Create a wildcard co-starter
	 *
	 * @param array $keys
	 * @param int $start_order
	 * @param array $extra =array()
	 */
	private function _create_wildcard_co(array $keys,$start_order,array $extra=array())
	{
		$this->route_result->init($keys);
		$this->route_result->save($data=array(
			$this->route_result->id_col => -$start_order,	// has to be set and unique (per route) for each wildcard
			'start_order' => $start_order,
			'result_time' => ELIMINATED_TIME,
			'team_name' => lang('Wildcard'),
		)+$extra);
	}

	/**
	 * Check if given type is one of the TWO_QUALI_ALL* types
	 *
	 * @param int $route_type
	 * @return boolean
	 */
	static function is_two_quali_all($route_type)
	{
		return in_array($route_type, array(
			TWO_QUALI_ALL,
			TWO_QUALI_ALL_NO_STAGGER,
			TWO_QUALI_ALL_SEED_STAGGER,
			TWO_QUALI_ALL_SUM,
			TWO_QUALI_ALL_NO_COUNTBACK,
			TWO_QUALI_GROUPS,
		));
	}

	/**
	 * Get the ranking as an sql statement, to eg. order by it
	 *
	 * @param int/array $cat category
	 * @param string $stand date of the ranking as Y-m-d string
	 * @return string sql or null for no ranking
	 */
	private function _ranking_sql($cat,$stand,$PerId='PerId')
	{
		$nul = null;
	 	$ranking =& $this->ranking($cat,$stand,$nul,$nul,$nul,$nul,$nul,$nul);//,$mode == 2 ? $comp['serie'] : '');
		if (!$ranking) return null;

		$sql = 'CASE '.$PerId;
	 	foreach($ranking as $data)
	 	{
	 		$sql .= ' WHEN '.$data['PerId'].' THEN '.$data['platz'];
	 	}
	 	$sql .= ' ELSE 9999';	// unranked competitors should be behind the ranked ones
		$sql .= ' END';

	 	return $sql;
	}

	/**
	 * Updates the result of the route specified in $keys
	 *
	 * @param array $keys WetId, GrpId, route_order
	 * @param array $results PerId => data pairs
	 * @param int $route_type ONE_QUALI, TWO_QUALI_*, TWOxTWO_QUALI
	 * @param string $discipline 'lead', 'speed', 'boulder' or 'speedrelay'
	 * @param array $old_values PerId/team_id => array pairs, default null
	 * 		Values at the time of display, to check if somethings changed,
	 * 		which causes save_result to read the results now.
	 * 		If multiple people are updating, you should provide the result of the time of display,
	 * 		to not accidently overwrite results entered by someone else!
	 * @param int $quali_preselected =0 preselected participants for quali --> no countback to quali, if set!
	 * @param boolean $update_checked =false false: do NOT update checked value, true: also update checked value
	 * @param string $order_by ='start_order ASC' ordering of list for setting start-numbers
	 * @param int $problem =null for which boulder to check (1, 2, ...), or default null for all route-judges
	 * @return boolean|int number of changed results or false on error
	 */
	function save_result($keys,$results,$route_type,$discipline,$old_values=null,$quali_preselected=0,$update_checked=false,$order_by='start_order ASC',$problem=null)
	{
		//error_log(__METHOD__."(".array2string($keys).", results=".array2string($results).", route_type=$route_type, discipline='$discipline', old_values=".array2string($old_values).", quali_preselected=$quali_preselected, update_checked=$update_checked)");
		$this->error = array();

		if (!$keys || !$keys['WetId'] || !$keys['GrpId'] || !is_numeric($keys['route_order']) ||
			!($comp = $this->comp->read($keys['WetId'])) ||
			!$this->acl_check($comp['nation'],self::ACL_RESULT, $comp, false, null, false, $problem) &&
			!$this->is_judge($comp, false, $keys, $problem) &&	// check additionally for route_judges
			!($discipline == 'selfscore' && count($results) == 1 && isset($results[$this->is_selfservice()])))
		{
			return $this->error = false;	// permission denied
		}
		// only allow full judges to (un)check boulder results
		if ($update_checked && !$this->is_judge($comp)) $update_checked = false;

		// setting discipline and route_type to allow using it in route_result->save()/->data2db
		$this->route_result->discipline = $discipline;
		$this->route_result->route_type = $route_type;

		// adding a new team for relay
		$data = $results[0];
		if ($discipline == 'speedrelay' && isset($data) && !empty($data['team_nation']) && !empty($data['team_name']))
		{
			$data['team_id'] = $this->route_result->get_max($keys,'team_id')+1;
			$data['start_order'] = $this->route_result->get_max($keys,'start_order')+1;
			$data['result_modified'] = time();
			$data['result_modifier'] = $this->user;
			$this->route_result->init($keys);
			$this->route_result->save($data);
		}
		unset($results[0]);

		//echo "<p>".__METHOD__."(".array2string($keys).",,$route_type,'$discipline')</p>\n"; _debug_array($results);
		if ((is_null($old_values) || $discipline == 'boulder') && $results)
		{
			$current_values = $this->route_result->results_by_id($keys);//+array($this->route_result->id_col=>$id));
			if (is_null($old_values)) $old_values = $current_values;
		}
		$modified = 0;
		foreach($results as $id => $data)
		{
			$keys[$this->route_result->id_col] = $id;

			// if $update_checked, ignore old result, just set given result in $data
			$old = $update_checked ? array() : $old_values[$id];
			if ($old) $old['result_time'] = $old['result_time_l'];
			$current = $current_values[$id];

			//error_log(__METHOD__."() #$id: current=".array2string($current));
			//error_log(__METHOD__."() #$id: old=".array2string($old));
			//error_log(__METHOD__."() #$id: data=".array2string($data));

			// speed result: split false_start value from eliminated
			foreach(array('', '_r') as $postfix)
			{
				if ($data['eliminated'.$postfix] == ranking_result_bo::ELIMINATED_FALSE_START)
				{
					$data['eliminated'.$postfix] = '';
					$data['false_start'] = 1;	// we only store "false_start", no "false_start_r"
				}
				elseif(isset($data['eliminated'.$postfix]))
				{
					$data['false_start'.$postfix] = null;
				}
			}

			// boulder result
			for ($i=1; $i <= ranking_route_result::MAX_BOULDERS && isset($data['top'.$i]); ++$i)
			{
				// if no change in a single boulder compared to old result, use current result, not old one for storing
				if ($old && $old['top'.$i] == $data['top'.$i] &&
					$old['zone'.$i] == $data['zone'.$i] &&
					$old['try'.$i] == $data['try'.$i])
				{
					$data['zone'.$i] = $current['zone'.$i];
					$data['top'.$i] = $current['top'.$i];
					$data['try'.$i] = $current['try'.$i];
					//error_log(__METHOD__."() #$id: $i. boulder unchanged use current result top=".array2string($current['top'.$i]).", zone=".array2string($current['zone'.$i]));
					continue;
				}
				//else error_log(__METHOD__."() #$id: $i. boulder changed top=".array2string($data['top'.$i]).", zone=".array2string($data['zone'.$i]));

				if ($data['top'.$i] && (int)$data['top'.$i] < (int)$data['zone'.$i])
				{
					$this->error[$id]['zone'.$i] = lang('Can NOT be higher than top!');
				}
			}
			//error_log(__METHOD__."() #$id: data=".array2string($data));

			if (isset($data['tops']) && $discipline != 'selfscore')	// boulder result with just the sums
			{
				// todo: validation
				if ($data['tops'] && (int)$data['tops'] > (int)$data['zones'])
				{
					$this->error[$id]['zones'] = lang('Can NOT be lower than tops!');
				}
				foreach(array('top','zone') as $name)
				{
					if ($data[$name.'s'] > $data[$name.'_tries'])
					{
						$this->error[$id][$name.'s'] = lang('Can NOT be higher than tries!');
					}
				}
			}

			// do NOT allow to modify checked via update of all results
			//if (!$update_checked) unset($data['checked']);

			foreach($data as $key => $val)
			{
				// something changed?
				if ((!$old && (string)$val !== '' || $old && (string)$old[$key] != (string)$val) &&
					($key != 'result_plus' || $data['result_height'] || $val == TOP_PLUS || $old['result_plus'] == TOP_PLUS) ||
					$update_checked && $key == 'checked')
				{
					// automatic increment all start-numbers
					if (($key == 'start_number' || $key == 'start_number_1') && (strchr($val,'+') !== false || $data['increment']) ||
						$key == 'increment' && $val && $data['start_number'])
					{
						if ($key == 'start_number' && $data['increment'])
						{
							$this->set_start_number($keys, $data['increment'], $order_by, $val);
						}
						else
						{
							$this->set_start_number($keys, $val, $order_by, $data['start_number']);
						}
						++$modified;
						continue;
					}
					if (!$this->route_result->read($keys)) continue;	// athlete NOT in startlist!

					if ($this->route_result->data['checked'] && !$update_checked)
					{
						//error_log(__METHOD__."() Athlete #$id already checked --> update failed!");
						$this->error[$id]['denied'] = lang('Athlete scorecard already checked, update denied!');
						continue 2;	// do not allow to overwrite a result marked as checked
					}
					//error_log(__METHOD__."() --> saving #$id because $key='$val' changed, was '{$old[$key]}'");
					$data['result_modified'] = time();
					$data['result_modifier'] = $this->user;

					//error_log(__METHOD__."() old: route_result->data=".array2string($this->route_result->data));
					if (($err = $this->route_result->save($data)))
					{
						$this->error[$id]['error'] = lang('Error saving the result (%1)',
							$this->db->readonly ? lang('Database is readonly') : $err);
						return false;
					}
					//error_log(__METHOD__."() new: route_result->data=".array2string($this->route_result->data));
					++$modified;
					break;
				}
			}
		}
		// always trying the update, to be able to eg. incorporate changes in the prev. heat
		//if ($modified)	// update the ranking only if there are modifications
		{
			unset($keys[$this->route_result->id_col]);

			// combined needs route information available, to determine it's combined
			$route = $this->route->read($keys);

			if ($keys['route_order'] == 2 && is_null($route_type))	// check the route_type, to know if we have a countback to the quali
			{
				$route = $this->route->read($keys);
				$route_type = $route['route_type'];
			}
			$use_time = false;
			// regular lead finals (quote = 0) or combined uses time to break ties
			if ($discipline == 'lead' && ($keys['route_order'] >= 2 || $route['discipline'] == 'combined'))
			{
				$use_time = !$route['route_quota'] || $route['discipline'] == 'combined';
			}
			$selfscore_points = null;
			if ($discipline == 'selfscore' && ($route || ($route = $this->route->read($keys))))
			{
				$selfscore_points = $route['selfscore_points'];
			}
			$n = $this->route_result->update_ranking($keys,$route_type,$discipline,$quali_preselected,$use_time,$selfscore_points,$route);
			//echo '<p>--> '.($n !== false ? $n : 'error, no')." places changed</p>\n";
		}
		// delete the export_route cache
		ranking_result_bo::delete_export_route_cache($keys);

		return $modified ? $modified : $n;
	}

	/**
	 * Set start-number of a given and the following participants
	 *
	 * @param array $keys 'WetId','GrpId', 'route_order', $this->route_result->id_col (PerId/team_id)
	 * @param string $increment [start]+increment n+N or just N(=increment) and $start_number
	 * @param string $order_by ='start_order ASC' ordering of list for setting start-numbers
	 * @param int $start =null
	 */
	function set_start_number($keys, $increment, $order_by='start_order ASC', $start=null)
	{
		$id = $keys[$this->route_result->id_col];
		unset($keys[$this->route_result->id_col]);
		if (strpos($increment, '+') !== false)
		{
			list($start, $increment) = explode('+', $increment);
		}
		foreach($this->route_result->search($keys, false, $order_by) as $data)
		{
			if (!$id || $data[$this->route_result->id_col] == $id)
			{
				$to_write = array(
					'result_modified' => time(),
					'result_modifier' => $this->user,
				);
				$where = $keys;
				$where[$this->route_result->id_col] = $data[$this->route_result->id_col];
				// for quali always update every heat, after only update current and further heats
				unset($where['route_order']);
				if ($keys['route_order'] >= 2) $where[] = 'route_order >= '.(int)$keys['route_order'];

				for ($i = 0; $i <= 3; ++$i)
				{
					$col = 'start_number'.($i ? '_'.$i : '');
					if (!array_key_exists($col,$data)) continue;
					if ($data[$this->route_result->id_col] == $id && $start)
					{
						$last = $to_write[$col] = $start;
						unset($id);
					}
					else
					{
						$last = $to_write[$col] = is_numeric($increment) ? $last + $increment : $last;
					}
				}
				$this->db->update($this->route_result->table_name, $to_write, $where, __LINE__, __FILE__, $this->route_result->app);
			}
		}
	}

	/**
	 * Check if a route has a result or a startlist ($startlist_only == true)
	 *
	 * @param array $keys WetId, GrpId, route_order
	 * @param boolean $startlist_only =false check of startlist only (not result)
	 * @return boolean true if there's a at least particial result, false if thers none, null if $key is not valid
	 */
	function has_results($keys,$startlist_only=false)
	{
		if (!$keys || !$keys['WetId'] || !$keys['GrpId'] || !is_numeric($keys['route_order'])) return null;

		if (count($keys) > 3) $keys = array_intersect_key($keys,array('WetId'=>0,'GrpId'=>0,'route_order'=>0,'PerId'=>0,'team_id'=>0));

		if (!$startlist_only) $keys[] = 'result_rank IS NOT NULL';

		return (boolean) $this->route_result->get_count($keys);
	}

	/**
	 * Check if a route has a startlist
	 *
	 * @param array $keys WetId, GrpId, route_order
	 * @param boolean $startlist_only =false check of startlist only (not result)
	 * @return boolean true if there's a at least particial result, false if thers none, null if $key is not valid
	 */
	function has_startlist($keys)
	{
		return $keys['route_order'] == -1 ? false : $this->has_results($keys,true);
	}

	/**
	 * Delete a participant from a route and renumber the starting-order of the following participants
	 *
	 * @param array $keys required 'WetId', 'PerId'/'team_id', possible 'GrpId', 'route_number'
	 * @return boolean true if participant was successful deleted, false otherwise
	 */
	function delete_participant($keys)
	{
		if (!$keys['WetId'] || !$keys[$this->route_result->id_col] ||
			!($comp = $this->comp->read($keys['WetId'])) ||
			!($this->acl_check($comp['nation'],self::ACL_RESULT,$comp) ||
				// route-judges are allowed to delete participants for selfscore
				($route = $this->route->read($keys)) && $route['discipline'] == 'selfscore' &&
					$this->is_judge($comp, false, $route)) ||
			$this->has_results($keys))
		{
			return false; // permission denied
		}
		return $this->route_result->delete_participant($keys);
	}

	/**
	 * Download a route as csv file
	 *
	 * @param array $keys WetId, GrpId, route_order
	 */
	function download($keys)
	{
		if (($route = $this->route->read($keys)) &&
			($cat = $this->cats->read($keys['GrpId'])) &&
			($comp = $this->comp->read($keys['WetId'])))
		{
			$keys['route_type'] = $route['route_type'];
			if (($keys['discipline'] = $route['discipline']) === 'combined')
			{
				$keys['discipline'] = $this->combined_order2discipline($keys['route_order'], $route['route_type']);
			}
			$result = $this->has_results($keys);
			$keys['comp_nation'] = $comp['nation'];
			$athletes =& $this->route_result->search('',false,$result ? 'result_rank' : 'start_order','','',false,'AND',false,$keys);
			//_debug_array($athletes); return;

			$stand = $comp['datum'];
			$nul = $test = $ranking = null;
			try {
				$this->calc->ranking($cat,$stand,$nul,$test,$ranking,$nul,$nul,$nul);
			}
			catch(Exception $e) {
				// ignore "No ranking defined" exception eg. for combined
				_egw_log_exception($e);
			}
			Api\Header\Content::type($cat['name'].' - '.$route['route_name'].'.csv','text/comma-separated-values');
			$name2csv = array(
				'WetId'    => 'comp',
				'GrpId'    => 'cat',
				'route_order' => 'heat',
				'PerId'    => 'athlete',
				'result_rank'    => 'place',
				'category',
				'route',
				'start_order' => 'startorder',
				'nachname' => 'lastname',
				'vorname'  => 'firstname',
				'nation'   => 'nation',
				'verband'  => 'federation',
				'birthyear' => 'birthyear',
				'ranking',
				'ranking-points',
				'start_number' => 'startnumber',
				'result' => 'result',
			);
			switch($comp['nation'])
			{
				case 'SUI':
					$name2csv = array_merge(
						array_slice($name2csv, 0, 12, true),
						array('acl_fed'  => 'regionalzentrum'),
						array_slice($name2csv, 12, 99, true),
						array(
						'ort'      => 'city',
						'plz'      => 'postcode',
						'geb_date' => 'birthdate',
					));
					break;
				case 'GER':
					$name2csv = array_merge(
						array_slice($name2csv, 0, 12, true),
						array('parent_fed'  => 'LV'),
						array_slice($name2csv, 12, 99, true)
					);
					break;
			}
			switch($keys['discipline'])
			{
				case 'boulder':
				case 'boulder2018':
					for ($i = 1; $i <= $route['route_num_problems']; ++$i)
					{
						$name2csv['boulder'.$i] = 'boulder'.$i;
					}
					break;
				case 'speed':
					unset($name2csv['result']);
					$name2csv['time_sum'] = 'result';
					$name2csv['result'] = 'time-left';
					$name2csv['result_r'] = 'time-right';
					break;
				case 'lead':
					if ($keys['route_order'] == -1 && self::is_two_quali_all($keys['route_type']))
					{
						unset($name2csv['result']);
						$name2csv['quali_points'] = 'result';
					}
			}
			echo implode(';',$name2csv)."\n";
			$charset = Api\Translation::charset();
			foreach($athletes as $athlete)
			{
				if (!$athlete['PerId']) continue;	// general results contain such a (wrong) entry ...

				$values = array();
				foreach($name2csv as $name => $csv)
				{
					switch($csv)
					{
						case 'category':
							$val = $cat['name'];
							break;
						case 'ranking':
							$val = $ranking[$athlete['PerId']]['platz'];
							break;
						case 'ranking-points':
							$val = isset($ranking[$athlete['PerId']]) ? sprintf('%1.2f',$ranking[$athlete['PerId']]['pkt']) : '';
							break;
						case 'route':
							$val = $route['route_name'];
							break;
						case 'result':
							$val = $athlete['discipline'] == 'boulder' ? $athlete[$name] :
								str_replace(array('&nbsp;',' '),'',$athlete[$name]);
							break;
						default:
							$val = $athlete[$name];
					}
					if (strchr($val,';') !== false)
					{
						$val = '"'.str_replace('"','',$val).'"';
					}
					$values[$csv] = $val;
				}
				// convert by default to iso-8859-1, as this seems to be the default of excel
				$csv_charset = $GLOBALS['egw_info']['user']['preferences']['common']['csv_charset'];
				if (empty($csv_charset)) $csv_charset = 'iso-8859-1';
				echo Api\Translation::convert(implode(';',$values), $charset, $csv_charset)."\n";
			}
			exit();
		}
	}

	/**
	 * Upload a route as csv file
	 *
	 * @param array $_keys WetId, GrpId, route_order and optional 'route_type and 'discipline'
	 * @param string|FILE $file uploaded file name or handle
	 * @param boolean $add_athletes =false add not existing athletes, default bail out with an error
	 * @param boolean|int $ignore_comp_heat =false ignore WetId and route_order, default do NOT, or integer WetId to check agains
	 * @param boolean $return_data =false true return array with data and do NOT store it
	 * @return int|string|array integer number of imported results or string with error message
	 */
	function upload($_keys,$file,$add_athletes=false,$ignore_comp_heat=false,$return_data=false)
	{
		if (!$_keys || !$_keys['WetId'] || !$_keys['GrpId'] || !is_numeric($_keys['route_order'])) // permission denied
		{
			return lang('Permission denied !!!');
		}
		$route_type = $_keys['route_type'];
		$discipline = $_keys['discipline'];
		$keys = array_intersect_key($_keys, array_flip(array('WetId','GrpId','route_order')));

		if (!isset($route_type))
		{
			if (!($route = $this->route->read($keys)))
			{
				return lang('Route NOT found!').' keys='.array2string($keys);
			}
			$route_type = $route['route_type'];
		}
		if (!isset($discipline))
		{
			$comp = $this->comp->read($keys['WetId']);
			if (!$comp['dicipline']) $cat = $this->cats->read($keys['GrpId']);
			$discipline = $comp['discipline'] ? $comp['discipline'] : $cat['discipline'];
		}
		if ($discipline === 'combined')
		{
			$discipline = $keys['discipline'] = $this->combined_order2discipline($keys['route_order'], $route_type);
		}
		if (is_resource($file))
		{
			$head = fread($file, 10);
			fseek($file, 0, SEEK_SET);
		}
		else
		{
			$head = file_get_contents($file,false,null,0,10);
		}
		if (($xml = strpos($head,'<?xml') === 0 || $discipline == 'speedrelay'))	// no csv import for speedrelay
		{
			$data = $this->parse_xml($keys+array(
				'route_type' => $route_type,
				'discipline' => $discipline,
			),$file,$add_athletes);
		}
		else
		{
			$data = $this->parse_csv($keys,$file,false,$add_athletes,$ignore_comp_heat);
		}
		if (!is_array($data) || $return_data) return $data;

		$this->route_result->route_type = $route_type;
		$this->route_result->discipline = $discipline;

		if (!$xml)
		{
			$this->route_result->delete(array(
				'WetId'    => $keys['WetId'],
				'GrpId'    => $keys['GrpId'],
				'route_order' => $keys['route_order'],
			));
		}
		//_debug_array($lines);
		foreach($data as $line)
		{
			$this->route_result->init($line);
			$this->route_result->save(array(
				'result_modifier' => $this->user,
				'result_modified' => time(),
			));
		}
		if ($xml)	// Zingerle timing ranks are NOT according to rules --> do an own ranking
		{
			$this->route_result->update_ranking($keys,$route_type,$discipline);
		}
		return count($data);
	}

	/**
	 * XMLReader instace of parse_xml
	 *
	 * @var XMLReader
	 */
	private $reader;

	/**
	 * Parse xml file or Zingerle's ClimbingData.xsd schema
	 *
	 * Schema DTD is in /ranking/doc/ClimbingData.xsd, URL http://tempuri.org/ClimbingData.xsd gives 404 Not Found
	 *
	 * That schema can NOT create routes, as it only contains start-numbers, no PerId's!!!!
	 *
	 * @param array $_keys WetId, GrpId, route_order and optional 'route_type' and 'discipline'
	 * @param string|FILE $file uploaded file name or handle
	 * @return array|string array with imported data (array of array for route_result->save) or string with error message
	 */
	protected function parse_xml($_keys,$file)
	{
		$route_type = $_keys['route_type'];
		$discipline = $_keys['discipline'];
		$keys = array_intersect_key($_keys, array_flip(array('WetId','GrpId','route_order')));

		if (!isset($route_type))
		{
			if (!($route = $this->route->read($keys)))
			{
				return lang('Route NOT found!').' keys='.array2string($keys);
			}
			$route_type = $route['route_type'];
		}
		if (!isset($discipline))
		{
			$comp = $this->comp->read($keys['WetId']);
			if (!$comp['dicipline']) $cat = $this->cats->read($keys['GrpId']);
			$discipline = $comp['discipline'] ? $comp['discipline'] : $cat['discipline'];
		}
		if ($this->route_result->isRelay != ($discipline == 'speedrelay'))
		{
			$this->route_result->__construct($this->config['ranking_db_charset'],$this->db,null,
				$discipline == 'speedrelay');
		}
		$this->route_result->route_type = $route_type;
		$this->route_result->discipline = $discipline;

		if (!($participants = $this->route_result->search(array(),false,'','','',false,'AND',false,$keys+array(
			'discipline'  => $discipline,
			'route_type'  => $route_type,
		))))
		{
			return lang('No participants yet!').' '.lang('ClimbingData xml files can only set results, not create NEW participants!');
		}
		$this->reader = new XMLReader();
		if (is_resource($file))
		{
			$this->reader->XML(stream_get_contents($file));
		}
		elseif (!$this->reader->open($file))
		{
			return lang('Could not open %1!',$file);
		}
		if (!$this->reader->setSchema(EGW_SERVER_ROOT.'/ranking/doc/ClimbingData.xsd'))
		{
			return lang('XML file uses unknown schema (format)!');
		}
		$results = $settings = array();
		while ($this->reader->read())
		{
			if ($this->reader->nodeType == XMLReader::ELEMENT)
			{
				switch ($this->reader->name)
				{
					case 'Settings':
						$settings = $this->read_node();
						break;

					case 'Results':
						$results[] = $this->read_node();
						break;
				}
			}
		}
		//_debug_array($settings);
		switch($settings['Mode'])
		{
			case 'IndividualQualification':
			case 'IndividualFinals':
				if ($discipline != 'speed')
				{
					return lang('Wrong Mode="%1" for discipline "%2"!',$settings['Mode'],$discipline);
				}
				if (($keys['route_order'] < 2) != ($settings['Mode'] == 'IndividualQualification'))
				{
					return lang('Wrong Mode="%1" for this heat (qualification - final mismatch)!',$settings['Mode'],$discipline);

				}
				break;
			case 'TeamQualification':
			case 'TeamFinals':
				if ($discipline != 'speedrelay')
				{
					return lang('Wrong Mode="%1" for discipline "%2"!',$settings['Mode'],$discipline);
				}
				/* as Arco 2011 used only on run per team, qualification used "TeamFinals" mode too
				if (($keys['route_order'] < 2) != ($settings['Mode'] == 'TeamQualification'))
				{
					return lang('Wrong Mode="%1" for this heat (qualification - final mismatch)!',$settings['Mode'],$discipline);
				}*/
				break;
			default:
				return lang('Unknown Mode="%1"!',$settings['Mode']);
		}
		//_debug_array($results);
		//_debug_array($participants);
		$data = array();
		foreach($results as $result)
		{
			if (!$result['StartNumber'])
			{
				continue;	// ignore records without startnumber (not sure how they get into the xml file, but they are!)
			}
			$participant = null;
			foreach($participants as $p)
			{
				if ($discipline == 'speedrelay' && $result['StartNumber'] == $p['team_id'])
				{
					$participant = $keys+array_intersect_key($p, array_flip(array(
						'team_id', 'start_order', 'team_nation', 'team_name',
						'PerId_1','PerId_2','PerId_3','start_number_1','start_number_2','start_number_3',
					)));
					break;
				}
				elseif ($discipline != 'speedrelay' && $result['StartNumber'] == ($p['start_number'] ? $p['start_number'] : $p['start_order']))
				{
					$participant = $keys+array_intersect_key($p, array_flip(array('PerId', 'start_order', 'start_number', 'start_order2n')));
					break;
				}
			}
			if (!$participant)
			{
				echo lang('No participant with startnumber "%1"!',$result['StartNumber']).' '.array2string($result);
				continue;
			}
			switch($settings['Mode'])
			{
				case 'IndividualQualification':
					$participant['result_time'] = self::parse_time($result['Run1'],$participant['eliminated']);
					$participant['result_time_r'] = self::parse_time($result['Run2'],$participant['eliminated_r']);
					break;
				case 'IndividualFinals':
					$participant['result_time'] = self::parse_time($result['BestRun'],$participant['eliminated']);
					break;
				case 'TeamQualification':
					$participant['result_time'] = $result['ResultValue'] / 1000.0;
					$start = isset($result['BestRun']) && $result['BestRun'] == $result['TeamTotalRun1'] ||
						!isset($result['TeamTotalRun2']) ? 1 : 5;
					for ($i = 1; $i <= 3; ++$i, ++$start)
					{
						$participant['result_time_'.$i] = self::parse_time($result['Run'.$start]);
					}
					break;
				case 'TeamFinals':
					$participant['result_time'] = $result['ResultValue'] / 1000.0;
					for ($start = 1; $start <= 3; ++$start)
					{
						$participant['result_time_'.$start] = self::parse_time($result['Run'.$start],$participant['eliminated']);
						if ($participant['eliminated']) break;
					}
					break;
			}

			//error_log($p['nachname'].': '.array2string($participant));
			$data[] = $participant;
		}
		if (!is_resource($file)) $this->reader->close();

		return $data;
	}

	/**
	 * Parse time from xml file m:ss.ddd
	 *
	 * @param string $str
	 * @param string &$eliminated=null on return '' or 1 if climber took a fall, no time
	 * @return double|string
	 */
	static function parse_time($str,&$eliminated=null)
	{
		$eliminated = '';
		$matches = null;
		if (!isset($str) || (string)$str == '')
		{
			// empty / not set
		}
		elseif (preg_match('/^([0-9]*:)?([0-9.]+)$/', $str, $matches))
		{
			$time = 60.0 * $matches[1] + $matches[2];
		}
		else
		{
			$eliminated = '1';
			$time = '';
		}
		//echo __METHOD__.'('.array2string($str).') eliminated='.array2string($eliminated).' returning '.array2string($time)."\n";
		return $time;
	}

	/**
	 * Return (flat) array of all child nodes
	 *
	 * @return array
	 */
	private function read_node()
	{
		$nodeName = $this->reader->name;

		$data = array();
		while($this->reader->read() && !($this->reader->nodeType == XMLReader::END_ELEMENT && $this->reader->name == $nodeName))
		{
			if ($this->reader->nodeType == XMLReader::ELEMENT)
			{
				$data[$this->reader->name] = trim($this->reader->readString());
			}
		}
		return $data;
	}

	/**
	 * Import the general result of a competition into the ranking
	 *
	 * @param array $keys WetId, GrpId, discipline, route_type, route_order=-1
	 * @param string|int $filter_nation =null only import athletes of the given nation or integer fed_id
	 * @param string $import_cat =null only import athletes of the given category
	 * @return string message
	 */
	function import_ranking($keys, $filter_nation=null, $import_cat=null)
	{
		if (!$keys['WetId'] || !$keys['GrpId'] || !is_numeric($keys['route_order']))
		{
			return false;
		}
		if ($import_cat)
		{
			list($import_cat_id, $import_comp) = explode(':', $import_cat);
			$import_cat = $this->cats->read($import_cat_id);
			if ($import_comp && ($import_comp = $this->comp->read($import_comp)) && ($import_comp['nation'] || $import_comp['fed_id']))
			{
				$filter_nation = $import_comp['fed_id'] ? $import_comp['fed_id'] : $import_comp['nation'];
			}
		}
		// check if comp belongs to a state-federation, but cup is national or an other federation
		// --> cup result should use its federation to skip non-members
		if (($comp = $this->comp->read($keys['WetId'])) && $comp['fed_id'] && $comp['serie'] &&
			($cup = $this->cup->read($comp['serie'])) && $cup['fed_id'] != $comp['fed_id'])
		{
			$cup_filter = $this->federation->query_list('fed_id', 'fed_id', array('fed_parent' => $cup['fed_id']));
		}
		// for numeric fed_id check if it is a region, in which case we need to get the state-federations which are parents of the sections
		if (is_numeric($filter_nation))
		{
			$feds = $this->federation->query_list('fed_id', 'fed_id', array('fed_parent' => $filter_nation));
		}
		$skipped = $last_rank = $ex_aquo = 0;
		$rank = 1;
		$cup_last_rank = $cup_ex_aquo = 0;
		$cup_rank = 1;
		foreach($this->route_result->search('',false,'result_rank','','',false,'AMD',false,array(
			'WetId' => $keys['WetId'],
			'GrpId' => $keys['GrpId'],
			'route_order' => $keys['route_order'],
			'discipline' => $keys['discipline'],
			// TWO_QUALI_SPEED is handled like ONE_QUALI (sum is stored in the result, the 2 times in the extra array)
			'route_type' => $keys['route_type'] == TWO_QUALI_SPEED ? ONE_QUALI : $keys['route_type'],
		)) as $row)
		{
			//error_log('row='.array2string($row));
			if ($row['result_rank'])
			{
				if ($import_cat && !$this->cats->in_agegroup($row['geb_date'], $import_cat))
				{
					$skipped++;
					continue;
				}
				// if requested filter by nation or federation
				$org_rank = $row['result_rank'];
				if ($filter_nation && (!is_numeric($filter_nation) && $row['nation'] != $filter_nation ||
					is_numeric($filter_nation) && $row['fed_id'] != $filter_nation && $row['fed_parent'] != $filter_nation) &&
						(empty($feds) || !in_array($row['fed_parent'], $feds)))
				{
					$skipped++;
					// if we have a cup_filter, only really skip, if it does not match
					if (empty($cup_filter) || !in_array($row['fed_parent'], $cup_filter))
					{
						continue;
					}
					// otherwise just set place to 0, but record
					$row['result_rank'] = 0;
				}
				// re-rank cup result
				if ($cup_last_rank === (int)$org_rank)
				{
					++$cup_ex_aquo;
				}
				else
				{
					$cup_ex_aquo = 0;
				}
				$cup_last_rank = (int)$org_rank;
				$row['cup_place'] = $cup_rank++ - $cup_ex_aquo;
				$result[$row['PerId']] = $row;
				//error_log(__METHOD__."() $row[cup_place]. $row[nachname] $row[vorname] ($row[PerId]) result_rank=$row[result_rank]");

				// no regular, but cup result
				if (!$row['result_rank']) continue;

				// re-rank regular result
				if ($last_rank === (int)$row['result_rank'])
				{
					++$ex_aquo;
				}
				else
				{
					$ex_aquo = 0;
				}
				$last_rank = (int)$row['result_rank'];

				//echo "<p>$row[nachname], $row[vorname] $row[geb_date]: result_rank=$row[result_rank], rank=$rank, ex_aquo=$ex_aquo --> ".($rank - $ex_aquo)."</p>\n";
				$row['result_rank'] = $rank++ - $ex_aquo;
				$result[$row['PerId']] = $row;
			}
		}
		if ($import_cat)
		{
			$keys['GrpId'] = $import_cat['GrpId'];
		}
		if ($import_comp)
		{
			$keys['WetId'] = $import_comp['WetId'];
		}
		if ($skipped)
		{
			if ($import_cat) $reason = $import_cat['name'];

			if ($filter_nation)
			{
				$reason .= ($reason ? ' '.lang('or').' ' : '');
				if (!is_numeric($filter_nation))
				{
					$reason .= $filter_nation;
				}
				elseif (($federation = $this->federation->read($filter_nation)))
				{
					$reason .= $federation['verband'];
				}
			}
		}
		return parent::import_ranking($keys, $result).($skipped ? "\n".lang('(%1 athletes not from %2 skipped)', $skipped, $reason) : '');
	}

	/**
	 * Get the default quota for a given disciplin, route_order and optional quali_type or participants number
	 *
	 * @param string $discipline 'speed', 'lead' or 'boulder'
	 * @param int $route_order
	 * @param int $quali_type =null TWO_QUALI_ALL, TWO_QUALI_HALF, ONE_QUALI
	 * @param int $num_participants =null
	 * @return int|NULL
	 */
	static function default_quota($discipline,$route_order,$quali_type=null,$num_participants=null)
	{
		$quota = null;

		switch($discipline)
		{
			case 'speed':
				if (!is_numeric($num_participants)) break;
				for($n = 16; $n > 1; $n /= 2)
				{
					if ($num_participants > $n || !$route_order && $num_participants >= $n)
					{
						$quota = $n;
						break;
					}
				}
				break;

			case 'lead':
				switch($route_order)
				{
					case 0: $quota = $quali_type == TWO_QUALI_HALF ? 13 : 26; break;	// quali
					case 1: $quota = 13; break;		// 2. quali
					case -3: $quota = 26; break;
					case 2: $quota = 8;  break;		// 1/2-final
				}
				break;

			case 'boulder':
				switch($route_order)
				{
					case 0: $quota = $quali_type == TWO_QUALI_HALF ? 10 : 20; break;	// quali
					case 1: $quota = 10; break;		// 2. quali
					case -3: $quota = 20; break;
					case 2: $quota = 6;  break;		// 1/2-final
				}
				break;

			case 'combined':
				switch($route_order)
				{
					case -3: $quota = 6; break;	// 6 from qualification to 1/4-final speed
					case 3:
					case 4: $quota = 4; break;	// 4 to 1/2-final and final speed (includes small final)
					case 0: case 1: case 2:
						$quota = 999; break;	// no quota (0 would trigger final rules!)
				}
		}
		//error_log(__METHOD__."($discipline,$route_order,$quali_type,$num_participants)=$quota");
		return $quota;
	}

	/**
	 * Get the quota for a given route from the previous heat
	 *
	 * @param array $keys
	 * @param int $route_type =null default use $keys['route_type'], one of (ONE|TWO)_QUALI_*
	 * @param string $discipline =null discipline to use, only matters so far for combined
	 * @return int|null quote or null for first heat, exception if none is set in previous heat
	 * @throws Api\Exception\WrongUserinput with error message
	 */
	function get_quota(array $keys, $route_type=null, $discipline=null)
	{
		if (!isset($route_type)) $route_type = $keys['route_type'];

		// return null for quali / first heat / !$route_order
		if (!($route_order = $keys['route_order']))
		{
			return null;
		}

		if (in_array($route_type, array(ONE_QUALI, TWO_QUALI_BESTOF, TWO_QUALI_SPEED)) && $route_order == 2)
		{
			$keys['route_order'] = 0;
		}
		// use new overall qualification result
		elseif ($route_order == 4 && $route_type == TWO_QUALI_GROUPS  ||
			$route_order == 2 && ($route_type != TWO_QUALI_GROUPS && self::is_two_quali_all($route_type) || $route_type == TWO_QUALI_HALF) ||
			$route_order == 3 && $route_type == THREE_QUALI_ALL_NO_STAGGER)
		{
			$keys['route_order'] = -3;
		}
		elseif($discipline == 'combined' && $route_order == 6)
		{
			$keys['route_order'] = -6;
		}
		else
		{
			$keys['route_order'] = $route_order-1;
		}
		if (!($prev = $this->route->read($keys)))
		{
			throw new Api\Exception\WrongUserinput(lang('No previous heat (%1) found!', $keys['route_order']));
		}
		//error_log(__METHOD__."(".array2string($keys).", '$route_type') prev=".array2string($prev));
		if (!(int)$prev['route_quota'] &&
			!($discipline == 'combined' || $route_order == 3))	// combined only uses quote for speed final (3)
		{
			throw new Api\Exception\WrongUserinput(lang('No quota set in the previous heat!!!'));
		}
		return (int)$prev['route_quota'];
	}

	/**
	 * Initialise a route for a given competition, category and route_order and check the (read) permissions
	 *
	 * For existing routes we only check the (read) permissions and read comp and cat.
	 *
	 * @param array& $content on call at least keys WetId, GrpId, route_order, on return initialised route
	 * @param array& $comp on call competition array or null, on return competition array
	 * @param array& $cat on call category array or null, on return category array
	 * @param string& $discipline on return discipline of route: 'lead', 'speed' or 'boulder'
	 * @return boolean|string true on success, false if permission denied or string with message (eg. 'No quota set in previous heat!')
	 */
	function init_route(array &$content,&$comp,&$cat,&$discipline)
	{
		if (!is_array($comp) && !($comp = $this->comp->read($content['WetId'])) ||
			!is_array($cat) && !($cat = $this->cats->read($content['GrpId'])) ||
			!in_array($cat['rkey'],$comp['gruppen']))
		{
			return false;	// permission denied
		}
		$discipline = !empty($content['discipline']) ? $content['discipline'] :
			($comp['discipline'] ? $comp['discipline'] :
			($cat['mgroups'] ? 'combined' : $cat['discipline']));

		// switch route_result class to relay mode, if necessary
		if ($this->route_result->isRelay != ($discipline == 'speedrelay'))
		{
			$this->route_result->__construct($this->config['ranking_db_charset'],$this->db,null,
				$discipline == 'speedrelay');
		}
		if (count($content) > 3 &&
			// switching to combined and not having store the route yet
			!($content['discipline'] === 'combined' && !$this->route->read($content,true)))
		{
			return true;	// no new route
		}
		// use new boulder mode by default
		if ($discipline === 'boulder') $discipline = 'boulder2018';

		$keys = array(
			'WetId' => $comp['WetId'],
			'GrpId' => $cat['GrpId'],
			'route_order' => $route_order=$content['route_order'],
		);
		if ((int)$comp['WetId'] && (int)$cat['GrpId'] && (!is_numeric($route_order) ||
			!($route = $this->route->read($content,true))))
		{
			// try reading the previous heat, to set some stuff from it
			if (($keys['route_order'] = $this->route->get_max_order($comp['WetId'],$cat['GrpId'])) >= 0 &&
				($previous = $this->route->read($keys,true)))
			{
				++$keys['route_order'];
				if ($keys['route_order'] == 1 && in_array($previous['route_type'],array(ONE_QUALI,TWO_QUALI_SPEED,TWO_QUALI_BESTOF)))
				{
					$keys['route_order'] = 2;
				}
				foreach(array('route_type','dsp_id','frm_id','dsp_id2','frm_id2','route_time_host','route_time_port') as $name)
				{
					$keys[$name] = $previous[$name];
				}
				if (!empty($previous['discipline'])) $keys['discipline'] = $previous['discipline'];
			}
			else
			{
				$keys['route_order'] = '0';
				switch($discipline)
				{
					case 'speed':
						$keys['route_type'] = TWO_QUALI_BESTOF;
						break;
					case 'lead':
						switch($comp['nation'])
						{
							case 'SUI':
								$keys['route_type'] = TWO_QUALI_ALL_NO_STAGGER;
								break;
							default:
								$keys['route_type'] = TWO_QUALI_ALL_SEED_STAGGER;
								break;
						}
						break;
					case 'boulder':
					default:
						$keys['route_type'] = ONE_QUALI;
				}
			}
			$keys['route_name'] = $keys['route_order'] >= 2 && !($discipline == 'combined' && $keys['route_order'] < 3) ? lang('Final') :
				($keys['route_order'] == 1 && $discipline != 'combined' ? '2. ' : '').lang('Qualification');

			if ($discipline == 'combined')
			{
				static $route_order_prefix = array(3 => '1/4-', 4 => '1/2-');
				$dummy = null;
				$keys['route_name'] = $route_order_prefix[$keys['route_order']].$keys['route_name'].' '.
					lang(self::combined_order2discipline($keys['route_order'], $dummy, true));
			}
			//error_log(__METHOD__."() discipline=$discipline, route_order=$keys[route_order]: $keys[route_name]");

			// check if previous heat has a quota set (combined has 3 quali)
			try {
				if ($previous && !($discipline == 'combined' && $route_order < 3))
				{
					$this->get_quota($keys, $content['route_type']);
				}
			}
			catch (Api\Exception\WrongUserinput $e) {
				$msg = $e->getMessage();
			}
			if (substr($discipline,0,5) != 'speed')
			{
				if ($comp['nation'] == 'SUI')
				{
					$keys['route_quota'] = 999;	// no default quota for SUI
				}
				else
				{
					$keys['route_quota'] = self::default_quota($discipline,$keys['route_order'],null,null);
				}
			}
			elseif ($previous && $previous['route_quota'])
			{
				$keys['route_quota'] = $previous['route_quota'] / 2;
				if ($keys['route_quota'] > 1)
				{
					$keys['route_name'] = '1/'.$keys['route_quota'].' - '.lang('Final');
				}
				elseif($keys['route_quota'] == 1)
				{
					$keys['route_quota'] = '';
					$keys['route_name'] = lang('Small final');
				}
			}
			elseif(isset($e) && $keys['route_name'] == lang('Final'))
			{
				unset($msg);	// suppress no quota set message for speed final
			}
			if ($previous && $previous['route_judge'])
			{
				$keys['route_judge'] = $previous['route_judge'];
			}
			else	// set judges from the competition
			{
				if ($comp['judges'])
				{
					$keys['route_judge'] = array();
					foreach($comp['judges'] as $uid)
					{
						$keys['route_judge'][] = Api\Accounts::username($uid);
					}
					$keys['route_judge'] = implode(', ',$keys['route_judge']);
				}
			}
			$content = array_merge($content, $this->route->init($keys));
			$content['new_route'] = true;
			$content['route_status'] = STATUS_STARTLIST;

			// default to 5 boulders
			$content['route_num_problems'] = 5;
		}
		else
		{
			$content = array_merge($content, $route);
		}

		if (empty($content['discipline']))
		{
			$content['discipline'] = $discipline;
		}
		else
		{
			$discipline = $content['discipline'];
		}
		// selfscore finals are allways discipline boulder
		if ($content['route_order'] >= 2 && $discipline == 'selfscore')
		{
			$discipline = $content['discipline'] = 'boulder';
		}
		return $msg ? $msg : true;
	}

	/**
	 * Get discipline of a combined heat
	 *
	 * @param string $route_order
	 * @param int& $route_type on return route/quali-type
	 * @param boolean $return_boulder =false true: return "boulder" instead of "boulder2018" eg. for Api\Translation
	 * @return string
	 */
	static public function combined_order2discipline($route_order, &$route_type=null, $return_boulder=false)
	{
		switch($route_order)
		{
			case 1:
			case 6:
				$route_type = ONE_QUALI;
				return $return_boulder ? 'boulder' : 'boulder2018';
			case 2:
			case 7:
				$route_type = ONE_QUALI;
				return 'lead';
			case 0:
			default:
				$route_type = TWO_QUALI_BESTOF;
				return 'speed';
		}
	}

	/**
	 * Singleton to get a ranking_result_bo instance
	 *
	 * @return ranking_result_bo
	 */
	static public function getInstance()
	{
		if (!is_object($GLOBALS['ranking_result_bo']))
		{
			$GLOBALS['ranking_result_bo'] = new ranking_result_bo;
		}
		return $GLOBALS['ranking_result_bo'];
	}

	/**
	 * Delete export cache for given route and additionaly the general result
	 *
	 * @param int|array $comp WetId or array with values for WetId, GrpId and route_order
	 * @param int $cat =null GrpId
	 * @param int $route_order =null
	 * @param boolean $previous_heats =false also invalidate previous heats, eg. if new heats got created to include them in route_names
	 */
	public static function delete_export_route_cache($comp, $cat=null, $route_order=null, $previous_heats=false)
	{
		Export::delete_route_cache($comp, $cat, $route_order, $previous_heats);
	}

	/**
	 * Fix ordering of result (so it is identical in xml/json and UI)
	 *
	 * @param array $query
	 * @param boolean $isRelay =false
	 */
	function process_sort(array &$query,$isRelay=false)
	{
		$alpha_sort = $isRelay ? ',team_nation '.$query['sort'].',team_name' :
			',nachname '.$query['sort'].',vorname';
		// in speed(relay) sort by time first and then alphabetic
		if (substr($query['discipline'],0,5) == 'speed')
		{
			$alpha_sort = ',result_time '.$query['sort'].$alpha_sort;
		}
		switch (($order = $query['order']))
		{
			case 'result_rank':
				if ($query['route'] < 0)      // in general result we sort unranked at the end and then as the rest by name
				{
					$query['order'] = 'result_rank IS NULL '.$query['sort'];
				}
				else    // in route-results we want unranked sorted by start_order for easier result-entering
				{
					$query['order'] = 'CASE WHEN result_rank IS NULL THEN start_order ELSE 0 END '.$query['sort'];
				}
				$query['order'] .= ',result_rank '.$query['sort'].$alpha_sort;
				break;
			case 'result_height':
				$query['order'] = 'CASE WHEN result_height IS NULL THEN -start_order ELSE 0 END '.$query['sort'].
					',result_height '.$query['sort'].',result_plus '.$query['sort'].$alpha_sort;
				break;
			case 'result_top,result_zone':
				$query['order'] = 'result_top IS NULL,result_top '.$query['sort'].',result_zone IS NULL,result_zone';
				break;
			case 'nation':
				$query['order'] = 'Federations.nation '.$query['sort'].$alpha_sort;
				break;
		}
	}
}