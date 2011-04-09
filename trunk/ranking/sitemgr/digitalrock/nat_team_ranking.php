<?php
/* $Id$ */

if (basename($_SERVER['PHP_SELF']) == basename(__FILE__) && $_SERVER['HTTP_HOST'] != 'localhost')
{
	include_once('cache.php');
	do_cache();
}
require ("open_db.inc.php");

$wettk=prepare_var('comp','strtoupper',array('GET',0));
$cats=prepare_var('cat','strtoupper',array('GET',1));
$cup=prepare_var('cup','strtoupper',array('GET',2));
$show_calc=prepare_var('show_calc','intval',array('GET'));
// allow to simulate overall ranking for years before 2008
if (($test_overall = prepare_var('test_overall','intval',array('GET'))) && !$cup && $wettk)
{
	$test_overall = '&test_overall=1';
}

$valid_cats = array();

if ($cup)	// do a cup ranking
{
	read_serie($cup,true);

	// get all comps of a serie
	$res = my_query("SELECT DISTINCT WetId FROM Wettkaempfe WHERE serie=$cup->SerId");
	$comps = array();
	while ($row = mysql_fetch_object($res))
	{
		$comps[] = $row->WetId;
	}
	$comps = implode(',',$comps);
	$wettk = (int) $comps;
}
read_wettk ($wettk);

if (!$wettk->quota) fail('no quota set !!!');

if (!$cup)
{
	$comps = $wettk->WetId;
	// no overall/combined for a cup ranking
	if ($test_overall || (int)$wettk->datum >= 2008)
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
	'youth speed' => array(56,57,58,59,60,61),
);

// get all cats from existing results of a comp or cup
$res = my_query("SELECT DISTINCT GrpId FROM Results WHERE WetId IN ($comps) AND platz > 0 ORDER BY GrpId");
$cats_found = array();
while ($row = mysql_fetch_object($res))
{
	$cats_found[] = $row->GrpId;
}
if (!count($cats_found))
{
	fail("No results yet !!!");
}

foreach($valid_cats as $name => $vcats)
{
	if (count(array_intersect($cats_found,$vcats)) != count($vcats) &&
		($name != 'overall' || count($cats_found) <= 2 ||	// show overall if we have more then 2 cats
		// no overall for youth
		$name == 'overall' && array_intersect($cats_found,array(15,16,17,18,19,20,56,57,58,59,60,61))))
	{
		unset($valid_cats[$name]);
	}
}
//echo "valid_cats=<pre>".print_r($valid_cats,true)."</pre>\n";

// get the data of all cats
$given_cats = explode(',',$cats);
$cats = $cat_data = array();
foreach($cats_found as $cat)
{
	setup_grp ($cat);			// defaults fuer Gruppe setzen
	$cat_data[$cat->GrpId] = $cat->name;

	if (in_array($cat->GrpId,$given_cats) || in_array($cat->rkey,$given_cats))
	{
		$cats[] = $cat->GrpId;
	}
}
include($grp_inc);

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
		if (count(array_intersect($cats,$vcats)))	// given cat(s) are at least included in this valid cat combination
		{
			$valid = $vcats;
			break;
		}
	}
	if (!$valid)	// no cats ==> use first valid
	{
		reset($valid_cats);
		list($name,$valid) = each($valid_cats);
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
$cats = implode(',',$valid);
$cat_names = implode(', ',$cat_names);

$quota = $wettk->quota;
// force quota for youth to 1, to allow to use a higher quota for registration, it still need to be set!
if (substr($name,0,5) == 'youth')
{
	$quota = 1;
}
//echo "<p>$name: quota=$quota</p>\n"; exit;

if ($cup)
{
	if ($cat->GrpId != $valid[0])
	{
		$cat = $valid[0];
		read_gruppe($cat);
	}
	$max_comps = get_max_wettk($cup,$cat);

	if ($show_calc)
	{
		$res = my_query("SELECT DISTINCT Wettkaempfe.WetId,dru_bez,Wettkaempfe.datum FROM Wettkaempfe".
			" JOIN Results ON Wettkaempfe.WetId=Results.WetId".
			" WHERE platz > 0  AND GrpId IN ($cats) AND serie=$cup->SerId".
			" ORDER BY Wettkaempfe.datum");
		$comp_data = array();
		while ($row = mysql_fetch_object($res))
		{
			$comp_data[$row->WetId] = $row->dru_bez.'<br />'.datum($row->datum);
		}
	}
}
elseif($name == 'overall' && count($valid) > 2)
{
	$min_cats = 2;	// overall ranking requires participation in 2 or more categories!
}

$PktId=2;	// uiaa
$pkte = 's.pkt';
$platz = 'r.platz';
// since 2009 int. cups use "averaged" points for ex aquo competitors (rounded down!)
if ((int)$wettk->datum >= 2009)
{
	$ex_aquos = '(SELECT COUNT(*) FROM Results ex WHERE ex.GrpId=r.GrpId AND ex.WetId=r.WetId AND ex.platz=r.platz)';
	$sql_pkte = $pkte = "(CASE WHEN r.datum<'2009-01-01' OR $ex_aquos=1 THEN $pkte ELSE FLOOR((SELECT SUM(pkte.pkt) FROM PktSystemPkte pkte WHERE PktId=$PktId AND $platz <= pkte.platz AND pkte.platz < $platz+$ex_aquos)/$ex_aquos) END)";
	$pkte .= ' AS pkt';
}
$res = my_query($q="SELECT nation,WetId,GrpId,nachname,vorname,r.platz,$pkte,r.PerId".
		($min_cats > 1 || !$cup && $show_calc ? ",(SELECT COUNT(*) FROM Results v WHERE r.WetId=v.WetId AND r.PerId=v.PerId AND v.platz > 0 AND GrpId IN ($cats)) AS num_cats":'').
		" FROM Personen".fed_join().
		" JOIN Results r ON Personen.PerId=r.PerId".
		" LEFT JOIN PktSystemPkte s ON r.platz=s.platz AND PktId=$PktId".
		" WHERE WetId IN ($comps) AND r.platz > 0".($cats ? " AND GrpId IN ($cats)" : '').
		($min_cats > 1 ? " HAVING num_cats >= $min_cats" : '').
		" ORDER BY nation,WetId,GrpId,r.platz");
//echo "$name, min_cats=$min_cats: $q";

$nations = $nation_comp_pkte = array();
for ($last_nation = '', $last_cat = $last_comp = $anz = $pkte = 0;
     true;
     $last_nation=$row->nation,$last_comp=$row->WetId,$last_cat=$row->GrpId)
{
	$row = mysql_fetch_object($res);

	if ($cup && (!$row || 								// cupranking and (behind result set or
		$last_comp && $row->WetId != $last_comp ||		// new competition or
		$last_nation && $row->nation != $last_nation))	// new nation)
	{
		$nation_comp_pkte[$last_nation][$last_comp] = $pkte;
		$anz = $pkte = 0;
	}
	if (!$row || $last_nation && $row->nation != $last_nation) 	// behind result set or new nation
	{
		if ($cup)
		{
			if ($max_comps) arsort($nation_comp_pkte[$last_nation],SORT_NUMERIC);
			$n = $pkte = 0;
			foreach($nation_comp_pkte[$last_nation] as $comp => $cpkte)
			{
				$n++;
				if ($max_comps && $n > $max_comps)
				{
					$nation_comp_pkte[$last_nation][$comp] = '('.$cpkte.')';
				}
				else
				{
					$pkte += $cpkte;
				}
			}
		}
		$nations[$last_nation] = sprintf('%04d-%s',9999-$pkte,$last_nation);
		$last_cat = $last_comp = $anz = $pkte = 0;
	}
	if (!$row) break;

	if ($row->GrpId != $last_cat) $anz = 0;	// quota counts for each cat

	if (++$anz > $quota) {
		//echo "<p>NOT counting: ".print_r($row,true)."</p>\n";
		continue;
	}
	if ($_GET['debug']) echo "<p>$row->nation: $pkte+$row->pkt=".($pkte+$row->pkt).": $row->platz. $row->nachname, $row->vorname (".$cat_data[$row->GrpId]."/$row->WetId)</p>\n";

	if (!$cup && $show_calc)
	{
		if (!isset($non_cup_pkte[$row->nation])) $non_cup_pkte[$row->nation] = array();
		$per_pkte = (int)$non_cup_pkte[$row->nation][$row->PerId];
		$non_cup_pkte[$row->nation][$row->PerId] = ($per_pkte+$row->pkt).'/'.$row->num_cats.' '.$row->vorname.' '.$row->nachname;
	}
	$pkte += (int) $row->pkt;
}
asort($nations);

if (!$cats) {
	$gruppe = $last_cat;
	setup_grp($gruppe);
	include($grp_inc);
}

if ($_GET['no_header'])
{
	echo '<b>'.$t_nat_team_ranking.($cup ? ' '.$cup->name : $wettk->name).': '.$cat_names."</b>\n";
}
else
{
	if (is_object($GLOBALS['page']))
	{
		$GLOBALS['page']->title = $t_nat_team_ranking.' '.($cup ? $cup->name : $wettk->name).': '.$name;
	}
	do_header ($cup ? $cup->name : $wettk->name,$t_nat_team_ranking.'<br />'.($cup ? '<b>'.$cup->name.'</b>' :
		'<b>'.$wettk->name.'</b><br />'.wettk_datum($wettk)).($cat_names ? '<br />' . $cat_names : ''),'center',0,'');
}
require_once('ioc2nation.php');

echo '<a name="start" />';
echo '<table width="100%">'."\n\t".'<tr align="center" bgcolor="#c0c0c0" class="th">'."\n".
	"\t\t<td><b>$t_rank</b></td>\n\t\t".'<td colspan="2"><b>'.$t_nation."</b></td>\n\t\t";

if ($cup && $show_calc)
{
	foreach($comp_data as $comp => $label)
	{
		echo '<td><a href="'.$GLOBALS['dr_config']['nat_team_ranking'].'comp='.$comp.'&amp;cat='.$cats.'" target="_blank">'.$label."</a></td>\n\t\t";
	}
}
if (!$show_calc) $show_calc_link = $GLOBALS['dr_config']['nat_team_ranking'].($cup?'cup='.$cup->SerId:'comp='.$wettk->WetId).'&amp;cat='.$cats.'&amp;show_calc=1'.$test_overall;
echo "\n\t\t<td><b>".(!$show_calc ? '<a href="'.$show_calc_link.'">' : '').$t_points.($cup ? '</a>' : '')."</b></td>\n\t</tr>\n";

$last_pkte = 0;
$n = 1;
foreach($nations as $nation => $sort)
{
	$pkte = 9999 - (int) $sort;
	$platz = $pkte == $last_pkte ? '' : $n.'.';
	$class = $n & 1 ? 'row_on' : 'row_off';

	echo "\t".'<tr align="center" bgcolor="#f0f0f0" class="'.$class.'">'."\n\t\t<td>".$platz."</td>\n\t\t<td>$nation</td>\n\t\t<td>".$ioc2nation[$nation];
	if (!$cup && $show_calc)
	{
		echo '<br /><span style="text-align:left; font-size: 80%;">'.implode(', ',$non_cup_pkte[$nation]).'</span>';
	}

	if ($cup && $show_calc)
	{
		foreach($comp_data as $comp => $label)
		{
			echo "</td>\n\t\t<td>".(isset($nation_comp_pkte[$nation][$comp]) ? $nation_comp_pkte[$nation][$comp] : '&nbsp;');
		}
	}
	echo "</td>\n\t\t<td>$pkte</td>\n\t</tr>\n";

	$last_pkte = $pkte;
	++$n;
}
echo "</table>\n";

if ($cup && $show_calc)
{
	echo '<p>';
	printf ($t_ser_mode,$cup->name,$max_comps ? sprintf($t_best,$max_comps) : $t_all);
	if ($max_comps) print (" " . $t_brakets);
}
if (!$_GET['no_header'])
{
	if (!$cup && $wettk->homepage)
	{
		see_also('<li>Internet: '. wettk_link_str( $wettk,$wettk->name )."</li>\n");
	}
	if (!$show_calc)
	{
		see_also('<li><a href="'.$show_calc_link.'">'.$t_calculation.'</a> '.$t_nat_team_ranking.' '.($cup?$cup->name:$wettk->name)."</li>\n");
	}
	foreach($valid_cats as $name => $vcats)
	{
		$vcats_str = implode(',',$vcats);
		if ($cats != $vcats_str && ($name != 'overall' || count(explode(',',$cats)) <= 2))
		{
			see_also('<li>'.$t_nat_team_ranking.' '.($cup ? $cup->name : $wettk->name).': <a href="'.$GLOBALS['dr_config']['nat_team_ranking'].
				($cup ? 'cup='.$cup->SerId : 'comp='.$wettk->WetId).'&amp;cat='.$vcats_str.$test_overall.'" target="_self">'.$name."</a></li>\n");
		}
	}
	do_footer();
}
