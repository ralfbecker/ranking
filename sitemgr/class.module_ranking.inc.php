<?php
/**************************************************************************\
* eGroupWare - digital ROCK Rankings - Registration and startlists         *
* http://www.egroupware.org, http://www.digitalROCK.de                     *
* Written and (c) by Ralf Becker <RalfBecker@outdoor-training.de>          *
* --------------------------------------------                             *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

require_once(EGW_INCLUDE_ROOT.'/etemplate/inc/class.sitemgr_module.inc.php');

class module_ranking extends sitemgr_module  
{
	function module_ranking()
	{
		$this->title = lang('Ranking');
		$this->description = lang('This module displays information from the ranking app.');
		
		$this->etemplate_method = 'ranking.uiregistration.lists';
	}
}
