#!/usr/bin/php -C
<?php
/**
 * Ranking - Import - Command line interface
 *
 * @link http://www.digitalrock.de
 * @link http://www.egroupware.org
 * @package ranking
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2008-12 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

if (php_sapi_name() !== 'cli')	// security precaution: forbit calling admin-cli as web-page
{
	die('<h1>import-cli.php must NOT be called as web-page --> exiting !!!</h1>');
}
$arguments = $_SERVER['argv'];
array_shift($arguments);	// remove cmd
// this is kind of a hack, as the autocreate_session_callback can not change the type of the loaded account-class
// so we need to make sure the right one is loaded by setting the domain before the header gets included.
@list(,$_REQUEST['domain']) = explode('@',array_shift($arguments));	// remove user from args
array_shift($arguments);	// remove pw from args

if (ini_get('session.save_handler') == 'files' && !is_writable(ini_get('session.save_path')) && is_dir('/tmp') && is_writable('/tmp'))
{
	ini_set('session.save_path','/tmp');	// regular users may have no rights to apache's session dir
}

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'currentapp' => 'ranking',
		'noheader' => true,
		'autocreate_session_callback' => 'user_pass_from_argv',
	)
);

include(dirname(__FILE__).'/../header.inc.php');

if ($_SERVER['argc'] <= 2)
{
	usage();
}

/**
 * Exit the script with a numeric exit code and an error-message, does NOT return
 *
 * @param int $exit_code
 * @param string $message
 */
// set our own exception handler, to not get the html from eGW's default one
function cli_exception_handler(Exception $e)
{
	echo $e->getMessage()."\n\n";
	if ($GLOBALS['debug']) echo $e->getTraceAsString()."\n\n";
	exit($e->getCode());
}
set_exception_handler('cli_exception_handler');

/**
 * callback to authenticate with the user/pw specified on the commandline
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
		//fail(1,lang("Wrong admin-account or -password !!!"));
		echo lang("Wrong admin-account or -password !!!")."\n\n";
		usage(1);
	}
	if (!$GLOBALS['egw_info']['user']['apps']['admin'])	// will be tested by the header too, but whould give html error-message
	{
		//fail(2,lang("Permission denied !!!"));
		echo lang("Permission denied !!!")."\n\n";
		usage(2);
	}
	return $sessionid;
}

/**
 * Give a usage message and exit
 *
 * @param string $action=null
 * @param int $ret=0 exit-code
 */
function usage($ret=0)
{
	$cmd = basename($_SERVER['argv'][0]);
	echo "Usage: $cmd username password [--add-athletes] [--charset utf-8] [--cat id/rkey] [--baseurl url] [--debug N] [--download] [--route-type (0-6) [--set-status (0-2)] [--import-ranking] competition\n\n";

	echo "Imports the results of a competition from an other eGroupWare site.\n\n";

	echo "--add-athletes automatic add non existing athletes\n";
	echo "--cat id or rkey of a category to import, default import all categories of a competition\n";
	echo "--download download files into current directory, instead of importing them\n";
	echo "--quali-type sets the qualification type:\n";
	if (!is_object($GLOBALS['import'])) $GLOBALS['import'] = new ranking_import();
	foreach($GLOBALS['import']->quali_types as $num => $label)
	{
		echo "\t$num\t$label\n";
	}
	echo "  Speed types:\n";
	foreach($GLOBALS['import']->quali_types_speed as $num => $label)
	{
		echo "\t$num\t$label\n";
	}
	echo "--set-status sets the status of an imported heat, default 'result official':\n";
	foreach($GLOBALS['import']->stati as $num => $label)
	{
		echo "\t$num\t$label\n";
	}
	echo "--import-ranking makes the general result official and imports it into the ranking\n";

	echo "\n";

	exit($ret);
}

$import = new ranking_import();

// defaults
$add_athletes = false;
$charset = 'iso-8859-1';
$cats = null;	// all
//$baseurl = 'http://localhost/sitemgr-site/index.php?page_name=resultservice&';
$baseurl = 'https://www.ifsc-climbing.org/index.php?page_name=resultservice&';
$debug = 0;
$only_download = false;
$set_status = STATUS_RESULT_OFFICIAL;
$route_type = null;
$detect_route_type = true;
$import_ranking = false;

while(($arg = array_shift($arguments)) && substr($arg,0,2) == '--')
{
	switch($arg)
	{
		case '--add-athletes':
			$add_athletes = true;
			break;
		case '--charset';
			$charset = array_shift($arguments);
			break;
		case '--cat';
			$cats = preg_split('/, */',array_shift($arguments));
			break;
		case '--baseurl';
			$baseurl = array_shift($arguments);
			break;
		case '--debug':
			$debug = (int)array_shift($arguments);
			break;
		case '--download';
			$only_download = true;
			break;
		case '--set-status':
			$set_status = (int)array_shift($arguments);
			if (!isset($import->stati[$set_status]))
			{
				echo "Error: not existing status!\n";
				usage(10);
			}
			break;
		case '--import-ranking':
			$set_status = STATUS_RESULT_OFFICIAL;
			$import_ranking = true;
			break;
		case '--quali-type':
			$route_type = (int)array_shift($arguments);
			$detect_route_type = false;
			if (!isset($import->quali_types[$route_type]) && !isset($import->quali_types_speed[$route_type]))
			{
				echo "Error: not existing quali-type!\n";
				usage(10);
			}
			break;
	}
}
if (!$arg) usage(3);

$import->from_url($arg, $cats, $route_type, null, $baseurl, $add_athletes, $set_status, $import_ranking, $only_download ? getcwd() : null, $debug, $charset);