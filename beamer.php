<?php
/**
 * EGroupware digital ROCK Rankings: beamer / videowall support
 *
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2011-17 by Ralf Becker <RalfBecker@digitalrock.de>
 */

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'currentapp'	=> 'ranking',
		'nonavbar'		=> True,
		'noheader'      => True,
		'autocreate_session_callback' => 'check_anon_access',
));
$_GET['cd'] = 'no';	// stop jdots framework
include('../header.inc.php');

/**
 * Create anonymous session without checking credentials
 *
 * @param array &$anon_account anon account_info with keys 'login', 'passwd' and optional 'passwd_type'
 * @return boolean|string string with sessionid or false on error (no anonymous user)
 */
function check_anon_access(&$anon_account)
{
	$anon_account = null;

	// create session without checking auth: create(..., false, false)
	return $GLOBALS['egw']->session->create('anonymous@'.$GLOBALS['egw_info']['user']['domain'],
		'', 'text', false, false);
}

ranking_beamer::beamer();