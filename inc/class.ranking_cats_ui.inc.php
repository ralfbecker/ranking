<?php
/**
 * EGroupware digital ROCK Rankings - category UI
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2011-17 by Ralf Becker <RalfBecker@digitalrock.de>
 */

class ranking_cats_ui extends ranking_bo
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
				$msg .= lang('Entry not found !!!');
			}
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

			list($button) = @each($_content['button']);

			if ($button == 'save' || $button == 'apply')
			{
				if ($this->is_admin)
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
					}
				}
				else
				{
					$msg .= lang('Permission denied !!!').' ('.$this->cats->data['nation'].')';
				}
				$link = $GLOBALS['egw']->link('/index.php',array(
					'menuaction' => $_content['referer'],
					'msg' => $msg,
				)+($_content['row'] ? array('row['.$_content['row'].']' => $this->cats->data['GrpId']) : array()));
				$js = "window.opener.location='$link'; $js";
			}
			if ($button == 'delete')
			{
				$link = $GLOBALS['egw']->link('/index.php',array(
					'menuaction' => 'ranking.ranking_cats_ui.index',
					'delete' => $this->cats->data['GrpId'],
				));
				$js = "window.opener.location='$link';";
			}
			if (in_array($button,array('save','delete','cancel')))
			{
				echo "<html><head><script>\n$js;\nwindow.close();\n</script></head></html>\n";
				common::egw_exit();
			}
		}
		$content = $this->cats->data + array(
			'msg' => $msg,
		);
		$sel_options = array(
			'nation' => $this->ranking_nations,
			'sex'    => $this->genders,
			'discipline' => $this->disciplines,
			'rls' => $this->rls_names,
			'vor_rls' => $this->rls_names,
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
		$tmpl = new etemplate('ranking.cat.edit');
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
		//echo __METHOD__."() query="; _debug_array($query_in);
		if (!$query_in['csv_export'])	// only store state if NOT called as csv export
		{
			$GLOBALS['egw']->session->appsession('ranking','cats_state',$query_in);
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

		//_debug_array($rows);

		$readonlys = array();
		foreach($rows as &$row)
		{
			if (!$this->is_admin || $row['results'])
			{
				$readonlys["delete[$row[GrpId]]"] = true;
			}
			if ($query['csv_export'])
			{
				$row['sex'] = lang($row['sex']);
			}
		}

		if ($this->debug)
		{
			echo "<p>".__METHOD__."(".print_r($query,true).") rows ="; _debug_array($rows);
			_debug_array($readonlys);
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
		if ($_GET['delete'] || is_array($_content['nm']['rows']['delete']))
		{
			if (is_array($_content['nm']['rows']['delete']))
			{
				list($id) = each($_content['nm']['rows']['delete']);
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
		if (!is_array($content['nm'])) $content['nm'] = $GLOBALS['egw']->session->appsession('ranking','cats_state');

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
		$readonlys['nm[rows][edit][0]'] = !$this->is_admin;

		$GLOBALS['egw_info']['flags']['app_header'] = lang('ranking').' - '.lang('Categories');
		$this->set_ui_state();
		$tmpl = new etemplate('ranking.cat.list');
		$tmpl->exec('ranking.ranking_cats_ui.index',$content,array(
			'nation' => $this->ranking_nations,
			'sex'    => array_merge($this->genders,array(''=>'')),	// no none
			'discipline' => $this->disciplines,
		),$readonlys);
	}
}