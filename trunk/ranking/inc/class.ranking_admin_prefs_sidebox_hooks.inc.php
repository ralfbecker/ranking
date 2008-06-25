<?php
/**
 * eGroupWare digital ROCK Rankings - Hooks: diverse static methods to be called as hooks
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2006-8 by Ralf Becker <RalfBecker@digitalrock.de>
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
				'Athletes' => $GLOBALS['egw']->link('/index.php',array(
					'menuaction' => 'ranking.uiathletes.index' )),
				'Federations' => $GLOBALS['egw']->link('/index.php',array(
					'menuaction' => 'ranking.ranking_federation_ui.index' )),
				'Competitions' => $GLOBALS['egw']->link('/index.php',array(
					'menuaction' => 'ranking.uicompetitions.index' )),
				'Cups' => $GLOBALS['egw']->link('/index.php',array(
					'menuaction' => 'ranking.uicups.index' )),
				/*'Categories' => $GLOBALS['egw']->link('/index.php',array(
					'menuaction' => 'ranking.ranking.cat_edit' )),*/
				'Registration' => $GLOBALS['egw']->link('/index.php',array(
					'menuaction' => 'ranking.uiregistration.index' )),
				'Resultservice' => $GLOBALS['egw']->link('/index.php',array(
					'menuaction' => 'ranking.uiresult.index' )),
				'Results' => $GLOBALS['egw']->link('/index.php',array(
					'menuaction' => 'ranking.uiregistration.result' )),
				'Ranking' => $GLOBALS['egw']->link('/index.php',array(
					'menuaction' => 'ranking.uiranking.index' )),
			);
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
							'text' => '<a class="textSidebox" href="'.$GLOBALS['egw']->link('/index.php',array(
									'menuaction' => 'ranking.ranking_display_ui.index',
									'dsp_id' => $dsp_id,
								)).
								'" onclick="window.open(this.href,\'display'.$dsp_id.'\',\'dependent=yes,width=700,height=580,scrollbars=yes,status=yes\');
								return false;">'.$dsp_name.'</a>',
							'no_lang' => true,
							'link' => false
						);
					}
					if ($GLOBALS['egw_info']['user']['apps']['admin'])
					{
						$file[] = array(
							'text' => '<a class="textSidebox" href="'.$GLOBALS['egw']->link('/index.php',array(
									'menuaction' => 'ranking.ranking_display_ui.display',
								)).
								'" onclick="window.open(this.href,\'_blank\',\'dependent=yes,width=640,height=480,scrollbars=yes,status=yes\');
								return false;">'.lang('Add').'</a>',
							'no_lang' => true,
							'link' => false
						);

					}
					display_sidebox($appname,lang('Displays'),$file);
				}
			}
		}

		if ($GLOBALS['egw_info']['user']['apps']['preferences'] && $location != 'admin')
		{
			$file = array(
				'Preferences'     => $GLOBALS['egw']->link('/preferences/preferences.php','appname='.$appname),
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
				'Site configuration' => $GLOBALS['egw']->link('/index.php',array(
					'menuaction' => 'admin.uiconfig.index',
					'appname'    => 'ranking',
				 )),
				'Nation ACL' => $GLOBALS['egw']->link('/index.php',array('menuaction' => 'ranking.admin.acl' )),
				'Import' => $GLOBALS['egw']->link('/index.php',array(
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

	static function hook_settings()
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
		create_select_box('Default ranking view','default_view',$ranking_views,
			'Which view do you want to see, when you start the ranking app?');

		return true;
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