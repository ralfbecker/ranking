<?php
/**
 * eGroupWare digital ROCK Rankings - result display
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
require ("open_db.inc.php");

$wettk=prepare_var('comp','strtoupper',array('GET',0));
$gruppe=prepare_var('cat','strtoupper',array('GET',1));
$feldfakt = prepare_var('show_calc','int',array('GET'),0);	// mit Feldfaktorberechnung

setup_grp ($gruppe);			// defaults fuer Gruppe setzen
require ($grp_inc);

read_wettk ($wettk);

$rls = $gruppe->vor && $wettk->datum < $gruppe->vor ? $gruppe->vor_rls : $gruppe->rls;
$has_feldfakt = $rls && $wettk->feld_pkte && $wettk->faktor && $gruppe->rkey != "OMEN";
if (!$has_feldfakt) $feldfakt = 0;

if ($feldfakt) {
   preg_match("/([0-9]{4})-([0-9]{1,2})-([0-9]{1,2})/",$wettk->datum,$date);
   $stand = date("Y-m-d",mktime(0,0,0,$date[2],$date[3]-1,$date[1]));

   require ("calc_rang.inc.php");
   calc_rangliste($gruppe,$stand,$anfang,$wettk,$pers,$rls,$ex_aquo,$ng);

   $feldfakt = is_array ($pers);
   $max_pkte = get_pkte ($wettk->feld_pkte,$pkte);
}
$res = my_query("SELECT r.platz,r.pkt,r.cup_platz,r.cup_pkt,p.*,Federations.*" .
		" FROM Personen p".
		" JOIN Results r USING(PerId)".fed_join('r').
		" WHERE r.WetId=$wettk->WetId AND r.GrpId=$gruppe->GrpId AND r.platz > 0" .
		" ORDER BY r.platz,p.nachname,p.vorname");

do_header ($wettk->name,"<b>$wettk->name</b><br />".wettk_datum($wettk).
                        "<p><b>$gruppe->name</b>");
echo '<table width="99%" align="center">'."\n\t".'<tr align="center" bgcolor="#c0c0c0" class="th">'."\n";

echo "\t\t<td><b>".$t_rank.'</b></td>'."\n\t\t".'<td colspan="2" align="left"><b>'.$t_name."</b></td>\n".
	($gruppe->from_year && $gruppe->to_year ? "\t\t<td><b>$t_agegroup</b></td>\n" : '').
	"\t\t<td><b>".($wettk->nation ? ($t_verband_del ? $t_sektion : $t_city) : $t_nation)."</b></td>\n\t\t<td><b>".
	(!$has_feldfakt || $feldfakt ? '' : '<a href="'.$GLOBALS['dr_config']['result'].'comp='.$wettk->WetId.'&amp;cat='.$gruppe->GrpId.'&amp;show_calc=1" target="_self">').
	$t_points. (!$has_feldfakt || $feldfakt ? '' : '</a>')."</b></td>\n";

if ($feldfakt)
{
	echo "\t\t<td>$t_rank in<br />CUWR</td>\n\t\t<td>$t_num_ex_aquos</td>\n\t\t<td>$t_points</td>\n";
}
echo "\t</tr>\n";

while(($row = mysqli_fetch_object($res)))
{
	$rows[] = clone($row);
}
// calculation the ex aquos on each place
$ex_aquo_place = array();
foreach($rows as $row)
{
	if (!$eyc_platz || in_array($row->nation,$european_nations))	// ignore non-europ. nation for EYS
	{
		$ex_aquo_place[isset($row->eyc_platz) ? $row->eyc_platz : $row->platz]++;
	}
}
// show cup points for eyc
$cup_pkte = stristr($wettk->rkey,'EYC') && ($y = (int) $wettk->rkey) >= 6 && $y < 90;

for ($last_platz = 0, $class = 'row_on', $n = 0; $row = $rows[$n++]; $last_platz=$row->platz, $class = $class == 'row_on' ? 'row_off' : 'row_on')
{
	echo "\t".'<tr align="center" bgcolor="#f0f0f0" class="'.$class.'">'."\n\t\t<td>".
		($row->platz==999 ? $t_disqualified : (!$last_platz || $last_platz!=$row->platz ? $row->platz.'.' : '&nbsp;'))."</td>\n";
	per_link ($row,$gruppe);

	if ($cup_pkte) $row->pkt = $row->cup_pkt;	// we could also depend on isset($row->cup_platz)
	$pkt = isset($row->pkt) ? sprintf('%4.2f',$row->pkt / 100.0) : '';
	echo "\t\t<td>$pkt</td>\n";

	if ($feldfakt)
	{
		if ($platz = $pers[$row->PerId]->platz)
		{
			for ($i = $pkt = 0; $i < $ex_aquo[$platz]; $i++)
			{
				$pkt += $pkte[$platz+$i];
			}
			$pkt /= $ex_aquo[$platz];
			$pkt = (int)floor($pkt);	// rounding down!
		}
		else
		{
			$pkt = '';
		}
		echo "\t\t<td>$platz</td>\n\t\t<td>$ex_aquo[$platz]</td>\n\t\t".'<td align="right">'.$pkt."</td>\n";

		$sum_pkte += $pkt;
	}
	echo "\t</tr>\n";
}
if ($feldfakt)
{
	echo "\t".'<tr valign="bottom">'."\n\t\t".'<td colspan="6">==> <b>'.$t_fieldfaktor.'</b> = '.
		$t_sum.' / '.$t_maximum.' = '.$sum_pkte.' / '.$max_pkte.' = <b>'.sprintf('%1.2f',$sum_pkte/$max_pkte)."</b></td>\n".
		"\t\t".'<td align="right">'.$t_sum."</td>\n\t\t".'<td align="right">-------<br />'.$sum_pkte."</td>\n\t</tr>\n";
}
echo "</table>\n";

if ($wettk->homepage) see_also('<li>Internet: '. wettk_link_str( $wettk,$wettk->name )."</li>\n");

if ($gruppe->rkey == "MEN")		// nach Open suchen
{
	$res = my_query("SELECT r.platz" .
			 " FROM Results r,Gruppen g" .
			 " WHERE r.GrpId=g.GrpId AND r.WetId=$wettk->WetId".
			 " AND g.rkey='OMEN'");
	if ($res && mysqli_fetch_object( $res ))
	{
		see_also ('<li><a href="'.$GLOBALS['dr_config']['result'].'comp='.$wettk->rkey.'&amp;cat='.OMEN.'" target="_self">OPEN '.$wettk->name."</a>.</li>\n");
	}
}
if ($gruppe->rkey != "OMEN")
{
	if ($rls && $wettk->faktor > 0)
	{
		see_also ('<li><a href="'.$GLOBALS['dr_config']['ranglist'].'comp='.$wettk->WetId.'&amp;cat='.$gruppe->GrpId.'" target="_self">'.
			$t_cuwr.'</a> '.$t_after.' '.$wettk->name."</li>\n");
	}
	if ($has_feldfakt && !$feldfakt)
	{
		see_also ('<li><a href="'.$GLOBALS['dr_config']['result'].'comp='.$wettk->WetId.'&amp;cat='.$gruppe->GrpId.'&amp;show_calc=1" target="_self">'.
			$t_fieldfaktor.'-'.$t_calculation."</a>.</li>\n");
	}
	if ($wettk->serie)
	{
		$serie = $wettk->serie; read_serie ($serie);
		if (grp_in_grps($gruppe->rkey,$serie->gruppen))
		{
			see_also ('<li><a href="'.$GLOBALS['dr_config']['ranglist'].'comp='.$wettk->WetId.'&amp;cat='.$gruppe->GrpId.'&amp;cup='.$serie->SerId.
				'" target="_self">'.$serie->name.'</a> '.$t_after.' '.$wettk->name.".</li>\n");
		}
	}
}
if (!$wettk->nation && (int)$wettk->datum >= 2005 && $wettk->quota && preg_match('/^[0-9]{2}_[^I]+/',$wettk->rkey))
{
	$cats = array();
	$res = my_query("SELECT DISTINCT GrpId FROM Results WHERE WetId=$wettk->WetId AND platz > 0 ORDER BY WetId");
	while ($res && ($row = mysqli_fetch_object($res)))
	{
		$cats[] = $row->GrpId;
	}
	// all valid Combinations
	$valid_cats = array(
		'combined (lead &amp; boulder)' => array(1,2,5,6),
		'lead' => array(1,2),
		'boulder' => array(5,6),
		'speed'    => array(23,24),
	);
	$links = array();
	foreach($valid_cats as $name => $vcats)
	{
		if (count(array_intersect($cats,$vcats)) == count($vcats) && in_array($gruppe->GrpId,$vcats))
		{
			$links[] = '<a href="'.$GLOBALS['dr_config']['nat_team_ranking'].'comp='.$wettk->WetId.'&amp;cat='.implode(',',$vcats).'" target="_self">'.$name.'</a>';
		}
	}
	see_also ('<li>'.$t_nat_team_ranking.': '.implode(', ',$links)."</li>\n");
/* combined ranking
	if ((int)$wettk->datum >= 2006)
	{
		// combined ranking
		$valid_cats = array(
			'MEN' => array(
				'GrpId' => 45,
				'GrpIds' => array(1,6,23),
			),
			'WOMEN' => array(
				'GrpId' => 42,
				'GrpIds' => array(2,5,24),
			),
		);
		$links = array();
		foreach($valid_cats as $name => $data)
		{
			if (count(array_intersect($cats,$data['GrpIds'])) > 1 && in_array($gruppe->GrpId,$data['GrpIds']))
			{
				$links[] = '<a href="'.$GLOBALS['dr_config']['ranglist'].'comp='.$wettk->WetId.'&amp;cat='.$data['GrpId'].'" target="_blank">'.$name.'</a>';
			}
		}
		if ($links)
		{
			see_also ('<li>'.$t_combined_ranking.': '.implode(', ',$links)."</li>\n");
		}
	}*/
}
do_footer();
