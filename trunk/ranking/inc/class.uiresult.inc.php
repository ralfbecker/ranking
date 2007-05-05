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
		
		if (!($comp = $this->comp->read($_GET['comp'] ? $_GET['comp'] : $content['WetId'])) ||
			!($cat = $this->cats->read($_GET['cat'] ? $_GET['cat'] : $content['GrpId'])) ||
			!in_array($cat['rkey'],$comp['gruppen']))
		{
			$msg = lang('Permission denied !!!');
			$js = "alert('".addslashes($msg)."'); window.close();";
			$GLOBALS['egw']->common->egw_header();
			echo "<html><head><script>".$js."</script></head></html>\n";
			$GLOBALS['egw']->common->egw_exit();
		}
		$discipline = $comp['discipline'] ? $comp['discipline'] : $cat['discipline'];

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
				// try reading the previous heat, to set some stuff from it
				if (($keys['route_order'] = $this->route->get_max_order($_GET['comp'],$_GET['cat'])) >= 0 &&
					($previous = $this->route->read($prev_keys=$keys)))
				{
					++$keys['route_order'];
					if ($keys['route_order'] == 1 && $previous['route_type'] == ONE_QUALI)
					{
						$keys['route_order'] = 2;
					}
					$keys['route_type'] = $previous['route_type'];
				}
				else
				{
					$keys['route_order'] = '0';
				}
				$keys['route_name'] = $keys['route_order'] >= 2 ? lang('Final') : 
					($keys['route_order'] == 1 ? '2. ' : '').lang('Qualification');

				if ($discipline != 'speed') 
				{
					$keys['route_quota'] = $this->default_quota($discipline,$keys['route_order']);
				}
				elseif ($previous && $previous['route_quota'] > 2)
				{
					$keys['route_quota'] = $previous['route_quota'] / 2;
				}
				if ($previous && $previous['route_judge'])
				{
					$keys['route_judge'] = $previous['route_judge'];
				}
				else	// set judges from the competition
				{
					$keys['route_judge'] = array();
					foreach($comp['judges'] as $uid)
					{
						$keys['route_judge'][] = $GLOBALS['egw']->common->grab_owner_name($uid);
					}
					$keys['route_judge'] = implode(', ',$keys['route_judge']);
				}
				
				$content = $this->route->init($keys);
				$content['new_route'] = true;
			}
		}
		// check if user has NO edit rights
		if (($view = !$this->acl_check($comp['nation'],EGW_ACL_RESULT,$comp)))
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
					if ($this->route->save($content) != 0)
					{
						$msg = lang('Error: saving the route!!!');
						$button = $js = '';	// dont exit the window
						break;
					}
					$param['msg'] = $msg = lang('Heat saved');

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
						$param['msg'] = ($msg .= lang('Error: heat already has a result!!!'));
						$param['show_result'] = 1;
					}
					elseif (is_numeric($content['route_order']) && 
						($num = $this->generate_startlist($comp,$cat,$content['route_order'],$content['route_type'],$content['discipline'],
							$content['max_compl']!=='' ? $content['max_compl'] : 999)))
					{
						$param['msg'] = ($msg .= lang('Startlist generated'));
						
						$to_set = array();
						$to_set['route_status'] = $content['route_status'] = STATUS_STARTLIST;	// set status to startlist
						if (!$content['route_quota'])
						{
							$content['route_quota'] = $to_set['route_quota'] = 
								$this->default_quota($discipline,$content['route_order'],$content['quali_type'],$num);
						}
						if ($this->route->read($content)) $this->route->save($to_set);
					}
					else
					{
						$param['msg'] = ($msg .= lang('Error: generating startlist!!!'));
					}
					$js = "opener.location.href='".$GLOBALS['egw']->link('/index.php',$param)."';";
					break;

				case 'delete':
					//_debug_array($content);
					if ($content['route_order'] < $this->route->get_max_order($content['WetId'],$content['GrpId']))
					{
						$msg = lang('You can only delete the last heat, not one in between!');
						$js = $button = '';	// dont exit the window
					}
					elseif ($this->route->delete(array(
						'WetId' => $content['WetId'],
						'GrpId' => $content['GrpId'],
						'route_order' => $content['route_order'])))
					{
						$param['msg'] = lang('Heat deleted');
						$js = "opener.location.href='".$GLOBALS['egw']->link('/index.php',$param)."';";
					}
					else
					{
						$msg = lang('Error: deleting the heat!!!');
						$js = $button = '';	// dont exit the window
					}
					break;
					
				case 'upload':
					if ($content['new_route'])
					{
						if ($this->route->save($content) != 0)
						{
							$msg = lang('Error: saving the heat!!!');
							$button = $js = '';	// dont exit the window
							break;
						}
						$param['msg'] = $msg = lang('Heat saved').', ';
						unset($content['new_route']);
					}
					if ($this->has_results($content))
					{
						$param['msg'] = $msg = lang('Error: route already has a result!!!');
						$param['show_result'] = 1;
					}
					elseif (!$content['file']['tmp_name'] || !is_uploaded_file($content['file']['tmp_name']))
					{
						$param['msg'] = ($msg .= lang('Error: no file to upload selected'));
					}
					elseif (is_numeric($imported = $this->upload($content,$content['file']['tmp_name'])))
					{
						$param['msg'] = ($msg .= lang('%1 participants imported',$imported));
						$js = "opener.location.href='".$GLOBALS['egw']->link('/index.php',$param)."';";
					}
					else
					{
						$param['msg'] = ($msg .= $imported);
					}
					break;
					
				case 'ranking':
					$param['msg'] = $msg = $this->import_ranking($content);
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
			'discipline'  => $discipline,
		));
		if ($discipline != 'boulder')
		{
			$tmpl->disable_cells('route_num_problems');
		}
		if ($previous)	// previous heat of a NEW route
		{
			if (!$previous['route_quota'] && ($content['discipline'] != 'speed' || $content['route_order'] <= 2))
			{
				$content['msg'] = lang('No quota set in the previous heat!!!');
			}
			elseif ($content['discipline'] == 'speed')
			{
				$content['route_quota'] = $previous['route_quota'] / 2;
				if ($content['route_quota'] > 1)
				{
					$content['route_name'] = '1/'.$content['route_quota'].' - '.lang('Final');
				}
				elseif($content['route_quota'] == 1)
				{
					$content['route_quota'] = '';
					$content['route_name'] = lang('Small final');					
				}
				else
				{
					$content['route_quota'] = '';
					$content['route_name'] = lang('Final');
				}
			}
		}
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
			'discipline' => $this->disciplines,
		);
		if ($content['route_order'] == -1)
		{
			unset($sel_options['route_status'][0]);
		}
		// cant delete general result or not yet saved routes
		$readonlys['button[startlist]'] = $readonlys['button[delete]'] = 
			$content['route_order'] == -1 || $content['new_route'];
		// disable max. complimentary selection if no quali.
		if ($content['route_order'] > (int)($content['route_type']==TWO_QUALI_HALF))
		{
			$tmpl->disable_cells('max_compl');
		}
		// no judge rights --> make everything readonly and disable all buttons but cancel
		if (!$this->acl_check($comp['nation'],EGW_ACL_RESULT,$comp))
		{
			foreach($this->route->db_cols as $col)
			{
				$readonlys[$col] = true;
			}
			$readonlys['button[upload]'] = $readonlys['button[ranking]'] = $readonlys['button[startlist]'] = $readonlys['button[delete]'] = $readonlys['button[save]'] = $readonlys['button[apply]'] = true;
			$content['no_upload'] = true;
		}
		else
		{
			if ($content['route_status'] != STATUS_RESULT_OFFICIAL || $content['new_route'] || $content['route_order'] != -1)
			{
				$readonlys['button[ranking]'] = true;	// only offical results can be commited into the ranking
			}
			if ($content['route_status'] == STATUS_RESULT_OFFICIAL || $content['route_order'] == -1)
			{
				$content['no_upload'] = $readonlys['button[upload]'] = true;	// no upload if result offical or general result
			}
		}
		$GLOBALS['egw_info']['flags']['java_script'] .= '<script>window.focus();</script>';

		//_debug_array($content);
		//_debug_array($sel_options);
		$GLOBALS['egw_info']['flags']['app_header'] = lang('Ranking').' - '.
			($content['new_route'] ? lang('Add heat') : lang('Edit heat'));
		$tmpl->exec('ranking.uiresult.route',$content,$sel_options,$readonlys,$preserv,2);
	}

	/**
	 * query the start or result list
	 *
	 * @param array &$query_in
	 * @param array &$rows returned rows/cups
	 * @param array &$readonlys eg. to disable buttons based on acl
	 */
	function get_rows(&$query_in,&$rows,&$readonlys)
	{
		//echo "<p>uiresult::get_rows(".print_r($query_in,true).",,)</p>\n";
		unset($query_in['return']);	// no need to save
		$query = $query_in;
		unset($query['rows']);		// no need to save, can not unset($query_in['rows']), as this is $rows !!!
		$GLOBALS['egw']->session->appsession('result','ranking',$query);
		
		$query['col_filter']['WetId'] = $query['comp'];
		$query['col_filter']['GrpId'] = $query['cat'];
		$query['col_filter']['route_order'] = $query['route'];
		// this is to transport the route_type to route_result::search's filter param
		$query['col_filter']['route_type'] = $query['route_type'];
		$query['col_filter']['discipline'] = $query['discipline'];
		
		switch (($order = $query['order']))
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
				$query['order'] .= ',result_rank '.$query['sort'].',nachname IS NULL,nachname '.$query['sort'].',vorname';
				break;
			case 'result_height':
				$query['order'] = 'CASE WHEN result_height IS NULL THEN -start_order ELSE 0 END '.$query['sort'].
					',result_height '.$query['sort'].',result_plus '.$query['sort'].',nachname '.$query['sort'].',vorname';
				break;
			case 'result_top,result_zone':
				$query['order'] = 'result_top IS NULL,result_top '.$query['sort'].',result_zone IS NULL,result_zone';
				break;
		}
		if($query['route'] == -2 && $query['discipline'] == 'speed' && strstr($query['template'],'speed_graph'))
		{
			$query['order'] = 'result_rank';
			$query['sort']  = 'ASC';
		}
		//echo "<p align=right>order='$query[order]', sort='$query[sort]', start=$query[start]</p>\n";
		$total = $this->route_result->get_rows($query,$rows,$readonlys);
		//echo $total; _debug_array($rows);

		// for speed: skip 1/8 and 1/4 Final if there are less then 16 (8) starters
		if($query['route'] == -2 && $query['discipline'] == 'speed' && strstr($query['template'],'speed_graph'))
		{
			$skip = count($rows)-1 >= 16 ? 0 : (count($rows)-1 >= 8 ? 1 : 2);	// -1 for the route_names
			if (!$skip) $rows['heat3'] = array(true);	// to not hide the 1/8-Final because of no participants yet
		}
		if ($query['ranking'] && strstr($query['template'],'startlist') &&
			($comp = $this->comp->read($query['comp'])) && ($cat = $this->cats->read($query['cat'])))
		{
			$stand = $comp['datum'];
 			$this->ranking($cat,$stand,$nul,$test,$ranking,$nul,$nul,$nul);
		}
		foreach($rows as $k => $row)
		{
			if (!is_int($k)) continue;
			
			// results for setting on regular routes (no general result)
			if($query['route'] >= 0) $rows['set'][$row['PerId']] = $row;

			if (!$quota_line && $query['route_quota'] && $row['result_rank'] > $query['route_quota'])
			{
				$rows[$k]['quota_class'] = 'quota_line';
				$quota_line = true;
			}
			if ($ranking)
			{
				$rows[$k]['ranking_place'] = $ranking[$row['PerId']]['platz'];
				$rows[$k]['ranking_points'] = $ranking[$row['PerId']]['pkt'];
			}
			$rows[$k]['class'] = $k & 1 ? 'row_off' : 'row_on'; 
			if ($query['discipline'] == 'speed' && $query['route'] >= 2 && 
				(strstr($query['template'],'startlist') && $order == 'start_order' || 
				!strstr($query['template'],'startlist') && !$row['result_rank'] && $order == 'result_rank'))
			{
				if (!$unranked)
				{
					$unranked[$k & 2] = $rows[$k]['class'];
					$unranked[2*!($k & 2)] = $rows[$k]['class'] == 'row_off' ? 'row_on' : 'row_off';	
				}
				$rows[$k]['class'] = $unranked[$k & 2];
			}
			// for the speed graphic, we have to make the athlets availible by the startnumber of each heat
			if($query['route'] == -2 && $query['discipline'] == 'speed' && strstr($query['template'],'speed_graph'))
			{
				for($suffix=2; $suffix <= 6; ++$suffix)
				{
					if (isset($row['start_order'.$suffix]))
					{
						$row['result'] = $row['result'.$suffix];
						$rows['heat'.($suffix+$skip)][$row['start_order'.$suffix]] = $row;
						unset($rows[$k]['result']);	// only used for winner and 3. place
						// make final or small final winners availible as winner1 and winner3
						if ($suffix+$skip >= 5 && $row['result'.$suffix] && $row['result_rank'.$suffix] == 1)
						{
							$rows['winner'.$row['result_rank']] = $row;
						}
					}
				}
			}
			if ($query['pstambl'])
			{
				list($page_name,$target) = explode(',',$query['pstambl']);
				$rows[$k]['link'] = ',index.php?page_name='.$page_name.'&person='.$row['PerId'].'&cat='.$query['cat']['GrpId'].',,,'.$target;
			}
		}
		// report the set-values at time of display back to index() for calling boresult::save_result
		$query_in['return'] = $rows['set'];
		
		// show previous heat only if it's counting
		$rows['no_prev_heat'] = $query['route'] < 2+(int)($query['route_type']==TWO_QUALI_HALF);
		
		// which result to show
		$rows['ro_result'] = $query['route_status'] == STATUS_RESULT_OFFICIAL ? '' : 'onlyPrint';
		$rows['rw_result'] = $query['route_status'] == STATUS_RESULT_OFFICIAL ? 'displayNone' : 'noPrint';
		$rows['route_type'] = $query['route_type'] == TWO_QUALI_ALL ? 'TWO_QUALI_ALL' : 
			($query['route_type'] == TWO_QUALI_HALF ? 'TWO_QUALI_HALF' : 'ONE_QUALI');
		$rows['num_problems'] = $query['num_problems'];
		$rows['no_delete'] = $query['readonly'];
		$rows['no_ranking'] = !$ranking;
		
		return $total;
	}

	/**
	 * Show a result / startlist
	 *
	 * @param array $content=null
	 * @param string $msg=''
	 */
	function index($content=null,$msg='',$pstambl='')
	{
		$tmpl =& new etemplate('ranking.result.index');
		
		if ($tmpl->sitemgr && !count($this->ranking_nations))
		{
			return lang('No rights to any nations, admin needs to give read-rights for the competitions of at least one nation!');
		}
		//_debug_array($content);exit;
		if (!is_array($content))
		{
			$content = array('nm' => $GLOBALS['egw']->session->appsession('result','ranking'));
			if (!is_array($content['nm']) || !$content['nm']['get_rows'])
			{
				if (!is_array($content['nm'])) $content['nm'] = array();
				$content['nm'] += array(
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
			
			$content['nm']['pstambl'] = $pstambl;
		}
		elseif ($content['nm']['show_result'] < 0)
		{
			$content['nm']['route'] = -1;
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
			'route_order' => $content['nm']['route'] < 0 ? -1 : $content['nm']['route'],
		);
		if ($comp && ($content['nm']['old_comp'] != $comp['WetId'] ||		// comp changed or
			$cat && ($content['nm']['old_cat'] != $cat['GrpId'] || 			// cat changed or
			!($route = $this->route->read($keys)))))	// route not found and no general result
		{
			$content['nm']['route'] = $keys['route_order'] = $this->route->get_max_order($comp['WetId'],$cat['GrpId']);
			if (!is_numeric($keys['route_order']) || !$this->has_startlist($keys))
			{
				if ($cat) $msg = lang('No startlist or result yet!');
				$content['nm']['show_result'] = '0';
			}
			elseif ($keys['route_order'] > 0)	// more then the quali --> show the general result
			{
				$content['nm']['route'] = $keys['route_order'] = -1;
			}
			else	// only quali --> show result if availible, else startlist
			{
				$content['nm']['show_result'] = $this->has_results($keys) ? '1' : '0';
			}
			if (is_numeric($keys['route_order'])) $route = $this->route->read($keys);
		}
		elseif ($comp && $cat && $keys['route_order'] >= 0 && !$this->has_startlist($keys))
		{
			$msg = lang('No startlist or result yet!');
			$content['nm']['show_result'] = '0';
		}
		//echo "<p>calendar='$calendar', comp={$content['nm']['comp']}=$comp[rkey]: $comp[name], cat=$cat[rkey]: $cat[name], route={$content['nm']['route']}</p>\n";

		// check if user pressed a button and react on it
		list($button) = @each($content['button']);
		unset($content['button']);
		if (!$button && $content['nm']['rows']['delete'])
		{
			list($PerId) = @each($content['nm']['rows']['delete']);
			$button = 'delete';
		}
		if ($button && $comp && $cat && is_numeric($content['nm']['route']))
		{
			//echo "<p align=right>$comp[rkey] ($comp[WetId]), $cat[rkey]/$cat[GrpId], {$content['nm']['route']}, button=$button</p>\n";
			switch($button)
			{
				case 'apply':
					if (is_array($content['nm']['rows']['set']) && $this->save_result($keys,$content['nm']['rows']['set'],$content['nm']['route_type'],$content['nm']['discipline'],$content['nm']['return']))
					{
						$msg = lang('Heat updated');
					}
					else
					{
						$msg = lang('Nothing to update');
					}
					if ($this->error)
					{
						foreach($this->error as $PerId => $data)
						{
							foreach($data as $field => $error)
							{
								$tmpl->set_validation_error("nm[rows][set][$PerId][$field]",$error);					
								$errors[$error] = $error;
							}
						}
						$msg = lang('Error').': '.implode(', ',$errors);
					}
					break;
					
				case 'download':
					$this->download($keys);
					break;
					
				case 'delete':
					if (!is_numeric($PerId) || !$this->acl_check($comp['nation'],EGW_ACL_RESULT,$comp) ||
						!$this->delete_participant($keys+array('PerId'=>$PerId)))
					{
						$msg = lang('Permission denied !!!');
					}
					else
					{
						$msg = lang('Participant deleted');
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
				'datum < '.$this->db->quote(date('Y-m-d',time()+7*24*3600)),	// starting 5 days from now
				'datum > '.$this->db->quote(date('Y-m-d',time()-365*24*3600)),	// until one year back
				'gruppen IS NOT NULL',
			),0,'datum DESC'),
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
			'eliminated' => $this->eliminated_labels,
		);
		if ($comp && !isset($sel_options['comp'][$comp['WetId']])) $sel_options['comp'][$comp['WetId']] = $comp['name'];

		if ($content['nm']['route'] < 2) unset($sel_options['eliminated'][0]);
		for($i=1; $i <= $route['route_num_problems']; ++$i)
		{
			$sel_options['zone'.$i] = array(lang('No'));
		}
		if (is_array($route)) $content += $route;
		$content['nm']['calendar'] = $calendar;
		$content['nm']['comp']     = $content['nm']['old_comp']= $comp ? $comp['WetId'] : null;
		$content['nm']['cat']      = $content['nm']['old_cat'] = $cat ? $cat['GrpId'] : null;
		$content['nm']['route_type'] = $route['route_type'];
		$content['nm']['route_status'] = $route['route_status'];
		$content['nm']['discipline'] = $comp['discipline'] ? $comp['discipline'] : $cat['discipline'];
		$content['nm']['num_problems'] = $route['route_num_problems'];
		$this->set_ui_state($calendar,$comp['WetId'],$cat['GrpId']);
		
		// make competition and category data availible for print
		$content['comp'] = $comp;
		$content['cat']  = $cat;

		$content['msg'] = $msg;
		
		if (count($sel_options['route']) > 1)	// more then 1 heat --> include a general result
		{
			if ($content['nm']['discipline'] == 'speed')	// for speed include pairing graph
			{
				$sel_options['show_result'][3] = lang('Pairing speed final');
				$sel_options['route'] = array(-2 => lang('Pairing speed final'))+$sel_options['route'];
			}
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

		// no startlist, no rights at all or result offical -->disable all update possebilities
		if (($readonlys['button[apply]'] = !$this->has_startlist($keys) || 
			!$this->acl_check($comp['nation'],EGW_ACL_RESULT,$comp) || $route['route_status'] == STATUS_RESULT_OFFICIAL))
		{
			$readonlys['nm'] = true;
			$sel_options['result_plus'] = $this->plus;
			$content['nm']['readonly'] = true;
		}
		else
		{
			unset($content['nm']['readonly']);
		}
		$readonlys['button[download]'] = $keys['route_order'] < 0 || !$this->has_startlist($keys);
		$content['no_route_selection'] = !$cat; 	// no cat selected
		if (!$this->acl_check($comp['nation'],EGW_ACL_RESULT,$comp))	// no judge
		{
			$readonlys['button[edit]'] = $readonlys['button[new]'] = true;
			
			if (!is_numeric($keys['route_order']) || !$sel_options['route']) $content['no_route_selection'] = true;	// no route yet
		}
		elseif (!is_numeric($keys['route_order']) || !$sel_options['route'])	// no route yet
		{
			$readonlys['nm[route]'] = $readonlys['button[edit]'] = true;
		}
		elseif ($comp && $cat)	// check if the highest heat has a result
		{
			$last_heat = $keys;
			$last_heat['route_order'] = $this->route_result->get_max_order($comp['WetId'],$cat['GrpId']);
			if (!$this->has_results($last_heat) && $content['route_order'] >= 2)
			{
				$last_heat = $this->route->read($last_heat);
				$tmpl->set_cell_attribute('button[new]','onclick',"alert('".
					addslashes(lang("You can only create a new heat, if the previous one '%1' has a result!",$last_heat['route_name'])).
					"'); return false;");
			}
		}
		// check if the type of the list to show changed: startlist, result or general result
		// --> set template and default order
		if ($content['nm']['show_result'] == 2 && $content['nm']['old_show'] != 2)
		{
			$content['nm']['route'] = -1;
		}
		elseif ($content['nm']['show_result'] == 3 && $content['nm']['old_show'] != 3)
		{
			$content['nm']['route'] = -2;
		}
		elseif ($content['nm']['show_result'] || $content['nm']['route'] < 0)
		{
			$content['nm']['show_result'] = $content['nm']['route'] < 0 ? ($content['nm']['route'] == -1 ? 2 : 3) : 1;
		}
		if ($content['nm']['route'] < 0)	// general result --> hide show_route selection
		{
			$sel_options['show_result'] = array(-1 => '');
			$readonlys['nm[show_result]'] = true;
		}
		if ((string)$content['nm']['old_show'] !== (string)$content['nm']['show_result'])
		{
			if ($content['nm']['route'] < 0)	// general result
			{
				$content['nm']['order'] = 'result_rank';
			}
			else
			{
				$content['nm']['order'] = $content['nm']['show_result'] ? 'result_rank' : 'start_order';
			}
			$content['nm']['sort'] = 'ASC';
			$content['nm']['old_show'] = $content['nm']['show_result'];
		}
		if ($content['nm']['show_result'] || $tmpl->sitemgr || !$this->has_startlist($keys))
		{
			$tmpl->disable_cells('nm[ranking]');	// dont show ranking in result of via sitemgr
		}
		$content['nm']['template'] = $this->_template_name($content['nm']['show_result'],$content['nm']['discipline']);
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

		return $tmpl->exec('ranking.uiresult.index',$content,$sel_options,$readonlys,array('nm' => $content['nm']));
	}
	
	/**
	 * Get the template name depending on show_result and discipline
	 *
	 * @param int $show_result 0=startlist, 1=result, 2=general result
	 * @param string $discipline 'lead', 'boulder', 'speed'
	 * @return string
	 */
	function _template_name($show_result,$discipline='lead')
	{
		if ($show_result == 3 && $discipline == 'speed')
		{
			return 'ranking.result.index.speed_graph';
		}
		if ($show_result == 2)
		{
			return 'ranking.result.index.rows_general';
		}
		if ($show_result)
		{
			switch($discipline)
			{
				default:
				case 'lead':    return 'ranking.result.index.rows_lead';
				case 'speed':   return 'ranking.result.index.rows_speed';
				case 'boulder': return 'ranking.result.index.rows_boulder';
			}
		}
		return 'ranking.result.index.rows_startlist';
	}
}
