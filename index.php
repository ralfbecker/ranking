<?php
/**
 * EGroupware digital ROCK Rankings
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2006-19 by Ralf Becker <RalfBecker@digitalrock.de>
 */

use EGroupware\Api;

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'currentapp'	=> 'ranking',
		'noheader'		=> True,
		'nonavbar'		=> True
));
include('../header.inc.php');

if (!($view = Api\Cache::getSession('ranking', 'menuaction')) &&
	!($view = $GLOBALS['egw_info']['user']['preferences']['ranking']['default_view']))
{
	$view = 'ranking.uiranking.index';
}
// fix old class-names stored in user prefs
if (substr($view, 0, 10) === 'ranking.ui' && $view !== 'ranking.uiranking.index')
{
	list($app, $class, $method) = explode('.', $view);
	$view = 'ranking.ranking_'.substr($class, 2, substr($class, -1) === 's' ? -1 : 99).'_ui.'.$method;
}

// urls which still need to run in iframe (NOT top-level)
if (in_array($view, array(
	'ranking.uiranking.index',
	'ranking.ranking_accounting.index',
)))
{
	ExecMethod($view);
	echo $GLOBALS['egw']->framework->footer();
	exit;
}

// redirect to currently active view incl. ajax=true
Api\Egw::redirect_link('/index.php', array(
	'menuaction' => $view,
	'ajax' => 'true',
), 'ranking');
