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
			if ((int)$_GET['comp'] && (int)$_GET['cat'] && (!is_numeric($_GET['route']) ||
				!($content = $this->route->read($content))))
			{
				// read the 1. Quali (route_order=0), to get the route-type
				$keys['route_order'] = '0';
				if(($content = $this->route->read($keys)))
				{
					$keys['route_order'] = 1+$this->route->get_max_order($_GET['comp'],$_GET['cat']);
					if ($keys['route_order'] == 1 && $content['route_type'] == ONE_QUALI)
					{
						$keys['route_order'] = 2;
					}
					$keys['route_type'] = $content['route_type'];
				}
				$content = $this->route->init($keys);
				$content['new_route'] = true;
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
			$param = array(
				'menuaction' => 'ranking.uiresult.index',
				'comp'  => $content['WetId'],
				'cat'   => $content['GrpId'],
				'route' => $content['route_order'],
				'msg'   => $msg,
			);
			if ($content['new_route'] || $button == 'startlist')
			{
				$param['show_result'] = $content['route_order'] != -1 ? 0 : 2;
			}
			switch($button)
			{
				case 'save':
				case 'apply':
					//_debug_array($content);
					if (!$this->route->save($content) == 0)
					{
						$msg = lang('Error: saving the route!!!');
						$button = $js = '';	// dont exit the window
						break;
					}
					$param['msg'] = $msg = lang('Route saved');

					$js = "opener.location.href='".$GLOBALS['egw']->link('/index.php',$param)."';";
					
					// if route is saved the first time, try getting a startlist (from registration or a previous heat)
					if (!$content['new_route']) break;
					
					unset($content['new_route']);	// no longer new
					$msg .= ', ';
					// fall-throught
				case 'startlist':
					//_debug_array($content);
					if ($this->has_results($content))
					{
						$param['msg'] = ($msg .= lang('Error: route already has a result!!!'));
						$param['show_result'] = 1;
					}
					elseif (is_numeric($content['route_order']) && $this->generate_startlist($comp,$cat,$content['route_order'],$content['route_type']))
					{
						$param['msg'] = ($msg .= lang('Startlist generated'));

						$content['route_status'] = STATUS_STARTLIST;	// set status to startlist
						if ($this->route->read($content)) $this->route->save(array('route_status' => STATUS_STARTLIST));
					}
					else
					{
						$param['msg'] = ($msg .= lang('Error: generating startlist!!!'));
					}
					$js = "opener.location.href='".$GLOBALS['egw']->link('/index.php',$param)."';";
					break;

				case 'delete':
					//_debug_array($content);
					if ($this->route->delete(array(
						'WetId' =>$content['WetId'],
						'GrpId' => $content['GrpId'],
						'route_order' => $content['route_order'])))
					{
						$param['msg'] = lang('Route deleted');
						$js = "opener.location.href='".$GLOBALS['egw']->link('/index.php',$param)."';";
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
		$readonlys['button[delete]'] = $content['new_route'] || $view;
		$readonlys['route_type'] = !!$content['route_order'];	// can only be set in the first route/quali

		$content += ($preserv = array(
			'WetId'       => $comp['WetId'],
			'GrpId'       => $cat['GrpId'],
			'route_order' => $content['route_order'],
		));
		foreach(array('new_route','route_type','route_order') as $name)
		{
			$preserv[$name] = $content[$name];
		}
		$sel_options = array(
			'WetId' => array($comp['WetId'] => strip_tags($comp['name'])),
			'GrpId' => array($cat['GrpId']  => $cat['name']),
			'route_order' => $this->order_nums,
			'route_status' => $this->stati,
			'route_type' => $this->quali_types,
		);
		if ($content['route_order'] == -1)
		{
			unset($sel_options['route_status'][0]);
		}
		// cant delete general result or not yet saved routes
		$readonlys['button[startlist]'] = $readonlys['button[delete]'] = $content['route_order'] == -1 || $content['new_route'];
		
		// no judge rights --> make everything readonly and disable all buttons but cancel
		if (!$this->is_admin && !$this->is_judge($comp))
		{
			foreach($this->route->db_cols as $col)
			{
				$readonlys[$col] = true;
			}
			$readonlys['button[startlist]'] = $readonlys['button[delete]'] = $readonlys['button[save]'] = $readonlys['button[apply]'] = true;
		}
		$GLOBALS['egw_info']['flags']['java_script'] .= '<script>window.focus();</script>';

		//_debug_array($content);
		//_debug_array($sel_options);
		$GLOBALS['egw_info']['flags']['app_header'] = lang('Ranking').' - '.
			($content['new_route'] ? lang('Add route') : lang('Edit route'));
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
		// this is to transport the route_type to route_result::search's filter param
		$query['col_filter']['route_type'] = $query['route_type'];
		
		switch ($query['order'])
		{
			case 'result_rank':
				if ($query['route'] == -1)	// in general result we sort unranked at the end and then as the rest by name
				{
					$query['order'] = 'result_rank IS NULL '.$query['sort'];
				}
				else	// in route-results we want unranked sorted by start_order for easier result-entering
				{
					$query['order'] = 'CASE WHEN result_rank IS NULL THEN start_order ELSE 0 END '.$query['sort'];
				}
				$query['order'] .= ',result_rank '.$query['sort'].',nachname '.$query['sort'].',vorname';
				break;
			case 'result_height':
				$query['order'] = 'CASE WHEN result_height IS NULL THEN -start_order ELSE 0 END '.$query['sort'].
					',result_height '.$query['sort'].',result_plus '.$query['sort'].',nachname '.$query['sort'].',vorname';
				break;
		}
		//echo "<p align=right>order='$query[order]', sort='$query[sort]', start=$query[start]</p>\n";
		$total = $this->route_result->get_rows($query,$rows,$readonlys);
		//echo $total; _debug_array($rows);
		
		foreach($rows as $k => $row)
		{
			if (is_int($k)) $rows['set'][$row['PerId']] = $row;

			if ($row['geb_date']) $rows[$k]['birthyear'] = (int)$row['geb_date'];
			
			if (!$quota_line && $query['route_quota'] && $row['result_rank'] > $query['route_quota'])
			{
				$rows[$k]['quota_class'] = 'quota_line';
				$quota_line = true;
			}
		}
		// show previous heat only if it's counting
		$rows['no_prev_heat'] = $query['route'] < 2+(int)($query['route_type']==TWO_QUALI_HALF);
		
		// which result to show
		$rows['ro_result'] = $query['route_status'] == STATUS_RESULT_OFFICIAL ? '' : 'onlyPrint';
		$rows['rw_result'] = $query['route_status'] == STATUS_RESULT_OFFICIAL ? 'displayNone' : 'noPrint';

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
					'show_result'=> 1,
				);
			}
			if ($_GET['calendar']) $content['nm']['calendar'] = $_GET['calendar'];
			if ($_GET['comp']) $content['nm']['comp'] = $_GET['comp'];
			if ($_GET['cat']) $content['nm']['cat'] = $_GET['cat'];
			if (is_numeric($_GET['route'])) $content['nm']['route'] = $_GET['route'];
			if ($_GET['msg']) $msg = $_GET['msg'];
			if (isset($_GET['show_result'])) $content['nm']['show_result'] = (int)$_GET['show_result'];
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
		if (!$comp || ($comp['nation'] ? $comp['nation'] : 'NULL') != $calendar)
		{
			//echo "<p>calendar changed to '$calendar', comp is '$comp[nation]' not fitting --> reset </p>\n";
			$comp = $cat = false;
			$content['nm']['route'] = '';	// dont show route-selection
		}
		if ($comp && (!($cat = $content['nm']['cat']) || (!($cat = $this->cats->read($cat)) || !in_array($cat['rkey'],$comp['gruppen']))))
		{
			$cat = false;
			$content['nm']['route'] = '';	// dont show route-selection
		}
		$keys = array(
			'WetId' => $comp['WetId'],
			'GrpId' => $cat['GrpId'],
			'route_order' => $content['nm']['route'],
		);
		if ($comp && $cat && ($content['nm']['old_cat'] != $cat['GrpId'] || 			// cat changed or
			!($route = $this->route->read($keys))))	// route not found and no general result
		{
			$content['nm']['route'] = $keys['route_order'] = $this->route->get_max_order($comp['WetId'],$cat['GrpId']);
			$route = $this->route->read($keys);
		}
		//echo "<p>calendar='$calendar', comp={$content['nm']['comp']}=$comp[rkey]: $comp[name], cat=$cat[rkey]: $cat[name], route={$content['nm']['route']}</p>\n";
		
		// check if user pressed a button and react on it
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
					else
					{
						$msg = lang('Nothing to update');
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
			'result_plus' => $this->plus_labels,
			'show_result' => array(
				0 => lang('Startlist'),
				1 => lang('Resultlist'),
			),
		);
		if (count($sel_options['route']) > 1)	// more then 1 heat --> include a general result
		{
			$label =  isset($sel_options['route'][-1]) ? $sel_options['route'][-1] : lang('General result');
			unset($sel_options['route'][-1]);
			$sel_options['route'] = array(-1 => $label)+$sel_options['route'];
			$sel_options['show_result'][2] = lang('General result');
		}
		elseif ($content['nm']['route'] == -1)	// general result with only one heat --> show quali if exist
		{
			$keys['route_order'] = $content['nm']['route'] = '0';
			if (!($route = $this->route->read($keys))) $keys['route_order'] = $content['nm']['route'] = '';
		}
		//_debug_array($sel_options);

		if (is_array($route)) $content += $route;
		$content['nm']['calendar'] = $calendar;
		$content['nm']['comp']     = $comp ? $comp['WetId'] : null;
		$content['nm']['cat']      = $content['nm']['old_cat'] = $cat ? $cat['GrpId'] : null;
		$content['nm']['route_type'] = $route['route_type'];
		$content['nm']['route_status'] = $route['route_status'];
		
		// make competition and category data availible for print
		$content['comp'] = $comp;
		$content['cat']  = $cat;

		$content['msg'] = $msg;
		
		// no startlist, no rights at all or result offical -->disable all update possebilities
		if (($readonlys['button[apply]'] = !$this->has_startlist($keys) || 
			!($this->is_admin || $this->is_judge($comp)) || $route['route_status'] == STATUS_RESULT_OFFICIAL))
		{
			$readonlys['nm'] = true;
			$sel_options['result_plus'] = $this->plus;
		}
		// check if the type of the list to show changed: startlist, result or general result
		// --> set template and default order
		if (($content['nm']['route'] == -1) !== ($content['nm']['show_result'] == 2))
		{
			if ($content['nm']['show_result'] == 2 && $content['nm']['old_show'] != 2)
			{
				$content['nm']['route'] = -1;
			}
			else
			{
				$content['nm']['show_result'] = $content['nm']['route'] == -1 ? 2 : 1;
			}		
		}
		if ($content['nm']['route'] == -1)	// general result --> hide show_route selection
		{
			$sel_options['show_result'] = array(-1 => '');
			$readonlys['nm[show_result]'] = true;
		}
		if ($content['nm']['old_show'] != $content['nm']['show_result'])
		{
			if ($content['nm']['route'] == -1)	// general result
			{
				$content['nm']['template'] = 'ranking.result.index.rows_general';
				$content['nm']['order'] = 'result_rank';
			}
			else
			{
				$content['nm']['template'] = $content['nm']['show_result'] ? 'ranking.result.index.rows_lead' : 'ranking.result.index.rows_startlist';
				$content['nm']['order'] = $content['nm']['show_result'] ? 'result_rank' : 'start_order';
			}
			$content['nm']['sort'] = 'ASC';
			$content['nm']['old_show'] = $content['nm']['show_result'];
		}
		// quota, to get a quota line for _official_ result-lists --> get_rows sets css-class quota_line on the table-row _below_
		$content['nm']['route_quota'] = $content['nm']['show_result'] && $route['route_status'] == STATUS_RESULT_OFFICIAL ? $route['route_quota'] : 0;
		
		// should we show the result offical footer?
		$content['result_official'] = $content['nm']['show_result'] && $route['route_status'] == STATUS_RESULT_OFFICIAL;

		// create a nice header
		$GLOBALS['egw_info']['flags']['app_header'] = /*lang('Ranking').' - '.*/(!$comp || !$cat ? lang('Resultservice') : 
			($content['nm']['show_result'] == '0' && $route['route_status'] == STATUS_UNPUBLISHED || 
			 $content['nm']['show_result'] != '0' && $route['route_status'] != STATUS_RESULT_OFFICIAL ? lang('provisional').' ' : '').
			(isset($sel_options['show_result'][(int)$content['nm']['show_result']]) ? $sel_options['show_result'][(int)$content['nm']['show_result']].' ' : '').
			($cat ? (isset($sel_options['route'][$content['nm']['route']]) ? $sel_options['route'][$content['nm']['route']].' ' : '').$cat['name'] : ''));
		$tmpl->exec('ranking.uiresult.index',$content,$sel_options,$readonlys,array('nm' => $content['nm']));
	}
}
