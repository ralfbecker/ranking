<?php
/**
 * EGroupware digital ROCK Rankings - category UI
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2011-19 by Ralf Becker <RalfBecker@digitalrock.de>
 */

use EGroupware\Api;
use EGroupware\Ranking\Base;

class ranking_cats_ui extends Base
{
	/**
	 * @var array $public_functions functions callable via menuaction
	 */
	var $public_functions = array(
		'index' => true,
		'edit'  => true,
	);

	/**
	 * Edit an category
	 *
	 * @param array $_content
	 * @param string $msg
	 */
	function edit(array $_content=null,$msg='',$view=false)
	{
		if ($_GET['rkey'] || $_GET['GrpId'])
		{
			if (!$this->cats->read($_GET,array($this->cats->results_col)))
			{
				Api\Framework::window_close(lang('Entry not found !!!'));
			}
		}
		else
		{
			$this->cats->init(array('sex' => '', 'nation' => 'NULL'));
		}
		if (!is_array($_content))	// new call
		{
			// for now only admins have edit rights
			if (!$this->is_admin)
			{
				$view = true;
			}
			$matches = null;
			$referer = preg_match('/menuaction=([^&]+)/',$_SERVER['HTTP_REFERER'],$matches) ?
				$matches[1] : 'ranking.ranking_cats_ui.index';
			$results = $this->cats->data['results'];
		}
		else
		{
			$view = $_content['view'];
			$referer = $_content['referer'];
			$results = $_content['results'];

			$this->cats->init($_content['cat_data']);
			$this->cats->data_merge($_content);
			// fix mgroups as GrpId => rkey, not just GrpIds
			if ($_content['mgroups'])
			{
				$this->cats->data['mgroups'] = $this->cats->query_list('rkey', 'GrpId', array('GrpId' => $_content['mgroups']));
			}

			$button = key($_content['button']);
			//error_log(__METHOD__."() button=$button, cats->data=".array2string($this->cats->data));

			if (in_array($button, array('save', 'apply', 'delete')) && !$this->is_admin)
			{
				Api\Framework::window_close(lang('Permission denied !!!').' ('.$this->cats->data['nation'].')');
			}
			if ($button == 'save' || $button == 'apply')
			{
				if ($this->cats->not_unique())
				{
					$msg .= lang("Error: Key '%1' exists already, it has to be unique !!!",$this->cats->data['rkey']);
				}
				elseif ($this->cats->save())
				{
					$msg .= lang('Error: while saving !!!');
				}
				else
				{
					$msg .= lang('%1 saved',lang('Category'));
					Api\Framework::refresh_opener($msg, 'ranking', $this->cats->data['GrpId'], $_content['GrpId'] ? 'edit' : 'add');
					if ($button == 'save') Api\Framework::window_close();
				}
			}
			if ($button == 'delete' && (int)$_content['GrpId'])
			{
				if ($this->cats->delete(array('GrpId' => $_content['GrpId'])))
				{
					Api\Framework::refresh_opener(lang('%1 deleted',lang('Category')), 'ranking', $_content['GrpId'], 'delete');
					Api\Framework::window_close();
				}
				else
				{
					$msg = lang('Error: deleting %1 !!!',lang('Category'));
				}
			}
		}
		$content = array(
			'msg' => $msg,
			'mgroups' => array_keys((array)$this->cats->data['mgroups']),
			'sex' => $this->cats->data['sex'] ? $this->cats->data['sex'] : '',
		)+$this->cats->data;

		$sel_options = array(
			'nation' => $this->ranking_nations,
			'sex'    => $this->genders,
			'discipline' => $this->disciplines,
			'rls' => $this->rls_names,
			'vor_rls' => $this->rls_names,
			'mgroups' => $this->cats->query_list('rkey', 'GrpId', array(
				'nation' => $this->cats->data['nation'] == 'NULL' ? null : $this->cats->data['nation'],
				'sex' => $this->cats->data['sex'],
			)),
		);
		$readonlys = array(
			'button[delete]' => !$this->cats->data['GrpId'] || !$this->is_admin || $results,
			'nation' => !!$this->only_nation_athlete,
			'button[save]' => $view || !$this->is_admin,
			'button[apply]' => $view || !$this->is_admin,
		);
		// dont allow non-admins to change sex and nation, once it's been set
		if ($this->cats->data['GrpId'] && !$this->is_admin)
		{
			if ($this->cats->data['nation']) $readonlys['nation'] = true;
			if ($this->cats->data['sex']) $readonlys['sex'] = true;
		}
		if ($view)
		{
			foreach(array_keys($this->cats->data) as $name)
			{
				$readonlys[$name] = true;
			}
		}
		else
		{
			if (!$this->athlete->data['GrpId'] /* ToDo: || has athleths */)
			{
				$readonlys['delete'] = true;
			}
		}

		$GLOBALS['egw_info']['flags']['app_header'] = lang('ranking').' - '.lang($view ? 'view %1' : 'edit %1',lang('Category'));

		$tmpl = new Api\Etemplate('ranking.cat.edit');
		$tmpl->exec('ranking.ranking_cats_ui.edit',$content,
			$sel_options,$readonlys,array(
				'cat_data' => $this->cats->data,
				'view' => $view,
				'referer' => $referer,
				'results' => $results,
			),2);
	}

	/**
	 * query cats for nextmatch in the cats list
	 *
	 * @param array &$query_in
	 * @param array &$rows returned rows/cups
	 * @param array &$readonlys eg. to disable buttons based on acl
	 */
	function get_rows(&$query_in,&$rows,&$readonlys)
	{
		if (!$query_in['csv_export'])	// only store state if NOT called as csv export
		{
			Api\Cache::setSession('ranking', 'cats_state', $query_in);
		}
		$query = $query_in;

		foreach((array) $query['col_filter'] as $col => $val)
		{
			if ($val == 'NULL') $query['col_filter'][$col] = null;
		}
		$cat_filter = array(
			'nation' => array_keys($this->ranking_nations),
		);
		if ($query['col_filter']['sex'])
		{
			$cat_filter['sex'] = $query['col_filter']['sex'] == 'NULL' ? null : $query['col_filter']['sex'];
		}
		else
		{
			unset($query['col_filter']['sex']);	// no filtering
		}

		$total = $this->cats->get_rows($query,$rows,$readonlys,'',false,false,array($this->cats->results_col));

		$readonlys = array();
		foreach($rows as &$row)
		{
			if (!$this->is_admin || $row['results'])
			{
				$row['class'] = 'NoDelete';
			}
			if ($query['csv_export'])
			{
				$row['sex'] = lang($row['sex']);
			}
		}

		return $total;
	}

	/**
	 * List existing Categories
	 *
	 * @param array $_content
	 * @param string $msg
	 */
	function index(array $_content=null,$msg='')
	{
		if ($_GET['delete'] || $_content && $_content['nm']['action'] == 'delete' && $_content['nm']['selected'])
		{
			if (is_array($_content['nm']['selected']))
			{
				$id = array_shift($_content['nm']['selected']);
			}
			else
			{
				$id = $_GET['delete'];
			}
			if (!$this->is_admin || !$this->cats->read(array('GrpId' => $id),array($this->cats->results_col)))
			{
				$msg = lang('Permission denied !!!');
			}
			elseif ($this->cats->data['results'])
			{
				$msg = lang('You need to delete the results first !!!');
			}
			else
			{
				$msg = $this->cats->delete(array('GrpId' => $id)) ?
					lang('%1 deleted',lang('Category')) : lang('Error: deleting %1 !!!',lang('Category'));
			}
		}
		$content = array(
			'msg' => $msg ? $msg : $_GET['msg'],
		);
		if (!is_array($content['nm'])) $content['nm'] = Api\Cache::getSession('ranking','cats_state');

		if (!is_array($content['nm']))
		{
			$content['nm'] = array(
				'get_rows'       =>	'ranking.ranking_cats_ui.get_rows',
				'no_cat'         => True,// I  disable the cat-selectbox
				'no_filter'      => True,
				'no_filter2'     => True,
				'order'          =>	'rkey',// IO name of the column to sort after (optional for the sortheaders)
				'sort'           =>	'ASC',// IO direction of the sort: 'ASC' or 'DESC'
				'csv_fields'     => false,
				'row_id'         => 'GrpId',
			);
			if ($this->only_nation_athlete)
			{
				$content['nm']['col_filter']['nation'] = $this->only_nation_athlete;
			}
			// also set nation filter, if grants are from a single nation
			elseif (count($fed_nations = $this->federation->get_user_nations()) == 1)
			{
				$content['nm']['col_filter']['nation'] = array_pop($fed_nations);
			}
		}
		$content['nm']['actions'] = $this->get_actions();
		$readonlys['add'] = !$this->is_admin;

		$GLOBALS['egw_info']['flags']['app_header'] = lang('ranking').' - '.lang('Categories');
		$this->set_ui_state();

		$tmpl = new Api\Etemplate('ranking.cat.list');
		$tmpl->exec('ranking.ranking_cats_ui.index',$content,array(
			'nation' => $this->ranking_nations,
			'sex'    => array_merge($this->genders,array(''=>'')),	// no none
			'discipline' => $this->disciplines,
		),$readonlys);
	}

	/**
	 * Return actions for start-/result-lists
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
				'url' => 'menuaction=ranking.ranking_cats_ui.edit&GrpId=$id',
				'popup' => '660x400',
				'group' => $group=0,
			),
			'add' => array(
				'caption' => 'Add',
				'url' => 'menuaction=ranking.ranking_cats_ui.edit',
				'popup' => '660x400',
				'disabled' => $this->is_admin,
				'group' => $group,
			),
			'delete' => array(
				'caption' => 'Delete',
				'disableClass' => 'noDelete',	// checks has result
				'allowOnMultiple' => false,
				'confirm' => 'Delete this category',
				'disableClass' => 'NoDelete',
				'group' => $group=5,
			),
		);

		return $actions;
	}
}