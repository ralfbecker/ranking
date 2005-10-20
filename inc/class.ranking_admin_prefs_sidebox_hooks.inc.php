<?php
/**************************************************************************\
* eGroupWare - Ranking Admin-, Preferences- and SideboxMenu-Hooks          *
* http://www.eGroupWare.org                                                *
* Written and (c) by Ralf Becker <RalfBecker@outdoor-training.de>          *
* -------------------------------------------------------                  *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

class ranking_admin_prefs_sidebox_hooks
{
	var $public_functions = array(
		'all_hooks' => true,
	);
	function all_hooks($args)
	{
		$appname = 'ranking';
		$location = is_array($args) ? $args['location'] : $args;
		//echo "<p>ranking_admin_prefs_sidebox_hooks::all_hooks(".print_r($args,True).") appname='$appname', location='$location'</p>\n";

		if ($location == 'sidebox_menu')
		{
			$file = array(
				'Competitions' => $GLOBALS['egw']->link('/index.php',array(
					'menuaction' => 'ranking.uicompetitions.index' )),
				'Cups' => $GLOBALS['egw']->link('/index.php',array(
					'menuaction' => 'ranking.uicups.index' )),
				/*'Categories' => $GLOBALS['egw']->link('/index.php',array(
					'menuaction' => 'ranking.ranking.cat_edit' )),*/
				'Athletes' => $GLOBALS['egw']->link('/index.php',array(
					'menuaction' => 'ranking.uiathletes.index' )),
				'Registration' => $GLOBALS['egw']->link('/index.php',array(
					'menuaction' => 'ranking.uiregistration.index' )),
				'Startlists' => $GLOBALS['egw']->link('/index.php',array(
					'menuaction' => 'ranking.uiregistration.startlist' )),
				'Results' => $GLOBALS['egw']->link('/index.php',array(
					'menuaction' => 'ranking.uiregistration.result' )),
				'Ranking' => $GLOBALS['egw']->link('/index.php',array(
					'menuaction' => 'ranking.uiranking.index' )),
			);
			display_sidebox($appname,$GLOBALS['egw_info']['apps']['ranking']['title'].' '.lang('Menu'),$file);
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
	
	function hook_settings()
	{
		$ranking_views = array(
			'ranking.uiranking.index'        => lang('Ranking'),
			'ranking.uiregistration.results' => lang('Results'),
			'ranking.uicompetitions.index'   => lang('Competitions'),
			'ranking.uicups.index'           => lang('Cups'),
		//	'ranking.uicats.index'           => lang('Categories'),
			'ranking.uiathletes.index'       => lang('Athletes'),
			'ranking.uiregistration.index'   => lang('Registration'),
			'ranking.uiregistration.lists'   => lang('Startlists'),
		);
		create_select_box('Default ranking view','default_view',$ranking_views,
			'Which view do you want to see, when you start the ranking app?');
			
		return true;
	}
}