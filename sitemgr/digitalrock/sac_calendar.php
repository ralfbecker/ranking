<?php

if (basename($_SERVER['PHP_SELF']) == basename(__FILE__) &&
	!in_array($_SERVER['HTTP_HOST'], ['localhost', 'ralfsmac.local', 'boulder.egroupware.org']))
{
	include_once('cache.php');
	do_cache();
}
require_once ('open_db.inc.php');

global $header_logos,$calendar_no_cats,$cats,$anz_year;

$header_logos ="<table>
 <tr>
  <td><a href=\"$url_drock\" title=\"$t_dig_rock_alt\" target=\"_blank\"><img src='dig_rock.mini.gif' border=0></a></td>
  <td><A HREF=\"http://www.sac-cas.ch\" target=_blank><IMG SRC=\"sac110.gif\" title=\"Schweizer Alpen-Club - Club Alpin Suisse\" BORDER=0></A></td>
 </tr>
</table>
";

require ('icc.inc.php');	// read int. settings before, national ones
$gruppe = 'SUI_F';
setup_grp ($gruppe);			// defaults f�r Gruppe setzen
require ($grp_inc);

$cats = array(
	'int' => array(
		'label'  => 'International',
		'nation' => '',
		'grps'	 => $icc_adults+$icc_youth,
//		'wettk_reg' => '^[0-9]{2,2}_(WC|WM|EM|MA|IE|TR|AM|AC|SM|RE|LM){1,1}.*',
		'wettk_reg' => '^[0-9]{2,2}[_^E]{1}[^YJ]{1,1}.*',
		'serie_reg' => '^[0-9]{2,2}_(WC|TR){1,1}.*',
		'rang_title'=> 'CUWR continuously updated WORLDRANKING',
		'bgcolor'   => '#B8C8FF',
		'nat_team_ranking' => !isset($_REQUEST['year']) || $_REQUEST['year'] >= 2005,
		'cat_id'    => array(68,69,70,86,259),
	),
	'youth' => array(
		'label'  => 'Int. Jugend',
		'nation' => '',
		'grps'	 => $icc_youth,
		'wettk_reg' => '^[0-9]{2,2}(EYC|_J|_Y){1,1}.*',
		'serie_reg' => '^[0-9]{2,2}_EYC',
		'rang_title'=> '',
		'bgcolor'   => '#D8E8FF',
		'cat_id'    => array(71,258),
	),
	'sui' => array(
		'label'  => 'Erwachsene',
		'nation' => 'SUI',
		'grps'	 => $sac_adults,
		'wettk_reg' => '^[0-9]{2,2}_[^R].*',
		'serie_reg' => '.*',
		'rang_title'=> 'SWISS RANKING',
		'bgcolor'   => 'A8F0A8',
		'cat_id'    => array(62,63),
	),
	'sui_jugend' => array(
		'label'  => 'Jugend',
		'nation' => 'SUI',
		'grps'	 => $sac_youth,
		'wettk_reg' => '^[0-9]{2,2}_[^R].*',
		'serie_reg' => '.*',
		'rang_title'=> 'SWISS RANKING',
		'bgcolor'   => 'D8FFD8',
		'cat_id'    => array(65),
	),
	'sui_local' => array(
		'label'  => 'RegioCups',
		'nation' => 'SUI',
		'grps'	 => $sac_adults+$sac_youth,
		'wettk_reg' => '^[0-9]{2,2}_RG_.*',
//		'serie_reg' => '',
		'rang_title'=> '',
		'bgcolor'   => '#F0F0F0',
		'cat_id'    => array(64),
	),
	'sui_ice' => array(
		'label'  => 'Iceclimbing',
		'nation' => 'SUI',
		'grps'	 => array (),
		'wettk_reg' => '^[0-9]{2,2}_RC_.*',
//		'serie_reg' => '',
		'rang_title'=> '',
		'bgcolor'   => '#F0F0F0',
		'cat_id'    => array(84),	// needs own category, as we have not groups to distinguiche from sui_local!
	),
);

do_header ($t_calendar,'','',0,'',2, $mode != 2);	// load jQuery for mode != 2

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
	font-size: 28px;
	font-weight: bold;
}
-->
</style>
';

$anz_year = 0;
include ('wettk_list_multi.inc.php');

do_footer ();
