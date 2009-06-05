<?php

/* $Id$ */

/**
 * Berechnet eine Rangliste vom Type $rls->window_type:
 *             monat = $rls->window_anz Monate zaehlen fuer Rangl.
 *             wettk = $rls->window_anz Wettkaempfe ---- " -----
 * Es werden, wenn definiert, nur die $rls->best_wettk besten Ergebnisse beruecksichtigt.
 *
 * @param string/gruppe &$gruppe = rkey der Gruppe, on return: Gruppen-Obj.
 * @param string &$stand  = rkey eines Wettk., Datum YYYY-MM-DD oder "."=akt.Datum,
 * 	on return: Datum des letzten Wettk. der Rangliste
 * @param string &$anfang = on return: Anfangsdatum der Rangl.,dh. aeltester Wettk.
 * @param wettkampf &$wettk  = on return: Wettk. zu dessen Stand Rangliste calculiert
 * @param array &$pers   = on return: Rangliste als Array nach PerId indiziert
 * @param ranglistensystem $rls    = on return: zur Berechnung verw. RanglistenSystem
 * @param array $ex_aquo= on return: Array mit Anzahl ex aquo nach platz
 * @param array $nicht_gewertet = on return: Array mit String aller WetId die
 *	nicht gewertet wurden nach PerId
 * @param string $serie  = rkey der Serie oder "" bei Rangliste
 * @return array Rangliste nach Ranglistenplatz sortiert
 *
 * Achtung:   Nicht beruecksichtigt sind die folgenden Parameter:
 *             - $rls->window_type=="wettk_athlet", dh. alte Schweizer Rangl.
 *             - $rls->min_wettk, dh. min. Anzahl Wettk. um gewertet zu werden
 *             - $wettk->open, dh. nur bessere Erg. von. Wettk. und Open verw.
 *             - $serie->max_rang, dh. max. Anz. Wettk. der Serie in Rangliste
 *             - $serie->faktor, dh. Faktor fuer Serienpunkte
 *            Diese Parameter werden im Moment von keiner Rangliste mehr verw.
 *
 * Juni 2006: fuer EYC zaehlen nur die Europ. Nationen, nicht-Europaer behalten
 * 	Platz im Ergebnis, werden aber für die Punkte nicht gewertet (Europ. ruecken auf)
 * 01.05.2001:	Jahrgaenge beruecksichtigen, dh. wenn in Gruppe from_year und
 *		to_year angegeben ist und rls->window_type != "wettk_athlet" &&
 *		rls->end_pflich_tol (!= 0 | I_EMPTY | nul) dann werden nur
 *		solche Athleten in die Rangliste aufgenommen, die zum Datum
 *		der Rangliste innerhalb der Jahrgangsgrenzen liegen
 * 07.10.2005: combined WCup (lead+boulder):
 *		icc.inc.php definiert $mgroups, das einem rkey mehrere Gruppen zuordnen kann
 *		Die max. Anzahl bester Ergebnisse gilt fuer jede Gruppe extra!
 * 10.06.2006: EYC nicht-europ. Teiln. zaehlen nicht fuer Punkte
 * 24.03.2008: 2008 overall ranking (no older overall or combinded ranking supported)
 * 27.10.2008: overall ranking requires results (with points > 0!) in 2 or more cats
 */
function calc_rangliste(&$gruppe,&$stand,&$anfang,&$wettk,&$ret_pers,&$rls,&$ret_ex_aquo,
	&$ret_nicht_gewertet,$serie='')
{
	global $t_no_ranking_for,$european_nations,$debug;
	global $sql_platz;

	if ($serie) read_serie ($serie);
	read_gruppe ($gruppe);

	$combined = is_array($gruppe->mgroups) && count($gruppe->mgroups) > 1;	// combined ranking

	if ($debug) echo "<p>calc_rangliste(gruppe='$gruppe->rkey',stand='$stand',serie='$serie->rkey') combined=$combined</p>\n";

	if ($stand == "." && (!$combined || $serie))	// . == letzter Wettk vor akt. Datum
	{
		$stand = date ("Y-m-d",time());
		$res = my_query($sql="SELECT DISTINCT w.* FROM Wettkaempfe w,Results r".
		      " WHERE w.WetId=r.WetId AND r.GrpId IN ($gruppe->GrpIds) AND ".
		      ($gruppe->nation ? "w.nation='$gruppe->nation'" : "ISNULL(w.nation)")." AND ".
		      ($serie ? "w.serie=$serie->SerId" : "w.faktor>0.0").
		      " AND w.datum<='$stand'".
		      " ORDER BY w.datum DESC LIMIT 1");
	}
	else
	{
		$res = my_query($sql="SELECT * FROM Wettkaempfe WHERE rkey='$stand' OR WetId='$stand'");

		if ($combined && !$cup) read_wettk($stand);	// --> fail comp not found
	}
	if ($res > 0 && mysql_num_rows($res))
	{
		$wettk = mysql_fetch_object ($res);
		$stand = $wettk->datum;

		if (!$combined || $serie)
		{
			// auf weiteren wettk im akt. Jahr testen
			$sql = check_group_sql($gruppe);
			$res = my_query($sql="SELECT * FROM Wettkaempfe WHERE ".
				($gruppe->nation ? "nation='$gruppe->nation'" : "ISNULL(nation)")." AND ".
				($serie ? "serie=$serie->SerId" : "faktor>0.0").
				" AND datum>'$wettk->datum' AND datum<='".(0+$wettk->datum)."-12-31'".
				" AND ($sql) ORDER BY datum ASC LIMIT 1");

			if ($res > 0 && ($next_wettk = mysql_fetch_object($res)))
			{
				if ($debug) echo "<p>next wettk: $next_wettk->name, $next_wettk->datum, '$next_wettk->gruppen'</p>\n";
			}
			else
			{
				$stand = (0+$wettk->datum) . '-12-31';	// kein weiterer wettk -> Stand 31.12.
			}
		}
		else
		{
			$anfang = $stand;
		}
	}
	if ($debug) echo "<p>stand='$stand'</p>\n";

	if ($combined)
	{
		$sql = get_combined_sql($gruppe,$wettk,$serie,$max_wettk,$MinCats);
		if ($debug) echo "<p>combined ranking: max_wettk=$max_wettk, MinCats=$MinCats, sql=$sql</p>\n";
	}
	else
	{
		$sql = get_ranking_sql($gruppe,$wettk,$serie,$max_wettk,$anfang,$stand,$rls);
	}
	if (!$sql) return false;	// no ranking defined

	$res = my_query($sql);

	$pers = $pkte = $anz = $platz = array();
	while ($row = mysql_fetch_object ($res))
	{
		$id = $row->PerId;
		$cat = $row->GrpId;
		if ($MinCats && $row->num_cats < $MinCats)
		{
			foreach($row->GrpIds ? explode(',',$row->GrpIds) : array($row->GrpId) as $cat)
			{
				$nicht_gewertet[$id][$row->WetId][$cat] = (int)$row->pkt;
			}
		}
		elseif (!isset($pers[$id]))		// Person neu --> anlegen
		{
			$pers[$id] = $row;
			$pkte[$id] = sprintf("%04.2f",$row->pkt);
			$anz[$id][$cat] = 1;
			++$platz[$row->platz][$id];
		}
		elseif (!$max_wettk || (int) $anz[$id][$cat] < (int)$max_wettk)
		{
			$pkte[$id] = sprintf("%04.2f",$pkte[$id] + $row->pkt);
			$anz[$id][$cat]++;
			++$platz[$row->platz][$id];
		}
		else
		{
			foreach($row->GrpIds ? explode(',',$row->GrpIds) : array($row->GrpId) as $cat)
			{
				$nicht_gewertet[$id][$row->WetId][$cat] = (int)$row->pkt;
			}
			if ($serie->split_by_places != 'only_counting')
			{
				++$platz[$row->platz][$id];
			}
		}
	}
	if (!is_array ($pers))
	{
		$ret_ex_aquo = $ex_aquo;
		$ret_pers = $pers;

		return ($pers);
	}
	arsort ($pkte);

	if ($serie->SerId == 60)
	{
		switch($serie->split_by_places)
		{
			case 'first':
				$max_pkte = current($pkte);
				if (next($pkte) != $max_pkte)
				{
					break;	// kein exAquo of 1. platz ==> fertig
				}
			case 'all':
			case 'only_counting':
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

	do
	{
		$id = key($pkte);
		$pers[$id]->platz = $abs_pl > 1 && current($pkte) == $last_pkte ?
				$last_platz : ($last_platz = $abs_pl);
		$ex_aquo[$last_platz] = 1+$abs_pl-$last_platz;
		$abs_pl++;
		$last_pkte = $pers[$id]->pkt = current($pkte);
		$rang[sprintf("%04d%s%s",$pers[$id]->platz,
					$pers[$id]->nachname,
					$pers[$id]->vorname)] = $pers[$id];
	}
	while (next ($pkte));

	ksort ($rang);			// Array $rang enthaelt jetzt Rangliste

	$ret_ex_aquo = $ex_aquo;
	$ret_pers = $pers;
	$ret_nicht_gewertet = $nicht_gewertet;

	return $rang;
}

function get_ranking_sql($gruppe,$wettk,$serie,&$max_wettk,&$anfang,$stand,&$rls)
{
	global $european_nations,$sql_platz;

	if ($serie)
	{
		$max_wettk = get_max_wettk($serie,$gruppe);

		if ((int)$stand >= 2000 && !grp_in_grps($gruppe->rkey,$serie->gruppen))
		{
			return false;			// keine Serienergebnis definiert
		}
	}
	else
	{ 				// $rls = verwendetets Ranglistensystem
		$rls = $gruppe->vor && $stand < $gruppe->vor ? $gruppe->vor_rls : $gruppe->rls;

		if (!$rls) return false; 		// keine Rangliste definiert

		$res = my_query("SELECT * FROM RangListenSysteme WHERE RlsId='$rls'");
		$rls = mysql_fetch_object ($res);
		$max_wettk = $rls->best_wettk;

		switch ($rls->window_type)
		{
			case "monat":			// Rangliste nach anzahl Monaten
				ereg("([0-9]{4})-([0-9]{1,2})-([0-9]{1,2})",$stand,$date);
				$anfang = date("Y-m-d",mktime(0,0,0,$date[2]-$rls->window_anz,$date[3]+1,$date[1]));
				break;
			case "wettk_athlet":
				fail( "Windowtype 'wettk_athlet' is no longer supported !!!" );
				break;
			case "wettk":			// Rangliste nach anzahl Wettk�mpfe
				$res = my_query("SELECT DISTINCT w.* FROM Wettkaempfe w,Results r".
					" WHERE w.WetId=r.WetId AND r.GrpId IN ($gruppe->GrpIds) AND ".
						($gruppe->nation ? "w.nation='$gruppe->nation'" : "ISNULL(w.nation)").
						" AND w.faktor>0.0 AND w.datum<='$stand'".
						" ORDER BY w.datum DESC LIMIT ".
						($rls->window_anz-1).",1");
				$row = mysql_fetch_object ($res);
				$anfang = $row->datum;
				break;
			case "wettk_nat":
				$res = my_query("SELECT * FROM Wettkaempfe WHERE ".
						($gruppe->nation ? "nation='$gruppe->nation'" :
								"ISNULL(nation)").
						" AND faktor>0.0 AND datum<='$stand'".
						" ORDER BY datum DESC LIMIT ".
						($rls->window_anz-1).",1");
				$row = mysql_fetch_object ($res);
				$anfang = $row->datum;
				break;
		}
	}
	if ($serie)
	{
		$platz = 'r.platz';
		if (stristr($serie->rkey,'EYC'))
		{
			$allowed_nations = $european_nations;

			// seit 2006 (evtl. vorher) zaehlen nur die europ. nation fuer die Platzierung und damit die Punkte
			if (($y = (int)$serie->rkey) >= 6 && $y < 90)
			{
				// Platz wenn nur die $allowed_nations zählen
				$sql_platz = $platz = "CASE WHEN r.platz=999 THEN 999 ELSE (SELECT count(*)".
					" FROM Results r2 JOIN Personen p2 ON r2.PerId=p2.PerId".fed_join('p2').
					" WHERE r2.WetId=r.WetId AND r2.GrpId=r.GrpId".
					" AND nation IN ('".implode("','",$allowed_nations)."')".
					" AND r2.platz < r.platz AND r2.platz > 0)+1 END";
			}
		}
		if ($allowed_nations)
		{
			$allowed_nations = "AND Federations.nation IN ('" . implode("','",$allowed_nations) . "')";
		}
		return "SELECT p.*,Federations.*,$platz AS platz,s.pkt,r.WetId,r.GrpId".
			" FROM Results r,Wettkaempfe w,PktSystemPkte s,Personen p".fed_join('p').
			" WHERE r.WetId=w.WetId AND p.PerId=r.PerId AND r.Platz>0".
			" AND r.GrpId IN ($gruppe->GrpIds) AND w.serie=$serie->SerId".
			" AND $platz=s.platz AND s.PktId=$serie->pkte".
			" AND s.pkt>0 AND w.datum<='$stand' $allowed_nations".
			" ORDER BY r.PerId,s.pkt DESC";
	}
	$use_jahrgang = $rls->window_type!="wettk_athlet" && $rls->end_pflicht_tol
		&& jahrgang( $gruppe,$stand,$from_year,$to_year);

	return "SELECT p.*,Federations.*,r.platz AS place,r.pkt/100.0 AS pkt,r.WetId,r.GrpId".
		" FROM Results r,Wettkaempfe w,Personen p".fed_join('p').
		" WHERE r.WetId=w.WetId AND p.PerId=r.PerId".
		" AND r.GrpId IN ($gruppe->GrpIds) AND r.pkt>0 AND r.Platz>0".
		" AND '$anfang'<=w.datum AND w.datum<='$stand'".
		($use_jahrgang ? " AND NOT ISNULL(p.geb_date) AND $from_year <= YEAR(p.geb_date) AND YEAR(p.geb_date) <= $to_year" :"").
		" ORDER BY r.PerId,r.pkt DESC";
}

function get_combined_sql($cat,$comp,$cup,&$max_wettk,&$MinCats)
{
	$PktSystem = $cup ? $cup->pkte : 2; 	// 2 = UIAA PktSystem
	$MinCats = 2;							// required minimum number of cats a climber has to participate in

	if (!$cup)	// combined/overall ranking of one competition
	{
		return "SELECT sum( PktSystemPkte.pkt ) AS pkt, count( * ) AS num_cats, Personen.*,Federations.*
FROM Results
JOIN Personen USING(PerId)".fed_join()."
LEFT JOIN PktSystemPkte ON Results.platz = PktSystemPkte.platz
AND PktSystemPkte.PktId=$PktSystem
WHERE WetId=$comp->WetId
AND GrpId IN ($cat->GrpIds)
AND Results.platz > 0
GROUP BY PerId
HAVING num_cats >= $MinCats
ORDER BY pkt DESC";
	}
	// combined/overall ranking for a cup
	$max_wettk = get_max_wettk($cup,$cat);

	// to get the num_cats we join with PktSystemPkte as we need a the points > 0 not just a place!
	return "SELECT Results.WetId, Results.GrpId, Results.platz, PktSystemPkte.pkt, (
	SELECT COUNT( DISTINCT v.GrpId )
	FROM Results v
	JOIN PktSystemPkte vp ON v.platz=vp.platz AND vp.PktId=$PktSystem
	WHERE v.GrpId IN ($cat->GrpIds) AND WetId IN (
		SELECT WetId
		FROM Wettkaempfe
		WHERE serie =$cup->SerId
	) AND v.platz > 0 AND vp.pkt > 0 AND v.PerId = Results.PerId
) AS num_cats, Personen.*,Federations.*
FROM Results
JOIN Personen USING(PerId)".fed_join()."
LEFT JOIN PktSystemPkte ON Results.platz = PktSystemPkte.platz AND PktSystemPkte.PktId=$PktSystem
WHERE WetId IN (
	SELECT WetId
	FROM Wettkaempfe
	WHERE serie =$cup->SerId
) AND GrpId IN ($cat->GrpIds) AND Results.platz > 0
HAVING num_cats >= $MinCats
ORDER BY Results.PerId, Results.GrpId, pkt DESC";
}
