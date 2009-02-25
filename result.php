<?php
/**
 * eGroupWare digital ROCK Rankings
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2006-9 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$
 */

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'currentapp'	=> 'sitemgr-link',	// anonymous should have NO ranking access
		'nonavbar'		=> True,
		'autocreate_session_callback' => 'check_anon_access',
));
include('../header.inc.php');

/**
 * Check if we allow anon access and with which creditials
 *
 * @param array &$anon_account anon account_info with keys 'login', 'passwd' and optional 'passwd_type'
 * @return boolean true if we allow anon access, false otherwise
 */
function check_anon_access(&$anon_account)
{
	$anon_account = array(
		'login'  => 'anonymous',
		'passwd' => 'anonymous',
		'passwd_type' => 'text',
	);
	return true;
}

// add ranking css file to improve printing
?>
<style type="text/css">
<!--
@import url(/egroupware/etemplate/templates/default/app.css);
@import url(/egroupware/ranking/templates/default/app.css);
-->
</style>
<?php

// allow to switch cat and route
if (isset($_POST['exec']['nm']))
{
	$_GET = array_merge($_GET,$_POST['exec']['nm']);
}
$GLOBALS['Common_BO'] = new stdClass;
$content = ExecMethod('ranking.uiresult.index');
// rewrite id of result, to be able to use it in css
$content = preg_replace('/id="ranking.result.index.rows[^"]+"/','id="result"',$content);
// remove (not working) download button
$content = preg_replace('/<a.*id="exec\[button\]\[download\]".*<\/a>/','',$content);

echo $content;

echo "</body>\n</html>\n";
$GLOBALS['egw']->common->egw_exit();
