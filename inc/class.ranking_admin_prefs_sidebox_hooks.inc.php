<?php
/**
 * eGroupWare digital ROCK Rankings - Hooks: diverse static methods to be called as hooks
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2006-11 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$
 */

/**
 * eGroupWare digital ROCK Rankings - Hooks: diverse static methods to be called as hooks
 */
class ranking_admin_prefs_sidebox_hooks
{
	static function all_hooks($args)
	{
		$appname = 'ranking';
		$location = is_array($args) ? $args['location'] : $args;
		//echo "<p>ranking_admin_prefs_sidebox_hooks::all_hooks(".print_r($args,True).") appname='$appname', location='$location'</p>\n";

		if ($location == 'sidebox_menu')
		{
			// add ranking version to the eGW version
			$GLOBALS['egw_info']['server']['versions']['phpgwapi'] .= ' / '.lang('Ranking').' '.lang('Version').' '.$GLOBALS['egw_info']['apps']['ranking']['version'];

			$file = array(
				'Athletes'      => egw::link('/index.php',array('menuaction' => 'ranking.uiathletes.index')),
				'Federations'   => egw::link('/index.php',array('menuaction' => 'ranking.ranking_federation_ui.index')),
				'Competitions'  => egw::link('/index.php',array('menuaction' => 'ranking.uicompetitions.index')),
				'Cups'          => egw::link('/index.php',array('menuaction' => 'ranking.uicups.index')),
				'Categories'    => egw::link('/index.php',array('menuaction' => 'ranking.ranking_cats_ui.index')),
				'Registration'  => egw::link('/index.php',array('menuaction' => 'ranking.uiregistration.index')),
				'Resultservice' => egw::link('/index.php',array('menuaction' => 'ranking.uiresult.index')),
				'Results'       => egw::link('/index.php',array('menuaction' => 'ranking.uiregistration.result')),
				'Ranking'       => egw::link('/index.php',array('menuaction' => 'ranking.uiranking.index')),
			);
			if (is_object($GLOBALS['boranking']) && in_array('SUI',$GLOBALS['boranking']->ranking_nations))
			{
				$file['Accounting'] = egw::link('/index.php',array('menuaction' => 'ranking.ranking_accounting.index'));
			}
			display_sidebox($appname,$GLOBALS['egw_info']['apps']['ranking']['title'].' '.lang('Menu'),$file);

			if (is_object($GLOBALS['uiresult']))	// we show the displays menu only if we are in the result-service
			{
				if (($displays = $GLOBALS['uiresult']->display->displays()) || $GLOBALS['egw_info']['user']['apps']['admin'])
				{
					if (!is_array($displays)) $displays = array();
					$file = array();
					foreach($displays as $dsp_id => $dsp_name)
					{
						$file[] = array(
							'text' => $dsp_name,
							'link' => "javascript:egw_openWindowCentered2('".egw::link('/index.php',array(
								'menuaction' => 'ranking.ranking_display_ui.index',
								'dsp_id' => $dsp_id,
							),false)."','display$dsp_id',700,580,'yes')",
							'no_lang' => true,
						);
					}
					if ($GLOBALS['egw_info']['user']['apps']['admin'])
					{
						$file['Add'] = "javascript:egw_openWindowCentered2('".egw::link('/index.php',array(
							'menuaction' => 'ranking.ranking_display_ui.display',
						),false)."','display$dsp_id',640,480,'yes')";
					}
					display_sidebox($appname,lang('Displays'),$file);
				}
			}
		}

		if ($GLOBALS['egw_info']['user']['apps']['preferences'] && $location != 'admin')
		{
			$file = array(
				'Preferences'     => egw::link('/preferences/preferences.php','appname='.$appname),
			);
			if ($location == 'preferences')
			{
				display_section($appname,$file);
			}
			else
			{
				$file[] = array(
					'text'   => 'Manual',
					'link'   => $GLOBALS['egw_info']['server']['webserver_url'].'/ranking/doc/manual.pdf',
					'target' => 'docs'
				);
				display_sidebox($appname,lang('Preferences'),$file);
			}
		}

		if ($GLOBALS['egw_info']['user']['apps']['admin'] && $location != 'preferences')
		{
			$file = Array(
				'Site configuration' => egw::link('/index.php',array(
					'menuaction' => 'admin.uiconfig.index',
					'appname'    => 'ranking',
				 )),
				'Nation ACL' => egw::link('/index.php',array('menuaction' => 'ranking.admin.acl' )),
				'Import' => egw::link('/index.php',array(
					'menuaction' => 'ranking.ranking_import.index' )),
			);
			if ($location == 'admin')
			{
				display_section($appname,$file);
			}
			else
			{
				display_sidebox($appname,lang('Admin'),$file);
			}
		}
	}

	/**
	 * Settings hook
	 *
	 * @param array|string $hook_data
	 * @return array
	 */
	static function hook_settings($hook_data)
	{
		$ranking_views = array(
			'ranking.uicompetitions.index'   => lang('Competitions'),
			'ranking.uicups.index'           => lang('Cups'),
		//	'ranking.uicats.index'           => lang('Categories'),
			'ranking.uiathletes.index'       => lang('Athletes'),
			'ranking.uiregistration.index'   => lang('Registration'),
			'ranking.uiresult.index'         => lang('Resultservice'),
			'ranking.uiregistration.result'  => lang('Results'),
			'ranking.uiranking.index'        => lang('Ranking'),
		);
		return array(
			'default_view' => array(
				'type'   => 'select',
				'label'  => 'Default ranking view',
				'name'   => 'default_view',
				'values' => $ranking_views,
				'help'   => 'Which view do you want to see, when you start the ranking app?',
				'xmlrpc' => True,
				'admin'  => False,
			),
		);
	}

	/**
	 * Hook called by link-class to include athletes in the appregistry of the linkage
	 *
	 * @param array/string $location location and other parameters (not used)
	 * @return array with method-names
	 */
	static function search_link($location)
	{
		return array(
			'query' => 'ranking.athlete.link_query',
			'title' => 'ranking.athlete.link_title',
//			'titles' => 'ranking.athlete.link_titles',
			'view' => array(
				'menuaction' => 'ranking.uiathletes.edit'
			),
			'view_id' => 'PerId',
			'add' => array(
				'menuaction' => 'ranking.uiathletes.edit'
			),
			'add_popup'  => '850x450',
		);
	}
}