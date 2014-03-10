<?php

/* $Id$ */

$extra_header[2015] = '<font color=red>provisional</font>';
$extra_header[2016] = '<font color=red>provisional</font>';

// IFSC specific
$results_since  = 1991;

global $t_header_logo;	// make it availible in sitemgr too
$t_footer_inc	= "icc_footer";
$t_header_logo	= '<td width="150" class="onlyPrint" align="center"><a href="' . $url_icc_info .
	'" target="_blank"><img src="ifsc-100.gif" border="0"></a><br /><font size="-1">International Federation<br />of Sport Climbing</font></td>';
$t_cuwr		= "<b>Worldranking</b>";
$t_head_cuwr	= "WR";
$t_nocuwr = 'no WR';

global $european_nations;
$european_nations = array(
	'ALB','AND','ARM','AUT','AZE','BLR','BEL','BIH','BUL',
	'CRO','CYP','CZE','DEN','EST','ESP','FIN','FRA','GBR',
	'GEO','GER','GRE','HUN','IRL','ISL','ISR','ITA','LAT',
	'LIE','LTU','LUX','MDA','MKD','MLT','MON','NED','NOR',
	'POL','POR','ROU','RUS','SRB','SLO','SMR','SUI','SVK',
	'SWE','TUR','UKR'
);

$mgroups = array(
	'ICC_MX' => array(
		1 => 'ICC_M',
		6 => 'ICC_MB',
		23 => 'ICC_MS',
	),
	 'ICC_FX' => array(
	 	2 => 'ICC_F',
		5 => 'ICC_FB',
		24 => 'ICC_FS',
	),
/* nicht benutzt
	'ICC_XX' => array(
	 	2 => 'ICC_F',
		1 => 'ICC_M',
		5 => 'ICC_FB',
		6 => 'ICC_MB',
		24 => 'ICC_FS',
		23 => 'ICC_MS',
	),
*/
);

//$mgroup_pktsys = 'EYC';

$icc_adults = array(
	'ICC_M'  => 'MEN lead',
	'ICC_F'  => 'WOMEN lead',
	'ICC_MB' => 'MEN boulder',
	'ICC_FB' => 'WOMEN boulder',
	'ICC_MS' => 'MEN speed',
	'ICC_FS' => 'WOMEN speed',
	'ICC_TR' => 'Team relay speed',
);
$icc_combined = array(
	'ICC_MX' => 'MEN overall',
	'ICC_FX' => 'WOMEN overall',
);
$icc_youth = array(
	'ICC_F_J' => 'female juniors',
	'ICC_M_J' => 'male juniors',
	'ICC_F_A' => 'female youth A',
	'ICC_M_A' => 'male youth A',
	'ICC_F_B' => 'female youth B',
	'ICC_M_B' => 'male youth B',
	'ICC_FBJ' => 'female juniors boulder',
	'ICC_MBJ' => 'male juniors boulder',
	'ICC_FBA' => 'female youth A boulder',
	'ICC_MBA' => 'male youth A boulder',
	'ICC_FBB' => 'female youth B boulder',
	'ICC_MBB' => 'male youth B boulder',
	'ICC_FSJ' => 'female juniors speed',
	'ICC_MSJ' => 'male juniors speed',
	'ICC_FSA' => 'female youth A speed',
	'ICC_MSA' => 'male youth A speed',
	'ICC_FSB' => 'female youth B speed',
	'ICC_MSB' => 'male youth B speed',
);

$icc_para = array(
	'ICC_F_AA' => 'WOMEN amputee arm PD',
	'ICC_F_AL' => 'WOMEN amputee leg PD',
	'ICC_F_AN' => 'WOMEN arthritis+neurological PD',
	'ICC_F_B1' => 'WOMEN visual impairment B1',
	'ICC_F_B2' => 'WOMEN visual impairment B2',
	'ICC_F_B3' => 'WOMEN visual impairment B3',
	'ICC_M_AA' => 'MEN amputee arm PD',
	'ICC_M_AL' => 'MEN amputee leg PD',
	'ICC_M_AB' => 'MEN amputee both leg PD',
	'ICC_M_AD' => 'MEN complete neurolog. leg disablity PD',
	'ICC_M_AN' => 'MEN arthritis+neurological PD 1',
	'ICC_M_A2' => 'MEN arthritis+neurological PD 2',
	'ICC_M_B1' => 'MEN visual impairment B1',
	'ICC_M_B2' => 'MEN visual impairment B2',
	'ICC_M_B3' => 'MEN visual impairment B3',
);
