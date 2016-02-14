<?php
/**
 * eGroupWare digital ROCK Rankings - Sektionenwertung
 *
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2002-12 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$
 */

if (basename($_SERVER['PHP_SELF']) == basename(__FILE__) &&
	$_SERVER['HTTP_HOST'] != 'localhost' && substr($_SERVER['HTTP_HOST'], -6) != '.local')
{
	include_once('cache.php');
	do_cache();
}
//$debug = True;

require ("open_db.inc.php");

$from = $stand = prepare_var('from','addslashes,strtoupper',array('GET',0),'.');
$gruppe=prepare_var('cat','strtoupper',array('GET',1));
$serie =prepare_var('cup','strtoupper',array('GET',2));		// Serie statt Rangliste berechnen
$show_calc = prepare_var('show_calc','int',array('GET'),0);
$results_per_wettk = /*prepare_var('max','int',array('GET'),*/$gruppe ? 2 : 1;//);

$window_anz = 12;	// letzten 12 monate zaehlen

if ($debug) echo "<p>$_SERVER[PHP_SELF]: stand='$stand', gruppe='$gruppe', serie='$serie'</p>\n";

if (!$gruppe)	// Sektionenrangliste: bestes Ergebnis jeder Kategorie (Erwachsene und Jugend)
{
	$g='GER_M';
	setup_grp ($g);			// defaults fuer GER einrichten
	require $grp_inc;

	if ($stand == '.' || preg_match('/^[\d]{4}-[\d]{2}-[\d]{2}$/', $stand))
	{
		if ($stand == '.') $stand = date('Y-m-d');
		$stand = "w.datum <= '$stand'";
	}
	else
	{
		$stand = "w.rkey='$stand'";
	}
	if (($res = my_query($sql="SELECT DISTINCT w.* FROM Wettkaempfe w,Results r".
		" WHERE w.WetId=r.WetId AND w.nation='GER' AND w.serie IS NOT NULL AND w.fed_id IS NULL AND $stand".
		" ORDER BY w.datum DESC LIMIT 1")) && mysqli_num_rows($res))
	{
		$wettk = mysqli_fetch_object ($res);
		$stand = $wettk->datum;

		// check if $wettk is last comp in the year
		if (($res = my_query($sql="SELECT DISTINCT w.* FROM Wettkaempfe w,Results r".
			" WHERE w.WetId=r.WetId AND w.nation='GER' AND w.serie IS NOT NULL AND w.fed_id IS NULL AND w.datum>'$stand'".
			" ORDER BY w.datum ASC LIMIT 1")) && mysqli_num_rows($res) &&
			($next_wettk = mysqli_fetch_object($res)) && (int)$wettk->datum !== (int)$next_wettk->datum)
		{
			$stand = (int)$wettk->datum.'-12-31';
		}
	}
	else
	{
		die("No compeition found!\n");
	}
	$date = explode('-', $stand);
	$anfang = date("Y-m-d",mktime(0,0,0,$date[1]-$window_anz,$date[2],$date[0]));

	if ($debug) echo "<p>stand='$stand', anfang='$anfang'</p>\n";

	$res = my_query($sql = "SELECT verband,fed_url,wettk.WetId,wettk.rkey,wettk.name,wettk.dru_bez,wettk.datum,".
		"grp.GrpId,grp.rkey AS grkey,grp.GrpId,grp.name AS gname,per.PerId,per.nachname,per.vorname,res.platz,res.pkt".
		" FROM Wettkaempfe wettk".
		" JOIN Results res USING(WetId)".
		" JOIN Gruppen grp USING(GrpId)".
		" JOIN Personen per USING(PerId)".fed_join('per','YEAR(wettk.datum)').
		" WHERE wettk.nation='GER' AND wettk.serie IS NOT NULL AND wettk.fed_id IS NULL AND".
		" res.platz > 0 AND '$anfang' < wettk.datum AND wettk.datum <= '$stand'".
		" ORDER BY verband,wettk.datum,grp.name,res.platz");
}
else	// Sektionenwertung pro Kategorie
{
	setup_grp ($gruppe);			// defaults fuer Gruppe einrichten
	require $grp_inc;

	if ($debug) echo "<pre>".print_r($gruppe,true)."</pre>\n";

	if ($serie)
	{
		read_serie ($serie);
		get_pkte($serie->pkte,$serien_pkte);
	}

	if ($stand == '.')			// . == letzter Wettk vor akt. Datum
	{
		$stand = date ("Y-m-d",time());
		$res = my_query($sql="SELECT DISTINCT w.* FROM Wettkaempfe w,Results r".
			" WHERE w.WetId=r.WetId AND r.GrpId IN ($gruppe->GrpIds) AND ".
			($gruppe->nation ? "w.nation='$gruppe->nation'" : "ISNULL(w.nation)")." AND ".
			($serie ? "w.serie=$serie->SerId" : "w.faktor>0.0").
			" AND w.datum<='$stand'".
			" ORDER BY w.datum DESC LIMIT 2");
	}
	else
	{
		$res = my_query($sql="SELECT * FROM Wettkaempfe WHERE rkey='$stand'");
	}
	if ($res > 0 && mysqli_num_rows($res))
	{
		$wettk = mysqli_fetch_object ($res);
		$stand = $wettk->datum;

		$sql = '';
		$gruppen = $gruppe->mgroups ? $gruppe->mgroups : array($gruppe->rkey,$grp2old[$gruppe->rkey]);
		foreach($gruppen as $grp)
		{
			if (strlen($grp))
			{
				$sql .= ($sql ? ' OR ':'').
					"find_in_set('$grp',if(instr(gruppen,'@'),left(gruppen,instr(gruppen,'@')-1),gruppen))".
					" OR '$grp' regexp if(instr(gruppen,'@'),left(gruppen,instr(gruppen,'@')-1),gruppen)";
			}
		}
		// auf weiteren wettk im akt. Jahr testen
		$res = my_query($sql="SELECT * FROM Wettkaempfe WHERE ".
			($gruppe->nation ? "nation='$gruppe->nation'" : "ISNULL(nation)")." AND ".
			($serie ? "serie=$serie->SerId" : "faktor>0.0").
			" AND datum>'$wettk->datum' AND datum<='".(0+$wettk->datum)."-12-31'".
			" AND ($sql) ORDER BY datum ASC LIMIT 1");

		if ($res > 0 && ($next_wettk = mysqli_fetch_object($res)))
		{
			if ($debug) echo "<p>next wettk: $next_wettk->name, $next_wettk->datum, '$next_wettk->gruppen'</p>\n";
		}
		else
		{
			$stand = (0+$wettk->datum) . '-12-31';	// kein weiterer wettk -> Stand 31.12.
		}
	}
	if ($debug) echo "<p>stand='$stand'</p>\n";

	if ($serie)
	{
		$valid = "wettk.serie = $serie->SerId";
	}
	else
	{
		$date = explode('-', $stand);
		$anfang = date("Y-m-d",mktime(0,0,0,$date[1]-$window_anz,$date[2],$date[0]));

		$valid = "'$anfang' < wettk.datum AND wettk.faktor > 0.0";
	}
	$year = (int)$stand;
	$res = my_query($sql = "SELECT verband,fed_url,wettk.WetId,wettk.rkey,wettk.name,wettk.dru_bez,wettk.datum,".
		"grp.GrpId,grp.rkey AS grkey,grp.GrpId,grp.name AS gname,per.PerId,per.nachname,per.vorname,res.platz,res.pkt".
		" FROM Wettkaempfe wettk".
		" JOIN Results res USING(WetId)".
		" JOIN Gruppen grp USING(GrpId)".
		" JOIN Personen per USING(PerId)".fed_join('per','YEAR(wettk.datum)').
		" WHERE grp.GrpId IN ($gruppe->GrpIds) AND res.platz > 0 AND $valid AND wettk.datum <= '$stand'".
		" ORDER BY verband,wettk.datum,grp.name,res.platz");
}
if ($debug) echo "<p>sql='$sql'</p>\n";

$rang = array();
$akt_verband = '';
while ($row = mysqli_fetch_object($res))
{
	if ($debug) echo "<pre>".print_r($row,true)."</pre>\n";

	// serien-punkte gem pkt-system der serie berechnen
	if ($serie) $row->pkt = 100 * (int) $serien_pkte[$row->platz];

	// neuer Verband
	if ($row->verband != $akt_verband)
	{
		if ($akt_verband)
		{
			$rang[$akt_verband] = array(
				'pkte'    => $pkte,
				'verband' => $akt_verband,
				'results' => $results,
				'url'     => $fed_url,
			);
		}
		$akt_verband = $row->verband;
		$fed_url = $row->fed_url;
		$pkte = 0;
		$results = array();
		$akt_wettk = $akt_grp = '';
	}
	if ($akt_wettk != $row->WetId || $akt_grp != $row->GrpId)
	{
		$anz_wettk = 0;
		$akt_wettk = $row->WetId;
		$akt_grp   = $row->GrpId;
	}
	if ($anz_wettk < $results_per_wettk)
	{
		$pkte += $row->pkt;
		++$anz_wettk;
		$row->valued = true;
	}
	$results[] = $row;
}
if ($akt_verband)
{
	$rang[$akt_verband] = array(
		'pkte'    => $pkte,
		'verband' => $akt_verband,
		'results' => $results,
		'url'     => $fed_url,
	);
}

function verband_pkte_cmp($r1,$r2) {
	if ($r1['pkte'] == $r2['pkte'])
	{
		return strcasecmp($r1['verband'],$r2['verband']);
	}
	return $r1['pkte'] > $r2['pkte'] ? -1 : 1;
}
uasort($rang,'verband_pkte_cmp');

if ($debug) echo "<pre>".print_r($rang,true)."</pre>";

do_header($t_sektionranking.($serie ? ' '.$serie->name : '')." $t_after ".
	(is_object($wettk)?$wettk->name:datum($stand)),
	($gruppe ? $t_sektionranking_cat.'<br>'.($serie?"<b>$serie->name</b>":'')."<p><b>$gruppe->name</b><br>" : $t_sektionranking."<br>").
	(is_object($wettk) ? "<i><font size=\"+0\">$t_after</font></i><br>$wettk->name<br>".datum($wettk->datum):"").
	(!is_object($wettk)||$wettk->datum != $stand ? "<br><i><font size=\"+0\">$t_stand</font></i><br><b>".datum($stand).'</b>' : ''));

?>
<table width="100%">
	<tr bgcolor="#808080" align="center">
<?php
$link = 'sektionenwertung.php?show_calc='.(!$show_calc ? 1 : ($show_calc != 2 ? 2 : 0)).
	($from != '.' ?'&from='.$from : '').'&cat='.$gruppe->rkey.($serie ? '&cup='.$serie->rkey : '');
	//'&max='.$results_per_wettk;

echo "\t\t<td><b>$t_rank</b></td>\n".
	"\t\t<td colspan=\"5\" align=\"left\"><b>$t_sektion</b></td>\n".
	"\t\t<td><b><a href=\"$link\" target=\"_self\">$t_points</a></b></td>\n".
	"\t</tr>\n";

$abs_rank = $last_pkte = $last_rank = 0;
foreach($rang as $verband => $data)
{
	$show_bold = $show_calc ? '<b>' : '';
	$show_bold_off = $show_calc ? '</b>' : '';
	++$abs_rank;
	$rank = $data['pkte'] == $last_pkte ? '&nbsp;' : $show_bold.$abs_rank.'.'.$show_bold_off;

	$pkte = $show_bold.sprintf('%0.2lf',$data['pkte'] / 100.0).$show_bold_off;

	if ($data['url'])
	{
		$verband = '<a href="'.htmlspecialchars($data['url']).'" target="_blank">'.htmlspecialchars($verband).'</a>';
	}
	echo "\t<tr bgcolor=\"#f0f0f0\">\n\t\t<td align=\"center\">$rank</td>\n\t\t<td colspan=\"5\">$show_bold$verband$show_bold_off</td>\n\t\t<td align=\"center\">$pkte</td>\n\t</tr>\n";

	if ($show_calc)
	{
		foreach($data['results'] as $result)
		{
			if (!$result->valued && $show_calc < 2) continue;

			echo "\t<tr bgcolor=\"#f0f0f0\">\n\t\t<td>&nbsp;</td>\n";

			foreach(array(
				'<a href="result.php?comp='.$result->WetId.'&cat='.$result->GrpId.'">'.htmlspecialchars($result->name).'</a>' => 'left',
				$result->gname => 'left',
				$result->platz.'.' => 'center',
				'<a href="pstambl.php?person='.$result->PerId.'&cat='.$result->GrpId.'">'.htmlspecialchars($result->nachname).'</a>' => 'left',
				'<a href="pstambl.php?person='.$result->PerId.'&cat='.$result->GrpId.'">'.htmlspecialchars($result->vorname).'</a>' => 'left',
				($result->valued?'':'(').sprintf('%0.2lf',$result->pkt/100.0).($result->valued?'':')') => 'center'
			) as $str => $align)
			{
				echo "\t\t<td align=\"$align\">$str</td>\n";
			}
			echo "\t</tr>\n";
		}
	}

	$last_pkte = $data['pkte'];
	$last_rank = $rank;
}
echo "</table>\n<br>\n";

printf($t_rangmode,$t_sektionranking,$results_per_wettk,$window_anz,$t_month.' '.$t_result_per_wettk);
if ($show_calc == 2 && $results_per_wettk) print (" " . $t_brakets);

do_footer();
