#!/usr/bin/php -qC
<?php
/**
 * eGroupWare digital ROCK Rankings - bridge to old ROCK programs
 *
 * @link http://www.egroupware.org
 * @package admin
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2010 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

function die_usage()
{
	$cmd = basename($_SERVER['argv'][0]);
	die("Usage: $cmd account[@domain] password WetId rock_route heat\n");
}

ini_set('display_errors',false);
// utf8 support
if (!extension_loaded('mbstring')) dl('mbstring.so');
if (ini_get('mbstring.func_overload') != 7) echo "mbstring.func_overload=7 required!!!\n\n";

//$_SERVER['argc']=3; $_SERVER['argv']=array('display.php','ralf','ralbec32');
if (isset($_SERVER['HTTP_HOST']))	// security precaution: forbit calling display demon as web-page
{
	die('<h1>bridge.php must NOT be called as web-page --> exiting !!!</h1>');
}
elseif ($_SERVER['argc'] < 6)
{
	die_usage();
}
// this is kind of a hack, as the autocreate_session_callback can not change the type of the loaded account-class
// so we need to make sure the right one is loaded by setting the domain before the header gets included.
@list(,$_GET['domain']) = explode('@',$_SERVER['argv'][1]);

if (!is_writable(ini_get('session.save_path')) && is_dir('/tmp')) ini_set('session.save_path','/tmp');	// regular users may have no rights to apache's session dir

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'currentapp' => 'ranking',
		'noheader' => true,
		'autocreate_session_callback' => 'user_pass_from_argv',
	)
);

include(dirname(dirname(__FILE__)).'/header.inc.php');
ob_end_flush();

/**
 * callback if the session-check fails, redirects via xajax to login.php
 * 
 * @param array &$account account_info with keys 'login', 'passwd' and optional 'passwd_type'
 * @return boolean/string true if we allow the access and account is set, a sessionid or false otherwise
 */
function user_pass_from_argv(&$account)
{
	$account = array(
		'login'  => $_SERVER['argv'][1],
		'passwd' => $_SERVER['argv'][2],
		'passwd_type' => 'text',
	);
	//print_r($account);
	if (!($sessionid = $GLOBALS['egw']->session->create($account)))
	{
		echo "Wrong account or -password !!!\n\n";
		die_usage();
	}
	if (!$GLOBALS['egw_info']['user']['apps']['ranking'])	// will be tested by the header too, but whould give html error-message
	{
		echo "Permission denied !!!\n\n";
		die_usage();
	}
	return $sessionid;
}

require_once(EGW_INCLUDE_ROOT.'/ranking/inc/class.boresult.inc.php');
$boresult = new boresult();

if (!($comp = $boresult->comp->read($_SERVER['argv'][3])))
{
	die("Compeitition {$_SERVER['argv'][3]} NOT found!");
}
$rock_route = $_SERVER['argv'][4];

// todo: detect heat from $rock_route
$heat = (int)$_SERVER['argv'][5];

$base_path = '/var/www/html';
list(,$wettk) = explode('.',$rock_route);
$year = 2000 + substr($wettk,0,2);
$file = $base_path.'/'.$year.'/'.$wettk.'/'.$rock_route.'.php';

if (!file_exists($file))
{
	die("Rock export file $file NOT found!\n\n");
}
$last_mtime = null;
while(true)
{
	clearstatcache();
	$mtime = @filemtime($file);

	if (file_exists($file) && (is_null($last_mtime) || $last_mtime != $mtime))
	{
		$imported = $boresult->import_rock($file,$comp,$heat);
		echo date('Y-m-d H:i:s ');
		if (is_int($imported))
		{
			echo "$imported participants imported.\n";
			$last_mtime = $mtime;
		}
		else
		{
			echo "Error: $imported\n";
		}
	}
	usleep(100000);	// sleep for a tenth sec
}
