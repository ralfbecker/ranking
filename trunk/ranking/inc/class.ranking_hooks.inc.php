<?php
/**
 * EGroupware digital ROCK Rankings - Hooks: diverse static methods to be called as hooks
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2006-14 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$
 */

/**
 * Rankings - Hooks: diverse static methods to be called as hooks
 */
class ranking_hooks
{
	static function all_hooks($args)
	{
		$appname = 'ranking';
		$location = is_array($args) ? $args['location'] : $args;
		//echo "<p>ranking_admin_prefs_sidebox_hooks::all_hooks(".print_r($args,True).") appname='$appname', location='$location'</p>\n";

		if ($location == 'sidebox_menu' || $location == 'return_ranking_views')
		{
			// add ranking version to the eGW version
			$GLOBALS['egw_info']['server']['versions']['phpgwapi'] .= ' / '.lang('Ranking').' '.lang('Version').' '.$GLOBALS['egw_info']['apps']['ranking']['version'];

			$file = array(
				'Athletes'      => egw::link('/index.php',array('menuaction' => 'ranking.ranking_athlete_ui.index')),
				'Federations'   => egw::link('/index.php',array('menuaction' => 'ranking.ranking_federation_ui.index')),
				'Competitions'  => egw::link('/index.php',array('menuaction' => 'ranking.ranking_competition_ui.index')),
				'Cups'          => egw::link('/index.php',array('menuaction' => 'ranking.ranking_cup_ui.index')),
				'Categories'    => egw::link('/index.php',array('menuaction' => 'ranking.ranking_cats_ui.index')),
				'Registration'  => egw::link('/index.php',array('menuaction' => 'ranking.uiregistration.index')),
				'Resultservice' => egw::link('/index.php',array('menuaction' => 'ranking.ranking_result_ui.index','ajax' => 'true')),
				'Results'       => egw::link('/index.php',array('menuaction' => 'ranking.uiregistration.result')),
				'Ranking'       => egw::link('/index.php',array('menuaction' => 'ranking.uiranking.index')),
				'Accounting'    => egw::link('/index.php',array('menuaction' => 'ranking.ranking_accounting.index')),
			);
			if ($location == 'return_ranking_views') return $file;
			display_sidebox($appname,$GLOBALS['egw_info']['apps']['ranking']['title'].' '.lang('Menu'),$file);

			$file = array();
			$file[] = array(
				'text' => lang('Beamer / videowalls'),
				'link' => "javascript:egw_openWindowCentered2('".egw::link('/index.php',array(
					'menuaction' => 'ranking.ranking_beamer.beamer',
				),false)."','beamer',1024,768,'yes')",
				'no_lang' => true,
			);
			$file[] = array(
				'text'   => 'Boulder timer',
				'link'   => $GLOBALS['egw_info']['server']['webserver_url'].'/ranking/timer/index.html',
				'target' => 'timer',
			);

			if (is_object($GLOBALS['uiresult']))	// we show the displays menu only if we are in the result-service
			{
				if (($displays = $GLOBALS['uiresult']->display->displays()) || $GLOBALS['egw_info']['user']['apps']['admin'])
				{
					if (!is_array($displays)) $displays = array();
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
				}
			}
			if ($GLOBALS['egw_info']['user']['apps']['admin'])
			{
				$file['Add'] = "javascript:egw_openWindowCentered2('".egw::link('/index.php',array(
					'menuaction' => 'ranking.ranking_display_ui.display',
				),false)."','display$dsp_id',640,480,'yes')";
			}
			// show SVN changelog under display
			$file[] = array(
				'text'   => 'Changelog',
				'link'   => 'https://svn.stylite.de/.svnlog/ranking.html',
				'target' => 'changelog',
			);
			display_sidebox($appname,lang('Displays'),$file);
		}

		if ($GLOBALS['egw_info']['user']['apps']['preferences'] && $location != 'admin')
		{
			$file = array(
				'Preferences'     => egw::link('/index.php',array(
					'menuaction' => 'preferences.uisettings.index',
					'appname' => $appname,
				)),
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
		$ranking_views = array();
		foreach(self::all_hooks('return_ranking_views') as $label => $url)
		{
			$ranking_views[preg_replace('/^.*menuaction=([^&]+).*/', '$1', $url)] = $label;
		}

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
			'query' => 'ranking.ranking_athlete.link_query',
			'title' => 'ranking.ranking_athlete.link_title',
//			'titles' => 'ranking.ranking_athlete.link_titles',
			'view' => array(
				'menuaction' => 'ranking.ranking_athlete_ui.edit'
			),
			'view_id' => 'PerId',
			'add' => array(
				'menuaction' => 'ranking.ranking_athlete_ui.edit'
			),
			'add_popup'  => '850x450',
		);
	}
}