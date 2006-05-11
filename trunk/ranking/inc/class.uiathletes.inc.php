<?php
/**************************************************************************\
* eGroupWare - digital ROCK Rankings - Athletes UI                             *
* http://www.egroupware.org, http://www.digitalROCK.de                     *
* Written and (c) by Ralf Becker <RalfBecker@outdoor-training.de>          *
* --------------------------------------------                             *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

require_once(EGW_INCLUDE_ROOT.'/ranking/inc/class.boranking.inc.php');

class uiathletes extends boranking 
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
	 * @var array $acl_deny_lables
	 */
	var $acl_deny_labels = array();

	function uiathletes()
	{
		$this->boranking();

		$this->tmpl =& CreateObject('etemplate.etemplate');
		
		$this->acl_deny_labels = array(
			ACL_DENY_BIRTHDAY	=> lang('birthday, shows only the year'),
			ACL_DENY_EMAIL		=> lang('email'),
			ACL_DENY_PHONE		=> lang('phone'),
			ACL_DENY_FAX		=> lang('fax'),
			ACL_DENY_CELLPHONE	=> lang('cellphone'),
			ACL_DENY_STREET		=> lang('street, postcode'),
			ACL_DENY_CITY		=> lang('city'),
			ACL_DENY_PROFILE	=> lang('complete profile'),
		);			
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
		if ($_GET['rkey'] || $_GET['PerId']) 
		{
			if ($this->athlete->read($_GET))
			{
				// read the athletes results
				$this->athlete->data['comp'] = $this->result->read(array('PerId' => $this->athlete->data['PerId'],'platz > 0'));
				if ($this->athlete->data['comp']) array_unshift($this->athlete->data['comp'],false);	// reindex with 1
			}
			else
			{
				$msg .= lang('Entry not found !!!');
			}
		}
		// set and enforce nation ACL
		if (!is_array($content))	// new call
		{
			if (!$_GET['PerId'] && !$_GET['rkey'])
			{
				$this->athlete->data['nation'] = $this->athlete_rights[0];
			}
			// we have no edit-rights for that nation
			if (!$this->is_admin && !in_array($this->athlete->data['nation'] ? $this->athlete->data['nation'] : 'NULL',$this->athlete_rights))
			{
				$view = true;
			}
		}
		else
		{
			$view = $content['view'] && !($content['edit'] && ($this->is_admin || in_array($this->athlete->data['nation'],$this->athlete_rights)));

			if (!$view && $this->only_nation_athlete) $content['nation'] = $this->only_nation_athlete;

			//echo "<br>uiathletes::edit: content ="; _debug_array($content);
			$this->athlete->data =& $content['athlete_data'];
			unset($content['athlete_data']);
			$old_geb_date = $this->athlete->data['geb_date'];

			$this->athlete->data_merge($content);
			//echo "<br>uiathletes::edit: athlete->data ="; _debug_array($this->athlete->data);

			if (($content['save'] || $content['apply']))
			{
				if ($this->is_admin || in_array($this->athlete->data['nation'],$this->athlete_rights))
				{
					if (!$this->athlete->data['rkey'])
					{
						$this->athlete->generate_rkey();
					}
					if ($old_geb_date && !$this->athlete->data['geb_date'] && !$this->is_admin)
					{
						$msg .= lang("Use the ACL to hide the birthdate, you can't remove it !!!");
						$this->athlete->data['geb_date'] = $old_geb_date;
					}
					elseif ($this->athlete->not_unique())
					{
						$msg .= lang("Error: Key '%1' exists already, it has to be unique !!!",$this->athlete->data['rkey']);
					}
					elseif ($this->athlete->save())
					{
						$msg .= lang('Error: while saving !!!');
					}
					else
					{
						$msg .= lang('%1 saved',lang('Athlete'));
	
						if ($content['save']) $content['cancel'] = true;	// leave dialog now

						if (is_array($content['foto']) && $content['foto']['tmp_name'] && $content['foto']['name'] && is_uploaded_file($content['foto']['tmp_name']))
						{
							//_debug_array($content['foto']);
							list($width,$height,$type) = getimagesize($content['foto']['tmp_name']);
							if ($type != 2)
							{
								$msg .= ($msg ? ', ' : '') . lang('Uploaded picture is no JPEG !!!');
							}
							else
							{
								if ($height > 250 && ($src = @imagecreatefromjpeg($content['foto']['tmp_name'])))	// we need to scale the picture down
								{
									$dst_w = (int) round(250.0 * $width / $height);
									echo "<p>{$content['foto']['name']}: $width x $height ==> $dst_w x 250</p>\n";
									$dst = imagecreatetruecolor($dst_w,250);
									if (imagecopyresampled($dst,$src,0,0,0,0,$dst_w,250,$width,$height))
									{
										imagejpeg($dst,$content['foto']['tmp_name']);
										$msg .= ($msg ? ', ' : '') . lang('Picture resized to %1 pixel',$dst_w.' x 250');
									}
									imagedestroy($src);
									imagedestroy($dst);
								}
								$msg .= ($msg ? ', ' : '') . ($this->athlete->attach_picture($content['foto']['tmp_name']) ? 
									lang('Picture attached') : lang('Error attaching the picture'));
							}
						}
					}
				}
				else
				{
					$msg .= lang('Permission denied !!!').' ('.$this->athlete->data['nation'].')';
				}
			}
			if ($content['cancel'])
			{
				$this->tmpl->location(array('menuaction'=>'ranking.uiathletes.index'));
			}
			if ($content['delete'] && in_array($content['nation'],$this->athlete_rights))
			{
				$this->index(array(
					'nm' => array(
						'rows' => array(
							'delete' => array(
								$this->athlete->data['PerId'] => 'delete'
							)
						)
					)
				));
				return;
			}
		}
		$tabs = 'contact|profile|freetext|other|pictures|results';
		$content = $this->athlete->data + array(
			'msg' => $msg,
			'is_admin' => $this->is_admin,
			$tabs => !$content[$tabs] && !$view && $this->tmpl->html->user_agent == 'mozilla' ? 'ranking.athlete.edit.freetext' : $content[$tabs],
			'foto' => $this->athlete->picture_url().'?'.time(),
		);
		$sel_options = array(
			'nation' => $this->athlete->distinct_list('nation'),
			'sex'    => $this->genders,
			'acl'    => $this->acl_deny_labels,
		);
		$readonlys = array(
			'delete' => !$this->athlete->data[$this->athlete->db_key_cols[$this->athlete->autoinc_id]],
			'nation' => !!$this->only_nation_athlete,
			'edit'   => !($view && ($this->is_admin || in_array($this->athlete->data['nation'],$this->athlete_rights))),
		);
		// dont allow non-admins to change sex and nation, once it's been set
		if ($this->athlete->data['PerId'] && !$this->is_admin)
		{
			if ($this->athlete->data['nation']) $readonlys['nation'] = true;
			if ($this->athlete->data['sex']) $readonlys['sex'] = true;
		}
		if ($view)
		{
			foreach($this->athlete->data as $name => $val)
			{
				$readonlys[$name] = true;
			}
			$readonlys['foto'] = $readonlys['delete'] = $readonlys['save'] = $readonlys['apply'] = true;
		}
		elseif (!$this->athlete->data['PerId'] || $this->athlete->data['last_comp'])
		{
			$readonlys['delete'] = true;
		}
		$GLOBALS['egw_info']['flags']['app_header'] = lang('ranking').' - '.lang($view ? 'view %1' : 'edit %1',lang('Athlete'));
		$this->tmpl->read('ranking.athlete.edit');
		$this->tmpl->exec('ranking.uiathletes.edit',$content,
			$sel_options,$readonlys,array(
				'athlete_data' => $this->athlete->data,
				'view' => $view,
			));
	}

	/**
	 * query athlets for nextmatch in the athlets list
	 *
	 * @param array $query
	 * @param array &$rows returned rows/cups
	 * @param array &$readonlys eg. to disable buttons based on acl
	 */
	function get_rows($query,&$rows,&$readonlys)
	{
		//echo "uiathletes::get_rows() query="; _debug_array($query);
		$GLOBALS['egw']->session->appsession('ranking','athlete_state',$query);

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
		foreach(array('vorname','nachname') as $name)
		{
			if ($query['col_filter']['nation'])
			{
				$filter = array('nation' => $query['col_filter']['nation']);
				if ($query['col_filter']['sex'])
				{
					$filter['sex'] = $query['col_filter']['sex'];
				}
				$sel_options[$name] =& $this->athlete->distinct_list($name,$filter);
			}
			else
			{
				$sel_options[$name] = array(1+$query['col_filter'][$name] => lang('Select a nation first'));
			}
			if (!isset($sel_options[$name][$query['col_filter'][$name]]))
			{
				$query['col_filter'][$name] = '';
			}
		}
		$sel_options['filter'] = array('' => lang('All')) + $this->cats->names($cat_filter,-1);

		$total = $this->athlete->get_rows($query,$rows,$readonlys,(int)$query['filter'] ? (int)$query['filter'] : true);
		
		//_debug_array($rows);
		
		$readonlys = array();

		foreach($rows as $row)
		{
			if (!$this->acl_check($row['nation'],EGW_ACL_ADD))
			{
				$readonlys["edit[$row[PerId]]"] = $readonlys["delete[$row[PerId]]"] = true;
			}
			if ($row['last_comp'])
			{
				$readonlys["delete[$row[PerId]]"] = true;
			}
		}
		$rows['sel_options'] =& $sel_options;

		if ($this->debug)
		{
			echo "<p>uiathles::get_rows(".print_r($query,true).") rows ="; _debug_array($rows);
			_debug_array($readonlys);
		}
		return $total;		
	}

	/**
	 * List existing Athletes
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
			//echo "<p>uiathletes::inddex() action='$action', id='$id'</p>\n";
			switch($action)
			{
				case 'view':
					$this->tmpl->location(array(
						'menuaction' => 'ranking.uiathletes.view',
						'PerId'      => $id,
					));
					break;
					
				case 'edit':
					$this->tmpl->location(array(
						'menuaction' => 'ranking.uiathletes.edit',
						'PerId'      => $id,
					));
					break;
					
				case 'delete':
					if (!$this->is_admin && $this->athlete->read(array('PerId' => $id)) &&
						!in_array($this->athlete->data['nation'],$this->athlete_rights))
					{
						$msg = lang('Permission denied !!!');
					}
					elseif ($this->athlete->has_results($id))
					{
						$msg = lang('You need to delete the results first !!!');
					}
					else
					{
						$msg = $this->athlete->delete(array('PerId' => $id)) ? 
							lang('%1 deleted',lang('Athlete')) : lang('Error: deleting %1 !!!',lang('Athlete'));
					}
					break;
			}						
		}
		$content = array();

		if (!is_array($content['nm'])) $content['nm'] = $GLOBALS['egw']->session->appsession('ranking','athlete_state');
		
		if (!is_array($content['nm']))
		{
			$content['nm'] = array(
				'get_rows'       =>	'ranking.uiathletes.get_rows',
				'filter_no_lang' => True,
				'filter_label'   => lang('Category'),
				'no_filter2'     => True,// I  disable the 2. filter (params are the same as for filter)
				'no_cat'         => True,// I  disable the cat-selectbox
				'bottom_too'     => True,// I  show the nextmatch-line (arrows, filters, search, ...) again after the rows
				'order'          =>	'nachname',// IO name of the column to sort after (optional for the sortheaders)
				'sort'           =>	'ASC',// IO direction of the sort: 'ASC' or 'DESC'
			);
			if (count($this->athlete_rights) == 1)
			{
				$content['nm']['col_filter']['nation'] = $this->athlete_rights[0];
			}
		}
		$content['msg'] = $msg;

		$this->tmpl->read('ranking.athlete.list');
		$GLOBALS['egw_info']['flags']['app_header'] = lang('ranking').' - '.lang('Athletes');
		$this->tmpl->exec('ranking.uiathletes.index',$content,array(
			'nation' => $this->athlete->distinct_list('nation'),
			'sex'    => array_merge($this->genders,array(''=>'')),	// no none
		));
	}
}
