<?php
/**
 * EGroupware digital ROCK Rankings webservice access: json
 *
 * Usage: http://www.digitalrock.de/egroupware/json.php?comp=yyy&cat=zzz[&route=xxx][&debug=1]
 *
 * @param comp competition number
 * @param cat  category number or rkey
 * @param route -1 = general result (default)
 *     0  = qualification
 *     1  = 2. qualification (if applicable)
 *     2  = further heats
 * @param debug 1: content-type: text/html
 *               2: additionally original route array
 *
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2010 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$
 */

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'currentapp'	=> 'ranking',
		'nonavbar'		=> True,
		'noheader'      => True,
		'autocreate_session_callback' => 'check_anon_access',
		'nocachecontrol'=> 'public',
));
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

$result = ranking_export::export();
$encoding = translation::charset();

if (!isset($_GET['debug']) || !$_GET['debug'])
{
	if ($_GET['callback'])	// JSONP uses application/javascript as content-type
	{
		header('Content-Type: application/javascript; charset='.$encoding);
	}
	else
	{
		header('Content-Type: application/json; charset='.$encoding);
	}
	egw_session::cache_control(isset($result['expires']) ? $result['expires'] : ranking_export::EXPORT_DEFAULT_EXPIRES);
	if (isset($result['etag']))
	{
		if ($result['etag'][0] != '"') $result['etag'] = '"'.$result['etag'].'"';
		header('Etag: '.$result['etag']);
	}
	if (isset($_SERVER['HTTP_IF_MATCH']) && $_SERVER['HTTP_IF_MATCH'] === $result['etag'])
	{
		header('HTTP/1.1 304 Not Modified');
		common::egw_exit();
	}
}
else
{
	header('Content-Type: text/html; charset='.$encoding);
}

/**
 * Remove all empty (null or "") values from an array
 *
 * @param array $arr
 * @return array
 */
function remove_empty(array $arr)
{
	foreach($arr as $key => &$val)
	{
		if (is_array($val))
		{
			$val = remove_empty($val);
		}
		elseif ((string)$val === '')
		{
			unset($arr[$key]);
		}
	}
	return $arr;
}
$json = json_encode($result=remove_empty($result));

if (isset($_GET['debug']) && $_GET['debug'])
{
	switch($_GET['debug'])
	{
		case 2:
			echo "<pre>".print_r($result,true)."</pre>\n";
			// fall through
		default:
			echo "<pre>".htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT))."</pre>\n";
			break;
	}
}
// jsonp callback
elseif ($_GET['callback'])
{
	echo $_GET['callback'].'('.$json.");\n";
}
else
{
	echo $json;
}
