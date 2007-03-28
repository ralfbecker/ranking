<?php
/**************************************************************************\
* phpGroupWare - Editable Templates                                        *
* http://www.phpgroupware.org                                              *
" Written by Ralf Becker <RalfBecker@outdoor-training.de>                  *
* --------------------------------------------                             *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

$setup_info['ranking']['name']      = 'ranking';
$setup_info['ranking']['version']   = '1.3.012';
$setup_info['ranking']['app_order'] = 1;
$setup_info['ranking']['tables']    = array('Wettkaempfe','Serien','Gruppen','RangListenSysteme','PktSysteme','Results','Feldfaktoren','PktSystemPkte','Gruppen2Personen','Personen','Routes','RouteResults');
$setup_info['ranking']['enable']    = 1;

$setup_info['ranking']['author'] = 
$setup_info['ranking']['maintainer'] = array(
	'name'  => 'Ralf Becker',
	'email' => 'RalfBecker@digitalROCK.de',
);
//$setup_info['ranking']['license']  = 'GPL';

$setup_info['ranking']['description'] =
	"<p>Calendar-, result- and ranking-service on this site is provided by digital ROCK.</p>
	<ul>
    	<li>UIAA Climbing officials can manage the competition calendar and other website content from everywhere all over the world.</li>
    	<li>National federations can online register athlets for competitions and update their personal profiles.</li>
    	<li>Judges can draw starting-lists from the registered athlets, enter results &amp; print result-lists.</li>
    	<li>Rankings and cup-results are generated on the fly by the system and linked to the athlet's profiles.</li>
    </ul>";

$setup_info['ranking']['note'] =
	"<p>If you want more information about the system, please contact us:\n".
	'<table><tr><td><a href="http://www.digitalrock.de" target="_blank"><img src="ranking/templates/default/images/navbar.png" /></a>&nbsp;&nbsp;</td>
	<td><p><b>digital ROCK</b> - Becker & Macht GbR<br />
	<a href="http://www.digitalrock.de" target="_blank">www.digitalROCK.de</a><br />
	<a href="mailto:RalfBecker at digitalROCK.de"><span onClick="document.location=\'mailto:RalfBecker\'+\'@\'+\'digitalROCK.de\'; return false;">Ralf Becker</span></a></p></td></tr></table></p>';

/* The hooks this app includes, needed for hooks registration */
$setup_info['ranking']['hooks']['preferences']  = 'ranking.ranking_admin_prefs_sidebox_hooks.all_hooks';
$setup_info['ranking']['hooks']['settings']     = 'ranking.ranking_admin_prefs_sidebox_hooks.hook_settings';
$setup_info['ranking']['hooks']['admin']        = 'ranking.ranking_admin_prefs_sidebox_hooks.all_hooks';
$setup_info['ranking']['hooks']['sidebox_menu'] = 'ranking.ranking_admin_prefs_sidebox_hooks.all_hooks';
$setup_info['ranking']['hooks']['edit_user']    = 'ranking.admin.edit_user';
$setup_info['ranking']['hooks'][] = 'config';
$setup_info['ranking']['hooks'][] = 'config_validate';

/* Dependacies for this app to work */
$setup_info['ranking']['depends'][] = array(
	 'appname' => 'phpgwapi',
	 'versions' => Array('1.2','1.3','1.4')
);
$setup_info['ranking']['depends'][] = array(
	'appname' => 'etemplate',
	'versions' => Array('1.2','1.3','1.4')
);



















