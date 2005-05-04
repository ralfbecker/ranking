<?php
	/**************************************************************************\
	* phpGroupWare - Rankings                                                  *
	* http://www.phpgroupware.org                                              *
	* Written by Ralf Becker <RalfBecker@outdoor-training.de>                  *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	$GLOBALS['egw_info'] = array(
		'flags' => array(
			'currentapp'	=> 'ranking',
			'noheader'		=> True,
			'nonavbar'		=> True
	));
	include('../header.inc.php');

	$default_view = $GLOBALS['egw_info']['user']['preferences']['ranking']['default_view'];
	ExecMethod($default_view ? $default_view : 'ranking.uiranking.index');

	$GLOBALS['phpgw']->common->phpgw_footer();
