<?php
/**
 * eGroupWare digital ROCK Rankings - SiteMgr block for resultservice
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2007 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$
 */

require_once(EGW_INCLUDE_ROOT.'/etemplate/inc/class.sitemgr_module.inc.php');

class module_ranking_results extends sitemgr_module
{
	function module_ranking_results()
	{
		$this->arguments = array(
			'arg2' => array(
				'type' => 'textfield',
				'label' => lang('Pagename(,target) for athlete profiles'),
				'default' => 'pstambl,profile'
			),
		);
		$this->title = lang('Resultservice');
		$this->description = lang('This module displays information from the resultservice of the ranking app.');

		$this->etemplate_method = 'ranking.ranking_result_ui.index';
	}

	function get_content(&$arguments,$properties)
	{
		$content = parent::get_content($arguments,$properties);

		if ($GLOBALS['egw_info']['flags']['app_header'])
		{
			$GLOBALS['page']->title = $GLOBALS['egw_info']['flags']['app_header'];
		}
		return $content;
	}
}
