<?php
/**
 * EGroupware digital ROCK Rankings - cups UI
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2006-19 by Ralf Becker <RalfBecker@digitalrock.de>
 */

namespace EGroupware\Ranking\Cup;

use EGroupware\Api;
use EGroupware\Ranking\Base;
use EGroupware\Ranking\Federation;

class Ui extends Base
{
	/**
	 * @var array $public_functions functions callable via menuaction
	 */
	var $public_functions = array(
		'index' => true,
		'edit'  => true,
	);

	/**
	 * Edit a cup
	 *
	 * @param array $_content
	 * @param string $msg
	 */
	function edit($_content=null,$msg='',$view=false)
	{
		$tmpl = new Api\Etemplate('ranking.cup.edit');

		if (($_GET['rkey'] || $_GET['SerId'] || $_GET['copy']) && !$this->cup->read(!empty($_GET['copy']) ? $_GET['copy'] : $_GET))
		{
			Api\Framework::window_close(lang('Entry not found !!!'));
		}
		// set and enforce nation ACL
		if (!is_array($_content))	// new call
		{
			if (!$_GET['SerId'] && !$_GET['rkey'] && !$_GET['copy'])
			{
				$this->check_set_nation_fed_id($this->cup->data);

				$this->cup->data['presets']['average_ex_aquo'] = true;
			}
			// we have no edit-rights for that nation
			if (!$this->acl_check_comp($this->cup->data))
			{
				$view = true;
			}
		}
		else
		{
			$button = key($_content['button'] ?? []);
			unset($_content['button']);

			$view = $_content['view'] && !($button === 'edit' && $this->acl_check_comp($this->cup->data));

			if (!$view && $this->only_nation_edit)
			{
				$this->check_set_nation_fed_id($this->cup->data);
			}

			if (!$view && is_array($_content['max_per']))
			{
				$_content['max_per_cat'] = array();
				foreach($_content['max_per'] as $row => $max)
				{
					if ((int)$max >= -1 && !empty($_content['per_cat'][$row]))
					{
						$_content['max_per_cat'][$_content['per_cat'][$row]] = (int)$max;
					}
				}
			}
			$this->cup->data = $_content['cup_data'];
			unset($_content['cup_data']);
			$this->cup->data_merge($_content);

			if (!$view && in_array($button, ['save','apply']) && $this->acl_check_comp($this->cup->data))
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
					$button = 'apply';
				}
				else
				{
					$msg .= lang('%1 saved',lang('Cup'));
				}
				Api\Framework::refresh_opener($msg, 'ranking', $this->cup->data['SerId'], $_content['SerId'] ? 'edit' : 'add');
				if ($button === 'save') Api\Framework::window_close();
			}
			if ($button === 'delete' && $this->acl_check_comp($this->cup->data) &&
				!$this->cup->data['num_comps'])
			{
				if ($this->cup->delete(array('SerId' => $this->cup->data['SerId'])))
				{
					Api\Framework::refresh_opener(lang('%1 deleted', lang('Cup')), 'ranking', $this->cup->data['SerId'], 'delete');
					Api\Framework::window_close();
				}
				else
				{
					$msg = lang('Error: deleting %1 !!!', lang('Cup'));
				}
			}
		}
		if ($button === 'copy' || !empty($_GET['copy']))
		{
			unset($this->cup->data['SerId']);
			$this->cup->data['name'] = preg_replace('/\d{4}/', date('Y'), $this->cup->data['name']);
			$this->cup->data['rkey'] = preg_replace('/\d{2}/', date('y'), $old_rkey=$this->cup->data['rkey']);
			if ($old_rkey === $this->cup->data['rkey']) unset($this->cup->data['rkey']);
			$msg .= lang('Entry copied - edit and save the copy now.');
		}
		$content = $this->cup->data + array(
			'msg' => $msg,
			'tabs' => $_content['tabs'],
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
			'continent' => Federation::$continents,
			'display_athlete' => $this->display_athlete_types,
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
		$content['average_ex_aquo'] = $content['presets']['average_ex_aquo'];

		// select a category parent fitting to the nation
		$content['cat_parent'] = self::cat_rkey2id($content['nation'] ? $content['nation'] : 'int');
		$content['cat_parent_name'] = ($content['nation']? $content['nation'] : 'Int.').' '.lang('Competitions');

		if ($view)
		{
			$readonlys = array(
				'__ALL__' => true,
				'button[cancel]' => false,
				'button[edit]'   => !$this->acl_check($content['nation'],self::ACL_EDIT),
			);
		}
		else
		{
			$readonlys = array(
				'button[delete]' => !$this->cup->data[$this->cup->db_key_cols[$this->cup->autoinc_id]] ||
					$this->cup->data['num_comps'],
				'nation' => !!$this->only_nation_edit,
				'fed_id' => is_numeric($this->only_nation_edit),
				'button[edit]'   => true,
			);
			// if only federation rights (no national rights), switch of ranking tab, to not allow changes there
			if (!$this->acl_check($this->comp->data['nation'], self::ACL_EDIT))
			{
				$readonlys['tabs'] = array('presets' => true);
				$readonlys['max_rang'] = true;
				// ToDo: limit category to state category(s)
			}
		}
		$GLOBALS['egw_info']['flags']['app_header'] = lang('ranking').' - '.lang($view ? 'view %1' : 'edit %1',lang('cup'));
		$tmpl->exec('ranking.'.self::class.'.edit',$content,
			$sel_options,$readonlys,array(
				'cup_data' => $this->cup->data,
				'view' => $view,
				'min_disciplines_per_cat' => $content['min_disciplines_per_cat'],
			),2);
	}

	/**
	 * query cups for nextmatch in the cups list
	 *
	 * @param array $query
	 * @param array &$rows returned rows/cups
	 * @param array &$readonlys eg. to disable buttons based on Acl
	 */
	function get_rows($query,&$rows,&$readonlys)
	{
		Api\Cache::setSession('cup_state', 'ranking', $query);

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
		foreach($rows as &$row)
		{
			if ($row['num_comps'] || !$this->acl_check_comp($row))
			{
				$row['class'] = 'NoDelete';
			}
		}
		return $total;
	}

	/**
	 * List existing cups
	 *
	 * @param array $_content
	 * @param string $msg
	 */
	function index($_content=null,$msg='')
	{
		if ($_content && $_content['nm']['action'] && $_content['nm']['selected'])
		{
			foreach($_content['nm']['selected'] as $id)
			{
				switch($_content['nm']['action'])
				{
					case 'delete':
						if (!$this->cup->read(array('SerId' => $id)) ||
							$this->cup->data['num_comps'] ||
							!$this->acl_check_comp($this->cup->data))
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
		}
		$content = array('nm' => Api\Cache::getSession('cup_state', 'ranking'));

		if (!is_array($content['nm']))
		{
			$content['nm'] = array(
				'get_rows'       =>	'ranking.'.self::class.'.get_rows',
				'no_filter'      => True,// I  disable the 1. filter
				'no_filter2'     => True,// I  disable the 2. filter (params are the same as for filter)
				'no_cat'         => True,// I  disable the cat-selectbox
				'bottom_too'     => True,// I  show the nextmatch-line (arrows, filters, search, ...) again after the rows
				'order'          =>	'year',// IO name of the column to sort after (optional for the sortheaders)
				'sort'           =>	'DESC',// IO direction of the sort: 'ASC' or 'DESC'
				'csv_fields'     => false,
				'dataStorePrefix' => 'ranking_cup',
				'row_id'         => 'SerId',
			);
			// do not consider "XYZ" when setting a default filter
			$read_rights = array_values(array_diff($this->read_rights, array('XYZ')));
			if (count($read_rights) == 1)
			{
				$content['nm']['col_filter']['nation'] = $read_rights[0];
			}
		}
		// actions are NOT stored in session
		$content['nm']['actions'] = $this->get_actions();

		$content['msg'] = $msg;
		$this->set_ui_state();

		$GLOBALS['egw_info']['flags']['app_header'] = lang('ranking').' - '.lang('cups');
		$tmpl = new Api\Etemplate('ranking.cup.list');
		$tmpl->exec('ranking.'.self::class.'.index',$content,array(
			'nation' => $this->ranking_nations,
		));
	}

	/**
	 * Return actions for cup list
	 *
	 * @return array
	 */
	function get_actions()
	{
		$actions =array(
			'edit' => array(
				'caption' => 'Edit',
				'default' => true,
				'allowOnMultiple' => false,
				'url' => 'menuaction=ranking.'.self::class.'.edit&SerId=$id',
				'popup' => '720x450',
				'group' => $group=0,
			),
			'add' => array(
				'caption' => 'Add',
				'url' => 'menuaction=ranking.'.self::class.'.edit',
				'popup' => '720x450',
				'disabled' => !$this->edit_rights && !$this->is_admin,
				'group' => $group,
			),
			'copy' => array(
				'caption' => 'Copy',
				'url' => 'menuaction=ranking.'.self::class.'.edit&copy=$id',
				'popup' => '720x450',
				'disabled' => !$this->edit_rights && !$this->is_admin,
				'group' => $group,
			),
			'delete' => array(
				'caption' => 'Delete',
				'disableClass' => 'noDelete',	// checks has result
				'allowOnMultiple' => false,
				'confirm' => 'Delete this cup',
				'disableClass' => 'NoDelete',
				'group' => $group=5,
			),
		);

		return $actions;
	}
}