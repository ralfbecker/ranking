<?php
/**************************************************************************\
* eGroupWare - digital ROCK Rankings - Configuration                       *
* http://www.egroupware.org, http://www.digitalROCK.de                     *
* Written and (c) by Ralf Becker <RalfBecker@outdoor-training.de>          *
* --------------------------------------------                             *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

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
