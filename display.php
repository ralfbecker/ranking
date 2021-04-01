#!/usr/bin/php -qC -d mbstring.func_overload=7
<?php
/**
 * eGroupWare digital ROCK Rankings - display "demon"
 *
 * @link http://www.egroupware.org
 * @package admin
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2007-11 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

function die_usage()
{
	$cmd = basename($_SERVER['argv'][0]);
	die("Usage: $cmd account[@domain] password [display#]\n");
}

ini_set('display_errors',true);
// utf8 support
if (!extension_loaded('mbstring')) dl('mbstring.so');
if (ini_get('mbstring.func_overload') != 7) echo "mbstring.func_overload=7 required!!!\n\n";

//$_SERVER['argc']=3; $_SERVER['argv']=array('display.php','ralf','ralbec32');
if (php_sapi_name() !== 'cli')	// security precaution: forbit calling display demon as web-page
{
	die('<h1>display.php must NOT be called as web-page --> exiting !!!</h1>');
}
elseif ($_SERVER['argc'] < 3)
{
	die_usage();
}
// this is kind of a hack, as the autocreate_session_callback can not change the type of the loaded account-class
// so we need to make sure the right one is loaded by setting the domain before the header gets included.
@list(,$_GET['domain']) = explode('@',$_SERVER['argv'][1]);

if (!is_writable(ini_get('session.save_path')) && is_dir('/tmp')) {
	ini_set('session.save_path','/tmp');	// regular users may have no rights to apache's session dir
	ini_set('session.save_handler','files');
}

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

include_once(EGW_INCLUDE_ROOT.'/ranking/inc/class.ranking_display_bo.inc.php');

if(!defined('DR_DISPLAY_POLL_FREQUENCY')) define('DR_DISPLAY_POLL_FREQUENCY',0.5);	// poll db every x sec for changed formats

$bo = new ranking_display_bo();

$dsp_id = $_SERVER['argv'][3] ? $_SERVER['argv'][3] : 1;

/* PHP has only 32bit integer!
$mtime = microtime(true);
$mtime_rounded = round($mtime,1);
$mtime10 = trunc(10.0*$mtime);
echo "mtime=$mtime, rounded=$mtime_rounded, 10*mtime=$mtime10\n";
*/

if (!$bo->display->read($dsp_id))
{
	echo lang('Display #%1 not found!!!',$dsp_id)."\n\n";
	die_usage();
}
// delete the athlete data, as they would continously grow otherwise
$bo->display->update(array(
	'dsp_athletes' => null,
	'dsp_id'      => $dsp_id,
));

// some test output for special chars          012345678901234567 (geht bis 13)
//$test = $GLOBALS['egw']->translation->convert('ÄÖÜßäöüéèÉçáëóÁÈćŠ','utf-8');
//$bo->display->update(array('dsp_current' => $GLOBALS['egw']->translation->convert($test,'utf-8'),'dsp_timeout'=>microtime(true)+10));
//$bo->display->output(); sleep(10);	// this source is utf-8

// Mac OS X seems to lack time_sleep_until ...
if (!function_exists('time_sleep_until')) {
   function time_sleep_until($future) {
       $sleep = ($future - microtime(1))*1000000;
       if ($sleep<=0) {
           trigger_error("Time in past", E_USER_WARNING);
           return false;
       }

       usleep($sleep);
       return true;
   }
}

$time = microtime(true);
$next_line = false;
while(true)
{
	$timeout = 1;
	$line = 0;
	if (!$bo->display->frm_id)
	{
		$show = $bo->display->dsp_current;
		$bo->format->init();
	}
	elseif(!$bo->format->read($bo->display->frm_id))
	{
		$show = lang('Format #%1 not found!',$bo->display->frm_id);
		$bo->format->init();
	}
	else
	{
		$line = $bo->display->dsp_line;		// it seems php can NOT set as __set() class var via a var param!
		$athlete = $bo->display->dsp_athletes[$bo->format->GrpId][$bo->format->route_order];
		if (!$bo->format->GrpId && $bo->display->dsp_athletes['current']['GrpId'])
		{
			$GrpId = $bo->display->dsp_athletes['current']['GrpId'];
			$route_order = $bo->display->dsp_athletes['current']['route_order'];
		}
		else
		{
			$GrpId = $bo->format->GrpId;
			$route_order = $bo->format->route_order;
		}
		$show = $bo->format->get_content($timeout,$line,$next_line,$athlete,$GrpId,$route_order,
			$bo->display->dsp_cols,$bo->display->dsp_rows);
	}
	$sleep_until = $time + $timeout;

	$bo->display->update($up = array(
		'dsp_current' => $show,
		'dsp_timeout' => $sleep_until,
		'frm_id'      => $bo->format->frm_id,
		'dsp_line'    => $line,
		'dsp_id'      => $dsp_id,
	));
	$etag = $bo->display->dsp_etag + 1;

	// output content to display
	$bo->display->output($show);

	// debug output
	printf("\r%d(%d)-%d: %-20s %.1lf (%ds) --> %d  ",$bo->format->frm_line,$bo->format->frm_id,$bo->display->dsp_line,$show,$sleep_until,$timeout,$bo->format->frm_go_frm_id);

	// wait til our timeout or someone changes the current active format
	$next_line = true;
	while (($time = microtime(true)) < $sleep_until)
	{
		time_sleep_until(min($sleep_until,$time+DR_DISPLAY_POLL_FREQUENCY));	// sleep max. poll-freq. sec before we check again

		if (!$bo->display->read($dsp_id))
		{
			echo lang('Display #%1 not found!!!',$dsp_id)."\n\n";
			die_usage();
		}
		if ($etag != $bo->display->dsp_etag || $bo->display->frm_id != $bo->format->frm_id ||	// someone externally changed our current format or athlete --> update display
			$athlete != $bo->display->dsp_athletes[$bo->format->GrpId][$bo->format->route_order] /*||
			$line != $bo->display->dsp_line ||
			$sleep_until != $bo->display->sleep_until*/)
		{
			$next_line = false;
			break;
		}
	}
}
