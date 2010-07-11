<?php
/**
 * eGroupWare digital ROCK Rankings - result business object/logic
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2007-10 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$
 */

require_once(EGW_INCLUDE_ROOT.'/ranking/inc/class.boranking.inc.php');
require_once(EGW_INCLUDE_ROOT.'/ranking/inc/class.route.inc.php');
require_once(EGW_INCLUDE_ROOT.'/ranking/inc/class.route_result.inc.php');

class boresult extends boranking
{
	/**
	 * values and labels for route_order
	 *
	 * @var array
	 */
	var $order_nums;
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
	 * Different types of qualification
	 *
	 * @var array
	 */
	var $quali_types = array(
		ONE_QUALI      => 'one Qualification',
		TWO_QUALI_HALF => 'two Qualification, half quota',	// no countback
		TWO_QUALI_ALL  => 'two Qualification for all, flash one after the other',			// multiply the rank
		TWO_QUALI_ALL_SEED_STAGGER => 'two Qualification for all, flash simultaniously',	// lead on 2 routes for all on flash
		TWO_QUALI_ALL_NO_STAGGER   => 'two Qualification for all, on sight',				// lead on 2 routes for all on sight
		TWOxTWO_QUALI  => 'two * two Qualification',		// multiply the rank of 2 quali rounds on two routes each
	);
	var $quali_types_speed = array(
		ONE_QUALI       => 'one Qualification',
		TWO_QUALI_SPEED => 'two Qualification',
		TWO_QUALI_BESTOF=> 'best of two (record format)',
	);
	var $eliminated_labels = array(
		''=> '',
		1 => 'fall',
		0 => 'wildcard',
	);
	/**
	 * values and labels for route_plus
	 *
	 * @var array
	 */
	var $plus,$plus_labels;
	/**
	 * Logfile for the bridge to the rock programms running via async service
	 * Set to null to switch it of.
	 *
	 * @var string
	 */
	var $rock_bridge_log = '/tmp/rock_bridge.log';

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
			0 =>    '',
			1 =>    '+ '.lang('plus'),
			'-1' => '- '.lang('minus'),
			TOP_PLUS  => lang('Top'),
		);
		$this->plus = array(
			0 =>    '',
			1 =>    '+',
			'-1' => '-',
			TOP_PLUS => lang('Top'),
		);
	}

	/**
	 * php4 constructor
	 *
	 * @deprecated use __construct()
	 * @return boresult
	 */
	function boresult()
	{
		self::__construct();
	}

	/**
	 * Generate a startlist for the given competition, category and heat (route_order)
	 *
	 * reimplented from boranking to support startlist from further heats and to store the startlist via route_result
	 *
	 * @param int/array $comp WetId or complete comp array
	 * @param int/array $cat GrpId or complete cat array
	 * @param int $route_order 0/1 for qualification, 2, 3, ... for further heats
	 * @param int $route_type=ONE_QUAL ONE_QUALI, TWO_QUALI_HALF or TWO_QUALI_ALL*
	 * @param int $discipline='lead' 'lead', 'speed', 'boulder'
	 * @param int $max_compl=999 maximum number of climbers from the complimentary list
	 * @param int $order=null 0=random, 1=reverse ranking, 2=reverse cup, 3=random(distribution ranking), 4=random(distrib. cup), 5=ranking, 6=cup
	 * @param int $order=null null = default order from self::quali_startlist_default(), int with bitfield of
	 * 	&1  use ranking for order, unranked are random behind last ranked
	 *  &2  use cup for order, unranked are random behind last ranked
	 *  &4  reverse ranking or cup (--> unranked first)
	 *  &8  use ranking/cup for distribution only, order is random
	 * @return int/boolean number of starters, if startlist has been successful generated AND saved, false otherwise
	 */
	function generate_startlist($comp,$cat,$route_order,$route_type=ONE_QUALI,$discipline='lead',$max_compl=999,$order=null)
	{
		$keys = array(
			'WetId' => is_array($comp) ? $comp['WetId'] : $comp,
			'GrpId' => is_array($cat) ? $cat['GrpId'] : $cat,
			'route_order' => $route_order,
		);
		if (!$comp || !$cat || !is_numeric($route_order) ||
			!$this->acl_check($comp['nation'],EGW_ACL_RESULT,$comp) ||	// permission denied
			!$this->route->read($keys) ||	// route does not exist
			$this->has_results($keys))		// route already has a result
		{
			//echo "failed to generate startlist"; _debug_array($keys);
			return false;
		}
		if ($route_order >= 2 || 	// further heat --> startlist from reverse result of previous heat
			$route_order == 1 && in_array($route_type,array(TWO_QUALI_ALL,TWO_QUALI_ALL_NO_STAGGER,TWO_QUALI_ALL_SEED_STAGGER)))	// 2. Quali uses same start-order
		{
			// delete existing starters
			$this->route_result->delete($keys);
			return $this->_startlist_from_previous_heat($keys,
				($route_order >= 2 ? 'reverse' : 'previous'),	// after quali reversed result, otherwise as previous heat
				$discipline);
		}
		// hack for speedrelay, which currently does NOT use registration --> randomize teams
		if ($discipline == 'speedrelay')
		{
			return $this->_randomize_speedrelay($keys);
		}
		// from now on only quali startlist from registration
		if (!is_array($comp)) $comp = $this->comp->read($comp);
		if (!is_array($cat)) $cat = $this->cats->read($cat);
		if (!$comp || !$cat) return false;

		// depricated startlist stored in the result
		if ($this->result->has_startlist(array(
			'WetId' => $keys['WetId'],
			'GrpId' => $keys['Grpid'],
		)))
		{
			// delete existing starters
			$this->route_result->delete($keys);

			$starters =& $this->result->read(array(
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
		$starters =& parent::generate_startlist($comp,$cat,
			in_array($route_type,array(ONE_QUALI,TWO_QUALI_ALL,TWO_QUALI_ALL_NO_STAGGER,TWO_QUALI_SPEED,TWO_QUALI_BESTOF)) ? 1 : 2,$max_compl,	// 1 = one route, 2 = two routes
			(string)$order === '' ? self::quali_startlist_default($discipline,$route_type,$comp['nation']) : $order,// ordering of quali startlist
			$route_type == TWO_QUALI_ALL_SEED_STAGGER,															// true = stagger, false = no stagger
			$old_startlist);

		// delete existing starters
		$this->route_result->delete($keys);

		$num = $this->_store_startlist($starters[1],$route_type == TWO_QUALI_HALF ? 0 : $route_order);

		if (!in_array($route_type,array(ONE_QUALI,TWO_QUALI_ALL)) && $discipline != 'speed')	// automatically generate 2. quali
		{
			$keys['route_order'] = 1;
			if (!$this->route->read($keys))
			{
				$keys['route_order'] = 0;
				$route = $this->route->read($keys,true);
				$this->route->save(array(
					'route_name'   => '2. '.$route['route_name'],
					'route_order'  => 1,
					'route_status' => STATUS_STARTLIST,
				));
			}
			$this->_store_startlist(isset($starters[2]) ? $starters[2] : $starters[1],1,isset($starters[2]));
		}
		return $num;
	}
	
	/**
	 * Randomize a startlist for speedrelay qualification
	 *
	 * @param array $keys values for WetId, GrpId and route_order
	 * @return int|boolean number of starters, if the startlist has been successful generated AND saved, false otherwise
	 */
	function _randomize_speedrelay(array $keys)
	{
		$start_order = null;
		if (($starter = $this->route_result->search('',true,'RAND()')))
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
	 * @param boolean $only_nations=false only return array with nations (as key and value)
	 * @return array
	 */
	function get_registered($keys,$only_nations=false)
	{
		static $stored_keys,$starters;
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
	 * @param string $nation=null nation of competition
	 * @return int 0=random, 1=reverse ranking, 2=reverse cup, 3=random(distribution ranking), 4=random(distrib. cup), 5=ranking, 6=cup
	 */
	static function quali_startlist_default($discipline,$route_type,$nation=null)
	{
		switch($nation)
		{
			case 'SUI':
				$order = 2|4;	// reverse cup
				break;

			default:
				$order = $discipline == 'speed' ? 1|4 :		// 5 = reverse of ranking for speed
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
	function _store_startlist($starters,$route_order,$use_order=true)
	{
		if (!$starters || !is_array($starters))
		{
			return false;
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
			)+(isset($starter['start_number']) ? array(
				'start_number' => $starter['start_number'],
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
	var $ko_start_order=array(
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
		4 => array(
			1 => 1, 4 => 2,
			2 => 3, 3 => 4,
		),
	);

	/**
	 * Generate a startlist from the result of a previous heat
	 *
	 * @internal use generate_startlist
	 * @param array $keys values for WetId, GrpId and route_order
	 * @param string $start_order='reverse' 'reverse' result, like 'previous' heat, as the 'result'
	 * @param string $discipline
	 * @return int/boolean number of starters, if the startlist has been successful generated AND saved, false otherwise
	 */
	function _startlist_from_previous_heat($keys,$start_order='reverse',$discipline='lead')
	{
		$ko_system = substr($discipline,0,5) == 'speed';
		//echo "<p>".__METHOD__."(".array2string($keys).",$start_order,$discipline) ko_system=$ko_system</p>\n";
		if ($ko_system && $keys['route_order'] > 2)
		{
			return $this->_startlist_from_ko_heat($keys,$prev_route);
		}
		$prev_keys = array(
			'WetId' => $keys['WetId'],
			'GrpId' => $keys['GrpId'],
			'route_order' => $keys['route_order']-1,
		);
		if ($prev_keys['route_order'] == 1 && !$this->route->read($prev_keys))
		{
			$prev_keys['route_order'] = 0;
		}
		if (!($prev_route = $this->route->read($prev_keys,true)) ||
			$start_order != 'previous' && !$this->has_results($prev_keys) ||	// startorder does NOT depend on result
			$ko_system && !$prev_route['route_quota'])
		{
			//echo "failed to generate startlist from"; _debug_array($prev_keys); _debug_array($prev_route);
			return false;	// prev. route not found or no result
		}
		if ($prev_route['route_type'] == TWO_QUALI_HALF && $keys['route_order'] == 2)
		{
			$prev_keys['route_order'] = array(0,1);		// use both quali routes
		}
		if ($prev_route['route_type'] == TWOxTWO_QUALI && $keys['route_order'] == 4)
		{
			$prev_keys['route_order'] = array(2,3);		// use both quali groups
		}
		if ($prev_route['route_quota'] &&
			(!self::is_two_quali_all($prev_route['route_type']) || $keys['route_order'] > 2))
		{
			$prev_keys[] = 'result_rank <= '.(int)$prev_route['route_quota'];
		}
		// which column get propagated to next heat
		$cols = $this->route_result->startlist_cols();

		if ($prev_route['route_quota'] == 1 || 				// superfinal
			$start_order == 'previous' && !$ko_system || 	// 2. Quali uses same startorder
			$ko_system && $keys['route_order'] > 2)			// speed-final
		{
			$order_by = 'start_order';						// --> same starting order as previous heat!
		}
		else
		{
			if ($ko_system || $start_order == 'result')		// first speed final or start_order by result (eg. boulder 1/2-f)
			{
				$order_by = 'result_rank';					// --> use result of previous heat
			}
			// quali on two routes with multiplied ranking
			elseif(self::is_two_quali_all($prev_route['route_type']) && $keys['route_order'] == 2)
			{
				$cols = array();
				if (self::is_two_quali_all($prev_route['route_type'])) $prev_keys['route_order'] = 0;
				$prev_keys[] = 'result_rank IS NOT NULL';	// otherwise not started athletes qualify too
				$join = $this->route_result->_general_result_join(array(
					'WetId' => $keys['WetId'],
					'GrpId' => $keys['GrpId'],
				),$cols,$order_by,$route_names,$prev_route['route_type'],$discipline,array());
				$order_by = str_replace(array('r2.result_rank IS NULL,r2.result_rank,r1.result_rank IS NULL,',
					',nachname ASC,vorname ASC'),'',$order_by);	// we dont want to order alphabetical, we have to add RAND()
				$order_by .= ' DESC';	// we need reverse order

				// just the col-name is ambigues
				foreach($prev_keys as $col => $val)
				{
					$prev_keys[] = $this->route_result->table_name.'.'.
						$this->db->expression($this->route_result->table_name,array($col => $val));
					unset($prev_keys[$col]);
				}
				foreach($cols as $key => $col)
				{
					if (strpos($col,'quali_points')===false) unset($cols[$key]);	// remove all cols but the quali_points
				}
				$cols[] = $this->route_result->table_name.'.PerId AS PerId';
				$cols[] = $this->route_result->table_name.'.start_number AS start_number';
			}
			else
			{
				$order_by = 'result_rank DESC';		// --> reversed result
			}
			if (($comp = $this->comp->read($keys['WetId'])) &&
				($ranking_sql = $this->_ranking_sql($keys['GrpId'],$comp['datum'],$this->route_result->table_name.'.PerId')))
			{
				$order_by .= ','.$ranking_sql.($start_order != 'result' ? ' DESC' : '');	// --> use the (reversed) ranking
			}
			$order_by .= ',RAND()';					// --> randomized
		}
		//echo "<p>route_result::search('','$cols','$order_by','','',false,'AND',false,".array2string($prev_keys).",'$join');</p>\n";
		$starters =& $this->route_result->search('',$cols,$order_by,'','',false,'AND',false,$prev_keys,$join);
		//_debug_array($starters);

		// ko-system: ex aquos on last place are NOT qualified, instead we use wildcards
		if ($ko_system && $keys['route_order'] == 2 && count($starters) > $prev_route['route_quota'])
		{
			$max_rank = $starters[count($starters)-1]['result_rank']-1;
		}
		$start_order = 1;
		$half_starters = count($starters)/2;
		foreach($starters as $n => $data)
		{
			// applying a quota for TWO_QUALI_ALL, taking ties into account!
			if (isset($data['quali_points']) && count($starters)-$n > $prev_route['route_quota'] &&
				$data['quali_points'] > $starters[count($starters)-$prev_route['route_quota']]['quali_points'])
			{
				//echo "<p>ignoring: n=$n, points={$data['quali_points']}, starters[".(count($starters)-$prev_route['route_quota'])."]['quali_points']=".$starters[count($starters)-$prev_route['route_quota']]['quali_points']."</p>\n";
				continue;
			}
			if ($ko_system && $keys['route_order'] == 2)	// first final round in ko-sytem
			{
				if (!isset($this->ko_start_order[$prev_route['route_quota']])) return false;
				if ($max_rank)
				{
					if ($data['result_rank'] > $max_rank) break;
					if ($start_order <= $prev_route['route_quota']-$max_rank)
					{
						$data['result_time'] = WILDCARD_TIME;
					}
				}
				$data['start_order'] = $this->ko_start_order[$prev_route['route_quota']][$start_order++];
			}
			// 2. quali is stagger'ed of 1. quali (50-100,1-49)
			elseif(in_array($prev_route['route_type'],array(TWO_QUALI_ALL,TWO_QUALI_ALL_SEED_STAGGER)) && $keys['route_order'] == 1)
			{
				if ($start_order <= floor($half_starters))
				{
					$data['start_order'] = $start_order+ceil($half_starters);
				}
				else
				{
					$data['start_order'] = $start_order-floor($half_starters);
				}
				++$start_order;
			}
			else
			{
				$data['start_order'] = $start_order++;
			}
			$this->route_result->init($keys);
			unset($data['result_rank']);
			$this->route_result->save($data);
		}
		if ($max_rank)	// fill up with wildcards
		{
			while($start_order <= $prev_route['route_quota'])
			{
				$this->_create_wildcard_co($keys,$this->ko_start_order[$prev_route['route_quota']][$start_order++]);
			}
		}
		return $start_order-1;
	}

	/**
	 * Generate a startlist from the result of a previous heat
	 *
	 * @param array $keys values for WetId, GrpId and route_order
	 * @param string $start_order='reverse' 'reverse' result, like 'previous' heat, as the 'result'
	 * @param boolean $ko_system=false use ko-system
	 * @param string $discipline
	 * @return int/boolean number of starters, if the startlist has been successful generated AND saved, false otherwise
	 */
	function _startlist_from_ko_heat($keys)
	{
		//echo "<p>".__METHOD__."(".print_r($keys,true).")</p>\n";
		$prev_keys = array(
			'WetId' => $keys['WetId'],
			'GrpId' => $keys['GrpId'],
			'route_order' => $keys['route_order']-1,
		);
		if (!($prev_route = $this->route->read($prev_keys)))
		{
			return false;
		}
		if ($prev_route['route_quota'] == 2)	// small final
		{
			$prev_keys[] = 'result_rank > 2';
		}
		else	// 1/2|4|8 Final
		{
			$prev_keys[] = 'result_rank = 1';

			if (!$prev_route['route_quota'] && --$prev_keys['route_order'] &&	// final
				!($prev_route = $this->route->read($prev_keys)))
			{
				return false;
			}
		}
		// which column get propagated to next heat
		$cols = $this->route_result->startlist_cols().',start_order';
		$starters =& $this->route_result->search('',$cols,
			$order_by='start_order','','',false,'AND',false,$prev_keys);
		//echo "<p>route_result::search('','$cols','$order_by','','',false,'AND',false,".array2string($prev_keys).",'$join');</p>\n"; _debug_array($starters);

		// reindex by _new_ start_order
		foreach($starters as &$starter)
		{
			$start_order = (int)(($starter['start_order']+1)/2);
			$starters_by_startorder[$start_order] =& $starter;
		}
		for($start_order=1; $start_order <= $prev_route['route_quota']; ++$start_order)
		{
			$data = $starters_by_startorder[$start_order];
			if (!isset($data) || $data[$this->route_result->id_col] <= 0)	// no starter --> wildcard for co
			{
				$this->_create_wildcard_co($keys,$start_order,array('result_rank' => 2));
			}
			else	// regular starter
			{
				// check if our co is a regular starter, as we otherwise have a wildcard
				$co = $starters_by_startorder[$start_order & 1 ? $start_order+1 : $start_order-1];
				if (!isset($co) || $co[$this->route_result->id_col] <= 0)
				{
					$data['result_time'] = WILDCARD_TIME;
					$data['result_rank'] = 1;
				}
				$data['start_order'] = $start_order;

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
	 * @param array $extra=array()
	 */
	function _create_wildcard_co(array $keys,$start_order,array $extra=array())
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
		return in_array($route_type,array(TWO_QUALI_ALL,TWO_QUALI_ALL_NO_STAGGER,TWO_QUALI_ALL_SEED_STAGGER));
	}

	/**
	 * Get the ranking as an sql statement, to eg. order by it
	 *
	 * @param int/array $cat category
	 * @param string $stand date of the ranking as Y-m-d string
	 * @return string sql or null for no ranking
	 */
	function _ranking_sql($cat,$stand,$PerId='PerId')
	{
	 	$ranking =& $this->ranking($cat,$stand,$nul,$nul,$nul,$nul,$nul,$nul,$mode == 2 ? $comp['serie'] : '');
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
	 * @param array $old_values values at the time of display, to check if somethings changed
	 * 		default is null, which causes save_result to read the results now.
	 * 		If multiple people are updating, you should provide the result of the time of display,
	 * 		to not accidently overwrite results entered by someone else!
	 * @return boolean/int number of changed results or false on error
	 */
	function save_result($keys,$results,$route_type,$discipline,$old_values=null)
	{
		$this->error = null;

		if (!$keys || !$keys['WetId'] || !$keys['GrpId'] || !is_numeric($keys['route_order']) ||
			!($comp = $this->comp->read($keys['WetId'])) ||
			!$this->acl_check($comp['nation'],EGW_ACL_RESULT,$comp)) // permission denied
		{
			return $this->error = false;
		}
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
		if (is_null($old_values) && $results)
		{
			$keys[$this->route_result->id_col] = array_keys($results);
			$old_values = $this->route_result->search($keys,'*');
		}
		$modified = 0;
		foreach($results as $id => $data)
		{
			$keys[$this->route_result->id_col] = $id;

			foreach($old_values as $old) if ($old[$this->route_result->id_col] == $id) break;
			if ($old[$this->route_result->id_col] != $id) unset($old);

			// to also check the result_details
			if ($data['result_details']) $data += $data['result_details'];
			if ($old && $old['result_details']) $old += $old['result_details'];

			if (isset($data['top1']))	// boulder result
			{
				for ($i=1; $i <= 6 && isset($data['top'.$i]); ++$i)
				{
					if ($data['top'.$i] && (int)$data['top'.$i] < (int)$data['zone'.$i])
					{
						$this->error[$id]['zone'.$i] = lang('Can NOT be higher than top!');
					}
				}
			}
			if (isset($data['tops']))	// boulder result with just the sums
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

			foreach($data as $key => $val)
			{
				// something changed?
				if ($key != 'result_details' && (!$old && (string)$val !== '' || (string)$old[$key] != (string)$val) &&
					($key != 'result_plus' || $data['result_height'] || $val == TOP_PLUS || $old['result_plus'] == TOP_PLUS))
				{
					if (($key == 'start_number' || $key == 'start_number_1') && strchr($val,'+') !== false)
					{
						$this->set_start_number($keys,$val);
						++$modified;
						continue;
					}
					//echo "<p>--> saving $PerId because $key='$val' changed, was '{$old[$key]}'</p>\n";
					$data['result_modified'] = time();
					$data['result_modifier'] = $this->user;

					$this->route_result->init($old ? $old : $keys);
					$this->route_result->save($data);
					++$modified;
					break;
				}
			}
		}
		// always trying the update, to be able to eg. incorporate changes in the prev. heat
		//if ($modified)	// update the ranking only if there are modifications
		{
			unset($keys[$this->route_result->id_col]);

			if ($keys['route_order'] == 2 && is_null($route_type))	// check the route_type, to know if we have a countback to the quali
			{
				$route = $this->route->read($keys);
				$route_type = $route['route_type'];
			}
			$n = $this->route_result->update_ranking($keys,$route_type,$discipline);
			//echo '<p>--> '.($n !== false ? $n : 'error, no')." places changed</p>\n";
		}
		return $modified ? $modified : $n;
	}

	/**
	 * Set start-number of a given and the following participants
	 *
	 * @param array $keys 'WetId','GrpId', 'route_order', $this->route_result->id_col (PerId/team_id)
	 * @param string $number [start]+increment
	 */
	function set_start_number($keys,$number)
	{
		$id = $keys[$this->route_result->id_col];
		unset($keys[$this->route_result->id_col]);
		list($start,$increment) = explode('+',$number);
		foreach($this->route_result->search($keys,false,'start_order') as $data)
		{
			if (!$id || $data[$this->route_result->id_col] == $id)
			{
				for ($i = 0; $i <= 3; ++$i)
				{
					$col = 'start_number'.($i ? '_'.$i : '');
					if (!array_key_exists($col,$data)) continue;
					if ($data[$this->route_result->id_col] == $id && $start)
					{
						$last = $data[$col] = $start;
						unset($id);
					}
					else
					{
						$last = $data[$col] = is_numeric($increment) ? $last + $increment : $last;
					}
				}
				$this->route_result->save($data);
			}
		}
	}

	/**
	 * Check if a route has a result or a startlist ($startlist_only == true)
	 *
	 * @param array $keys WetId, GrpId, route_order
	 * @param boolean $startlist_only=false check of startlist only (not result)
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
	 * @param boolean $startlist_only=false check of startlist only (not result)
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
			!$this->acl_check($comp['nation'],EGW_ACL_RESULT,$comp) ||
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
			$keys['discipline'] = $comp['discipline'] ? $comp['discipline'] : $cat['discipline'];
			$result = $this->has_results($keys);
			$athletes =& $this->route_result->search('',false,$result ? 'result_rank' : 'start_order','','',false,'AND',false,$keys);
			//_debug_array($athletes); return;

			$stand = $comp['datum'];
 			$this->ranking($cat,$stand,$nul,$test,$ranking,$nul,$nul,$nul);

			$browser =& CreateObject('phpgwapi.browser');
			$browser->content_header($cat['name'].' - '.$route['route_name'].'.csv','text/comma-separated-values');
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
			if ($comp['nation'] == 'SUI')
			{
				$name2csv += array(
					'ort'      => 'city',
					'plz'      => 'postcode',
					'geb_date' => 'birthdate',
				);
			}
			switch($keys['discipline'])
			{
				case 'boulder':
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
			}
			echo implode(';',$name2csv)."\n";
			$charset = $GLOBALS['egw']->translation->charset();
			foreach($athletes as $athlete)
			{
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
							$val = isset($ranking[$athlete['PerId']]) ? sprintf('%1.2lf',$ranking[$athlete['PerId']]['pkt']) : '';
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
				echo $GLOBALS['egw']->translation->convert(implode(';',$values),$charset,
					$_GET['charset'] ? $_GET['charset'] : 'iso-8859-1')."\n";
			}
			$GLOBALS['egw']->common->egw_exit();
		}
	}

	/**
	 * Upload a route as csv file
	 *
	 * @param array $keys WetId, GrpId, route_order
	 * @param string|FILE $file uploaded file name or handle
	 * @param string/int error message or number of lines imported
	 * @param boolean $add_athletes=false add not existing athletes, default bail out with an error
	 * @return int/string integer number of imported results or string with error message
	 */
	function upload($keys,$file,$add_athletes=false)
	{
		if (!$keys || !$keys['WetId'] || !$keys['GrpId'] || !is_numeric($keys['route_order'])) // permission denied
		{
			return lang('Permission denied !!!');
		}
		$csv = $this->parse_csv($keys,$file,false,$add_athletes);

		if (!is_array($csv)) return $csv;

		$this->route_result->delete(array(
			'WetId'    => $keys['WetId'],
			'GrpId'    => $keys['GrpId'],
			'route_order' => $keys['route_order'],
		));
		//_debug_array($lines);
		foreach($csv as $line)
		{
			$this->route_result->init($line);
			$this->route_result->save(array(
				'result_modifier' => $this->user,
				'result_modified' => time(),
			));
		}
		return count($csv);
	}

	/**
	 * Import the general result of a competition into the ranking
	 *
	 * @param array $keys WetId, GrpId, discipline, route_type, route_order=-1
	 * @param string $filter_nation=null only import athletes of the given nation
	 * @return string message
	 */
	function import_ranking($keys,$filter_nation=null)
	{
		if (!$keys['WetId'] || !$keys['GrpId'] || !is_numeric($keys['route_order']))
		{
			return false;
		}
		$skiped = 0;
		foreach($this->route_result->search('',false,'result_rank','','',false,'AMD',false,array(
			'WetId' => $keys['WetId'],
			'GrpId' => $keys['GrpId'],
			'route_order' => $keys['route_order'],
			'discipline' => $keys['discipline'],
			// TWO_QUALI_SPEED is handled like ONE_QUALI (sum is stored in the result, the 2 times in the extra array)
			'route_type' => $keys['route_type'] == TWO_QUALI_SPEED ? ONE_QUALI : $keys['route_type'],
		)) as $row)
		{
			if ($row['result_rank'])
			{
				if ($filter_nation && $row['nation'] != $filter_nation)
				{
					$skiped++;
					continue;
				}
				$row['result_rank'] -= $skiped;
				$result[$row['PerId']] = $row;
			}
		}
		return parent::import_ranking($keys,$result).($skiped ? "\n".lang('(%1 athletes not from %2 skipped)',$skiped,$filter_nation) : '');
	}

	/**
	 * Gets called via the async service if an automatic import from the rock programms is configured
	 *
	 * @param array $hook_data
	 */
	function import_from_rock($hook_data)
	{
		//echo "import_from_rock"; _debug_array($this->config);
		$this->_bridge_log("**** bridge run started");
		foreach($this->config as $name => $value) if (substr($name,0,11)=='rock_import') $this->_bridge_log("config[$name]='$value'");

		if (!$this->config['rock_import_comp'] || !($comp = $this->comp->read($this->config['rock_import_comp'])))
		{
			$this->_bridge_log("no competition configured or competition ({$this->config['rock_import_comp']}) not found!");
			return;
		}

		for ($n = 1; $n <= 2; ++$n)
		{
			if (!($rroute = $this->config['rock_import'.$n]))
			{
				$this->_bridge_log("$n: No route configured!");
				continue;
			}

			list(,$rcomp) = explode('.',$rroute);
			$year = 2000 + (int) $rcomp;
			$file = $this->config['rock_import_path'].'/'.$year.'/'.$rcomp.'/'.$rroute.'.php';

			unset($route); unset($tn);
			if (!file_exists($file))
			{
				$this->_bridge_log("$n: File '$file' not found!");
				continue;
			}
			include($file);

			if (!is_array($route) || !$route['teilnehmer'])
			{
				$this->_bridge_log("$n: File '$file' does not include a rock route or participants!");
				continue;
			}

			if (!$this->config['rock_import_cat'.$n] || !($cat = $this->cats->read($this->config['rock_import_cat'.$n])) ||
				!in_array($cat['rkey'],$comp['gruppen']) || $route['GrpId'] != $cat['GrpId'])
			{
				//_debug_array($cat);
				//_debug_array($comp);
				//$route['teilnehmer'] = 'not shown'; _debug_array($route);
				$this->_bridge_log("$n: Category not configured, not belonging to the competition or not found!\n");
				continue;
			}
			$discipline = $comp['discipline'] ? $comp['discipline'] : $cat['discipline'];
			$route_imported = $this->_rock2route($route);

			if (!$this->route->read($keys = array(
				'WetId' => $comp['WetId'],
				'GrpId' => $cat['GrpId'],
				'route_order' => (int)$this->config['rock_import_route'.$n],
			)))
			{
				// create a new route
				$this->route->init($keys);
				$this->route->save($route_imported);
				//_debug_array($this->route->data);
			}
			elseif($this->route->data['route_status'] == STATUS_RESULT_OFFICIAL)
			{
				$error .= "$n: Result already offical!\n";
				continue;	// we dont change the result if it's offical!
			}
			else
			{
				// incorporate changes, not sure if we should do that automatic ???
				unset($route_imported['route_type']);
				unset($route_imported['route_name']);
				$this->route->save($route_imported);
				//_debug_array($this->route->data);
			}
			$this->route_result->delete($keys);
			foreach($route['teilnehmer'] as $PerId => $data)
			{
				$keys['PerId'] = $PerId;
				$this->route_result->init($keys);
				$this->route_result->save($this->_rock2result($data,$discipline));
			}
			$this->_bridge_log("$n: number of participants imported: ".count($route['teilnehmer']));
		}
		$this->_bridge_log("**** bridge run finished");
	}

	/**
	 * translate a rock participant into a result-service result
	 *
	 * @param array $rdata rock participant data
	 * @param string $discipline lead, speed or boulder
	 * @return array
	 */
	function _rock2result($rdata,$discipline)
	{
		list($PerId,$GrpId) = explode('+',$rdata['key']);

		$data = array(
			'PerId' => $PerId,
			'GrpId' => $GrpId,
			'start_order' => $rdata['startfolgenr'],
			'start_number' => $rdata['startfolgenr'] != $rdata['startnummer'] ? $rdata['startnummer'] : null,
			'result_rank' =>  $rdata['platz'] ? (int)$rdata['platz'] : null,
			'result_height' => $discipline == 'lead' ? (strstr($rdata['hoehe'],'Top') ? TOP_HEIGHT : 100*substr($rdata['hoehe'],0,-1)) : null,
			'result_plus' => $discipline == 'lead' ? (strstr($rdata['hoehe'],'Top') ? TOP_PLUS : (int)(substr($rdata['hoehe'],-1).'1')) : null,
			'result_time' => $discipline == 'speed' && $rdata['time'][0] ? 100*$rdata['time'][0] : null,
		);

		if ($discipline == 'boulder')
		{
			if ($rdata['boulder'][0])
			{
				$data['top1'] = '';		// otherwise the result is not recogniced as a boulder result!
				for($i = 1; $i <= 6; ++$i)
				{
					$result = trim($rdata['boulder'][$i]);
					if ($result{0} == 't')
					{
						list(,$data['top'.$i],,$data['zone'.$i]) = preg_split('/[tzb ]/',$result);
					}
					else
					{
						$data['zone'.$i] = (string)(int)substr($result,1);
					}
				}
			}
			else
			{
				unset($data['result_rank']);	// otherwise not climbed athlets are already ranked
			}
		}
		return $data;
	}

	/**
	 * translate a rock route into a result-service route
	 *
	 * @param array $route rock route-data
	 * @return array
	 */
	function _rock2route($route)
	{
		list($iso_open,$iso_close) = preg_split('/ ?- ?/',$route['isolation'],2);

		return array(
			'route_name' => $route['bezeichnung'],
			'route_judge' => $route['jury'][0],
			'route_status' => $route['frei_str'] ? STATUS_RESULT_OFFICIAL : STATUS_STARTLIST,
			'route_type' => ONE_QUALI,	// ToDo: set it from the erge_modus
			'route_iso_open' => $iso_open,
			'route_iso_open' => $iso_close,
			'route_start' => $route['start'],
			'route_result' => $route['frei_str'],
			'route_quota' => $route['quote'],
			'route_num_problems' => substr($route['erge_modus'],0,9) == 'BoulderZ:' ?
				count(explode('+',substr($route['erge_modus'],9))) : null,
		);
	}

	function _bridge_log($str)
	{
		if ($this->rock_bridge_log && ($f = @fopen($this->rock_bridge_log,'a+')))
		{
			fwrite ($f,date('Y-m-d H:i:s: ').$str."\n");
			fclose($f);
		}
	}

	/**
	 * Get the default quota for a given disciplin, route_order and optional quali_type or participants number
	 *
	 * @param string $discipline 'speed', 'lead' or 'boulder'
	 * @param int $route_order
	 * @param int $quali_type=null TWO_QUALI_ALL, TWO_QUALI_HALF, ONE_QUALI
	 * @param int $num_participants=null
	 * @return int
	 */
	static function default_quota($discipline,$route_order,$quali_type=null,$num_participants=null)
	{
		$quota = null;

		switch($discipline)
		{
			case 'speed':
				if (!is_numeric($num_participants)) break;
				for($n = 16; $n > 1; $n /= 2) if ($num_participants > $n || !$route_order && $num_participants >= $n)
				{
					$quota = $n;
					break;
				}
				break;

			case 'lead':
				switch($route_order)
				{
					case 0: $quota = $quali_type == TWO_QUALI_HALF ? 13 : 26; break;	// quali
					case 1: $quota = 13; break;		// 2. quali
					case 2: $quota = 8;  break;		// 1/2-final
				}
				break;

			case 'boulder':
				switch($route_order)
				{
					case 0: $quota = $quali_type == TWO_QUALI_HALF ? 10 : 20; break;	// quali
					case 1: $quota = 10; break;		// 2. quali
					case 2: $quota = 6;  break;		// 1/2-final
				}
				break;
		}
		//echo "<p>boresult::default_quota($discipline,$route_order,$quali_type,$num_participants)=$quota</p>\n";
		return $quota;
	}

	/**
	 * Initialise a route for a given competition, category and route_order and check the (read) permissions
	 *
	 * For existing routes we only check the (read) permissions and read comp and cat.
	 *
	 * @param array &$content on call at least keys WetId, GrpId, route_order, on return initialised route
	 * @param array &$comp on call competition array or null, on return competition array
	 * @param array &$cat  on call category array or null, on return category array
	 * @param string &$discipline on return discipline of route: 'lead', 'speed' or 'boulder'
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
		$discipline = $comp['discipline'] ? $comp['discipline'] : $cat['discipline'];
		// switch route_result class to relay mode, if necessary
		if ($this->route_result->isRelay != ($discipline == 'speedrelay'))
		{
			$this->route_result->__construct($this->config['ranking_db_charset'],$this->db,null,
				$discipline == 'speedrelay');
		}
		if (count($content) > 3)
		{
			return true;	// no new route
		}
		$keys = array(
			'WetId' => $comp['WetId'],
			'GrpId' => $cat['GrpId'],
			'route_order' => $route_order=$content['route_order'],
		);
		if ((int)$comp['WetId'] && (int)$cat['GrpId'] && (!is_numeric($route_order) ||
			!($content = $this->route->read($content,true))))
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
			}
			else
			{
				$keys['route_order'] = '0';
				$keys['route_type'] = ONE_QUALI;
			}
			$keys['route_name'] = $keys['route_order'] >= 2 ? lang('Final') :
				($keys['route_order'] == 1 ? '2. ' : '').lang('Qualification');

			if ($previous && !$previous['route_quota']/* && ($discipline != 'speed' || $content['route_order'] <= 2)*/)
			{
				$msg = lang('No quota set in the previous heat!!!');
			}
			if (substr($discipline,0,5) != 'speed')
			{
				if ($comp['nation'] == 'SUI')
				{
					$keys['route_quota'] = '';	// no default quota for SUI
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
						$keys['route_judge'][] = common::grab_owner_name($uid);
					}
					$keys['route_judge'] = implode(', ',$keys['route_judge']);
				}
			}
			$content = $this->route->init($keys);
			$content['new_route'] = true;
			$content['route_status'] = STATUS_STARTLIST;

			// default to 5 boulders
			$content['route_num_problems'] = 5;
		}
		return $msg ? $msg : true;
	}

	/**
	 * Singleton to get a boresult instance
	 *
	 * @return boresult
	 */
	static public function getInstance()
	{
		if (!is_object($GLOBALS['boresult']))
		{
			$GLOBALS['boresult'] = new boresult;
		}
		return $GLOBALS['boresult'];
	}
	
	/**
	 * Get URL for athlete profile
	 * 
	 * Currently /pstambl.php is used for every HTTP_HOST not www.ifsc-climbing.org,
	 * for which /index.php?page_name=pstambl is used.
	 * 
	 * @param array $athlete
	 * @param int $cat=null
	 * @return string
	 */
	static function profile_url($athlete,$cat=null)
	{
		static $base;
		if (is_null($base))
		{
			$base = 'http://'.$_SERVER['HTTP_HOST'];
			if ($_SERVER['HTTP_HOST'] == 'www.ifsc-climbing.org')
			{
				$base .= '/index.php?page_name=pstambl&person=';
			}
			else
			{
				$base .= '/pstambl.php?person=';
			}
		}
		return $base.$athlete['PerId'].($cat ? '&cat='.$cat : '');
	}

	/**
	 * Export route for xml or json access
	 * 
	 * @param int $comp
	 * @param int|string $cat
	 * @param int $heat=-1
	 * @return array
	 */
	function export_route($comp,$cat,$heat=-1)
	{
		$start = microtime(true);
	
		if (!($cat = $this->cats->read($cat)))
		{
			throw new Exception(lang('Category NOT found !!!'));
		}
		//echo "<pre>".print_r($cat,true)."</pre>\n";
		if (!($discipline = $cat['discipline']))
		{
			if (!($comp = $this->comp->read($comp)))
			{
				throw new Exception(lang('Competition NOT found !!!'));
			}
			$discipline = $comp['discipline'];
		}
	
		if (!isset($heat) || !is_numeric($heat)) $heat = -1;	// General result
	
		if (!($route = $this->route->read(array(
			'WetId' => $comp,
			'GrpId' => $cat['GrpId'],
			'route_order' => $heat,
		))))
		{
			throw new Exception(lang('Route NOT found !!!'));
		}
		//printf("<p>reading route+result took %4.2lf s</p>\n",microtime(true)-$start);
	
		//echo "<pre>".print_r($route,true)."</pre>\n";
	
		// append category name to route name
		$route['route_name'] .= ' '.$cat['name'];
		
		if ($this->route_result->isRelay != ($discipline == 'speedrelay'))
		{
			$this->route_result->__construct($this->config['ranking_db_charset'],$this->db,null,
					$discipline == 'speedrelay');
		}
		if (!($result = $this->route_result->search(array(),false,'result_rank','','',false,'AND',false,array(
			'WetId' => $comp,
			'GrpId' => $cat['GrpId'],
			'route_order' => $heat,
			'discipline'  => $discipline,
			'route_type'  => $route['route_type'],
		)))) $result = array();
		//echo "<pre>".print_r($result,true)."</pre>\n";
		
		// return route_names as part of route, not as participant
		if (isset($result['route_names']))
		{
			$route['route_names'] = $result['route_names'];
			unset($result['route_names']);
		}
	
		// remove empty/null values from route
		foreach($route as $name => $value)
		{
			if ((string)$value === '') unset($route[$name]);
		}
		// remove not needed route attributes
		$route = array_diff_key($route,array_flip(array(
			'route_type',
			'frm_id','frm_id2',
			'user_timezone_read',
			'route_time_host','route_time_port',
			'route_status',	// 'route_result' is set if result is official
			'slist_order',
		)));
		if ($discipline != 'boulder') unset($route['route_num_problems']);
		
		if ($discipline == 'speedrelay')	// fetch athlete names
		{
			foreach($result as $key => $row)
			{
				$ids[] = $row['PerId_1'];
				$ids[] = $row['PerId_2'];
				if (!empty($row['PerId_3'])) $ids[] = $row['PerId_3'];
			}
			foreach($this->athlete->search(array('PerId' => $ids),false) as $athlete)
			{
				$athletes[$athlete['PerId']] = array(
					'PerId'     => $athlete['PerId'],
					'federation'=> $athlete['verband'],
					'firstname' => $athlete['vorname'],
					'lastname'  => $athlete['nachname'],
					'nation'    => $athlete['nation'],
					'url'       => self::profile_url($athlete,$cat['GrpId']),
				);
			}
			//echo "<pre>".print_r($athletes,true)."</pre>\n";die('Stop');
		}
		
		$tn = $unranked = array();
		$last_modified = (int)$route['route_modified'];	// seems to get not set atm.
		foreach($result as $key => $row)
		{
			if (isset($row['quali_points']) && $row['quali_points'])
			{
				$row['quali_points'] = number_format($row['quali_points'],2);
			}
			if ($row['result_modified'] > $last_modified) $last_modified = $row['result_modified'];
			
			if ($heat == -1)	// rename result to result0 for general result
			{
				$row['result0'] = $row['result'];
				unset($row['result']);
			}
			// use english names
			$row['firstname'] = $row['vorname'];
			$row['lastname']  = $row['nachname'];
			$row['federation']= $row['verband'];
			if ($row['PerId']) $row['url'] = self::profile_url($row,$cat['GrpId']);
			
			// remove &nbsp; in lead results
			if ($discipline == 'lead')
			{
				if (isset($row['quali_points']))
				{
					for($i = 0; $i <= 1; ++$i)
					{
						if(isset($row['result'.$i]))
						{
							$row['result'.$i] = str_replace('&nbsp;',' ',$row['result'.$i]);
						}
					}
				}
				if(isset($row['result']))
				{
					list($row['result']) = explode('&nbsp;',$row['result']);
				}
				for($i = 0; $i <= 5; ++$i)
				{
					if(isset($row['result'.$i]))
					{
						list($row['result'.$i]) = explode('&nbsp;',$row['result'.$i]);
					}
				}
			}
			// remove single boulder meaningless in general result
			if ($discipline == 'boulder' && $heat == -1)
			{
				for($i = 1; $i <= 8; ++$i)
				{
					unset($row['boulder'.$i]);
				}
			}
			// for speed show time_sum as result, plus result_l and result_r
			if (isset($row['time_sum']))
			{
				$row['result_l'] = $row['result'];
				$row['result'] = $row['time_sum'];
				unset($row['time_sum']);	// identical to result
			}
			if ($discipline == 'speedrelay')
			{
				$athletes[$row['PerId_1']]['start_number'] = $row['start_number_1'];
				if ($heat > -1) $athletes[$row['PerId_1']]['result_time'] = $row['result_time_1'];
				$athletes[$row['PerId_2']]['start_number'] = $row['start_number_2'];
				if ($heat > -1) $athletes[$row['PerId_2']]['result_time'] = $row['result_time_2'];
				$row['athletes'] = array(
					$athletes[$row['PerId_1']],
					$athletes[$row['PerId_2']],
				);
				if (!empty($row['PerId_3']))
				{
					$athletes[$row['PerId_3']]['start_number'] = $row['start_number_3'];
					if ($heat > -1) $athletes[$row['PerId_3']]['result_time'] = $row['result_time_3'];
					$row['athletes'][] = $athletes[$row['PerId_3']];
				}
				unset($row['time_sum']);	// identical to result
			}
			// always return result attribute
			if ($heat != -1 && !isset($row['result'])) $row['result'] = '';
			
			// remove not needed attributes
			$row = array_diff_key($row,array_flip(array(
				// remove keys, they are already in route
				'GrpId','WetId','route_order','route_type','discipline',
				'geb_date',	// we still have birthyear
				// remove renamed values
				'vorname','nachname','verband','plz','ort',
				'general_result','org_rank','result_modifier',
				'RouteResults.*','result_detail',
				// speed single route use: result, result_l, result_r
				'result_time','result_time_l','result_time_r',
				// speed general result use: result*, result_rank*
				'result_time2','result_time3','result_time4','result_time5','result_time6',
				'start_order2','start_order3','start_order4','start_order5','start_order6',
				// lead general result
				'result_height','result_height1','result_height2','result_height3','result_height4','result_height5',
				'result_plus','result_plus1','result_plus2','result_plus3','result_plus4','result_plus5',
				// boulder general result
				'top','top1','top2','top3','top4','top5','top6','top7','top8',
				'zone','zone1','zone2','zone3','zone4','zone5','zone6','zone7','zone8',
				'result_top','result_top1','result_top2','result_top3','result_top4','result_top5','result_top6','result_top7','result_top8',
				'result_zone','result_zone1','result_zone2','result_zone3','result_zone4','result_zone5','result_zone6','result_zone7','result_zone8',
				// teamrelay
				'PerId_1','PerId_2','PerId_3',
				'start_number_1','start_number_2','start_number_3',
				'result_time_1','result_time_2','result_time_3',
			)));
			
			ksort($row);
			if ($row['result_rank'])
			{
				$tn[$row[$this->route_result->id_col]] = $row;
			}
			else
			{
				$unranked[$row[$this->route_result->id_col]] = $row;
			}
			$last_rank = $row['result_rank'];
		}
		$tn = array_merge($tn,$unranked);
	
		$ret = $route+array(
			'discipline'    => $discipline,
			'participants'  => $tn,
			'last_modified' => $last_modified,
		);
		$ret['etag'] = md5(serialize($ret));
	
		return $ret;
	}
}