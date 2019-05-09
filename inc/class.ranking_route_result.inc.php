<?php
/**
 * eGroupWare digital ROCK Rankings - route-results storage object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2007-18 by Ralf Becker <RalfBecker@digitalrock.de>
 */

if (!defined('ONE_QUALI'))
{
	include_once(EGW_INCLUDE_ROOT.'/ranking/inc/class.ranking_result_bo.inc.php');
}

use EGroupware\Api;

/**
 * route object
 */
class ranking_route_result extends Api\Storage\Base
{
	const TOP_PLUS = 9999;
	const TOP_HEIGHT = 99999999;
	const ELIMINATED_TIME = 999999;
	const WILDCARD_TIME = 1;
	/**
	 * Number of false starts without penality / being eliminated
	 *
	 * 2016: 1 false start in whole competition was ok
	 * 2017+: 0 false starts (competitor elimited on 1. false start!)
	 */
	const MAX_FALSE_STARTS = 0;

	/**
	 * Name of the result table
	 */
	const RESULT_TABLE = 'RouteResults';
	const RELAY_TABLE = 'RelayResults';

	/**
	 * Maximum number of boulders
	 *
	 * There have to be columns for each boulder in eTemplate "ranking.result.index.rows_boulder
	 */
	const MAX_BOULDERS = 10;

	var $non_db_cols = array(	// fields in data, not (direct) saved to the db
		'ranking',
	);
	var $charset,$source_charset;

	/**
	 * Class instanciated for relay table
	 *
	 * @var boolean
	 */
	var $isRelay = false;
	/**
	 * Id of an athlete or a team
	 *
	 * @var string
	 */
	var $id_col = 'PerId';

	//var $athlete_join = 'LEFT JOIN Personen USING(PerId) LEFT JOIN Athlete2Fed a2f ON Personen.PerId=a2f.PerId AND a2f.a2f_end=9999 LEFT JOIN Federations USING(fed_id)';
	const ATHLETE_JOIN = ' LEFT JOIN Personen USING(PerId) LEFT JOIN Athlete2Fed a2f ON Personen.PerId=a2f.PerId AND a2f.a2f_end=9999 LEFT JOIN Federations ON a2f.fed_id=Federations.fed_id';
	const ACL_FED_JOIN = ' LEFT JOIN Athlete2Fed a2acl_f ON Personen.PerId=a2acl_f.PerId AND a2acl_f.a2f_end=-1 LEFT JOIN Federations acl_fed ON a2acl_f.fed_id=acl_fed.fed_id';
	const FED_PARENT_JOIN = ' LEFT JOIN Federations parent_fed ON Federations.fed_parent=parent_fed.fed_id';

	var $rank_lead = 'CASE WHEN result_height IS NULL THEN NULL ELSE (SELECT 1+COUNT(*) FROM RouteResults r WHERE RouteResults.WetId=r.WetId AND RouteResults.GrpId=r.GrpId AND RouteResults.route_order=r.route_order AND (RouteResults.result_height < r.result_height OR RouteResults.result_height = r.result_height AND RouteResults.result_plus < r.result_plus)) END';
	var $rank_lead_countback = 'CASE WHEN result_height IS NULL THEN NULL ELSE (SELECT 1+COUNT(*) FROM RouteResults r WHERE RouteResults.WetId=r.WetId AND RouteResults.GrpId=r.GrpId AND RouteResults.route_order=r.route_order AND (RouteResults.result_height < r.result_height OR RouteResults.result_height = r.result_height AND RouteResults.result_plus < r.result_plus)) END';
	// "points" for TWO_QUALI_ALL_SUM: height-sum incl. plus/minus counting +/- 1cm
	var $rank_lead_sum = 'CASE WHEN RouteResults.result_height IS NULL THEN r1.result_height/1000.0+r1.result_plus/100.0 WHEN r1.result_height IS NULL THEN RouteResults.result_height/1000.0+RouteResults.result_plus/100.0 ELSE (RouteResults.result_height+r1.result_height)/1000.0+(RouteResults.result_plus+r1.result_plus)/100.0 END';
	var $rank_boulder = 'CASE WHEN result_top IS NULL AND result_zone IS NULL THEN NULL ELSE (SELECT 1+COUNT(*) FROM RouteResults r WHERE RouteResults.WetId=r.WetId AND RouteResults.GrpId=r.GrpId AND RouteResults.route_order=r.route_order AND (RouteResults.result_top < r.result_top OR RouteResults.result_top = r.result_top AND RouteResults.result_zone < r.result_zone OR RouteResults.result_top IS NULL AND r.result_top IS NULL AND RouteResults.result_zone < r.result_zone OR RouteResults.result_top IS NULL AND r.result_top IS NOT NULL)) END';
	var $rank_speed_quali = 'CASE WHEN result_time IS NULL THEN NULL ELSE (SELECT 1+COUNT(*) FROM RouteResults r WHERE RouteResults.WetId=r.WetId AND RouteResults.GrpId=r.GrpId AND RouteResults.route_order=r.route_order AND RouteResults.result_time > r.result_time) END';
	var $rank_speed_final = 'CASE WHEN result_time IS NULL THEN NULL ELSE 1+(SELECT RouteResults.result_time > r.result_time FROM RouteResults r WHERE RouteResults.WetId=r.WetId AND RouteResults.GrpId=r.GrpId AND RouteResults.route_order=r.route_order AND RouteResults.start_order != r.start_order AND (RouteResults.start_order-1) DIV 2 = (r.start_order-1) DIV 2) END';
	// 2918 combined speed final incl. small final in first 2 starters (return 3 for final and 2 for small final, all non-winners (!=1) are then sorted by time)
	//var $rank_speed_combi_final = 'CASE WHEN result_time IS NULL THEN NULL WHEN (start_order-1)DIV 2 THEN 2 ELSE 4 END';
	// 2019+ combined 1/2-final offset for loosers, gets added to $rank_speed_final
	var $rank_speed_combi_semi_final = 'CASE WHEN result_time IS NULL THEN NULL WHEN (start_order-1)DIV 4 THEN 0 ELSE 4 END';
	// 2019+ combined final offset for loosers, gets added to $rank_speed_final
	var $rank_speed_combi_final = 'CASE WHEN result_time IS NULL THEN NULL ELSE 6-2*((start_order-1)DIV 2) END';

	/**
	 * Discipline only set by ranking_result_bo->save_result, to be used in data2db
	 *
	 * @var string
	 */
	var $discipline;
	/**
	 * Type of route (how many qualifications) only set by ranking_result_bo->save_result, to be used in data2db
	 *
	 * @var int
	 */
	var $route_type;
	/**
	 * constructor of the competition class
	 */
	function __construct($source_charset='',$db=null,$pdf_dir=null,$relay=false)
	{
		unset($pdf_dir);
		$this->isRelay = $relay;
		$this->id_col =  $relay ? 'team_id' : 'PerId';
		//$this->debug = 1;
		parent::__construct('ranking',$relay ? self::RELAY_TABLE : self::RESULT_TABLE,$db);	// call constructor of extended class

		if ($source_charset) $this->source_charset = $source_charset;

		$this->charset = Api\Translation::charset();
	}

	/**
	 * searches db for rows matching searchcriteria
	 *
	 * '*' and '?' are replaced with sql-wildcards '%' and '_'
	 *
	 * For a union-query you call search for each query with $start=='UNION' and one more with only $order_by and $start set to run the union-query.
	 *
	 * @param array|string $criteria array of key and data cols, OR a SQL query (content for WHERE), fully quoted (!)
	 * @param boolean|string|array $only_keys =true True returns only keys, False returns all cols. or
	 *	comma seperated list or array of columns to return
	 * @param string $order_by ='' fieldnames + {ASC|DESC} separated by colons ',', can also contain a GROUP BY (if it contains ORDER BY)
	 * @param string|array $extra_cols ='' string or array of strings to be added to the SELECT, eg. "count(*) as num"
	 * @param string $wildcard ='' appended befor and after each criteria
	 * @param boolean $empty =false False=empty criteria are ignored in query, True=empty have to be empty in row
	 * @param string $op ='AND' defaults to 'AND', can be set to 'OR' too, then criteria's are OR'ed together
	 * @param mixed $start =false if != false, return only maxmatch rows begining with start, or array($start,$num), or 'UNION' for a part of a union query
	 * @param array $filter =null if set (!=null) col-data pairs, to be and-ed (!) into the query without wildcards
	 * @param string $join ='' sql to do a join, added as is after the table-name, eg. "JOIN table2 ON x=y" or
	 *	"LEFT JOIN table2 ON (x=y AND z=o)", Note: there's no quoting done on $join, you are responsible for it!!!
	 * @return boolean|array of matching rows (the row is an array of the cols) or False
	 */
	function &search($criteria,$only_keys=True,$order_by='',$extra_cols='',$wildcard='',$empty=False,$op='AND',$start=false,$filter=null,$join='')
	{
		//error_log(__METHOD__."(crit=".array2string($criteria).",only_keys=".array2string($only_keys).",order_by=$order_by,extra_cols=".array2string($extra_cols).",$wildcard,$empty,$op,$start,filter=".array2string($filter).",join=$join)");
		if (!is_array($extra_cols)) $extra_cols = $extra_cols ? explode(',',$extra_cols) : array();
		$initial_filter = $filter;

		// avoid PerId is ambigous SQL error
		if (is_array($criteria) && isset($criteria[$this->id_col]))
		{
			$criteria[$this->table_name.'.'.$this->id_col] = $criteria[$this->id_col];
			unset($criteria[$this->id_col]);
		}
		if (is_array($filter) && isset($filter['PerId']))
		{
			$filter[] = $this->db->expression($this->table_name, $this->table_name.'.', array('PerId' => $filter['PerId']));
			unset($filter['PerId']);
		}
		if (is_array($filter) && array_key_exists('route_type',$filter))	// pseudo-filter to transport the route_type
		{
			$route_type = $filter['route_type'];
			unset($filter['route_type']);
			$extra_cols[] = $route_type.' AS route_type';
		}
		if (is_array($filter) && array_key_exists('quali_preselected', $filter))
		{
			$quali_preselected = $filter['quali_preselected'];
			unset($filter['quali_preselected']);
		}
		if (is_array($filter) && array_key_exists('discipline',$filter))	// pseudo-filter to transport the discipline
		{
			$discipline = $filter['discipline'];
			unset($filter['discipline']);
			$extra_cols[] = "'$discipline' AS discipline";
		}
		if (is_array($filter) && array_key_exists('combined',$filter))	// pseudo-filter to transport route is combined
		{
			$combined = $filter['combined'];
			unset($filter['combined']);
		}
		if (is_array($filter) && array_key_exists('comp_nation',$filter))	// pseudo-filter to transport the nation
		{
			$comp_nation = $filter['comp_nation'];
			unset($filter['comp_nation']);
		}
		if (is_array($filter) && isset($filter['route_order']))
		{
			$route_order =& $filter['route_order'];
		}
		elseif (isset($criteria['route_order']))
		{
			$route_order =& $criteria['route_order'];
		}
		if ($route_order === 0) $route_order = '0';		// otherwise it get's ignored by so_sql;
		$initial_route_order = $route_order;

		// keep general_result_join from setting order_by
		if (isset($filter['keep_order_by']))
		{
			$keep_order_by = $filter['keep_order_by'] ? $order_by : false;
			unset($filter['keep_order_by']);
		}

		if (!$only_keys && !$join || $route_order < 0)
		{
			if (!$this->isRelay)
			{
				$join = self::ATHLETE_JOIN.($join && is_string($join) ? "\n".$join : '');
				$extra_cols = array_merge($extra_cols, array(
					'vorname','nachname','acl','Federations.nation AS nation','geb_date',
					'Federations.verband AS verband','Federations.fed_url AS fed_url',
					'Federations.fed_id AS fed_id','Federations.fed_parent AS fed_parent',
					'ort','plz','rkey',
				));

				switch($comp_nation)
				{
					case 'SUI':
						$join .= self::ACL_FED_JOIN;
						$extra_cols[] = 'acl_fed.fed_shortcut AS acl_fed';
						$extra_cols[] = 'acl_fed.fed_id AS acl_fed_id';
						break;
					case 'GER':
						$join .= self::FED_PARENT_JOIN;
						$extra_cols[] = 'parent_fed.fed_shortcut AS parent_fed';
						break;
				}
			}
			// combined heats have different route-order
			if ($combined && in_array($route_order, array(6, 7)))
			{
				$extra_cols[] = '('.$this->_sql_rank_prev_heat($route_order, 'combined').') AS rank_prev_heat';
			}
			elseif ($discipline === 'speed' && $route_order >= 2)
			{
				$extra_cols[] = '('.$this->_sql_rank_prev_heat($route_order, 'speed-final').') AS quali_details';
			}
			elseif ($combined && $route_order > 0)
			{

			}
			// first heat after qualification
			// quali points are to be displayed with 2 digits for 2008 (but all digits counting)
			elseif ($route_order == 2 && in_array($route_type, array(TWO_QUALI_ALL,TWO_QUALI_ALL_NO_COUNTBACK)) ||
				$route_order == 3 && $route_type == THREE_QUALI_ALL_NO_STAGGER)
			{
				$extra_cols[] = 'ROUND(('.$this->_sql_rank_prev_heat($route_order,$route_type).'),2) AS rank_prev_heat';
			}
			// heats after first heat after qualifcation
			elseif ($route_order >= 2+(int)($route_type == TWO_QUALI_HALF)+($route_type == THREE_QUALI_ALL_NO_STAGGER ? 2 : 0))
			{
				$extra_cols[] = '('.$this->_sql_rank_prev_heat($route_order,$route_type).') AS rank_prev_heat';
			}
			// general result
			elseif ($route_order < 0)
			{
				// overall qualification result: 1: groupA, 2: groupB, 3: quali overall, 0: other
				$quali_overall = $route_order < -2 ? $route_order+6 : 0;

				$extra_cols[] = "1 AS general_result";
				// use users from the qualification(s)
				if ($route_type == TWOxTWO_QUALI)
				{
					$route_order = array(2,3);	// only use the second quali, containing the rank of both
				}
				elseif ($route_type == TWO_QUALI_HALF)
				{
					$route_order = array(0,1);
				}
				elseif ($route_order == -6)		// combined: overall speed final
				{
					$route_order = 3;
					$discipline = 'speed';
					$quali_overall = 6;
				}
				elseif ($quali_overall == 2)	// Group B
				{
					$route_order = 2;
				}
				// general result or qualification overall for two quali groups
				elseif ($route_type == TWO_QUALI_GROUPS && in_array($route_order, array(-1, -3)))
				{
					$route_order = array(-4, -5);
				}
				else
				{
					$route_order = 0;
				}
				$result_cols = array('result_rank');
				switch($discipline)
				{
					case 'combined':	// needs all columns
					default:
					case 'lead':
						$result_cols[] = 'result_height';
						$result_cols[] = 'result_plus';
						if ($discipline != 'combined') break;
						// fall through for combined
					case 'speed':
					case 'speedrelay':
						$result_cols[] = 'result_time';
						$result_cols[] = 'start_order';
						$result_cols[] = 'result_detail';	// false_start
						if ($discipline != 'combined') break;
						// fall through for combined
					case 'boulder':
					case 'boulder2018':
					case 'selfscore':
						$result_cols[] = 'result_top';
						$result_cols[] = 'result_zone';
						break;
				}

				$order_by_parts = preg_split('/[ ,]/',$order_by);

				$route_names = null;
				$join .= $this->general_result_join(array(
					'WetId' => $filter['WetId'] ? $filter['WetId'] : $criteria['WetId'],
					'GrpId' => $filter['GrpId'] ? $filter['GrpId'] : $criteria['GrpId'],
				), $extra_cols, $order_by, $route_names, $route_type, $discipline, $result_cols, $quali_overall);

				if ($keep_order_by) $order_by = $keep_order_by;

				foreach($filter as $col => $val)
				{
					if (!is_numeric($col))
					{
						$filter[] = $this->db->expression($this->table_name,$this->table_name.'.',array($col => $val));
						unset($filter[$col]);
					}
				}
				if (count($route_names) == 1)
				{
					// not yet a 2. route --> show everyone from 1. route (used for xml/json export)
				}
				// speed stores both results in the first quali
				elseif (in_array($route_type, array(TWO_QUALI_ALL,TWO_QUALI_ALL_SUM,TWO_QUALI_ALL_NO_COUNTBACK,THREE_QUALI_ALL_NO_STAGGER)) && $discipline != 'speed')
				{
					$filter[] = '('.$this->table_name.'.result_rank IS NOT NULL OR r1.result_rank IS NOT NULL'.
						// if exist need to check first final route too, as prequalifed are not ranking in quali
						(isset($route_names[2]) ? ' OR r2.result_rank IS NOT NULL' : '').')';
				}
				else
				{
					$filter[] = '('.$this->table_name.'.result_rank IS NOT NULL'.
						// if exist need to check first final route too, as prequalifed are not ranking in quali
						(isset($route_names[2]) ? ' OR r2.result_rank IS NOT NULL' : '').')';
				}
				$rows =& parent::search($criteria,$only_keys,$order_by,$extra_cols,$wildcard,$empty,$op,$start,$filter,$join);

				if (!$rows) return $rows;

				// apply head to head comparison to break ties in overall ranking of combined final or qualification
				// as the head-to-head must have higher precedence then the regular countback, we can not use regular code
				if ($discipline == 'combined' && (in_array($quali_overall, array(3, 6)) || $initial_route_order == -1))
				{
					$this->do_combined_general_result($rows, $quali_overall, $initial_filter, null, $comp_nation);
				}
				else
				{
					// the general result is always sorted by the overal rank (to get it)
					// now we need to store that rank in result_rank
					$old = null;
					//echo "<p>quali_preselected=$quali_preselected</p>\n";
					foreach($rows as $n => &$row)
					{
						if ($row['route_order'] <= 0) $row['result_rank0'] = $row['result_rank'];
						$row['org_rank'] = $row['result_rank'.max(0, $row['route_order'])];
						//echo "<p>$n: $row[nachname], org_rank=$row[org_rank], result_rank=$row[result_rank] ";

						// check for ties
						$row['result_rank'] = $old['result_rank'];
						foreach(array_reverse(array_keys($route_names)) as $route_order)
						{
							// for quali_preselected: do NOT use qualification, if we have a first final result (route_order=2)
							// same is true for 2012+ EYC: no countback to quali
							if (($route_type==TWO_QUALI_ALL_NO_COUNTBACK || $quali_preselected) && $route_order < 2 && $row['result_rank2']) continue;

							if ($route_type == TWOxTWO_QUALI && $route_order == 3 ||
								$route_type == TWO_QUALI_HALF && $route_order == 1 ||
								$route_type == TWO_QUALI_GROUPS && $route_order < 0)
							{
								if (!$old || $old['org_rank'] < $row['org_rank']) $row['result_rank'] = $n+1;
								//echo "route_order=$route_order, result_rank=$row[result_rank] --> no further countback ";
								break;		// no further countback
							}
							if (ranking_result_bo::is_two_quali_all($route_type) && $route_order == 1 ||
								$route_type == THREE_QUALI_ALL_NO_STAGGER  && $route_order == 2 ||
								$route_type == TWO_QUALI_GROUPS && $route_order < 4)
							{
								if ($route_type == TWO_QUALI_ALL_SUM)	// sum is in quali_points and higher points are better
								{
									if (!$old || $old['quali_points'] > $row['quali_points']) $row['result_rank'] = $n+1;
								}
								else	// original quali_points --> lower points are better
								{
									if (!$old || $old['quali_points'] < $row['quali_points']) $row['result_rank'] = $n+1;
								}
								break;		// no further countback
							}
							if (!$old || !$row['result_rank'.$route_order] && $old['result_rank'.$route_order] ||	// 1. place or no result yet
								$old['result_rank'.$route_order] < $row['result_rank'.$route_order])	// or worse place then the previous
							{
								$row['result_rank'] = $n+1;						// --> set the place according to the position in the list
								break;
							}
							// for quali on two routes with half quota, there's no countback to the quali only if there's a result for the 2. heat
							if ($route_type == TWO_QUALI_HALF && $route_order == 2 && $row['result_rank2'] ||
								$route_type == TWOxTWO_QUALI  && $route_order == 4 && $row['result_rank4'] ||
								$route_type == TWO_QUALI_GROUPS && $route_order == 4 && $row['result_rank4'])
							{
								break;	// --> not use countback
							}
						}
						//echo " --> rank=$row[result_rank]</p>\n";
						$old = $row;
					}
				}
				// randomize ex-aquos for startlist
				if (!$keep_order_by && ($k = array_search('RAND()', $order_by_parts)) !== false)
				{
					usort($rows, function($a,$b)
					{
						if ($a['result_rank'] == $b['result_rank'])
						{
							return rand(0,1) ? -1 : 1;
						}
						return $a['result_rank'] - $b['result_rank'];
					});
					unset($order_by_parts[$k]);
				}

				// now we need to check if user wants to sort by something else
				$order = array_shift($order_by_parts);
				$sort  = array_pop($order_by_parts);
				// $keep_order_by bypasses sorting here for startlist creation of first heat after quali. with two groups
				if (!$keep_order_by)
				{
					if ($order != 'result_rank')
					{
						// sort the rows now by the user's criteria
						usort($rows, function($a, $b) use ($sort, $order)
						{
							return ($sort == 'DESC' ? -1 : 1)*
								($this->table_def['fd'][$order] == 'varchar' || in_array($order, array('nachname','vorname','nation','ort')) ?
									strcasecmp($a[$order], $b[$order]) : ($a[$order] - $b[$order]));
						});
					}
					elseif($sort == 'DESC')
					{
						$rows = array_reverse($rows);
					}
				}

				foreach(array(-4 => 1, -5 => 0, ) as $from => $to)
				{
					if (isset($route_names[$from]))
					{
						$route_names[$to] = $route_names[$from];
						unset($route_names[$from]);
					}
				}
				$rows['route_names'] = $route_names;

				return $rows;
			}
		}
		// otherwise wildcards or not matching LEFT JOINs remove PerId/team_id
		if (!is_string($only_keys) || strpos($only_keys,$this->id_col) !== false)
		{
			$extra_cols[] = $this->table_name.'.'.$this->id_col.' AS '.$this->id_col;
		}
		return parent::search($criteria,$only_keys,$order_by,$extra_cols,$wildcard,$empty,$op,$start,$filter,$join);
	}

	/**
	 * Calculate rank in combined general result using several tie-breakers
	 *
	 * Rules require a certain order of tie breakes being applied:
	 * Final:
	 * - final_points (sorted by them in SQL already)
	 * - head-to-head comparison for max. 2 tied, not applying for more!
	 * - countback to qualification
	 * Qualification:
	 * - quali_points (sorted by them in SQL already)
	 * - head-to-head comparison for max. 2 tied, not applying for more!
	 * - seeding list aka start-order of speed qualification
	 *
	 * @param array& $results result-rows with rank in key "result_rank" on return
	 * @param int $quali_overall 0: general result, 3: overall qualification, 6: overall final
	 * @param array $filter filter for this ranking to be able load qualification, if needed
	 * @param array $qualification =null qualification result, default query it (for testing)
	 * @param string $comp_nation =null nation of the competition: "GER" does NOT use seeding list as final tie breaker
	 */
	function do_combined_general_result(array &$results, $quali_overall, array $filter, array $qualification=null, $comp_nation=null)
	{
		$input = $results;
		// we only check finals, if lead final has at least one result (first rank has a lead final result)
		$check_finals = $quali_overall != 3;// && !empty($results[0]['result_rank7']);

		// first set result_rank by final_points or quali_points (already ordered by them)
		$old = null;
		foreach($results as $n => &$result)
		{
			$result['result_rank0'] = $result['result_rank'];	// save qualification result to result_rank0

			if (!$old)
			{
				$result['result_rank'] = 1;
			}
			// check n-1 and n are tied
			else
			{
				$result['result_rank'] = $old['result_rank'];

				if (isset($old['final_points']))
				{
					if (!isset($result['final_points']) ||
						$old['final_points'] < $result['final_points'])
					{
						$result['result_rank'] = $n+1;
					}
				}
				elseif (isset($old['quali_points']))
				{
					if (!isset($result['quali_points']) ||
						$old['quali_points'] < $result['quali_points'])
					{
						$result['result_rank'] = $n+1;
					}
				}
			}
			//error_log("1: $result[nachname], $result[vorname] ($result[nation]) final_points=$result[final_points], quali_points=$result[quali_points], start_order=$result[start_order] --> result_rank=$result[result_rank]");
			$old =& $result;
		}

		// now do the tie-breaking and countback in order specified in the rules
		unset($old);
		$skip = 0;
		$need_sorting = false;
		foreach($results as $n => &$result)
		{
			$score = 0;
			if ($skip-- > 0 || !empty($old) && $result['result_rank'] == $old['result_rank'])
			{
				if ($check_finals && isset($result['final_points']))
				{
					// final head-to-head tie-breaker: check only 2 are tied (requirement for head-to-head comparison!)
					if ($n == count($results)-1 || $results[$n+1]['result_rank'] != $result['result_rank'])
					{
						$score = self::head_to_head_comp($result, $old, array(5, 6, 7));
					}

					// no head-to-head tie-break --> countback to qualification
					if (!$score)
					{
						// we need to load full quali overall, as tie breaker wont work (no pre-ordered list!)
						if (!isset($qualification))
						{
							$qualification = array();
							foreach($this->search(array(), false, 'result_rank', '', '', false, 'AND', false, array(
								'route_order' => -3,	// qualification overall
							)+$filter) as $k => $row)
							{
								if (is_int($k)) $qualification[$row['PerId']] = $row['result_rank'];
							}
						}
						// get tied athletes we need to do quali countback (might be more then just $old and $result!)
						$tied = array();
						for($i = $n-1; $i < count($results) && $old['result_rank'] == $results[$i]['result_rank']; ++$i)
						{
							$tied[$i] = $qualification[$results[$i]['PerId']];
						}
					}
				}
				else
				{
					// qualification head-to-head tie-breaker: check only 2 are tied (requirement for head-to-head comparison!)
					if ($n == count($results)-1 || $results[$n+1]['result_rank'] != $result['result_rank'])
					{
						$score = self::head_to_head_comp($result, $old, array(0, 1, 2));
					}

					// no head-to-head tie-break --> countback to seeding list
					if (!$score && $comp_nation !== 'GER')
					{
						// get tied athletes we need to do seeding list countback (might be more then just $old and $result!)
						$tied = array();
						for($i = $n-1; $i < count($results) && $old['result_rank'] == $results[$i]['result_rank']; ++$i)
						{
							$tied[$i] = 1000 - $results[$i]['start_order'];	// best in seeding list starts last!
						}
					}
				}
				if (isset($tied))
				{
					// sort them by ascending quali result or seeding/start-list
					asort($tied, SORT_NUMERIC);
					$rank = $old['result_rank'];
					$skip = -2;	// old and result are already processed, no need to skip
					foreach(array_keys($tied) as $i)
					{
						$results[$i]['result_rank'] = $rank++;
					}
					$need_sorting = true;
					unset($tied);
				}
				elseif(!$score)
				{
					// GER does not use seeding list
				}
				elseif ($score < 0)
				{
					++$result['result_rank'];
				}
				else
				{
					++$old['result_rank'];
					$need_sorting = true;
				}
			}
			//error_log("2: $result[nachname], $result[vorname] ($result[nation]) final_points=$result[final_points], quali_points=$result[quali_points], score=$score, start_order=$result[start_order] --> result_rank=$result[result_rank]");
			$old =& $result;
		}

		if ($need_sorting)
		{
			usort($results, function($a, $b)
			{
				return $a['result_rank']-$b['result_rank'];
			});
		}
		// dump results for writing tests, if not running the test ;)
		if ($filter)
		{
			file_put_contents($path=$GLOBALS['egw_info']['server']['temp_dir'].'/combined-general-'.$filter['WetId'].'-'.$filter['GrpId'].'-'.$filter['route_order'].'.php',
				"<?php\n\n\$input = ".var_export($input, true).";\n\$quali_overall = $quali_overall;\n\$qualification = ".var_export($qualification, true).";\n\$results = ".var_export($results, true).";\n");
			error_log(__METHOD__."() logged input and results to ".realpath($path));
		}
}

	/**
	 * Do a head to head comparison for two results and given heats
	 *
	 * @param array $first
	 * @param array $second
	 * @param array $heats
	 * @return int >0 if $first wins more often, <0 if $second, 0 no tie-break
	 */
	static function head_to_head_comp(array $first, array $second, array $heats)
	{
		$score = 0;
		foreach($heats as $heat)
		{
			if (empty($first['result_rank'.$heat]) || empty($second['result_rank'.$heat]))
			{
				// do nothing until both have a result
			}
			elseif ($first['result_rank'.$heat] > $second['result_rank'.$heat])
			{
				$score--;
			}
			elseif ($first['result_rank'.$heat] < $second['result_rank'.$heat])
			{
				$score++;
			}
		}
		if ($score) error_log(__METHOD__."() head-to-head tie-break on $first[result_rank]. place between $first[nachname], $first[vorname] ($first[nation]) and $second[nachname], $second[vorname] ($second[nation]) heats=".implode(',', $heats)." score=$score");
		return $score;
	}

	/**
	 * Subquery to get the rank in the previous heat
	 *
	 * @param int $route_order
	 * @param int|string $route_type ONE_QUALI, TWO_QUALI_HALF, TWO_QUALI_ALL or "combined" for combined boulder or lead final
	 * @param int $quali_overal =0 1: Group A, 2: Group B, 3: Overal qualification, 0: other
	 * @param array $route_names =null
	 * @param array $keys =null WetId and GrpId for combined final checks
	 * @param string $discipline =null 'lead', 'speed', 'boulder', 'speedrelay' or 'combined'
	 * @return string
	 */
	private function _sql_rank_prev_heat($route_order, $route_type, $quali_overal=0, array $route_names=null, array $keys=null, $discipline=null)
	{
		if ($route_order == 2 && $route_type == TWO_QUALI_ALL_SUM)
		{
			return $this->rank_lead_sum;
		}
		// prev. result for heat after quali for two quali groups (must not be used in general_result_join: $quali_overal > 0!)
		if ($route_type == TWO_QUALI_GROUPS && $route_order == 4 && !$quali_overal)
		{
			return "SELECT result_rank FROM $this->table_name p WHERE $this->table_name.WetId=p.WetId AND $this->table_name.GrpId=p.GrpId AND ".
				"p.route_order IN (-4,-5) AND $this->table_name.$this->id_col=p.$this->id_col";
		}
		if ($route_order == 2 && ranking_result_bo::is_two_quali_all($route_type) ||
			$quali_overal == 2 && $route_order == 4)
		{
			$ro0 = $quali_overal == 2 && $route_order == 4 ? 2 : 0;
			$ro1 = $quali_overal == 2 && $route_order == 4 ? 3 : 1;
			// points for place r with c ex aquo: p(r,c) = (c+2r-1)/2
			$c1 = $this->_count_ex_aquo('r1');
			$c2 = $this->_count_ex_aquo('r2');

			$r1 = $this->_unranked('r1');
			$r2 = $this->_unranked('r2');

			//pre 2008: rounding to 2 digits: return "SELECT ROUND(SQRT((($c1)+2*$r1-1)/2 * (($c2)+2*$r2-1)/2),2) FROM $this->table_name r1".
			return "SELECT SQRT((($c1)+2*$r1-1)/2 * (($c2)+2*$r2-1)/2) FROM $this->table_name r1".
				" JOIN $this->table_name r2 ON r1.WetId=r2.WetId AND r1.GrpId=r2.GrpId AND r2.route_order=$ro1 AND r1.$this->id_col=r2.$this->id_col".
				" WHERE $this->table_name.WetId=r1.WetId AND $this->table_name.GrpId=r1.GrpId AND r1.route_order=$ro0".
				" AND $this->table_name.$this->id_col=r1.$this->id_col";
		}
		// combined lead and boulder finals have countback to their disciplines qualification
		elseif ($route_type === 'combined' && in_array($route_order, array(6, 7)))
		{
			// boulder: 6 -> 1, lead: 7 -> 2
			return "SELECT result_rank FROM $this->table_name p WHERE $this->table_name.WetId=p.WetId AND $this->table_name.GrpId=p.GrpId AND ".
				'p.route_order='.(int)($route_order-5)." AND $this->table_name.$this->id_col=p.$this->id_col";
		}
		// speed finals have countback to speed qualification
		elseif ($route_type === 'speed-final' && $route_order >= 2)
		{
			// we need both times from details
			return "SELECT result_detail FROM $this->table_name p WHERE $this->table_name.WetId=p.WetId AND $this->table_name.GrpId=p.GrpId AND ".
				"p.route_order=0 AND $this->table_name.$this->id_col=p.$this->id_col";
		}
		// 3 qualifications or finals (combined only)
		elseif ($route_type == THREE_QUALI_ALL_NO_STAGGER && in_array($route_order, array(2, 3, -6)))
		{
			$result = $route_order != -6 ? array('r1','r2','r3') : array('r5','r6','r7');
 			// points for place r with c ex aquo: p(r,c) = (c+2r-1)/2
			$c1 = $this->_count_ex_aquo($result[0]);//'r1'
			$c2 = $this->_count_ex_aquo($result[1]);//'r2'
			$c3 = $this->_count_ex_aquo($result[2]);//'r3'

			$r1 = $this->_unranked($result[0]);//'r1'
			$r2 = $this->_unranked($result[1]);//'r2'
			$r3 = $this->_unranked($result[2]);//'r3'

			// combined final
			if ($route_order == -6)
			{
				// check if we already have a result for boulder (6) or lead (7), if not use factor of 1
				for($r = 6; $r <= 7; ++$r)
				{
					if (!isset($route_names[$r]) || $keys && !$this->get_count(array(
						'WetId' => $keys['WetId'],
						'GrpId' => $keys['GrpId'],
						'route_order' => $r,
					), 'result_rank'))
					{
						switch($r)
						{
							case 6:	// no boulder final result yet
								$r2 = $c2 = 1;
								break;
							case 7:	// no lead final result yet
								$r3 = $c3 = 1;
								break;
						}
					}
				}
			}
			// combined quali after boulder (just two or three qualis)
			elseif ($route_order == 2 && $discipline === 'combined')
			{
				return "SELECT ((($c1)+2*$r1-1)/2 * (($c2)+2*$r2-1)/2) FROM $this->table_name r1".
					" JOIN $this->table_name r2 ON r1.WetId=r2.WetId AND r1.GrpId=r2.GrpId AND r2.route_order=1 AND r1.$this->id_col=r2.$this->id_col".
					" WHERE $this->table_name.WetId=r1.WetId AND $this->table_name.GrpId=r1.GrpId AND r1.route_order=0".
					" AND $this->table_name.$this->id_col=r1.$this->id_col";
			}

			return "SELECT ((($c1)+2*$r1-1)/2 * (($c2)+2*$r2-1)/2 * (($c3)+2*$r3-1)/2) FROM $this->table_name r1".
				" JOIN $this->table_name r2 ON r1.WetId=r2.WetId AND r1.GrpId=r2.GrpId AND r2.route_order=1 AND r1.$this->id_col=r2.$this->id_col".
				" JOIN $this->table_name r3 ON r1.WetId=r3.WetId AND r1.GrpId=r3.GrpId AND r3.route_order=2 AND r1.$this->id_col=r3.$this->id_col".
				" WHERE $this->table_name.WetId=r1.WetId AND $this->table_name.GrpId=r1.GrpId AND r1.route_order=0".
				" AND $this->table_name.$this->id_col=r1.$this->id_col";
		}
		elseif($route_type == TWOxTWO_QUALI && in_array($route_order,array(2,3)))
		{
			return "SELECT result_detail FROM $this->table_name p WHERE $this->table_name.WetId=p.WetId AND $this->table_name.GrpId=p.GrpId AND ".
				'p.route_order='.(int)($route_order-2)." AND $this->table_name.$this->id_col=p.$this->id_col";
		}
		return "SELECT result_rank FROM $this->table_name p WHERE $this->table_name.WetId=p.WetId AND $this->table_name.GrpId=p.GrpId AND ".
			'p.route_order '.($route_order == 2 && $route_type != THREE_QUALI_ALL_NO_STAGGER ? 'IN (0,1)' :
			'='.(int)($route_order-1))." AND $this->table_name.$this->id_col=p.$this->id_col";
	}

	private function _count_ex_aquo($r)
	{
		// result_rank == NULL is counted wrong if we do: $r.result_rank=c$r.result_rank
		$rank_equal = "(CASE WHEN $r.result_rank IS NULL THEN c$r.result_rank IS NULL ELSE $r.result_rank=c$r.result_rank END)";
		//pre 2008: "SELECT COUNT(*) FROM $this->table_name c$r WHERE $r.WetId=c$r.WetId AND $r.GrpId=c$r.GrpId AND $r.route_order=c$r.route_order AND $r.result_rank=c$r.result_rank";
		return "SELECT COUNT(*) FROM $this->table_name c$r WHERE $r.WetId=c$r.WetId AND $r.GrpId=c$r.GrpId AND $r.route_order=c$r.route_order AND $rank_equal";
	}

	private function _unranked($r)
	{
		// athlets not climbined in one quali, get ranked last in that quali
		$unranked = "(SELECT MAX($this->table_name.result_rank)+1 FROM $this->table_name WHERE $r.WetId=$this->table_name.WetId AND $r.GrpId=$this->table_name.GrpId AND $r.route_order=$this->table_name.route_order)";
		//pre 2008: "(CASE WHEN $r.result_rank IS NULL THEN 999999 ELSE $r.result_rank END)";
		return "(CASE WHEN $r.result_rank IS NULL THEN $unranked ELSE $r.result_rank END)";
	}

	/**
	 * Return join and extra_cols for a general result
	 *
	 * @param array $keys values for WetId and GrpId
	 * @param array &$extra_cols
	 * @param string &$order_by
	 * @param array &$route_names route_order => route_name pairs
	 * @param int $route_type ONE_QUALI, TWO_QUALI_HALF or TWO_QUALI_ALL
	 * @param string $discipline 'lead', 'speed', 'boulder', 'speedrelay'
	 * @param array $result_cols =array() result relevant col
	 * @param int $quali_overall =0 1: groupA, 2: groupB, 3: quali overall, 6: combined final, 0: other
	 * @return string join
	 */
	public function general_result_join($keys,&$extra_cols,&$order_by,&$route_names,$route_type,$discipline,$result_cols=array(), $quali_overall=0)
	{
		//error_log(__METHOD__."(".array2string($keys).",".array2string($extra_cols).",,,type=$route_type,$discipline,".array2string($result_cols).", quali_overall=$quali_overall)");
		if (!isset($GLOBALS['egw']->route) || !is_object($GLOBALS['egw']->route))
		{
			$GLOBALS['egw']->route = new ranking_route($this->source_charset,$this->db);
		}
		$route_names = $GLOBALS['egw']->route->query_list('route_name','route_order',$keys+array('route_order >= 0'),'route_order');

		// qualificaiton overall result or group A/B
		if ($route_type == TWO_QUALI_GROUPS)
		{
			if ($quali_overall == 3 || !$quali_overall)	// overall group A+B (runs as $result_type == TWO_QUALI_HALF!)
			{
				$route_names = $GLOBALS['egw']->route->query_list('route_name', 'route_order',
					$keys+array($quali_overall ? 'route_order <= -4' : '(route_order <= -4 OR route_order > 3)'),'route_order');
				foreach(array(-5, -4) as $route_order)
				{
					if (!isset($route_names[$route_order])) $route_names[$route_order] = ranking_route::default_name($route_order);
				}
			}
			elseif($quali_overall == 2)	// overall group B
			{
				$route_names = array_slice($route_names, 2, 2, true);
			}
			else	// overall for group A
			{
				$route_names = array_slice($route_names, 0, 2, true);
			}
		}
		// overall speed final for combined
		elseif ($discipline == 'speed' && $quali_overall == 6)
		{
			$route_names = array_slice($route_names, 3, 3, true);
		}
		// overall for two qualifications --> ignore other rounds
		elseif ($quali_overall)
		{
			$route_names = array_slice($route_names, 0, 2+(int)($route_type == THREE_QUALI_ALL_NO_STAGGER), true);
		}
		// combined general result --> use overall speed final (-6) instead of speed finals (3,4,5)
		elseif ($discipline == 'combined' && count($route_names) > 6)	// require at least boulder final to exist
		{
			$route_names = array_slice($route_names, 0, 3, true)+
				array(-6 => empty($route_names[5])?lang('Final').' '.lang('Speed'):$route_names[5])+
				array_slice($route_names, 6, 2, true);

			// do NOT add lead final to general result, unless it has a result, messes up startlist generation of it
			if (isset($route_names[7]) && !ranking_result_bo::getInstance()->has_results(array(
				'WetId' => $keys['WetId'],
				'GrpId' => $keys['GrpId'],
				'route_order' => 7,
			)))
			{
				unset($route_names[7]);
			}
		}

		//error_log(__METHOD__."() route_names=".array2string($route_names));
		$order_bys = array("$this->table_name.result_rank");	// Quali

		$join = "\n";
		$no_more_heats = false;
		foreach(array_keys($route_names) as $route_order)
		{
			if ($route_type == TWOxTWO_QUALI)
			{
				if (in_array($route_order,array(2,3))) continue;	// base of the query, no need to join
			}
			elseif (0 <= $route_order && $route_order < 2-(int)ranking_result_bo::is_two_quali_all($route_type)-(int)($route_type == THREE_QUALI_ALL_NO_STAGGER) ||
				$route_type == TWO_QUALI_GROUPS && in_array($route_order, array(-4, -5)) ||
				$discipline == 'speed' && $quali_overall == 6 && $route_order == 3)
			{
				continue;	// no need to join the qualification
			}

			switch ($ro = $route_order)
			{
				case -6:	// combined speed final
				case -5:	// overall group A
					$ro = 5; break;
				case -4:	// overall group B
					$ro = 4; break;
			}
			$join .= "LEFT JOIN $this->table_name r$ro ON $this->table_name.WetId=r$ro.WetId AND $this->table_name.GrpId=r$ro.GrpId AND r$ro.route_order=$route_order AND $this->table_name.$this->id_col=r$ro.$this->id_col\n";
			foreach($result_cols as $col)
			{
				$extra_cols[] = "r$ro.$col AS $col$ro";
			}
			if ($route_type == TWOxTWO_QUALI && $route_order < 2) continue;	// dont order by the 1. quali

			if (ranking_result_bo::is_two_quali_all($route_type) && ($route_order == 1 ||
				$quali_overall == 2 && $route_order == 3) ||	// Group B
				$route_type == THREE_QUALI_ALL_NO_STAGGER && $route_order <= 2)
			{
				// only order are the quali-points, same SQL as for the previous "heat" of route_order=2=Final
				$product = '('.$this->_sql_rank_prev_heat(1+$route_order, $route_type, $quali_overall, null, null, $discipline).')';
				$extra_cols[] = "$product AS quali_points";
				$order_bys = array('quali_points');
				if ($route_type == TWO_QUALI_ALL_SUM) $order_bys[0] .= ' DESC';
			}
			// combined final (-6 speed final overall)
			elseif ($route_order == -6)
			{
				$product = '('.$this->_sql_rank_prev_heat(-6, $route_type, $quali_overall, $route_names, $keys).')';
				$extra_cols[] = "$product AS final_points";
				$order_bys[] = 'final_points IS NULL,final_points';
				$no_more_heats = true;
			}
			elseif (!$no_more_heats)
			{
				$order_bys[] = "r$ro.result_rank";
			}
			// not participating in one qualification (order 0 or 1) of TWO_QUALI_ALL is ok
			if (!$quali_overall && (!in_array($route_type, array(TWO_QUALI_ALL, TWO_QUALI_ALL_NO_COUNTBACK, THREE_QUALI_ALL_NO_STAGGER)) ||
				$route_order >= 2 && $route_type != THREE_QUALI_ALL_NO_STAGGER ||
				$route_order >= 3) || $quali_overall == 6)
			{
				$order_bys[] = "r$ro.result_rank IS NULL";
			}
		}
		$order_by = implode(',',array_reverse($order_bys));
		if ($this->isRelay)
		{
			$order_by .= ',RelayResults.team_nation ASC,RelayResults.team_name ASC';
		}
		else
		{
			$order_by .= ',nachname ASC,vorname ASC';
		}
		$extra_cols[] = $this->table_name.'.*';		// trick so_sql to return the cols from the quali as regular cols

		// fix combined general result again to contain route_names[5] == 'Speed final'
		if (isset($route_names[-6]))
		{
			$route_names = array_slice($route_names, 0, 3, true)+array(5 => $route_names[-6])+array_slice($route_names, 4, 2, true);
		}

		//error_log(__METHOD__."() join=$join, order_by=$order_by, extra_cols=".array2string($extra_cols));
		return $join;
	}

	/**
	 * changes the data from the db-format to our work-format
	 *
	 * @param array $data if given works on that array and returns result, else works on internal data-array
	 * @return array
	 */
	function db2data($data=0)
	{
		// hack to give the ranking translation of 'Top' to 'Top' precedence over the etemplate one 'Oben'
		if ($GLOBALS['egw_info']['user']['preferences']['common']['lang'] == 'de') $GLOBALS['egw']->translation->lang_arr['top'] = 'Top';

		static $plus2string = array(
			-1 => '-',
			0  => " ",
			1  => '+',
		);
		if (!is_array($data))
		{
			$data =& $this->data;
		}

		// make sure all includes profile!
		if ($data['acl'] & ranking_athlete::ACL_DENY_ALL) $data['acl'] |= ranking_athlete::ACL_DENY_PROFILE;

		if (!$data['discipline'])	// get's only set by search method
		{
			if ($data['result_height'] || $data['result_height1'])	// lead result
			{
				$data['discipline'] = 'lead';
			}
			elseif (array_key_exists('result_time_1',$data))	// speed relay
			{
				$data['discipline'] = 'speedrelay';
			}
			elseif ($data['result_time'])	// speed result
			{
				$data['discipline'] = 'speed';
			}
			elseif ($data['result_detail'])
			{
				$data['discipline'] = 'boulder';
			}
		}
		// "unpack" result-details, only if we are NO general result, as it messes up the general result
		if ($data['result_detail'] && (!$data['general_result'] || $data['discipline'] == 'selfscore'))
		{
			$data['result_detail'] = self::unserialize($data['result_detail']);

			foreach($data['result_detail'] as $name => $value)
			{
				$data[$name] = $value;
			}
			unset($data['result_detail']);
		}
		// combined speed final needs qualification times
		if (!empty($data['quali_details']))
		{
			$data['quali_details'] = self::unserialize($data['quali_details']);
			$data['quali_time'] = $data['quali_details']['eliminated_l'] === '' &&
				$data['quali_details']['result_time_l'] < $data['quali_details']['result_time_r'] ||
				$data['quali_details']['eliminated_r'] !== '' || empty($data['quali_details']['result_time_r']) ?
				$data['quali_details']['result_time_l'] : $data['quali_details']['result_time_r'];
			$data['quali_time2'] = $data['quali_details']['eliminated_l'] === '' &&
				$data['quali_details']['result_time_l'] < $data['quali_details']['result_time_r'] ||
				$data['quali_details']['eliminated_r'] !== '' || empty($data['quali_details']['result_time_r']) ?
				$data['quali_details']['result_time_r'] : $data['quali_details']['result_time_l'];
		}
		if ($data['discipline'] == 'combined')
		{
			if ($data['general_result'])
			{
				// remove unused data to not have to send it to clients
				unset($data['result_time1'], $data['result_time2'], $data['result_time7'], $data['result_time8']);
				unset($data['result_top'], $data['result_top2'], $data['result_top3'], $data['result_top4'], $data['result_top5'], $data['result_top7']);
				unset($data['result_zone'], $data['result_zone2'], $data['result_zone3'], $data['result_zone4'], $data['result_zone5'], $data['result_zone7']);
				unset($data['result_height'], $data['result_height1'], $data['result_height3'], $data['result_height4'], $data['result_height5'], $data['result_height6']);
				unset($data['result_plus'], $data['result_plus1'], $data['result_plus3'], $data['result_plus4'], $data['result_plus5'], $data['result_plus6']);
				$data_speed = $this->db2data(array('discipline' => 'speed')+$data);
				$data_boulder = $this->db2data(array('discipline' => 'boulder2018')+$data);
			}
			else
			{
				$data['discipline'] = ranking_result_bo::combined_order2discipline($data['route_order']);
			}
		}
		switch($data['discipline'])
		{
			default:
			case 'lead':
				if ($data['result_height'] || $data['result_height1'] || $data['result_height2'] ||	// lead result
					$data['general_result'] && in_array($data['route_type'], array(TWO_QUALI_GROUPS,THREE_QUALI_ALL_NO_STAGGER)))
				{
					// commented as combined produces without sqrt bigger numbers, not sure why it was in here anyway
					//if ($data['quali_points'] > 999) $data['quali_points'] = '';	// 999 = sqrt(999999)
					foreach($data['general_result'] ? array(1,2,3,'',0,4,5,6,7) : array('') as $suffix)
					{
						$to_suffix = $suffix;
						if ($data['general_result'] && $suffix === '' && $data['route_order'] > 0)
						{
							$to_suffix = $data['route_order'];
						}
						elseif($suffix === 0)
						{
							$to_suffix = '';
						}
						// labels for modus "boulder: height, tries"
						if ($data['result_plus'.$suffix] > 1)
						{
							$data['result_height'.$to_suffix] = 0.001 * $data['result_height'.$suffix];
							$data['result'.$suffix] = $data['result_height'.$suffix] >= 999 ?
								lang('Top') : $data['result_height'.$suffix];
							$data['result'.$suffix] .= "\u{00A0}".(100-$data['result_plus'.$suffix]).'.';
						}
						elseif ($data['result_height'.$suffix] == self::TOP_HEIGHT)
						{
							$data['result_height'.$to_suffix] = '';
							$data['result_plus'.$to_suffix]   = self::TOP_PLUS;
							$data['result'.$to_suffix] = lang('Top');
						}
						elseif ($data['result_height'.$suffix])
						{
							$data['result_height'.$to_suffix] = 0.001 * $data['result_height'.$suffix];
							//$data['result'.$to_suffix] = sprintf('%4.2lf',$data['result_height'.$suffix]).
							$data['result'.$to_suffix] = $data['result_height'.$to_suffix].
								$plus2string[$data['result_plus'.$suffix]];
							$data['result_plus'.$to_suffix] = $data['result_plus'.$suffix];
						}
						elseif(($suffix === '' || $suffix == 1 ) && $data['result_height2'] &&
							$data['route_type'] != TWO_QUALI_HALF && $data['route_type'] != THREE_QUALI_ALL_NO_STAGGER)
						{
							$data['result'.$to_suffix] = lang('Prequalified');
						}
						if ($suffix !== $to_suffix)
						{
							if (isset($data['result_rank'.$suffix])) $data['result_rank'.$to_suffix] = $data['result_rank'.$suffix];
							unset($data['result_rank'.$suffix]);
							unset($data['result_height'.$suffix]);
							unset($data['result_plus'.$suffix]);
						}
					}
					// general result with quali on two routes for all --> add rank to result
					if ($data['general_result'] && in_array($data['route_type'], array(TWO_QUALI_ALL,TWOxTWO_QUALI,TWO_QUALI_ALL_NO_COUNTBACK,THREE_QUALI_ALL_NO_STAGGER,TWO_QUALI_GROUPS)))
					{
						foreach(in_array($data['route_type'], array(TWOxTWO_QUALI,TWO_QUALI_GROUPS)) ? array('',1,2,3) :
							($data['route_type'] == THREE_QUALI_ALL_NO_STAGGER ? array('',1,2,7) : array('',1)) as $suffix)
						{
							if ($data['result_rank'.$suffix] && ($data['result'.$suffix] || $data['route_type'] == THREE_QUALI_ALL_NO_STAGGER))
							{
								$data['result'.$suffix] .= "\u{00A0}\u{00A0}".$data['result_rank'.$suffix].'.';
							}
						}
					}
					if ($data['general_result'] && $data['route_type'] == TWO_QUALI_GROUPS && !empty($data['result_rank']))
					{
						$detail = json_decode($data['result_detail'], true);
						$data['route_order'] += 5;	// -4/-5 --> 0/1
						if (!($suffix = $data['route_order'])) $suffix = '';
						$data['result'.$suffix] = $data['result_rank'].'. ['.sprintf('%4.2lf',$detail['quali_points']).']';
						if ($suffix !== '')
						{
							$data['result_rank'.$suffix] = $data['result_rank'];
							unset($data['result_rank']);
						}
					}
					// lead time
					if ($data['result_time'])
					{
						$data['result_time'] /= 1000;
					}
				}
				if ($data['qoints'] && $data['result_rank'] && !$data['general_result'])
				{
					$data['result'] .= "\u{00A0}\u{00A0}".sprintf('%4.2lf',$data['qoints']);
				}
				if ($data['other_detail'])
				{
					$data['other_detail'] = self::unserialize($data['other_detail']);
				}
				if ($data['rank_prev_heat'] && $data['route_type'] == TWOxTWO_QUALI && in_array($data['route_order'],array(2,3)))
				{
					$data['rank_prev_heat'] = self::unserialize($data['rank_prev_heat']);
					$data['rank_prev_heat'] = sprintf('%4.2lf',$data['rank_prev_heat']['qoints']);
				}
				if ($data['ability_percent'] && $data['result_height'])
				{
					$data['result_height'] /= 100.0/$data['ability_percent'];
				}

				if ($data['discipline'] != 'combined' || !$data['general_result']) break;

				// from here on we have combined general result
				foreach(array('', 3, 4, 5) as $suffix)
				{
					if ($data['result_rank'.$suffix])
					{
						if ($suffix == 3 && !isset($data_speed['result'.$suffix])) $data_speed['result'.$suffix] = $data_speed['result'];
						$data['result'.$suffix] = $data_speed['result'.$suffix] . "\u{00A0}\u{00A0}".$data['result_rank'.$suffix].'.';
					}
				}
				foreach(array(1, 6) as $suffix)
				{
					if ($data['result_rank'.$suffix])
					{
						$data['result'.$suffix] = $data_boulder['result'.$suffix] . "\u{00A0}\u{00A0}".$data['result_rank'.$suffix].'.';
					}
				}
				if (isset($data['final_points'])) $data['final_points'] = sprintf('%4.2lf', $data['final_points']);
				break;

			case 'selfscore':
				$data['result'] = count($data['score']);
				// selfscore with fixed number of points per boulder distributed to all reaching top
				// comment as it will not allow to have 99/100 boulders
				//if ($data['result_top'] != 100*$data['result']-$data['result'])
				{
					$data['result'] = number_format($data['result_top']/100, 2, '.', '');
				}
				// fall through, as final for selfscore is always boulder
			case 'boulder':
			case 'boulder2018':
				for($i=1; $i <= self::MAX_BOULDERS; ++$i)
				{
					$data['boulder'.$i] = ($data['top'.$i] ? 't'.$data['top'.$i].' ' : '').
						((string)$data['zone'.$i] !== '' ? ($data['discipline'] === 'boulder2018' ? 'z' : 'b').$data['zone'.$i] : '');
				}
				$suffix = $data['discipline'] == 'selfscore' ? 2 : '';	// general result can have route_order as suffix
				while (isset($data['result_zone'.$suffix]) || $suffix < 2 || isset($data['result_zone'.(1+$suffix)]) ||
					$data['general_result'] && $suffix < 6)	// combined general result has boulder final with suffix 6
				{
					if (isset($data['result_zone'.$suffix]))
					{
						$to_suffix = $suffix;
						if ($data['general_result'] && $suffix === '' && $data['route_order'])
						{
							$to_suffix = $data['route_order'];
						}
						$tops = round($data['result_top'.$suffix] / 100);
						$top_tries = $tops ? $tops * 100 - $data['result_top'.$suffix] : '';
						$zones = abs(round($data['result_zone'.$suffix] / 100));
						$zone_tries = $data['result_zone'.$suffix] ? $zones * 100 - $data['result_zone'.$suffix] : '';
						// boulder without problem specific results (route_num_problems=0)
						if (!$suffix && !isset($data['zone1']))
						{
							$data['tops'] = $tops;
							$data['top_tries'] = $top_tries;
							$data['zones'] = $zones;
							$data['zone_tries'] = $zone_tries;
						}
						if ($data['discipline'] === 'boulder2018')
						{
							$data['result'.$to_suffix] = $tops.'T'.$zones.'z'."\u{00A0}".(int)$top_tries."\u{00A0}".(int)$zone_tries;
						}
						else
						{
							$data['result'.$to_suffix] = $tops.'t'.$top_tries."\u{00A0}".$zones.'b'.$zone_tries;
						}
						if ($suffix !== $to_suffix)
						{
							$data['result_rank'.$to_suffix] = $data['result_rank'.$suffix];
							unset($data['result_rank'.$suffix]);
						}
						if (!$data['route_order']) $data['result_rank0'] = $data['result_rank'];
					}
					++$suffix;
				}
				break;

			case 'speedrelay':
				for($i = 1; $i <= 3; ++$i)
				{
					$col = 'result_time_'.$i;
					if ((string)$data[$col] != '')
					{
						$data[$col] = sprintf('%4.2lf',$data[$col]*0.001);
					}
				}
				// fall through
			case 'speed':
				if ($data['result_time'] || $data['eliminated'] || $data['eliminated_r'] || $data['false_start'])	// speed result
				{
					if ($data['false_start'] > self::MAX_FALSE_STARTS)
					{
						$data['time_sum'] = $data['false_start'] > 1 ?
							lang('%1. false start', $data['false_start']) : lang('false start');
						if ($data['result_time_l'] || $data['eliminated_l'])
						{
							$data['eliminated_r'] = ranking_result_bo::ELIMINATED_FALSE_START;
						}
						else
						{
							$data['eliminated_l'] = ranking_result_bo::ELIMINATED_FALSE_START;
							$data['eliminated'] = ranking_result_bo::ELIMINATED_FALSE_START;
							$data['result'] = $data['time_sum'];
							$data['result_time'] = null;
						}
					}
					if (!$data['general_result'])
					{
						if ($data['result_time'])
						{
							if ($data['result_time'] == 1000*self::ELIMINATED_TIME)
							{
								$data['eliminated'] = ranking_result_bo::FALL;
								$data['result_time'] = null;
								$data['result'] = lang('fall');
								if (!isset($data['time_sum'])) $data['time_sum'] = $data['result'];
							}
							elseif ($data['result_time'] == 1000*self::WILDCARD_TIME)
							{
								$data['eliminated'] = 0;
								$data['result_time'] = null;
								$data['time_sum'] = $data['result'] = lang('Wildcard');
							}
							else
							{
								$data['result_time'] *= 0.001;
								$data['time_sum'] = $data['result'] = sprintf('%4.3lf',$data['result_time']);
							}
						}
						if ($data['result_time_r'] || isset($data['eliminated_r']))	// speed with two goes
						{
							// do not overwrite false start for final or quali on single route
							if ($data['false_start'] <= self::MAX_FALSE_STARTS || $data['result_time_l'] && (string)$data['eliminated_l'] === '')
							{
								$data['result'] = (string)$data['eliminated_l'] === '' ? sprintf('%4.3lf',$data['result_time_l']) :
									($data['eliminated_l'] ? lang('fall') : lang('Wildcard'));
							}
							$data['result_time'] = $data['result_time_l'];
							$data['eliminated'] = $data['eliminated_l'];
							switch((string)$data['eliminated_r'])
							{
								case '':
									$data['result_r'] = $data['result_time_r'] ? sprintf('%4.3lf',$data['result_time_r']) : '';
									break;
								case ranking_result_bo::ELIMINATED_FALSE_START:
									$data['result_r'] = $data['time_sum'];
									break;
								default:
									$data['result_r'] = lang('fall');
									break;
							}
						}
					}
					else
					{
						$suffix = '';	// general result can have route_order as suffix
						while (isset($data['result_time'.$suffix]) || $suffix < 2 || isset($data['result_time'.(1+$suffix)]) ||
							$suffix < 1+$data['route_order'] || isset($data['result_time5']) && $suffix < 5)
						{
							if ($data['result_time'.$suffix])
							{
								$data['result_time'.$suffix] *= 0.001;
								if ($data['result_time'.$suffix] == self::ELIMINATED_TIME)
								{
									$detail = self::unserialize($data['result_detail'.$suffix]);
									$data['result'.$suffix] = $detail['false_start'] > self::MAX_FALSE_STARTS ?
										($data['false_start'] > 1 ? lang('%1. false start', $data['false_start']) : lang('false start')) : lang('fall');
								}
								elseif ($data['result_time'.$suffix] == self::WILDCARD_TIME ||
									$data['result_time'.$suffix] == 1000.0*self::WILDCARD_TIME)	// Wildcard in combined general result
								{
									$data['result'.$suffix] = lang('Wildcard');
								}
								else
								{
									$data['result'.$suffix] = sprintf('%4.3lf',$data['result_time'.$suffix]);
								}
							}
							++$suffix;
						}
						if (!$data['route_order']) $data['result_rank0'] = $data['result_rank'];
					}
				}
				if ($data[$this->id_col] < 0)	// Wildcard
				{
					$data['nachname'] = '-- '.lang('Wildcard').' --';
				}
				break;
		}
		$this->_shorten_name($data['nachname']);
		$this->_shorten_name($data['vorname']);

		if ($data['geb_date']) $data['birthyear'] = (int)$data['geb_date'];

		return $data;
	}

	/**
	 * Unserialize data from either json_encode or serialize
	 *
	 * As of 1.9.017 update, we should only get json_encode data.
	 *
	 * @param string $data
	 * @return array
	 */
	public static function unserialize($data)
	{
		return !$data ? array() : ($data[0] == '{' ? json_decode($data, true) : unserialize($data));
	}

	/**
	 * Appriviate the name to make it better printable
	 *
	 * @param string &$name
	 * @param int $max =12 maximum length
	 */
	private function _shorten_name(&$name,$max=13)
	{
		if (strlen($name) <= $max) return;

		// add a space after each dash or comma, if there's none already
		if (true) $name = preg_replace('/([-,]+ *)/','\\1 ',$name);

		// check all space separated parts for their length
		$parts = explode(' ',$name);
		foreach($parts as &$part)
		{
			if (strlen($part) > $max)
			{
				$part = substr($part,0,$max-1).'.';
			}
		}
		$name = implode(' ',$parts);
	}

	/**
	 * changes the data from our work-format to the db-format
	 *
	 * @param array $data if given works on that array and returns result, else works on internal data-array
	 * @return array
	 */
	function data2db($data=0)
	{
		if (!is_array($data))
		{
			$data =& $this->data;
		}
		if ($data['result_plus'] == self::TOP_PLUS)	// top
		{
			$data['result_height'] = self::TOP_HEIGHT;
			$data['result_plus']   = 0;
		}
		elseif ($data['result_height'])
		{
			$data['result_height'] = round(1000 * $data['result_height']);
		}
		else
		{
			unset($data['result_plus']);	// no plus without height
		}
		// time for lead is handled by speed, further down

		// speed
		if ($data['result_time_r'] || isset($data['eliminated_r']) || $data['ability_percent'])	// result on 2. speed route
		{
			$data['result_detail'] = array(
				'result_time_l' => $data['result_time'] ? number_format($data['result_time'],3) : '',
				'eliminated_l'  => $data['eliminated'],
				'result_time_r' => $data['result_time_r'] ? number_format($data['result_time_r'],3) : '',
				'eliminated_r'  => $data['eliminated_r'],
			);
			// speed with record format (best of two) AND not eliminated both times
			if ($this->route_type == TWO_QUALI_BESTOF &&	// best of mode is quali AND final!
				!((string)$data['eliminated'] !== '' && $data['eliminated_r']))
			{
				if ($data['result_time'] && $data['result_time_r'])
				{
					$data['result_time'] = round(1000 * (min($data['result_time'],$data['result_time_r'])));
				}
				elseif($data['result_time_r'])
				{
					$data['result_time'] = round(1000 * $data['result_time_r']);
				}
				elseif($data['result_time'])
				{
					$data['result_time'] = round(1000 * $data['result_time']);
				}
				elseif ($data['eliminated'] || $data['eliminated_r'])
				{
					$data['result_time'] = round(1000 * self::ELIMINATED_TIME);
				}
			}
			elseif ((string)$data['eliminated'] !== '' || $data['eliminated_r'])
			{
				$data['result_time'] = round(1000 * ((string)$data['elimitated'] !== '' ?
					($data['eliminated'] ? self::ELIMINATED_TIME : self::WILDCARD_TIME) : self::ELIMINATED_TIME));
			}
			else
			{
				$data['result_time'] = $data['result_time']+$data['result_time_r'] ?
					round(1000 * ($data['result_time']+$data['result_time_r'])) : null;

				if ($data['ability_percent'] && !is_null($data['result_time']))
				{
					$data['result_time'] = round($data['ability_percent']*$data['result_time']/100.0);
				}
			}
		}
		// speed relay, todo: eliminated
		elseif (isset($data['result_time_1']))
		{
			$data['result_time'] = null;
			for($i = 1; $i <= 3; ++$i)
			{
				if ($data['result_time_'.$i])
				{
					$data['result_time_'.$i] = round(1000 * $data['result_time_'.$i]);
					$data['result_time'] += $data['result_time_'.$i];
				}
			}
			if ((string)$data['eliminated'] !== '' && !($data['result_time'] && !$data['eliminated']))
			{
				$data['result_time'] = round(1000 * ((string)$data['elimitated'] !== '' ?
					($data['eliminated'] ? self::ELIMINATED_TIME : self::WILDCARD_TIME) : self::ELIMINATED_TIME));
			}
		}
		elseif ($data['result_time'] || isset($data['eliminated']))	// speed with result on only one route
		{
			$data['result_detail'] = array(
				'result_time_l' => $data['result_time'] ? number_format($data['result_time'],3) : '',
				'eliminated_l'  => $data['eliminated'],
			);
			switch((string)$data['eliminated'])
			{
				case '1': $data['result_time'] = self::ELIMINATED_TIME; break;
				case '0': $data['result_time'] = self::WILDCARD_TIME; break;
			}
			if ($data['result_time'])
			{
				$data['result_time'] = round(1000 * $data['result_time']);
			}
		}
		if ($data['false_start'] > 0)
		{
			$data['result_detail']['false_start'] = (int)$data['false_start'];

			if ($data['false_start'] > self::MAX_FALSE_STARTS)
			{
				$data['result_time'] = round(1000 * self::ELIMINATED_TIME);
			}
		}
		// saving the boulder results, if there are any
		if (isset($data['zone1']) || isset($data['zone2']) || isset($data['zone3']) || isset($data['zone4']) ||
			isset($data['zone5']) || isset($data['zone6']) || isset($data['zone7']) || isset($data['zone8']) ||
			isset($data['zone9']) || isset($data['zone10']))
		{
			$data['result_top'] = $data['result_zone'] = $data['result_detail'] = null;
			for($i = 1; $i <= self::MAX_BOULDERS; ++$i)
			{
				if ($data['top'.$i])
				{
					$data['result_top'] += 100 - $data['top'.$i];
					$data['result_detail']['top'.$i] = $data['top'.$i];
					// cant have top without zone or more tries for the zone --> setting zone as top
					//if (!$data['zone'.$i] || $data['zone'.$i] > $data['top'.$i]) $data['zone'.$i] = $data['top'.$i];
				}
				if (is_numeric($data['zone'.$i]))
				{
					if ($data['zone'.$i])
					{
						$data['result_zone'] += 100 - $data['zone'.$i];
					}
					elseif (is_null($data['result_zone']))
					{
						$data['result_zone'] = 0;		// this is to recognice climbers with no zone at all
					}
					$data['result_detail']['zone'.$i] = $data['zone'.$i];
				}
				if (!empty($data['try'.$i]))
				{
					$data['result_detail']['try'.$i] = $data['try'.$i];
				}
			}
		}
		// boulder result with just the sums (route_num_problems=0)
		elseif (isset($data['tops']) || array_key_exists('score', $data))
		{
			$data['result_zone'] = $data['result_top'] = null;
			if (is_numeric($data['zones']))
			{
				$data['result_zone'] = 100 * $data['zones'] - $data['zone_tries'];
				if ($data['tops'] > 0)
				{
					$data['result_top'] = 100 * $data['tops'] - $data['top_tries'];
				}
			}
			unset($data['result_detail']);	// do NOT store existing problem specific results
			if ($data['score']) $data['result_detail'] = array('score' => $data['score']);
		}
		if ((float)$data['ability_percent'])
		{
			if ($data['result_height']) $data['result_height'] *= 100.0/$data['ability_percent'];
			$data['result_detail']['ability_percent'] = $data['ability_percent'];
		}
		if (isset($data['ranking'])) $data['result_detail']['ranking'] = $data['ranking'];

		// store checked state in result_details
		if (isset($data['checked'])) $data['result_detail']['checked'] = $data['checked'];

		if (is_array($data['result_detail'])) $data['result_detail'] = json_encode($data['result_detail']);

		return $data;
	}

	/**
	 * merges in new values from the given new data-array
	 *
	 * Reimplemented to also merge top1-MAX_BOULDERS and zone1-MAX_BOULDERS
	 *
	 * @param $new array in form col => new_value with values to set
	 */
	function data_merge($new)
	{
		parent::data_merge($new);

		for($i = 1; $i <= self::MAX_BOULDERS; ++$i)
		{
			if (isset($new['try'.$i])) $this->data['try'.$i] = $new['try'.$i];
			if (isset($new['zone'.$i])) $this->data['zone'.$i] = $new['zone'.$i];
			if (isset($new['top'.$i])) $this->data['top'.$i] = $new['top'.$i];
		}
		foreach(array('eliminated','result_time_r','eliminated_r','tops','top_tries','zones','zone_tries','ability_percent','checked','score','false_start') as $name)
		{
			if (array_key_exists($name, $new)) $this->data[$name] = $new[$name];
		}
	}

	/**
	 * Update the ranking of a given route
	 *
	 * @param array $_keys values for keys WetId, GrpId and route_order
	 * @param int $route_type =ONE_QUALI ONE_QUALI, TWO_QUALI_HALF, TWO_QUALI_ALL
	 * @param string $discipline ='lead' 'lead', 'speed', 'boulder', 'speedrelay'
	 * @param int $quali_preselected =0 preselected participants for quali --> no countback to quali, if set!
	 * @param boolean $use_time =false important for lead where we use time now in final, if tied after countback
	 * @param int $selfscore_points =null point distributed per boulder, or null if 1 for each top
	 * @param array $route =null whole route array, important for combined
	 * @return int|boolean updated rows or false on error (no route specified in $keys)
	 */
	function update_ranking($_keys,$route_type=ONE_QUALI,$discipline='lead',$quali_preselected=0,$use_time=null,$selfscore_points=null,array $route=null)
	{
		//error_log(__METHOD__.'('.array2string($_keys).", route_type=$route_type, '$discipline', quali_preselcted=$quali_preselected, use_time=$use_time, selfscore_points=$selfscore_points, ".array2string($route).")");
		if (!$_keys['WetId'] || !$_keys['GrpId'] || !is_numeric($_keys['route_order'])) return false;

		$keys = array_intersect_key($_keys,array('WetId'=>0,'GrpId'=>0,'route_order'=>0));	// remove other content

		$extra_cols = array();
		switch($discipline)
		{
			default:
			case 'lead':
				$mode = $this->rank_lead;
				$order_by = 'result_height IS NULL,new_rank ASC';
				$extra_cols[] = 'result_detail';
				break;
			case 'speedrelay':
			case 'speed':
				$order_by = 'result_time IS NULL,new_rank ASC';
				$extra_cols[] = 'result_time';
				if ($keys['route_order'] < 2)
				{
					$mode = str_replace('RouteResults',$this->table_name,$this->rank_speed_quali);
				}
				else
				{
					/* 2018 combined final include small final as first 2 starters
					// (could also be used for speed to save one column in general result)
					if ($route && $route['discipline'] == 'combined' && $keys['route_order'] == 5)
					{
						$mode = str_replace('RouteResults',$this->table_name,$this->rank_speed_combi_final);
						$order_by = "result_time IS NULL,$mode,result_time";
					}*/
					// 2019+ combined final (including loosers in all heats)
					if ($route && $route['discipline'] == 'combined' && $keys['route_order'] > 3)
					{
						$mode = '('.str_replace('RouteResults', $this->table_name,
							($keys['route_order'] == 5 ? $this->rank_speed_combi_final : $this->rank_speed_combi_semi_final)).
							'+'.str_replace('RouteResults', $this->table_name, $this->rank_speed_final).')';
						$order_by = "result_time IS NULL,$mode,result_time";
					}
					else
					{
						$mode = str_replace('RouteResults',$this->table_name,$this->rank_speed_final);
						// ORDER BY CASE column-alias does NOT work with MySQL 5.0.22-Debian_Ubuntu6.06.6, it works with 5.0.51a-log SUSE
						//$order_by = 'result_time IS NULL,CASE new_rank WHEN 1 THEN 0 ELSE result_time END ASC';
						$order_by = "result_time IS NULL,CASE ($mode) WHEN 1 THEN 0 ELSE result_time END ASC";
					}

					// speed final used time(s) from speed qualification
					$extra_cols[] = '('.str_replace('result_detail','result_time',
						$this->_sql_rank_prev_heat($keys['route_order'], 'speed-final')).') AS quali_time';
					$extra_cols[] = 'start_order';	// needed to identify the pairings
					$order_by .= ',(start_order-1) DIV 2,quali_time';	// order tied pairs by quali_time
				}
				break;
			case 'boulder2018':
				$extra_cols[] = $this->boulder_points('result_top', 'result_zone').' AS boints';
				$mode = $this->rank_boulder_points();
				$order_by = 'boints DESC';
				break;
			case 'boulder':
			case 'selfscore':
				$mode = $this->rank_boulder;
				$order_by = 'result_top IS NULL,result_top DESC,result_zone IS NULL,result_zone DESC';
		}
		$extra_cols[] = $mode.' AS new_rank';

		// do we have a countback
		if ($quali_preselected && $keys['route_order'] == 2 || $route_type == TWO_QUALI_ALL_NO_COUNTBACK ||
			$route_type == TWO_QUALI_GROUPS && $keys['route_order'] == 4 ||
			$route_type == TWO_QUALI_GROUPS && $keys['route_order'] < 4 ||
			$route && $route['discipline'] == 'combined' && $keys['route_order'] < 3)
		{
			// no countback to quali, if we use preselected athletes or TWO_QUALI_ALL_NO_COUNTBACK
		}
		elseif ($route_type == TWOxTWO_QUALI && $keys['route_order'] <= 4)	// 2x2 til 1/2-Final incl.
		{
			if (in_array($keys['route_order'],array(2,3)))
			{
				$extra_cols[] = '('.$this->_sql_rank_prev_heat($keys['route_order'],$route_type).') AS other_detail';
			}
		}
		// combined lead and boulder finals have countback to their disciplines qualification
		elseif ($route && $route['discipline'] == 'combined' && in_array($keys['route_order'], array(6, 7)))
		{
			// combined lead final uses time BEFORE countback to qualification
			if ($use_time) $order_by .= ',result_time ASC';
			$extra_cols[] = '('.$this->_sql_rank_prev_heat($keys['route_order'], 'combined').') AS rank_prev_heat';
			$order_by .= ',rank_prev_heat ASC';
		}
		// speed finals use countback to speed qualification
		elseif ($discipline == 'speed' && $keys['route_order'] >= 2)
		{
			$extra_cols[] = '('.$this->_sql_rank_prev_heat($keys['route_order'], 'speed-final').') AS quali_details';
		}
		elseif (substr($discipline,0,5) != 'speed' && $keys['route_order'] >= (2+(int)($route_type == TWO_QUALI_HALF)))
		{
			$extra_cols[] = '('.$this->_sql_rank_prev_heat($keys['route_order'],$route_type).') AS rank_prev_heat';
			$order_by .= ',rank_prev_heat ASC';
		}
		// lead countback
		elseif ($discipline == 'lead')
		{
			// quali points are to be displayed with 2 digits for 2008 (but all digits counting)
			if ($keys['route_order'] == 2 && in_array($route_type, array(TWO_QUALI_ALL,TWO_QUALI_ALL_NO_COUNTBACK)) ||
				$keys['route_order'] == 3 && $route_type == THREE_QUALI_ALL_NO_STAGGER)
			{
				$extra_cols[] = 'ROUND(('.$this->_sql_rank_prev_heat($keys['route_order'],$route_type).'),2) AS rank_prev_heat';
				$order_by .= ',rank_prev_heat ASC';
			}
			elseif ($keys['route_order'] >= 2+(int)($route_type == TWO_QUALI_HALF) && $route_type != THREE_QUALI_ALL_NO_STAGGER ||
				$keys['route_order'] >= 3 && $route_type == THREE_QUALI_ALL_NO_STAGGER)
			{
				$extra_cols[] = '('.$this->_sql_rank_prev_heat($keys['route_order'],$route_type).') AS rank_prev_heat';
				$order_by .= ',rank_prev_heat ASC';
			}
		}
		// lead final use time, after regular countback
		if ($use_time)
		{
			$order_by .= ',result_time ASC';
			$extra_cols[] = 'result_time';
		}
		//error_log(__METHOD__.'('.array2string($keys).", $route_type, '$discipline', $quali_preselected) extra_cols=".array2string($extra_cols).", order_by=$order_by");
		$this->db->transaction_begin();
		$join = '';//'JOIN Personen USING(PerId)'; $extra_cols[] = 'Nachname'; $extra_cols[] = 'Vorname';
		$result = $this->search($keys, $this->id_col.',result_rank,result_detail AS detail',
			'ORDER BY '.$order_by.' FOR UPDATE', $extra_cols, '', false, 'AND', false, null, $join);

		if (in_array($route_type, array(TWO_QUALI_ALL,TWO_QUALI_ALL_NO_COUNTBACK)) && $keys['route_order'] < 2 ||		// calculate the points
			$route_type == TWOxTWO_QUALI && $keys['route_order'] < 4)
		{
			$places = array();
			foreach($result as &$data)
			{
				$places[$data['new_rank']]++;
			}
			foreach($result as &$data)
			{
				// points for place r with c ex aquo: p(r,c) = (c+2r-1)/2
				$data['new_qoints'] = ($places[$data['new_rank']] + 2*$data['new_rank'] - 1)/2;
				if ($data['other_detail'])
				{
					$data['new_quali_points'] = $data['new_qoints'] ? sprintf('%4.2lf',round(sqrt($data['other_detail']['qoints'] * $data['new_qoints']),2)) :
						$data['other_detail']['qoints']+10000;
				}
			}
			if ($route_type == TWOxTWO_QUALI && in_array($keys['route_order'],array(2,3)))
			{
				usort($result, function($a, $b) {
					return round(100*$a['new_quali_points']) - round(100*$b['new_quali_points']);
				});
				$old = null;
				foreach($result as $i => &$data)
				{
					$data['new_rank'] = $old['new_rank'];
					if (!$old || $old['new_quali_points'] < $data['new_quali_points'])
					{
						$data['new_rank'] = $i+1;
					}
					$old = $data;
				}
			}
		}
		// if fixed number of points are distributes for a single boulder
		if ($discipline == 'selfscore' && $selfscore_points)
		{
			self::calc_selfscore_points($result, $selfscore_points);
		}
		// final with 2018+ boulder rules --> apply tie breaking
		if ($discipline === 'boulder2018' && !$route['route_quota'])
		{
			$this->boulder2018_final_tie_breaking($result, $keys, $route['route_num_problems'],
				// for regular boulder final (and previous heat is on one route), we have to do countback to previous heat first
				!($route && $route['discipline'] === 'combined') && ($route['route_order'] > 2 || $route['route_type'] == ONE_QUALI));
		}
		// speed final tie breaking by countback to speed qualification
		if ($discipline == 'speed' && $keys['route_order'] >= 2)
		{
			$this->speed_final_tie_breaking($result, $keys, $route && $route['discipline'] === 'combined');
		}
		// speed quali tie breaking by using 2. time
		if ($discipline == 'speed' && $keys['route_order'] == 0)
		{
			$this->speed_quali_tie_breaking($result, $keys);
		}
		$modified = 0;
		$old_time = $old_prev_rank = null;
		$old_rank = $old_speed_rank = null;
		foreach($result as $i => &$data)
		{
			// for ko-system of speed the rank is only 1 (winner) or 2 (looser)
			if (substr($discipline,0,5) == 'speed' && $keys['route_order'] >= 2 && $data['new_rank'])
			{
				if ($data['eliminated']) $data['time_sum'] = self::ELIMINATED_TIME;
				$new_speed_rank = $data['new_rank'];
				if (!($data['new_rank'] & 1))	// all winners must have rank=1(!)
				{
					$data['new_rank'] = !$old_time || $old_time < $data['time_sum'] ||
						 $old_speed_rank < $new_speed_rank ? $i+1 : $old_rank;
				}
				//echo "<p>$i. $data[$this->id_col]: time=$data[time_sum], last=$old_time, $data[result_rank] --> $data[new_rank]</p>\n";
				$old_time = $data['time_sum'];
				$old_speed_rank = $new_speed_rank;
			}
			//echo "<p>$i. $data[$this->id_col]: prev=$data[rank_prev_heat], $data[result_rank] --> $data[new_rank]</p>\n";
			if ($data['new_rank'] && $data['new_rank'] != $i+1 && $old_prev_rank)	// do we have a tie and a prev. heat
			{
				// use the previous heat to break the tie
				$data['new_rank'] = $old_prev_rank < $data['rank_prev_heat'] ? $i+1 : $old_rank;
				//echo "<p>$i. ".$data[$this->id_col].": prev=$data[rank_prev_heat], $data[result_rank] --> $data[new_rank]</p>\n";
			}
			// break ties in lead finals (already ordered by time)
			if ($data['new_rank'] && $data['new_rank'] != $i+1 && $use_time)
			{
				$data['new_rank'] = $old_time < $data['result_time_l'] ? $i+1 : $old_rank;
				//echo "<p>".($i+1).'. '.$data[$this->id_col].": old_time=$old_time, time=$data[result_time_l] --> $data[new_rank]</p>\n";
			}
			$to_update = array();
			if ($places)	// calculate the quali-points of the single heat
			{
				if ($data['new_qoints'] != $data['qoints'] || $data['new_quali_points'] != $data['quali_points'])
				{
					// keep existing details, like ranking for prequalified
					$to_update['result_detail'] = self::unserialize($data['detail']);
					$to_update['result_detail']['qoints'] = $data['new_qoints'];
					$to_update['result_detail']['quali_points'] = $data['new_quali_points'];
					//echo "<p>qoints: $data[qoints] --> $qoints</p>\n";
					$to_update['result_detail'] = json_encode($to_update['result_detail']);
				}
			}
			// if fixed number of points are distributes for a single boulder, need to update just calculated points
			if ($discipline == 'selfscore' && $selfscore_points)
			{
				$to_update['result_top'] = $to_update['result_zone'] = $data['result_top'];
			}
			if (is_array($data['detail']) && array_key_exists('attempts', $data['detail']))
			{
				$to_update['result_detail'] = json_encode($data['detail']);
			}
			// for lead finals, do not yet store first places, they might have to use the time
			if ($data['new_rank'] != $data['result_rank'])
			{
				$to_update['result_rank'] = $data['new_rank'];
			}
			if ($to_update && $this->db->update($this->table_name,$to_update,$keys+array($this->id_col=>$data[$this->id_col]),__LINE__,__FILE__))
			{
				//_debug_array($to_update+array($this->id_col=>$data[$this->id_col]));
				++$modified;
			}
			$old_prev_rank = $data['rank_prev_heat'];
			$old_time = $data['result_time_l'] ? $data['result_time_l'] : $data['time_sum'];
			$old_rank = $data['new_rank'];
		}
		$this->db->transaction_commit();

		// store Group A/B results in result-table
		if ($route_type == TWO_QUALI_GROUPS && $_keys['route_order'] < 4)
		{
			$this->_store_ranking($_keys, $_keys['route_order'] < 2 ? -5 : -4, $discipline, $route_type);
		}
		// store combined speed final in result-table
		elseif ($route && $route['discipline'] == 'combined' && in_array($_keys['route_order'], array(3,4,5)))
		{
			$this->_store_ranking($_keys, -6, $route['discipline'], $route['route_type']);
		}
		if ($modified) ranking_result_bo::delete_export_route_cache($keys);

		return $modified;
	}

	/**
	 * Return SQL to calculate 2018+ boulder points from combined top/zone columns
	 *
	 * @param string $result_top
	 * @param string $result_zone
	 * @return string SQL
	 */
	function boulder_points($result_top='result_top', $result_zone='result_zone')
	{
		return "(COALESCE(1000000*(($result_top+99) div 100),0)+".
			"10000*(($result_zone+99) div 100)-".
			"COALESCE(100*(100-$result_top mod 100),0)-".
			"(100-$result_zone mod 100))";
	}

	/**
	 * Rank a single boulder heat by 2018+ boulder points
	 *
	 * @return string SQL
	 */
	function rank_boulder_points()
	{
		return "CASE WHEN result_top IS NULL AND result_zone IS NULL THEN NULL ELSE (".
			"SELECT 1+COUNT(*) FROM RouteResults r WHERE RouteResults.WetId=r.WetId AND RouteResults.GrpId=r.GrpId AND RouteResults.route_order=r.route_order AND ".
			$this->boulder_points('RouteResults.result_top', 'RouteResults.result_zone')." < ".
			$this->boulder_points('r.result_top', 'r.result_zone').") END";
	}

	/**
	 * Store ranking in RouteResults table
	 *
	 * This is necessary, as ranking is not done in pure SQL, and we need it's result for place multiplication later.
	 *
	 * @param array $keys
	 * @param int $route_order
	 * @param string $discipline ='lead'
	 * @param int $route_type =TWO_QUALI_GROUPS
	 */
	private function _store_ranking(array $keys, $route_order, $discipline='lead', $route_type=TWO_QUALI_GROUPS)
	{
		$keys['route_order'] = $route_order;
		$keys['discipline'] = $discipline;
		$keys['route_type'] = $route_type;
		$results = $this->search(array(), false, 'result_rank ASC', '', '', false, 'AND', false, $keys);
		unset($results['route_names']);

		$this->db->transaction_begin();
		$this->delete($keys);
		foreach($results as $result)
		{
			$keys['PerId'] = $result['PerId'];
			$result_time = $result_detail = null;
			if ($discipline == 'combined')	// result from combined speed final
			{
				foreach(array(5,4,'') as $suffix)
				{
					if (!empty($result['result_time'.$suffix]))
					{
						$result_time = $result['result_time'.$suffix];
						if ($result_time > 1000 && $result_time != ELIMINATED_TIME) $result_time /= 1000.0;
						break;
					}
				}
			}
			elseif ($result['quali_points'])
			{
				$result_detail = json_encode(array('quali_points' => $result['quali_points']));
			}
			$this->init(array(
				'result_rank'   => $result['result_rank'],
				'result_time'   => $result_time,
				'result_detail' => $result_detail,
			));
			$this->save($keys);
		}
		$this->db->transaction_commit();

		ranking_result_bo::delete_export_route_cache($keys);
	}

	/**
	 * Max. number of attempts to use for tie breaking in final
	 */
	const MAX_ATTEMPTS = 10;

	/**
	 * Apply 2018+ boulder final tie-breaking rules
	 *
	 * New rank is in key 'new_rank' (NOT 'result_rank')!
	 *
	 * @param array& $results on return new_rank and detail[attempts] might be changed (and need storing)
	 * @param array $keys
	 * @param int $num_problems
	 * @param boolean $countback_first =true regular boulder final, false: combined boulder final
	 *
	 * According to Tim Hatch:
	 * In the boulder World Cup, in the final (rule 8.20.B):
	 *
	 * 1/	Rank using T (DESC) then Z (DESC) then TA (ASC) then ZA (ASC)
	 * 2/	If tied after 1/, then countback to the previous round (unless the previous round had 2 groups)
	 * 3/	If tied after 2/, then compare best results (tops)
	 * 4/	If tied after 3/, then compare best results (zones)
	 *
	 * For the final boulder "stage” in a Combined discipline competition, the procedure is modified as follow (rule 11.10.B):
	 *
	 * 1/	Rank using T (DESC) then Z (DESC) then TA (ASC) then ZA (ASC)
	 * 2/	If tied after 1/, then compare best results (tops)
	 * 3/	If tied after 2/, then compare best results (zones)
	 * 4/	If tied after 3/, then countback to the qualification boulder “stage"
	 */
	public function boulder2018_final_tie_breaking(array &$results, array $keys, $num_problems, $countback_first=true)
	{
		// remove "old" attempts, in case they get not written again
		$old_attempts = array();
		$last_result = null;
		foreach($results as $i => &$result)
		{
			if (is_string($result['detail']))
			{
				$result['detail'] = self::unserialize($result['detail']);
				$old_attempts[$result['PerId']] = $result['detail']['attempts'];
				unset($result['detail']['attempts']);
			}
			// for regular (not combined) boulder final, we have to do the countback to 1/2-final first
			if ($countback_first && !empty($result['new_rank']) &&	// but only if competitor has climbed in final!
				$result['new_rank'] == $last_result['new_rank'] &&
				$result['rank_prev_heat'] != $last_result['rank_prev_heat'])
			{
				$result['new_rank'] = $i+1;
			}
			$last_result = $result;
		}
		$input = $results;

		// split only podium
		for($place=1; $place <= 3; ++$place)
		{
			$last_result = null;
			foreach(array('top', 'zone') as $what)
			{
				for($attempt=1; $attempt <= self::MAX_ATTEMPTS; ++$attempt)
				{
					$to_split = array();
					unset($last_result);
					foreach($results as &$result)
					{
						if ($result['new_rank'] < $place) continue;	// keep attempts!
						unset($result['detail']['attempts']);
						if ($result['new_rank'] > $place || $attempt == self::MAX_ATTEMPTS) continue;

						// calculate how many $what (top/zone) are archived in $attempt try
						for($n = 1, $num=0; $n <= $num_problems; ++$n)
						{
							if ($result['detail'][$what.$n] == $attempt) ++$num;
						}
						$result['detail']['attempts'] = $num.' '.$what.'s in '.$attempt.'.';

						$to_split[] =& $result;
					}
					// check if we need tie-breaking on given $place --> continue to zone or next place if not
					if (count($to_split) < 2)
					{
						unset($to_split[0]['detail']['attempts']);
						break 1;
					}
					else
					{
						// sort by highest number of top/zone in given attempt (usort keeps references!)
						usort($to_split, function($a, $b)
						{
							return $b['detail']['attempts'] - $a['detail']['attempts'];
						});
						/* Check if we found a winner, we have multiple cases, eg. for 3 tied:
						 * a) all slit, eg:    0: 2, 1: 1, 2: 0 --> write all and break to next place
						 * b) first split, eg: 0: 2, 1: 1, 2: 1 --> write first, increment places of others!
						 * c) last split, eg:  0: 1, 1: 1, 2: 0 --> write last with $place+=$n
						 * z) none split, eg:  0: 1, 1: 1, 2: 1 --> continue with higher attempt
						 */
						$last_num_attempt = null;
						$split = 0;
						foreach($to_split as $n => &$result)
						{
							// current one is split from next or in case last from the one before --> write it
							if ($n < count($to_split)-1 ?
									(int)$result['detail']['attempts'] !== (int)$to_split[1+$n]['detail']['attempts'] :
									$last_num_attempt !== (int)$result['detail']['attempts'])
							{
								$result['new_rank'] += $n;
								$split++;
								$last_num_attempt = (int)$result['detail']['attempts'];
							}
							// loosers have their place incremented by number of split ones
							else
							{
								$result['new_rank'] += $split;
								$last_num_attempt = (int)$result['detail']['attempts'];
								unset($result['detail']['attempts']);
							}

							foreach($results as &$r)
							{
								if ($r['PerId'] == $result['PerId'])
								{
									$r['new_rank'] = $result['new_rank'];
									$r['detail'] = $result['detail'];
									break;
								}
							}
						}
						// todo: this is not yet correct for case c)!
						if ($split) $place += $split-1;
						// check how many are split
						switch($split)
						{
							case 0:	// none split --> continue with higher attempt
								break;
							case count($to_split):	// all split --> continue to next place
							default:	// some, but not all split --> continue with higher attempt (and maybe different place)
								break 3;
						}
					}
				}
			}
			// no tie-break archived --> remove detail "attempts"
		}

		// sort by new rank
		usort($results, function($a, $b)
		{
			if (!isset($a['new_rank'])) $a['new_rank'] = 99999;
			if (!isset($b['new_rank'])) $b['new_rank'] = 99999;
			return $a['new_rank'] - $b['new_rank'];
		});
		// only return attempts, if they really changed
		foreach($results as &$result)
		{
			if ($old_attempts[$result['PerId']] == $result['detail']['attempts'])
			{
				unset($result['detail']['attempts']);
			}
			else
			{
				//error_log(__METHOD__."() attempts changed from '{$old_attempts[$result['PerId']]}' to '{$result['detail']['attempts']}'");
				if (!isset($result['detail']['attempts'])) $result['detail']['attempts'] = null;	// need to force update by update_ranking
			}
		}
		/* dump results for writing tests, if not running the test ;)
		if ($keys['WetId'])
		{
			file_put_contents($path=$GLOBALS['egw_info']['server']['temp_dir'].'/final-'.$keys['WetId'].'-'.$keys['GrpId'].'.php',
				"<?php\n\n\$input = ".var_export($input, true).";\n\n\$results = ".var_export($results, true).";\n");
			error_log(__METHOD__."() logged input and results to ".realpath($path));
		}*/
		unset($keys, $input);	// suppress warning for not used parameters
	}

	/**
	 * Apply 2018+ speed final tie-breaking rules
	 *
	 * New rank is in key 'new_rank' (NOT 'result_rank')!
	 *
	 * @param array& $results on return new_rank might be changed (and need storing)
	 * @param array $keys
	 * @param boolean $combined =false true for combined
	 */
	public function speed_final_tie_breaking(array &$results, array $keys, $combined=false)
	{
		$input = $results;
		$last = $last_new_rank = $last_pairing = $last_quali_time2 = null;
		foreach($results as $n => &$result)
		{
			$quali_time2 = $result['quali_details']['result_time_l'] == $result['quali_time'] ?
				$result['quali_details']['result_time_r'] : $result['quali_details']['result_time_l'];

			// check if we have a tie in a pairing (other ties wont matter for KO system!)
			if ($last_new_rank && $last_new_rank == $result['new_rank'] && $last_pairing == intdiv($result['start_order']-1, 2))
			{
				// in last final heat (small final and final) we are NOT sorted by result_time
				if ($result['result_time'] != $last['result_time'])
				{
					if ($last['result_time'] && (!$result['result_time'] || $result['result_time'] > $last['result_time']))
					{
						$result['new_rank']++;
					}
					elseif ($result['result_time'] && (!$last['result_time'] || $last['result_time'] > $result['result_time']))
					{
						$results[$n-1]['new_rank']++;
					}
				}
				if ($last_new_rank != $result['new_rank'])
				{
					// already fixed above for last final heat
				}
				// do result have a wildcard
				elseif (!isset($result['result_time']) && !$result['eliminated'])
				{
					$results[$n-1]['new_rank']++;
				}
				// do last have a wildcard
				elseif (!isset($last['result_time']) && !$last['eliminated'])
				{
					$result['new_rank']++;
				}
				elseif ($result['quali_time'] > $last['quali_time'])
				{
					$result['new_rank']++;
				}
				elseif ($result['quali_time'] == $last['quali_time'])
				{
					// while every finalist must have a quali_time, they might not have a $quali_time2 (worse result from quali)
					if ($last_quali_time2 && (!$quali_time2 || $quali_time2 > $last_quali_time2))
					{
						$result['new_rank']++;
					}
					// as we cant (yet) order by some json, we have to check if last one is the looser because of 2. time
					elseif ($quali_time2 && (!$last_quali_time2 || $last_quali_time2 > $quali_time2))
					{
						$results[$n-1]['new_rank']++;
					}
				}
			}
			$last = $result;
			$last_new_rank = $result['new_rank'];
			$last_pairing = intdiv($result['start_order']-1, 2);
			$last_quali_time2 = $quali_time2;
		}

		// order again by new_rank, as it might have changed because of above tie-breaks
		usort($results, function($a, $b)
		{
			if (!isset($a['new_rank'])) $a['new_rank'] = 99999;
			if (!isset($b['new_rank'])) $b['new_rank'] = 99999;
			// sort first by new_rank
			if ($a['new_rank'] != $b['new_rank'])
			{
				return $a['new_rank'] - $b['new_rank'];
			}
			// sort tied with times by time
			elseif ($a['result_time'] && $b['result_time'])
			{
				// first by result-time
				if ($a['result_time'] != $b['result_time'])
				{
					return $a['result_time'] > $b['result_time'] ? 1 : -1;
				}
				// then by quali-time
				elseif ($a['quali_time'] != $b['quali_time'])
				{
					return $a['quali_time'] > $b['quali_time'] ? 1 : -1;
				}
			}
			// sort by having a time first
			return $a['result_time'] ? -1 : 1;
		});

		// now we need to fix new_rank for loosers of broken ties, as regular code only looks for result_time
		if (!$combined || $keys['route_order'] < 5)
		{
			foreach($results as $n => &$result)
			{
				if ($result['new_rank'] > 1 && $result['result_time'])
				{
					$result['new_rank'] = $n+1;
				}
			}
		}
		/* dump results for writing tests, if not running the test ;)
		if ($keys['WetId'])
		{
			file_put_contents($path=$GLOBALS['egw_info']['server']['temp_dir'].'/final-'.$keys['WetId'].'-'.$keys['GrpId'].'.php',
				"<?php\n\n\$input = ".var_export($input, true).";\n\n\$results = ".var_export($results, true).";\n");
			error_log(__METHOD__."() logged input and results to ".realpath($path));
		}*/
		unset($keys, $input);	// suppress warning for not used parameters
	}

	/**
	 * 2019+ tie breaking of speed qualification using 2. time (2019 rule 9.17.A.1.b)
	 *
	 * @param array& $results
	 */
	protected function speed_quali_tie_breaking(array &$results)
	{
		foreach($results as $n => &$result)
		{
			if (empty($result['new_rank'])) break;

			$result['detail'] = self::unserialize($result['detail']);
			$result['result_time2'] = $result['detail']['result_time_l'] == $result['result_time'] ?
				$result['detail']['result_time_r'] : $result['detail']['result_time_l'];
		}

		// we need to do this in 2 steps to kope with more than 2 ex aquo with different or no 2. time!
		// 1. step: sort them by new rank and 2. time
		usort($results, function($a, $b)
		{
			if (!isset($a['new_rank'])) $a['new_rank'] = 99999;
			if (!isset($b['new_rank'])) $b['new_rank'] = 99999;

			if ($a['new_rank'] != $b['new_rank'] || $a['new_rank'] == 99999 && $b['new_rank'] == 99999)
			{
				return $a['new_rank'] - $b['new_rank'];
			}

			// $a has better 2. time than $b
			if ($a['result_time2'] && (empty($b['result_time2']) || $a['result_time2'] < $b['result_time2']))
			{
				return -1;
			}
			// $b has better 2. time than $a
			if ($b['result_time2'] && (empty($a['result_time2']) || $b['result_time2'] < $a['result_time2']))
			{
				return 1;
			}
			return 0;
		});

		// 2. step: now ex. aquo are ordered correctly fix new_rank
		$last_new_rank = $last_time2 = null;
		foreach($results as $n => &$result)
		{
			if ($last_new_rank && $last_new_rank == $result['new_rank'] &&
				$last_time2 && (empty($result['result_time2']) || $last_time2 < $result['result_time2']))
			{
				$result['new_rank'] = $n+1;
			}
			else
			{
				$last_new_rank = $result['new_rank'];
			}
			$last_time2 = $result['result_time2'];
		}
	}

	/**
	 * Calculate points and rank by them (not done in database query!)
	 *
	 * @param array& $result
	 * @param int $selfscore_points points to distribute per boulder eg. 1000
	 * @ToDo support bonus/top/flash with given number of points each, currently only top is supported
	 */
	protected static function calc_selfscore_points(array &$result, $selfscore_points)
	{
		// count total number of tops
		$points = array();
		foreach($result as &$data)
		{
			$data['result_detail'] = self::unserialize($data['detail']);
			foreach((array)$data['result_detail']['score'] as $boulder => $top)
			{
				if ($top) $points[$boulder]++;
			}
		}
		// calculate points per boulder
		foreach($points as $boulder => &$pts)
		{
			$pts = $selfscore_points / $pts;
		}
		// calculate new points
		foreach($result as &$data)
		{
			$data['result_top'] = 0.0;
			foreach((array)$data['result_detail']['score'] as $boulder => $top)
			{
				if ($top) $data['result_top'] += $points[$boulder];
			}
			$data['result_top'] = $data['result_zone'] = round(100.0 * $data['result_top']);
		}
		// sort by new points
		usort($result, function($a, $b)
		{
			return $a['result_top'] < $b['result_top'] ? 1 : ($a['result_top'] > $b['result_top'] ? -1 : 0);
		});
		// update new rank
		foreach($result as $i => &$data)
		{
			if ($data['result_top'])
			{
				$data['new_rank'] = !$i || $result[$i-1]['result_top'] != $data['result_top'] ? 1+$i : $result[$i-1]['new_rank'];
			}
			else
			{
				$data['new_rank'] = null;
			}
		}
	}

	/**
	 * saves the content of data to the db
	 *
	 * Reimplemented to remove cached results
	 *
	 * @param array $keys =null if given $keys are copied to data before saveing => allows a save as
	 * @param string|array $extra_where =null extra where clause, eg. to check an etag, returns true if no affected rows!
	 * @return int|boolean 0 on success, or errno != 0 on error, or true if $extra_where is given and no rows affected
	 */
	function save($keys=null,$extra_where=null)
	{
		if ((!$err = parent::save($keys, $extra_where)))
		{
			ranking_result_bo::delete_export_route_cache($this->data);
		}
		return $err;
	}

	/**
	 * Delete a participant from a route and renumber the starting-order of the following participants
	 *
	 * @param array $keys required 'WetId', $this->id_col, possible 'GrpId', 'route_order'
	 * @return boolean true if participant was successful deleted, false otherwise
	 */
	function delete_participant($keys)
	{
		$to_delete = $this->search($keys,true,'','start_order,start_order2n');

		if (!$this->delete($keys)) return false;

		foreach($to_delete as $data)
		{
			$this->db->query("UPDATE $this->table_name SET start_order=start_order-1 WHERE ".
				$this->db->expression($this->table_name,array(
					'WetId' => $data['WetId'],
					'GrpId' => $data['GrpId'],
					'route_order' => $data['route_order'],
					'start_order > '.(int)$data['start_order'],
				)),__LINE__,__FILE__);
		}
		// update 2. lane start-order, if existing
		if ($to_delete[0]['start_order2n'])
		{
			$num_participants = (int)$this->db->select($this->table_name, 'COUNT(*)', array(
				'WetId' => $data['WetId'],
				'GrpId' => $data['GrpId'],
				'route_order' => $data['route_order'],
			), __LINE__, __FILE__)->fetchColumn();

			// Example for 21 starters: 1. in Lane A will be 11. in LaneB
			$this->db->query("UPDATE $this->table_name SET start_order2n=1+((FLOOR($num_participants/2)+start_order-1) % $num_participants) WHERE ".
				$this->db->expression($this->table_name,array(
					'WetId' => $data['WetId'],
					'GrpId' => $data['GrpId'],
					'route_order' => $data['route_order'],
				)),__LINE__,__FILE__);
		}
		ranking_result_bo::delete_export_route_cache($keys);

		return true;
	}

	/**
	 * Determine the highest existing value of given column for $keys (competition, category and route_order)
	 *
	 * @param array $keys
	 * @param string $col ='route_order'
	 * @return mixed value of $col or null
	 */
	function get_max(array $keys,$col='route_order')
	{
		$max = $this->db->select($this->table_name,'MAX('.$col.')',$keys,__LINE__,__FILE__)->fetchColumn();

		return $max !== false ? $max : null;
	}

	/**
	 * Determine the highest existing route_order for $comp and $cat
	 *
	 * @param int $comp WetId
	 * @param int $cat GrpId
	 * @return int route_order or null
	 */
	function get_max_order($comp,$cat)
	{
		return $this->get_max(array(
			'WetId' => $comp,
			'GrpId' => $cat,
		),'route_order');
	}

	/**
	 * Determine count of rows matching $keys (competition, category and route_order)
	 *
	 * @param array $keys
	 * @param string $col ='*'
	 * @return int
	 */
	function get_count(array $keys,$col='*')
	{
		$cnt = $this->db->select($this->table_name,'COUNT('.$col.')',$keys,__LINE__,__FILE__)->fetchColumn();

		return $cnt !== false ? (int)$cnt : null;
	}

	/**
	 * Merge the resultservice results from athlete $from to athlete $to
	 *
	 * @param int $from
	 * @param int $to
	 * @return int number of merged results
	 */
	function merge($from,$to)
	{
		if (!(int)$from || !(int)$to)
		{
			return false;
		}
		$this->db->update($this->table_name,array('PerId'=>$to),array('PerId'=>$from),__LINE__,__FILE__,'ranking');
		$affected = $this->db->affected_rows();

		for ($i = 1; $i <= 3; ++$i)
		{
			$this->db->update(self::RELAY_TABLE,array('PerId_'.$i=>$to),array('PerId_'.$i=>$from),__LINE__,__FILE__,'ranking');
			$affected += $this->db->affected_rows();
		}
		return $affected;
	}

	/**
	 * which column should get propagated to next heat, depends on isRelay or not
	 *
	 * @return array with columns
	 */
	function startlist_cols()
	{
		if ($this->isRelay)
		{
			$cols = array('team_id','result_rank','team_nation','team_name','start_number_1','PerId_1','start_number_2','PerId_2','start_number_3','PerId_3');
		}
		else
		{
			$cols = array('PerId','result_rank','start_number');
		}
		return $cols;
	}

	/**
	 * Check the status / existance of start list or result for all categories of given competitions
	 *
	 * @param int|array $comps
	 * @param array $status =array() result of result::result_status to get a combined array (status 0 get NOT overwritten!)
	 * @return array of WetId => GrpId => status: 1=result, 2=startlist
	 */
	function result_status($comps,$status=array())
	{
		foreach($this->db->select($this->table_name,'WetId,GrpId,MAX(result_rank) AS rank',array('WetId' => $comps),
			__LINE__,__FILE__,false,'GROUP BY WetId,GrpId') as $row)
		{
			if (!isset($status[$row['WetId']][$row['GrpId']]) || $status[$row['WetId']][$row['GrpId']] > 2)
			{
				$status[$row['WetId']][$row['GrpId']] = $row['rank'] ? 1 : 2;
			}
		}
		return $status;
	}

	/**
	 * Read results and return them in an array indexed by PerId or team_id ($this->id_cold)
	 *
	 * @param array $keys
	 * @return array PerId => array pairs
	 */
	function results_by_id(array $keys)
	{
		$by_id = array();
		if (($values = $this->search($keys, '*')))
		{
			// reindex by id
			foreach($values as $value)
			{
				$by_id[$value[$this->id_col]] = $value;
			}
		}
		//error_log(__METHOD__."(".array2string($keys).") returning ".array2string($by_id));
		return $by_id;
	}
}
