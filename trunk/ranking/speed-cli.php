#!/usr/bin/php -C
<?php
/**
 * Ranking - Import - Command line interface
 *
 * Following patch is neccessary in class.boresult.inc.php to disable caching, as cache is not accessable by cli program:
 *
 *		$location = 'export_route:'.$comp.':'.$cat.':'.$heat;
 *		// switch caching off for speed-cli.php, as it can not (un)set the cache,
 *		// because of permissions of /tmp/egw_cache only writable by webserver-user
 *		// for all other purposes caching is ok and should be enabled
 *-		if ($update_cache || !($data = egw_cache::getInstance('ranking', $location)) !== false)
 *+		//if ($update_cache || !($data = egw_cache::getInstance('ranking', $location)) !== false)
 *		{
 *			if (!isset(self::$instance)) new boresult();
 *
 * @link http://www.digitalrock.de
 * @link http://www.egroupware.org
 * @package ranking
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2011 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

if (isset($_SERVER['HTTP_HOST']))	// security precaution: forbit calling admin-cli as web-page
{
	die('<h1>import-cli.php must NOT be called as web-page --> exiting !!!</h1>');
}
$arguments = $_SERVER['argv'];
array_shift($arguments);	// remove cmd
// this is kind of a hack, as the autocreate_session_callback can not change the type of the loaded account-class
// so we need to make sure the right one is loaded by setting the domain before the header gets included.
@list(,$_GET['domain']) = explode('@',array_shift($arguments));	// remove user from args
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
ob_end_flush();

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
	echo "Usage: $cmd username password [--check-existing] competition directory [cat1 [cat2]]\n";
	echo "	--check-existing check already existing files too, normal only changed files get imported\n\n";

	echo "Imports the results of a speed competition from a mounted windows PC.\n\n";

	echo "Files have to be named cat.route.xml.\n\n";

	echo "\n";

	exit($ret);
}

include_once(EGW_INCLUDE_ROOT.'/ranking/inc/class.boresult.inc.php');
$boresult = new boresult();

$check_existing = false;
while(($arg = array_shift($arguments)) && substr($arg,0,2) == '--')
{
	switch($arg)
	{
		case '--check-existing':
			$check_existing = true;
			break;
	}
}

if (!$arg || count($arguments) < 2) usage(3);

if (!($comp = $boresult->comp->read(is_numeric($arg) ? array('WetId'=>$arg) : array('rkey'=>$arg))))
{
	throw new Exception("Competition '$arg' not found!",4);
}

$path = array_shift($arguments);
if (!file_exists($path))
{
	throw new Exception("Importpath '$path' not found!",5);
}

if (!is_dir($path))
{
	throw new Exception("Importpath '$path' is no directory!",6);
}

$first_run = true;
$timestamps = array();
while (true)
{
	clearstatcache();
	$files = scandir($path);
	foreach($files as $file)
	{
		// check if path match cat-N.xml
		$cat = $route = $xml = null;
		list($cat,$route,$xml) = explode('.',$file,3);
		if ($xml == 'xml' && is_numeric($route) && in_array($cat,$arguments))
		{
			$mtime = filemtime($path.'/'.$file);
			//echo "filemtime('$file')=".date('Y-m-d H:i:s',$mtime)."\n";
			if (!isset($timestamps[$file]) && $first_run && $check_existing || isset($timestamps[$file]) && $timestamps[$file] < $mtime)
			{
				//echo "$file: isset()=".array2string(isset($timestamps[$file])).", first_run=".array2string($first_run).", check_existing=".array2string($check_existing)."\n";
				echo "\n$file modified ".date('Y-m-d H:i:s',$mtime)." needs importing\n";

				try {
					usleep(200000);	// Zingerle software does 2 write operations and timestamps have only 1 sec accurancy
					check_import($path.'/'.$file,$cat,$route);
				}
				catch(Exception $e) {
					echo $e->getMessage()."\n";
					echo $e->getTraceAsString()."\n";
				}
			}
			$timestamps[$file] = $mtime;
		}
	}
	if (!$timestamps)
	{
		echo "No matching files found!\n";
	}
	else
	{
		echo ".";
	}
	sleep(1);
	$first_run = false;
}

function check_import($path,$cat,$route)
{
	global $comp;
	global $boresult;

	if (!($cat = $boresult->cats->read($cat)))
	{
		throw new Exception("Category '$cat' not found!",7);
	}
	if (!($route = $boresult->route->read($keys=array(
		'WetId' => $comp['WetId'],
		'GrpId' => $cat['GrpId'],
		'route_order' => $route,
	))))
	{
		throw new Exception("Route for keys=".array2string($keys)." NOT found!",8);
	}
	if ($route['route_status'] == STATUS_RESULT_OFFICIAL)
	{
		throw new Exception("Route result already offical!",9);
	}
	$imported = $boresult->upload($keys,$path);
	if (!is_numeric($imported))
	{
		throw new Exception($imported,10);
	}
	echo "$imported results imported.\n";
}
