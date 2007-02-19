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

	if (!($view = $GLOBALS['egw']->session->appsession('menuaction','ranking')))
	{
		$view = $GLOBALS['egw_info']['user']['preferences']['ranking']['default_view'];
	}
	ExecMethod($view ? $view : 'ranking.uiranking.index');

	$GLOBALS['egw']->common->egw_footer();
