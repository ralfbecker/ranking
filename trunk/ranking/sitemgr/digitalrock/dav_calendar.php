<?php
/* $Id$ */

if (basename($_SERVER['PHP_SELF']) == basename(__FILE__) &&
	!in_array($_SERVER['HTTP_HOST'],array('localhost','ralfsmacbook.local','boulder.outdoor-training.de')))
{
	include_once('cache.php');
	do_cache();
}
$no_dav = isset($_GET['no_dav']) || defined('DR_PATH');

$dav_home = $dav_link = 'http://www.alpenverein.de';
if (!$no_dav)
{
	$dav_page = '/template_loader.php?tplpage_id=194';
	$dav_cache = 'dav_layout.php';
	$dav_update_freq = 6*60*60; // 6h
	$dav_start = '<!-- START: breadcrumb to footer nav including right banner 1 -->';
	$dav_ende  = '<!-- END: breadcrumb to footer nav including right banner 1 -->';
	$dav_sidebar = '<!-- Statische Links Ende-->';
	$dav_title = 'zur&uuml;ck zur Startseite';
}
else
{
	$dav_title = "hier geht's zur Homepage des Deutschen Alpenvereins";
}

require_once ('open_db.inc.php');

global $header_logos,$calendar_no_cats,$cats,$anz_year;

$header_logos ="<table>
 <tr>
  <td><a href='/' title=\"$t_dig_rock_alt\" target=\"_blank\"><img src='dig_rock.mini.gif' border=0></a></td>
  <td><a href='$dav_link' title=\"$dav_title\"><img src='dav.mini.gif' border=0></a></td>
 </tr>
</table>
";

if (!$no_dav)
{
	@include $dav_cache;

	if (!isset($dav_update) || $dav_update < time()-$dav_update_freq) {
		$page = mb_convert_encoding(str_replace('charset=iso-8859-1','charset=utf-8',file_get_contents($dav_home.$dav_page)),'utf-8','iso-8859-1');

		if (($start = strpos($page,$dav_start)) === false ||
			($dav_right = strstr($page,$dav_ende)) == false) {
			echo "<h1>Wrong format of $dav_home$dav_page</h1>\n";
			echo str_replace("\n","<br>\n",htmlspecialchars($page));
			exit;
		}
		$dav_left = substr($page,0,$start).$dav_start."\n";

		foreach(array('dav_left','dav_right') as $var)
		{
			$$var = ereg_replace('(href=|src=|background=|window.open\()"([^h])','\1"'.$dav_home.'/\2',$$var);
			$$var = str_replace(array(
					"='/images",
					"'template_loader.php",
					$dav_home.$dav_page,
				),array(
					"='".$dav_home.'/images',
					"'".$dav_home.'/template_loader.php',
					$PHP_SELF,
				),$$var);
		}
		$dav_left .= "\n".'<div class="GLOBALBoxedContent">';
		$dav_right = "\n</div><br>\n".$dav_right;

		$f = @fopen($dav_cache,'w');
		@fwrite($f,"<?php\n".'$dav_update='.time().";\n".
			'$dav_left=\''.str_replace("'","\\'",$dav_left)."';\n".
			'$dav_right=\''.str_replace("'","\\'",$dav_right)."';\n");
		@fclose($f);
	}
}
//$dav_right=$dav_left='';

require ('icc.inc.php');	// int. Einstellungen lesen, vor nationalen
$gruppe = 'GER_F';
setup_grp ($gruppe);			// defaults fr Gruppe setzen
require ($grp_inc);

$cats = array(
	'int' => array(
		'label'  => 'International',
		'nation' => '',
		'grps'   => $icc_adults,
//		'wettk_reg' => '^[0-9]{2,2}_(WC|WM|EM|MA|IE|TR|AM|AC|SM|RE|LM){1,1}.*',
		'wettk_reg' => '^[0-9]{2,2}[_^E]{1}[^YJ]{1,1}.*',
		'serie_reg' => '^[0-9]{2,2}_(WC|TR){1,1}.*',
		'rang_title'=> 'CUWR continuously updated WORLDRANKING',
		'bgcolor'   => '#B8C8FF',
		'nat_team_ranking' => !isset($_REQUEST['year']) || $_REQUEST['year'] >= 2005,
		'cat_id' => array(68,69,70,86),
	),
	'youth' => array(
		'label'  => 'Int. Jugend',
		'nation' => '',
		'grps'   => $icc_youth,
		'wettk_reg' => '^[0-9]{2,2}(EYC|_J|_Y){1,1}.*',
		'serie_reg' => '^[0-9]{2,2}_EYC',
		'rang_title'=> '',
		'bgcolor'   => '#D8E8FF',
		'cat_id' => array(71),
	),
	'ger_boulder' => array(
		'label'  => 'Bouldern',
		'nation' => 'GER',
		'grps'   => $ger_bouldern+$ger_junior,
		'wettk_reg' => '^[0-9]{2,2}_B+.*',
		'serie_reg' => '^[0-9]{2,2}_BC',
		'rang_title'=> 'Deutsche Boulder RANGLISTE',
		'bgcolor'   => 'FFDBA8',
		'cat_id' => array(59),
	),
	'ger' => array(
		'label'  => 'Sportklettern',
		'nation' => 'GER',
		'grps'   => $ger_adult,
		'serie_grps' => $ger_adult+$ger_junior,
		'wettk_reg' => '^[0-9]{2,2}[_J]{1,1}[^WLJ]+.*',
		'serie_reg' => '^[0-9]{2,2}_DC',
		'rang_title'=> 'Deutsche RANGLISTE',
		'bgcolor'   => 'A8F0A8',
		'cat_id' => array(57),
	),
	'ger_speed' => array(
		'label'  => 'Speed',
		'nation' => 'GER',
		'grps'   => $ger_speed,
		'wettk_reg' => '^[0-9]{2,2}[_J]{1,1}[^WLJ]+.*',
		'serie_reg' => '',
		'rang_title'=> '',
		'bgcolor'   => 'A8F0A8',
		'cat_id' => array(60),
	),
	'ger_jugend' => array(
		'label'  => 'Jugend',
		'nation' => 'GER',
		'grps'   => $ger_youth,
		'wettk_reg' => '^[0-9]{2,2}[_J]{1,1}[^WL]+.*',
		'serie_reg' => '^[0-9]{2,2}_JC',
//		'rang_title'=> 'Deutsche Jugend RANGLISTE',
		'bgcolor'   => 'D8FFD8',
		'cat_id' => array(57,58),
	),
	'ger_state' => array(
		'label'  => 'Landesmeisterschaft',
		'nation' => 'GER',
		'grps'   => $ger_adult + $ger_youth + $ger_bouldern + $ger_ak,
		'wettk_reg' => '^[0-9]{2,2}[_J]{1,1}LM[0-9_]{1,1}.*',
		'serie_reg' => '^[0-9]{2,2}[_J]{1,1}LM.*',
		'rang_title'=> '',
		'bgcolor'   => '#F0F0F0',
		'cat_id' => array(61,56),
	),
);

if (!$no_dav)
{
	echo $dav_left;
}
else
{
	do_header ($t_calendar,'','',0,'',2, $mode != 2);	// load jQuery for mode != 2
}

echo '<style type="text/css">
<!--
.mini, .mini_no, .mini_red, .mini_link {
	font-size: 11px;
}

.mini_no {
	font-style: italic;
}

.mini_red, a.mini_link, a.mini_link:hover {
	color: red;
	font-weight: normal;
}

.comp, .comp_cup, .comp_date, a.comp_link, a.rank_link {
	font-size: 13px;
}

a.rank_link {
//	font-weight: bold;
	font-size: 16px;
}

.comp_cup {
	font-weight: bold;
}

.comp {
//	font-style: italic;
}

a.mini_link:hover, a.comp_link:hover {
	text-decoration: underline;
}

a.mini_link:hover {
	font-weight: normal;
}

.cal_head {
	font-size: 24px;
	font-weight: bold;
}
-->
</style>
';

$anz_year = 0;
if (!$no_dav)
{
	echo "<table width='99%'><tr><td>\n";
}
include ('wettk_list_multi.inc.php');

do_footer ($no_dav);

if (!$no_dav)
{
	echo "</td></tr></table>";

	$cup_menu = '<br>
<table width="100%" cellpadding="10" background="http://www.alpenverein.de/pix/edelweiss_rechts1.gif">
	<tr>
		<td align="center">
			<h1 align="center" style="color: #E050E0; font-size: 10pt; letter-spacing: 2px; margin-top: 10pt;">SPONSOREN<br>IM REFERAT<br>SPITZENSPORT</h1>
		</td>
	</tr><tr>
		<td align="center">
			<a href="http://www.alpenverein.de/spitzenberg/sponsoren.php?open=spitze#elvia"><img src="http://www.alpenverein.de/pix/Logo_Elvia.gif" width="150" height="37" border="0"></a>
		</td>
	</tr>
</table>
<table width="100%" cellpadding="10" background="http://www.alpenverein.de/pix/edelweiss_rechts2.gif" >
	<tr>
		<td align="center">
			<a href="http://www.alpenverein.de/spitzenberg/sponsoren.php?open=spitze#muenchen"><img src="/2003/dav-sponsoren/outdoor_ispo.gif" width="150" height="35" border="0"></a>
		</td>
	</tr><tr>
		<td align="center">
			<a href="http://www.alpenverein.de/spitzenberg/sponsoren.php?open=spitze#invia"><img src="/2003/dav-sponsoren/invia.gif" width="150" height="87" border="0"></a>
		</td>
	</tr><tr>
		<td align="center">
			<a href="http://www.alpenverein.de/spitzenberg/sponsoren.php?open=spitze#sportiva"><img src="http://www.alpenverein.de/pix/Logo_La_Sportiva.gif" width="150" height="80" border="0"></a>
		</td>
	</tr><tr>
		<td align="center">
			<a href="http://www.alpenverein.de/spitzenberg/sponsoren.php?open=spitze#krimmer"><img src="/2003/dav-sponsoren/krimmer.gif" width="150" height="81" border="0"></a>
		</td>
	</tr><tr>
		<td align="center">
			<a href="http://www.alpenverein.de/spitzenberg/sponsoren.php?open=spitze#reiter"><img src="/2003/dav-sponsoren/reiter.gif" width="135" height="112" border="0"></a>
		</td>
	</tr><tr>
		<td align="center">
			<a href="http://www.alpenverein.de/spitzenberg/sponsoren.php?open=spitze#riap"><img src="/2003/dav-sponsoren/riap.gif" border="0"></a>
		</td>
	</tr><tr>
		<td align="center">
			<a href="http://www.alpenverein.de/spitzenberg/sponsoren.php?open=spitze#salomon"><img src="/2003/dav-sponsoren/salomon.gif" border="0"></a>
		</td>
	</tr>
</table>
';

	echo str_replace($dav_sidebar,$dav_sidebar."\n".$cup_menu,$dav_right);
}
?>
