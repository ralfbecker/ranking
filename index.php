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

	$GLOBALS['phpgw_info']['flags'] = array(
		'currentapp'	=> 'ranking',
		'noheader'		=> True,
		'nonavbar'		=> True
	);
	include('../header.inc.php');

	$ranking = CreateObject('ranking.ranking');

	$ranking->start();

	$GLOBALS['phpgw']->common->phpgw_footer();
?>