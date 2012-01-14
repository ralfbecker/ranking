<?php
/**
 * EGroupware digital ROCK Rankings
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2006-12 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$
 */

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'currentapp'	=> 'ranking',
		'noheader'		=> True,
		'nonavbar'		=> True
));
include('../header.inc.php');

if (!($view = $GLOBALS['egw']->session->appsession('menuaction','ranking')) &&
	!($view = $GLOBALS['egw_info']['user']['preferences']['ranking']['default_view']))
{
	$view = 'ranking.uiranking.index';
}
// fix old class-names stored in user prefs
$old2new = array(
	'uicompetitions' => 'ranking_competition_ui',
	'uicups' => 'ranking_cup_ui',
	'uiathlets' => 'ranking_athlete_ui',
);
if (isset($old2new[$view])) $view = $old2new[$view];

ExecMethod($view);

common::egw_footer();
