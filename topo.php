<?php
/**
 * EGroupware digital ROCK Rankings: generate topo graphic
 *
 * Usage: http://www.digitalrock.de/egroupware/topo.php?comp=yyy&cat=zzz[&route=xxx]
 *
 * @param comp competition number
 * @param cat  category number or rkey
 * @param route -1 = general result (default)
 *     0  = qualification
 *     1  = 2. qualification (if applicable)
 *     2  = further heats
 *
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2011 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$
 */

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'currentapp'	=> 'ranking',
		'nonavbar'		=> True,
		'noheader'      => True,
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
	$anon_account = null;

	// create session without checking auth: create(..., false, false)
	return $GLOBALS['egw']->session->create('anonymous@'.$GLOBALS['egw_info']['user']['domain'],
		'', 'text', false, false);
}

// some defaults
$place = (int)$_GET['place'] > 0 ? (int)$_GET['place'] : 1;
$num = (int)$_GET['num'] > 0 ? (int)$_GET['num'] : 8;
$width = (int)$_GET['width'] > 0 ? (int)$_GET['width'] : 1024;
$height = (int)$_GET['height'] > 0 ? (int)$_GET['height'] : null;	// null = 4/3 ratio
$topo = isset($_GET['topo']) ? (int)$_GET['topo'] : 0;
$margin = is_numeric($_GET['margin']) ? (int)$_GET['margin'] : 50;
$src = (int)$_GET['src'];
$icon = $_GET['icon'] ? basename($_GET['icon']) : 'griff32';
$png = isset($_GET['png']) ? (boolean) $png : $src == 3;
ranking_topo::$fontsize = (int)$_GET['fontsize'] > 0 ? (int)$_GET['fontsize'] : 18;
ranking_topo::$fontfile = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';

try
{
	ranking_topo::render($_GET['comp'],$_GET['cat'],$_GET['route'],$place,$num,$width,$height,$margin,$icon,$src,$topo,$png);
}
// exceptin handler
catch(Exception $e)
{
	header("HTTP/1.1 404 Not Found");
	echo "<html>\n<head>\n\t<title>Error ".$e->getMessage()."</title>\n</head>\n";
	echo "<body>\n\t<h1>".$e->getMessage()."</h1>\n";
	echo "<p>The requested ressource was not found on this server.<br>\n<br>\n";
	echo 'URI: ' . $_SERVER['REQUEST_URI'] . "</p>\n";
	echo "<pre>".$e->getTraceAsString()."</pre>\n";
	echo "</body></html>\n";
	exit;
}
