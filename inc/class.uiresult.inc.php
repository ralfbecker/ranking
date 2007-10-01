<?php
/**
 * eGroupWare digital ROCK Rankings - result UI
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2007 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$ 
 */

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
		$start = microtime(true);
		$this->boresult();
		error_log("boresult constructor took ".sprintf('%4.2lf s',microtime(true)-$start));
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
					($previous = $this->route->read($keys)))
				{
					++$keys['route_order'];
					if ($keys['route_order'] == 1 && in_array($previous['route_type'],array(ONE_QUALI,TWO_QUALI_SPEED)))
					{
						$keys['route_order'] = 2;
					}
					foreach(array('route_type','dsp_id','frm_id','dsp_id2','frm_id2','route_time_host','route_time_port') as $name)
					{
						$keys[$name] = $previous[$name];
					}
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
					if ($comp['judges'])
					{
						foreach($comp['judges'] as $uid)
						{
							$keys['route_judge'][] = $GLOBALS['egw']->common->grab_owner_name($uid);
						}
						$keys['route_judge'] = implode(', ',$keys['route_judge']);
					}
				}
				$content = $this->route->init($keys);
				$content['new_route'] = true;
			}
			// speed uses a different type for quali on two routes
			if ($content['route_type'] == TWO_QUALI_SPEED)
			{
				$content['route_type'] = TWO_QUALI_ALL;
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
					if (isset($content['frm_line']))	// if a frm_line is given translate it back to a frm_id
					{
						include_once(EGW_INCLUDE_ROOT.'/ranking/inc/class.ranking_display_format.inc.php');
						$format = new ranking_display_format($this->db);
						$content['frm_id'] = $format->read(array(
							'dsp_id' => $content['dsp_clone_of'] ? $content['dsp_clone_of'] : $content['dsp_id'],
							'WetId'  => $content['WetId'],
							'frm_line' => $content['frm_line'],
						)) ? $format->frm_id : 0;
						$content['frm_id2'] = $format->read(array(
							'dsp_id' => $content['dsp_clone_of2'] ? $content['dsp_clone_of2'] : $content['dsp_id2'],
							'WetId'  => $content['WetId'],
							'frm_line' => $content['frm_line2'],
						)) ? $format->frm_id : 0;
					}
					//_debug_array($content);
					// speed uses a different type for quali on two routes
					if ($discipline == 'speed' && $content['route_type'] == TWO_QUALI_ALL) $content['route_type'] = TWO_QUALI_SPEED;
					$err = $this->route->save($content);
					if ($discipline == 'speed' && $content['route_type'] == TWO_QUALI_SPEED) $content['route_type'] = TWO_QUALI_ALL;
					if ($err)
					{
						$msg = lang('Error: saving the heat!!!');
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
			'no_display'  => true,		// we enable it later, for some cases (judge and display-rights)
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
		foreach(array('new_route','route_type','route_order','dsp_id','frm_id','dsp_id2','frm_id2') as $name)
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
			include_once(EGW_INCLUDE_ROOT.'/ranking/inc/class.ranking_display.inc.php');
			$display =& new ranking_display($this->db);
			// display selection, only if user has rights on the displays
			if (($sel_options['dsp_id'] = $sel_options['dsp_id2'] = $display->displays()))
			{
				$content['no_display'] = false;
				foreach(array('','2') as $num)
				{
					if ($content['dsp_id'.$num] && $display->read($content['dsp_id'.$num]))
					{
						$preserv['dsp_clone_of'.$num] = $display->dsp_clone_of;
						
						if (is_null($format))
						{
							include_once(EGW_INCLUDE_ROOT.'/ranking/inc/class.ranking_display_format.inc.php');
							$format = new ranking_display_format($this->db);
						}
						if ($content['frm_id'.$num] && $format->read($content['frm_id'.$num]))
						{
							$content['frm_line'.$num] = $format->frm_line;
						}
						$content['max_line'.$num] = $format->max_line(array(
							'dsp_id' => $display->dsp_clone_of ? $display->dsp_clone_of : $display->dsp_id,
							'WetId'  => $content['WetId'],
						));
					}
					if (!$content['max_line'.$num]) $content['max_line'.$num] = 1;
				}
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
		$rows['no_prev_heat'] = $query['route'] < 2+(int)($query['route_type']==TWO_QUALI_HALF) ||
			$query['route_type']==TWOxTWO_QUALI && $query['route'] == 4;
		
		// which result to show
		$rows['ro_result'] = $query['route_status'] == STATUS_RESULT_OFFICIAL ? '' : 'onlyPrint';
		$rows['rw_result'] = $query['route_status'] == STATUS_RESULT_OFFICIAL ? 'displayNone' : 'noPrint';
		if ($query['discipline'] == 'lead')
		{
			$rows['route_type'] = $query['route_type'] == TWO_QUALI_ALL ? 'TWO_QUALI_ALL' : 
				($query['route_type'] == TWO_QUALI_HALF ? 'TWO_QUALI_HALF' : 
				($query['route_type'] == ONE_QUALI ? 'ONE_QUALI' : 'TWOxTWO_QUALI'));
		}
		$rows['speed_only_one'] = $query['route_type'] == ONE_QUALI && !$query['route'];
		$rows['num_problems'] = $query['num_problems'];
		$rows['no_delete'] = $query['readonly'];
		$rows['no_ranking'] = !$ranking;
		$rows['time_measurement'] = $query['time_measurement'];
		
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
			'eliminated_r' => $this->eliminated_labels,
		);
		if ($comp && !isset($sel_options['comp'][$comp['WetId']])) $sel_options['comp'][$comp['WetId']] = $comp['name'];

		if ($content['nm']['route'] < 2) unset($sel_options['eliminated'][0]);
		unset($sel_options['eliminated_r'][0]);
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
		$content['nm']['time_measurement'] = $route['route_time_host'] && $route['route_status'] != STATUS_RESULT_OFFICIAL;
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
			if (!$this->has_results($last_heat) && ($last_heat['route_order'] >= 3 || $route['route_type'] != TWOxTWO_QUALI))
			{
				$last_heat = $this->route->read($last_heat);
				$tmpl->set_cell_attribute('button[new]','onclick',"alert('".
					addslashes(lang("You can only create a new heat, if the previous one '%1' has a result!",$last_heat['route_name'])).
					"'); return false;");
			}
			$GLOBALS['egw_info']['flags']['include_xajax'] = true;
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

		if (!is_object($GLOBALS['egw']->js))
		{
			require_once(EGW_API_INC.'/class.javascript.inc.php');
			$GLOBALS['egw']->js =& new javascript();
		}
		$GLOBALS['egw']->js->validate_file('.','ranking','ranking',false);
		
		// create a nice header
		$GLOBALS['egw_info']['flags']['app_header'] = /*lang('Ranking').' - '.*/(!$comp || !$cat ? lang('Resultservice') : 
			($content['nm']['show_result'] == '0' && $route['route_status'] == STATUS_UNPUBLISHED || 
			 $content['nm']['show_result'] != '0' && $route['route_status'] != STATUS_RESULT_OFFICIAL ? lang('provisional').' ' : '').
			(isset($sel_options['show_result'][(int)$content['nm']['show_result']]) ? $sel_options['show_result'][(int)$content['nm']['show_result']].' ' : '').
			($cat ? (isset($sel_options['route'][$content['nm']['route']]) ? $sel_options['route'][$content['nm']['route']].' ' : '').$cat['name'] : ''));
		
		return $tmpl->exec('ranking.uiresult.index',$content,$sel_options,$readonlys,array('nm' => $content['nm']));
	}
	
	/**
	 * Update a result of a single participant
	 *
	 * @param string $request_id eTemplate request id
	 * @param string $name can be repeated multiple time together with value
	 * @param string $value
	 * @return string
	 */
	function ajax_update($request_id,$name,$value)
	{
		//$start = microtime(true);
		$response = new xajaxResponse();
		
		require_once(EGW_INCLUDE_ROOT.'/etemplate/inc/class.etemplate_request.inc.php');
		if (!($request =& etemplate_request::read($request_id)))
		{
			$response->addAlert(lang('Result form is timed out, please reload the form by clicking on the application icon.'));
		}
		else
		{
			$params = func_get_args();
			array_shift($params);	// request_id
			$content = $to_process = array();
			while(($name = array_shift($params)))
			{
				if (!isset($request->to_process[$name])) continue;
				$to_process[$name] = $request->to_process[$name];

				etemplate::set_array($content,$name,$value=array_shift($params));
				//$args .= ",$name='$value'";
			}
			//$response->addAlert("ajax_update('$request_id',$PerId$args)");
		
			//_debug_array($request->preserv); exit;
			$content = $content['exec'];
			$tpl = new etemplate();	// process_show is NOT static
			if ($tpl->process_show($content,$to_process,'exec'))
			{
				// validation errors
				$response->addAlert(implode("\n",$GLOBALS['egw_info']['etemplate']['validation_errors']));
			}
			else
			{
				$keys = array(
					'WetId' => $request->preserv['nm']['comp'],
					'GrpId' => $request->preserv['nm']['cat'],
					'route_order' => $request->preserv['nm']['route'] < 0 ? -1 : $request->preserv['nm']['route'],
				);
				list($athlete) = each($content['nm']['rows']['set']);
				$old_result = $this->route_result->read($keys+array('PerId'=>$athlete));

				if (is_array($content['nm']['rows']['set']) && $this->save_result($keys,$content['nm']['rows']['set'],
					$request->preserv['nm']['route_type'],$request->preserv['nm']['discipline']))
				{
					$new_result = $this->route_result->read($keys+array('PerId'=>$athlete));
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
							$errors[$error] = $error;
						}
					}
					$response->addAlert(lang('Error').': '.implode(', ',$errors));
					$msg = '';
				}
				else
				{
					if($this->route->read($keys) && ($dsp_id=$this->route->data['dsp_id']) && ($frm_id=$this->route->data['frm_id']))
					{
						// add display update(s)
						include_once(EGW_INCLUDE_ROOT.'/ranking/inc/class.ranking_display.inc.php');
						$display = new ranking_display($this->db);
						$display->activate($frm_id,$athlete,$dsp_id,$keys['GrpId'],$keys['route_order']);
					}
					if ($new_result && $new_result['result_rank'] != $old_result['result_rank'])	// the ranking has changed
					{
						$this->_update_ranks($keys,$response,$request);
					}
				}
				//if ($msg) $response->addAlert($msg);
			}
		}
		//error_log("processing of ajax_update took ".sprintf('%4.2lf s',microtime(true)-$start));
		return $response->getXML();
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
	
	/**
	 * Start the time measurement for $PerId
	 *
	 * @param string $request_id
	 * @param int $PerId
	 * @return string
	 */
	function ajax_time_measurement($request_id,$PerId)
	{
		//$start = microtime(true);
		$response = new xajaxResponse();
		
		require_once(EGW_INCLUDE_ROOT.'/etemplate/inc/class.etemplate_request.inc.php');
		if (!($request =& etemplate_request::read($request_id)))
		{
			$response->addAlert(lang('Result form is timed out, please reload the form by clicking on the application icon.'));
			return $this->_stop_time_measurement($response);
		}
		$keys = array(
			'WetId' => $request->preserv['nm']['comp'],
			'GrpId' => $request->preserv['nm']['cat'],
			'route_order' => $request->preserv['nm']['route'],
		);
		if (!($route = $this->route->read($keys)) ||
			!($old_result = $this->route_result->read($keys+array('PerId'=>$PerId))) ||
			!($PerId < 0 && $route['route_order'] >= 2) && !($athlete = $this->athlete->read($PerId)))
		{
			$response->addAlert("internal error: ".__FILE__.': '.__LINE__);
			return $this->_stop_time_measurement($response);
		}
		require_once(EGW_INCLUDE_ROOT.'/ranking/inc/class.ranking_time_measurement.inc.php');
		$timy =& new ranking_time_measurement($route['route_time_host'],$route['route_time_port']);
		
		if (!$timy->is_connected())
		{
			$response->addAlert(lang("Can't connect to time controll program at '%1': %2",$route['route_time_host'].':'.$route['route_time_port'],$timy->error));
			return $this->_stop_time_measurement($response);
		}
		// allow the request to run max. 15min and close the session, to not block other request from that session
		set_time_limit(900);
		$GLOBALS['egw']->session->commit_session();

		// check if we measure two participants (quali on two routes or final) or just one (quali on one route)
		if ($route['route_order'] >= 2 || $route['route_type'] != ONE_QUALI)
		{
			if ($athlete && (string)$old_result['eliminated_r'] === '')	// real athlete, no wildcard and not eliminated
			{
				$side = '';		// we do both sides
				$side1 = 'l';
				$side2 = 'r';
			}
			else	// wildcard left
			{
				$side = $side2 = 'r';
				$athlete = null;
			}
			// find out the other participant
			if ($route['route_order'] < 2 && $old_result['result_time'])	// quali and already measured
			{
				$side1 = 'r'; 
				$side2 = 'l';
				$other_sorder = $old_result['start_order'] + 1;
			}
			else
			{
				$other_sorder = $old_result['start_order'] + ($route['route_order'] >= 2 ? ($old_result['start_order']&1 ? 1 : -1) : -1);
			}
			if ($other_sorder) list($old_other) = $this->route_result->search($keys+array('start_order'=>$other_sorder),false);
			if (!$old_other)
			{
				if ($route['route_order'] < 2)
				{
					// last participant starting on right
					$side1 = $side = $other_sorder ? 'r' : 'l';
				}
				else
				{
					// other participant not found --> error
					$response->addAlert(lang("Can't find co-participant!"));
					$timy->close();
					return $this->_stop_time_measurement($response);
				}
			}
			elseif ($old_other['PerId'] > 0 && (string)$old_other['elimitated'] === '')	// real other participant and not eliminated
			{
				if (!($other_athlete = $this->athlete->read($other_PerId=$old_other['PerId'])))
				{
					// other participant not found --> error
					$response->addAlert(lang("Can't find co-participant!"));
					$timy->close();
					return $this->_stop_time_measurement($response);					
				}
			}
			elseif ($old_other['PerId'] < 0)	// wildcard as other participant
			{
				$side = $side1;
				$old_other = null;
			}
		}
		else
		{
			$side1 = $side = 'l';	// only one side, maybe this should be configurable in future
		}
		$startnr = $old_result['start_number'] ? $old_result['start_number'] : $old_result['start_order'];
		if ($athlete)
		{
			$timy->send("start:$side1:$startnr:".$old_result['time_sum'].':'.$athlete['nachname'].', '.$athlete['vorname'].' ('.$athlete['nation'].')');
		}
		elseif ($route['route_order'] >= 2 || $route['route_type'] != ONE_QUALI)	// two routes with only one climber, set other to 0
		{
			$timy->send("start:l:0");	
		}
		if ($old_other)
		{
			$time = is_numeric($old_result['time_sum']) ? $old_result['time_sum'] : '';
			$other_snr = $old_other['start_number'] ? $old_other['start_number'] : $old_other['start_order'];
			$timy->send("start:$side2:$other_snr:".$old_other['time_sum'].':'.$other_athlete['nachname'].', '.$other_athlete['vorname'].' ('.$other_athlete['nation'].')');	
		}
		elseif ($route['route_order'] >= 2 || $route['route_type'] != ONE_QUALI)	// two routes with only one climber, set other to 0
		{
			$s = $side1 == 'l' ? 'r' : 'l';
			$timy->send("start:$s:0");	
		}
		$timy->send('notify:'.$side);
		
		if(($dsp_id=$route['dsp_id']) && ($frm_id=$route['frm_id']))
		{
			// add display update(s)
			include_once(EGW_INCLUDE_ROOT.'/ranking/inc/class.ranking_display.inc.php');
			$display = new ranking_display($this->db);
			if ($route['dsp_id2'] && $route['frm_id2'])
			{
				$dsp_id2 = $route['dsp_id2'];
				$frm_id2 = $route['frm_id2'];
			}
			else
			{
				$dsp_id2 = $route['dsp_id'];
				$frm_id2 = $route['frm_id'];
			}
			$display->activate($frm_id,$PerId,$dsp_id,$keys['GrpId'],$keys['route_order']);
			if ($other_athlete) $display->activate($frm_id2,$other_PerId,$dsp_id2,$keys['GrpId'],$keys['route_order']);
		}
		//error_log("***** waiting for Timy responses ...");
		$stop = $ranking_changed = false;
		while (!$stop)
		{
			if (!($str = $timy->receive()) && !$timy->is_connected()) break;

			list($event_side,$event,$time,$time2) = explode(':',$str);
			error_log("timy->receive()=".$str);

			switch($event)
			{
				case 'start':
					if (!$side && $event_side == 'l') continue;	// ignore 2. start event
					if (is_object($display))
					{
						$display->activate($frm_id,$PerId,$dsp_id,$keys['GrpId'],$keys['route_order']);
						if ($other_athlete) $display->activate($frm_id2,$other_PerId,$dsp_id2,$keys['GrpId'],$keys['route_order']);
					}
					break;
					
				case 'stop':
					$result = $event_side == 'l' ? 'result_time' : 'result_time_r';
					if ($event_side == $side1)	// side1
					{
						$this->save_result($keys,array($PerId=>array(
							$result => $time,
						)),$route['route_type'],'speed');
						$new_result = $this->route_result->read($keys+array('PerId'=>$PerId));
						$response->addAssign("exec[nm][rows][set][$PerId][$result]",'value',$time);
						$response->addAssign("set[$PerId][time_sum]",'innerHTML',$new_result['time_sum']);
						if ($new_result && $new_result['result_rank'] != $old_result['result_rank'])	// the ranking has changed
						{
							$ranking_changed = true;
						}
						if (is_object($display)) $display->activate($frm_id,$PerId,$dsp_id,$keys['GrpId'],$keys['route_order']);
					}
					else	// other participant
					{
						$this->save_result($keys,array($other_PerId=>array(
							$result => $time,
						)),$route['route_type'],'speed');
						$new_other_result = $this->route_result->read($keys+array('PerId'=>$other_PerId));
						$response->addAssign("exec[nm][rows][set][$other_PerId][$result]",'value',$time);
						$response->addAssign("set[$other_PerId][time_sum]",'innerHTML',$new_other_result['time_sum']);
						if ($new_other_result && $new_other_result['result_rank'] != $old_other['result_rank'])	// the ranking has changed
						{
							$ranking_changed = true;
						}
						if (is_object($display)) $display->activate($frm_id2,$other_PerId,$dsp_id2,$keys['GrpId'],$keys['route_order']);
					}
					if ($side || isset($new_result) && isset($new_other_result))	// all athletes measured
					{
						if ($ranking_changed)
						{
							$this->_update_ranks($keys,$response,$request);
						}
						$stop = true;
					}
					break;
					
				case 'false':
					$response->addAlert(lang('False start %1: %2',$event_side != 'r' ? lang('left') : lang('right'),
						$event_side != 'b' ? $time : $time2).($event_side == 'b' ? ', '.lang('right').': '.$time : ''));
					//if (is_object($display)) $display->activate($frm_id,$PerId,$dsp_id,$keys['GrpId'],$keys['route_order']);
					$stop = true;
					break;
			}
		}
		//error_log("***** closing connection to Timy: stop=$stop");
		$timy->close();

		if (!$stop)
		{
			return $this->_stop_time_measurement($response,lang('Measurement aborted!'));
		}
		//error_log("processing of ajax_time_measurement took ".sprintf('%4.2lf s',microtime(true)-$start));
		return $this->_stop_time_measurement($response,lang('Time measured'));
	}
	
	function _update_ranks(array $keys,xajaxResponse &$response,etemplate_request &$request)
	{
error_log("content[order]=".$request->content['nm']['order'].", changes[order]=".$request->changes['nm']['order']);
		$order = $request->changes['nm']['order'] ? $request->changes['nm']['order'] : $request->content['nm']['order'];

		if ($order != 'result_rank')	// --> update only the rank-values
		{
			foreach($this->route_result->search($keys,array('PerId','result_rank'),'','','',false,'AND',false,$keys) as $data)
			{
				$response->addAssign("set[$data[PerId]][result_rank]",'innerHTML',$data['result_rank']);
			}
		}
		else							// --> submit the form to reload the page
		{
			$response->addScript('document.eTemplate.submit();');
		}
	}
	
	/**
	 * Stop the running time measurement ON CLIENT SIDE
	 * 
	 * @access private
	 * @param xajaxResponse $response response object with preset responses
	 * @return string
	 */
	function _stop_time_measurement(&$response,$msg = '')
	{
		$response->addScript("set_style_by_class('td','ajax-loader','display','none'); document.getElementById('msg').innerHTML='".
			htmlspecialchars($msg)."';");

		return $response->getXML();
	}
}
