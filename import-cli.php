#!/usr/bin/php -C
<?php
/**
 * Ranking - Import - Command line interface
 *
 * @link http://www.digitalrock.de
 * @link http://www.egroupware.org
 * @package ranking
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2008 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
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
	if (!is_object($GLOBALS['boresult'])) $GLOBALS['boresult'] = CreateObject('ranking.boresult');
	foreach($GLOBALS['boresult']->quali_types as $num => $label)
	{
		echo "\t$num\t$label\n";
	}
	echo "\t".TWO_QUALI_SPEED."\ttwo qualifications speed\n";
	echo "--set-status sets the status of an imported heat, default 'result official':\n";
	foreach($GLOBALS['boresult']->stati as $num => $label)
	{
		echo "\t$num\t$label\n";
	}
	echo "--import-ranking makes the general result official and imports it into the ranking\n";

	echo "\n";

	exit($ret);
}

function get_exec_id($html)
{
	global $debug;
	if (!preg_match('/name="etemplate_exec_id" value="([^"]+)"/m',$html,$matches))
	{
		throw new Exception("Error: etemplate_exec_id not found!",6);
	}
	if ($debug > 2) echo "etemplate_exec_id='$matches[1]'\n";
	return $matches[1];
}

include_once(EGW_INCLUDE_ROOT.'/ranking/inc/class.boresult.inc.php');
$boresult = new boresult();

// defaults
$add_athletes = false;
$charset = 'iso-8859-1';
$cats = null;	// all
//$baseurl = 'http://localhost/sitemgr-site/index.php?page_name=resultservice&';
$baseurl = 'http://www.ifsc-climbing.org/index.php?page_name=resultservice&';
$debug = 0;
$only_download = false;
require_once(EGW_INCLUDE_ROOT.'/ranking/inc/class.route_result.inc.php');
$set_status = STATUS_RESULT_OFFICIAL;
$route_type = ONE_QUALI;
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
			$cats = split(', *',array_shift($arguments));
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
			if (!isset($boresult->stati[$set_status]))
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
			if (!isset($boresult->quali_types[$route_type]) && $route_type !== TWO_QUALI_SPEED)
			{
				echo "Error: not existing status!\n";
				usage(10);
			}
			break;
	}
}
if (!$arg) usage(3);

if (!($comp = $boresult->comp->read(is_numeric($arg) ? array('WetId'=>$arg) : array('rkey'=>$arg))))
{
	throw new Exception("Competition '$arg' not found!",4);
}
if (is_null($cats))
{
	$cats = $comp['gruppen'];
}
echo $comp['rkey'].': '.$comp['name']."\n";
//echo "competition=$arg\n\n";
//print_r($comp);
//echo "cats="; print_r($cats);
$cat = $cats[0];

if (!($ch = curl_init($url=$baseurl.'comp='.$arg.'&cat='.$cat.'&route=0')))
{
	throw new Exception("Error: opening URL '$url'!",5);
}
$cookiefile = tempnam('/tmp','importcookies');
curl_setopt($ch, CURLOPT_COOKIEFILE,$cookiefile);
curl_setopt($ch, CURLOPT_COOKIEJAR,$cookiefile); # SAME cookiefile
curl_setopt($ch,CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
curl_setopt($ch,CURLOPT_HEADER,true);

// getting the page of the competition and category --> get's us to the general result
if ($debug > 2) echo "\nGETting $url\n";
$get = curl_exec($ch);
//echo substr($get,0,500)."\n\n";
$exec_id = get_exec_id($get);

curl_setopt($ch,CURLOPT_POST,true);		// from now on only posts
foreach($cats as $n => $cat_name)
{
	if (!($cat = $boresult->cats->read($cat_name)))
	{
		throw new Exception("Error: Cat '$cat_name' not found!",7);
	}
	echo $cat['rkey'].': '.$cat['name']."\n";

	if ($n)	// changing the cat via a post --> get's us to the general result
	{
		// setting route=0 qualification with a post
		curl_setopt($ch,CURLOPT_URL,$url=$baseurl.'comp='.$arg.'&cat='.$cat['GrpId']);
		curl_setopt($ch,CURLOPT_POSTFIELDS,$post=http_build_query(array(
			'etemplate_exec_id' => $exec_id,
			'exec' => array(
				'nm' => array(
					'cat' => $cat['GrpId'],
					'show_result' => 1,
					'route' => 0,
				),
			)
		)));
		if ($debug > 2) echo "POSTing $url with $post\n";
		$exec_id = get_exec_id($download=curl_exec($ch));	// switch to route=0 and get new exec-id
		//if ($n) echo $download."\n\n";
	}
	// setting route=0 qualification with a post
	curl_setopt($ch,CURLOPT_URL,$url=$baseurl.'comp='.$arg.'&cat='.$cat['GrpId']);
	curl_setopt($ch,CURLOPT_POSTFIELDS,$post=http_build_query(array(
		'etemplate_exec_id' => $exec_id,
		'exec' => array(
			'nm' => array(
				'cat' => $cat['GrpId'],
				'show_result' => 1,
				'route' => 0,
			),
		)
	)));
	if ($debug > 2) echo "POSTing $url with $post\n";
	$exec_id = get_exec_id($download=curl_exec($ch));	// switch to route=0 and get new exec-id
	if ($debug > 4) echo $download."\n\n";

	for($route=0; $route <= 6; ++$route)
	{
		// download each heat
		curl_setopt($ch,CURLOPT_URL,$url=$baseurl.'comp='.$arg.'&cat='.$cat['GrpId']);
		curl_setopt($ch,CURLOPT_POSTFIELDS,$post=http_build_query(array(
			'etemplate_exec_id' => $exec_id,
			'submit_button' => 'exec[button][download]',
			'exec' => array(
				'nm' => array(
					'cat' => $cat['GrpId'],
					'show_result' => 1,
					'route' => $route,
				),
			)
		)));
		if ($debug > 2) echo "\nPOSTing $url with $post\n";
		$download = curl_exec($ch);
		list($headers,$download) = explode("\r\n\r\n",$download,2);
		if ($debug > 3) echo $headers."\n";
		if (!preg_match('/attachment; filename="([^"]+)"/m',$headers,$matches))
		{
			if ($route == 1) continue;	// me might not have a 2. quali
			break;	// no further heat
		}
		$fname = str_replace('/','-',$matches[1]);

		// convert from the given charset to eGW's
		$download = $GLOBALS['egw']->translation->convert($download,$charset);
		if ($debug > 1) echo "$fname:\n".implode("\n",array_slice(explode("\n",$download),0,4))."\n\n";

		if ($only_download)
		{
			file_put_contents($fname,$download);
		}
		else // import
		{
			$content = array(
				'WetId' => $comp['WetId'],
				'GrpId' => $cat['GrpId'],
				'route_order' => $route,
			);
			if (!$boresult->init_route($content,$comp,$cat,$discipline))
			{
				throw new Exception(lang('Permission denied !!!'),9);
			}
			require_once(EGW_API_INC.'/class.global_stream_wrapper.inc.php');
			$num_imported = $boresult->upload($content,fopen('global://download','r'),$add_athletes);

			if (is_numeric($num_imported))
			{
				$need_save = $content['new_route'];
				if (!$route && $route_type)
				{
					$content['route_type'] = $route_type;
					$need_save = true;
				}
				// set number of problems from csv file
				if ($content['route_num_problems'])
				{
					list($line1) = explode("\n",$download);
					for($n = 3; $n <= 6; $n++)
					{
						if (strpos($line1,'boulder'.$n)) $num_problems = $n;
					}
					if ($num_problems && $num_problems != $content['route_num_problems'])
					{
						$content['route_num_problems'] = $num_problems;
						$need_save = true;
					}
				}
				// set the name from the csv file
				if (substr($fname,0,strlen($cat['name'])+3) == $cat['name'].' - ' &&
					($name_from_file = str_replace('.csv','',substr($fname,strlen($cat['name'])+3))) != $content['route_name'])
				{
					$content['route_name'] = $name_from_file;
					$need_save = true;
				}
				if ($set_status != $content['route_status'])
				{
					$content['route_status'] = $set_status;
					$need_save = true;
				}
				// save the route, if we set something above
				if ($need_save && $boresult->route->save($content) != 0)
				{
					throw new Exception(lang('Error: saving the heat!!!'),8);
				}
				echo $fname.': '.lang('%1 participants imported',$num_imported)."\n";
			}
			else
			{
				throw new Exception($num_imported,9);
			}
		}
	}
	if ($import_ranking && $route >= 2)
	{
		$content = array(
			'WetId' => $comp['WetId'],
			'GrpId' => $cat['GrpId'],
			'route_order' => -1,
		);
		if (!$boresult->init_route($content,$comp,$cat,$discipline))
		{
			throw new Exception(lang('Permission denied !!!'),9);
		}
		$content['route_status'] = STATUS_RESULT_OFFICIAL;
		if ($boresult->route->save($content) != 0)
		{
			throw new Exception(lang('Error: saving the heat!!!'),8);
		}
		echo $boresult->import_ranking($content)."\n";
	}
}