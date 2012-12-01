<?php
/**
 * eGroupWare digital ROCK Rankings - route-results storage object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2007-11 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$
 */

require_once(EGW_INCLUDE_ROOT . '/etemplate/inc/class.so_sql.inc.php');
require_once(EGW_INCLUDE_ROOT . '/ranking/inc/class.route.inc.php');
require_once(EGW_INCLUDE_ROOT . '/ranking/inc/class.boresult.inc.php');

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
define('TWO_QUALI_ALL_NO_COUNTBACK',9);	// 2012+ EYC, no countback, otherwise like TWO_QUALI_ALL

define('LEAD',4);
define('BOULDER',8);
define('SPEED',16);

define('STATUS_UNPUBLISHED',0);
define('STATUS_STARTLIST',1);
define('STATUS_RESULT_OFFICIAL',2);

/**
 * route object
 */
class route_result extends so_sql
{
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
	const MAX_BOULDERS = 8;

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

	var $rank_lead = 'CASE WHEN result_height IS NULL THEN NULL ELSE (SELECT 1+COUNT(*) FROM RouteResults r WHERE RouteResults.WetId=r.WetId AND RouteResults.GrpId=r.GrpId AND RouteResults.route_order=r.route_order AND (RouteResults.result_height < r.result_height OR RouteResults.result_height = r.result_height AND RouteResults.result_plus < r.result_plus)) END';
	var $rank_lead_countback = 'CASE WHEN result_height IS NULL THEN NULL ELSE (SELECT 1+COUNT(*) FROM RouteResults r WHERE RouteResults.WetId=r.WetId AND RouteResults.GrpId=r.GrpId AND RouteResults.route_order=r.route_order AND (RouteResults.result_height < r.result_height OR RouteResults.result_height = r.result_height AND RouteResults.result_plus < r.result_plus)) END';
	// "points" for TWO_QUALI_ALL_SUM: height-sum incl. plus/minus counting +/- 1cm
	var $rank_lead_sum = 'CASE WHEN RouteResults.result_height IS NULL THEN r1.result_height/1000.0+r1.result_plus/100.0 WHEN r1.result_height IS NULL THEN RouteResults.result_height/1000.0+RouteResults.result_plus/100.0 ELSE (RouteResults.result_height+r1.result_height)/1000.0+(RouteResults.result_plus+r1.result_plus)/100.0 END';
	var $rank_boulder = 'CASE WHEN result_top IS NULL AND result_zone IS NULL THEN NULL ELSE (SELECT 1+COUNT(*) FROM RouteResults r WHERE RouteResults.WetId=r.WetId AND RouteResults.GrpId=r.GrpId AND RouteResults.route_order=r.route_order AND (RouteResults.result_top < r.result_top OR RouteResults.result_top = r.result_top AND RouteResults.result_zone < r.result_zone OR RouteResults.result_top IS NULL AND r.result_top IS NULL AND RouteResults.result_zone < r.result_zone OR RouteResults.result_top IS NULL AND r.result_top IS NOT NULL)) END';
	var $rank_speed_quali = 'CASE WHEN result_time IS NULL THEN NULL ELSE (SELECT 1+COUNT(*) FROM RouteResults r WHERE RouteResults.WetId=r.WetId AND RouteResults.GrpId=r.GrpId AND RouteResults.route_order=r.route_order AND RouteResults.result_time > r.result_time) END';
	var $rank_speed_final = 'CASE WHEN result_time IS NULL THEN NULL ELSE 1+(SELECT RouteResults.result_time >= r.result_time FROM RouteResults r WHERE RouteResults.WetId=r.WetId AND RouteResults.GrpId=r.GrpId AND RouteResults.route_order=r.route_order AND RouteResults.start_order != r.start_order AND (RouteResults.start_order-1) DIV 2 = (r.start_order-1) DIV 2) END';

	/**
	 * Discipline only set by boresult->save_result, to be used in data2db
	 *
	 * @var string
	 */
	var $discipline;
	/**
	 * Type of route (how many qualifications) only set by boresult->save_result, to be used in data2db
	 *
	 * @var int
	 */
	var $route_type;
	/**
	 * constructor of the competition class
	 */
	function __construct($source_charset='',$db=null,$pdf_dir=null,$relay=false)
	{
		$this->isRelay = $relay;
		$this->id_col =  $relay ? 'team_id' : 'PerId';
		//$this->debug = 1;
		parent::__construct('ranking',$relay ? self::RELAY_TABLE : self::RESULT_TABLE,$db);	// call constructor of extended class

		if ($source_charset) $this->source_charset = $source_charset;

		$this->charset = translation::charset();
	}

	/**
	 * searches db for rows matching searchcriteria
	 *
	 * '*' and '?' are replaced with sql-wildcards '%' and '_'
	 *
	 * For a union-query you call search for each query with $start=='UNION' and one more with only $order_by and $start set to run the union-query.
	 *
	 * @param array/string $criteria array of key and data cols, OR a SQL query (content for WHERE), fully quoted (!)
	 * @param boolean/string/array $only_keys=true True returns only keys, False returns all cols. or
	 *	comma seperated list or array of columns to return
	 * @param string $order_by='' fieldnames + {ASC|DESC} separated by colons ',', can also contain a GROUP BY (if it contains ORDER BY)
	 * @param string/array $extra_cols='' string or array of strings to be added to the SELECT, eg. "count(*) as num"
	 * @param string $wildcard='' appended befor and after each criteria
	 * @param boolean $empty=false False=empty criteria are ignored in query, True=empty have to be empty in row
	 * @param string $op='AND' defaults to 'AND', can be set to 'OR' too, then criteria's are OR'ed together
	 * @param mixed $start=false if != false, return only maxmatch rows begining with start, or array($start,$num), or 'UNION' for a part of a union query
	 * @param array $filter=null if set (!=null) col-data pairs, to be and-ed (!) into the query without wildcards
	 * @param string $join='' sql to do a join, added as is after the table-name, eg. "JOIN table2 ON x=y" or
	 *	"LEFT JOIN table2 ON (x=y AND z=o)", Note: there's no quoting done on $join, you are responsible for it!!!
	 * @return boolean/array of matching rows (the row is an array of the cols) or False
	 */
	function &search($criteria,$only_keys=True,$order_by='',$extra_cols='',$wildcard='',$empty=False,$op='AND',$start=false,$filter=null,$join='')
	{
		//echo "<p>".__METHOD__."(crit=".print_r($criteria,true).",only_keys=".print_r($only_keys,true).",order_by=$order_by,extra_cols=".print_r($extra_cols,true).",$wildcard,$empty,$op,$start,filter=".print_r($filter,true).",join=$join)</p>\n";
		if (!is_array($extra_cols)) $extra_cols = $extra_cols ? explode(',',$extra_cols) : array();

		// avoid PerId is ambigous SQL error
		if (is_array($criteria) && isset($criteria[$this->id_col]))
		{
			$criteria[$this->table_name.'.'.$this->id_col] = $criteria[$this->id_col];
			unset($criteria[$this->id_col]);
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
		if (is_array($filter) && isset($filter['route_order']))
		{
			$route_order =& $filter['route_order'];
		}
		else
		{
			$route_order =& $criteria['route_order'];
		}
		if ($route_order === 0) $route_order = '0';		// otherwise it get's ignored by so_sql;

		if (!$only_keys && !$join || $route_order < 0)
		{
			if (!$this->isRelay)
			{
				$join = self::ATHLETE_JOIN;
				$extra_cols = array_merge($extra_cols,array('vorname','nachname','Federations.nation AS nation','geb_date','Federations.verband AS verband','ort','plz',));

				//if ($comp_nation == 'SUI')	// ToDo pass nation, so we dont need to do this join for other federations than SAC
				{
					$join .= self::ACL_FED_JOIN;
					$extra_cols[] = 'acl_fed.verband AS acl_fed';
				}
			}
			// quali points are to be displayed with 2 digits for 2008 (but all digits counting)
			if ($route_order == 2 && in_array($route_type, array(TWO_QUALI_ALL,TWO_QUALI_ALL_NO_COUNTBACK)))
			{
				$extra_cols[] = 'ROUND(('.$this->_sql_rank_prev_heat($route_order,$route_type).'),2) AS rank_prev_heat';
			}
			elseif ($route_order >= 2+(int)($route_type == TWO_QUALI_HALF))
			{
				$extra_cols[] = '('.$this->_sql_rank_prev_heat($route_order,$route_type).') AS rank_prev_heat';
			}
			elseif ($route_order < 0)			// general result
			{
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
				else
				{
					$route_order = 0;
				}
				$result_cols = array('result_rank');
				switch($discipline)
				{
					default:
					case 'lead':
						$result_cols[] = 'result_height';
						$result_cols[] = 'result_plus';
						break;
					case 'speed':
					case 'speedrelay':
						$result_cols[] = 'result_time';
						$result_cols[] = 'start_order';
						break;
					case 'boulder':
						$result_cols[] = 'result_top';
						$result_cols[] = 'result_zone';
						break;
				}
				$order_by_parts = preg_split('/[ ,]/',$order_by);

				$join .= $this->_general_result_join(array(
					'WetId' => $filter['WetId'] ? $filter['WetId'] : $criteria['WetId'],
					'GrpId' => $filter['GrpId'] ? $filter['GrpId'] : $criteria['GrpId'],
				),$extra_cols,$order_by,$route_names,$route_type,$discipline,$result_cols);

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
				elseif (in_array($route_type, array(TWO_QUALI_ALL,TWO_QUALI_ALL_SUM,TWO_QUALI_ALL_NO_COUNTBACK)) && $discipline != 'speed')
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
				$rows =& parent::search($criteria,$only_keys,$order_by,$extra_cols,$wildcard,$empty,$op,$start,$filter,$join,$need_full_no_count);

				if (!$rows) return $rows;

				// the general result is always sorted by the overal rank (to get it)
				// now we need to store that rank in result_rank
				$old = null;
				//echo "<p>quali_preselected=$quali_preselected</p>\n";
				foreach($rows as $n => &$row)
				{
					if ($row['route_order'] == 0) $row['result_rank0'] = $row['result_rank'];
					$row['org_rank'] = $row['result_rank'.$row['route_order']];
					//echo "<p>$n: $row[nachname], org_rank=$row[org_rank], result_rank=$row[result_rank] ";

					// check for ties
					$row['result_rank'] = $old['result_rank'];
					foreach(array_reverse(array_keys($route_names)) as $route_order)
					{
						// for quali_preselected: do NOT use qualification, if we have a first final result (route_order=2)
						// same is true for 2012+ EYC: no countback to quali
						if (($route_type==TWO_QUALI_ALL_NO_COUNTBACK || $quali_preselected) && $route_order < 2 && $row['result_rank2']) continue;

						if ($route_type == TWOxTWO_QUALI && $route_order == 3 ||
							$route_type == TWO_QUALI_HALF && $route_order == 1)
						{
							if (!$old || $old['org_rank'] < $row['org_rank']) $row['result_rank'] = $n+1;
							//echo "route_order=$route_order, result_rank=$row[result_rank] --> no further countback ";
							break;		// no further countback
						}
						if (boresult::is_two_quali_all($route_type) && $route_order == 1)
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
							$route_type == TWOxTWO_QUALI  && $route_order == 4 && $row['result_rank4'] )
						{
							break;	// --> not use countback
						}
					}
					//echo " --> rank=$row[result_rank]</p>\n";
					$old = $row;
				}

				// now we need to check if user wants to sort by something else
				$order = array_shift($order_by_parts);
				$sort  = array_pop($order_by_parts);
				if ($order != 'result_rank')
				{
					// sort the rows now by the user's criteria
					usort($rows,create_function('$a,$b',$func='return '.($sort == 'DESC' ? '-' : '').
						($this->table_def['fd'][$order] == 'varchar' || in_array($order,array('nachname','vorname','nation','ort')) ?
						"strcasecmp(\$a['$order'],\$b['$order'])" :
						"(\$a['$order']-\$b['$order'])").';'));
						//"(\$a['$order'] ? \$a['$order']-\$b['$order'] : -99999999)").';'));
					//echo "<p>order='$order', sort='$sort', func=$func</p>\n";
				}
				elseif($sort == 'DESC')
				{
					$rows = array_reverse($rows);
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
		return parent::search($criteria,$only_keys,$order_by,$extra_cols,$wildcard,$empty,$op,$start,$filter,$join,$need_full_no_count);
	}

	/**
	 * Subquery to get the rank in the previous heat
	 *
	 * @param int $route_order
	 * @param int $route_type ONE_QUALI, TWO_QUALI_HALF, TWO_QUALI_ALL
	 * @return string
	 */
	function _sql_rank_prev_heat($route_order,$route_type)
	{
		if ($route_order == 2 && $route_type == TWO_QUALI_ALL_SUM)
		{
			return $this->rank_lead_sum;
		}
		if ($route_order == 2 && boresult::is_two_quali_all($route_type))
		{
			// points for place r with c ex aquo: p(r,c) = (c+2r-1)/2
			$r = 'r1';
			// result_rank == NULL is counted wrong if we do: $r.result_rank=c$r.result_rank
			$rank_equal = "(CASE WHEN $r.result_rank IS NULL THEN c$r.result_rank IS NULL ELSE $r.result_rank=c$r.result_rank END)";
			$c1 = "SELECT COUNT(*) FROM $this->table_name c$r WHERE $r.WetId=c$r.WetId AND $r.GrpId=c$r.GrpId AND $r.route_order=c$r.route_order AND $rank_equal";
			//pre 2008: $c1 = "SELECT COUNT(*) FROM $this->table_name c$r WHERE $r.WetId=c$r.WetId AND $r.GrpId=c$r.GrpId AND $r.route_order=c$r.route_order AND $r.result_rank=c$r.result_rank";
			$r = 'r2';
			$rank_equal = "(CASE WHEN $r.result_rank IS NULL THEN c$r.result_rank IS NULL ELSE $r.result_rank=c$r.result_rank END)";
			$c2 = "SELECT COUNT(*) FROM $this->table_name c$r WHERE $r.WetId=c$r.WetId AND $r.GrpId=c$r.GrpId AND $r.route_order=c$r.route_order AND $rank_equal";
			//pre 2008: $c2 = "SELECT COUNT(*) FROM $this->table_name c$r WHERE $r.WetId=c$r.WetId AND $r.GrpId=c$r.GrpId AND $r.route_order=c$r.route_order AND $r.result_rank=c$r.result_rank";
			$r = 'r1';
			// athlets not climbined in one quali, get ranked last in that quali
			$unranked = "(SELECT MAX($this->table_name.result_rank)+1 FROM $this->table_name WHERE $r.WetId=$this->table_name.WetId AND $r.GrpId=$this->table_name.GrpId AND $r.route_order=$this->table_name.route_order)";
			$r1 = "(CASE WHEN $r.result_rank IS NULL THEN $unranked ELSE $r.result_rank END)";
			//pre 2008: $r1 = "(CASE WHEN $r.result_rank IS NULL THEN 999999 ELSE $r.result_rank END)";
			$r = 'r2';
			$unranked = "(SELECT MAX($this->table_name.result_rank)+1 FROM $this->table_name WHERE $r.WetId=$this->table_name.WetId AND $r.GrpId=$this->table_name.GrpId AND $r.route_order=$this->table_name.route_order)";
			$r2 = "(CASE WHEN $r.result_rank IS NULL THEN $unranked ELSE $r.result_rank END)";
			//pre 2008: $r2 = "(CASE WHEN $r.result_rank IS NULL THEN 999999 ELSE $r.result_rank END)";
			//pre 2008: rounding to 2 digits: return "SELECT ROUND(SQRT((($c1)+2*$r1-1)/2 * (($c2)+2*$r2-1)/2),2) FROM $this->table_name r1".
			return "SELECT SQRT((($c1)+2*$r1-1)/2 * (($c2)+2*$r2-1)/2) FROM $this->table_name r1".
				" JOIN $this->table_name r2 ON r1.WetId=r2.WetId AND r1.GrpId=r2.GrpId AND r2.route_order=1 AND r1.$this->id_col=r2.$this->id_col".
				" WHERE $this->table_name.WetId=r1.WetId AND $this->table_name.GrpId=r1.GrpId AND r1.route_order=0".
				" AND $this->table_name.$this->id_col=r1.$this->id_col";
		}
/*		elseif ($route_order == 4 &&  $route_type == TWOxTWO_QUALI)
		{
			// points for place r with c ex aquo: p(r,c) = (c+2r-1)/2
			$r = 'r1';
			$c1 = "SELECT COUNT(*) FROM $this->table_name c$r WHERE $r.WetId=c$r.WetId AND $r.GrpId=c$r.GrpId AND c$r.route_order IN (0,1) AND $r.result_rank=c$r.result_rank";
			$r = 'r2';
			$c2 = "SELECT COUNT(*) FROM $this->table_name c$r WHERE $r.WetId=c$r.WetId AND $r.GrpId=c$r.GrpId AND c$r.route_order IN (2,3) AND $r.result_rank=c$r.result_rank";
			$r = 'r1';
			$r1 = "(1+(SELECT COUNT(*) FROM $this->table_name r$r WHERE $r.WetId=r$r.WetId AND $r.GrpId=r$r.GrpId AND r$r.route_order IN (0,1) AND $r.result_rank>r$r.result_rank))";
			$r1 = "(CASE WHEN $r.result_rank IS NULL THEN 999999 ELSE $r1 END)";
			$r = 'r2';
			$r2 = "(1+(SELECT COUNT(*) FROM $this->table_name r$r WHERE $r.WetId=r$r.WetId AND $r.GrpId=r$r.GrpId AND r$r.route_order IN (2,3) AND $r.result_rank>r$r.result_rank))";
			$r2 = "(CASE WHEN $r.result_rank IS NULL THEN 999999 ELSE $r2 END)";
			return "SELECT ROUND(SQRT((($c1)+2*$r1-1)/2 * (($c2)+2*$r2-1)/2),2) FROM $this->table_name r1".
				" JOIN $this->table_name r2 ON r1.WetId=r2.WetId AND r1.GrpId=r2.GrpId AND r2.route_order IN (2,3) AND r1.$this->id_col=r2.$this->id_col".
				" WHERE $this->table_name.WetId=r1.WetId AND $this->table_name.GrpId=r1.GrpId AND r1.route_order IN (0,1)".
				" AND $this->table_name.$this->id_col=r1.$this->id_col";
		}*/
		elseif($route_type == TWOxTWO_QUALI && in_array($route_order,array(2,3)))
		{
			return "SELECT result_detail FROM $this->table_name p WHERE $this->table_name.WetId=p.WetId AND $this->table_name.GrpId=p.GrpId AND ".
				'p.route_order='.(int)($route_order-2)." AND $this->table_name.$this->id_col=p.$this->id_col";
		}
		return "SELECT result_rank FROM $this->table_name p WHERE $this->table_name.WetId=p.WetId AND $this->table_name.GrpId=p.GrpId AND ".
			'p.route_order '.($route_order == 2 ? 'IN (0,1)' : '='.(int)($route_order-1))." AND $this->table_name.$this->id_col=p.$this->id_col";
	}

	/**
	 * Return join and extra_cols for a general result
	 *
	 * @internal
	 * @param array $keys values for WetId and GrpId
	 * @param array &$extra_cols
	 * @param string &$order_by
	 * @param array &$route_names route_order => route_name pairs
	 * @param int $route_type ONE_QUALI, TWO_QUALI_HALF or TWO_QUALI_ALL
	 * @param string $discipline 'lead', 'speed', 'boulder', 'speedrelay'
	 * @param array $result_cols=array() result relevant col
	 * @return string join
	 */
	function _general_result_join($keys,&$extra_cols,&$order_by,&$route_names,$route_type,$discipline,$result_cols=array())
	{
		//echo "<p>".__METHOD__."(".print_r($keys,true).",".print_r($extra_cols,true).",,,type=$route_type,$discipline,".print_r($result_cols,true).")</p>\n";
		if (!isset($GLOBALS['egw']->route) || !is_object($GLOBALS['egw']->route))
		{
			$GLOBALS['egw']->route = new route($this->source_charset,$this->db);
		}
		$route_names = $GLOBALS['egw']->route->query_list('route_name','route_order',$keys,'route_order');
		//echo "route_names="; _debug_array($route_names);
		$order_by = array("$this->table_name.result_rank");	// Quali

		$join = "\n";
		foreach($route_names as $route_order => $label)
		{
			if ($route_order < 0) continue;	// general result
			if ($route_type == TWOxTWO_QUALI)
			{
				if (in_array($route_order,array(2,3))) continue;	// base of the query, no need to join
			}
			elseif ($route_order < 2-(int)boresult::is_two_quali_all($route_type))
			{
				continue;	// no need to join the qualification
			}

			$join .= "LEFT JOIN $this->table_name r$route_order ON $this->table_name.WetId=r$route_order.WetId AND $this->table_name.GrpId=r$route_order.GrpId AND r$route_order.route_order=$route_order AND $this->table_name.$this->id_col=r$route_order.$this->id_col\n";
			foreach($result_cols as $col)
			{
				$extra_cols[] = "r$route_order.$col AS $col$route_order";
			}
			if ($route_type == TWOxTWO_QUALI && $route_order < 2) continue;	// dont order by the 1. quali

			if (boresult::is_two_quali_all($route_type) && $route_order == 1)
			{
				// only order are the quali-points, same SQL as for the previous "heat" of route_order=2=Final
				$product = '('.$this->_sql_rank_prev_heat(1+$route_order,$route_type).')';
				$order_by = array($product);
				if ($route_type == TWO_QUALI_ALL_SUM) $order_by[0] .= ' DESC';
				$extra_cols[] = "$product AS quali_points";
			}
			else
			{
				$order_by[] = "r$route_order.result_rank";
			}
			// not participating in one qualification (order 0 or 1) of TWO_QUALI_ALL is ok
			if (!in_array($route_type, array(TWO_QUALI_ALL, TWO_QUALI_ALL_NO_COUNTBACK)) || $route_order >= 2)
			{
				$order_by[] = "r$route_order.result_rank IS NULL";
			}
		}
		$order_by = implode(',',array_reverse($order_by));
		if ($this->isRelay)
		{
			$order_by .= ',RelayResults.team_nation ASC,RelayResults.team_name ASC';
		}
		else
		{
			$order_by .= ',nachname ASC,vorname ASC';
		}
		$extra_cols[] = $this->table_name.'.*';		// trick so_sql to return the cols from the quali as regular cols

		//echo "join=$join, order_by=$order_by, extra_cols="; _debug_array($extra_cols);
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
			0  => '&nbsp;',
			1  => '+',
		);
		if (!is_array($data))
		{
			$data =& $this->data;
		}
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
		if ($data['result_detail'] && !$data['general_result'])
		{
			foreach(unserialize($data['result_detail']) as $name => $value)
			{
				$data[$name] = $value;
			}
			unset($data['result_detail']);
		}
		switch($data['discipline'])
		{
			default:
			case 'lead':
				if ($data['result_height'] || $data['result_height1'] || $data['result_height2'])	// lead result
				{
					if ($data['quali_points'] > 999) $data['quali_points'] = '';	// 999 = sqrt(999999)
					foreach($data['general_result'] ? array(1,2,3,'',0,4,5,6) : array('') as $suffix)
					{
						$to_suffix = $suffix;
						if ($data['general_result'] && $suffix === '' && $data['route_order'])
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
							$data['result'.$suffix] .= '&nbsp;'.(100-$data['result_plus'.$suffix]).'.';
						}
						elseif ($data['result_height'.$suffix] == TOP_HEIGHT)
						{
							$data['result_height'.$to_suffix] = '';
							$data['result_plus'.$to_suffix]   = TOP_PLUS;
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
						elseif(($suffix === '' || $suffix == 1 ) && $data['result_height2'] && $data['route_type'] != TWO_QUALI_HALF)
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
					if (in_array($data['route_type'],array(TWO_QUALI_ALL,TWOxTWO_QUALI,TWO_QUALI_ALL_NO_COUNTBACK)))
					{
						// quali on two routes for all --> add rank to result
						foreach($data['route_type'] == TWOxTWO_QUALI ? array('',1,2,3) : array('',1) as $suffix)
						{
							if ($data['result'.$suffix] && $data['result_rank'.$suffix]) $data['result'.$suffix] .= '&nbsp;&nbsp;'.$data['result_rank'.$suffix].'.';
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
					$data['result'] .= '&nbsp;&nbsp;'.sprintf('%4.2lf',$data['qoints']);
				}
				if ($data['other_detail'])
				{
					$data['other_detail'] = unserialize($data['other_detail']);
				}
				if ($data['rank_prev_heat'] && $data['route_type'] == TWOxTWO_QUALI && in_array($data['route_order'],array(2,3)))
				{
					$data['rank_prev_heat'] = unserialize($data['rank_prev_heat']);
					$data['rank_prev_heat'] = sprintf('%4.2lf',$data['rank_prev_heat']['qoints']);
				}
				if ($data['ability_percent'] && $data['result_height'])
				{
					$data['result_height'] /= 100.0/$data['ability_percent'];
				}
				break;

			case 'boulder':
				for($i=1; $i <= self::MAX_BOULDERS; ++$i)
				{
					$data['boulder'.$i] = ($data['top'.$i] ? 't'.$data['top'.$i].' ' : '').
						((string)$data['zone'.$i] !== '' ? 'b'.$data['zone'.$i] : '');
				}
				$suffix = '';	// general result can have route_order as suffix
				while (isset($data['result_zone'.$suffix]) || $suffix < 2 || isset($data['result_zone'.(1+$suffix)]))
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
						$zones = round($data['result_zone'.$suffix] / 100);
						$zone_tries = $zones ? $zones * 100 - $data['result_zone'.$suffix] : '';
						// boulder without problem specific results (route_num_problems=0)
						if (!$suffix && !isset($data['zone1']))
						{
							$data['tops'] = $tops;
							$data['top_tries'] = $top_tries;
							$data['zones'] = $zones;
							$data['zone_tries'] = $zone_tries;
						}
						$data['result'.$to_suffix] = $tops.'t'.$top_tries.' '.$zones.'b'.$zone_tries;
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
				if ($data['result_time'] || $data['eliminated'] || $data['eliminated_r'])	// speed result
				{
					if (!array_key_exists('result_time2',$data) && !$data['ability_percent'])
					{
						if ($data['result_time'])
						{
							$data['result_time'] *= 0.001;
							if ($data['result_time'] == ELIMINATED_TIME)
							{
								$data['eliminated'] = 1;
								$data['result_time'] = null;
								$data['time_sum'] = $data['result'] = lang('fall');
							}
							elseif ($data['result_time'] == WILDCARD_TIME)
							{
								$data['eliminated'] = 0;
								$data['result_time'] = null;
								$data['time_sum'] = $data['result'] = lang('Wildcard');
							}
							else
							{
								$data['time_sum'] = $data['result'] = sprintf('%4.2lf',$data['result_time']);
							}
						}
						if ($data['result_time_r'] || isset($data['eliminated_r']))	// speed with two goes
						{
							$data['result'] = (string)$data['eliminated_l'] === '' ? sprintf('%4.2lf',$data['result_time_l']) :
								($data['eliminated_l'] ? lang('fall') : lang('Wildcard'));
							$data['result_time'] = $data['result_time_l'];
							$data['eliminated'] = $data['eliminated_l'];
							$data['result_r'] = (string)$data['eliminated_r'] === '' ?
								($data['result_time_r'] ? sprintf('%4.2lf',$data['result_time_r']) : '') :
								($data['eliminated_r'] ? lang('fall') : lang('Wildcard'));
						}
					}
					else
					{
						$suffix = '';	// general result can have route_order as suffix
						while (isset($data['result_time'.$suffix]) || $suffix < 2 || isset($data['result_time'.(1+$suffix)]))
						{
							if ($data['result_time'.$suffix])
							{
								$data['result_time'.$suffix] *= 0.001;
								if ($data['result_time'.$suffix] == ELIMINATED_TIME)
								{
									$data['result'.$suffix] = lang('fall');
								}
								elseif ($data['result_time'.$suffix] == WILDCARD_TIME)
								{
									$data['result'.$suffix] = lang('Wildcard');
								}
								else
								{
									$data['result'.$suffix] = sprintf('%4.2lf',$data['result_time'.$suffix]);
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
	 * Appriviate the name to make it better printable
	 *
	 * @param string &$name
	 * @param int $max=12 maximum length
	 */
	function _shorten_name(&$name,$max=13)
	{
		if (strlen($name) <= $max) return;

		// add a space after each dash or comma, if there's none already
		$name = preg_replace('/([-,]+ *)/','\\1 ',$name);

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
		if ($data['result_plus'] == TOP_PLUS)	// top
		{
			$data['result_height'] = TOP_HEIGHT;
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
				else
				{
					$data['result_time'] = round(1000 * ELIMINATED_TIME);
				}
			}
			elseif ((string)$data['eliminated'] !== '' || $data['eliminated_r'])
			{
				$data['result_time'] = round(1000 * ((string)$data['elimitated'] !== '' ?
					($data['eliminated'] ? ELIMINATED_TIME : WILDCARD_TIME) : ELIMINATED_TIME));
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
					($data['eliminated'] ? ELIMINATED_TIME : WILDCARD_TIME) : ELIMINATED_TIME));
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
				case '1': $data['result_time'] = ELIMINATED_TIME; break;
				case '0': $data['result_time'] = WILDCARD_TIME; break;
			}
			if ($data['result_time'])
			{
				$data['result_time'] = round(1000 * $data['result_time']);
			}
		}
		// saving the boulder results, if there are any
		if (isset($data['top1']) || isset($data['top2']) || isset($data['top3']) || isset($data['top4']) ||
			isset($data['top5']) || isset($data['top6']) || isset($data['top7']) || isset($data['top8']))
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
			}
		}
		// boulder result with just the sums (route_num_problems=0)
		elseif (isset($data['tops']))
		{
			if (is_numeric($data['zones']))
			{
				$data['result_zone'] = 100 * $data['zones'] - $data['zone_tries'];
				if ($data['tops'] > 0)
				{
					$data['result_top'] = 100 * $data['tops'] - $data['top_tries'];
				}
			}
			unset($data['result_detail']);	// do NOT store existing problem specific results
		}
		if (isset($data['ability_percent']))
		{
			if ($data['result_height']) $data['result_height'] *= 100.0/$data['ability_percent'];
			$data['result_detail']['ability_percent'] = $data['ability_percent'];
		}
		if (isset($data['ranking'])) $data['result_detail']['ranking'] = $data['ranking'];

		if (is_array($data['result_detail'])) $data['result_detail'] = serialize($data['result_detail']);

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
			if (isset($new['top'.$i])) $this->data['top'.$i] = $new['top'.$i];
			if (isset($new['zone'.$i])) $this->data['zone'.$i] = $new['zone'.$i];
		}
		foreach(array('eliminated','result_time_r','eliminated_r','tops','top_tries','zones','zone_tries','ability_percent') as $name)
		{
			if (isset($new[$name])) $this->data[$name] = $new[$name];
		}
	}

	/**
	 * Update the ranking of a given route
	 *
	 * @param array $keys values for keys WetId, GrpId and route_order
	 * @param boolean $do_countback=true should we do a countback on further heats
	 * @param int $route_type=ONE_QUALI ONE_QUALI, TWO_QUALI_HALF, TWO_QUALI_ALL
	 * @param string $discipline='lead' 'lead', 'speed', 'boulder', 'speedrelay'
	 * @param int $quali_preselected=0 preselected participants for quali --> no countback to quali, if set!
	 * @param boolean $is_final=false important for lead where we use time now in final, if tied after countback
	 * @return int|boolean updated rows or false on error (no route specified in $keys)
	 */
	function update_ranking($keys,$route_type=ONE_QUALI,$discipline='lead',$quali_preselected=0,$is_final=null)
	{
		//error_log(__METHOD__.'('.array2string($keys).", $route_type, '$discipline', $quali_preselected)");
		if (!$keys['WetId'] || !$keys['GrpId'] || !is_numeric($keys['route_order'])) return false;

		$keys = array_intersect_key($keys,array('WetId'=>0,'GrpId'=>0,'route_order'=>0));	// remove other content

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
				if ($keys['route_order'] < 2)
				{
					$mode = str_replace('RouteResults',$this->table_name,$this->rank_speed_quali);
				}
				else
				{
					$mode = str_replace('RouteResults',$this->table_name,$this->rank_speed_final);
					// ORDER BY CASE column-alias does NOT work with MySQL 5.0.22-Debian_Ubuntu6.06.6, it works with 5.0.51a-log SUSE
					//$order_by = 'result_time IS NULL,CASE new_rank WHEN 1 THEN 0 ELSE result_time END ASC';
					$order_by = "result_time IS NULL,CASE ($mode) WHEN 1 THEN 0 ELSE result_time END ASC";
					$extra_cols[] = 'result_time';
				}
				break;
			case 'boulder':
				$mode = $this->rank_boulder;
				$order_by = 'result_top IS NULL,result_top DESC,result_zone IS NULL,result_zone DESC';
		}
		$extra_cols[] = $mode.' AS new_rank';

		// do we have a countback
		if ($quali_preselected && $keys['route_order'] == 2 || $route_type == TWO_QUALI_ALL_NO_COUNTBACK)
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
		elseif (substr($discipline,0,5) != 'speed' && $keys['route_order'] >= (2+(int)($route_type == TWO_QUALI_HALF)))
		{
			$extra_cols[] = '('.$this->_sql_rank_prev_heat($keys['route_order'],$route_type).') AS rank_prev_heat';
			$order_by .= ',rank_prev_heat ASC';
		}
		// lead countback
		elseif ($discipline == 'lead')
		{
			// quali points are to be displayed with 2 digits for 2008 (but all digits counting)
			if ($keys['route_order'] == 2 && in_array($route_type, array(TWO_QUALI_ALL,TWO_QUALI_ALL_NO_COUNTBACK)))
			{
				$extra_cols[] = 'ROUND(('.$this->_sql_rank_prev_heat($keys['route_order'],$route_type).'),2) AS rank_prev_heat';
				$order_by .= ',rank_prev_heat ASC';
			}
			elseif ($keys['route_order'] >= 2+(int)($route_type == TWO_QUALI_HALF))
			{
				$extra_cols[] = '('.$this->_sql_rank_prev_heat($keys['route_order'],$route_type).') AS rank_prev_heat';
				$order_by .= ',rank_prev_heat ASC';
			}
		}
		// lead final use time, after regular countback
		if ($discipline == 'lead' && $is_final)
		{
			$order_by .= ',result_time ASC';
		}
		//error_log(__METHOD__.'('.array2string($keys).", $route_type, '$discipline', $quali_preselected) extra_cols=".array2string($extra_cols).", order_by=$order_by");
		$result = $this->search($keys,$this->id_col.',result_rank,result_detail AS detail',$order_by,$extra_cols);

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
				usort($result,create_function('$a,$b','return round(100*$a["new_quali_points"])-round(100*$b["new_quali_points"]);'));
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
		$modified = 0;
		$old_time = $old_prev_rank = null;
		$first_places = array();
		foreach($result as $i => &$data)
		{
			// for ko-system of speed the rank is only 1 (winner) or 2 (looser)
			if (substr($discipline,0,5) == 'speed' && $keys['route_order'] >= 2 && $data['new_rank'])
			{
				if ($data['eliminated']) $data['time_sum'] = ELIMINATED_TIME;
				$new_speed_rank = $data['new_rank'];
				if ($data['new_rank'] > 1)	// all winners must have rank=1(!)
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
			if ($data['new_rank'] && $data['new_rank'] != $i+1 && $discipline == 'lead' && $is_final)
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
					$to_update['result_detail'] = $data['detail'] ? unserialize($data['detail']) : array();
					$to_update['result_detail']['qoints'] = $data['new_qoints'];
					$to_update['result_detail']['quali_points'] = $data['new_quali_points'];
					//echo "<p>qoints: $data[qoints] --> $qoints</p>\n";
					$to_update['result_detail'] = serialize($to_update['result_detail']);
				}
			}
//_debug_array($data); _debug_array($to_update);
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
			$old_time = $data['result_time_l'];
			$old_rank = $data['new_rank'];
		}
		if ($modified) boresult::delete_export_route_cache($keys);

		return $modified;
	}

	/**
	 * saves the content of data to the db
	 *
	 * Reimplemented to remove cached results
	 *
	 * @param array $keys=null if given $keys are copied to data before saveing => allows a save as
	 * @param string|array $extra_where=null extra where clause, eg. to check an etag, returns true if no affected rows!
	 * @return int|boolean 0 on success, or errno != 0 on error, or true if $extra_where is given and no rows affected
	 */
	function save($keys=null,$extra_where=null)
	{
		if ((!$err = parent::save($keys, $extra_where)))
		{
			boresult::delete_export_route_cache($this->data);
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
		$to_delete = $this->search($keys,true,'','start_order');

		if (!$this->delete($keys)) return false;

		boresult::delete_export_route_cache($keys);

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
		return true;
	}

	/**
	 * Determine the highest existing value of given column for $keys (competition, category and route_order)
	 *
	 * @param array $keys
	 * @param string $col='route_order'
	 * @return mixed value of $col or null
	 */
	function get_max(array $keys,$col='route_order')
	{
		$this->db->select($this->table_name,'MAX('.$col.')',$keys,__LINE__,__FILE__);

		return $this->db->next_record() ? $this->db->f(0) : null;
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
	 * @param string $col='*'
	 * @return int
	 */
	function get_count(array $keys,$col='*')
	{
		$this->db->select($this->table_name,'COUNT('.$col.')',$keys,__LINE__,__FILE__);

		return $this->db->next_record() ? (int)$this->db->f(0) : null;
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
	 * @param array $status=array() result of result::result_status to get a combined array (status 0 get NOT overwritten!)
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
}
