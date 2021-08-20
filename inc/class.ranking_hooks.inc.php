<?php
/**
 * EGroupware digital ROCK Rankings - Hooks: diverse static methods to be called as hooks
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2006-19 by Ralf Becker <RalfBecker@digitalrock.de>
 */

use EGroupware\Api;
use EGroupware\Api\Egw;

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

			$links = array(
				'Athletes'      => Egw::link('/index.php',array('menuaction' => 'ranking.ranking_athlete_ui.index','ajax' => 'true')),
				'Federations'   => Egw::link('/index.php',array('menuaction' => 'ranking.ranking_federation_ui.index','ajax' => 'true')),
				'Competitions'  => Egw::link('/index.php',array('menuaction' => 'ranking.ranking_competition_ui.index','ajax' => 'true')),
				'Cups'          => Egw::link('/index.php',array('menuaction' => 'ranking.ranking_cup_ui.index','ajax' => 'true')),
				'Categories'    => Egw::link('/index.php',array('menuaction' => 'ranking.ranking_cats_ui.index','ajax' => 'true')),
				'Registration'  => Egw::link('/index.php',array('menuaction' => 'ranking.ranking_registration_ui.index','ajax' => 'true')),
				'Resultservice' => Egw::link('/index.php',array('menuaction' => 'ranking.ranking_result_ui.index','ajax' => 'true')),
				'Results'       => Egw::link('/index.php',array('menuaction' => 'ranking.ranking_registration_ui.result','ajax' => 'true')),
				'Ranking'       => Egw::link('/index.php',array('menuaction' => 'ranking.uiranking.index','ajax' => 'true')),
				'Accounting'    => Egw::link('/index.php',array('menuaction' => 'ranking.ranking_accounting.index','ajax' => 'true')),
			);
			// show import only if user has more than read rights
			$bo = ranking_bo::getInstance();
			if (!empty($bo->edit_rights) || !empty($bo->athlete_rights) || !empty($bo->register_rights))
			{
				$links['Import'] = Egw::link('/index.php',array('menuaction' => 'ranking.ranking_import.index','ajax' => 'true'));
			}
			if ($location == 'return_ranking_views') return $links;
			display_sidebox($appname,$GLOBALS['egw_info']['apps']['ranking']['title'].' '.lang('Menu'),$links);

			$docs = array();
			$docs[] = array(
				'text'   => 'Manual',
				'link'   => $GLOBALS['egw_info']['server']['webserver_url'].'/ranking/doc/manual.pdf',
				'target' => 'manual'
			);
			$docs[] = array(
				'text'   => 'Combined Format Manual',
				'link'   => $GLOBALS['egw_info']['server']['webserver_url'].'/ranking/doc/CombinedFormatManual.pdf',
				'target' => 'combined'
			);
			// show GitHub changelog under Documenation
			$docs[] = array(
				'text'   => 'Changelog',
				'link'   => 'https://github.com/ralfbecker/ranking/commits/master',
				'target' => 'changelog',
			);
			$docs['Placeholders'] = Egw::link('/index.php','menuaction=ranking.ranking_merge.show_replacements');
			display_sidebox($appname, lang('Documentation'), $docs);

			$file = array();
			$file[] = array(
				'text' => lang('Beamer / videowalls'),
				'link' => "javascript:egw_openWindowCentered2('".Egw::link('/index.php',array(
					'menuaction' => 'ranking.ranking_beamer.beamer',
				),false)."','beamer',1024,768,'yes')",
				'no_lang' => true,
			);
			$file[] = array(
				'text'   => 'Boulder timer',
				'link'   => $GLOBALS['egw_info']['server']['webserver_url'].'/ranking/timer/index.html',
				'target' => 'timer',
			);

			if (is_object($GLOBALS['ranking_result_ui']))	// we show the displays menu only if we are in the result-service
			{
				if (($displays = $GLOBALS['ranking_result_ui']->display->displays()) || $GLOBALS['egw_info']['user']['apps']['admin'])
				{
					if (!is_array($displays)) $displays = array();
					foreach($displays as $dsp_id => $dsp_name)
					{
						$file[] = array(
							'text' => $dsp_name,
							'link' => "javascript:egw_openWindowCentered2('".Egw::link('/index.php',array(
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
				$file['Add'] = "javascript:egw_openWindowCentered2('".Egw::link('/index.php',array(
					'menuaction' => 'ranking.ranking_display_ui.display',
				),false)."','display$dsp_id',640,480,'yes')";
			}
			display_sidebox($appname,lang('Displays'),$file);
		}

		if ($GLOBALS['egw_info']['user']['apps']['admin'] && $location != 'preferences')
		{
			$file = Array(
				'Site configuration' => Egw::link('/index.php',array(
					'menuaction' => 'admin.admin_config.index',
					'appname'    => 'ranking',
					'ajax'       => 'true',
				 )),
				'Nation ACL' => Egw::link('/index.php',array('menuaction' => 'ranking.admin.acl' )),
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
		unset($hook_data);	// not used, but required by function signature
		$ranking_views = array();
		foreach(self::all_hooks('return_ranking_views') as $label => $url)
		{
			$ranking_views[preg_replace('/^.*menuaction=([^&]+).*/', '$1', $url)] = $label;
		}

		$settings = array(
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
		$settings[] = array(
			'type'  => 'section',
			'title' => lang('Data exchange settings'),
			'no_lang'=> true,
			'xmlrpc' => False,
			'admin'  => False
		);

		// Merge print
		if ($GLOBALS['egw_info']['user']['apps']['filemanager'])
		{
			$settings['default_document'] = array(
				'type'   => 'vfs_file',
				'size'   => 60,
				'label'  => 'Default document to insert entries',
				'name'   => 'default_document',
				'help'   => lang('If you specify a document (full vfs path) here, %1 displays an extra document icon for each entry. That icon allows to download the specified document with the data inserted.',lang('infolog')).' '.
					lang('The document can contain placeholder like {{%1}}, to be replaced with the data.','info_subject').' '.
					lang('The following document-types are supported:').implode(', ', ranking_merge::get_file_extensions()),
				'run_lang' => false,
				'xmlrpc' => True,
				'admin'  => False,
			);
			$settings['document_dir'] = array(
				'type'   => 'vfs_dirs',
				'size'   => 60,
				'label'  => 'Directory with documents to insert entries',
				'name'   => 'document_dir',
				'help'   => lang('If you specify a directory (full vfs path) here, %1 displays an action for each document. That action allows to download the specified document with the data inserted.',lang('ranking')).' '.
					lang('The document can contain placeholder like {{%1}}, to be replaced with the data.','nachname').' '.
					lang('The following document-types are supported:').implode(', ', ranking_merge::get_file_extensions()),
				'run_lang' => false,
				'xmlrpc' => True,
				'admin'  => False,
				'default' => '/templates/ranking',
			);
		}

		return $settings;
	}

	/**
	 * Hook called by link-class to include athletes in the appregistry of the linkage
	 *
	 * @param array|string $location location and other parameters (not used)
	 * @return array with method-names
	 */
	static function search_link($location)
	{
		unset($location);	// not used, but required by function signature
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
			'add_popup'  => '900x470',
		);
	}

	/**
	 * Hook called before backup starts
	 *
	 * Used to setup Api\Db::$tablealiases according to ranking configuration
	 * to back up ranking tables in a different database.
	 *
	 * @param string|array $location
	 */
	static function backup_starts($location)
	{
		unset($location);	// not used

		$config = Api\Config::read('ranking');

		if (!empty($config['ranking_db_name']) && empty($config['ranking_db_host']) &&
			empty($config['ranking_db_user']))
		{
			foreach(array_keys($GLOBALS['egw']->db->get_table_definitions('ranking')) as $table)
			{
				Api\Db::$tablealiases[$table] = $config['ranking_db_name'].'.'.$table;
			}
		}
	}
}