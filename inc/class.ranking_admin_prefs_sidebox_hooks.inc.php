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
				'Competitions' => $GLOBALS['phpgw']->link('/index.php',array(
					'menuaction' => 'ranking.ranking.competitions' )),
				'Cups' => $GLOBALS['phpgw']->link('/index.php',array(
					'menuaction' => 'ranking.ranking.cup_edit' )),
				'Categories' => $GLOBALS['phpgw']->link('/index.php',array(
					'menuaction' => 'ranking.ranking.cat_edit' )),
			);
			display_sidebox($appname,$GLOBALS['phpgw_info']['apps']['ranking']['title'].' '.lang('Menu'),$file);
		}

		if ($GLOBALS['phpgw_info']['user']['apps']['preferences'] && $location != 'admin')
		{
			$file = array(
				//'Preferences'     => $GLOBALS['phpgw']->link('/preferences/preferences.php','appname='.$appname),
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

		if ($GLOBALS['phpgw_info']['user']['apps']['admin'] && $location != 'preferences')
		{
			$file = Array(
				//'Site configuration' => $GLOBALS['phpgw']->link('/index.php',array('menuaction' => 'ranking.ranking.admin' )),
				'Nation ACL' => $GLOBALS['phpgw']->link('/index.php',array('menuaction' => 'ranking.admin.acl' )),
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
}