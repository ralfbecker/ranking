<?php
/* $Id$ */

if (basename($_SERVER['PHP_SELF']) == basename(__FILE__) && $_SERVER['HTTP_HOST'] != 'localhost')
{
	include_once('cache.php');
	do_cache();
}

require_once ('open_db.inc.php');
include_once ('calc_rang.inc.php');


$stand = $from = prepare_var('comp','addslashes,strtoupper',array('GET',0),$_GET['from'] ? $_GET['from'] : '.');
$gruppe=prepare_var('cat','strtoupper',array('GET',1));
$serie =prepare_var('cup','strtoupper',array('GET',2));		// Serie statt Rangliste berechnen
$show_calc=prepare_var('show_calc','int',array('GET'),0);	// Kalkulation, dh. Wettk.erg. zeigen
$debug = (boolean) $_GET['debug'];

if ($debug) echo "<p>$_SERVER[PHP_SELF]: stand='$stand', gruppe='$gruppe', serie='$serie'</p>\n";

setup_grp ($gruppe,1);			// defaults fuer Gruppe einrichten
include $grp_inc;

if ($serie)
{
	read_serie ($serie);
	get_pkte($serie->pkte,$pkte);
}

if (!function_exists('cmp_pkt'))
{
	function cmp_pkt($a,$b)
	{
		if ($a->pkt == $b->pkt)
		{
			return strcasecmp($a->nachname.$a->vorname,$b->nachname.$b->vorname);
		}
		return $a->pkt > $b->pkt ? -1 : 1;
	}

	class result
	{
		var $platz,$pkt,$PerId,$WetId,$GrpId;

	 	function result($platz,$pkt,$PerId,$WetId,$GrpId)
		{
			$this->platz = $platz;
			$this->pkt = $pkt;
			$this->PerId = $PerId;
			$this->WetId = $WetId;
			$this->GrpId = $GrpId;
		}
	};

	class wettk
	{
		var $WetId,$rkey,$bezeichnung,$dru_bez,$datum;

		function wettk($Id,$rk,$bez,$dbez,$date = '')
		{
			$this->WetId = $Id; $this->rkey = $rk;
			$this->bezeichnung = $bez;
			$this->dru_bez = $dbez;
			$this->datum = $date;
		}
	};
}

$rang = calc_rangliste($gruppe,$stand,$anfang,$wettk,$pers,$rls,$ex_aquo,$nicht_gewertet,$serie);

if (($combined = is_array($gruppe->mgroups) && count($gruppe->mgroups) > 1) && !$serie)
{
	get_pkte(2,$pkte);
}

if ($rang && $show_calc)		// Liste enthaltene Wettk. erzeugen
{
	if (!$gruppe->rls)			// gar keine Rangliste definiert EYC
	{
		$query = "SELECT DISTINCT w.*,r.GrpId FROM Wettkaempfe w,Results r WHERE w.WetId=r.WetId AND r.GrpId IN ($gruppe->GrpIds) AND ";
	}
	else
	{
		if ($serie || $rls->window_type != "wettk_nat") // nur Wettk. mit Ergebnis
		{
			$query = "SELECT w.*,f.ff,f.GrpId FROM Wettkaempfe w,Feldfaktoren f".
				" WHERE w.WetId=f.WetId AND f.GrpId IN ($gruppe->GrpIds) AND ";
		}
		else
		{				// alle Wettk. verwenden
			$query = "SELECT w.*,f.ff,f.GrpId FROM Wettkaempfe w".
				" LEFT JOIN Feldfaktoren f ON ".
				" w.WetId=f.WetId AND f.GrpId IN ($gruppe->GrpIds) WHERE ";
		}
	}
	$query .= ($gruppe->nation ? "w.nation='$gruppe->nation'" : "ISNULL(w.nation)")." AND ".
		($serie ? "w.serie=$serie->SerId" : "'$anfang'<=w.datum")." AND w.datum<='$stand'";

	$res = my_query($query .=" ORDER BY w.datum DESC");
	while (($row = mysql_fetch_object($res)))
	{
		$GrpIds = $row->GrpIds ? explode(',',$row->GrpIds) : array($row->GrpId);
		sort($GrpIds);
		foreach($GrpIds as $GrpId)
		{
			$row->GrpId = $GrpId;
			$wettks[$row->WetId] = clone($row);	// $row is an object!
		}
	}
	global $sql_platz,$sql_pkte;
	$eyc_platz = '';
	if ($sql_platz)	// neuer EYC bei dem nur europäer für die Punkte berücksichtigt werden
	{
		$eyc_platz = ','.$sql_platz.' AS eyc_platz';
	}
	if ($sql_pkte)	// 2009+ international ranking
	{
		$sql_pkte = ','.$sql_pkte.' AS pkt';
		$pkt_join = 'JOIN PktSystemPkte s ON s.PktId='.(int)$serie->pkte.' AND s.platz=r.platz';
	}
	$res = my_query($query = "SELECT * $eyc_platz$sql_pkte FROM Results r $pkt_join WHERE GrpId IN ($gruppe->GrpIds)".
		($wettks ? ' AND r.WetId IN ('.implode(',',array_keys($wettks)).')' : '').
		" AND '$anfang'<=datum AND datum<='$stand' ORDER BY r.WetId,r.platz");

	while ($row = mysql_fetch_object($res))
	{
		if (isset($sql_pkte))
		{
			$row->pkt *= 100;
		}
		elseif (isset($pkte))
		{
			$row->pkt = 100.0 * $pkte[isset($row->eyc_platz) ? $row->eyc_platz : $row->platz];
		}
		$result[$row->WetId][$row->PerId][$row->GrpId] = $row;
	}
}
do_header(($combined && !$serie ? '' : ($serie ? $serie->name : $t_head_cuwr)." $t_after ").
	(is_object($wettk)?$wettk->name:datum($stand)).($combined && !$serie ? ': '.$gruppe->name : ''),
	($combined && !$serie ? '' : ($serie ? '<b>'.$serie->name.'</b>' : $t_cuwr)).'<p><b>'.$gruppe->name.'</b><br />'.
	(is_object($wettk) ? ($combined && !$serie ? '<br />' : '<i><font size="+0">'.$t_after.'</font></i><br />').
	(!$gruppe->mgroups ? '<a href="'.$GLOBALS['dr_config']['result'].'comp='.$wettk->WetId.'&amp;cat='.$gruppe->GrpId.'">' : '').
	$wettk->name.'<br />'.datum($wettk->datum).'</a>' : '').
	(!is_object($wettk)||$wettk->datum != $stand ? '<br /><i><font size="+0">'.$t_stand.'</font></i><br /><b>'.datum($stand).'</b>' : ''));

if (!$rang)
{
   fail( $t_no_ranking_for . $gruppe->name );
}
echo '<table width="99%" align="center">'."\n\t".'<tr bgcolor="#808080" align="center" class="th">'."\n";

$show_calc_link = $GLOBALS['dr_config']['ranglist'].'cat='.$gruppe->GrpId.'&amp;show_calc='.(int)!$show_calc.
	($serie ? '&amp;cup='.$serie->SerId : '').($from && $from != '.' ? '&amp;comp='.$from : '');

echo "\t\t<td><b>$t_rank</b></td>\n\t\t".'<td colspan="2" align="left"><b>'.$t_name."</b></td>\n".
	($gruppe->from_year && $gruppe->to_year ? "\t\t<td><b>$t_agegroup</b></td>\n" : '').
	"\t\t<td><b>".($wettk->nation ? ($t_verband_del ? $t_sektion : $t_city) : $t_nation)."</b></td>\n".
	"\t\t".'<td><b><a href="'.$show_calc_link.'" target="_self">'.$t_points."</a></b></td>\n";

if ($show_calc)
{
	foreach($wettks as $w)
	{
		if ($combined)
		{
			static $cats;
			if (!isset($cats[$w->GrpId]))
			{
				$cat = $w->GrpId;
				read_gruppe($cat);
				$cats[$w->GrpId] = $cat->name;
			}
			$cat = $cats[$w->GrpId];
		}
		if ($combined && $serie)
		{
			$link = $GLOBALS['dr_config']['ranglist'].'comp='.$w->WetId.'&amp;cat='.$gruppe->GrpId;
		}
		else
		{
			$link = $GLOBALS['dr_config']['result'].'comp='.$w->WetId.'&amp;cat='.$w->GrpId;
		}
		echo "\t\t".'<td><a href="'.$link.'" target="_blank">'.
			$w->dru_bez.'<br />'.datum($w->datum,0).($combined ? '<br />'.$cat :
			($serie ? '' : '<br />'.sprintf("%1.2f",$w->ff)))."</a></td>\n";
	}
}
echo "\t<tr>\n";

if (is_array ($rang))
{
	$qualified = array();
}
if ($serie)
{
	list($grps,$championship) = explode('@',$serie->gruppen);
	$grps = explode(',',$grps);
	foreach($grps as $grp)
	{
		list($grp,$anz) = explode('=',$grp);
		list($qualified[strtoupper($grp)]) = explode('+',$anz);
	}
	$anz_qualified = $qualified[$gruppe->rkey];
}
$last_platz=0;
$class = 'row_on';
foreach ($rang as $r)
{
	$bgcolor = !$anz_qualified || $last_platz >= intval($anz_qualified) ? '#f0f0f0' : '#f0e0c0';
	echo "\t".'<tr align="center" bgcolor="'.$bgcolor.'" class="'.$class.'">'."\n\t\t<td>".($last_platz==$r->platz ? '&nbsp;' : $r->platz.'.'). "</td>\n";
	$class = $class == 'row_on' ? 'row_off' : 'row_on';
	per_link ($r,$gruppe);
	printf("\t\t<td>%1.2f</td>\n",$r->pkt);

	if ($show_calc && is_array($wettks))
	{
		foreach($wettks as $w)
		{
			if ($result[$w->WetId][$r->PerId][$w->GrpId]->platz)
			{
				if ($result[$w->WetId][$r->PerId][$w->GrpId]->platz == 999)
				{
					$platz = $t_disqualified;
				}
				else
				{
					$platz = $result[$w->WetId][$r->PerId][$w->GrpId]->platz.'.';
					$platz_eyc = $result[$w->WetId][$r->PerId][$w->GrpId]->eyc_platz;
					if (isset($platz_eyc) && $platz_eyc != $platz)
					{
						$platz .= " ($platz_eyc.)";
					}
				}
				printf ("\t\t<td>%s<br>%s%1.2f%s</td>\n",$platz,
						isset($nicht_gewertet[$r->PerId][$w->WetId][$w->GrpId]) ? "(" : "",
					$result[$w->WetId][$r->PerId][$w->GrpId]->pkt/100.0,
						isset($nicht_gewertet[$r->PerId][$w->WetId][$w->GrpId]) ? ")" : "");
			}
			else
			{
				echo "\t\t<td>&nbsp;</td>\n";
			}
		}
	}
	echo "\t</tr>\n";

	$last_platz = $r->platz;
}
echo "</table>\n";

if ($show_calc && ($serie || !$combined))
{
	echo '<p>';
	if (jahrgang( $gruppe,$stand,$from,$to ))
	{
		printf( $t_jahrgang,$gruppe->name,$from,$to );
	}
	if ($serie)
	{
		$max_serie = get_max_wettk($serie,$gruppe);
		printf ($t_ser_mode,$serie->name,$max_serie ? sprintf($t_best,$max_serie) : $t_all);
		if ($max_serie) print (" " . $t_brakets);
	}
	else
	{
		printf($t_rangmode,$t_cuwr,$rls->best_wettk ? sprintf($t_best,$rls->best_wettk): $t_all,
			$rls->window_anz,$rls->window_type=="monat" ? $t_month : $t_competitions);
		if ($rls->best_wettk) print (" " . $t_brakets);
	}
}
if ($serie)
{
	if ($anz_qualified && $championship != '' && $t_qualified_for != ``)
	{
		read_wettk($championship);
		printf('<p style="background: #f0e0c0;">'.$t_qualified_for."</p>\n",$anz_qualified,$championship->name,wettk_datum($championship));
	}
}
if (!$show_calc)
{
	see_also('<li><a href="'.$show_calc_link.'">'.$t_calculation.'</a> '.($serie ? $serie->name : ($combined ? $gruppe->name : $t_cuwr))."</li>\n");
}
if (is_object ($wettk))
{
	if ($serie)
	{
		if ($gruppe->rls)
		{
			see_also('<li><a href="'.$GLOBALS['dr_config']['ranglist'].'comp='.$wettk->WetId.'&amp;cat='.$gruppe->GrpId.'">'.
				$t_cuwr.'</a> '.$t_after.' '.$wettk->name."</li>\n");
		}
	}
	elseif ($wettk->serie)
	{
		$wk_serie=$wettk->serie; read_serie($wk_serie);
		see_also ('<li><a href="'.$GLOBALS['dr_config']['ranglist'].'comp='.$wettk->WetId.'&amp;cat='.$gruppe->GrpId.'&amp;cup='.$wettk->serie.'">'.
			$wk_serie->name.'</a> '.$t_after.' '.$wettk->name.".</li>\n");
	}
}
if (!$serie && !$combined)
{
	see_also('<li><a href="CUWR_rules.php">'.$t_cuwr_rules."</a></li>\n");
}
// add national team ranking, if international and at least 2005 and NOT EYC
elseif (((int) $serie->rkey > 80 ? 1990 : 2000)+(int) $serie->rkey >= 2005 && !$serie->nation && !strstr($serie->rkey,'_EYC'))
{
	see_also('<li><a href="'.$GLOBALS['dr_config']['nat_team_ranking'].'cup='.$serie->SerId.'&amp;cat='.$gruppe->GrpId.
		'" target="_self">'.$t_nat_team_ranking.'</a> '.$serie->name."</li>\n");
}
do_footer();
