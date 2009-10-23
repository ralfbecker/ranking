<?php
/**
 * eGroupWare digital ROCK Rankings - cups UI
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2006 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$
 */

require_once(EGW_INCLUDE_ROOT.'/ranking/inc/class.boranking.inc.php');
require_once(EGW_INCLUDE_ROOT.'/etemplate/inc/class.etemplate.inc.php');

class uicups extends boranking
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
				$this->cup->data['nation'] = $this->edit_rights[0];
			}
			// we have no edit-rights for that nation
			if (!$this->acl_check($this->cup->data['nation'],EGW_ACL_EDIT))
			{
				$view = true;
			}
		}
		else
		{
			$view = $content['view'] && !($content['edit'] && $this->acl_check($content['nation'],EGW_ACL_EDIT));

			if (!$view && $this->only_nation_edit) $content['nation'] = $this->only_nation_edit;

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
			//echo "<br>uicups::edit: content ="; _debug_array($content);
			$this->cup->data = $content['cup_data'];
			unset($content['cup_data']);
			$this->cup->data_merge($content);

			//echo "<br>uicups::edit: cup->data ="; _debug_array($this->cup->data);

			if (($content['save'] || $content['apply']) && $this->acl_check($content['nation'],EGW_ACL_EDIT))
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
			}
			if ($content['cancel'])
			{
				$tmpl->location(array('menuaction'=>'ranking.uicups.index'));
			}
			if ($content['delete'] && $this->acl_check($content['nation'],EGW_ACL_EDIT))
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
		$tabs = 'general|ranking|presets';
		$content = $this->cup->data + array(
			'msg' => $msg,
			$tabs => $content[$tabs],
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
		$readonlys = array(
			'delete' => !$this->cup->data[$this->cup->db_key_cols[$this->cup->autoinc_id]],
			'nation' => !!$this->only_nation_edit,
			'edit'   => !($view && $this->acl_check($content['nation'],EGW_ACL_EDIT)),
		);
		if ($view)
		{
			foreach($this->cup->data as $name => $val)
			{
				$readonlys[$name] = true;
			}
			$readonlys['delete'] = $readonlys['save'] = $readonlys['apply'] = true;
			$readonlys['presets[name]'] = true;
			$readonlys['presets'] = array();
			foreach(array('name','pkte','pkt_bis','faktor','feld_pkte','feld_bis') as $name)
			{
				$readonlys['presets'][$name] = true;
			}
		}
		$GLOBALS['egw_info']['flags']['app_header'] = lang('ranking').' - '.lang($view ? 'view %1' : 'edit %1',lang('cup'));
		$tmpl->exec('ranking.uicups.edit',$content,
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
			if (!$this->acl_check($row['nation'],EGW_ACL_EDIT))
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
						'menuaction' => 'ranking.uicups.view',
						'SerId'      => $id,
					));
					break;

				case 'edit':
					$tmpl->location(array(
						'menuaction' => 'ranking.uicups.edit',
						'SerId'      => $id,
					));
					break;

				case 'delete':
					if (!$this->is_admin && $this->cup->read(array('SerId' => $id)) &&
						!in_array($this->cup->data['nation'],$this->edit_rights))
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
				'get_rows'       =>	'ranking.uicups.get_rows',
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
		$tmpl->exec('ranking.uicups.index',$content,array(
			'nation' => $this->ranking_nations,
		));
	}
}
