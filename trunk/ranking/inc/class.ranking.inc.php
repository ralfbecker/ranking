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
		var $pkte;
		var $tmpl;
		var $cat;
		var $akt_grp; // selected cat to work on
		var $rls;

		var $public_functions = array
		(
			'start'          => True,
			'comp_edit'      => True,
			'cup_edit'       => True,
			'cat_edit'       => True,
			//'delete'       => True,
			//'show'         => True,
			//'admin'       => True,
			//'preferences' => True,
			'writeLangFile' => True
		);

		function ranking($lang_on_messages = True)
		{
			$this->comp = CreateObject('ranking.competition');
			$this->cup  = CreateObject('ranking.cup');
			$this->pkte = CreateObject('ranking.pktsystem');
			$this->pkt_names = $this->pkte->names();
			$this->cat  = CreateObject('ranking.category');
			$this->cat_names = $this->cat->names();
			$this->tmpl = CreateObject('etemplate.etemplate');
			$this->rls  = CreateObject('ranking.rls_system');
			$this->rls_names = $this->rls->names();

			if ($lang_on_messages)
			{
				reset($this->messages);
				while (list($key,$msg) = each($this->messages))
					$this->messages[$key] = lang($msg);
			}
		}

		function comp_edit($content = '')
		{
			if (($_GET['rkey'] || $_GET['WetId']) && !$this->comp->read($_GET))
				$msg .= $this->messages['not_found'];

			if (is_array($content))
			{
				//echo "<br>comp_edit: HTTP_POST_VARS ="; _debug_array($post_vars);
				$this->comp->data = $content['comp_data'];
				unset($content['comp_data']);

				$this->comp->data_merge($content);
				//echo "<br>comp_edit: comp->data ="; _debug_array($this->comp->data);

				if (isset($content['save']))
				{
					if (!$this->comp->data['rkey'])
						$msg .= $this->messages['rkey_empty'];
					elseif ($this->comp->not_unique())
						$msg .= sprintf($this->messages['rkey_not_unique'],$this->comp->data['rkey']);
					else
						$msg .= $this->messages[!$this->comp->save() ? 'comp_saved' : 'error_writing'];
				}
				elseif (isset($content['read']))
				{
					$found = $this->comp->search($content,False,'datum DESC');

					if (!$found)
						$msg .= $this->messages['nothing_found'];
					elseif (count($found) == 1)
						$this->comp->init($found[0]);
					else
					{
						$this->comp_list($found);
						return;
					}
				}
				elseif (isset($content['cancel']))
				{
					$this->comp->init();
				}
				elseif (isset($content['delete']))
				{
					$this->comp->delete();
					$this->comp->init();
				}
				elseif (isset($content['edit']))
				{
					list($WetId) = each($content['edit']);
					if ($WetId > 0)
						$this->comp->read(array('WetId' => $WetId));
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
			$content = $this->comp->data + array(
				'msg' => $msg
			);
			$sel_options = array(
				'pkte' => $this->pkt_names,
				'feld_pkte' => array(0 => lang('none')) + $this->pkt_names,
				'serie' => array(0 => lang('none')) + $this->cup->names(),
				'akt_grp' => $this->cat_names
			);
			$no_button = array(
				'delete' => !$this->comp->data[$this->comp->db_key_cols[$this->comp->autoinc_id]],
				'start[comp]' => True
			);
			$this->tmpl->read('ranking.comp.edit');

			$this->tmpl->exec('ranking.ranking.comp_edit',$content,
				$sel_options,$no_button,array('comp_data' => $this->comp->data));
		}

		function comp_list($found)
		{
			if (!is_array($found) || !count($found))
			{
				$this->comp_edit();
				return;
			}
			$content = array(
				'msg' => sprintf($this->messages['anz_found'],count($found)),
				'comps' => array_merge(array('not listed'),$found)
			);
			$this->tmpl->read('ranking.comp.list');

			$this->tmpl->exec('ranking.ranking.comp_edit',$content,array('akt_grp'=>$this->cat_names));
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



