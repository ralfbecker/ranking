<?php
/**************************************************************************\
* eGroupWare - digital ROCK Rankings - Registration and startlists         *
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
		'lists'     => true,
		'result'    => true,
		'startlist' => true,
		'index'     => true,
		'add'       => true,
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
		
		if (!($comp = $this->comp->read($comp)) || 			// unknown competition
			!$this->acl_check($nation,EGW_ACL_REGISTER,$comp) || 	// no rights for that nation
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
	 * Show the registrations of a competition and allow to register for it
	 *
	 * @param array $content
	 * @param string $msg
	 */
	function index($content=null,$msg='')
	{
		$tmpl =& new etemplate('ranking.register.form');

		if (!is_array($content))
		{
			if ($_GET['calendar'] || $_GET['comp'])
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
			else
			{
				$content = $GLOBALS['egw']->session->appsession('registration','ranking');
			}
		}
		if($content['comp']) $comp = $this->comp->read($content['comp']);

		if ($tmpl->sitemgr && $_GET['comp'] && $comp)	// no calendar and/or competition selection, if in sitemgr the comp is direct specified
		{
			$readonlys['calendar'] = $readonlys['comp'] = true;
		}
		if ($this->only_nation)
		{
			$calendar = $this->only_nation;
			$tmpl->disable_cells('calendar');
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
		$nation = $athlete ? $athlete['nation'] : $content['nation'];
		if ($this->only_nation_register && !$this->is_judge($comp))
		{
			$nation = $this->only_nation_register;
		}
		$select_options = array(
			'calendar' => $this->ranking_nations,
			'comp'     => $this->comp->names(array(
				'nation' => $calendar,
				'datum >= '.$this->db->quote(date('Y-m-d',time())),
				'gruppen IS NOT NULL',
			),0,'datum ASC'),
		);
		foreach($this->is_judge($comp) ? $this->athlete->distinct_list('nation') : $this->register_rights as $nat)
		{
			$select_options['nation'][$nat] = $nat;
		}
		// check if a valid competition is selected
		if ($comp)
		{
			//_debug_array($this->comp->data);
			foreach((array) $this->comp->data['gruppen'] as $i => $rkey)
			{
				if (($cat = $this->cats->read(array('rkey'=>$rkey))))
				{
					$cat2col[$cat['GrpId']] = $tmpl->num2chrs($i);
					$readonlys['download['.$cat['GrpId'].']'] = true;
				}
			}
			$readonlys['download'] = true;

			if ($nation)	// read prequalified athlets
			{
				$prequalified = $this->national_prequalified($comp,$nation);
				//_debug_array($prequalified);
			}
			//_debug_array($cat2col);
			if (!$this->registration_check($comp,$nation))	// user allowed to register that nation
			{
				echo "<h1>user not allowed to register nation '$nation'</h1>\n";
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
					//_debug_array($cat);
					//_debug_array($comp);
					$msg = lang('Permission denied !!!');
				}
				elseif($content['delete'])
				{
					$msg = $this->register($comp['WetId'],$cat['GrpId'],$athlete['PerId'],2) ?
						lang('%1, %2 deleted for category %3',strtoupper($athlete['nachname']), $athlete['vorname'], $cat['name']) :
						lang('Error: registration');
				}
				else // register
				{
					$msg = $this->register($comp['WetId'],$cat['GrpId'],$athlete,isset($prequalified[$cat['GrpId']][$athlete['PerId']])) ?
						lang('%1, %2 registered for category %3',strtoupper($athlete['nachname']), $athlete['vorname'], $cat['name']) :
						lang('Error: registration');
				}
			}
			// generate a startlist
			elseif ($content['startlist'] && $this->acl_check($comp['nation'],EGW_ACL_RESULT,$comp))
			{
				$cats = false;
				list($cat) = @each($content['startlist']);
				foreach($cat ? array($cat) : $comp['gruppen'] as $cat)
				{
					if ($cat && ($cat = $this->cats->read($cat)))
					{
						$max_compl = $content['max_compl'][$cat['GrpId']];
						if ($max_compl === '') $max_compl = 999;	// all
						$num_routes = $content['num_routes'][$cat['GrpId']];
						
						if ($num_routes && $this->generate_startlist($comp,$cat,$num_routes,$max_compl,1))
						{
							$cats[] = $cat['name'];
						}
						else
						{
							break;
						}
					}
				}
				if ($cats)
				{
					$msg .= lang('Startlist for category %1 generated',implode(', ',$cats));
				}
				else
				{
					$msg = lang('Error: generating startlist!!!');
				}
			}
			elseif ($content['download'])
			{
				list($cat) = @each($content['download']);
				return $this->lists(array(
					'comp' => $comp['WetId'],
					'cat'  => (int) $cat,
					'download' => 1,
				));
			}	
			$starters =& $this->result->read(array(
				'WetId'  => $comp['WetId'],
				'GrpId'  => -1,
			)+($nation ? array(
				'nation' => $nation,
			):array()),'',true,'nation,GrpId,reg_nr');
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
						if (!is_array($athlete)) continue;

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
			$rows = array(false,false);	// we need 2 to be the index of the first row
			$starters[] = array('nation'=>'');	// to get the last line out
			foreach((array)$starters as $starter)
			{
				// download button only if there's a startlist (platz==0 && pkt>64)
				$download = 'download['.$starter['GrpId'].']';
				if ($starter['GrpId'] && (!isset($readonlys[$download]) || $readonlys[$download] || $starter['platz']))
				{
					// outside SiteMgr we always offer the download
					$readonlys['download'] = $readonlys[$download] = $tmpl->sitemgr && !$starter['platz'] && $starter['pkt'] < 64;
				}
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
			foreach((array)$cat2col as $cat => $col)
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
			// dont show registration line if no comp, in sitemgr or no registration rights
			'registration' => $comp && !$tmpl->sitemgr ? $this->registration_check($comp) : false,
			'rows'     => &$rows,
			'cats'     => &$cats,
			'count'    => $starters ? count($starters)-1 : 0,	// -1 as we add an empty starter at the end
			'msg'      => $msg,
			'deadline' => $comp ? $comp['deadline'] : '',
		);
		if ($cats)
		{
			foreach($cats as $col => $cat)
			{
				$content['startlist'][$col] = array(
					'num_routes' => 'num_routes['.$cat['GrpId'].']',
					'max_compl'  => 'max_compl['.$cat['GrpId'].']',
					'button'     => 'startlist['.$cat['GrpId'].']',
					'download'   => 'download['.$cat['GrpId'].']',
				);
			}
		}
		// dont show startlist options, if no comp selected, in sitemgr, no starters, a nation selected or no rights to generate a startlist
		if (!$comp || $tmpl->sitemgr || count($starters) <= 1 || $nation || !$this->acl_check($comp['nation'],EGW_ACL_RESULT,$comp))
		{
			$content['startlist'] =  false;
		}
		// save calendar, competition & nation between calls in the session
		$GLOBALS['egw']->session->appsession('registration','ranking',$preserv);
		//_debug_array($content);
		$GLOBALS['egw_info']['flags']['app_header'] = lang('ranking').' - '.lang('Registration').
			($nation && $nation != 'NULL' ? ': '.$nation : '');
		return $tmpl->exec('ranking.uiregistration.index',$content,$select_options,$readonlys,$preserv);
	}

	function result()
	{
		return $this->lists(null,'','result');
	}

	function startlist()
	{
		return $this->lists(null,'','startlist');
	}

	/**
	 * Show/download the startlist or result of a competition for one or all categories
	 *
	 * @param array $content
	 * @param string $msg
	 * @param string $show='' 'startlist','result' or '' for whatever is availible
	 */
	function lists($content=null,$msg='',$show='')
	{
		//echo "uiregistration::lists(,'$msg','$show') content="; _debug_array($content);

		$tmpl =& new etemplate('ranking.register.lists');
		
		if (!is_array($content))
		{
			if ($_GET['calendar'] || $_GET['comp'])
			{
				$content['calendar'] = $_GET['calendar'];
				$content['comp'] = $_GET['comp'];
				$content['cat']  = $_GET['cat'];
				$content['download'] = $_GET['download'];
			}
			else
			{
				$content = $GLOBALS['egw']->session->appsession('registration','ranking');
			}
		}
		if ($content['comp']) $comp = $this->comp->read($content['comp']);
		$cat      = $content['cat'];

		if ($tmpl->sitemgr && $_GET['comp'] && $comp)	// no calendar and/or competition selection, if in sitemgr the comp is direct specified
		{
			$readonlys['calendar'] = $readonlys['comp'] = true;
		}
		if ($this->only_nation)
		{
			$calendar = $this->only_nation;
			$tmpl->disable_cells('calendar');
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
		if ($comp && $cat && (!($cat = $this->cats->read($cat)) || !in_array($cat['rkey'],$comp['gruppen'])))
		{
			$cat = '';
			//$msg = lang('Unknown category or not a category of this competition');
		}
		if ($comp)
		{
			$keys = array(
				'WetId'  => $comp['WetId'],
				'GrpId'  => $cat ? $cat['GrpId'] : -1,
			);
			// if we already have a result, dont include starters without result
			if ($show == 'result' || !$show && $this->result->has_results($keys))
			{
				$show = 'result';
				$keys[] = 'platz > 0';
				$order = 'GrpId,platz,nachname,vorname';
			}
			// if we have a startlist (not just starters) sort by startnumber
			elseif ($show == 'startlist' || !$show && $this->result->has_startlist($keys))
			{
				$keys[] = 'platz=0 AND pkt > 64';
				$show = 'startlist';
				$order = 'GrpId,pkt,nachname,vorname';
			}
			else	// sort by nation 
			{
				$keys['platz'] = 0;
				$order = 'GrpId,nation,pkt,nachname,vorname';
			}
			$starters =& $this->result->read($keys,'',true,$order);
			
			if ($content['download'] && $starters && count($starters))
			{
				$browser =& CreateObject('phpgwapi.browser');
				$browser->content_header($comp['rkey'].'.csv','text/comma-separated-values');
				$name2csv = array(
					'WetId'    => 'comp',
					'GrpId'    => 'cat',
					'PerId'    => 'athlete',
					'platz'    => 'place',
					'category',			
					$show == 'startlist' ? 'startnumber' : 'points',
					'nachname' => 'lastname',
					'vorname'  => 'firstname',
					'nation'   => 'nation',
					'geb_date' => 'birthdate',
					'ranking',
					'ranking-points',
				);
				echo implode(';',$name2csv)."\n";
				$charset = $GLOBALS['egw']->translation->charset();
				$c['GrpId'] = 0;
				foreach($starters as $athlete)
				{
					if ($c['GrpId'] != $athlete['GrpId'])
					{
						$c = $this->cats->read($athlete['GrpId']);
						
						$stand = $comp['datum'];
		 				$this->ranking($c,$stand,$nul,$test,$ranking,$nul,$nul,$nul);
					}
					$values = array();
					foreach($name2csv as $name => $csv)
					{
						switch($csv)
						{
							case 'startnumber':
								//$val = ($athlete['pkt'] >> 14 ? (1+($athlete['pkt'] >> 14)).': ' : '') .(($athlete['pkt'] >> 6) & 255);
								$val = $this->pkt2start($athlete['pkt']);
								break;
							case 'points':
								$val = $athlete['pkt'];
								break;
							case 'place':
								$val = $athlete['platz'] ? $athlete['platz'] : '';
								break;
							case 'category':
								$val = $c['name'];
								break;
							case 'ranking':
								$val = $ranking[$athlete['PerId']]['platz'];
								break;
							case 'ranking-points':
								$val = isset($ranking[$athlete['PerId']]) ? sprintf('%1.2lf',$ranking[$athlete['PerId']]['pkt']) : '';
								break;
							default:
								$val = $athlete[$name];
						}
						if (strchr($val,';') !== false)
						{
							$val = '"'.str_replace('"','',$val).'"';
						}
						$values[$csv] = $val;
					}
					// convert by default to iso-8859-1, as this seems to be the default of excel
					echo $GLOBALS['egw']->translation->convert(implode(';',$values),$charset,
						$_GET['charset'] ? $_GET['charset'] : 'iso-8859-1')."\n";
				}
				$GLOBALS['egw']->common->egw_exit();
			}
			if (!$show || !$starters || !count($starters))
			{
				// if we have registrations, show them
				if($this->result->read(array(
					'WetId'  => $comp['WetId'],
					'GrpId'  => $cat ? $cat['GrpId'] : -1,
				),'',true))
				{
					return $this->index(array(
						'calendar' => $calendar,
						'comp'     => $comp['WetId'],
					));
				}
				$msg = lang('Competition has not yet a startlist');
				$readonlys['download'] = true;
			}
			else
			{
				$c = $cat;
				$rows = array(false);
				foreach($starters as $athlete)
				{
					if ($athlete['GrpId'] != $c['GrpId'])
					{
						$c = $this->cats->read($athlete['GrpId']);
					}
					$rows[] = $athlete + array(
						'start'    => ($athlete['pkt'] >> 14 ? (1+($athlete['pkt'] >> 14)).': ' : '') .
							(($athlete['pkt'] >> 6) & 255),
						'year'     => substr($athlete['geb_date'],0,4),
						'cat_name' => $c['name'],
					);
				}
				unset($starters);
				//_debug_array($rows);
			}
		}
		$content = $preserv = array(
			'calendar' => $calendar,
			'comp'     => $comp['WetId'],
			'cat'      => $cat ? $cat['GrpId'] : '',
		);
		$content += array(
			'rows'     => $rows,
			'msg'      => $msg,
			'result'   => $athlete['platz'] > 0,
		);
		// save calendar, competition & cat between calls in the session
		$GLOBALS['egw']->session->appsession('registration','ranking',$preserv);
		
		$select_options = array(
			'calendar' => $this->ranking_nations,
			'cat'      => $this->cats->names(array('rkey' => $comp['gruppen']),0),
		);
		// are we showing a result or a startlist
		if ($comp && $athlete['platz'] > 0 || $show == 'result')
		{
			$GLOBALS['egw_info']['flags']['app_header'] = lang('ranking').' - '.lang('Results');
			$select_options['comp'] = $this->comp->names(array(
				'nation' => $calendar,
				'datum < '.$this->db->quote(date('Y-m-d')),
			),0,'datum DESC');
		}
		else
		{
			$GLOBALS['egw_info']['flags']['app_header'] = lang('ranking').' - '.lang('Startlists');
			$select_options['comp'] = $this->comp->names(array(
				'nation' => $calendar,
				'WetId' => $this->result->comps_with_startlist(array('nation' => $calendar)),
			),0,'datum ASC');
		}
		// anyway include the used competition
		if ($comp && !isset($select_options['comp'][$comp['WetId']]))
		{
			$select_options['comp'] = array_merge(array(
				$comp['WetId']	=> $comp['name']
			),$select_options['comp']);
		}
		return $tmpl->exec('ranking.uiregistration.lists',$content,$select_options,$readonlys,$preserv);
	}
}
