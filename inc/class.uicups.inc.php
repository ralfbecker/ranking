<?php
/**************************************************************************\
* eGroupWare - digital ROCK Rankings - cups UI                             *
* http://www.egroupware.org, http://www.digitalROCK.de                     *
* Written and (c) by Ralf Becker <RalfBecker@outdoor-training.de>          *
* --------------------------------------------                             *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

require_once(EGW_INCLUDE_ROOT.'/ranking/inc/class.uiranking.inc.php');

class uicups extends uiranking 
{
	/**
	 * @var array $public_functions functions callable via menuaction
	 */
	var $public_functions = array(
		'index' => true,
		'edit'  => true,
		'view'  => true,
	);

	function uicups()
	{
		$this->uiranking();
	}

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
		if (($_GET['rkey'] || $_GET['SerId']) && !$this->cup->read($_GET))
		{
			$msg .= $this->messages['not_found'];
		}
		// set and enforce nation ACL
		if (!is_array($content))	// new call
		{
			if (!$_GET['SerId'] && !$_GET['rkey'])
			{
				$this->cup->data['nation'] = $this->edit_rights[0];
			}
			// we have no edit-rights for that nation
			if (!in_array($this->cup->data['nation'] ? $this->cup->data['nation'] : 'NULL',$this->edit_rights))
			{
				$view = true;
			}
		}
		else
		{
			$view = $content['view'];

			if (!$view && $this->only_nation_edit) $content['nation'] = $this->only_nation_edit;

			//echo "<br>uicups::edit: content ="; _debug_array($content);
			$this->cup->data = $content['cup_data'];
			unset($content['cup_data']);

			$this->cup->data_merge($content);
			//echo "<br>uicups::edit: cup->data ="; _debug_array($this->cup->data);

			if (($content['save'] || $content['apply']) && in_array($content['nation'],$this->edit_rights))
			{
				if (!$this->cup->data['rkey'])
				{
					$msg .= $this->messages['rkey_empty'];
				}
				elseif ($this->cup->not_unique())
				{
					$msg .= sprintf($this->messages['rkey_not_unique'],$this->cup->data['rkey']);
				}
				elseif ($this->cup->save())
				{
					$msg .= $this->messages['error_writing'];
				}
				else
				{
					$msg .= $this->messages['cup_saved'];

					if ($content['save']) $content['cancel'] = true;	// leave dialog now
				}
			}
			if ($content['cancel'])
			{
				$this->tmpl->location(array('menuaction'=>'ranking.uicups.index'));
			}
			if ($content['delete'] && in_array($content['nation'],$this->edit_rights))
			{
				$this->index(array(
					'nm' => array(
						'rows' => array(
							'delete' => array(
								$this->cup->data['SerId'] => 'delete'
							)
						)
					)
				));
				return;
			}
		}
		$content = $this->cup->data + array(
			'msg' => $msg
		);
		$sel_options = array(
			'pkte'      => $this->pkt_names,
			'feld_pkte' => array(0 => lang('none')) + $this->pkt_names,
			'serie'     => array(0 => lang('none')) + $this->cup->names(array(
				'nation'=>$this->cup->data['nation'])),
			'nation'    => $this->ranking_nations,
			'gruppen'   => $this->cats->names(array('nation' => $this->cup->data['nation'])),
			'split_by_places' => $this->split_by_places,
		);
		$readonlys = array(
			'delete' => !$this->cup->data[$this->cup->db_key_cols[$this->cup->autoinc_id]],
			'nation' => !!$this->only_nation_edit,
		);
		if ($view)
		{
			foreach($this->cup->data as $name => $val)
			{
				$readonlys[$name] = true;
			}
			$readonlys['delete'] = $readonlys['save'] = $readonlys['apply'] = true;
		}
		$GLOBALS['phpgw_info']['flags']['app_header'] = lang('ranking').' - '.lang('edit cup');
		$this->tmpl->read('ranking.cup.edit');
		$this->tmpl->exec('ranking.uicups.edit',$content,
			$sel_options,$readonlys,array(
				'cup_data' => $this->cup->data,
				'view' => $view,
			));
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
		$GLOBALS['phpgw']->session->appsession('ranking','cup_state',$query);

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
			if (!$this->is_admin && !in_array($row['nation']?$row['nation']:'NULL',$this->edit_rights))
			{
				$readonlys["edit[$row[SerId]]"] = $readonlys["delete[$row[SerId]]"] = true;
			}
		}
		if ($this->debug)
		{
			echo "<p>uicups::get_rows(".print_r($query,true).") rows ="; _debug_array($rows);
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
					$this->tmpl->location(array(
						'menuaction' => 'ranking.uicups.view',
						'SerId'      => $id,
					));
					break;
					
				case 'edit':
					$this->tmpl->location(array(
						'menuaction' => 'ranking.uicups.edit',
						'SerId'      => $id,
					));
					break;
					
				case 'delete':
					$msg = 'delete is not yet implemented!!!';
					break;
			}						
		}
		$content = array();

		if (!is_array($content['nm'])) $content['nm'] = $GLOBALS['phpgw']->session->appsession('ranking','cup_state');
		
		if (!is_array($content['nm']))
		{
			$content['nm'] = array(
				'get_rows'       =>	'ranking.uicups.get_rows',
				'no_filter'      => True,// I  disable the 1. filter
				'no_filter2'     => True,// I  disable the 2. filter (params are the same as for filter)
				'no_cat'         => True,// I  disable the cat-selectbox
				'bottom_too'     => True,// I  show the nextmatch-line (arrows, filters, search, ...) again after the rows
				'order'          =>	'year',// IO name of the column to sort after (optional for the sortheaders)
				'sort'           =>	'DESC',// IO direction of the sort: 'ASC' or 'DESC'
			);
			if (count($this->read_rights) == 1)
			{
				$content['nm']['col_filter']['nation'] = $this->read_rights[0];
			}
		}
		$content['msg'] = $msg;

		$this->tmpl->read('ranking.cup.list');
		$GLOBALS['phpgw_info']['flags']['app_header'] = lang('ranking').' - '.lang('cups');
		$this->tmpl->exec('ranking.uicups.index',$content,array(
			'nation' => $this->ranking_nations,
		));
	}
}
