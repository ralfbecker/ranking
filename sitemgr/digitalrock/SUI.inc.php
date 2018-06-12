<?php

// SAC spezifisch

$results_since  = 1993;
$t_footer_inc	= "sac_footer";
$t_header_logo	= '<td width="10%">
<a href="http://www.sac-cas.ch" target=_blank><img src="sac110.gif" title="Schweizer Alpen-Club - Club Alpin Suisse" BORDER="0" /></a>
</td>';
$t_cuwr = "SWISS RANKING - permanente Rangliste";
$t_head_cuwr = "Swiss Ranking";
$t_nocuwr = 'nicht in Rangliste';

$sac_adults = array(
	'SUI_M'   => 'Herren',
	'SUI_F'   => 'Damen',
	'SUI_M_2' => 'Elite 2 Herren',
	'SUI_F_2' => 'Elite 2 Damen',
	'SUI_M_3' => 'Open Herren',
	'SUI_F_3' => 'Open Damen',
);

$sac_youth = array(
	'SUI_F_10'=> 'U10 Damen',
	'SUI_M_10'=> 'U10 Herren',
	'SUI_F_12'=> 'U12 Damen',
	'SUI_M_12'=> 'U12 Herren',
	'SUI_F_M' => 'U14 Damen',
	'SUI_M_M' => 'U14 Herren',
	'SUI_F_B' => 'U16 Damen',
	'SUI_M_B' => 'U16 Herren',
	'SUI_F_X' => 'Jugend Damen',
	'SUI_M_X' => 'Jugend Herren',
	'SUI_M_J' => 'Junioren (alt)',
	'SUI_F_J' => 'Juniorinnen (alt)',
);

// from 2011 on u18 category is (calendar-wise) with the adults
$calendar_year = isset($_REQUEST['year']) && (int)$_REQUEST['year'] >= $results_since ?
	(int)$_REQUEST['year'] : (int)date('Y')+(int)(date('m') >= 12);	// already show next year in December

$sac_u18 = array(
	'SUI_F_A' => 'U18 Damen',
	'SUI_M_A' => 'U18 Herren',
);
if ($calendar_year < 2011)
{
	$sac_youth += $sac_u18;
}
else
{
	$sac_adults += $sac_u18;
}
