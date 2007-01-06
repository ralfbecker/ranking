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

require_once(EGW_INCLUDE_ROOT.'/ranking/inc/class.boresult.inc.php');
require_once(EGW_INCLUDE_ROOT.'/etemplate/inc/class.uietemplate.inc.php');

class uiresult extends boresult
{
	/**
	 * @var array $public_functions functions callable via menuaction
	 */
	var $public_functions = array(
		'index' => true,
		'route' => true,
	);

	function uiresult()
	{
		$this->boresult();
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
			$content = $keys = array(
				'WetId' => $_GET['comp'],
				'GrpId' => $_GET['cat'],
				'route_order' => $_GET['route'],
			);
			if ((int)$_GET['comp'] && (int)$_GET['cat'] && is_numeric($_GET['route']) &&
				!($content = $this->route->read($content)))
			{
				unset($keys['route_order']);
				$content = $this->route->init($keys);
			}
		}
		if (!($comp = $this->comp->read($content['WetId'])) ||
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
				'route' => !is_numeric($content['route_order']) ? $content['route'] : $content['route_order'],
				'msg'   => $msg,
			))."';";
			switch($button)
			{
				case 'save':
				case 'apply':
					//_debug_array($content);
					if (($new_route = !is_numeric($content['route_order']))) $content['route_order'] = $content['route'];
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
						
						// if route is saved the first time, try getting a startlist (from registration or a previous heat)
						if ($new_route) $this->generate_startlist($comp,$cat,$content['route_order']);
					}
					else
					{
						$msg = lang('Error: saving the route!!!');
						$button = $js = '';	// dont exit the window
					}
					break;

				case 'delete':
					//_debug_array($content);
					if ($this->route->delete(array(
						'WetId' =>$content['WetId'],
						'GrpId' => $content['GrpId'],
						'route_order' => $content['route_order'])))
					{
						$msg = lang('Route deleted');
					}
					else
					{
						$msg = lang('Error: deleting the route!!!');
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
				$sel_options['route'] = array_intersect_key($sel_options['route'],array(1=>1,2=>2));	// only 2. quali or 2. hear
			}
			else
			{
				$readonlys['route'] = true;	// no selection possible
				$preserv['route'] = $content['route'];
			}
			//echo "<p>max=$max, route=$content[route], r/o=$readonlys[route]</p>\n";
		}
		//_debug_array($content);
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
		//echo "<p>uiresult::get_rows(".print_r($query,true).",,)</p>\n";
		unset($query['rows']);
		$GLOBALS['egw']->session->appsession('result','ranking',$query);
		
		$query['col_filter']['WetId'] = $query['comp'];
		$query['col_filter']['GrpId'] = $query['cat'];
		$query['col_filter']['route_order'] = $query['route'];
		
		switch ($query['order'])
		{
			case 'result_rank':
				$query['order'] = 'result_rank IS NULL '.$query['sort'].',result_rank '.$query['sort'].',start_order';
				break;
			case 'result_height':
				$query['order'] = 'result_height '.$query['sort'].',result_plus '.$query['sort'].',start_order';
				$query['sort'] = 'ASC';
				break;
		}
		//echo "<p align=right>order='$query[order]', sort='$query[sort]', start=$query[start]</p>\n";
		$total = $this->route_result->get_rows($query,$rows,$readonlys);
		//echo $total; _debug_array($rows);		
		foreach($rows as $k => $row)
		{
			if (is_int($k)) $rows['set'][$row['PerId']] = $row;

			if ($row['geb_date']) $rows[$k]['birthyear'] = (int)$row['geb_date'];
		}
		$rows['no_prev_heat'] = $query['route'] < 2;

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
			$content = array('nm' => $GLOBALS['egw']->session->appsession('result','ranking'));
			if (!is_array($content['nm']))
			{
				$content['nm'] = array(
					'get_rows'   => 'ranking.uiresult.get_rows',
					'no_cat'     => true,
					'no_filter'  => true,
					'no_filter2' => true,
					'num_rows'   => 1000,
					'order'      => 'start_order',
					'sort'       => 'ASC',
				);
			}
			if ($_GET['calendar']) $content['nm']['calendar'] = $_GET['calendar'];
			if ($_GET['comp']) $content['nm']['comp'] = $_GET['comp'];
			if ($_GET['cat']) $content['nm']['cat'] = $_GET['cat'];
			if (is_numeric($_GET['route'])) $content['nm']['route'] = $_GET['route'];
			if ($_GET['msg']) $msg = $_GET['msg'];
		}

		if($content['nm']['comp']) $comp = $this->comp->read($content['nm']['comp']);
		//echo "<p>calendar='$calendar', comp={$content['nm']['comp']}=$comp[rkey]: $comp[name]</p>\n";
		if ($tmpl->sitemgr && $_GET['comp'] && $comp)	// no calendar and/or competition selection, if in sitemgr the comp is direct specified
		{
			$readonlys['nm[calendar]'] = $readonlys['nm[comp]'] = true;
		}
		if ($this->only_nation)
		{
			$calendar = $this->only_nation;
			$tmpl->disable_cells('nm[calendar]');
		}
		elseif ($comp && !$content['nm']['calendar'])
		{
			$calendar = $comp['nation'] ? $comp['nation'] : 'NULL';
		}
		elseif ($content['nm']['calendar'])
		{
			$calendar = $content['nm']['calendar'];
		}
		else
		{
			list($calendar) = each($this->ranking_nations);
		}
		if ($comp && ($comp['nation'] ? $comp['nation'] : 'NULL') != $calendar)
		{
			//echo "<p>calendar changed to '$calendar', comp is '$comp[nation]' not fitting --> reset </p>\n";
			$comp = false;
		}
		if ($comp && ($cat = $content['nm']['cat']) && (!($cat = $this->cats->read($cat)) || !in_array($cat['rkey'],$comp['gruppen'])))
		{
			$cat = false;
			//$msg = lang('Unknown category or not a category of this competition');
		}
		$keys = array(
			'WetId' => $comp['WetId'],
			'GrpId' => $cat['GrpId'],
			'route_order' => $content['nm']['route'],
		);
		if ($comp && $cat && ($content['nm']['old_cat'] != $cat['GrpId'] || !$this->route->read($keys)))	// route not found
		{
			$keys['route_order'] = $content['nm']['route'] = $this->route->get_max_order($comp['WetId'],$cat['GrpId']);
		}
		//echo "<p>calendar='$calendar', comp={$content['nm']['comp']}=$comp[rkey]: $comp[name], cat=$cat[rkey]: $cat[name], route={$content['nm']['route']}</p>\n";
		// check if user pressed a button
		list($button) = @each($content['button']);
		unset($content['button']);
		
		if ($button && $comp && $cat && is_numeric($content['nm']['route']))
		{
			//echo "<p align=right>$comp[rkey] ($comp[WetId]), $cat[rkey]/$cat[GrpId], {$content['nm']['route']}, button=$button</p>\n";
			switch($button)
			{
				case 'apply':
					if (is_array($content['nm']['rows']['set']) && $this->save_result($keys,$content['nm']['rows']['set']))
					{
						$msg = lang('Route updated');
					}
					break;
					
				case 'startlist':
					if (!$this->has_results($keys) && $this->generate_startlist($comp,$cat,$content['nm']['route']))
					{
						$msg = lang('Startlist generated');
					}
					break;
			}
		}
		unset($content['nm']['rows']);
		
		// create new view
		$sel_options = array(
			'calendar' => $this->ranking_nations,
			'comp'     => $this->comp->names(array(
				'nation' => $calendar,
				'datum >= '.$this->db->quote(date('Y-m-d',time()-2*30*24*3600)),
				'gruppen IS NOT NULL',
			),0,'datum ASC'),
			'cat'      => $this->cats->names(array('rkey' => $comp['gruppen']),0),
			'route'    => $comp && $cat ? $this->route->query_list('route_name','route_order',array(
				'WetId' => $comp['WetId'],
				'GrpId' => $cat['GrpId'],
			),'route_order DESC') : array(),
			'result_plus' => $this->plus,
			'show_result' => array(1=>'Resultlist'),
		);
		//_debug_array($sel_options);
		$content['nm']['calendar'] = $calendar;
		$content['nm']['comp']     = $comp ? $comp['WetId'] : null;
		$content['nm']['cat']      = $content['nm']['old_cat'] = $cat ? $cat['GrpId'] : null;

		$content['msg'] = $msg;
		
		$readonlys['button[startlist]'] = !$comp || !$cat || !is_numeric($content['nm']['route']) || $this->has_results($keys);
		$readonlys['button[apply]'] = !$this->has_startlist($keys);
		
		if ($content['nm']['old_show'] != $content['nm']['show_result'])
		{
			$content['nm']['template'] = $content['nm']['show_result'] ? 'ranking.result.index.rows_lead' : 'ranking.result.index.rows_startlist';
			$content['nm']['order'] = $content['nm']['show_result'] ? 'result_rank' : 'start_order';
			$content['nm']['sort'] = 'ASC';
			$content['nm']['old_show'] = $content['nm']['show_result'];
		}
		$GLOBALS['egw']->session->appsession('result','ranking',$content['nm']);

		$GLOBALS['egw_info']['flags']['app_header'] = lang('Ranking').' - '.lang('Resultservice');
		$tmpl->exec('ranking.uiresult.index',$content,$sel_options,$readonlys,array('nm' => $content['nm']));
	}
}
