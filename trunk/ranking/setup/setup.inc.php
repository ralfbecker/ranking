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
	$setup_info['ranking']['version']   = '0.9.13.001';
	$setup_info['ranking']['app_order'] = 1;
	$setup_info['ranking']['tables']    = array('rang.Wettkaempfe','rang.Serien','rang.Gruppen','rang.Personen','rang.Results','rang.RangListenSysteme','rang.PktSysteme','rang.Feldfaktoren','rang.Gruppen2Personen','rang.PktSystemPkte');
	$setup_info['ranking']['enable']    = 1;

	/* The hooks this app includes, needed for hooks registration */
	$setup_info['ranking']['hooks']['preferences'] = 'ranking.admin_prefs_sidebox_hooks.all_hooks';
	$setup_info['ranking']['hooks']['admin'] = 'ranking.admin_prefs_sidebox_hooks.all_hooks';
	$setup_info['ranking']['hooks']['sidebox_menu'] = 'ranking.admin_prefs_sidebox_hooks.all_hooks';

	/* Dependacies for this app to work */
	$setup_info['ranking']['depends'][] = array(
		 'appname' => 'phpgwapi',
		 'versions' => Array('0.9.13','0.9.14','0.9.15','1.0.0','1.0.1')
	);
    $setup_info['ranking']['depends'][] = array(   // this is only necessary as long the etemplate-class is not in the api
             'appname' => 'etemplate',
             'versions' => Array('0.9.13','0.9.14','0.9.15','1.0.0','1.0.1')
    );