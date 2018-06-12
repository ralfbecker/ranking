<?php

// DAV spezifisch
$results_since  = 1990;

$t_footer_inc	= "dav_footer";
$t_header_logo	= "<TD VALIGN=BOTTOM><A HREF=\"http://www.alpenverein.de\" target=_blank><IMG SRC=\"dav.gif\" ALT=\"hier geht's zum Deutschen Alpenverein\" BORDER=0 HEIGHT=126 ></A></TD>";
$t_cuwr		= "Deutsche  R&nbsp;A&nbsp;N&nbsp;G&nbsp;L&nbsp;I&nbsp;S&nbsp;T&nbsp;E";
$t_head_cuwr	= "Dt. Rangliste";
$t_verband_del  = "^(DAV|Sektion|Deutscher|Dt|Alpenverein|[:., ])*";
$t_sektion	= "DAV Sektion";
$t_qualified_for = 'Die %s besten Wettk&auml;mpfer qualifizieren sich f&uuml;r die %s am %s.';
$t_nocuwr = 'nicht in Rangliste';

$ger_youth = array(
	'GER_F_X' => 'weibl. Jugend',
	'GER_F_J' => 'Junorinnen',
	'GER_F_A' => 'weibl. Jugend A',
	'GER_F_AB' => 'weibl. Jugend AB',
	'GER_F_AJ' => 'weibl. Jugend A + Juniorinnen',
	'GER_F_B' => 'weibl. Jugend B',
	'GER_F_BC' => 'weibl. Jugend BC', // nur LVs
	'GER_F_B2' => 'weibl. Jugend B (nur 2 Jahrg&auml;nge)', // nur LVs
	'GER_F_C' => 'weibl. Jugend C',
	'GER_F_CD' => 'weibl. Jugend CD',
	'GER_F_D' => 'weibl. Jugend D',
	'GER_F_E' => 'weibl. Jugend E',
	'GER_F_F' => 'weibl. Jugend F',
	'GER_M_J' => 'Junioren',
	'GER_M_AJ' => 'm&auml;nnl. Jugend A + Junioren',
	'GER_M_A' => 'm&auml;nnl. Jugend A',
	'GER_M_AB' => 'm&auml;nnl. Jugend AB',
	'GER_M_B' => 'm&auml;nnl. Jugend B',
	'GER_M_BC' => 'm&auml;nnl. Jugend BC',	// nur LVs
	'GER_M_B2' => 'm&auml;nnl. Jugend B (nur 2 Jahrg&auml;nge',	// nur LVs
	'GER_M_C' => 'm&auml;nnl. Jugend C',
	'GER_M_CD' => 'm&auml;nnl. Jugend CD',
	'GER_M_D' => 'm&auml;nnl. Jugend D',
	'GER_M_E' => 'm&auml;nnl. Jugend E',
	'GER_M_F' => 'm&auml;nnl. Jugend F',
);
$ger_adult = array(
	'GER_F' => 'Damen',
	'GER_M' => 'Herren',
	'GER_FX' => 'Damen Combined',
	'GER_MX' => 'Herren Combined',
);
$ger_junior = array(
	'GER_F_J' => 'Junorinnen',
	'GER_M_J' => 'Junioren',
);

$ger_bouldern = array(
	'GER_FB' => 'Damen',
	'GER_MB' => 'Herren'
);

$ger_speed = array(
	'GER_FS' => 'Damen',
	'GER_MS' => 'Herren',
	'GER_M_J' => 'Junioren',
	'GER_F_J' => 'Junorinnen'
);

$ger_ak = array(
	'GER_X40' => '&Uuml; 40',
	'GER_F40' => 'Damen &Uuml; 40',
 	'GER_FA1' => 'Damen AK I',
	'GER_M40' => 'Herren &Uuml; 40',
	'GER_MA1' => 'Herren AK I',
	'GER_MA2' => 'Herren AK II',
);

$mgroups = array(
	'GER_X_X' => array(
	 	48 => 'GER_F_A',
	 	49 => 'GER_F_B',
	 	50 => 'GER_F_J',
	 	11 => 'GER_M_A',
	 	12 => 'GER_M_B',
	 	13 => 'GER_M_J',
		14 => 'GER_F_X',
	),
	'GER_XX' => array(
		7  => 'GER_F',
		4  => 'GER_M',
		28 => 'GER_FB',
		27 => 'GER_MB',
	),
	'GER_X' => array(
		7  => 'GER_F',
		4  => 'GER_M',
	),
	'GER_XB' => array(
		28 => 'GER_FB',
		27 => 'GER_MB',
	),
);
