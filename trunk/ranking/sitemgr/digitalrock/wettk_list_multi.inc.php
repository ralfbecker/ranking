<?php
/**
 * eGroupWare digital ROCK Rankings - ranglist display
 *
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2002-12 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$
 */

if (!strlen($date_format)) $date_format = '%d.%m.%y';
$anz_year = strlen($anz_year) ? intval($anz_year) : 1;	// number of years before/after are shown
if (!($year = intval($_SERVER['QUERY_STRING'])))
{
	$year = intval($_REQUEST['year']) ? intval($_REQUEST['year']) :
		date('Y')+(int)(date('m') >= 12);	// already show next year in December
}
if ($year < 100)
{
	$year += ($year < 70 ? 2000 : 1900);
}
$mode = prepare_var('mode','intval',array('GET'),0);
//echo "<!-- year='$year', _POST[year]='".$_POST['year']."', _SYSTEM[QUERY_STRING]='".$_SERVER['QUERY_STRING']."' -->\n";

if (!is_array($cats))
{
	$cats = array(
		'default' => array(
			'label'  => 'Default',
			'nation' => $nation,
			'grps'	 => $grps,
//			'gnames' => $gnames,
			'wettk_reg' => $wettk_reg,
			'serie_reg' => $serie_reg,
			'rang_title'=> $rang_title,
			'bgcolor'   => '#FFFFFF',
		)
	);
}

function wettk_grps($wetid)	// check for which categories we have a result
{
	if ($res =my_query("SELECT g.rkey,MAX(r.platz) AS platz,MAX(r.pkt) as pkt".
	                   " FROM Gruppen g,Results r".
	                   " WHERE r.GrpId=g.GrpId AND r.WetId=".(int) $wetid.
	                   " GROUP BY g.rkey ORDER BY g.name"))
	{
		while ($row=mysql_fetch_object($res))
		{
			// 0=result, 3=startlist, 4=starters
			$rgrps[$row->rkey] = $row->platz ? 0 : ($row->pkt > 64 ? 3 : 4);
		}
	}
	if (isset($GLOBALS['dr_config']['resultservice']) &&
		($res =my_query("(SELECT g.rkey,MAX(r.result_rank) AS platz".
	                   " FROM Gruppen g,RouteResults r".
	                   " WHERE r.GrpId=g.GrpId AND r.WetId=".(int) $wetid.
	                   " GROUP BY g.rkey ORDER BY g.name)".
	                   "UNION".
					   "(SELECT g.rkey,MAX(r.result_rank) AS platz".
	                   " FROM Gruppen g,RelayResults r".
	                   " WHERE r.GrpId=g.GrpId AND r.WetId=".(int) $wetid.
	                   " GROUP BY g.rkey ORDER BY g.name)")))
	{
		while ($row=mysql_fetch_object($res))
		{
			// resultservice: 1=result, 2=startlist
			if (!isset($rgrps[$row->rkey]) || $rgrps[$row->rkey] > 2) $rgrps[$row->rkey] = $row->platz ? 1 : 2;
			$rgrps[0][$row->rkey] = true;
		}
	}
	//echo "<p>$wetid: ".print_r($rgrps,true)."</p>\n";
	return ($rgrps);
}

function result($wettk,$grp,$rgrps,$grp_name,$no_result=True)		// Link auf Wettkampfergebnis
{
	//echo "<p>result('$wettk->rkey','$grp',,'$grp_name','$no_result')</p>\n";
	global $t_show_result, $t_no_result;

	$grp_name = str_replace('<br />',' ',$grp_name);
	if (isset($rgrps[0][$grp]) && $rgrps[0][$grp])
	{
		return '<a class="mini_link" href="'.$GLOBALS['dr_config']['resultservice'].'comp='.$wettk->WetId.'&amp;cat='.$grp.'" title="'.$t_show_result.'">'.$grp_name.'</a>';
	}
	elseif (isset($rgrps[$grp]) && $rgrps[$grp] == 0)
	{
		return '<a class="mini_link" href="'.$GLOBALS['dr_config']['result'].'comp='.$wettk->rkey.'&amp;cat='.$grp.'" title="'.$t_show_result.'">'.$grp_name.'</a>';
	}
	if (grp_in_grps($grp,$wettk->gruppen))
	{
		$title = $no_result ? " title='$t_no_result'" : '';
		return "<span class=\"mini\"$title>$grp_name</span>";
	}
}

function serie($serie,$wkey,$grp)	// Link auf Cupergebnis zum Datum $wettk
{
	global $gname;
	print ('<td align="center">');
	if (grp_in_grps($grp,$serie->gruppen))
	{
		echo '<a href="'.$GLOBALS['dr_config']['ranglist'].'comp='.$wkey.'&amp;cat='.$grp.'&amp;cup='.$serie->rkey.'">'.$gname[$grp].'</a>';
	}
	echo "</td>\n";
}

function ranglist($wkey,$grp)	// Link auf Rangliste zum Datum $wettk
{
	global $gname;
	$gruppe = $grp; setup_grp($gruppe);
	echo '<td align="center">'.($gruppe->rls ? '<a href="'.$GLOBALS['dr_config']['ranglist'].'comp='.$wkey.'&amp;cat='.$grp.'">' : '').
		$gname[$grp].($gruppe->rls ? '</A>' : '' )."</td>\n";
}

/**
 * Since when categorising of competition uses cat_id instead of regular expression for rkey
 */
$cats_use_cat_id_since = 2011;

function get_cats(&$wettk)
{
	global $cats,$cats_use_cat_id_since;

	if (isset($wettk->cats))
	{
		return $wettk->cats;
	}
	$wettk->cats = False;
	$group_found = array();
	foreach ($cats as $key => $cat)
	{
		if ($cat['nation'] == $wettk->nation &&
			((int)$wettk->datum >= $cats_use_cat_id_since && in_array($wettk->cat_id, (array)$cat['cat_id']) ||
			 (int)$wettk->datum <  $cats_use_cat_id_since && preg_match('/'.$cat['wettk_reg'].'/',$wettk->rkey)))
		{
			list($grps) = explode('@',$wettk->gruppen);
			if ($grps)
			{
				$found = 0;
				foreach($cat['grps'] as $grp => $gname)
				{
					if (!isset($group_found[$grp]))	// use each group only once
					{
						if (grp_in_grps($grp,$wettk->gruppen))
						{
							++$found;
							$group_found[$grp] = True;
						}
					}
				}
				if ($found)
				{
					$wettk->cats[] = $key;
				}
			}
			else
			{
				$wettk->cats[] = $key;	// wettk has no specific groups set, but matches that cat
			}
		}
	}
	return $wettk->cats;
}

foreach($cats as $key => $nul)
{
	$cat = &$cats[$key];	// so we change to orig. data

	$wettks .= ($wettks ? ' OR (' : '(') .
		($cat['nation'] ? "nation='$cat[nation]'" : 'ISNULL(nation)').
		($cat['wettk_reg'] ? " AND rkey REGEXP '$cat[wettk_reg]')":')');

	if ($cat['serie_reg'])
	{
		$serien .= ($serien ? 'OR (' : '(') .
			($cat['nation'] ? "nation='$cat[nation]'" : 'ISNULL(nation)').
			($cat['serie_reg'] ? " AND rkey REGEXP '$cat[serie_reg]')":')');
	}
}
unset($cat);	// else next usage might destroy something

// query the availible years from the db
$years = array();
$_years = my_query($sql="SELECT DISTINCT DATE_FORMAT(datum,'%Y') AS year FROM Wettkaempfe".
                   " WHERE $wettks ORDER BY datum DESC");
//echo "<p>years='$sql'</p>\n";
while($row = mysql_fetch_object($_years))
{
	if ($mode != 2 || !strstr($extra_header[(int)$row->year],'provisional'))
	{
		$years[] = (int) $row->year;
	}
}
// show last years rankings
if (!in_array($year,$years)) list(,$year) = @each($years);

$wettks = my_query($sql="SELECT *,DATE_FORMAT(datum,'%d.%m.%y') AS fdatum" .
                   " FROM Wettkaempfe WHERE ($wettks)" .
                   " AND ABS(YEAR(datum) - $year) <= $anz_year" .
                   " ORDER BY datum ASC");
//echo "<p>wettk='$sql'</p>\n";
$wettk = mysql_fetch_object($wettks);

if ($serien)
{
	$serien = my_query("SELECT *,IF(0+rkey<70,2000+rkey,1900+rkey) AS year".
	                   " FROM Serien WHERE ($serien)" .
	                   " AND ABS(IF(0+rkey<70,2000,1900)+rkey - $year) <= $anz_year" .
	                   " ORDER BY year ASC");

	while ($serie = mysql_fetch_object($serien))
	{
		//echo "<p>Serie $serie->rkey - $serie->name: ";
		foreach($cats as $c => $cat)
		{
			if (isset($cat['serie_reg']) && !empty($cat['serie_reg']))
			{
				foreach($cat['grps'] as $grp => $gname)
				{
					if (grp_in_grps($grp,$serie->gruppen) && preg_match('/'.$cat['serie_reg'].'/i',$serie->rkey))
					{
						//echo "$cat[label],\n";
						$cats[$c]['serien'][$serie->rkey] = $serie;
						break;
					}
				}
			}
		}
	}
	//echo "<pre>"; print_r($cats); echo "</pre>\n";
}

$hidden_vars = '';
foreach ($_GET as $name => $value)
{
	if ($value && $name != 'year') $hidden_vars .= "\t\t\t".'<input type="hidden" name="'.$name.'" value="'.htmlspecialchars($value).'">'."\n";
}
echo "\t\t".'<form id="yearSelection" action="'.$_SERVER['PHP_SELF'].'" method="GET" style="float:left; padding-top: 35px; margin-bottom: 5px;">'."\n".$hidden_vars.
     "\t\t\t".'<span class="cal_head">'.$extra_header[$year].' '.($mode != 2 ? $t_calendar : $t_ranking)."</span>\n".
     "\t\t\t".'<select class="cal_head" name="year" onchange="this.form.submit();">'."\n";

foreach($years as $y)	// the availible years are queried now from the db
{
	echo "\t\t\t\t<option".($y == $year ? ' selected="1"':'').">$y</option>\n";
}
echo "\t\t\t</select>\n";
echo "\t\t</form>\n";
echo '<div style="float: right">'.$header_logos."</div>\n";

if ($mode != 2)
{
	$last_s_year = $year;

	if (!defined(DR_PATH))	// not running in SiteMgr
	{
		echo "<script>
	jQuery(document).ready(function(){
		var scrollIntoView = document.getElementById('scrollIntoView');
		if (typeof scrollIntoView != 'undefined')
		{
			scrollIntoView.scrollIntoView(true);
			document.getElementById('yearSelection').scrollIntoView(true);
		}
	});
</script>\n";
		if ($mode != 1 && !strstr($extra_header[$year],'provisional'))	// provisional calendar does NOT include ranking!
		{
			$div_style='height: 300px; overflow: auto;';	// display including ranking
		}
		else
		{
			$div_style = 'height: 75%; overflow: auto;';	// display without ranking
		}
	}
	echo '<div style="'.$div_style.'clear: both;">'."\n";
	echo '<table width="100%">'."\n";
	while ($next_serie || $wettk) {			// solange noch eine Zeile da
		$s_year = 0;
		if ($serie && ($s_year = 1900+$serie->rkey) < 1970)
			$s_year += 100;

		if ($s_year && $s_year >= 0+$wettk->datum) { // erst alle Serien ausgeben
			$serie = mysql_fetch_object($serien);
		}
		else
		{				    // dann die Wettk�mpfe
			if ($last_s_year != intval($wettk->datum))
			{
				$last_s_year = intval($wettk->datum);

				echo '<tr bgcolor="#FFFFFF">'."\n\t".'<td colspan="2">'.
					"\t\t".'<br /><span class="cal_head">'.$last_s_year.'</span>'.
					"\t</td>\n</tr>\n";

			}
			$file_prefix = '';
			switch((string)$wettk->nation)
			{
				case '':
					$prefix = '/';
					if ((int)$wettk->datum >= 2007) $file_prefix = '/var/www/ifsc-climbing.org/';
					break;
				case 'GER':
					$prefix = 'http://ranking.alpenverein.de/';
					break;
				case 'SUI':
					$prefix = 'http://ranking.sac-cas.ch/';
					break;
			}
			// since 2008 year directory has a nation subdir!
			$nat = (int)$wettk->datum >= 2008 && $wettk->nation ? $nat = '/'.$wettk->nation : '';
			$infos   = intval($wettk->datum) . $nat . "/$wettk->rkey.pdf";
			$info2   = intval($wettk->datum) . $nat . "/i$wettk->rkey.pdf";
			$starter = intval($wettk->datum) . $nat . "/S$wettk->rkey.pdf";
			$result  = intval($wettk->datum) . $nat . "/R$wettk->rkey.pdf";
			$rgrps = wettk_grps($wettk->WetId);

			//echo "$wettk->rkey: $wettk->name<pre>"; print_r($wettk); echo "</pre>\n";
			if (get_cats($wettk))
			{
				//echo "Cats"; print_r($wettk->cats); echo "<p>\n";
				foreach ($wettk->cats as $key)
				{
					$cat = $cats[$key];

					$minis = '';
					if (count($cats) > 1 && !$calendar_no_cats)
					{
						$sep = $rgrps || $wettk->gruppen && $wettk->gruppen[0] != '@' ? ': ' : '';
						$minis .= '<span class="mini">'.$cat['label'].$sep.'</span>';
					}
					$first = True;
					foreach($cat['grps'] as $grp => $gname)
					{
						if ($item = result ($wettk,$grp,$rgrps,$gname,!file_exists($file_prefix.$result)))
						{
							$minis .= ($first ? "\n" : ",\n") . $item;
							$first = False;
						}
					}
					// show starters from online-registration and the national team ranking
					if ($rgrps && $year >= 2005)
					{
//echo "$wettk->rkey: $wettk->name"; _debug_array($rgrps);
						$have_starter = 0;
						foreach($rgrps as $grp => $grp_has_starter)
						{
							if (!$grp) continue;
							//$have_starter = $have_starter || $grp_has_starter;
							$have_starter = max($have_starter,$grp_has_starter);
						}
//echo "<p>--> have_starter=$have_starter</p>\n";
						if ($have_starter >= 3)
						{
							$minis .= ($minis?"\n| ":'').'<a class="mini_link" href="'.$GLOBALS['dr_config']['startlist'].'comp='.$wettk->WetId.
								'" target="_blank" title="'.$t_show_starter.'">'.$t_starter.'</a> ';
						}
						elseif (!$have_starter && $cat['nat_team_ranking'] && $wettk->quota)
						{
							$minis .= ",\n".'<a class="mini_link" href="'.$GLOBALS['dr_config']['nat_team_ranking'].
								'comp='.$wettk->WetId.'" target="_blank">'.$t_nat_team_ranking.'</a>';

						}
					}
					// add selfregister link if enabled and registration deadline (or if not set comp. date) is not over
					if ($wettk->selfregister && !date_over($wettk->deadline?$wettk->deadline:$wettk->datum))
					{
						$t_register = 'Anmelden';
						$t_selfregister = 'sich selbst für diesen Wettkampf anmelden';
						$link = 'https://'.$_SERVER['HTTP_HOST']."/egroupware/ranking/athlete.php?action=register-$wettk->WetId";
						$minis .= ($minis?"\n| ":'')."<a class='mini_link' href=\"javascript:window.open('$link','_blank','dependent=yes,width=900,height=450,scrollbars=yes,status=yes')\" title='$t_selfregister'>$t_register</a>";
					}
					if (file_exists($file_prefix.$result))
					{
						$minis .= ($minis?"\n| ":'').'<a class="mini_link" href="'.$prefix.$result.'" target="_blank" title="'.$t_show_result.'">'.$t_result.'</a>';
					}
					elseif (file_exists($file_prefix.$starter) && !$rgrps)	// show Startlist
					{
						$minis .= ($minis?"\n| ":'').'<a class="mini_link" href="'.$prefix.$starter.'" target="_blank" title="'.$t_show_starter.'">'.$t_starter.'</a>';
					}
	/* nicht mehr nicht in Rangliste / not in CUWR anzeigen RB 28.03.2004
					if ($wettk->faktor <= 0.0)
					{
						$minis .= ($minis?"\n| ":'')."<SPAN CLASS='mini_no'>$t_no_cuwr</SPAN>";
					}
	*/
					if (file_exists($file_prefix.$infos))
					{
						$minis .= ($minis?"\n| ":'').'<a class="mini_link" href="'.$prefix.$infos.'" target="_blank" title="'.$t_show_infos.'">'.$t_infos.'</a>';
					}
					if (file_exists($file_prefix.$info2))
					{
						$minis .= ($minis?"\n| ":'').'<a class="mini_link" href="'.$prefix.$info2.'" target="_blank" title="'.$t_show_infos.'">'.$t_info2.'</a>';
					}
//_debug_array($wettk);
					list($y,$m,$d) = explode('-',$wettk->datum);
					list($show_y,$show_m,$show_d) = explode('-',date('Y-m-d',time()-5*86400));	// 5 days back
					if (!isset($show_comp) && $y == $show_y && ($m > $show_m || $m == $show_m && $d >= $show_d))
					{
						$show_comp = ' id="scrollIntoView"';
					}
					echo '<tr bgcolor="'.$cat['bgcolor'].'"'.$show_comp.'>'."\n\t".'<td class="comp_date">'.wettk_datum($wettk)."</td>\n";
					if (isset($show_comp)) $show_comp = '';
					$wettk_class = $wettk->serie && ($wettk->faktor > 0.0 || $wettk->nation != 'GER') ? 'comp_cup' : 'comp';
					$wettk_label = str_replace('#','<font color="red">###</font> ',wettk_link_str($wettk,$wettk->name,'comp_link',!file_exists($result)));
					echo "\t".'<td><span class="'.$wettk_class.'">'.$wettk_label.'</span><br />'.$minis."</td>\n";
					echo "</tr>\n";
				}
			}

			$wettk = mysql_fetch_object($wettks);
		}
	}
	echo "</table>\n";
	echo "</div>\n";

	if ($extra_footer[$year]) echo $extra_footer[$year]."\n";
}
if ($mode != 1 && !strstr($extra_header[$year],'provisional'))	// provisional calendar does NOT include ranking!
{
	$do_list = /*defined('DR_PATH') &&*/ $mode == 2;	// sitemgr and produce just a list of rankings

	if (!$mode)
	{
		echo '<a name="ranking">'."\n";

		echo '<table width="100%" style="clear: both;"><tr>'."\n";
		echo "\t".'<td align="left" valign="bottom"><span class="cal_head">'.$t_ranking."</span></td>\n";
		echo "\t".'<td width="20%" align="right">'.$dav_header."</td>\n";
		echo "</tr></table>\n";
	}
	echo $do_list ? "<ul>\n" : '<table width="100%">'."\n";
	foreach($cats as $cat)
	{
		if ($cat['serien'])
		{
			$grps = array();
			foreach($cat['serien'] as $rkey => $serie)
			{
				foreach($cat['grps'] as $grp => $gname)
				{
					//echo "<p>grp_in_grps('$grp','$serie->gruppen') = ".(grp_in_grps($grp,$serie->gruppen) ? 'True' : 'False')."</p>\n";
					if (grp_in_grps($grp,$serie->gruppen))
					{
						$grps[$grp] = str_replace('<br />',' ',$gname);
					}
				}
			}
		}
		else
		{
			$grps = $cat['grps'];
		}
		//echo "<p>$cat[label]: serien="; print_r($cat[serien]).", grps="; print_r($grps); echo "</p>\n";

		if ($cat['rang_title'])
		{
			if ($do_list)
			{
				echo "\t<li><b>".$cat['rang_title']."</b>\n\t\t<ul>\n";
			}
			else
			{
				echo "\t".'<tr bgcolor="'.$cat['bgcolor'].'">'."\n";
				echo "\t\t".'<td nowrap>'."\n";
				echo "\t\t\t".'<span class="comp_cup">'.$cat['rang_title'].":</span>\n";
				echo "\t\t</td><td>\n";
			}
			$i = 0;
			foreach($grps as $grp => $gname)
			{
				if ($do_list)
				{
					echo (!($i & 1) ? "\t\t\t<li>" : ', ').'<a href="'.$GLOBALS['dr_config']['ranglist'].'cat='.$grp.'">'.$gname.'</a>'.($i & 1 ? "</li>\n" : '');
				}
				else
				{
					echo ($i?",\n":'')."\t\t\t".'<a class="mini_link" href="'.$GLOBALS['dr_config']['ranglist'].'cat='.$grp.'">'.$gname.'</a>';
				}
				$i++;
			}
			//echo "<br />\n";
			echo $do_list ? "\t\t</ul>\n\t</li>\n" : "\n\t\t</td>\n\t</tr>\n";
		}
		if ($cat['serien'])
		{
			foreach($cat['serien'] as $rkey => $serie)
			{
				$i = 0;
				if ($do_list)
				{
					echo "\t<li><b>$serie->name</b>\n\t\t<ul>\n";
				}
				else
				{
					echo "\t".'<tr bgcolor="'.$cat['bgcolor'].'">'."\n";
					echo "\t\t".'<td>'."\n";
					echo "\t\t\t".'<span class="comp">'.$serie->name.":</span>\n";
					echo "\t\t</td><td>\n";
				}
				foreach(isset($cat['serie_grps']) ? $cat['serie_grps'] : $cat['grps'] as $grp => $gname)
				{
					if (grp_in_grps($grp,$serie->gruppen))
					{
						$gname = str_replace('<br />',' ',$gname);
						if ($do_list)
						{
							echo (!($i & 1) ? "\t\t\t<li>" : ', ').'<a href="'.$GLOBALS['dr_config']['ranglist'].'cat='.$grp.'&amp;cup='.$rkey.'">'.
								$gname.'</a>'.($i & 1 ? "</li>\n" : '');
						}
						else
						{
							echo ($i?",\n":'')."\t\t\t".'<a class="mini_link" href="'.$GLOBALS['dr_config']['ranglist'].'cat='.$grp.'&amp;cup='.$rkey.'">'.
								$gname.'</a>';
						}
						++$i;
					}
				}
				if ($year >= 2008 && !$serie->nation && stristr($rkey,'_wc'))	// overall world cup
				{
					$i = 0;
					foreach(array(
						'MEN overall' => 45,
						'WOMEN overall' => 42,
					) as $gname => $grp)
					{
						if ($do_list)
						{
							echo (!($i & 1) ? "\t\t\t<li>" : ', ').'<a href="'.$GLOBALS['dr_config']['ranglist'].'cat='.$grp.'&amp;cup='.$rkey.'">'.
								$gname.'</a>'.($i & 1 ? "</li>\n" : '');
						}
						else
						{
							echo ($i?",\n":'<br />')."\t\t\t".'<a class="mini_link" href="'.$GLOBALS['dr_config']['ranglist'].'cat='.$grp.'&amp;cup='.$rkey.'">'.
								$gname.'</a>';
						}
						++$i;
					}
				}
				if ($year >= 2005 && !$serie->nation && stristr($rkey,'_wc'))	// national team ranking of the cup
				{
					// all valid Combinations
					$valid_cats = array(
						'lead' => array(1,2),
						'boulder' => array(5,6),
						'speed'    => array(23,24),
					);
					$i = 0;
					foreach($valid_cats as $label => $cat_ids)
					{
						if ($do_list)
						{
							echo (!$i ? "\t\t\t<li>" : ', ').'<a href="'.$GLOBALS['dr_config']['nat_team_ranking'].'cat='.implode(',',$cat_ids).
								'&amp;cup='.$serie->SerId.'">'.(!$i ? $t_nat_team_ranking.': ' : '').$label.'</a>'.($i == 2 ? "</li>\n" : '');
						}
						else
						{
							echo ($i?",\n":'<br />')."\t\t\t".'<a class="mini_link" href="'.$GLOBALS['dr_config']['nat_team_ranking'].'cat='.implode(',',$cat_ids).
								'&amp;cup='.$serie->SerId.'">'.$t_nat_team_ranking.': '.$label.'</a>';
						}
						++$i;
					}
				}
				echo $do_list ? "\t\t</ul>\n\t</li>\n" : "\n\t\t</td>\n\t</tr>\n";
			}
		}
	}
	echo $do_list ? "</ul>\n" : "</table>\n";
}
