<?php
/**
 * EGroupware digital ROCK Rankings - setup
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2006-17 by Ralf Becker <RalfBecker@digitalrock.de>
 */

// RB: never install by default, only via setup
$setup_info['ranking']['only_db'] = array('never');

$setup_info['ranking']['name']      = 'ranking';
$setup_info['ranking']['version']   = '21.1.004';
$setup_info['ranking']['app_order'] = 1;
$setup_info['ranking']['tables']    = array('Wettkaempfe','Serien','Gruppen','RangListenSysteme','PktSysteme','Results','Feldfaktoren','PktSystemPkte','Gruppen2Personen','Personen','Routes','RouteResults','RelayResults','Displays','DisplayFormats','Federations','Athlete2Fed','Licenses','RouteHolds','Registration');
$setup_info['ranking']['only_db']   = array('none');	// do NOT install by default, was array('mysql','postgres');
$setup_info['ranking']['enable']    = 1;

$setup_info['ranking']['author'] =
$setup_info['ranking']['maintainer'] = array(
	'name'  => 'Ralf Becker',
	'email' => 'RalfBecker@digitalROCK.de',
);
$setup_info['ranking']['license']  = 'GPL';

$setup_info['ranking']['description'] =
	"<p>Calendar-, result- and ranking-service on this site is provided by digital ROCK.</p>
	<ul>
    	<li>Climbing officials can manage the competition calendar and other website content from everywhere all over the world.</li>
    	<li>Athletes can register for national licenses, to be confirmed by their federation (Sektion) and state-federation (LV)</li>
    	<li>National federations can online register athletes for competitions and update their personal profiles.</li>
    	<li>Judges can draw starting-lists from the registered athletes, enter results &amp; print result-lists.</li>
    	<li>Rankings and cup-results are generated on the fly by the system and linked to the athlete's profiles.</li>
    </ul>";

$setup_info['ranking']['note'] =
	"<p>If you want more information about the system, please contact us:\n".
	'<table><tr><td><a href="https://www.digitalrock.de" target="_blank"><img src="ranking/templates/default/images/navbar.png" /></a>&nbsp;&nbsp;</td>
	<td><p><b>digital ROCK</b> a business unit of <a href="https://www.egroupware.org" target="_blank">EGroupware GmbH</a><br />
	<a href="https://www.digitalrock.de" target="_blank">www.digitalROCK.de</a><br />
	<a href="mailto:RalfBecker at digitalROCK.de"><span onClick="document.location=\'mailto:RalfBecker\'+\'@\'+\'digitalROCK.de\'; return false;">Ralf Becker</span></a></p></td></tr></table></p>';

/* The hooks this app includes, needed for hooks registration */
$setup_info['ranking']['hooks']['preferences']  = 'ranking_hooks::all_hooks';
$setup_info['ranking']['hooks']['settings']     = 'ranking_hooks::hook_settings';
$setup_info['ranking']['hooks']['admin']        = 'ranking_hooks::all_hooks';
$setup_info['ranking']['hooks']['sidebox_menu'] = 'ranking_hooks::all_hooks';
$setup_info['ranking']['hooks']['edit_user']    = 'ranking.admin.edit_user';
$setup_info['ranking']['hooks']['search_link']  = 'ranking_hooks::search_link';
$setup_info['ranking']['hooks'][] = 'config';
$setup_info['ranking']['hooks'][] = 'config_validate';
$setup_info['ranking']['hooks']['backup_starts']  = 'ranking_hooks::backup_starts';

// calendar integration
$setup_info['ranking']['hooks']['calendar_search_union']  = 'ranking_calendar_integration::calendar_search_union';

/* Dependacies for this app to work */
$setup_info['ranking']['depends'][] = array(
	 'appname' => 'api',
	 'versions' => Array('20.1')
);