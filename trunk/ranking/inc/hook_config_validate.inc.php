<?php
/**
 * eGroupWare digital ROCK Rankings - configuration validation
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2006 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$ 
 */

/*
  Set a global flag to indicate this file was found by setup/config.php.
  config.php will unset it after parsing the form values.
*/
$GLOBALS['egw_info']['server']['found_validation_hook'] = True;

function final_validation($settings)
{
	//echo "final_validation(\$settings) \$settings="; _debug_array($settings);
	
	$install_async_job = false;

	for($n = 1; $n <= 2; ++$n)
	{
		if (($route = $settings['rock_import'.$n]))
		{
			list(,$comp) = explode('.',$route);
			$year = (int) $comp + 2000;
			$file = $settings['rock_import_path'].'/'.$year.'/'.$comp.'/'.$route.'.php';
	
			if (!file_exists($file))
			{
				$GLOBALS['config_error'] .= "File '$file' does NOT exist !!!\n";
			}
			else
			{
				$install_async_job = true;
			}
		}
	}
	_set_async_job($install_async_job);
}

/**
 * Check if exist and if not start or stop an async job to import rock routes
 *
 * @param boolean $start=true true=start, false=stop
 */
function _set_async_job($start=true)
{
	//echo "<p>boresultr::set_async_job(".($start?'true':'false').")</p>\n";

	require_once(EGW_API_INC.'/class.asyncservice.inc.php');
	
	$async =& new asyncservice();
	
	if ($start === !$async->read('ranking-import-rock'))
	{
		if ($start)
		{
			$async->set_timer(array('min' => '*'),'ranking-import-rock','ranking.boresult.import_from_rock',null);
		}
		else
		{
			$async->cancel_timer('ranking-import-rock');
		}
	}
}
