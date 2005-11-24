<?php
	/**************************************************************************\
	* phpGroupWare - Editable Templates                                        *
	* http://www.phpgroupware.org                                              *
	" Written by Ralf Becker <RalfBecker@outdoor-training.de>                  *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	$setup_info['ranking']['name']      = 'ranking';
	$setup_info['ranking']['version']   = '1.0.0.011';
	$setup_info['ranking']['app_order'] = 1;
	$setup_info['ranking']['tables']    = array('Wettkaempfe','Serien','Gruppen','RangListenSysteme','PktSysteme','Results','Feldfaktoren','PktSystemPkte','Gruppen2Personen','Personen');
	$setup_info['ranking']['enable']    = 1;

	/* The hooks this app includes, needed for hooks registration */
	$setup_info['ranking']['hooks']['preferences'] = 'ranking.ranking_admin_prefs_sidebox_hooks.all_hooks';
	$setup_info['ranking']['hooks']['settings'] = 'ranking.ranking_admin_prefs_sidebox_hooks.hook_settings';
	$setup_info['ranking']['hooks']['admin'] = 'ranking.ranking_admin_prefs_sidebox_hooks.all_hooks';
	$setup_info['ranking']['hooks']['sidebox_menu'] = 'ranking.ranking_admin_prefs_sidebox_hooks.all_hooks';

	/* Dependacies for this app to work */
	$setup_info['ranking']['depends'][] = array(
		 'appname' => 'phpgwapi',
		 'versions' => Array('1.0.0','1.0.1','1.2')
	);
	$setup_info['ranking']['depends'][] = array(
		'appname' => 'etemplate',
		'versions' => Array('1.0.0','1.0.1','1.2')
	);









