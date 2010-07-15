<?php
/**
 * eGroupWare digital ROCK Rankings - result UI
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2007-10 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$
 */

require_once(EGW_INCLUDE_ROOT.'/ranking/inc/class.boresult.inc.php');

class uiresult extends boresult
{
	/**
	 * @var array $public_functions functions callable via menuaction
	 */
	var $public_functions = array(
		'index' => true,
		'route' => true,
	);

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
				'route_order' => $_GET['route'] == '-2' ? -1 : $_GET['route'],	// -2 pairing speed --> show general result
			);
		}
		// read $comp, $cat, $discipline and check the permissions
		if (!($ok = $this->init_route($content,$comp,$cat,$discipline)))
		{
			$js = "alert('".addslashes(lang('Permission denied !!!'))."'); window.close();";
			$GLOBALS['egw']->common->egw_header();
			echo "<html><head><script>".$js."</script></head></html>\n";
			$GLOBALS['egw']->common->egw_exit();
		}
		elseif(is_string($ok))
		{
			$msg .= $ok;
		}
		if (!isset($content['slist_order']))
		{
			$content['slist_order'] = self::quali_startlist_default($discipline,$content['route_type'],$comp['nation']);
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
					$err = $this->route->save($content);
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
							$content['max_compl']!=='' ? $content['max_compl'] : 999,$content['slist_order'])))
					{
						$param['msg'] = ($msg .= lang('Startlist generated'));

						$to_set = array();
						$to_set['route_status'] = $content['route_status'] = STATUS_STARTLIST;	// set status to startlist
						if (!$content['route_quota'])
						{
							$content['route_quota'] = $to_set['route_quota'] =
								$this->default_quota($discipline,$content['route_order'],$content['quali_type'],$num);
						}
						if ($this->route->read($content,true)) $this->route->save($to_set);
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
					if (!($content['upload_options'] & 1) && $this->has_results($content))
					{
						$param['msg'] = $msg = lang('Error: route already has a result!!!');
						$param['show_result'] = 1;
					}
					elseif (!$content['file']['tmp_name'] || !is_uploaded_file($content['file']['tmp_name']))
					{
						$param['msg'] = ($msg .= lang('Error: no file to upload selected'));
					}
					elseif (is_numeric($imported = $this->upload($content,$content['file']['tmp_name'],$content['upload_options'] & 2)))
					{
						// set number of problems from csv file
						if ($content['route_num_problems'])
						{
							list($line1) = file($content['file']['tmp_name']);
							for($n = 3; $n <= 6; $n++)
							{
								if (strpos($line1,'boulder'.$n)) $num_problems = $n;
							}
							if ($num_problems && $num_problems != $content['route_num_problems'])
							{
								$content['route_num_problems'] = $num_problems;
								$need_save = true;
							}
						}
						// set the name from the csv file
						if (substr($content['file']['name'],0,strlen($cat['name'])+3) == $cat['name'].' - ' &&
							($name_from_file = str_replace('.csv','',substr($content['file']['name'],strlen($cat['name'])+3))) != $content['route_name'])
						{
							$content['route_name'] = $name_from_file;
							$need_save = true;
						}
						// save the route, if we set something above
						if ($need_save && $this->route->save($content) != 0)
						{
							$msg = lang('Error: saving the heat!!!');
							$button = $js = '';	// dont exit the window
							break;
						}
						$param['msg'] = ($msg .= lang('%1 participants imported',$imported));
						$param['show_result'] = 1;
						$js = "opener.location.href='".$GLOBALS['egw']->link('/index.php',$param)."';";
					}
					else
					{
						$param['msg'] = ($msg .= $imported);
					}
					break;

				case 'ranking':
					$param['msg'] = $msg = $this->import_ranking($content,$comp['nation'] != 'NULL' ? $comp['nation'] : null);
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
		foreach(array('new_route','route_type','route_order','dsp_id','frm_id','dsp_id2','frm_id2') as $name)
		{
			$preserv[$name] = $content[$name];
		}
		$sel_options = array(
			'WetId' => array($comp['WetId'] => strip_tags($comp['name'])),
			'GrpId' => array($cat['GrpId']  => $cat['name']),
			'route_order' => $this->order_nums,
			'route_status' => $this->stati,
			'route_type' => $discipline == 'speed' ? $this->quali_types_speed : $this->quali_types,
			'discipline' => $this->disciplines,
			'upload_options' => array(
				1 => array(
					'label' => 'delete result',
					'title' => 'Delete an eventually existing result withour further confirmation',
				),
				2 => array(
					'label' => 'add athletes',
					'title' => 'add not existing athletes to the database, use with caution!',
				),
				3 => 'all above',
			),
			'slist_order' => self::slist_order_options($comp['serie']),
		);
		if ($content['route_order'] == -1)
		{
			unset($sel_options['route_status'][0]);
		}
		// cant delete general result or not yet saved routes
		$readonlys['button[startlist]'] = $readonlys['button[delete]'] =
			$content['route_order'] == -1 || $content['new_route'];
		// disable max. complimentary selection if no quali.
		if ($content['route_order'] > (int)($content['route_type']==TWO_QUALI_HALF) || $content['route_order'] < 0)
		{
			$tmpl->disable_cells('max_compl');
			$tmpl->disable_cells('slist_order');
			if ($readonlys['button[startlist]']) $content['no_startlist'] = true;	// disable empty startlist row
		}
		// hack for speedrelay to use startlist button for redomizing
		if ($discipline == 'speedrelay' && !$content['route_order'])
		{
			$tmpl->set_cell_attribute('button[startlist]','label','Randomize startlist');
			$tmpl->disable_cells('max_compl');
			$tmpl->disable_cells('slist_order');
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
			if ($content['route_status'] != STATUS_RESULT_OFFICIAL || $content['new_route'] || 
				$content['route_order'] != -1 || $discipline == 'speedrelay')
			{
				$readonlys['button[ranking]'] = true;	// only offical results can be commited into the ranking
			}
			if ($content['route_status'] == STATUS_RESULT_OFFICIAL || $content['route_order'] == -1)
			{
				$content['no_upload'] = $readonlys['button[upload]'] = true;	// no upload if result offical or general result
			}
			$display = new ranking_display($this->db);
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
	 * Return options for startlist order
	 *
	 * @param boolean $cup
	 * @return array
	 */
	private static function slist_order_options($cup)
	{
		$options = array(
			0  => 'random (keep existing startorder!)',
			9  => 'random (distribution ranking)',
			10 => 'random (distribution cup)',
			1  => 'ranking (unranked last)',
			5  => 'reverse ranking (unranked first)',
			2  => 'cup (unranked last)',
			6  => 'reverse cup (unranked first)',
		);
		if (!$cup)
		{
			unset($options[2]);
			unset($options[4]);
			unset($options[6]);
		}
		return $options;
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
		// check if route_result object is instancated for relay or not
		if ($this->route_result->isRelay != ($query['discipline'] == 'speedrelay'))
		{
			$this->route_result->__construct($this->config['ranking_db_charset'],$this->db,null,
				$query['discipline'] == 'speedrelay');
		}
		$alpha_sort = $this->route_result->isRelay ? ',team_nation '.$query['sort'].',team_name' :
			',nachname '.$query['sort'].',vorname';
		// in speed(relay) sort by time first and then alphabetic
		if (substr($query['discipline'],0,5) == 'speed')
		{
			$alpha_sort = ',result_time '.$query['sort'].$alpha_sort;
		}
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
				$query['order'] .= ',result_rank '.$query['sort'].$alpha_sort;
				break;
			case 'result_height':
				$query['order'] = 'CASE WHEN result_height IS NULL THEN -start_order ELSE 0 END '.$query['sort'].
					',result_height '.$query['sort'].',result_plus '.$query['sort'].$alpha_sort;
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
		if($query['route'] == -2 && substr($query['discipline'],0,5) == 'speed' && strstr($query['template'],'speed_graph'))
		{
			$skip = count($rows)-1 >= 16 ? 0 : (count($rows)-1 >= 8 ? 1 : 2);	// -1 for the route_names
			if (!$skip) $rows['heat3'] = array(true);	// to not hide the 1/8-Final because of no participants yet
		}
		$comp = $this->comp->read($query['comp']);
		if (($query['ranking'] & 3) && strstr($query['template'],'startlist') &&
			($cat = $this->cats->read($query['cat'])))
		{
			$stand = $comp['datum'];
			if (!$this->ranking($cat,$stand,$nul,$test,$ranking,$nul,$nul,$nul,$query['ranking']==2?$comp['serie']:'') && $query['ranking'] == 2 &&
				// if there no cup ranking yet (first competition) --> use the one from last year
				($last_cup = $this->cup->read($last_cup_rkey=str_replace('??',substr((int)$comp['datum']-1,-2),$cat['serien_pat']))))
			{
				$stand = ((int)$comp['datum']-1).'-12-31';
				$this->ranking($cat,$stand,$nul,$test,$ranking,$nul,$nul,$nul,$last_cup);
			}
		}
		// speedrelay quali startlist: add empty row to add new team
		if ($query['discipline'] == 'speedrelay' && !$query['route'] && !$query['show_result'])
		{
			$rows[] = array(
				'team_id' => 0,
				'start_order' => count($rows)+1,
			);
			$readonlys['delete[0]'] = true;
			++$total;
		}
		$need_start_number = false;
		foreach($rows as $k => $row)
		{
			if (!is_int($k)) continue;

			// results for setting on regular routes (no general result)
			if($query['route'] >= 0)
			{
				$rows['set'][$row[$this->route_result->id_col]] = $row;
			}
			if (!$quota_line && $query['route_quota'] && $query_in['order'] == 'result_rank' && $query_in['sort'] == 'ASC' &&
				$row['result_rank'] > $query['route_quota'])	// only show quota line if sorted by rank ASC
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
			if (substr($query['discipline'],0,5) == 'speed' && $query['route'] >= 2 &&
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
			if($query['route'] == -2 && substr($query['discipline'],0,5) == 'speed' && strstr($query['template'],'speed_graph'))
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
			if ($query['readonly']) $readonlys['set['.$row['PerId'].']'] = true;	// disable all result input

			// shorten DAV or SAC Sektion
			$rows[$k]['verband'] = preg_replace('/^(Deutscher Alpenverein|Schweizer Alpen[ -]{1}Club) /','',$row['verband']);

			if (!$need_start_number && $row['start_number']) $need_start_number = true;
			
			if ($query['discipline'] == 'speedrelay')
			{
				if ($query['route'] > 0)
				{
					$readonlys["set[$row[team_id]][team_nation]"] = $readonlys["set[$row[team_id]][team_name]"] = true;
				}
				if ($row['team_id'] && $query['template'] == 'ranking.result.index.rows_relay')
				{
					$PerIds = $titles = array();
					for($i = 1; $i <= 3; ++$i)
					{
						if ($row['PerId_'.$i] > 0) $PerIds[$row['PerId_'.$i]] = $row['PerId_'.$i];
					}
					if (count($PerIds) < 3) $titles[''] = lang('None');
					if ($PerIds) $titles += egw_link::titles('ranking',$PerIds);	
					$rows['sel_options']['set['.$row['team_id'].'][PerId_1]'] = 
						$rows['sel_options']['set['.$row['team_id'].'][PerId_2]'] = 
						$rows['sel_options']['set['.$row['team_id'].'][PerId_3]'] = $titles;

					$readonlys['set['.$row['team_id'].'][team_nation]'] = true;
				}
			}
		}
		// disable print-only start-number
		$rows['no_printnumber'] = !$need_start_number;
		$rows['no_start_number'] = !$need_start_number && $query['route_status'] == STATUS_RESULT_OFFICIAL;

		// report the set-values at time of display back to index() for calling boresult::save_result
		$GLOBALS['egw']->session->appsession('set_'.$query['comp'].'_'.$query['cat'].'_'.$query['route'],'ranking',$rows['set']);

		// show previous heat only if it's counting
		$rows['no_prev_heat'] = $query['route'] < 2+(int)($query['route_type']==TWO_QUALI_HALF) ||
			$query['route_type']==TWOxTWO_QUALI && $query['route'] == 4;

		// which result to show
		$rows['ro_result'] = $query['route_status'] == STATUS_RESULT_OFFICIAL ? '' : 'onlyPrint';
		$rows['rw_result'] = $query['route_status'] == STATUS_RESULT_OFFICIAL ? 'displayNone' : 'noPrint';
		if (!in_array($query['discipline'],array('speed','boulder')))
		{
			$rows['route_type'] = boresult::is_two_quali_all($query['route_type']) ? 'TWO_QUALI_ALL' :
				($query['route_type'] == TWO_QUALI_HALF ? 'TWO_QUALI_HALF' :
				($query['route_type'] == ONE_QUALI ? 'ONE_QUALI' : 'TWOxTWO_QUALI'));
		}
		$rows['speed_only_one'] = $query['route_type'] == ONE_QUALI && !$query['route'];
		$rows['num_problems'] = $query['num_problems'];
		$rows['no_delete'] = $query['readonly'];
		$rows['no_ranking'] = !$ranking;
		// disable unused update / start time measurement buttons
		$readonlys['update'] = ($rows['time_measurement'] = $query['time_measurement']) || 
			$query['route_status'] == STATUS_RESULT_OFFICIAL;
		$rows['discipline'] = $query['discipline'];

		// make div. print values available
		foreach(array('calendar','route_name','comp_name','comp_date','comp_logo','comp_sponsors','show_result','result_official','route_data') as $name)
		{
			$rows[$name] =& $query[$name];
		}
		switch((string)$comp['nation'])
		{
			case '':	// international
				$rows['no_ort'] = $rows['no_verband'] = $rows['no_acl_fed'] = true;
				break;
			case 'GER':
				$rows['fed_label'] = 'Sektion';
				$rows['no_ort'] = $rows['no_nation'] = $rows['no_acl_fed'] = true;
				break;
			case 'SUI':
			default:
				$rows['fed_label'] = 'Sektion';
				$rows['acl_fed_label'] = 'Regionalzentrum';
				$rows['no_nation'] = $rows['no_PerId'] = true;
				break;
		}
		// jury list --> switch extra columns on and all federation columns off
		$rows['no_jury_result'] = $rows['no_remark'] = $query['ranking'] != 4;
		if ($query['ranking'] == 4)
		{
			$rows['no_ort'] = $rows['no_verband'] = $rows['no_acl_fed'] = true;
		}
		if ($query['route_type'] == TWO_QUALI_BESTOF && $query['route'] == 0)
		{
			$rows['sum_or_bestof'] = lang('Best of');
		}
		else
		{
			$rows['sum_or_bestof'] = lang('Sum');
		}
		return $total;
	}
	
	/**
	 * Set options of the three athlete selectboxes, after nation changed
	 * 
	 * @param int $comp
	 * @param int $cat
	 * @param int $team_id
	 * @param string $nation
	 */
	function ajax_set_athlets($comp,$cat,$team_id,$nation)
	{
		$response = new xajaxResponse();
		//$response->alert(__METHOD__."($comp,$cat,$team_id,'$nation')"); return $response->getXML();
		$starters = $this->get_registered(array(
			'WetId' => $comp,
			'GrpId' => $cat,
			'nation'=> $nation,
		));
		$id = 'exec[nm][rows][set]['.(int)$team_id.'][team_name]';
		$script = "document.getElementById('$id').value='$nation';";
		// delete existing options
		for($i = 1; $i <= 3; ++$i)
		{
			$id = 'exec[nm][rows][set]['.(int)$team_id.'][PerId_'.$i.']';
			$script .= "document.getElementById('$id').options.length=0;";
		}
		// set option "None" for last selectbox
		$label = lang('None');
		$script .= "selectbox_add_option('$id','$label','',false);";
		// add starter as options to all 3 selectboxes
		foreach($starters as $starter)
		{
			$label = $this->athlete->link_title($starter);
			$PerId = $starter['PerId'];
			for($i = 1; $i <= 3; ++$i)
			{
				$id = 'exec[nm][rows][set]['.(int)$team_id.'][PerId_'.$i.']';
				$script .= "selectbox_add_option('$id','$label','$PerId',false);";
			}
		}
		// set 3 different starter
		for($i = 1; $i <= 3; ++$i)
		{
			$starter = array_shift($starters);
			$PerId = $starter['PerId'];
			$id = 'exec[nm][rows][set]['.(int)$team_id.'][PerId_'.$i.']';
			$script .= "document.getElementById('$id').value = '$PerId';";
		}

		$response->script($script);
		
		return $response->getXML();
	}

	/**
	 * Show a result / startlist
	 *
	 * @param array $content=null
	 * @param string $msg=''
	 */
	function index($content=null,$msg='',$pstambl='')
	{
		$tmpl = new etemplate('ranking.result.index');

		if ($tmpl->sitemgr && !count($this->ranking_nations))
		{
			return lang('No rights to any nations, admin needs to give read-rights for the competitions of at least one nation!');
		}
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
		elseif ($comp && (!$content['nm']['calendar'] || isset($_GET['comp'])))
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
		if ($comp && (!($cat = $content['nm']['cat']) || !($cat = $this->cats->read($cat)) || !is_array($comp['gruppen']) || !in_array($cat['rkey'],$comp['gruppen'])))
		{
			$cat = false;
			$content['nm']['route'] = '';	// dont show route-selection
		}
		$keys = array(
			'WetId' => $comp['WetId'],
			'GrpId' => $cat['GrpId'],
			'route_order' => $content['nm']['route'] < 0 ? -1 : $content['nm']['route'],
		);
		// get discipline and check if relay
		$content['nm']['discipline'] = $comp['discipline'] ? $comp['discipline'] : $cat['discipline'];
		if ($this->route_result->isRelay != ($content['nm']['discipline'] == 'speedrelay'))
		{
			$this->route_result->__construct($this->config['ranking_db_charset'],$this->db,null,
				$content['nm']['discipline'] == 'speedrelay');
		}
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
			list($id) = @each($content['nm']['rows']['delete']);
			$button = 'delete';
		}
		if ($button && $comp && $cat && is_numeric($content['nm']['route']))
		{
			//echo "<p align=right>$comp[rkey] ($comp[WetId]), $cat[rkey]/$cat[GrpId], {$content['nm']['route']}, button=$button</p>\n";
			switch($button)
			{
				case 'apply':
					$old = $GLOBALS['egw']->session->appsession('set_'.$content['nm']['comp'].'_'.$content['nm']['cat'].'_'.$content['nm']['route'],'ranking');
					if (is_array($content['nm']['rows']['set']) && $this->save_result($keys,$content['nm']['rows']['set'],$content['nm']['route_type'],$content['nm']['discipline'],$old))
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
					if (!is_numeric($id) || !$this->acl_check($comp['nation'],EGW_ACL_RESULT,$comp) ||
						!$this->delete_participant($keys+array($this->route_result->id_col => $id)))
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
				'datum < '.$this->db->quote(date('Y-m-d',time()+10*24*3600)),	// starting 10 days from now
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
			'ranking' => array(
				1 => 'display ranking',
			) + ($comp['serie'] ? array(2 => 'display cup') : array()) + array(
				4 => 'Show jurylist',
			),
		);
		if ($comp && !isset($sel_options['comp'][$comp['WetId']])) $sel_options['comp'][$comp['WetId']] = $comp['name'];

		if ($content['nm']['route'] < 2) unset($sel_options['eliminated'][0]);
		unset($sel_options['eliminated_r'][0]);
		for($i=1; $i <= $route['route_num_problems']; ++$i)
		{
			$sel_options['zone'.$i] = array(lang('No'));
		}
		if (is_array($route)) $content += $route;
		$content['nm']['route_data'] = $route;
		$content['nm']['calendar'] = $calendar;
		$content['nm']['comp']     = $content['nm']['old_comp']= $comp ? $comp['WetId'] : null;
		$content['nm']['cat']      = $content['nm']['old_cat'] = $cat ? $cat['GrpId'] : null;
		$content['nm']['route_type'] = $route['route_type'];
		$content['nm']['route_status'] = $route['route_status'];
		$content['nm']['num_problems'] = $route['route_num_problems'];
		$content['nm']['time_measurement'] = $route['route_time_host'] && $route['route_status'] != STATUS_RESULT_OFFICIAL;
		$this->set_ui_state($calendar,$comp['WetId'],$cat['GrpId']);

		// make competition and category data availible for print
		$content['comp'] = $comp;
		$content['cat']  = $cat;
		$content['nm']['comp_name'] = $comp['name'];
		$content['nm']['comp_date'] = $comp['date_span'];

		unset($content['nm']['comp_logo']);
		unset($content['nm']['comp_sponsors']);
		$docroot_base_path = '/'.(int)$comp['datum'].'/'.($calendar != 'NULL' ? $calendar.'/' : '');
		foreach((array)$this->comp->attachments($comp,true,false) as $type => $linkdata)
		{
			if ($type == 'logo' || $type == 'sponsors')
			{
				$content['nm']['comp_'.$type] = egw::link($linkdata);
				// check if images are directly reachable from the docroot --> use them direct
				if (file_exists($_SERVER['DOCUMENT_ROOT'].$docroot_base_path.basename($linkdata)))
				{
					$content['nm']['comp_'.$type] = $docroot_base_path.basename($linkdata);
				}
			}
		}
		$content['msg'] = $msg;

		if (count($sel_options['route']) > 1)	// more then 1 heat --> include a general result
		{
			if (substr($content['nm']['discipline'],0,5) == 'speed')	// for speed include pairing graph
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
		if (($readonlys['button[apply]'] =
			!($content['nm']['discipline'] == 'speedrelay' && !$keys['route_order']) && !$this->has_startlist($keys) ||
			!$this->acl_check($comp['nation'],EGW_ACL_RESULT,$comp) || 
			$route['route_status'] == STATUS_RESULT_OFFICIAL || 
			$content['nm']['route_order'] < 0 || $content['nm']['show_result'] > 1))
		{
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
			if (!$this->has_results($last_heat) && ($last_heat['route_order'] >= 3 || !in_array($route['route_type'],array(TWO_QUALI_ALL,TWOxTWO_QUALI))))
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
		if ($content['nm']['discipline'] == 'speedrelay')
		{
			// todo show only nations registered for the competitiion
			//$sel_options['team_nation'] = $this->federation->query_list('nation','nation');
			$sel_options['team_nation'] = $this->get_registered(array(
				'WetId' => $comp['WetId'],
				'GrpId' => $cat['GrpId'],
			),true);
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
		$content['nm']['result_official'] = $content['nm']['show_result'] && $route['route_status'] == STATUS_RESULT_OFFICIAL;

		if (!is_object($GLOBALS['egw']->js))
		{
			$GLOBALS['egw']->js = new javascript();
		}
		$GLOBALS['egw']->js->validate_file('.','ranking','ranking',false);

		// create a nice header
		$content['nm']['route_name'] = $GLOBALS['egw_info']['flags']['app_header'] =
			/*lang('Ranking').' - '.*/(!$comp || !$cat ? lang('Resultservice') :
			($content['nm']['show_result'] == '0' && $route['route_status'] == STATUS_UNPUBLISHED ||
			 $content['nm']['show_result'] != '0' && $route['route_status'] != STATUS_RESULT_OFFICIAL ? lang('provisional').' ' : '').
			(isset($sel_options['show_result'][(int)$content['nm']['show_result']]) ? $sel_options['show_result'][(int)$content['nm']['show_result']].' ' : '').
			($cat ? (isset($sel_options['route'][$content['nm']['route']]) ? $sel_options['route'][$content['nm']['route']].' ' : '').$cat['name'] : ''));

		return $tmpl->exec('ranking.uiresult.index',$content,$sel_options,$readonlys,array('nm' => $content['nm']));
	}

	/**
	 * Update a result of a single participant
	 *
	 * Lead:  
	 * 	xajax_doXMLHTTP('ranking.uiresult.ajax_update',this.form.etemplate_exec_id.value,
	 * 		'exec[nm][rows][set][$row_cont[PerId]][result_height]',
	 * 		document.getElementById('exec[nm][rows][set][$row_cont[PerId]][result_height]').value,
	 * 		'exec[nm][rows][set][$row_cont[PerId]][result_plus]',
	 * 		document.getElementById('exec[nm][rows][set][$row_cont[PerId]][result_plus]').value);
	 * Speed links: 
	 * 	xajax_doXMLHTTP('ranking.uiresult.ajax_update',this.form.etemplate_exec_id.value,
	 * 		'exec[nm][rows][set][$row_cont[PerId]][result_time]',
	 * 		document.getElementById('exec[nm][rows][set][$row_cont[PerId]][result_time]').value,
	 * 		'exec[nm][rows][set][$row_cont[PerId]][eliminated]',
	 * 		document.getElementById('exec[nm][rows][set][$row_cont[PerId]][eliminated]').value);
	 * Speed rechts: 
	 * 	xajax_doXMLHTTP('ranking.uiresult.ajax_update',this.form.etemplate_exec_id.value,
	 * 		'exec[nm][rows][set][$row_cont[PerId]][result_time_r]',
	 * 		document.getElementById('exec[nm][rows][set][$row_cont[PerId]][result_time_r]').value,
	 * 		'exec[nm][rows][set][$row_cont[PerId]][eliminated_r]',
	 * 		document.getElementById('exec[nm][rows][set][$row_cont[PerId]][eliminated_r]').value);
	 * Relay:
	 * 	xajax_doXMLHTTP('ranking.uiresult.ajax_update',this.form.etemplate_exec_id.value,
	 * 		'exec[nm][rows][set][$row_cont[team_id]][result_time_1]',
	 * 		document.getElementById('exec[nm][rows][set][$row_cont[team_id]][result_time_1]').value,
	 * 		'exec[nm][rows][set][$row_cont[team_id]][result_time_2]',
	 * 		document.getElementById('exec[nm][rows][set][$row_cont[team_id]][result_time_2]').value,
	 * 		'exec[nm][rows][set][$row_cont[team_id]][result_time_3]',
	 * 		document.getElementById('exec[nm][rows][set][$row_cont[team_id]][result_time_3]').value,
	 * 		'exec[nm][rows][set][$row_cont[team_id]][eliminated]',
	 * 		document.getElementById('exec[nm][rows][set][$row_cont[team_id]][eliminated]').value);
	 * 
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

		if (!($request =& etemplate_request::read($request_id)))
		{
			$response->addAlert(lang('Result form is timed out, please reload the form by clicking on the application icon.'));
		}
		else
		{
			$params = func_get_args();
			array_shift($params);	// request_id
			$first_name = $params[0];
			$content = $to_process = array();
			while(($name = array_shift($params)))
			{
				if (!isset($request->to_process[$name])) continue;
				$to_process[$name] = $request->to_process[$name];

				etemplate::set_array($content,$name,$value=array_shift($params));
				//$args .= ",$name='$value'";
			}
			//$response->addAlert("ajax_update('\$request_id',$args)"); return $response->getXML();

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
				if ($this->route_result->isRelay != ($request->preserv['nm']['discipline'] == 'speedrelay'))
				{
					$this->route_result->__construct($this->config['ranking_db_charset'],$this->db,null,
						$request->preserv['nm']['discipline'] == 'speedrelay');
				}
				list($id) = each($content['nm']['rows']['set']);
				$old_result = $this->route_result->read($keys+array($this->route_result->id_col=>$id));

				if (is_array($content['nm']['rows']['set']) && $this->save_result($keys,$content['nm']['rows']['set'],
					$request->preserv['nm']['route_type'],$request->preserv['nm']['discipline']))
				{
					$new_result = $this->route_result->read($keys+array($this->route_result->id_col=>$id));
					$msg = lang('Heat updated');
				}
				else
				{
					$msg = lang('Nothing to update');
				}
				if ($this->error)
				{
					foreach($this->error as $id => $data)
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
					$first_name = preg_replace('/^.*\[([^\]]+)\]$/','\\1',$first_name);
					if($this->route->read($keys))
					{
						if (($dsp_id=$this->route->data['dsp_id']) && ($frm_id=$this->route->data['frm_id']))
						{
							// add display update(s)
							$display = new ranking_display($this->db);
							$display->activate($frm_id,$id,$dsp_id,$keys['GrpId'],$keys['route_order']);
						}
						//$response->addAlert('first_name='.$first_name);
						switch($first_name)
						{
							case 'result_height':
								$to_update = array(
									'current_1' => $id,
								);
								break;
								
							case 'result_time':
								if ($request->preserv['nm']['route'] >= 2)
								{
									$to_update = array(
										'current_1' => $id,
										'current_2' => $this->get_co($keys,$id),
									);
									break;
								}
								else
								{
									$to_update = array(
										'current_1' => $id,
									);
								}
								break;

							case 'result_time_r':
								if ($request->preserv['nm']['route'] >= 2)
								{
									$to_update = array(
										'current_1' => $this->get_co($keys,$id),
										'current_2' => $id,
									);
								}
								else
								{
									$to_update = array(
										'current_1' => $id,
									);
								}
								break;
								
							case 'result_time_1':	// relay
								// we need to check, if id is the co (right climber) or not
								$co = $this->get_co($keys,$id,$id_isnt_co);
								$to_update = array(
									'current_1' => $id_isnt_co ? $id : $co,
									'current_2' => $id_isnt_co ? $co : $id,
								);
								break;
						}
						$this->route->update($to_update+array(
							'route_modified' => time(),
							'route_modifier' => $GLOBALS['egw_info']['user']['account_id'],
						));
					}
					//error_log(__METHOD__."() new_result=".array2string($new_result).", old_result=".array2string($old_result));
					if ($new_result && ($new_result['result_rank'] != $old_result['result_rank'] ||
						isset($new_result['time_sum']) && $new_result['time_sum'] != $old_result['time_sum']))	// the ranking has changed
					{
						$this->_update_ranks($keys,$response,$request);
					}
				}
				if ($msg) $response->assign('msg','innerHTML',$msg);
			}
		}
		//error_log("processing of ajax_update took ".sprintf('%4.2lf s',microtime(true)-$start));
		return $response->getXML();
	}
	
	/**
	 * Get co-participant for given PerId/team_id and route (specified by $keys)
	 * 
	 * @param array $keys
	 * @param int $id PerId or team_id
	 * @param boolean $id_isnt_co=null true if id is NOT the co, false otherwise
	 * @return NULL|int
	 */
	protected function get_co(array $keys,$id,&$id_isnt_co=null)
	{
		if (!($results = $this->route_result->search($keys,false,'start_order')))
		{
			//error_log(__METHOD__."(".array2string($keys).",$id) returning NULL (no results)");
			return null;
		}
		foreach($results as $n => $result)
		{
			if ($result[$this->route_result->id_col] == $id)
			{
				if (($id_isnt_co = ($result['start_order'] & 1 == 1)))
				{
					$co = $results[$n+1];
				}
				else
				{
					$co = $results[$n-1];
				}
				error_log(__METHOD__."(".array2string($keys).",{$this->route_result->id_col}=$id,".array2string($id_isnt_co).") returning ".array2string($co[$this->route_result->id_col]));
				return $co[$this->route_result->id_col];
			}
		}
		//error_log(__METHOD__."(".array2string($keys).",{$this->route_result->id_col}=$id) returning NULL (id NOT found)".array2string($results));
		return null;
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
		if ($show_result == 3 && substr($discipline,0,5) == 'speed')
		{
			return 'ranking.result.index.speed_graph';
		}
		if ($show_result == 2)
		{
			switch($discipline)
			{
				case 'speedrelay': 
					return 'ranking.result.index.rows_relay_general';
				default:
					return 'ranking.result.index.rows_general';
			}
		}
		if ($show_result)
		{
			switch($discipline)
			{
				default:
				case 'lead':    return 'ranking.result.index.rows_lead';
				case 'speed':   return 'ranking.result.index.rows_speed';
				case 'boulder': return 'ranking.result.index.rows_boulder';
				case 'speedrelay': return 'ranking.result.index.rows_relay_speed';
			}
		}
		// startlist
		switch($discipline)
		{
			case 'speedrelay': 
				return 'ranking.result.index.rows_relay';
			default:
				return 'ranking.result.index.rows_startlist';
		}
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
		$timy = new ranking_time_measurement($route['route_time_host'],$route['route_time_port']);

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
		//error_log("content[order]=".$request->content['nm']['order'].", changes[order]=".$request->changes['nm']['order']);
		$order = $request->changes['nm']['order'] ? $request->changes['nm']['order'] : $request->content['nm']['order'];

		if ($order != 'result_rank')	// --> update only the rank-values
		{
			foreach($this->route_result->search($keys,false,'','','',false,'AND',false,$keys) as $data)
			{
				$response->assign("set[{$data[$this->route_result->id_col]}][result_rank]",'innerHTML',(string)$data['result_rank']);
				if (isset($data['time_sum']))
				{
					$response->assign("set[{$data[$this->route_result->id_col]}][time_sum]",'innerHTML',(string)$data['time_sum']);
				}
			}
		}
		else							// --> submit the form to reload the page
		{
			$response->script('document.eTemplate.submit();');
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
