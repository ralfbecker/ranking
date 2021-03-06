<?php
/**
 * eGroupWare digital ROCK Rankings - configuration validation
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2006-14 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$
 */

/*
  Set a global flag to indicate this file was found by setup/config.php.
  config.php will unset it after parsing the form values.
*/
$GLOBALS['egw_info']['server']['found_validation_hook'] = True;

if (!is_object($GLOBALS['ranking_result_bo']))
{
	try {
		$GLOBALS['ranking_result_bo'] = new ranking_result_bo();
	}
	catch(Exception $e) {
		$GLOBALS['config_error'] .= "Invalid DB configuration!";
	}
}
