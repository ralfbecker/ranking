<?php
/**************************************************************************\
* eGroupWare - digital ROCK Rankings - result UI                           *
* http://www.egroupware.org, http://www.digitalROCK.de                     *
* Written and (c) by Ralf Becker <RalfBecker@outdoor-training.de>          *
* --------------------------------------------                             *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

require_once(EGW_INCLUDE_ROOT.'/ranking/inc/class.boranking.inc.php');
require_once(EGW_INCLUDE_ROOT.'/etemplate/inc/class.uietemplate.inc.php');

class uiresult extends boranking 
{
	/**
	 * @var array $public_functions functions callable via menuaction
	 */
	var $public_functions = array(
		'index' => true,
		'route' => true,
	);
	/**
	 * values and labels for route_order
	 *
	 * @var array
	 */
	var $order_nums;
	/**
	 * values and labels for route_status
	 *
	 * @var unknown_type
	 */
	var $stati = array(
		0 => 'unpublished',
		1 => 'startlist',
		2 => 'provisional result',
		3 => 'offical result',
	);

	function uiresult()
	{
		$this->boranking();
		
		$this->order_nums = array(
			0 => lang('Qualification'),
			1 => lang('2. Qualification'),
		);
		for($i = 2; $i <=5; ++$i)
		{
			$this->order_nums[$i] = lang('%1. Heat',$i);
		}
	}

	/**
	 * Edit or add a route / heat
	 *
	 * @param array $content=null
	 * @param string $msg=''
	 */
	function route($content=null,$msg='')
	{
		$tmpl = new etemplate('ranking.result.route');
		
		if (!is_array($content))
		{
			$content = array(
				'WetId' => $_GET['comp'],
				'GrpId' => $_GET['cat'],
				'route_order' => $_GET['route'],
			);
			if ((int)$_GET['comp'] && (int)$_GET['cat'] && is_numeric($_GET['route']))
			{
				$content = $this->route->read($content);
			}
		}
		if (!is_array($content) ||
			!($comp = $this->comp->read($content['WetId'])) ||
			!($cat = $this->cats->read($content['GrpId'])) ||
			!in_array($cat['rkey'],$comp['gruppen']))
		{
			$msg = lang('Permission denied !!!');
			$js = "alert('".addslashes($msg)."'); window.close();";
			$GLOBALS['egw']->common->egw_header();
			echo "<html><head><script>".$js."</script></head></html>\n";
			$GLOBALS['egw']->common->egw_exit();
		}
		// check if user has NO edit rights
		if (($view = !$this->is_admin && !$this->is_judge($comp)))
		{
			foreach($content as $name => $value)
			{
				$readonlys[$name] = true;
			}
			$readonlys['button[save]'] = $readonlys['button[apply]'] = true;
		}
		elseif ($content['button'])
		{
			list($button) = each($content['button']);
			unset($content['button']);
			
			// reload the parent window
			$js = "opener.location.href='".$GLOBALS['egw']->link('/index.php',array(
				'menuaction' => 'ranking.uiresult.index',
				'comp'  => $content['WetId'],
				'cat'   => $content['GrpId'],
				'route' => $content['route_order'],
				'msg'   => $msg,
			))."';";
			switch($button)
			{
				case 'save':
				case 'apply':
					if (!is_numeric($content['route_order'])) $content['route_order'] = $content['route'];
					_debug_array($content);
					if ($this->route->save($content) == 0)
					{
						$msg = lang('Route saved');
						$js = "opener.location.href='".$GLOBALS['egw']->link('/index.php',array(
							'menuaction' => 'ranking.uiresult.index',
							'comp'  => $content['WetId'],
							'cat'   => $content['GrpId'],
							'route' => $content['route_order'],
							'msg'   => $msg,
						))."';";
					}
					else
					{
						$msg = lang('Error saving the route!!!');
						$button = $js = '';	// dont exit the window
					}
					break;

				case 'delete':
					_debug_array($content);
					if ($this->route->delete(array(
						'WetId' =>$content['WetId'],
						'GrpId' => $content['GrpId'],
						'route_order' => $content['route_order'])))
					{
						$msg = lang('Route delete');
					}
					else
					{
						$msg = lang('Error delting the route!!!');
						$js = $button = '';	// dont exit the window
					}
					break;
			}
			if (in_array($button,array('save','delete')))	// close the popup and refresh the parent
			{
				$js .= 'window.close();';
				echo "<html><head><script>".$js."</script></head></html>\n";
				$GLOBALS['egw']->common->egw_exit();
			}
		}
		$content += array(
			'msg' => $msg,
			'js'  => $js ? "<script>$js</script>" : '',
		);
		$readonlys['button[delete]'] = is_null($content['route_order']) || $view;
		$content += ($preserv = array(
			'WetId'       => $comp['WetId'],
			'GrpId'       => $cat['GrpId'],
			'route_order' => $content['route_order'],
		));

		$sel_options = array(
			'WetId' => array($comp['WetId'] => strip_tags($comp['name'])),
			'GrpId' => array($cat['GrpId']  => $cat['name']),
			'route' => $this->order_nums,
			'route_status' => $this->stati,
		);
		if (is_numeric($content['route_order']))	// already selected
		{
			$readonlys['route'] = true;
			$preserv['route'] = $content['route'] = $content['route_order'];
		}
		elseif (!is_numeric($content['route']))
		{
			$max = $this->route->get_max_order($comp['WetId'],$cat['GrpId']);
			$content['route'] = is_null($max) ? 0 : 1 + $max;
			if ($content['route'] == 1)
			{
				$content['route'] = 2;	// show 2. heat, but allow to select 2. quali
				_debug_array($sel_options['route']);
				$sel_options['route'] = array_intersect_key($sel_options['route'],array(1=>1,2=>2));	// only 2. quali or 2. hear
				_debug_array($sel_options['route']);
			}
			else
			{
				$readonlys['route'] = true;	// no selection possible
				$preserv['route'] = $content['route'];
			}
			echo "<p>max=$max, route=$content[route], r/o=$readonlys[route]</p>\n";
		}
		_debug_array($content);
		//_debug_array($sel_options);
		$GLOBALS['egw_info']['flags']['app_header'] = lang('Ranking').' - '.($content['route'] ? lang('Edit route') : lang('Add route'));
		$tmpl->exec('ranking.uiresult.route',$content,$sel_options,$readonlys,$preserv,2);
	}

	/**
	 * query the start or result list
	 *
	 * @param array $query
	 * @param array &$rows returned rows/cups
	 * @param array &$readonlys eg. to disable buttons based on acl
	 */
	function get_rows($query,&$rows,&$readonlys)
	{
		$GLOBALS['egw']->session->appsession('ranking','result_state',$query);

		return $total;		
	}

	/**
	 * Show a result / startlist
	 *
	 * @param array $content=null
	 * @param string $msg=''
	 */
	function index($content=null,$msg='')
	{
		$tmpl =& new etemplate('ranking.result.index');
		
		//_debug_array($content);
		if (!is_array($content))
		{
			$content = $GLOBALS['egw']->session->appsession('result','ranking');
			if (!$content) $content = array();
			
			if ($_GET['calendar']) $content['calendar'] = $_GET['calendar'];
			if ($_GET['comp']) $content['comp'] = $_GET['comp'];
			if ($_GET['cat']) $content['cat'] = $_GET['cat'];
			if ($_GET['route']) $content['route'] = $_GET['route'];
			if ($_GET['msg']) $msg = $_GET['msg'];
		}
		if($content['comp']) $comp = $this->comp->read($content['comp']);

		if ($tmpl->sitemgr && $_GET['comp'] && $comp)	// no calendar and/or competition selection, if in sitemgr the comp is direct specified
		{
			$readonlys['calendar'] = $readonlys['comp'] = true;
		}
		if ($this->only_nation)
		{
			$calendar = $this->only_nation;
			$tmpl->disable_cells('calendar');
		}
		elseif ($comp)
		{
			$calendar = $comp['nation'] ? $comp['nation'] : 'NULL';
		}
		elseif ($content['calendar'])
		{
			$calendar = $content['calendar'];
		}
		else
		{
			list($calendar) = each($this->ranking_nations);
		}
		if ($comp && ($cat = $content['cat']) && (!($cat = $this->cats->read($cat)) || !in_array($cat['rkey'],$comp['gruppen'])))
		{
			$cat = '';
			//$msg = lang('Unknown category or not a category of this competition');
		}
		
		$sel_options = array(
			'calendar' => $this->ranking_nations,
			'comp'     => $this->comp->names(array(
				'nation' => $calendar,
				'datum >= '.$this->db->quote(date('Y-m-d',time())),
				'gruppen IS NOT NULL',
			),0,'datum ASC'),
			'cat'      => $this->cats->names(array('rkey' => $comp['gruppen']),0),
			'route'    => $comp && $cat ? $this->route->query_list('route_name','route_order',array(
				'WetId' => $comp['WetId'],
				'GrpId' => $cat['GrpId'],
			),'route_order') : array(),
		);
		$preserv = $content = array(
			'calendar' => $calendar,
			'comp'     => $comp ? $comp['WetId'] : null,
			'cat'      => $cat ? $cat['GrpId'] : null,
			'route'    => $content['route'],
		);
		$content['msg'] = $msg;
		//_debug_array($content);
		$GLOBALS['egw']->session->appsession('result','ranking',$preserv);

		$GLOBALS['egw_info']['flags']['app_header'] = lang('Ranking').' - '.lang('Resultservice');
		$tmpl->exec('ranking.uiresult.index',$content,$sel_options,$readonlys,$preserv);
	}
}
