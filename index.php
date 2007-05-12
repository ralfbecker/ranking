<?php
/**
 * eGroupWare digital ROCK Rankings
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2006/7 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$ 
 */

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'currentapp'	=> 'ranking',
		'noheader'		=> True,
		'nonavbar'		=> True
));
include('../header.inc.php');

if (!($view = $GLOBALS['egw']->session->appsession('menuaction','ranking')))
{
	$view = $GLOBALS['egw_info']['user']['preferences']['ranking']['default_view'];
}
ExecMethod($view ? $view : 'ranking.uiranking.index');

$GLOBALS['egw']->common->egw_footer();
