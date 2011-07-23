<?php

/* $Id$ */

require_once ('open_db.inc.php');

global $header_logos,$calendar_no_cats,$cats,$anz_year;

$header_logos = defined('DR_PATH') ? '' : "<a href=\"$url_icc_info\" title=\"$t_icc_alt\"><IMG SRC=\"ifsc-100.gif\" border=0></a>";

$calendar_no_cats = True;

$gruppe = 'ICC_F';
setup_grp ($gruppe);			// defaults fr Gruppe setzen
require ($grp_inc);
$cats = array(
	'int' => array(
		'label'  => 'IFSC',
		'nation' => '',
		'grps'	 => $icc_adults,
		'serie_grps' => $icc_adults + (!isset($_REQUEST['year']) || $_REQUEST['year'] >= 2008 ? $icc_combined : array()),
		'wettk_reg' => '^[0-9]{2,2}_(WC|WM|EM|EC|LC|TR|AM|AC|SM|LM|NAC){1,1}.*',
		'serie_reg' => '^[0-9]{2,2}_(WC|TR){1,1}.*',
		'rang_title'=> 'Worldranking',
		'bgcolor'   => '#B8C8FF',
		'nat_team_ranking' => !isset($_REQUEST['year']) || $_REQUEST['year'] >= 2005,
	),
	'youth' => array(
		'label'  => 'Youth/EYC',
		'nation' => '',
		'grps'	 => $icc_youth,
		'wettk_reg' => '^[0-9]{2,2}(EYC|_Y|_JWM|_NAC){1,1}.*',
		'serie_reg' => '^[0-9]{2,2}_EY[CS]',
//		'rang_title'=> '',
		'bgcolor'   => '#D8E8FF',
		'nat_team_ranking' => !isset($_REQUEST['year']) || $_REQUEST['year'] >= 2005,
	),
	'masters' => array(
		'label'  => 'Masters',
		'nation' => '',
		'grps'	 => $icc_adults + $icc_youth,
		'wettk_reg' => '^[0-9]{2,2}_[^PWERASL]{1}.*',
//		'serie_reg' => '^[0-9]{2,2}_(WC|TR){1,1}.*',
//		'rang_title'=> 'CUWR continuously updated WORLDRANKING',
		'bgcolor'   => '#F0F0F0',
	),
	'para' => array(
		'label'  => 'Paraclimbing',
		'nation' => '',
		'grps'	 => $icc_para,
		'wettk_reg' => '^[0-9]{2,2}_PE.*',
		'bgcolor'   => '#F0F0F0',
	),
);

do_header ($t_calendar,'','',0,'',2);

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
	font-size: 21px;
	font-weight: bold;
}
-->
</style>
';

$anz_year = 0;
include ('wettk_list_multi.inc.php');

do_footer ();

?>
