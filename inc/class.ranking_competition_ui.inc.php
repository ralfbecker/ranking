<?php
/**
 * EGroupware digital ROCK Rankings - competition UI
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2006-16 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$
 */

class ranking_competition_ui extends ranking_bo
{
	/**
	 * @var array $public_functions functions callable via menuaction
	 */
	var $public_functions = array(
		'index' => true,
		'edit'  => true,
		'view'  => true,
	);
	var $attachment_type = array();
	var $display_athlete_types = array(
		'' => 'Default',
		ranking_competition::NATION => 'Nation',
		ranking_competition::FEDERATION => 'Federation',
		ranking_competition::CITY => 'City',
		ranking_competition::PC_CITY => 'PC City',
		ranking_competition::NATION_PC_CITY => 'Nation PC City',
		ranking_competition::PARENT_FEDERATION => 'Parent federation',
	);

	function __construct()
	{
		parent::__construct();

		$this->attachment_type = array(
			'info'      => lang('Information PDF'),
			'startlist' => lang('Startlist PDF'),
			'result'    => lang('Result PDF'),
			'logo'      => lang('Competition logo'),
			'sponsors'  => lang('Sponsor logos'),
		);
	}

	/**
	 * View a competition
	 */
	function view()
	{
		$this->edit(null,'',true);
	}

	/**
	 * Edit a competition
	 *
	 * @param array $_content =null
	 * @param string $msg =''
	 */
	function edit($_content=null,$msg='',$view=false)
	{
		$tmpl = new etemplate('ranking.comp.edit');

		if (($_GET['rkey'] || $_GET['WetId']) && !$this->comp->read($_GET))
		{
			$msg .= lang('Entry not found !!!');
		}
		// set and enforce nation ACL
		if (!is_array($_content))	// new call
		{
			if (!$_GET['WetId'] && !$_GET['rkey'])
			{
				$this->check_set_nation_fed_id($this->comp->data);
			}
			// we have no edit-rights for that nation
			if (!$this->acl_check_comp($this->comp->data))
			{
				$view = true;
			}
		}
		else
		{
			//echo "<br>ranking_competition_ui::edit: content ="; _debug_array($_content);
			$this->comp->data = $_content['comp_data'];
			$old_rkey = $_content['comp_data']['rkey'];
			unset($_content['comp_data']);

			if (substr($_content['homepage'], 0, 4) === 'www.') $_content['homepage'] = 'http://'.$_content['homepage'];

			$view = $_content['view'] && !($_content['edit'] && $this->acl_check_comp($this->comp->data));

			if (!$view && $this->only_nation_edit)
			{
				$this->check_set_nation_fed_id($this->comp->data);
			}
			if (!$_content['cat_id']) $_content['cat_id'] = ranking_so::cat_rkey2id($_content['nation']);

			if ($_content['serie'] && $_content['serie'] != $this->comp->data['serie'] &&
				$this->cup->read(array('SerId' => $_content['serie'])))
			{
				foreach((array)$this->cup->data['presets']+array('gruppen' => $this->cup->data['gruppen']) as $key => $val)
				{
					$_content[$key] = $val;
				}
			}
			$this->comp->data_merge($_content);
			//echo "<br>ranking_competition_ui::edit: comp->data ="; _debug_array($this->comp->data);

			if (!$view  && ($_content['save'] || $_content['apply']) && $this->acl_check_comp($this->comp->data))
			{
				if (!$this->comp->data['rkey'])
				{
					// generate an rkey using the cup's rkey or the year as prefix
					$pattern = date('y');
					if ($this->comp->data['serie'] && $this->cup->read(array('SerId' => $this->comp->data['serie'])))
					{
						$pattern = $this->cup->data['rkey'];
						if (strlen($pattern) > 5) $pattern = str_replace('_','',$pattern);
					}
					$n = 0;
					do
					{
						$this->comp->data['rkey'] = $pattern . '_' . strtoupper(
							(!$this->comp->data['dru_bez'] ? ++$n :					// number starting with 1
							(++$n < 2 ? substr($this->comp->data['dru_bez'],0,2) : 	// 2 char shortcut from dru_bez
							$this->comp->data['dru_bez'][0].$n)));					// 1. char from dru_bez plus number
					}
					while ($this->comp->not_unique());
				}
				elseif ($this->comp->not_unique())
				{
					$msg .= lang("Error: Key '%1' exists already, it has to be unique !!!",$this->comp->data['rkey']);
				}
				if ($this->comp->save())
				{
					$msg .= lang('Error: while saving !!!');
				}
				else
				{
					$msg .= lang('%1 saved',lang('Competition'));

					//echo "<p>renaming attachments from '?$old_rkey' to '?".$this->comp->data['rkey']."'</p>\n";
					if ($old_rkey && $this->comp->data['rkey'] != $old_rkey &&
						!$this->comp->rename_attachments($old_rkey))
					{
						$msg .= ', '.lang("Error: renaming the attachments !!!");
					}
					foreach(array_keys($this->comp->attachment_prefixes) as $type)
					{
						$file = $_content['upload_'.$type];
						if (is_array($file) && $file['tmp_name'] && $file['name'])
						{
							//echo $type; _debug_array($file);
							if ($type != 'logo' && $type != 'sponsors')
							{
								$extension = '.pdf';
								$error_msg = $file['type'] != 'application/pdf' &&
									strtolower(substr($file['name'],-4)) != $extension ?
									lang('File is not a PDF') : false;
							}
							else
							{
								$error_msg = ($extension=ranking_competition::is_image($file['name'],$file['type'])) ? false :
									lang('File is not an image (%1)',str_replace(array('\\','$'),'',implode(', ',ranking_competition::$image_types)));
							}
							if (!$error_msg && $this->comp->attach_files(array($type => $file['tmp_name']),$error_msg,null,$extension))
							{
								$msg .= ",\n".lang("File '%1' successful attached as %2",$file['name'],$this->attachment_type[$type]);
							}
							else
							{
								$msg .= ",\n".lang("Error: attaching '%1' as %2 (%3) !!!",$file['name'],$this->attachment_type[$type],$error_msg);
							}
						}
					}
					if ($_content['save'] || $_content['apply'])
					{
						$link = egw::link($_content['referer'],array(
							'msg' => $msg,
						));
						$js = "window.opener.location='$link';";
					}
				}
			}
			if ($_content['delete'])
			{
				$link = egw::link('/index.php',array(
					'menuaction' => 'ranking.ranking_competition_ui.index',
					'delete' => $this->comp->data['WetId'],
				));
				$js = "window.opener.location='$link';";
			}
			if ($_content['copy'])
			{
				unset($this->comp->data['WetId']);
				unset($this->comp->data['rkey']);
				unset($this->comp->data['datum']);
				$msg .= lang('Entry copied - edit and save the copy now.');
			}
			if ($_content['save'] || $_content['delete'])
			{
				echo "<html><head><script>\n$js;\nwindow.close();\n</script></head></html>\n";
				common::egw_exit();
			}

			if ($_content['remove'] && $this->acl_check_comp($this->comp->data))
			{
				list($type) = each($_content['remove']);

				$msg .= $this->comp->remove_attachment($type) ?
					lang('Removed the %1',$this->attachment_type[$type]) :
					lang('Error: removing the %1 !!!',$this->attachment_type[$type]);
			}
		}
		$content = $this->comp->data + array(
			'msg'  => $msg,
			'tabs' => $_content['tabs'],
			'referer' => $_content['referer'] ? $_content['referer'] :
				common::get_referer('/index.php?menuaction=ranking.ranking_competition_ui.index'),
		);
		foreach((array) $this->comp->attachments(null,false,false) as $type => $linkdata)
		{
			$content['files'][$type] = array(
				'icon' => $type != 'logo' && $type != 'sponsors' ? $type :
					egw::link($linkdata),
				'file' => $this->comp->attachment_path($type),
				'link' => $linkdata,
			);
		}
		$content['quota_extra'][] = array('fed' => '');		// one extra line to add a new fed or cat value
		$content['prequal_extra'][] = array('cat' => '');
		$content['quali_preselected'][] = array('cat' => '');

		$sel_options = array(
			'pkte'      => $this->pkt_names,
			'feld_pkte' => array(0 => lang('none')) + $this->pkt_names,
			'serie'     => array(0 => lang('none')) + $this->cup->names(array(
				'nation'=> $this->comp->data['nation'],
				'fed_id'=> $this->comp->data['fed_id'])),
			'nation'    => $this->ranking_nations,
			'fed'       => $this->federation->get_competition_federations($this->comp->data['nation']),
			'gruppen'   => $this->cats->names(array('nation' => $this->comp->data['nation'])),
			'cat'       => $this->cats->names(array('nation' => $this->comp->data['nation']),-1),
			'prequal_comps' => $this->comp->names(array(
				!$this->comp->data['datum'] ? 'datum > \''.(date('Y')-3).'\'' :
					'datum < '.$this->db->quote($this->comp->data['datum']).' AND datum > \''.((int)$this->comp->data['datum']-3).'-01-01\'',
				'nation' => $this->comp->data['nation'],
			)),
			'host_nation' => $this->athlete->distinct_list('nation'),
			'discipline' => $this->disciplines,
			'display_athlete' => $this->display_athlete_types,
			'fed_id'       => $this->federation->federations($this->comp->data['nation'], true),
			'selfregister' => $this->comp->selfregister_types,
			'open_comp'    => $this->comp->open_comp_types,
			'prequal_type' => $this->comp->prequal_types,
			'continent'    => ranking_federation::$continents,
		);
		// select a category parent fitting to the nation
		$content['cat_parent'] = ranking_so::cat_rkey2id($content['nation'] ? $content['nation'] : 'int');
		$content['cat_parent_name'] = ($content['nation']? $content['nation'] : 'Int.').' '.lang('Competitions');

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
				'delete' => !$this->comp->data[$this->comp->db_key_cols[$this->comp->autoinc_id]] ||
					!$this->acl_check_comp($this->comp->data),
				'nation' => !!$this->only_nation_edit,
				'fed_id' => is_numeric($this->only_nation_edit),
				'edit'   => !$view || !$this->acl_check_comp($this->comp->data),
				'copy'   => !$this->comp->data[$this->comp->db_key_cols[$this->comp->autoinc_id]],
			);
			// if only federation rights (no national rights), switch of ranking tab, to not allow changes there
			if (!$this->acl_check($this->comp->data['nation'], EGW_ACL_EDIT))
			{
				$readonlys['tabs'] = array('ranking' => true);
				// ToDo: limit category to state category(s)
			}
		}
		$GLOBALS['egw_info']['flags']['app_header'] = lang('ranking').' - '.lang($view ? 'view %1' : 'edit %1',lang('competition'));

		$tmpl->exec('ranking.ranking_competition_ui.edit',$content,
			$sel_options,$readonlys,array(
				'comp_data' => $this->comp->data,
				'view' => $view,
				'referer' => $content['referer'],
			),2);
	}

	/**
	 * query competitions for nextmatch in the competitions list
	 *
	 * @param array $query
	 * @param array &$rows returned rows/competitions
	 * @param array &$readonlys eg. to disable buttons based on acl
	 */
	function get_rows($query,&$rows,&$readonlys)
	{
		$GLOBALS['egw']->session->appsession('ranking','comp_state',$query);
		if (!$this->is_admin && !in_array($query['col_filter']['nation'],$this->read_rights))
		{
			$query['col_filter']['nation'] = $this->read_rights;
			if (($null_key = array_search('NULL',$this->read_rights)) !== false)
			{
				$query['col_filter']['nation'][$null_key] = null;
			}
		}
		$nation = $query['col_filter']['nation'];

		if ($query['cat_id'])
		{
			$query['col_filter']['cat_id'] = $GLOBALS['egw']->categories->return_all_children($query['cat_id']);
		}
		elseif ($query['cat_id'] === '0')
		{
			$query['col_filter']['cat_id'] = null;
		}
		else
		{
			unset($query['col_filter']['cat_id']);
		}

		foreach((array) $query['col_filter'] as $col => $val)
		{
			if ($val == 'NULL') $query['col_filter'][$col] = null;
		}
		// set the cups based on the selected nation
		$cups = $this->cup->names(!$nation ? array() : array('nation' => $query['col_filter']['nation']),true);
		// unset the cup, if it's not (longer) in the selected nations cups
		if (!isset($cups[$query['col_filter']['serie']])) $query['col_filter']['serie'] = '';

		$total = $this->comp->get_rows($query,$rows,$readonlys);

		$readonlys = array();
		foreach($rows as $n => $row)
		{
			foreach((array) $this->comp->attachments($row,true) as $type => $linkdata)
			{
				$rows[$n]['pdf'][$type] = array(
					'icon' => $type,
					'file' => $this->comp->attachment_path($type,$row),
					'link' => $linkdata.',_blank',
					'label'=> $this->attachment_type[$type],
				);
			}
			$readonlys["edit[$row[WetId]]"] = $readonlys["delete[$row[WetId]]"] = !$this->acl_check_comp($row);
		}
		// set the cups based on the selected nation
		$rows['sel_options']['serie'] = $cups;

		if ($this->debug)
		{
			echo "<p>ranking_competition_ui::get_rows(".print_r($query,true).") rows ="; _debug_array($rows);
			_debug_array($readonlys);
		}
		return $total;
	}

	/**
	 * List existing competitions
	 *
	 * @param array $_content =null
	 * @param string $msg =''
	 */
	function index($_content=null,$msg='')
	{
		$tmpl = new etemplate('ranking.comp.list');

		if ($_content['nm']['rows']['delete'] || $_GET['delete'] > 0)
		{
			if ($_content['nm']['rows']['delete'])
			{
				list($id) = each($_content['nm']['rows']['delete']);
			}
			elseif($_GET['delete'] > 0)
			{
				$id = (int)$_GET['delete'];
			}
			if (!$this->comp->read(array('WetId' => $id)) || !$this->acl_check_comp($this->comp->data))
			{
				$msg = lang('Permission denied !!!');
			}
			elseif ($this->comp->has_results($id))
			{
				$msg = lang('You need to delete the results first !!!');
			}
			else
			{
				$msg = $this->comp->delete(array('WetId' => $id)) ? lang('%1 deleted',lang('Competition')) :
					lang('Error: deleting %1 !!!',lang('Competition'));
			}
		}
		$content = array();

		$content['nm'] = $GLOBALS['egw']->session->appsession('ranking','comp_state');

		if (!is_array($content['nm']))
		{
			$content['nm'] = array(
				'get_rows'       =>	'ranking.ranking_competition_ui.get_rows',
				'no_filter'      => True,// I  disable the 1. filter
				'no_filter2'     => True,// I  disable the 2. filter (params are the same as for filter)
				'bottom_too'     => True,// I  show the nextmatch-line (arrows, filters, search, ...) again after the rows
				'order'          =>	'datum',// IO name of the column to sort after (optional for the sortheaders)
				'sort'           =>	'DESC',// IO direction of the sort: 'ASC' or 'DESC'
				'csv_fields'     => false,
			);
			// do not consider "XYZ" when setting a default filter
			$read_rights = array_values(array_diff($this->read_rights, array('XYZ')));
			if (count($read_rights) == 1)
			{
				$content['nm']['col_filter']['nation'] = $read_rights[0];
				$content['nm']['cat_parent'] = ranking_so::cat_rkey2id($read_rights[0]);
			}
			else
			{
				$content['nm']['cat_parent'] = ranking_so::cat_rkey2id('parent');
			}
		}
		$content['msg'] = $msg ? $msg : $_GET['msg'];
		$readonlys['nm[rows][edit][0]'] = !$this->edit_rights && !$this->is_admin;

		$GLOBALS['egw_info']['flags']['app_header'] = lang('ranking').' - '.lang('competitions');
		$tmpl->exec('ranking.ranking_competition_ui.index',$content,array(
			'nation' => $this->ranking_nations,
//			'serie'  => $this->cup->names(array(),true),
			'cat_id' => array(lang('None')),
		),$readonlys);
	}
}
