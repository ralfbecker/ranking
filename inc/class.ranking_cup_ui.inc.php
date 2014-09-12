<?php
/**
 * EGroupware digital ROCK Rankings - cups UI
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2006-14 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$
 */

class ranking_cup_ui extends ranking_bo
{
	/**
	 * @var array $public_functions functions callable via menuaction
	 */
	var $public_functions = array(
		'index' => true,
		'edit'  => true,
		'view'  => true,
	);

	/**
	 * View a cup
	 */
	function view()
	{
		$this->edit(null,'',true);
	}

	/**
	 * Edit a cup
	 *
	 * @param array $content
	 * @param string $msg
	 */
	function edit($content=null,$msg='',$view=false)
	{
		$tmpl = new etemplate('ranking.cup.edit');

		if (($_GET['rkey'] || $_GET['SerId']) && !$this->cup->read($_GET))
		{
			$msg .= lang('Entry not found !!!');
		}
		// set and enforce nation ACL
		if (!is_array($content))	// new call
		{
			if (!$_GET['SerId'] && !$_GET['rkey'])
			{
				$this->check_set_nation_fed_id($this->cup->data);
			}
			// we have no edit-rights for that nation
			if (!$this->acl_check_comp($this->cup->data))
			{
				$view = true;
			}
		}
		else
		{
			$view = $content['view'] && !($content['edit'] && $this->acl_check_comp($this->cup->data));

			if (!$view && $this->only_nation_edit)
			{
				$this->check_set_nation_fed_id($this->cup->data);
			}

			if (!$view && is_array($content['max_per']))
			{
				$content['max_per_cat'] = array();
				foreach($content['max_per'] as $row => $max)
				{
					if ((int)$max > 0 && !empty($content['per_cat'][$row]))
					{
						$content['max_per_cat'][$content['per_cat'][$row]] = (int)$max;
					}
				}
			}
			//echo "<br>ranking_cup_ui::edit: content ="; _debug_array($content);
			$this->cup->data = $content['cup_data'];
			unset($content['cup_data']);
			$this->cup->data_merge($content);

			//echo "<br>ranking_cup_ui::edit: cup->data ="; _debug_array($this->cup->data);

			if (!$view && ($content['save'] || $content['apply']) && $this->acl_check_comp($this->cup->data))
			{
				if (!$this->cup->data['rkey'])
				{
					$msg .= lang('Key must not be empty !!!');
				}
				elseif ($this->cup->not_unique())
				{
					$msg .= lang("Error: Key '%1' exists already, it has to be unique !!!",$this->cup->data['rkey']);
				}
				elseif ($this->cup->save())
				{
					$msg .= lang('Error: while saving !!!');
				}
				else
				{
					$msg .= lang('%1 saved',lang('Cup'));

					if ($content['save']) $content['cancel'] = true;	// leave dialog now
				}
				$link = egw::link('/index.php',array(
					'menuaction' => 'ranking.ranking_cup_ui.index',
					'msg' => $msg,
				));
				$js = "window.opener.location='$link';";
			}
			if ($content['delete'] && $this->acl_check_comp($this->cup->data))
			{
				$link = egw::link('/index.php',array(
					'menuaction' => 'ranking.ranking_cup_ui.index',
					'delete' => $this->cup->data['SerId'],
				));
				$js = "window.opener.location='$link';";
			}
			if ($content['copy'])
			{
				unset($this->cup->data['SerId']);
				unset($this->cup->data['rkey']);
				$msg .= lang('Entry copied - edit and save the copy now.');
			}
			if ($content['save'] || $content['delete'])
			{
				echo "<html><head><script>\n$js;\nwindow.close();\n</script></head></html>\n";
				common::egw_exit();
			}
			if (!empty($js)) $GLOBALS['egw']->js->set_onload($js);
		}
		$content = $this->cup->data + array(
			'msg' => $msg,
			'tabs' => $content['tabs'],
		);
		$sel_options = array(
			'pkte'      => $this->pkt_names,
			'feld_pkte' => array(0 => lang('none')) + $this->pkt_names,
			'serie'     => array(0 => lang('none')) + $this->cup->names(array(
				'nation'=>$this->cup->data['nation'])),
			'nation'    => $this->ranking_nations,
			'gruppen'   => $this->cats->names(array('nation' => $this->cup->data['nation'])),
			'cat'       => $this->cats->names(array('nation' => $this->cup->data['nation']), -1),
			'split_by_places' => $this->split_by_places,
			'fed_id'       => $this->federation->federations($this->cup->data['nation'], true),
			'selfregister' => $this->comp->selfregister_types,
		);
		$content['per_cat'] = $content['max_per'] = array();
		foreach($this->cup->data['max_per_cat'] as $rkey => $max)
		{
			$content['per_cat'][] = $rkey;
			$content['max_per'][] = $max;
		}
		$content['per_cat'][] = '';	// one extra row to add further cats
		foreach($this->cup->data['gruppen'] as $rkey)
		{
			$sel_options['per_cat'][$rkey] = $sel_options['gruppen'][$rkey];
		}
		$content['presets']['quali_preselected'][] = array('cat' => '');

		if ($view)
		{
			$readonlys = array(
				'__ALL__' => true,
				'cancel' => false,
			);
		}
		else
		{
			$readonlys = array(
				'delete' => !$this->cup->data[$this->cup->db_key_cols[$this->cup->autoinc_id]],
				'nation' => !!$this->only_nation_edit,
				'fed_id' => is_numeric($this->only_nation_edit),
				'edit'   => !($view && $this->acl_check($content['nation'],EGW_ACL_EDIT)),
			);
			// if only federation rights (no national rights), switch of ranking tab, to not allow changes there
			if (!$this->acl_check($this->comp->data['nation'], EGW_ACL_EDIT))
			{
				$readonlys['tabs'] = array('presets' => true);
				$readonlys['max_rang'] = true;
				// ToDo: limit category to state category(s)
			}
		}
		$GLOBALS['egw_info']['flags']['app_header'] = lang('ranking').' - '.lang($view ? 'view %1' : 'edit %1',lang('cup'));
		$tmpl->exec('ranking.ranking_cup_ui.edit',$content,
			$sel_options,$readonlys,array(
				'cup_data' => $this->cup->data,
				'view' => $view,
				'min_disciplins_per_cat' => $content['min_disciplins_per_cat'],
			),2);
	}

	/**
	 * query cups for nextmatch in the cups list
	 *
	 * @param array $query
	 * @param array &$rows returned rows/cups
	 * @param array &$readonlys eg. to disable buttons based on acl
	 */
	function get_rows($query,&$rows,&$readonlys)
	{
		$GLOBALS['egw']->session->appsession('ranking','cup_state',$query);

		if (!$this->is_admin && !in_array($query['col_filter']['nation'],$this->read_rights))
		{
			$query['col_filter']['nation'] = $this->read_rights;
			if (($null_key = array_search('NULL',$this->read_rights)))
			{
				$query['col_filter']['nation'][$null_key] = null;
			}
		}
		foreach((array) $query['col_filter'] as $col => $val)
		{
			if ($val == 'NULL') $query['col_filter'][$col] = null;
		}
		$total = $this->cup->get_rows($query,$rows,$readonlys);

		$readonlys = array();
		foreach($rows as $row)
		{
			if (!$this->acl_check_comp($row))
			{
				$readonlys["edit[$row[SerId]]"] = $readonlys["delete[$row[SerId]]"] = true;
			}
		}
		if ($this->debug)
		{
			echo "<p>ranking_cup_ui::get_rows(".print_r($query,true).") rows ="; _debug_array($rows);
			_debug_array($readonlys);
		}
		return $total;
	}

	/**
	 * List existing cups
	 *
	 * @param array $content
	 * @param string $msg
	 */
	function index($content=null,$msg='')
	{
		$tmpl = new etemplate('ranking.cup.list');

		$content = $content['nm']['rows'];

		if ($content['view'] || $content['edit'] || $content['delete'])
		{
			foreach(array('view','edit','delete') as $action)
			{
				if ($content[$action])
				{
					list($id) = each($content[$action]);
					break;
				}
			}
			//echo "<p>ranking::competitions() action='$action', id='$id'</p>\n";
			switch($action)
			{
				case 'view':
					$tmpl->location(array(
						'menuaction' => 'ranking.ranking_cup_ui.view',
						'SerId'      => $id,
					));
					break;

				case 'edit':
					$tmpl->location(array(
						'menuaction' => 'ranking.ranking_cup_ui.edit',
						'SerId'      => $id,
					));
					break;

				case 'delete':
					if (!$this->cup->read(array('SerId' => $id)) || !$this->acl_check_comp($this->cup->data))
					{
						$msg = lang('Permission denied !!!');
					}
					else
					{
						$msg = $this->cup->delete(array('SerId' => $id)) ?
							lang('%1 deleted',lang('Cup')) : lang('Error: deleting %1 !!!',lang('Cup'));
					}
					break;
			}
		}
		$content = array();

		if (!is_array($content['nm'])) $content['nm'] = $GLOBALS['egw']->session->appsession('ranking','cup_state');

		if (!is_array($content['nm']))
		{
			$content['nm'] = array(
				'get_rows'       =>	'ranking.ranking_cup_ui.get_rows',
				'no_filter'      => True,// I  disable the 1. filter
				'no_filter2'     => True,// I  disable the 2. filter (params are the same as for filter)
				'no_cat'         => True,// I  disable the cat-selectbox
				'bottom_too'     => True,// I  show the nextmatch-line (arrows, filters, search, ...) again after the rows
				'order'          =>	'year',// IO name of the column to sort after (optional for the sortheaders)
				'sort'           =>	'DESC',// IO direction of the sort: 'ASC' or 'DESC'
				'csv_fields'     => false,
			);
			if (count($this->read_rights) == 1)
			{
				$content['nm']['col_filter']['nation'] = $this->read_rights[0];
			}
		}
		$content['msg'] = $msg;

		$GLOBALS['egw_info']['flags']['app_header'] = lang('ranking').' - '.lang('cups');
		$tmpl->exec('ranking.ranking_cup_ui.index',$content,array(
			'nation' => $this->ranking_nations,
		));
	}
}
