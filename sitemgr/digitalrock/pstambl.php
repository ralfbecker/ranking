<?php
require ('open_db.inc.php');

$pers=prepare_var('person','strtoupper',array('GET',0));
$gruppe=prepare_var('cat','strtoupper',array('GET',1));
$all=prepare_var('all','int',array('GET'),0);		// show all competitions

$anz_best=12;				// Anzahl beste Wettk. zum anzeigen

if (!$gruppe) 				// alten Schluessel aufloesen
{
	$res = my_query("SELECT g.rkey AS gkey,p.rkey AS pkey".
                    " FROM Gruppen2Personen g2p,Gruppen g,Personen p".
		            " WHERE g2p.GrpId=g.GrpId AND g2p.PerId=p.PerId AND g2p.old_key='".mysql_escape_string(strtolower($pers))."'");

	if ($row = mysql_fetch_object ($res))
	{
		$pers = $row->pkey;
		$gruppe = $row->gkey;
	}
}
setup_grp ($gruppe);			// defaults f�r Gruppe setzen
require ($grp_inc);

global $heute;
$heute = explode('-',date ("Y-m-d"));	// heutiges Datum setzen

function age($geb_date)			// $geb_date als YYYY-MM-DD
{
	global $heute;
	$geb = explode('-',$geb_date);
	$age = $heute[0] - $geb[0] - ($heute[1] < $geb[1] || $heute[1] == $geb[1] && $heute[2] < $geb[2]);
	return $age;
}

function age_in_days($geb_date) 	// alter in Tagen (nur ungef�hr)
{
	global $heute;
	$date = explode('-',$geb_date);

	return sprintf ("%06d",365 * ($heute[0]-$date[0]) + 12 * ($heute[1]-$date[1]) + ($heute[2]-$date[2]));
}

read_pers ($pers);
$res = my_query ("SELECT r.platz,r.pkt,w.*,g.rkey AS gkey,g.GrpId,g.name AS gname".
                 " FROM Results r,Wettkaempfe w,Gruppen g".
		         " WHERE r.PerId=$pers->PerId AND r.WetId=w.WetId AND r.GrpId=g.GrpId AND r.platz > 0".
	             " ORDER BY w.datum DESC");

for ($anz_wk=0; $row = mysql_fetch_object($res); $anz_wk++)
{
	//$gewicht = $row->platz * (1+($heute[0]-$row->datum)) * (5*($row->nation!="")+1);
	// more weight for the date:
	$gewicht = $row->platz/2 + ($heute[0]-$row->datum) + 4*($row->nation!="");
	$per_wk[sprintf("%04d:%s:%s:%s",$gewicht,age_in_days($row->datum),$row->gkey,$row->rkey)] = $row;
}
if ($anz_wk && !$all) 			// $anz_best besten Ergebnisse suchen
{
	ksort ($per_wk);
	for (reset ($per_wk),$i = 0; ($pwk = current ($per_wk)) && $i < $anz_best; next($per_wk),$i++)
	{
		$key = $pwk->datum.$pwk->gkey;
		$best[$key] = current($per_wk);
	}
	krsort ($best);
	$per_wk = $best;
}
//else ksort ($per_wk);

if (!($pers->acl & 128) && file_exists ($foto = $_SERVER['DOCUMENT_ROOT'].($foto_url = '/jpgs/'.$pers->rkey.'.jpg')))
{
	$t_header_logo = '<td width="10%"><img src="'.$foto_url.'" /></td>';
}
if ($pers->homepage && !($pers->acl & 128))
{
	$homepage = '<a href="'.htmlspecialchars((!stristr($pers->homepage,'://') ? 'http://' : '').$pers->homepage).
		'" target="_blank">'.str_replace('http://','',$pers->homepage).'</a><br />';
}
$name = $pers->vorname . ' ' . $pers->nachname;
if ($pers->email && !($pers->acl & (2|128)))
{
	$name = mailto($pers->email,$name);
}
$name = '<b>'.$name.'</b><br />'.$homepage.($gruppe->nation ? $pers->ort : $pers->nation).
	($pers->verband ? '<br />'.($pers->fed_url ? '<a href="'.htmlspecialchars((!stristr($pers->fed_url,'://') ? 'http://' : '').$pers->fed_url).'" target="_blank">' : '').
		htmlspecialchars($pers->verband).($pers->fed_url ? '</a>' : '') : '');

if (defined('DR_PATH'))
{
	if (file_exists($foto)) echo "<table>\n\t<tr>\n\t\t".$t_header_logo."\n\t\t".'<td style="padding-left: 20px;">';
	echo '<p style="font-size: 200%;">'.$name.'</p>';
	if (file_exists($foto)) echo "</td>\n\t</tr>\n</table>\n";
}
else
{
	do_header ($pers->vorname.' '.$pers->nachname.($gruppe->nation ? ', '.$pers->ort : ' ('.$pers->nation.')'),$name,"left",1,'');
}
if ($pers->acl & 128)
{
	echo '<h3 style="color: red;">Sorry, the climber requested not to show his profile!</h3>'."\n";
	do_footer();
	return;
}
echo '<table cols="6">'."\n";

$spacer = "\t".'<tr style="height: 5px;">'."\n\t\t".'<td width="5%"></td>'."\n\t\t".'<td width="15%"></td>'."\n\t\t".'<td width="20%"></td>'.
	"\n\t\t".'<td width="10%"></td>'."\n\t\t".'<td width="20%"></td>'."\n\t\t".'<td></td>'."\n\t</tr>\n";

if ($pers->geb_date)
{
	echo "\t<tr>\n\t\t<td><i>age:</i></td>\n\t\t<td><b>".age($pers->geb_date).
		"</b></td>\n\t\t<td><i>date of birth:</i></td>\n\t\t<td><b>".(($pers->acl & 1) || strstr($pers->geb_date,'-01-01') ?
		(int)$pers->geb_date : datum($pers->geb_date))."</b></td>\n\t</tr>\n";
}
if ($pers->geb_ort)
{
	echo "\t<tr>\n\t\t<td><i>place of birth:</i></td>\n\t\t<td><b>".htmlspecialchars($pers->geb_ort)."</b></td>\n\t</tr>\n";
}
if ($pers->groesse || $pers->gewicht)
{
	echo "\t<tr>\n\t\t<td><i>height:</i></td>\n\t\t<td><b>".htmlspecialchars($pers->groesse)." cm</b></td>\n".
		"\t\t<td><i>weight:</i></td>\n\t\t<td><b>".htmlspecialchars($pers->gewicht)." kg</b></td>\n\t</tr>\n";
}
if ($pers->geb_date || $pers->geb_ort || $pers->groesse && $pers->gewicht) echo $spacer;

$insert_empty_line = false;
if ($pers->ort && !($pers->acl & 64))
{
	echo "\t<tr>\n\t\t<td><i>address:</i></td>\n\t\t".'<td colspan="2"><b>'.
	($pers->plz && !($pers->acl & 32) ? htmlspecialchars($pers->plz).' ' : '' ) . htmlspecialchars($pers->ort)."</b></td>\n\t\t".
	'<td colspan="2"><b>'.(!($pers->acl & 32) ? htmlspecialchars($pers->strasse) : '')."</b></td>\n\t</tr>\n";
	$insert_empty_line = true;
}
foreach(array('telefon' => 4,'mobil' => 16,'fax' => 8) as $name => $acl)
{
	if ($pers->$name && !($pers->acl & $acl))
	{
		switch($name)
		{
			case 'telefon': $label = 'phone'; break;
			case 'mobil':   $label = 'cellphone'; break;
			default:        $label = $name; break;
		}
		echo "\t<tr>\n\t\t<td><i>$label:</i></td>\n\t\t".'<td COLSPAN="2"><b>'.htmlspecialchars($pers->$name)."</b></td>\n\t</tr>\n";
		$insert_empty_line = true;
	}
}
if ($insert_empty_line) echo $spacer;

$insert_empty_line = false;
foreach(array(
	'practicing climbing since:' => !(int)$pers->practice ? false : ($heute[0]-$pers->practice).' years ('.$pers->practice.')',
	'professional climber (if not, profession):' => $pers->profi,
	'other sports practiced:' => $pers->sport,
	'hobbies:' => $pers->hobby,
) as $label => $value)
{
	if ($value)
	{
		echo "\t<tr>\n\t\t".'<td colspan="3"><i>'.$label.'</i></td>'."\n\t\t".'<td colspan="3"><b>'.htmlspecialchars($value)."</b></td>\n\t</tr>\n";
		$insert_empty_line = true;
	}
}
if ($insert_empty_line) echo $spacer;

if (!$all)				// Ranglisten und Serienstand ausgeben
{
	require ("calc_rang.inc.php");
	echo "\t<tr>\n";
	$stand = '.';
	if ($gruppe->rls && calc_rangliste($gruppe,$stand,$anfang,$wettk,$rang,$rls,$ex_aquo,$ng))
	{
		$akt_cuwr = $rang[$pers->PerId]->platz;
		echo "\t\t".'<td colspan="2" nowrap><i><a href="'.$GLOBALS['dr_config']['ranglist'].'cat='.$gruppe->GrpId.'">'.
			$t_cuwr.'</a>:</i></td>'."\n\t\t<td><b>".($akt_cuwr ? $akt_cuwr.'.' : '')."</b></td>\n";
	}
	for ($i = 0,$year = 0 + $heute[0]; $i < 3 && $year>=$results_since; $year--)
	{
		$serie = str_replace('??',sprintf("%02d",$year % 100),$gruppe->serien_pat);
		$stand = '.';
		read_serie($serie,0);
		if (is_object($serie) && calc_rangliste($gruppe,$stand,$anfang,$wettk,$rang,$rls,$ex_aquo,$ng,$serie) && $year == 0 + $stand)
		{
			if ($i == 1) echo "\t<tr>\n";
			$platz = $rang[$pers->PerId]->platz;
			echo "\t\t".'<td colspan="2" nowrap><i><a href="'.$GLOBALS['dr_config']['ranglist'].'cat='.$gruppe->GrpId.'&amp;cup='.$serie->SerId.'">'.
				$serie->name.'</a>:</i></td>'."\n\t\t<td><b>".($platz ? $platz.'.' : '')."</b></td>\n";
			if (!($i % 2)) echo "\t</tr>\n";
			$i++;
		}
	}
	echo $spacer;

	if ($pers->freetext)
	{
		echo "\n<tr>\t".'<td colspan="6">'.$pers->freetext."</td>\n\t</tr>\n";
		echo "\n<tr>\t".'<td colspan="6">&nbsp;</td>'."\n\t</tr>\n";
	}
}
echo "\t<tr>\n\t\t".'<td colspan="6"><b><a name="results">'.($all || $anz_wk < $anz_best ? '' : $anz_best.' best ').
	"results:</a></b></td>\n\t</tr>\n";

if ($anz_wk)
{
	foreach ($per_wk as $key => $pwk)
	{
		echo "\t<tr>\n\t\t<td>"./*$key.'&nbsp;&nbsp;'.*/$pwk->platz.".</td>\n\t\t".'<td colspan="4">'.($pwk->gkey != $gruppe->rkey ? $pwk->gname.': ' : '').
			'<a href="'.$GLOBALS['dr_config']['result'].'comp='.$pwk->WetId.'&amp;cat='.$pwk->GrpId.'">'.$pwk->name."</a></td>\n\t\t<td>".
			datum($pwk->datum)."</td>\n\t</tr>\n";
	}
}
echo "</table>\n<p>";

echo "<div style='position: relative; width: 100%'>\n";
if (!$all && $anz_wk > $anz_best)
{
	echo '<a href="'.$GLOBALS['dr_config']['pstambl'].'all=1&amp;person='.$pers->PerId.'&amp;cat='.$gruppe->GrpId.'#results">show all results</a>.'."\n";
}
$link = 'https://'.$_SERVER['HTTP_HOST']."/egroupware/ranking/athlete.php?PerId=$pers->PerId&amp;action=profile";
echo "<a href=\"javascript:window.open('$link','_blank','dependent=yes,width=900,height=450,scrollbars=yes,status=yes')\" style='position: absolute; right:10px'>Edit this profile</a>\n";
echo "</div>\n";

do_footer ();
