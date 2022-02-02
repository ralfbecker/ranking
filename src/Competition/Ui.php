<?php
/**
 * EGroupware digital ROCK Rankings - competition UI
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2006-19 by Ralf Becker <RalfBecker@digitalrock.de>
 */

namespace EGroupware\Ranking\Competition;

use EGroupware\Api;
use EGroupware\Api\Egw;
use EGroupware\Ranking\Base;
use EGroupware\Ranking\Federation;
use EGroupware\Ranking\Competition;

class Ui extends Base
{
	/**
	 * @var array $public_functions functions callable via menuaction
	 */
	var $public_functions = array(
		'index' => true,
		'edit'  => true,
	);
	var $attachment_type = array();

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
	 * Edit a competition
	 *
	 * @param array $_content =null
	 * @param string $msg =''
	 */
	function edit($_content=null,$msg='',$view=false)
	{
		if (($_GET['rkey'] || $_GET['WetId'] || $_GET['copy']) && !$this->comp->read(!empty($_GET['copy']) ? $_GET['copy'] : $_GET))
		{
			Api\Framework::window_close(lang('Entry not found !!!'));
		}
		// set and enforce nation ACL
		if (!is_array($_content))	// new call
		{
			if (!$_GET['WetId'] && !$_GET['rkey'] && !$_GET['copy'])
			{
				$this->check_set_nation_fed_id($this->comp->data);

				$this->comp->data['average_ex_aquo'] = true;
			}
			// we have no edit-rights for that nation
			if (!$this->acl_check_comp($this->comp->data))
			{
				$view = true;
			}
		}
		else
		{
			$button = key($_content['button'] ?? []);
			unset($_content['button']);

			$this->comp->data = $_content['comp_data'];
			$old_rkey = $_content['comp_data']['rkey'];
			unset($_content['comp_data']);

			if (substr($_content['homepage'], 0, 4) === 'www.') $_content['homepage'] = 'https://'.$_content['homepage'];

			$view = $_content['view'] && !($_content['edit'] && $this->acl_check_comp($this->comp->data));

			if (!$view && $this->only_nation_edit)
			{
				$this->check_set_nation_fed_id($this->comp->data);
			}
			if (!$_content['cat_id']) $_content['cat_id'] = self::cat_rkey2id($_content['nation']);

			if ($_content['serie'] && $_content['serie'] != $this->comp->data['serie'] &&
				$this->cup->read(array('SerId' => $_content['serie'])))
			{
				foreach((array)$this->cup->data['presets']+array('gruppen' => $this->cup->data['gruppen']) as $key => $val)
				{
					if ((string)$val !== '') $_content[$key] = $val;
				}
			}
			$this->comp->data_merge($_content);

			if (!$view  && in_array($button, ['save', 'apply', 'update_prequal']) && $this->acl_check_comp($this->comp->data))
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
				try
				{
					if ($this->comp->save())
					{
						$msg .= lang('Error: while saving !!!');
					}
					else
					{
						$msg .= lang('%1 saved', lang('Competition'));

						//echo "<p>renaming attachments from '?$old_rkey' to '?".$this->comp->data['rkey']."'</p>\n";
						if ($old_rkey && $this->comp->data['rkey'] != $old_rkey &&
							!$this->comp->rename_attachments($old_rkey))
						{
							$msg .= ', ' . lang("Error: renaming the attachments !!!");
						}
						foreach (array_keys($this->comp->attachment_prefixes) as $type)
						{
							$file = $_content['upload_' . $type];
							if (is_array($file) && $file['tmp_name'] && $file['name'])
							{
								//echo $type; _debug_array($file);
								if ($type != 'logo' && $type != 'sponsors')
								{
									$extension = '.pdf';
									$error_msg = $file['type'] != 'application/pdf' &&
									strtolower(substr($file['name'], -4)) != $extension ?
										lang('File is not a PDF') : false;
								}
								else
								{
									$error_msg = ($extension = Competition::is_image($file['name'], $file['type'])) ? false :
										lang('File is not an image (%1)', str_replace(array('\\', '$'), '', implode(', ', Competition::$image_types)));
								}
								if (!$error_msg && $this->comp->attach_files(array($type => $file['tmp_name']), $error_msg, null, $extension))
								{
									$msg .= ",\n" . lang("File '%1' successful attached as %2", $file['name'], $this->attachment_type[$type]);
								}
								else
								{
									$msg .= ",\n" . lang("Error: attaching '%1' as %2 (%3) !!!", $file['name'], $this->attachment_type[$type], $error_msg);
								}
							}
						}
						if ($button === 'update_prequal')
						{
							$deleted = $unprequalified = $changed = null;
							$prequalified = $this->registration->update_prequalified($this->comp->data['WetId'], $deleted, $unprequalified, $changed);
							if ($prequalified === false)
							{
								$msg .= "\n" . lang('Prequalified will be generated automatic on January 1st, no need for a manual update.');
							}
							else
							{
								$msg .= "\n" . lang('%1 prequalified athletes in registration, %2 changed or added.',
										$prequalified, $changed);
							}
							if ($deleted)
							{
								$msg .= "\n" . lang('%1 no longer prequalified athletes (without registration) deleted.', $deleted);
							}
							if ($unprequalified)
							{
								$msg .= "\n" . lang('%1 no longer prequalified athletes with registration, you need to check quota!',
										count(call_user_func_array('array_merge', $unprequalified)));
								$names = $this->cats->names(array('GrpId' => array_keys($unprequalified)), 0);
								foreach ($unprequalified as $GrpId => $athletes)
								{
									$msg .= "\n" . $names[$GrpId] . ': ' . implode(', ', $athletes);
								}
							}

						}
						Api\Framework::refresh_opener($msg, 'ranking', $this->comp->data['WetId'], $_content['WetId'] ? 'edit' : 'add');
						if ($button === 'save') Api\Framework::window_close();
					}
				}
				catch(Exception $e) {
					$msg = lang('Error').': '.$e->getMessage();
					unset($button);
				}
			}
			if ($button === 'delete' && $this->acl_check_comp($this->comp->data) &&
				!$this->comp->has_results($this->comp->data['WetId']))
			{
				if ($this->comp->delete(array('WetId' => $this->comp->data['WetId'])))
				{
					Api\Framework::refresh_opener(lang('%1 deleted',lang('Competition')), 'ranking', $this->comp->data['WetId'], 'delete');
					Api\Framework::window_close();
				}
				else
				{
					$msg = lang('Error: deleting %1 !!!',lang('Competition'));
				}
			}
			if ($_content['remove'] && $this->acl_check_comp($this->comp->data))
			{
				$type = key($_content['remove']);

				$msg .= $this->comp->remove_attachment($type) ?
					lang('Removed the %1',$this->attachment_type[$type]) :
					lang('Error: removing the %1 !!!',$this->attachment_type[$type]);
			}
		}
		if ($button === 'copy' || !empty($_GET['copy']))
		{
			unset($this->comp->data['WetId']);
			unset($this->comp->data['datum']);
			$old_rkey = $this->comp->data['rkey'];
			// replace year in various fields
			foreach(['name', 'dru_bez', 'rkey'] as $name)
			{
				$this->comp->data[$name] = preg_replace('/([, \']+(20)?)\d{2}(,| |$)/',
					'${1}'.date('y').'${3}', $this->comp->data[$name]);
			}
			// remove rkey, if identical to old
			if ($old_rkey === $this->comp->data['rkey']) unset($this->comp->data['rkey']);
			$msg .= lang('Entry copied - edit and save the copy now.');
		}

		$content = $this->comp->data + array(
			'msg'  => $msg,
			'tabs' => $_content['tabs'],
			'referer' => $_content['referer'] ? $_content['referer'] :
				Api\Header\Referer::get('/index.php?menuaction=ranking.'.self::class.'.index'),
		);
		foreach((array) $this->comp->attachments(null,false,false) as $type => $linkdata)
		{
			$content['files'][$type] = array(
				'icon' => $type != 'logo' && $type != 'sponsors' ? $type :
					Egw::link($linkdata),
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
				'nation'=> $this->comp->data['nation'] ? $this->comp->data['nation'] : null,
				'fed_id'=> $this->is_admin || in_array($this->comp->data['nation'], $this->edit_rights) ?
					'' : $this->comp->data['fed_id'],
				(empty($this->comp->data['datum']) ? 'SUBSTRING(rkey, 1, 2) < 80 AND 2000+SUBSTRING(rkey, 1, 2) >= YEAR(NOW())' :
					'rkey LIKE '.$this->db->quote(substr($this->comp->data['datum'], 2, 2).'%')),
			)),
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
			'continent'    => Federation::$continents,
		);
		// if cup is not in sel_options, try reading it without filters
		if (!empty($content['serie']) && !isset($sel_options['serie'][$content['serie']]))
		{
			$sel_options['serie'] += $this->cup->names(['SerId' => $content['serie']]);
		}
		// select a category parent fitting to the nation
		$content['cat_parent'] = self::cat_rkey2id($content['nation'] ? $content['nation'] : 'int');
		$content['cat_parent_name'] = ($content['nation']? $content['nation'] : 'Int.').' '.lang('Competitions');

		if ($view)
		{
			$readonlys = array(
				'__ALL__' => true,
				'button[cancel]' => false,
				'button[edit]' => !$this->acl_check_comp($this->comp->data),
			);
		}
		else
		{
			$readonlys = array(
				'delete' => !$this->comp->data[$this->comp->db_key_cols[$this->comp->autoinc_id]] ||
					!$this->acl_check_comp($this->comp->data),
				'nation' => !!$this->only_nation_edit,
				'fed_id' => is_numeric($this->only_nation_edit),
				'button[edit]'   => true,
				'button[copy]'   => !$this->comp->data[$this->comp->db_key_cols[$this->comp->autoinc_id]],
			);
			// if only federation rights (no national rights), switch of ranking tab, to not allow changes there
			if (!$this->acl_check($this->comp->data['nation'], self::ACL_EDIT))
			{
				$readonlys['tabs'] = array('ranking' => true);
				// ToDo: limit category to state category(s)
			}
		}
		$GLOBALS['egw_info']['flags']['app_header'] = lang('ranking').' - '.lang($view ? 'view %1' : 'edit %1',lang('competition'));

		$tmpl = new Api\Etemplate('ranking.comp.edit');
		$tmpl->exec('ranking.'.self::class.'.edit',$content,
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
	 * @param array &$readonlys eg. to disable buttons based on Acl
	 */
	function get_rows($query,&$rows,&$readonlys)
	{
		Api\Cache::setSession('comp_state', 'ranking', $query);
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
		foreach($rows as &$row)
		{
			foreach((array) $this->comp->attachments($row,true) as $type => $linkdata)
			{
				$row['pdf'][$type] = array(
					'icon' => $type,
					'file' => $this->comp->attachment_path($type,$row),
					'link' => $linkdata.',_blank',
					'label'=> $this->attachment_type[$type],
				);
			}
			if (!$this->acl_check_comp($row))
			{
				$row['class'] = 'NoDelete';
			}
		}
		// set the cups based on the selected nation
		$rows['sel_options']['serie'] = $cups;

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
		if ($_content && $_content['nm']['action'] && $_content['nm']['selected'])
		{
			foreach($_content['nm']['selected'] as $id)
			{
				switch($_content['nm']['action'])
				{
					case 'delete':
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
						break;
				}
			}
		}
		$content = array('nm' => Api\Cache::getSession('comp_state', 'ranking'));

		if (!is_array($content['nm']))
		{
			$content['nm'] = array(
				'get_rows'       =>	'ranking.'.self::class.'.get_rows',
				'no_filter'      => True,// I  disable the 1. filter
				'no_filter2'     => True,// I  disable the 2. filter (params are the same as for filter)
				'bottom_too'     => True,// I  show the nextmatch-line (arrows, filters, search, ...) again after the rows
				'order'          =>	'datum',// IO name of the column to sort after (optional for the sortheaders)
				'sort'           =>	'DESC',// IO direction of the sort: 'ASC' or 'DESC'
				'csv_fields'     => false,
				'default_cols'   => '!status',
				'dataStorePrefix' => 'ranking_comp',
				'row_id'         => 'WetId',
			);
			// do not consider "XYZ" when setting a default filter
			$read_rights = array_values(array_diff($this->read_rights, array('XYZ')));
			if (count($read_rights) == 1)
			{
				$content['nm']['col_filter']['nation'] = $read_rights[0];
				$content['nm']['cat_parent'] = self::cat_rkey2id($read_rights[0]);
			}
			else
			{
				$content['nm']['cat_parent'] = self::cat_rkey2id('parent');
			}
		}
		// actions are NOT stored in session
		$content['nm']['actions'] = $this->get_actions();

		$content['msg'] = $msg ? $msg : $_GET['msg'];
		$this->set_ui_state();

		$GLOBALS['egw_info']['flags']['app_header'] = lang('ranking').' - '.lang('competitions');
		$tmpl = new Api\Etemplate('ranking.comp.list');
		$tmpl->exec('ranking.'.self::class.'.index',$content,array(
			'nation' => $this->ranking_nations,
//			'serie'  => $this->cup->names(array(),true),
			'discipline' => $this->disciplines,
			'cat_id' => array(lang('None')),
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
				'url' => 'menuaction=ranking.'.self::class.'.edit&WetId=$id',
				'popup' => '900x500',
				'group' => $group=0,
			),
			'add' => array(
				'caption' => 'Add',
				'url' => 'menuaction=ranking.'.self::class.'.edit',
				'popup' => '900x500',
				'disabled' => !$this->edit_rights && !$this->is_admin,
				'group' => $group,
			),
			'copy' => array(
				'caption' => 'Copy',
				'allowOnMultiple' => false,
				'url' => 'menuaction=ranking.'.self::class.'.edit&copy=$id',
				'popup' => '900x500',
				'group' => $group,
			),
			'delete' => array(
				'caption' => 'Delete',
				'disableClass' => 'noDelete',	// checks has result
				'allowOnMultiple' => false,
				'confirm' => 'Delete this competition',
				'group' => $group=5,
			),
		);

		return $actions;
	}
}
