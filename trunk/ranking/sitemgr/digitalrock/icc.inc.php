<?php

/* $Id$ */

$extra_header[2010] = '<font color=red>provisional</font>';
$extra_header[2011] = '<font color=red>provisional</font>';

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
	'ICC_M'  => 'MEN<BR>lead',
	'ICC_F'  => 'WOMEN<BR>lead',
	'ICC_MB' => 'MEN<BR>boulder',
	'ICC_FB' => 'WOMEN<BR>boulder',
	'ICC_MS' => 'MEN<BR>speed',
	'ICC_FS' => 'WOMEN<BR>speed'
);
$icc_combined = array(
	'ICC_MX' => 'MEN overall',
	'ICC_FX' => 'WOMEN overall',
);
$icc_youth = array(
	'ICC_F_J' => 'female<BR>juniors',
	'ICC_M_J' => 'male<BR>juniors',
	'ICC_F_A' => 'female<BR>youth A',
	'ICC_M_A' => 'male<BR>youth A',
	'ICC_F_B' => 'female<BR>youth B',
	'ICC_M_B' => 'male<BR>youth B',
	'ICC_FSJ' => 'female<BR>juniors speed',
	'ICC_MSJ' => 'male<BR>juniors speed',
	'ICC_FSA' => 'female<BR>youth A speed',
	'ICC_MSA' => 'male<BR>youth A speed',
	'ICC_FSB' => 'female<BR>youth B speed',
	'ICC_MSB' => 'male<BR>youth B speed',
);

?>
