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

if (!defined('DR_PATH'))
{
	define('DR_PATH',realpath(strstr($_SERVER['DOCUMENT_ROOT'],'uiaaclimbing.com') ? $_SERVER['DOCUMENT_ROOT'].'/../digitalrock.de' : $_SERVER['DOCUMENT_ROOT']));
}
class module_ranking_digitalrock extends Module 
{
	function module_ranking_digitalrock()
	{
		$this->arguments = array(
			'type' => array(
				'type' => 'select', 
				'label' => lang('Type of display'),
				'options' => array(
					'calendar' => lang('Competition calendar'),
					'result' => lang('Result (latest result, result of all cats or result of one cat)'),
					'pstambl' => lang('Personal profile'),
					'ranglist' => lang('Ranglist'),
					'nat_team_ranking' => lang('National team ranking'),
				),
			),
			'nation' => array(
				'type' => 'textfield',
				'label' => lang('Nation to use (empty = international)'),
			),	
			'cat' => array(
				'type' => 'textfield',
				'label' => lang('Key of the category'),
			),	
			'cup' => array(
				'type' => 'textfield',
				'label' => lang('Key of the cup'),
			),	
			'comp' => array(
				'type' => 'textfield',
				'label' => lang('Key of the competition'),
			),	
			'person' => array(
				'type' => 'textfield',
				'label' => lang('Key of the Person'),
			),	
			'year' => array(
				'type' => 'textfield',
				'label' => lang('Year of the calendar (empty for current)'),
			),	
		);
		$this->title = lang('digital ROCK');
		$this->description = lang('This module displays calendar, results and rankings');
	}
	
	function get_content(&$arguments,$properties) 
	{
		foreach($arguments as $name => $value)
		{
			$GLOBALS['dr_config']['params'][$name] = isset($_REQUEST[$name]) ? $_REQUEST[$name] : $arguments[$name];
		}
		if (empty($arguments['type'])) $arguments['type'] = $_GET['pagename'];
		if (!preg_match('/^[a-z_]+$/i',$arguments['type'])) $arguments['type'] = 'calendar';
		$file = $arguments['type'];
		switch($file)
		{
			case 'calendar':
				$file = (empty($arguments['nation']) ? 'icc' : strtolower($arguments['nation'])).'_calendar';
				break;
				
			case 'result':
				if (!$GLOBALS['dr_config']['params']['comp'])
				{
					$file = 'latest';
				}
				elseif (!$GLOBALS['dr_config']['params']['cat'])
				{
					$file = 'all_result';
				}
				break;
				
			case 'ranglist':
				if (!$GLOBALS['dr_config']['params']['cat'])
				{
					$_GET['mode'] = 2;
					$file = 'icc_calendar';
				}
				break;
				
			case 'pstambl':
				if (!$GLOBALS['dr_config']['params']['person'])
				{
					return '';	// otherwise we get a fatal error
				}
				break;
				
			case 'nat_team_ranking':
				if (!$GLOBALS['dr_config']['params']['comp'] && !$GLOBALS['dr_config']['params']['cup'])
				{
					return '';	// otherwise we get a fatal error
				}
				break;
		}
		if (!file_exists($file = DR_PATH.'/'.$file.'.php'))
		{
			return lang('File %1 not found (either type=%2 or DR_PATH=%3 wrong)',$file,$arguments['type'],DR_PATH);
		}
		ob_start();
		include($file);
		$content = ob_get_contents();
		ob_end_clean();
		
		//$content = preg_replace('/<html>.*<body>(.*)<\\/body>.*<\\/html>/i','\\1',$content);
		
		return $GLOBALS['egw']->translation->convert($content,'iso-8859-1');
	}
}
