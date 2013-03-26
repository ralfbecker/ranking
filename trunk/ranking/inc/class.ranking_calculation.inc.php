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
	 * Calculate an aggregated ranking eg. national team ranking or sektionen wertung
	 *
	 * @param string|int $date date of ranking, "." for current date or WetId or rkey of competition
	 * @param array $filter=array() to filter results by GrpId or WetId
	 * @param int $best_results=null use N best results per category and competition
	 * @param string $by='nation' 'nation', 'fed_id', 'fed_parent' or 3-letter nation code
	 * @param int $window=12 number of month in ranking
	 * @return array of array with values 'rank', 'name', 'points', 'results' (array)
	 */
	public function aggregated($date='.', array $filter=array(), $best_results=3, $by='nation', $window=12)
	{
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
		if ($date == '.' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date))
		{
			if ($date == '.') $date = date('Y-m-d');

			$date_comp = $this->bo->comp->last_comp($date, $filter['GrpId'], $filter['nation']);
		}
		else
		{
			if (!($date_comp = $this->bo->comp->read($date)))
			{
				throw new Exception ("Competition '$date' NOT found!");
			}
		}
		$date = $date_comp['datum'];

		// if no single competition, use date filter for window
		if (!isset($filter['comp']))
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
			$filter[] = $this->bo->comp->table_name.'.nation='.$this->bo->db->quote($filter['nation']);
			unset($filter['nation']);
		}
		$filter[] = 'platz > 0';
		$filter[] = $this->bo->comp->table_name.'.faktor > 0';

		$ranking = array();
		$last_aggr = $last_comp = $last_cat = null;
		$used = 0;
		//_debug_array($filter);
		$join = ' JOIN '.$this->bo->comp->table_name.' USING(WetId)';
		$join .= ' JOIN '.$this->bo->cats->table_name.' USING(GrpId)';
		$join .= ' JOIN '.ranking_athlete::ATHLETE_TABLE.' USING(PerId)';
		$join .= str_replace('USING(fed_id)','ON a2f.fed_id='.ranking_athlete::FEDERATIONS_TABLE.'.fed_id', $this->bo->athlete->fed_join('Personen','YEAR(Wettkaempfe.datum)'));
		$extra_cols = $this->bo->comp->table_name.'.name AS comp_name,'.$this->bo->cats->table_name.'.name AS cat_name,nachname,vorname,'.ranking_athlete::FEDERATIONS_TABLE.'.nation,verband,fed_url,'.ranking_athlete::FEDERATIONS_TABLE.'.fed_id AS fed_id,fed_parent,acl.fed_id AS acl_fed_id,geb_date,'.ranking_athlete::ATHLETE_TABLE.'.PerId AS PerId';
		foreach($this->bo->result->aggregated_results($filter, $extra_cols, $join, $by.','.$this->bo->comp->table_name.'.datum,WetId,GrpId,platz') as $result)
		{
			if (!isset($last_aggr) || $last_aggr != $result[$by])
			{
				switch($by)
				{
					case 'fed_id':
						$name = $result['verband'];
						$url = $result['fed_url'];
						break;
					default:
						$name = $result[$by];
						$url = '';
						break;
				}
				$ranking[$result[$by]] = array(
					$by => $result[$by],
					'url' => $url,
 					'name' => $name,
					'points' => 0,
					'results' => array(),
				);
				$results =& $ranking[$result[$by]]['results'];
				$points =& $ranking[$result[$by]]['points'];
				$last_aggr = $result[$by];
				$last_cat = $last_comp = null;
			}
			if ($result['WetId'] != $last_comp || $result['GrpId'] != $last_cat)
			{
				$used = 0;
				$last_comp = $result['WetId'];
				$last_cat = $result['GrpId'];
			}
			if ($used >= $best_results) continue;	// no more results counting
			$results[] = array_intersect_key($result, array_flip(array('platz','comp_name','PerId','vorname','nachname','pkt','WetId','GrpId','cat_name')));
			$points += $result['pkt'];
			$used++;
		}
		usort($ranking, function($a, $b) {
			return $b['points'] - $a['points'];
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
