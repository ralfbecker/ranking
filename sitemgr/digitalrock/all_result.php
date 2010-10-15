<?php
/* $Id$ */

if (basename($_SERVER['PHP_SELF']) == basename(__FILE__) && $_SERVER['HTTP_HOST'] != 'localhost')
{
	include_once('cache.php');
	do_cache();
}
require_once 'open_db.inc.php';

if (!isset($wettk))
{
	$wettk=prepare_var('comp','strtoupper',array(0,'GET'));
	$anz=prepare_var('num','intval',array(1,'GET'),null);
	$nation=prepare_var('nation','addslashes,strtoupper',array(2,'GET'));
}
//echo "<p>$_SERVER[PHP_SELF]: query_string='$_SERVER[QUERY_STRING]', wettk='$wettk', anz='$anz', nation='$nation'</p>\n";

read_wettk ($wettk);

if (!$anz)
{
	$res = my_query("SELECT DISTINCT count(g.rkey) AS anz FROM Gruppen g,Results r".
	                " WHERE g.GrpId=r.GrpId AND r.WetId=".$wettk->WetId.
			" AND r.platz=1");
	$anz_grps = mysql_fetch_object($res);
	$anz = $anz_grps->anz > 3 ? 3 : 8;
}

$gruppe=$wettk->nation=="GER" ? "GER_F" : ($nation=="SUI" ? "SUI_F" : "ICC_F");
global $grp_inc;
setup_grp ($gruppe);			// defaults fr Gruppe setzen
include ($grp_inc);
//echo "<p>gruppe='$gruppe->rkey', grp_inc='$grp_inc', t_verband_del='$t_verband_del'</p>\n";

$res = my_query("SELECT GrpId,name FROM Gruppen");
while ($row = mysql_fetch_object ($res))
{
	$grpname[$row->GrpId] = $row->name;
}

// check if we have a resultservice result
if (isset($GLOBALS['dr_config']['resultservice']))
{
	$res = my_query("SELECT DISTINCT GrpId FROM RouteResults WHERE WetId=".(int)$wettk->WetId);
	while ($row = mysql_fetch_object($res))
	{
		$res_service[$row->GrpId] = true;
	}
}
$res = my_query($sql="SELECT r.GrpId,r.platz,r.pkt,p.rkey,p.vorname,p.nachname,p.ort,Federations.nation,verband,fed_url,p.PerId" .
		" FROM Personen p".
		" JOIN Results r USING(PerId)".fed_join('p').
		" JOIN Gruppen USING(GrpId)".
		" WHERE r.WetId=" . $wettk->WetId .
		($anz || $nation ? ' AND ' : '').
		($anz ? ($nation ? '(' : '')." r.Platz<=$anz" : "").
		($nation ? ($anz ? ' OR' : '')." Federation.nation='$nation'".($anz ? ')' : '') : '').
		" AND r.platz > 0".
		" ORDER BY Gruppen.name,r.GrpId,r.platz,p.nachname,p.vorname");

if (strstr($_SERVER['PHP_SELF'],'all_result.php')) { // we are NOT included
?>
<!doctype html public "-//W3C//DTD HTML 3.2 Final//EN">
<html>
<head>
   <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
   <meta name="Author" content="Ralf Becker [http://www.digitalROCK.de]" />
   <meta name="GENERATOR" content="all_result.php (c) 2001-<?php echo date('Y'); ?> by Ralf Becker" />
   <meta name="KeyWords" content="digital ROCK, Klettern, Wettkampfklettern, Sportklettern, climbing, climbing competitions, UIAA, DAV, ICC, EYC, worldcup, worldranking, CUWR" />
   <base target="profil" />
   <title>digital ROCK: <?php echo $wettk->name; ?></title>
</head>
<body text="#000000" bgcolor="#FFFFFF">
<?php
}
if (!defined('DR_PATH'))	// we are NOT running inside sitemgr
{
	echo '<table width="99%" align="center">'."\t<tr>\n\t\t<td>\n";
}
echo '<p style="font-size: 120%"><b>'.(defined('DR_PATH') ? $wettk->name : str_replace(" - ","<br />",$wettk->name)).'</b><br />'.wettk_datum($wettk)."</p>\n";
if (!defined('DR_PATH'))	// we are NOT running inside sitemgr
{ ?>
		</td>
  		<td align="right"><a href="<?php echo $url_drock; ?>" target="_blank"><img src="dig_rock.mini.jpg" alt="<?php echo $t_dig_rock_alt ?>" border="0"></a></td>
	</tr>
</table>
<?php
}
echo '<table width="99%">'."\n\t".'<tr align="center" bgcolor="#c0c0c0">'."\n";
echo "\t\t<td><b>$t_rank</b></td>\n\t\t".'<td colspan="2" align="left"><b>'.$t_name."</b></td>\n".
	"\t\t<td><b>".($wettk->nation ? ($t_verband_del ? $t_sektion : $t_city) : $t_nation)."</b></td>\n".
	"\t</tr>\n";

$cats = array();
for ($aktGrpId=0;			// noch keine Gruppe
     $row = mysql_fetch_object($res);
     $last_platz=$row->platz)
{
   if ($row->GrpId != $aktGrpId) {	// neue Gruppe --> Gruppen-Header
      $last_platz = 0;
      $gruppe = $aktGrpId = $row->GrpId;
      setup_grp ($gruppe);
      $cats[] = $gruppe->GrpId;
      echo "\t".'<tr bgcolor="#c0c0c0">'."\n\t\t".'<td colspan="4"><b>'.$grpname[$row->GrpId] . '</b>'.
	    ($anz ?  ' &nbsp; <a href="'.$GLOBALS['dr_config'][$res_service[$row->GrpId] ? 'resultservice' : 'result'].'comp='.$wettk->WetId.'&amp;cat='.$row->GrpId.
	    '" target="_blank">'.$t_complete_result.'</a>' : '') ."</td>\n\t</tr>\n";
   }
   echo "\t".'<tr align="center" bgcolor="#f0f0f0">'."\n\t\t<td>".
	 ($row->platz == 999 ? $t_disqualified : (!$last_platz || $last_platz!=$row->platz ? $row->platz.'.':'&nbsp;')).
	 "</td>\n";
   per_link ($row,$gruppe,false);
   echo "\t</tr>\n";
}
if (!$wettk->nation && (int)$wettk->datum >= 2005 && $wettk->quota && preg_match('/^[0-9]{2}_[^I]+/',$wettk->rkey))
{
	// all valid Combinations
	$valid_cats = array(
		'combined (lead &amp; boulder)' => array(1,2,5,6),
		'lead' => array(1,2),
		'boulder' => array(5,6),
		'speed'    => array(23,24),
		'youth lead' => array(15,16,17,18,19,20),
		'youth speed' => array(56,57,58,59,60,61),
	);
	if ((int)$wettk->datum >= 2008)
	{
		$valid_cats['overall'] = array(1,2,5,6,23,24);
	}
	if ((int)$wettk->datum >= 2009)	// no more combined ranking from 2009 on, only overall
	{
		unset($valid_cats['combined (lead &amp; boulder)']);
	}

	$links = array();
	foreach($valid_cats as $name => $vcats)
	{
		if ((count($icats=array_intersect($cats,$vcats)) == count($vcats) ||
			($name == 'overall' && count($icats) > 2))	// show overall if we have more then 2 cats
			&& ($name != 'overall' || $wettk->WetId != 991))	// temporary disabling 2009 Word Championship
		{
			$links[] = '<a href="'.$GLOBALS['dr_config']['nat_team_ranking'].'comp='.$wettk->WetId.'&amp;cat='.implode(',',$vcats).'" target="_blank">'.$name.'</a>';
		}
	}
	if ($links)
	{
		echo "\t<tr bgcolor=\"#c0c0c0\">\n\t\t<td colspan=\"4\"><b>$t_nat_team_ranking</b>:\n";
		echo implode(",\n",$links)."</td>\t</tr>\n";
	}
	if ((int)$wettk->datum >= 2008 && $wettk->WetId != 991)	// temporary disabling 2009 Word Championship
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
			if (count(array_intersect($cats,$data['GrpIds'])) > 1)
			{
				$links[] = '<a href="'.$GLOBALS['dr_config']['ranglist'].'comp='.$wettk->WetId.'&amp;cat='.$data['GrpId'].'" target="_blank">'.$name.'</a>';
			}
		}
		if ($links)
		{
			echo "\t<tr bgcolor=\"#c0c0c0\">\n\t\t<td colspan=\"4\"><b>$t_combined_ranking</b>: ".implode(",\n",$links)."</td>\t</tr>\n";
		}
	}
}
echo "</table>\n";

if (!defined('DR_PATH'))
{
	echo "</body>\n</html>\n";
}
