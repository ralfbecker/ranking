<?php
/**
 * eGroupWare digital ROCK Rankings
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2006-11 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$
 */

// redirect to new eliste.html using JSON to fetch result
header('Location: /egroupware/ranking/sitemgr/digitalrock/eliste.html?'.$_SERVER['QUERY_STRING']);
exit;
