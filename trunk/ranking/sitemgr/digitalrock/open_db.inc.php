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

error_reporting(E_ALL & ~E_NOTICE);
ini_set('mbstring.internal_encoding','utf-8');
if (!headers_sent()) header('Content-type: text/html; charset=utf-8');

global $gruppe,$grp_inc,$url_drock,$url_icc_info,$t_see_also,$jpgs;
if (!include_once '.db_rang.php')
{
	throw new Exception(".db_rang.php NOT found!".' cwd='.getcwd());
}

if (!$url_drock) $url_drock='http://www.digitalROCK.de';
if (!$url_icc_info) $url_icc_info='http://www.ifsc-climbing.org';

if (defined('DR_PATH'))	// running inside sitemgr
{
	if (!isset($GLOBALS['dr_config']['prefix']))	// old version of sitemgr module
	{
		$GLOBALS['dr_config'] += array(
			'result'  => 'index.php?page_name=result&amp;',
			'pstambl' => 'index.php?page_name=pstambl&amp;',
			'pstambl_target' => 'profil',
			'ranglist' => 'index.php?page_name=ranglist&amp;',
			'startlist' => 'index.php?page_name=startlists&amp;',
			'nat_team_ranking' => 'index.php?page_name=nat_team_ranking&amp;',
			'resultservice' => 'index.php?page_name=resultservice&amp;',
		);
	}
}
else
{
	$GLOBALS['dr_config'] = array(
		'result'  => 'result.php?',
		'pstambl' => 'pstambl.php?',
		'pstambl_target' => false,
		'ranglist' => 'ranglist.php?',
		'nat_team_ranking' => 'nat_team_ranking.php?',
		'startlist' => '/egroupware/ranking/starter.php?',
		'resultservice' => '/egroupware/ranking/result.php?'
	);
}

function do_header($head_title,$title,$align="center",$raise=0,$target='profil',$do_body=1,$load_jquery=false)
{
	global $t_header_logo,$t_dig_rock_alt,$url_drock,$url_icc_info;

	if ($do_body && !defined('DR_PATH'))
	{
?>
<html>
<head>
   <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
   <meta name="Author" content="Ralf Becker [http://www.digitalROCK.de]">
   <meta name="GENERATOR" content="<?php echo substr($_SERVER['PHP_SELF'],1); ?> (c) 2000-<?php echo date('Y'); ?> by Ralf Becker">
   <meta name="KeyWords" content="digital ROCK, Klettern, Wettkampfklettern, Sportklettern, climbing, climbing competitions, UIAA, DAV, ICC, EYC, worldcup, worldranking, CUWR">
   <title>digital ROCK: <?php echo $head_title; ?></title>
<?php
		if ($target) {
			echo "<base target='$target'>\n";
		}
		if ($raise) {
			// focus window and hide location bar for mobil browsers
?>
   <script language="JavaScript">
      window.focus();
      window.setTimeout(function(){window.scrollTo(0, 1);}, 100);
   </script>
<?php
		}
		if ($load_jquery) echo '<script src="'.($_SERVER['HTTPS']?'https://':'http://').'ajax.googleapis.com/ajax/libs/jquery/1.6.2/jquery.min.js"></script>'."\n";
?>
</head>
<body text="#000000" bgcolor="#FFFFFF">
<?php
	} // do_body
	if ($do_body != 2)
	{
?>
<table width="100%">
 <tr>
  <?php echo $t_header_logo; ?>
  <td align="<?php echo $align; ?>" class="onlyPrint"><font size="+2">
   <?php echo preg_replace('/ - /','<br />',$title); ?>
  </font></td>
<?php if (!defined('DR_PATH')) {		// we dont run inside sitemgr
?>
  <td width="10%" class="onlyPrint"><a href="<?php echo $url_drock; ?>" target=_blank><img src="http://www.digitalrock.de/dig_rock.klein.jpg" title="<?php echo $t_dig_rock_alt; ?>" border="0" /></a></td>
<?php } else { ?>
<style type="text/css">
<!--
@media print {
	#divAppboxHeader { display: none; }
}
-->
</style>
<?php } ?>
 </tr>
</table>
<?php
	}
}

global $see_also_done,$grp2old;
$see_also_done=0;

$grp2old = array(	// mapt neue auf alte Gruppennamen, zb. fuer grp_in_grps
	// int. Gruppen
	'ICC_F'   => 'WOMEN',
	'ICC_FB'  => 'BWOMEN',
	'ICC_FS'  => 'SWOMEN',
	'ICC_FX'  => 'AWOMEN',
	'ICC_M'   => 'MEN',
	'ICC_MB'  => 'BMEN',
	'ICC_MS'  => 'SMEN',
	'ICC_MO'  => 'OMEN',
	'ICC_MX'  => 'AMEN',
	'ICC_F_J' => 'W_JUNIOR',
	'ICC_F_A' => 'W_JUG_A',
	'ICC_F_B' => 'W_JUG_B',
	'ICC_M_J' => 'M_JUNIOR',
	'ICC_M_A' => 'M_JUG_A',
	'ICC_M_B' => 'M_JUG_B',
	// deutsche Gruppen
	'GER_F'   => 'DAMEN',
	'GER_FB'  => 'BDAMEN',
	'GER_FS'  => 'SDAMEN',
	'GER_M'   => 'HERREN',
	'GER_MB'  => 'BHERREN',
	'GER_MS'  => 'SHERREN',
	'GER_F_X' => 'MAEDELS',
	'GER_M_A' => 'JUGEND_A',
	'GER_M_B' => 'JUGEND_B',
	'GER_M_J' => 'JUNIOR',
	// schweizer Gruppen
	'SUI_F'   => 'SUI_D_1',
	'SUI_F_2' => 'SUI_D_2',
	'SUI_F_3' => 'SUI_D_3',
	'SUI_M'   => 'SUI_H_1',
	'SUI_M_2' => 'SUI_H_2',
	'SUI_M_3' => 'SUI_H_3',
	'SUI_F_J' => 'SUI_D_J',
	'SUI_F_A' => 'SUI_D_A',
	'SUI_F_B' => 'SUI_D_B',
	'SUI_F_X' => 'SUI_D_AB',
	'SUI_F_M' => 'SUI_D_M',
	'SUI_M_J' => 'SUI_H_J',
	'SUI_M_A' => 'SUI_H_A',
	'SUI_M_B' => 'SUI_H_B',
	'SUI_M_M' => 'SUI_H_M',
);

function see_also($txt)
{
	global $see_also_done,$t_see_also;
	if (!$see_also_done)
	{
		echo '
<div class="noPrint">
<hr><p>
<style>
#see_also li { margin-top: 10px; }
</style>
';
      echo '<b> '.$t_see_also."</b>\n".'<ul id="see_also">'."\n";
      $see_also_done++;
   }
   echo "\t".$txt;
}

function do_footer($do_body=True)
{
	global $t_footer_inc,$see_also_done,$mysql;

	if ($see_also_done)
	{
		echo "</ul></div>\n";
		echo "<hr width='100%'/>\n";
	}
	else
	{
		echo "<p>\n";
	}
	if (!defined('DR_PATH'))	// not running in sitemgr
	{
		include ($t_footer_inc.'.inc.php');

		if ($do_body) echo "</body>\n</html>\n";
	}
}

function wettk_datum($wettk)
{
	if (is_object( $wettk ))
	{
		preg_match( "/.*@(.*)/",$wettk->gruppen,$grps );
		preg_match( "/([0-9]{4})-([0-9]{1,2})-([0-9]{1,2})/",$wettk->datum,$dats );
		if ($grps[1] && !($grps[1]+0))	// keine Zahl --> statt Tag+Monat verw.
		{
			return ("$grps[1] $dats[1]");
		}					// wenn nichts angegeb. und ab 2001, dann wettk 2-taegig
		$days = $grps[1] ? $grps[1]+0 : ($dats[1] >= 2001 ? 2 : 1);

		$ende = mktime( 0,0,0,$dats[2],$dats[3]+$days-1,$dats[1] );
		$day = date( "d",$ende );
		$month = date( "m",$ende );

		return sprintf("$dats[3]%s.$month.$dats[1]",$days > 1 ? sprintf( "%s.%s%02d",
			$month != $dats[2] ? sprintf( ".%02d",$dats[2] ) : '',$days > 2 ? '-' : '/',$day ) : '' );
	}
}

function date_over($date,$today_is_over=false)
{
	list($y,$m,$d) = explode('-',$date);
	list($now_y,$now_m,$now_d) = explode('-',date('Y-m-d'));

	$ret = $y_now > $y || $y_now == $y && ($m_now > $m || $m_now == $m && $d_now+(int)$today_is_over >= $d);
	//error_log(__METHOD__."('$date', $today_is_over) y=$y,m=$m,d=$d, now_y=$now_y,_m=$now_m,_d=$now_d returning $ret");
	return $ret;
}

function grp_in_grps($grp,$grps)
{
	global $grp2old;

	list($grps) = explode('@',$grps);
	$grps = preg_replace('/=[^,]*/','',$grps);
	return stristr( ",".$grps.",",",".$grp."," ) || $grps && preg_match('/^'.$grps.'$/i',$grp ) ||
		(isset($grp2old[$grp]) && (stristr( ",".$grps.",",",".$grp2old[$grp]."," ) || $grps && preg_match( '/^'.$grps.'$/i',$grp2old[$grp] )));
}

function check_group_sql($gruppe)
{
	$sql = '';
	$gruppen = $gruppe->mgroups ? array_values($gruppe->mgroups) : array($gruppe->rkey,$grp2old[$gruppe->rkey]);
	foreach($gruppen as $grp)
	{
		if (strlen($grp))
		{
			$sql .= ($sql ? ' OR ':'').
				"find_in_set('$grp',if(instr(gruppen,'@'),left(gruppen,instr(gruppen,'@')-1),gruppen))".
				" OR '$grp' regexp if(instr(gruppen,'@'),left(gruppen,instr(gruppen,'@')-1),gruppen)";
		}
	}
	return $sql;
}

// Anzahl gewertete Wettkaepfe fuer Gruppe $gruppe (rkey oder object) und Serie $serie ermitteln
function get_max_wettk($serie,$gruppe)
{
	$grp = is_object($gruppe) ? $gruppe->rkey : $gruppe;
	if (preg_match('/'.preg_quote($grp).'=[^+]*\+([^,]*)/i',$serie->gruppen,$matches))
	{
		//echo "<p>get_max_wettk($serie->rkey,$grp) = $matches[1]</p>\n";
		return (int) $matches[1];
	}
	// combined ranking --> 5 per cat
	if (is_object($gruppe) && is_array($gruppe->mgroups) && count($gruppe->mgroups) > 1)
	{
		global $t_per_cat;
		if (!$t_per_cat) $t_per_cat = 'per category';
		return 5 .' '.$t_per_cat;

		// old minimum of all cats
		foreach($gruppe->mgroups as $cat)
		{
			read_gruppe($cat);
			$max = get_max_wettk($serie,$cat);
			if (!$max_wettk || (int)$max < (int)$max_wettk) $max_wettk = $max;
		}
		return $max_wettk;
	}
	if ($serie->max_serie < 0 && is_object($gruppe))	// $max_wettk less then the total
	{
		$sql = check_group_sql($gruppe);
		$res = my_query($sql="SELECT count(*) FROM Wettkaempfe WHERE ".
			($gruppe->nation ? "nation='$gruppe->nation'" : "ISNULL(nation)")." AND serie=$serie->SerId AND ($sql)");
		$anz_wettk = (int)mysql_result($res,0,0);
		//echo "<p>$sql: anz_wettk=$anz_wettk</p>\n";
		return ($serie->max_serie + $anz_wettk)." (=$anz_wettk$serie->max_serie)";
	}
	return $serie->max_serie;
}

function setup_grp(&$gruppe)
{
	global $grp_inc;

	read_gruppe( $gruppe );

	if (!$gruppe->nation)	        // Internationale Ergebnisse
		$grp_inc = "icc.inc.php";
	else					// Nationale Ergebnisse
		$grp_inc = "$gruppe->nation.inc.php";
}

function jahrgang( $gruppe,$stand,&$from_year,&$to_year)
{
	if (($from_year = $gruppe->from_year) < 0) // neg. ist Altersangabe
	{
		$from_year += $stand;
	}
	if (($to_year = $gruppe->to_year) < 0)
	{
		$to_year += $stand;
	}
	if ($from_year > $to_year)
	{
		$y = $from_year; $from_year = $to_year; $to_year = $y;
	}
	return $gruppe->from_year && $gruppe->to_year;
}

function datum($date,$long = True)
{
	if (!preg_match('/'."([0-9]{4})-([0-9]{1,2})-([0-9]{1,2})".'/',$date,$regs))
	{
		return '';
	}
	return "$regs[3].$regs[2].".($long ? $regs[1] : sprintf('%02d',$regs[1] % 100));
}

function fed_join($per_table='Personen',$year=null,$f='')
{
	return " JOIN Athlete2Fed ON $per_table.PerId=Athlete2Fed.PerId AND ".
		(is_null($year) ? 'a2f_end=9999' : "a2f_end >= $year AND $year >= a2f_start").
		" JOIN Federations $f ON Athlete2Fed.fed_id=".($f?$f:'Federations').".fed_id";
}

function read_pers(&$pers,$do_fail=True)
{
	$perId = $pers;
	if (!is_object ($pers))
	{
		$pers = addslashes($pers);

		$res = my_query ("SELECT * FROM Personen ".fed_join().' WHERE '.(is_numeric($pers) ? "Personen.PerId=$pers" : "rkey='$pers'"),__FILE__,__LINE__);

		$pers = $res ? mysql_fetch_object($res) : False;
	}
	if ($do_fail && !is_object ($pers))
	{
		fail("Error: unknown PerId '$perId' !!!");
	}
	return ($pers);
}

function read_wettk(&$wettk,$do_fail=True)
{
	$wettkId = $wettk;
	if (!is_object ($wettk))
	{
		$wettk = addslashes(urldecode($wettk));

		$res = my_query ("SELECT * FROM Wettkaempfe WHERE ".(is_numeric($wettk) ? "WetId=$wettk" : "rkey='$wettk' OR name='$wettk'"),__FILE__,__LINE__);

		$wettk = $res ? mysql_fetch_object($res) : False;
	}
	if ($do_fail && !is_object ($wettk))
	{
		fail("Error: unknown WettkId '$wettkId' !!!");
	}
	return ($wettk);
}

function read_serie(&$serie,$do_fail=True)
{
	$serId = $serie;
	if (!is_object ($serie))
	{
		$serie = addslashes($serie);

		$res = my_query ("SELECT * FROM Serien WHERE ".(is_numeric($serie) ? "SerId=$serie" : "rkey='$serie'"),__FILE__,__LINE__);

		$serie = $res ? mysql_fetch_object($res) : False;
	}
	if ($do_fail && !is_object ($serie))
	{
		fail("Error: unknown SerId '$serId' !!!");
	}
	return $serie;
}

function read_gruppe(&$gruppe,$do_fail=True)
{
	global $grp2old;

	$grpId = $gruppe;
	if (!is_object ($gruppe))
	{
		$gruppe = addslashes(urldecode($gruppe));

		$res = my_query ($query = "SELECT * FROM Gruppen WHERE ".(is_numeric($gruppe) ? "GrpId=$gruppe" : "rkey='$gruppe' ".
			(($newId = array_search($gruppe,$grp2old)) !== False ?  "OR rkey='$newId' " : '').
			"OR name='$gruppe'"),__FILE__,__LINE__);

		$gruppe = $res ? mysql_fetch_object ($res) : False;
	}
	if ($do_fail && !is_object ($gruppe))
	{
		fail("Error: unknown GroupId '$grpId' !!!");
	}
	if (!$gruppe->nation)	// Internationale Ergebnisse
		$grp_inc = "icc.inc.php";
	else					// Nationale Ergebnisse
		$grp_inc = "$gruppe->nation.inc.php";

	if (defined('DR_PATH')) $grp_inc = DR_PATH.'/'.$grp_inc;

	// soll spaeter aus Datenbank gelesen werden
	global $mgroups,$mgroup_pktsys;
	require $grp_inc;
	$gruppe->mgroups = isset($mgroups[$gruppe->rkey]) ? $mgroups[$gruppe->rkey] : 0;
	$gruppe->mgroup_pktsys = $mgroup_pktsys;

	$gruppe->GrpIds = $gruppe->GrpId;
	if ($gruppe->mgroups && @count($gruppe->mgroups)) {
		$gruppe->GrpIds = implode(',',array_keys($gruppe->mgroups));
	}
	return ($gruppe);
}

function nation_city_sektion( $per,$gruppe )
{
	global $t_verband_del;

	// return "$per->verband:$t_verband_del:$per->ort";

	if (!$gruppe->nation)
	{
		return htmlspecialchars($per->nation);
	}
	if (!$t_verband_del)
	{
		return htmlspecialchars($per->ort);
	}					// Verband vor Sektion l�chen
	if (!($sektion = preg_replace('/'.$t_verband_del.'/i',"",$per->verband)) && $per->ort)
	{
		return '<i>'.htmlspecialchars($per->ort).'</i>';	// keine Sektion angegeben --> Ort
	}
	if ($sektion && $per->fed_url)
	{
		return '<a href="'.htmlspecialchars($per->fed_url).'" target="_blank">'.htmlspecialchars($sektion).'</a>';
	}
	return htmlspecialchars($sektion);
}

function per_link($per,$gruppe,$jahrgang = true)
{
	if (!($ncs = nation_city_sektion( $per,$gruppe ))) $ncs = '&nbsp;';
	if (!($year = substr($per->geb_date,0,4))) $year = '&nbsp;';

	$a_href = '<a href="'.$GLOBALS['dr_config']['pstambl'].'person='.$per->PerId.'&amp;cat='.$gruppe->GrpId.'"'.
		($GLOBALS['dr_config']['pstambl_target'] ? ' target="'.$GLOBALS['dr_config']['pstambl_target'].'"' : '').'>';

	echo "\t\t".'<td align="left" style="text-transform: uppercase;">'.$a_href.htmlspecialchars($per->nachname) . "</a></td>\n\t\t".
		'<td align="left">'.$a_href.htmlspecialchars($per->vorname)."</a></td>\n\t\t<td>".
		($jahrgang && $gruppe->from_year && $gruppe->to_year ? $year."</td>\n\t\t<td>" : '').$ncs."</td>\n";
}

function wettk_link_str($wettk,$text='',$class='')
{
	global $url_drock,$url_icc_info;

	if ($class)
		$class = ' class="'.$class.'"';

	if (!$text)
		$text = $wettk->name;

	if (!$wettk->homepage)
		return $text;

	if (!stristr($wettk->homepage,'http://'))
	{
		if (preg_match('/^([0-9][0-9]+)_.*/',$wettk->homepage,$args))
		{
			$year = $args[1] + ($args[1] < 90 ? 2000 : 1900);
		}
		$homepage = $url_drock.($year ? '/'.$year : '').'/'.$wettk->homepage;
	}
	else
	{
		$homepage = $wettk->homepage;
	}
	return '<a '.$class.' href="'.htmlspecialchars($homepage).'" target="_blank">'.$text.'</a>';
}

function get_pkte ($PktId,&$pkte)
{
	$PktId = addslashes($PktId);

	if (!intval($PktId) && ($res = my_query("SELECT PktId FROM PktSysteme WHERE rkey='$PktId'",__FILE__,__LINE__)) &&
	    $row = mysql_fetch_object($res))
	{
		$PktId = $row->PktId;
	}
	$res = my_query($sql="SELECT platz,pkt FROM PktSystemPkte WHERE PktId='$PktId'",__FILE__,__LINE__);
	while ($row = mysql_fetch_object($res))
	{
		$max_pkte += ($pkte[$row->platz] = $row->pkt);
	}
	return ($max_pkte);
}

function mailto($email,$content='',$at=' AT ',$dot=' DOT ')
{
	list($name,$domain) = explode('@',$email);
	$d_parts = "'".implode("'+unescape('%2E')+'",explode('.',$domain))."'";
	$anti_spam = $name . $at . str_replace('.',$dot,$domain);

	if (!$content)
	{
		$content = $anti_spam;
	}
	else
	{
		$titel = ' title="'.$anti_spam.'"';
	}
	return '<a href="#" onclick="location.href=\'mai\'+\'lto:\'+\''.$name.'\'+unescape(\'%40\')+'.$d_parts.'; return false;"'.$titel.'>'.$content.'</a>';
}

function fail ($error,$more = '')
{
	echo "<h2>$error</h2>\n";
	echo "<p><b>URL:</b> 'http://".$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']."'</p>\n";
	echo '<p><b>Referer:</b> <a href="'.htmlspecialchars($_SERVER['HTTP_REFERER']).'" title="return to the refering page">'.$_SERVER['HTTP_REFERER']."</a></p>\n";
	if ($more)
		echo $more;

	echo '<p>Please contact '.mailto('RalfBecker@digitalrock.de').
		' to report the error and include the complete error-message.</p>';
	exit;
}

function prepare_var($name,$hows,$from,$default='')
{
	$var = get_param($name,$from,$default);

	if (!is_array($hows))
	{
		$hows = explode(',',$hows);
	}
	foreach($hows as $how)
	{
		switch($how)
		{
			case 'int': case 'intval':
				$var = (int) $var;
				break;
			case 'slashes': case 'addslashes':
				$var = addslashes($var);
				break;
			case 'upper': case 'strtoupper':
				$var = strtoupper($var);
				break;
			default:
				echo "<p>perpare_var(): Unknown mode '$how'</p>\n";
		}
	}
	return $var;
}

function get_param($name,$from,$default='')
{
	if (defined('DR_PATH') && $GLOBALS['dr_config']['params'][$name])		// we run inside sitemgr
	{
		//error_log("get_param('$name',".print_r($from,true).",'$default') from dr_config='{$GLOBALS['dr_config']['params'][$name]}'");
		return $GLOBALS['dr_config']['params'][$name];
	}
	if (!is_array($from))
	{
		$from = explode(',',$from);
	}
	foreach ($from as $f)
	{
		switch($f)
		{
			case 'POST': $params =& $_POST; break;
			case 'GET':  $params =& $_GET;  break;
			default:     $params =& $_REQUEST; break;
		}
		if (isset($params[$name]))
		{
			//error_log("get_param('$name',".print_r($from,true).",'$default') from $f='{$params[$name]}'");
			return $params[$name];
		}
		if (is_numeric($f) && $_SERVER['QUERY_STRING'] && strchr($_SERVER['QUERY_STRING'],'=') === False)
		{
			$query = preg_split('/[ +]/',urldecode($_SERVER['QUERY_STRING']));

			if (isset($query[intval($f)]))
			{
				//error_log("get_param('$name',".print_r($from,true).",'$default') from QUERY_STRING='{$query[$f]}'");
				return $query[$f];
			}
		}
	}
	//error_log("get_param('$name',".print_r($from,true).",'$default') default='$default'");
	return $default;
}

if (!function_exists('_debug_array'))
{
	function _debug_array($array)
	{
		echo "<pre>".print_r($array,true)."</pre>\n";
	}
}

// mysql_pconnect tut seit 30.03.2003 nicht mehr auf www.digitalROCK.de
global $mysql;
$mysql = @mysql_connect($hostname,$username,$password) or
   fail ("Couldn't open Database Connection !!!",
         $_SERVER['HTTP_HOST'] == 'localhost' ? mysql_error() : '');

@mysql_select_db ($db_name,$mysql) or
   fail ("Couldn't open Database !!!",
         $_SERVER['HTTP_HOST'] == 'localhost' ? mysql_error() : '');
my_query("SET NAMES 'utf8'");

function my_query ($query,$file='',$line='')
{
	global $mysql;

	$res = mysql_query($query,$mysql) or
		fail ('Error querying the Database !!!',mysql_error()."<p><table><tr><td><b>Query</b>:</td><td>$query</td></tr>\n".
			($file || $line ? "<tr><td><b>File</b>:</td><td>$file</td></tr>\n<tr><td><b>Line</b>:</td><td>$line</td></tr>":'')."</table>\n");

	return $res;
}

// echo "HTTP_ACCEPT_LANGUAGE: ".$_SERVER['HTTP_ACCEPT_LANGUAGE']."<p>";

$langs = isset($_GET['lang']) ? array($_GET['lang']) : explode( ", ",$_SERVER['HTTP_ACCEPT_LANGUAGE'] );

for ($i = 0; $langs[$i]; ++$i)
{
	$file = substr($langs[$i],0,2).".inc.php";
	if (file_exists( $file ))
	{
		// echo "Benutze $file fuer $langs[$i]";
		break;
	}
}

if (!file_exists( $file ))
{
   $file = "en.inc.php";
   // echo "Benutze default $file fuer $langs[$i]";
}

require_once( $file );
