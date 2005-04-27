<?php
/**************************************************************************\
* eGroupWare - digital ROCK Rankings - Accreditation                       *
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
require_once(EGW_INCLUDE_ROOT.'/etemplate/inc/class.uietemplate.inc.php');

class uiregistration extends boranking 
{
	/**
	 * @var array $public_functions functions callable via menuaction
	 */
	var $public_functions = array(
		'register' => true,
		'add'      => true,
	);

	function uiregistration()
	{
		$this->boranking();
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
		foreach(array('vorname','nachname') as $name)
		{
			$filter = array('nation' => $query['col_filter']['nation']);
			if ($query['col_filter']['sex']) $filter['sex'] = $query['col_filter']['sex'];

			$sel_options[$name] =& $this->athlete->distinct_list($name,$filter);

			if (!isset($sel_options[$name][$query['col_filter'][$name]]))
			{
				$query['col_filter'][$name] = '';
			}
		}
		$total = $this->athlete->get_rows($query,$rows,$readonlys,$query['show_all'] ? true : $query['cat']);
		$rows['sel_options'] =& $sel_options;
		$rows['comp'] = $query['comp'];
		$rows['cat']  = $query['cat'];

		if ($this->debug)
		{
			echo "<p>uiregistration::get_rows(".print_r($query,true).") rows ="; _debug_array($rows);
		}
		return $total;		
	}

	/**
	 * Register athlets for a competition
	 *
	 * @param array $content
	 * @param string $msg
	 */
	function add($content=null,$msg='')
	{
		if (!is_array($content))
		{
			$content = array(
				'comp'     => $_GET['comp'],
				'nation'   => $_GET['nation'],
				'cat'      => $_GET['cat'],
			);
		}
		$nation = $content['nation'];
		$comp   = $content['comp'];
		$cat    = $content['cat'];
		$show_all = $content['show_all'];
		
		if (!in_array($nation,$this->register_rights) || 	// no rights for that nation
			!($comp = $this->comp->read($comp)) || 			// unknown competition
			!($cat  = $this->cats->read($cat ? $cat : $comp['gruppen'][0])) ||	// unknown category
			(!in_array($cat['rkey'],$comp['gruppen'])))		// cat not in this competition
		{
			$msg = lang('Permission denied !!!');
		}
		$content = $preserv = array(
			'comp'     => $comp['WetId'],
			'nation'   => $nation,
			'nm'       => $content['nm'] ? $content['nm'] : array(
				'get_rows'       =>	'ranking.uiregistration.get_rows',
				'no_filter'      => True,// I  disable the 1. filter
				'no_filter2'     => True,// I  disable the 2. filter (params are the same as for filter)
				'no_cat'         => True,// I  disable the cat-selectbox
				'order'          =>	'last_comp',// IO name of the column to sort after (optional for the sortheaders)
				'sort'           =>	'DESC',// IO direction of the sort: 'ASC' or 'DESC'
				'col_filter'     => array(
					'nation' => $nation,
				),
				'comp'           => $comp['WetId'],
			),
		);
		$content += array(
			'comp_name' => $comp ? $comp['name'] : '',
			'cat'       => $cat['GrpId'],
			'show_all'  => $show_all,
			'msg'       => $msg,
		);
		// make (maybe changed) category infos avalible for nextmatch
		$content['nm']['cat'] = $cat['GrpId'];
		$content['nm']['col_filter']['sex'] = $cat['sex'];
		$content['nm']['show_all'] = $show_all;

		$select_options = array(
			'cat' => $this->cats->names(array('rkey' => $comp['gruppen']),0),
		);
		//_debug_array($content);
		$GLOBALS['egw_info']['flags']['app_header'] = lang('ranking').' - '.lang('Register');
		$GLOBALS['egw_info']['flags']['java_script'] .= '<script>window.focus();</script>';
		$tmpl =& new etemplate('ranking.register.add');
		$tmpl->exec('ranking.uiregistration.add',$content,$select_options,$readonly,$preserv,2);
	}
		
	/**
	 * Show the registration of a competition
	 *
	 * @param array $content
	 * @param string $msg
	 */
	function register($content=null,$msg='')
	{
		$tmpl =& new etemplate('ranking.register.form');

		if (!is_array($content))
		{
			$content = array(
				'calendar' => $_GET['calendar'],
				'comp'     => $_GET['comp'],
				'nation'   => $_GET['nation'],
			);
			if ($_GET['athlete'] && ($athlete = $this->athlete->read($_GET['athlete'])))
			{
				$content['nation'] = $athlete['nation'];
				$content['cat']    = $_GET['cat'];
			}
		}
		$comp     = $this->comp->read($content['comp']);

		if ($this->only_nation)
		{
			$calendar = $this->only_nation;
		}
		elseif ($comp)
		{
			$calendar = $comp['nation'] ? $comp['nation'] : 'NULL';
		}
		elseif ($content['calendar'])
		{
			$calendar = $content['calendar'];
		}
		else
		{
			list($calendar) = each($this->ranking_nations);
		}
		$nation   = $this->only_nation_register ? $this->only_nation_register : ($athlete ? $athlete['nation'] : $content['nation']);

		$select_options = array(
			'calendar' => $this->ranking_nations,
			'comp'     => $this->comp->names(array(
				'nation' => $calendar,
				'datum >= '.$this->db->quote(date('Y-m-d',time())),
			),0,'datum ASC'),
		);
		foreach($this->athlete_rights as $nat)
		{
			$select_options['nation'][$nat] = $nat;
		}
		// check if a valid competition is selected
		if ($comp)
		{
			//_debug_array($this->comp->data);
			foreach($this->comp->data['gruppen'] as $i => $rkey)
			{
				if (($cat = $this->cats->read(array('rkey'=>$rkey))))
				{
					$cat2col[$cat['GrpId']] = $tmpl->num2chrs($i);
				}
			}
			if ($nation)	// read prequalified athlets
			{
				$prequalified = $this->national_prequalified($comp,$nation);
				//_debug_array($prequalified);
			}
			//_debug_array($cat2col);
			if (!$this->registration_check($comp,$nation))	// user allowed to register that nation
			{
				$nation = '';
			}
			// athlete to register
			elseif($athlete || $content['register'] || $content['delete'])
			{
				if ($athlete)
				{
					$cat = $_GET['cat'];
				}
				else
				{
					
					list($athlete) = $content['register'] ? each($content['register']) : each($content['delete']);
					list($cat,$athlete) = explode('/',$athlete);
					$athlete = $this->athlete->read($athlete);
				}
				if (!($cat = $this->cats->read($cat)) || !in_array($cat['rkey'],$comp['gruppen']))
				{
					_debug_array($cat);
					_debug_array($comp);
					$msg = lang('Permission denied !!!');
				}
				elseif($content['delete'])
				{
					if ($this->result->delete(array(
						'WetId' => $comp['WetId'],
						'GrpId' => $cat['GrpId'],
						'PerId' => $athlete['PerId'],
						'platz = 0',	// precausion to not delete a result
					)))
					{
						$msg = lang('%1, %2 deleted for category %3',strtoupper($athlete['nachname']), $athlete['vorname'], $cat['name']);
					}
					else
					{
						$msg = lang('Error: registration');
					}
				}
				else // register
				{
					// prequalified athlets get a number < 1000
					$is_prequalified = isset($prequalified[$cat['GrpId']][$athlete['PerId']]);

					list($num) = $this->result->search(array(),'MAX(pkte)','','','',false,'AND',false,array(
						'nation' => $nation,
						$is_prequalified ? 'pkte < 1000' : 'pkte >= 1000',
					),false);
					if ($num)
					{
						$num++;
					}
					else
					{
						$num = $is_prequalified ? 1 : 1000;
					}
					if ($this->result->save(array(
						'PerId' => $athlete['PerId'],
						'WetId' => $comp['WetId'],
						'GrpId' => $cat['GrpId'],
						'platz' => 0,
						'pkte'  => $num,
						'datum' => date('Y-m-d'),
					)) == 0)
					{
						$msg = lang('%1, %2 registered for category %3',strtoupper($athlete['nachname']), $athlete['vorname'], $cat['name']);
					}
					else
					{
						$msg = lang('Error: registration');
					}
				}
			}
			$starters =& $this->result->read(array(
				'WetId'  => $comp['WetId'],
				'GrpId'  => -1,
			)+($nation ? array(
				'nation' => $nation,
			):array()),'',true,'nation,platz,pkt,GrpId');
			//_debug_array($starters);
			
			$nat = '';
			$nat_starters = array();
			$prequal_lines = 0;
			if ($nation)
			{
				foreach($prequalified as $cat_id => $athletes)
				{
					$i = 0;
					$col = $cat2col[$cat_id];
					foreach($athletes as $athlete)
					{
						$registered = false;
						// search athlete in starters
						foreach((array)$starters as $starter)
						{
							if ($starter['PerId'] == $athlete['PerId'] && $starter['GrpId'] == $cat_id)
							{
								$registered = true;
								break;
							}
						}
						$delete_button = 'delete['.$cat_id.'/'.$athlete['PerId'].']';
						$register_button = 'register['.$cat_id.'/'.$athlete['PerId'].']';
						$nat_starters[$i++][$col] = $athlete+array(
							'cn' => strtoupper($athlete['nachname']).', '.$athlete['vorname'],
							'class' => $registered ? 'prequalifiedRegistered' : 'prequalified',
							'delete_button' => $delete_button,
							'register_button' => $register_button,
						);
						$readonlys[$registered ? $delete_button : $register_button] = false;	// re-enable the button
					}
					if (count($athletes) > $prequal_lines)
					{
						$prequal_lines = count($athletes);
						$nat = lang('Prequalified');
					}
				}
			}
			$rows = array(false);	// we need 1 to be the index of the first row
			$starters[] = array('nation'=>'');	// to get the last line out
			foreach((array)$starters as $starter)
			{
				// new nation and data for the previous nation ==> write that data
				if ($nat != $starter['nation'])
				{
					foreach($nat_starters as $i => $row)
					{
						$rows[] = array(
							'nation' => !$nation || $nat && $nat != $nation || !$nat && $i != $quota ? $nat : 
								($i == $quota ? lang('Complimentary') : lang('Quota')),
						) + $row;
						$nat = '';
					}
					$nat_starters = array();
					$nat = $starter['nation'];
					$quota = $comp['host_quota'] && $comp['host_nation'] == $nation ? $comp['host_quota'] : $comp['quota'];
				}
				if ($nation && isset($prequalified[$starter['GrpId']][$starter['PerId']]))
				{
					continue;	// prequalified athlets are in an own block
				}
				// set a new column for an unknown/new rkey/cat
				if ($starter['nation'] && !isset($cat2col[$starter['GrpId']]))
				{
					$cat2col[$starter['GrpId']] = $tmpl->num2chrs(count($cat2col));
				}
				$col = $cat2col[$starter['GrpId']];
				// find first free line to add that starter
				for ($i = 0; isset($nat_starters[$i][$col]); ++$i) ;
				$delete_button = 'delete['.$starter['GrpId'].'/'.$starter['PerId'].']';
				$nat_starters[$i][$col] = $starter+array(
					'cn' => strtoupper($starter['nachname']).', '.$starter['vorname'],
					'class' => $nation && $i >= $quota ? 'complimentary' : 'registered',
					'delete_button' => $delete_button,
				);
				$readonlys[$delete_button] = !$nation;	// re-enable the button
			}
			$cats = array();
			foreach($cat2col as $cat => $col)
			{
				$cats[$col] = $this->cats->read(array('GrpId' => $cat));
			}
		}
		else
		{
			$comp = '';
		}
		if (!$comp || !$nation)		// no register-button
		{
			$readonlys['register'] = true;
		}
		$content = $preserv = array(
			'calendar' => $calendar,
			'comp'     => $comp['WetId'],
			'nation'   => $nation,
		);
		$content += array(
			'registration' => $comp ? $this->registration_check($comp) : false,
			'rows'     => &$rows,
			'cats'     => &$cats,
			'count'    => $starters ? count($starters)-1 : 0,	// -1 as we add an empty starter at the end
			'msg'      => $msg,
		);
		//_debug_array($content);
		$GLOBALS['egw_info']['flags']['app_header'] = lang('ranking').' - '.lang('Registration').
			($nation ? ': '.$nation : '');
		$tmpl->exec('ranking.uiregistration.register',$content,$select_options,$readonlys,$preserv);
	}
}
