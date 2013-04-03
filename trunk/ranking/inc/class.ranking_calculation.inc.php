<?php
/**
 * EGroupware digital ROCK Rankings - calculate diverse rankings
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2013 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$
 */

require_once(EGW_INCLUDE_ROOT.'/ranking/inc/class.boranking.inc.php');

/**
 * Calculate diverse rankings:
 * - world ranking
 * - cup ranking
 * - national team ranking
 * - sektionenwertung
 */
class ranking_calculation
{
	/**
	 * Reference to boranking class
	 *
	 * @var boranking
	 */
	protected $bo;

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
	 * Echo diverse diagnositics about ranking calculation
	 *
	 * @var boolean
	 */
	var $debug = false;

	public function __construct(boranking $bo=null)
	{
		$this->bo = $bo ? $bo : new boranking();
	}

	/**
	 * Calculate nation team ranking for a given comp. or cup and given cats
	 *
	 * @param int $_comp
	 * @param int|string|array $_cats
	 * @param int $_cup
	 * @param array &$date_comp=null on return latest competition
	 * @return array see ranking_calculation::aggregate
	 */
	public function nat_team_ranking($_comp, $_cats, $_cup, array &$date_comp=null)
	{
		$valid_cats = array();

		if ($_cup)	// do a cup ranking
		{
			if (!($cup = $this->bo->cup->read($_cup)))
			{
				throw new egw_exception_wrong_parameter("Cup '$_cup' NOT found!");
			}

			// get all comps of a serie
			$comps = $this->bo->comp->search(array('serie' => $cup['SerId']), true, 'datum');
			foreach($comps as &$c)
			{
				$c = $c['WetId'];
			}
			$_comp = $comps[0];
		}
		if (!($comp = $this->bo->comp->read($_comp)))
		{
			throw new egw_exception_wrong_parameter("Competition '$_comp' NOT found!");
		}
		if (!$comp['quota'])
		{
			throw new egw_exception_wrong_parameter("No quota set for competition $comp[name]!");
		}

		if (!$cup)
		{
			$comps = array($comp['WetId']);
			// no overall/combined for a cup ranking
			if ((int)$comp['datum'] >= 2008)
			{
				$valid_cats['overall'] = array(1,2,5,6,23,24);
			}
			else
			{
				$valid_cats['combined (lead &amp; boulder)'] = array(1,2,5,6);
			}
		}
		// all valid Combinations
		$valid_cats += array(
			'lead' => array(1,2),
			'boulder' => array(5,6),
			'speed'    => array(23,24),
			'youth lead' => array(15,16,17,18,19,20),
			'youth boulder' => array(79,80,81,82,83,84),
			'youth speed' => array(56,57,58,59,60,61),
		);

		// get all cats from existing results of a comp or cup
		$cats_found = array();
		foreach($this->bo->db->select($this->bo->result->table_name, 'DISTINCT GrpId', array(
			'WetId' => $comps,
			'platz > 0',
		), __LINE__, __FILE__, false, 'ORDER BY GrpId', 'ranking') as $row)
		{
			$cats_found[] = $row['GrpId'];
		}
		if (!$cats_found)
		{
			throw new egw_exception_wrong_parameter("No results yet!");
		}

		foreach($valid_cats as $name => $vcats)
		{
			if (count(array_intersect($cats_found,$vcats)) != count($vcats) &&
				($name != 'overall' || count($cats_found) <= 2 ||	// show overall if we have more then 2 cats
				// no overall for youth
				$name == 'overall' && array_intersect($cats_found, array(15,16,17,18,19,20,56,57,58,59,60,61,79,80,81,82,83,84))))
			{
				unset($valid_cats[$name]);
			}
		}
		//echo "valid_cats=<pre>".print_r($valid_cats,true)."</pre>\n";

		// get the data of all cats
		$cat_data = $this->bo->cats->names(array('GrpId' => $cats_found), 0, 'GrpId');
		$given_cats = explode(',', $_cats);
		$cats = array();
		foreach($cats_found as $cat)
		{
			if (in_array($cat, $given_cats))
			{
				$cats[] = $cat;
			}
		}

		// check if we have a valid combination
		$valid = '';
		foreach($valid_cats as $name => $vcats)
		{
			if (count(array_intersect($cats,$vcats)) == count($vcats) ||
				($name == 'overall' && count($cats) > 2))	// show overall if we have more then 2 cats
			{
				$valid = $vcats;
				break;
			}
		}
		if (!$valid)
		{
			//otherwise choose one which includes the given cat (reverse order ensures the combined has lowest significants)
			foreach(array_reverse($valid_cats) as $name => $vcats)
			{
				if (count(array_intersect($cats, $vcats)))	// given cat(s) are at least included in this valid cat combination
				{
					$valid = $vcats;
					break;
				}
			}
			if (!$valid)	// no cats ==> use first valid
			{
				reset($valid_cats);
				list($name, $valid) = each($valid_cats);
			}
		}
		foreach($valid as $key => $c)
		{
			if (isset($cat_data[$c]))
			{
				$cat_names[] = $cat_data[$c];
			}
			else
			{
				unset($valid[$key]);
			}
		}
		$cats = implode(',', $valid);
		$cat_names = implode(', ', $cat_names);

		$quota = $wettk['quota'];
		// force quota for youth to 1, to allow to use a higher quota for registration, it still need to be set!
		if (substr($name,0,5) == 'youth')
		{
			$quota = 1;
		}
		// hardcoding quota of 3 for everything else, as national quota is 4 since 2012, while rules still want 3 for nat-team-ranking
		else
		{
			$quota = 3;
		}
		//echo "<p>$name: quota=$quota</p>\n"; exit;

		if ($cup)
		{
			$cat = $this->bo->cats->read($valid[0]);
			$max_comps = $this->bo->cup->get_max_comps($cat['rkey'], $cup);
		}
		elseif($name == 'overall' && count($valid) > 2)
		{
			$min_cats = 2;	// overall ranking requires participation in 2 or more categories!
		}
		$filter = array(
			'GrpId' => $valid,
		);
		if ($cup)
		{
			$filter['SerId'] = $cup['SerId'];
		}
		else
		{
			$filter['WetId'] = $comp['WetId'];
		}
		$ret = $this->aggregated('.', $filter, $quota, 'nation', true, $min_cats, $max_comps, $date_comp);

		if ($cup)
		{
			$ret['params']['cup_name'] = $cup['name'];
			$ret['params']['SerId'] = $cup['SerId'];
		}
		$ret['params']['cat_name'] = $name;

		// add see also links for other available national team rankings
		foreach($valid_cats as $name => $vcats)
		{
			$vcats_str = implode(',',$vcats);
			if ($cats != $vcats_str && ($name != 'overall' || count(explode(',',$cats)) <= 2))
			{
				$ret['params']['see_also'][] = array(
					'name' => $ret['params']['name'].' '.($cup ? $cup['name'] : $comp['name']).': '.$name,
					'url'  => '#!type=nat_team_ranking&'.($cup ? 'cup='.$cup['SerId'] : 'comp='.$comp['WetId']).'&cat='.$vcats_str,
				);
			}
		}

		return $ret;
	}

	/**
	 * Calculate an aggregated ranking eg. national team ranking or sektionen wertung
	 *
	 * @param string|int $date date of ranking, "." for current date or WetId or rkey of competition
	 * @param array $filter=array() to filter results by GrpId or WetId
	 * @param int $best_results=null use N best results per category and competition
	 * @param string $by='nation' 'nation', 'fed_id', 'fed_parent' or 3-letter nation code
	 * @param boolean $use_cup_points=true
	 * @param int $min_cats=null required minimum number of cats, eg. 2 for overall
	 * @param int $max_comps=null used max. number of comps for national team ranking of a cup
	 * @param array &$date_comp=null on return data of last competition in ranking
	 * @param int $window=12 number of month in ranking
	 * @return array of array with values 'rank', 'name', 'points', 'counting', 'comps' (array)
	 * 	plus categorys and competitions arrays
	 */
	public function aggregated(&$date='.', array $filter=array(), $best_results=3, $by='nation', $use_cup_points=true,
		$min_cats=null, $max_comps=null, &$date_comp=null, $window=12)
	{
		//error_log(__METHOD__."(date='$date', filter=".array2string($filter).", best_results=$best_results, by='$by', use_cup_points=".array2string($use_cup_points).", min_cats=$min_cats, max_comps=$max_comps)");
		// set $by from $filter['nation'] or visa versa
		switch($by ? $by : $filter['nation'])
		{
			case 'GER':
			case 'fed_id':
				$by = 'fed_id';
				if (!array_key_exists('nation', $filter)) $filter['nation'] = 'GER';
				break;
			case 'SUI':
			case 'acl_fed_id':
				$by = 'acl_fed_id';
				if (!array_key_exists('nation', $filter)) $filter['nation'] = 'SUI';
				break;
			default:
				$by = 'nation';
				if (!array_key_exists('nation', $filter)) $filter['nation'] = null;
				break;
		}
		// get date-range for results
		if (!empty($filter['WetId']))
		{
			if (!($date_comp = $this->bo->comp->read($filter['WetId'])))
			{
				throw new egw_exception_wrong_parameter ("Competition '$filter[WetId]' NOT found!");
			}
			$date = $date_comp['datum'];
		}
		elseif ($date == '.' || !isset($date) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date))
		{
			if ($date == '.' || !isset($date)) $date = date('Y-m-d');

			$date_comp = $this->bo->comp->last_comp($date, $filter['GrpId'], $filter['nation'], $filter['SerId']);
			$date = $date_comp['datum'];

			if (!$this->bo->comp->next_comp_this_year($date_comp['datum'],$filter['GrpId'],$filter['nation'],$filter['SerId']))
			{
				$date = (int)$date_comp['datum'] . '-12-31';	// no further comp. -> stand 31.12.
			}
		}
		else
		{
			if (!($date_comp = $this->bo->comp->read($date)))
			{
				throw new egw_exception_wrong_parameter ("Competition '$date' NOT found!");
			}
		}

		// if no single competition, use date filter for window
		if (empty($filter['WetId']))
		{
			$filter[] = $this->bo->comp->table_name.'.datum <= '.$this->bo->db->quote($date);
			list($y,$m,$d) = explode('-', $date);
			$y -= (int)($window / 12);
			$m -= $window % 12;
			if ($m < 1)
			{
				--$y;
				$m += 12;
			}
			$filter[] = $this->bo->comp->table_name.'.datum > '.$this->bo->db->quote(sprintf('%04d-%02d-%02d', $y, $m, $d));
		}
		if (array_key_exists('nation', $filter))
		{
			$filter[] = $this->bo->comp->table_name.'.nation'.
				(isset($filter['nation']) ? '='.$this->bo->db->quote($filter['nation']) : ' IS NULL');
			unset($filter['nation']);
		}
		if (!empty($filter['SerId']))
		{
			$filter[] = $this->bo->comp->table_name.'.serie='.(int)$filter['SerId'];
			unset($filter['SerId']);
		}
		$filter[] = $this->bo->result->table_name.'.platz > 0';
		$filter[] = $this->bo->comp->table_name.'.faktor > 0';

		//_debug_array($filter);
		$join = ' JOIN '.$this->bo->comp->table_name.' USING(WetId)';
		$join .= ' JOIN '.$this->bo->cats->table_name.' USING(GrpId)';
		$join .= ' JOIN '.ranking_athlete::ATHLETE_TABLE.' USING(PerId)';
		$join .= str_replace('USING(fed_id)','ON a2f.fed_id='.ranking_athlete::FEDERATIONS_TABLE.
			'.fed_id', $this->bo->athlete->fed_join('Personen','YEAR(Wettkaempfe.datum)'));
		$extra_cols = $this->bo->comp->table_name.'.name AS comp_name,'.
			$this->bo->comp->table_name.'.dru_bez AS comp_short,'.
			$this->bo->comp->table_name.'.datum AS comp_date,'.
			$this->bo->cats->table_name.'.name AS cat_name,nachname,vorname,'.
			ranking_athlete::FEDERATIONS_TABLE.'.nation,verband,fed_url,'.
			ranking_athlete::FEDERATIONS_TABLE.'.fed_id AS fed_id,fed_parent,acl.fed_id AS acl_fed_id,geb_date,'.
			ranking_athlete::ATHLETE_TABLE.'.PerId AS PerId';
		$append = 'ORDER BY '.$by.','.$this->bo->comp->table_name.'.datum DESC,WetId,GrpId,Results.platz';
		if ($use_cup_points)
		{
			$PktId = $use_cup_points !== true ? $use_cup_points : 2;	// uiaa
			$pkte = 's.pkt';
			$platz = 'Results.platz';
			// since 2009 int. cups use "averaged" points for ex aquo competitors (rounded down!)
			if ((int)$date_comp['datum'] >= 2009)
			{
				$ex_aquos = '(SELECT COUNT(*) FROM Results ex WHERE ex.GrpId=Results.GrpId AND ex.WetId=Results.WetId AND ex.platz=Results.platz)';
				$pkte = "(CASE WHEN Results.datum<'2009-01-01' OR $ex_aquos=1 THEN $pkte ELSE FLOOR((SELECT SUM(pkte.pkt) FROM PktSystemPkte pkte WHERE PktId=$PktId AND $platz <= pkte.platz AND pkte.platz < $platz+$ex_aquos)/$ex_aquos) END)";
			}
			$join .= ' LEFT JOIN PktSystemPkte s ON Results.platz=s.platz AND s.PktId='.(int)$PktId;

			if ($min_cats > 1)
			{
				$extra_cols .= ',(SELECT COUNT(*) FROM Results v WHERE Results.WetId=v.WetId AND Results.PerId=v.PerId AND v.platz > 0 AND '.
					$this->bo->db->expression('Results', 'v.', array('GrpId' => $filter['GrpId'])).') AS num_cats';

				$append = 'HAVING num_cats >= '.(int)$min_cats.' '.$append;
			}
			$extra_cols .= ','.$pkte.'*100 AS pkt';
		}
		$ranking = $competions = $categorys = array();
		$last_aggr = $last_comp = $last_cat = null;
		$used = 0;
		foreach($this->bo->result->aggregated_results($filter, $extra_cols, $join, $append) as $result)
		{
			if (!isset($last_aggr) || $last_aggr != $result[$by])
			{
				switch($by)
				{
					case 'fed_id':
						$name = $result['verband'];
						$url = $result['fed_url'];
						break;
					case 'nation':
						$name = $this->bo->federation->get_nationname($result['nation']);
						$url = '';
						break;
					case 'acl_fed_id':
						if (!isset($rzs))
						{
							$rzs = $this->bo->federation->federations('SUI');
						}
						$name = isset($rzs[$result['acl_fed_id']]) ? $rzs[$result['acl_fed_id']] :
							(empty($result['acl_fed_id']) ? 'None' : $result['acl_fed_id']);
						$url = '';
						break;
					default:
						$name = $result[$by];
						$url = '';
						break;
				}
				$ranking[$result[$by]] = array(
					$by => $result[$by],
 					'name' => $name,
					'url' => $url,
					'rank' => '',
					'points' => 0,
					'counting' => array(),
					'comps' => array(),
				);
				$results =& $ranking[$result[$by]]['counting'];
				$points =& $ranking[$result[$by]]['points'];
				$comps =& $ranking[$result[$by]]['comps'];
				$last_aggr = $result[$by];
				$last_cat = $last_comp = null;
			}
			if ($result['WetId'] != $last_comp || $result['GrpId'] != $last_cat)
			{
				$used = 0;
				$last_comp = $result['WetId'];
				$last_cat = $result['GrpId'];
			}
			// build competitions array
			if (!isset($competitions[$result['WetId']]))
			{
				$competitions[$result['WetId']] = array(
					'WetId' => $result['WetId'],
					'name'  => $result['comp_name'],
					'short' => $result['comp_short'],
					'date'  => $result['comp_date'],
				);
			}
			// build categories array
			if (!isset($categorys[$result['GrpId']]))
			{
				$categorys[$result['GrpId']] = array(
					'GrpId' => $result['GrpId'],
					'name'  => $result['cat_name'],
				);
			}
			if ($used >= $best_results) continue;	// no more results counting

			// change or fixed point format to float
			$result['pkt'] = round($result['pkt']/100.0, 2);

			$results[] = array_intersect_key($result, array_flip(array('platz','comp_name','PerId','vorname','nachname','pkt','WetId','GrpId','cat_name')));

			$points += $result['pkt'];
			$comps[$result['WetId']] += $result['pkt'];
			$used++;
		}
		if ($max_comps)	// mark and remove points from not counting competitions
		{
			foreach($ranking as &$federation)
			{
				arsort($federation['comps'], SORT_NUMERIC);
				$n = 1;
				foreach($federation['comps'] as &$pts)
				{
					if ($n++ > $max_comps)
					{
						$federation['points'] -= $pts;
						$pts = '('.$pts.')';
					}
				}
			}
		}
		usort($ranking, function($a, $b)
		{
			$ret = $b['points'] - $a['points'];
			if (!$ret) $ret = strcasecmp($a['name'], $b['name']);
			return $ret;
		});
		$abs_rank = $rank = $last_points = 0;
		foreach($ranking as &$federation)
		{
			$abs_rank++;
			if ($federation['points'] != $last_points)
			{
				$rank = $abs_rank;
			}
			$federation['rank'] = $rank;
			$last_points = $federation['points'];
		}
		$names = array(
			'date', 'filter', 'best_results', 'aggregate_by',
			'use_cup_points', 'min_cats', 'max_comps'
		);
		$params = func_get_args();
		if (count($params) < count($names)) $params = array_pad($params, count($names), null);
		$params['end'] = $date;
		$ranking['params'] = array_combine($names, array_slice($params, 0, count($names)));
		$ranking['competitions'] = $competitions;
		$ranking['categorys'] = $categorys;

		//_debug_array($ranking);exit;
		return $ranking;
	}

	/**
	 * Calculates a ranking of type $rls->window_type:
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
	 * @param array &$comps=null if array on return WetId => comp array
	 * @param &$max_comp=null on return max. number of competitions counting
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
	 * 10.06.2006: EYC nicht-europ. Teiln. zaehlen nicht fuer Punkte
	 * 01.01.2009: Int. competition use "averaged" points for ex aquo
	 */
	function &ranking (&$cat,&$stand,&$start,&$comp,&$ret_pers,&$rls,&$ret_ex_aquo,&$not_counting,$cup='',
		array &$comps=null, &$max_comp=null)
	{
		if ($cup && !is_array($cup))
		{
			$cup = $this->bo->cup->read($cup);
		}
		if (!is_array($cat))
		{
			$cat = $this->bo->cats->read($cat);
		}
		if ($this->debug) echo "<p>boranking::ranking(cat='$cat[rkey]',stand='$stand',...,cup='$cup[rkey]')</p>\n";

		if (!$stand || $stand == '.')	// last comp. before today
		{
			$stand = date ('Y-m-d',time());

			$comp = $this->bo->comp->last_comp($stand,$cat['GrpIds'],$cat['nation'],$cup['SerId']);
		}
		elseif (!preg_match('/^[0-9]{4}-[0-9]{1,2}-[0-9]{1,2}/',$stand))
		{
			if (!is_array($stand))
			{
				$comp = $this->bo->comp->read($stand);
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
			if ($this->bo->cats->cat2old[$cat['rkey']]) $cats[] = $this->bo->cats->cat2old[$cat['rkey']];

			if (!$this->bo->comp->next_comp_this_year($comp['datum'],$cats,$cat['nation'],$cup['SerId']))
			{
				$stand = (int)$comp['datum'] . '-12-31';	// no further comp. -> stand 31.12.
			}
		}
		if ($this->debug) echo "<p>boranking::ranking: stand='$stand', comp='$comp[rkey]'</p>\n";

		if ($cup)
		{
			$max_comp = $this->bo->cup->get_max_comps($cat['rkey'],$cup);

			if ((int) $stand >= 2000 && !in_array($cat['rkey'],(array)$cup['gruppen']))
			{
				return false;			// no cup (ranking) defined for that group
			}
		}
		else
		{
			// $rls = used ranking-system
			$rls = $cat['vor'] && $stand < $cat['vor'] ? $cat['vor_rls'] : $cat['rls'];

			if (!$rls || !($rls = $this->bo->rls->read(array('RlsId' => $rls))))
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
					if (!($first_comp = $this->bo->comp->last_comp($stand,$cats,$cat['nation'],0,$rls['window_anz'])))
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
			$results =& $this->bo->result->cup_results($cup,$cat['GrpIds'],$stand,
				stristr($cup['rkey'],'EYC') || stristr($cup['rkey'],'EYS') ? $this->european_nations : false);
		}
		else
		{
			if (!$rls || !($rls['window_type'] != 'wettk_athlet' && $rls['end_pflicht_tol'] &&
				$this->bo->cats->age_group($cat,$stand,$from_year,$to_year)))
			{
				$from_year = $to_year = 0;
			}
			$results =& $this->bo->result->ranking_results($cat['GrpIds'],$stand,$start,$from_year,$to_year);
		}
		$pers = false;
		$pkte = $anz = $platz = array();
		foreach($results as $result)
		{
			$id = $result['PerId'];
			$nc = false;
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
				$nc = true;
				if ($cup && $cup['split_by_places'] != 'only_counting')
				{
					++$platz[$result['platz']][$id];
				}
			}
			$pers[$id]['results'][$result['WetId']] = $result['platz'].".\n".
				($nc ? '(' : '').sprintf('%04.2f',$result['pkt']).($nc ? ')' : '');

			if (is_array($comps) && !isset($comps[$result['WetId']]))
			{
				$comps[$result['WetId']] = $this->bo->comp->read($result['WetId']);
			}
		}
		if (!$pers)
		{
			return ($pers);
		}
		arsort ($pkte);

		if ($cup && $cup['SerId'] == 60)	// EYC 2003. not sure what this is for, why only 2003?
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
}
