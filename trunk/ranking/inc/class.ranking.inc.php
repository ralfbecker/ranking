<?php
	/**************************************************************************\
	* eGroupWare - digital ROCK Rankings                                       *
	* http://www.egroupware.org, http://www.digitalROCK.de                     *
	* Written and (c) by Ralf Becker <RalfBecker@outdoor-training.de>          *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	class ranking
	{
		var $messages = array(
			'not_found' => 'Entry not found !!!',
			'rkey_empty' => 'Key must not be empty !!!',
			'nothing_found' => 'Nothing matched search criteria !!!',
			'anz_found' => '%d matches on search criteria',
			'cat_saved' => 'Category saved',
			'comp_saved' => 'Competition saved',
			'cup_saved' => 'Cup saved',
			'error_writing' => 'Error: saveing !!!',
			'rkey_not_unique' => 'Error: Key \'%s\' exists already, it has to be unique !!!'
		);
		var $split_by_places = array(
			'no' => 'No never',
			'only_counting' => 'Only if competition is counting',
			'all' => 'Allways'
		);
		var $genders = array(
			'female' => 'female',
			'male' => 'male',
			'' => 'none'
		);
		var $comp;
		var $cup;
		var $pkte,$pkt_names;
		var $cat,$cat_names;
		var $rls,$rls_names;
		/**
		 * @var array $ranking_nations Nations allowed to create rankings and competitions
		 */
		var $ranking_nations=array();
		/**
		 * @var string $only_nation nation if there's only one ranking-nation
		 */
		var $only_nation='';
		/**
		 * @var string $only_nation_edit nation if there's only one nation the user has edit-rights to
		 */
		var $only_nation_edit='';
		var $nations=array();
		var $tmpl;
		var $akt_grp; // selected cat to work on
		/**
		 * @var array $read_rights nations the user is allowed to see
		 */
		var $read_rights = array();
		/**
		 * @var array $edit_rights nations the user is allowed to edit
		 */
		var $edit_rights = array();
		/**
		 * @var boolean $is_admin true if user is an administrator, implies all read- and edit-rights
		 */
		var $is_admin = false;

		var $public_functions = array
		(
			'start'          => True,
			'competitions'   => True,
			'get_comps'      => True,
			'edit_comp'      => True,
			'cup_edit'       => True,
			'cat_edit'       => True,
			'writeLangFile' => True
		);
		var $maxmatches = 12;

		function ranking($lang_on_messages = True)
		{
			$this->comp = CreateObject('ranking.competition');
			$this->cup  = CreateObject('ranking.cup');
			$this->pkte = CreateObject('ranking.pktsystem');
			$this->pkt_names = $this->pkte->names();
			$this->cat  = CreateObject('ranking.category');
			$this->cat_names = $this->cat->names();
			$this->rls  = CreateObject('ranking.rls_system');
			$this->rls_names = $this->rls->names();
			$this->tmpl = CreateObject('etemplate.etemplate');
			
			if ($lang_on_messages)
			{
				foreach($this->messages as $key => $msg)
				{
					$this->messages[$key] = lang($msg);
				}
			}
			
			if ((int) $GLOBALS['phpgw_info']['user']['preferences']['common']['maxmatchs']) 
			{
				$this->maxmatches = (int) $GLOBALS['phpgw_info']['user']['preferences']['common']['maxmatchs'];
			}
			
			// read the nation ACL
			foreach(array('read_rights' => PHPGW_ACL_READ,'edit_rights' => PHPGW_ACL_EDIT) as $var => $right)
			{
				$ids = (array) $GLOBALS['phpgw']->acl->get_location_list_for_id('ranking',$right);
				foreach($ids as $n => $val)
				{
					if ($val == 'run') unset($ids[$n]);
				}
				$this->$var = array_values($ids);
				//echo $var; _debug_array($this->$var);
			}
			//$this->is_admin = $GLOBALS['phpgw_info']['user']['apps']['admin'];

			// setup list with nations we rank and intersect it with the read_rights
			$this->ranking_nations = array('NULL'=>lang('international'))+$this->comp->nations();
			if (!$this->is_admin)
			{
				foreach($this->ranking_nations as $key => $label)
				{
					if (!in_array($key,$this->read_rights)) unset($this->ranking_nations[$key]);
				}
				if (count($this->ranking_nations) == 1)
				{
					$this->only_nation = $this->ranking_nations[0];
				}
				if (count($this->edit_rights) == 1)
				{
					$this->only_nation_edit = $this->edit_rights[0];
				}
				//echo "<p>read_rights=".print_r($this->read_rights,true).", edit_rights=".print_r($this->edit_rights,true).", only_nation_edit='$this->only_nation_edit', only_nation='$this->only_nation'</p>\n";
			}
			// this need to go to the athlet-class
			$GLOBALS['phpgw']->db->select('rang.Personen','DISTINCT nation',false,__LINE__,__FILE__,false,'ORDER BY nation');
			while($GLOBALS['phpgw']->db->next_record())
			{
				$nat = $GLOBALS['phpgw']->db->f(0);
				$this->nations[$nat] = $nat;
			}
		}

		/**
		 * Edit a competition
		 *
		 * @param array $content
		 * @param string $msg
		 */
		function edit_comp($content=null,$msg='')
		{
			if (($_GET['rkey'] || $_GET['WetId']) && !$this->comp->read($_GET))
			{
				$msg .= $this->messages['not_found'];
			}
			// set and enforce the nation if only one allowed
			if ($this->only_nation_edit)
			{
				$content['nation'] = $this->comp->data['nation'] = $this->only_nation_edit;
			}
			elseif (!$_GET['WetId'] && !$_GET['rkey'])
			{
				$this->comp->data['nation'] = $this->edit_rights[0];
			}
			if (is_array($content))
			{
				//echo "<br>comp_edit: content ="; _debug_array($content);
				$this->comp->data = $content['comp_data'];
				unset($content['comp_data']);

				$this->comp->data_merge($content);
				//echo "<br>comp_edit: comp->data ="; _debug_array($this->comp->data);

				if ($content['save'] || $content['apply'])
				{
					if (!$this->comp->data['rkey'])
					{
						$msg .= $this->messages['rkey_empty'];
					}
					elseif ($this->comp->not_unique())
					{
						$msg .= sprintf($this->messages['rkey_not_unique'],$this->comp->data['rkey']);
					}
					elseif (!$this->comp->save())
					{
						$msg .= $this->messages['error_writing'];
					}
					else
					{
						$msg .= $this->messages['comp_saved'];

						if ($content['save']) $content['cancel'] = true;	// leave dialog now
					}
				}
				if ($content['cancel'])
				{
					$this->tmpl->location(array('menuaction'=>'ranking.ranking.competitions'));
				}
				if ($content['delete'])
				{
					$this->competitions(array(
						'nm' => array(
							'rows' => array(
								'delete' => array(
									$this->comp->data['WetId'] => 'delete'
								)
							)
						)
					));
					return;
				}
			}
			$content = $this->comp->data + array(
				'msg' => $msg
			);
			$sel_options = array(
				'pkte'      => $this->pkt_names,
				'feld_pkte' => array(0 => lang('none')) + $this->pkt_names,
				'serie'     => array(0 => lang('none')) + $this->cup->names(array(
					'nation'=>$this->comp->data['nation'])),
				'nation'    => $this->ranking_nations,
			);
			$no_button = array(
				'delete' => !$this->comp->data[$this->comp->db_key_cols[$this->comp->autoinc_id]],
				'start[comp]' => True
			);
			$GLOBALS['phpgw_info']['flags']['app_header'] = lang('ranking').' - '.lang('competitions');
			$this->tmpl->read('ranking.comp.edit');
			if ($this->only_nation_edit)
			{
				$this->tmpl->set_cell_attribute('nation','readonly',true);
			}
			$this->tmpl->exec('ranking.ranking.edit_comp',$content,
				$sel_options,$no_button,array('comp_data' => $this->comp->data));
		}

		/**
		 * query competitions for nextmatch in the competitions list
		 *
		 * @param array $query
		 * @param array &$rows returned rows/competitions
		 * @param array &$readonlys eg. to disable buttons based on acl
		 */
		function get_comps($query,&$rows,&$readonlys)
		{
			$GLOBALS['phpgw']->session->appsession('ranking','comp_state',$query);
			
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
			$total = $this->comp->get_rows($query,$rows,$readonlys);
			
			$readonlys = array();
			foreach($rows as $row)
			{
				if (!$this->is_admin && !in_array($row['nation']?$row['nation']:null,$this->edit_rights))
				{
					$readonlys["edit[$row[WetId]]"] = $readonlys["delete[$row[WetId]]"] = true;
				}
			}
			if ($this->debug)
			{
				echo "<p>ranking::get_comps(".print_r($query,true).") rows ="; _debug_array($rows);
				_debug_array($readonlys);
			}
			return $total;		
		}

		/**
		 * List existing competitions
		 *
		 * @param array $content
		 * @param string $msg
		 */
		function competitions($content=null,$msg='')
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
							'menuaction' => 'ranking.ranking.view_comp',
							'WetId'      => $id,
						));
						break;
						
					case 'edit':
						$this->tmpl->location(array(
							'menuaction' => 'ranking.ranking.edit_comp',
							'WetId'      => $id,
						));
						break;
						
					case 'delete':
						$msg = 'delete is not yet implemented!!!';
						break;
				}						
			}
			$content = array();

			if (!is_array($content['nm'])) $content['nm'] = $GLOBALS['phpgw']->session->appsession('ranking','comp_state');
			
			if (!is_array($content['nm']))
			{
				$content['nm'] = array(
					'get_rows'       =>	'ranking.ranking.get_comps',
					'no_filter'      => True,// I  disable the 1. filter
					'no_filter2'     => True,// I  disable the 2. filter (params are the same as for filter)
					'no_cat'         => True,// I  disable the cat-selectbox
					'bottom_too'     => True,// I  show the nextmatch-line (arrows, filters, search, ...) again after the rows
					'order'          =>	'datum',// IO name of the column to sort after (optional for the sortheaders)
					'sort'           =>	'DESC',// IO direction of the sort: 'ASC' or 'DESC'
				);
				if (count($this->read_rights) == 1)
				{
					$content['nm']['col_filter']['nation'] = $this->read_rights[0];
				}
			}
			$content['msg'] = $msg;

			$this->tmpl->read('ranking.comp.list');
			$GLOBALS['phpgw_info']['flags']['app_header'] = lang('ranking').' - '.lang('competitions');
			$this->tmpl->exec('ranking.ranking.competitions',$content,array(
				'nation' => $this->ranking_nations,
				'serie'  => $this->cup->names(array(),true),
			));
		}

		function cup_edit($content = '')
		{
			if (($_GET['rkey'] || $_GET['SerId']) && !$this->cup->read($_GET))
				$msg .= $this->messages['not_found'];

			if (is_array($content))
			{
				$this->cup->data = $content['cup_data'];

				$this->cup->data_merge($content);
				//echo "<br>cup_edit: cup->data ="; _debug_array($this->cup->data);

				if (isset($content['save']))
				{
					if (!$this->cup->data['rkey'])
						$msg .= $this->messages['rkey_empty'];
					elseif ($this->cup->not_unique())
						$msg .= sprintf($this->messages['rkey_not_unique'],$this->cup->data['rkey']);
					else
						$msg .= $this->messages[!$this->cup->save() ? 'cup_saved' : 'error_writing'];
				}
				elseif (isset($content['read']))
				{
					$found = $this->cup->search($content,False,'year DESC');

					if (!$found)
						$msg .= $this->messages['nothing_found'];
					elseif (count($found) == 1)
						$this->cup->init($found[0]);
					else
					{
						$this->cup_list($found);
						return;
					}
				}
				elseif (isset($content['cancel']))
				{
					$this->cup->init();
				}
				elseif (isset($content['delete']))
				{
					$this->cup->delete();
					$this->cup->init();
				}
				elseif (isset($content['edit']))
				{
					list($SerId) = each($content['edit']);
					if ($SerId > 0)
						$this->cup->read(array('SerId' => $SerId));
				}
				elseif (isset($content['start']))
				{
					$this->start();
					return;
				}
			}
			elseif ($content)
			{
				$msg = $content;
			}
			$content = $this->cup->data + array(
				'msg' => $msg
			);
			$sel_options = array(
				'pkte' => $this->pkt_names,
				'split_by_places' => $this->split_by_places,
				'akt_grp' => $this->cat_names
			);
			$no_button = array(
				'delete' => !$this->cup->data[$this->cup->db_key_cols[$this->cup->autoinc_id]],
				'start[cup]' => True
			);
			$this->tmpl->read('ranking.cup.edit');

			$this->tmpl->exec('ranking.ranking.cup_edit',$content,
				$sel_options,$no_button,array('cup_data' => $this->cup->data));
		}

		function cup_list($found)
		{
			if (!is_array($found) || !count($found))
			{
				$this->cup_edit();
				return;
			}
			$content = array(
				'msg' => sprintf($this->messages['anz_found'],count($found)),
				'cups' => array_merge(array('not listed'),$found)
			);
			$this->tmpl->read('ranking.cup.list');

			$this->tmpl->exec('ranking.ranking.cup_edit',$content,array('akt_grp'=>$this->cat_names));
		}

		function cat_edit($content = '')
		{
			if (($_GET['rkey'] || $_GET['GrpId']) && !$this->cup->read($_GET))
				$msg .= $this->messages['not_found'];

			if (is_array($content))
			{
				$this->cat->data = $content['cat_data'];

				$this->cat->data_merge($content);
				//echo "<br>cat_edit: cat->data ="; _debug_array($this->cat->data);

				if (isset($content['save']))
				{
					if (!$this->cat->data['rkey'])
						$msg .= $this->messages['rkey_empty'];
					elseif ($this->cat->not_unique())
						$msg .= sprintf($this->messages['rkey_not_unique'],$this->cat->data['rkey']);
					else {
						$msg .= $this->messages[!$this->cat->save() ? 'cat_saved' : 'error_writing'];
					}
				}
				elseif (isset($content['read']))
				{
					$found = $this->cat->search($content,False,'nation,rkey');

					if (!$found)
						$msg .= $this->messages['nothing_found'];
					elseif (count($found) == 1)
						$this->cat->init($found[0]);
					else
					{
						$this->cat_list($found);
						return;
					}
				}
				elseif (isset($content['cancel']))
				{
					$this->cat->init();
				}
				elseif (isset($content['delete']))
				{
					$this->cat->delete();
					$this->cat->init();
				}
				elseif (isset($content['edit']))
				{
					list($GrpId) = each($content['edit']);
					if ($GrpId > 0)
						$this->cat->read(array('GrpId' => $GrpId));
				}
				elseif (isset($content['start']))
				{
					$this->start();
					return;
				}
			}
			elseif ($content)
			{
				$msg = $content;
			}
			$content = $this->cat->data + array(
				'msg' => $msg,
			);
			$sel_options = array(
				'akt_grp' => $this->cat_names,
				'sex' => $this->genders,
				'rls' => $this->rls_names,
				'vor_rls' => array('' => lang('none')) + $this->rls_names
			);
			$no_button = array(
				'delete' => !$this->cat->data[$this->cat->db_key_cols[$this->cat->autoinc_id]],
				'start[cat]' => True
			);
			$this->tmpl->read('ranking.cat.edit');

			$this->tmpl->exec('ranking.ranking.cat_edit',$content,
				$sel_options,$no_button,array('cat_data' => $this->cat->data));
		}

		function cat_list($found)
		{
			if (!is_array($found) || !count($found))
			{
				$this->cat_edit();
				return;
			}
			$content = array(
				'msg' => sprintf($this->messages['anz_found'],count($found)),
				'cats' => array_merge(array('not listed'),$found)
			);
			$this->tmpl->read('ranking.cat.list');

			$this->tmpl->exec('ranking.ranking.cat_edit',$content,array('akt_grp'=>$this->cat_names));
		}

		/**
		 * Main menu for ranking
		 *
		 * @param array $content etemplate content
		 */
		function start($content='')
		{
			if (is_array($content) && isset($content['start']))
			{
				list($prog) = each($content['start']);

				switch ($prog)
				{
					case 'comp':
						$this->comp_edit();
						return;
					case 'cup':
						$this->cup_edit();
						return;
					case 'cat':
						$this->cat_edit();
						return;
					default:
						$msg = 'noch nicht implementiert !!!';
				}
			}
			$this->tmpl->read('ranking.start');

			$this->tmpl->exec('ranking.ranking.start',array('msg' => $msg),$sel_options);
		}

		/*!
		@function writeLangFile
		@abstract writes langfile with all templates and messages registered here
		@discussion can be called via http://domain/phpgroupware/index.php?ranking.ranking.writeLangFile
		*/
		function writeLangFile()
		{
			$r = new ranking(False);	// no lang on messages

			$add = array_merge($r->messages,$r->split_by_places);
			$add = array_merge($add,$r->genders);
			$this->tmpl->writeLangFile('ranking','en',$add);
		}
	};



