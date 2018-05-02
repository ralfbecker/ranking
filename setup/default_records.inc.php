<?php
/**
 * EGroupware digital ROCK Rankings - setup
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2006-18 by Ralf Becker <RalfBecker@digitalrock.de>
 */

use EGroupware\Api;

$db_backup = new Api\Db\Backup();
if (($f = $db_backup->fopen_backup(__DIR__.'/db_backup-ranking.bz2', True)))
{
	$db_backup->db_restore($f);
	fclose($f);
}