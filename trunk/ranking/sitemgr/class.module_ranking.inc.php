<?php
/**
 * eGroupWare digital ROCK Rankings - SiteMgr block for registration
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2006 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$ 
 */

require_once(EGW_INCLUDE_ROOT.'/etemplate/inc/class.sitemgr_module.inc.php');

class module_ranking extends sitemgr_module  
{
	function module_ranking()
	{
		$this->arguments = array();
		$this->title = lang('Ranking');
		$this->description = lang('This module displays information from the ranking app.');
		
		$this->etemplate_method = 'ranking.uiregistration.lists';
	}
}
