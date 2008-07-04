<?php
/**
 * eGroupWare digital ROCK Rankings - registration UI
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2006-8 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$
 */

require_once(EGW_INCLUDE_ROOT.'/ranking/inc/class.boranking.inc.php');
require_once(EGW_INCLUDE_ROOT.'/etemplate/inc/class.uietemplate.inc.php');

class uiregistration extends boranking
{
	/**
	 * functions callable via menuaction
	 *
	 * @var array
	 */
	var $public_functions = array(
		'lists'     => true,
		'result'    => true,
		'startlist' => true,
		'index'     => true,
		'add'       => true,
	);

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

		// admins and judges are allowed to EXECPTIONAL register athletes without license
		$allow_no_license_register = $this->is_admin || $this->is_judge($query['comp']);

		$readonlys = array();
		foreach($rows as &$row)
		{
			if ($row['license'] == 'n')	// athlete has NO license
			{
				if ($allow_no_license_register)	// admins or judges have to confirm the registration
				{
					$row['confirm'] = "if(confirm('This athlete has NO license! Are you sure you want to make an EXCEPTION')) ";
				}
				else	// others are denied to register
				{
					$readonlys["register[$row[PerId]]"] = true;
				}
			}
			elseif ($row['license'] == 's')	// suspended athlets can NOT be registered
			{
				$readonlys["register[$row[PerId]]"] = true;
			}
			$readonlys["apply_license[$row[PerId]]"] = $row['license'] != 'n';
		}
		$rows['sel_options'] =& $sel_options;
		$rows['comp'] = $query['comp'];
		$rows['cat']  = $query['cat'];
		$rows['license_year'] = $query['col_filter']['license_year'];
		$rows['license_nation'] = $query['col_filter']['license_nation'];

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
			if ($_GET['comp'] && $_GET['nation'] && $_GET['cat'])
			{
				$content = array(
					'comp'     => $_GET['comp'],
					'nation'   => $_GET['nation'],
					'cat'      => $_GET['cat'],
				);
			}
			else
			{
				$content = $GLOBALS['egw']->session->appsession('registration','ranking');
			}
			if ($_GET['msg']) $msg = $_GET['msg'];
		}
		$nation = $content['nation'];
		$comp   = $content['comp'];
		$cat    = $content['cat'];
		$show_all = $content['show_all'];

		if (!($comp = $this->comp->read($comp)) || 			// unknown competition
			!$this->acl_check_athlete(array('nation'=>$nation,'fed_id'=>$nation),EGW_ACL_REGISTER,$comp) || // no rights for that nation/federation
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
					'license_nation' => $comp['nation'],
					'license_year'   => (int)$comp['datum'],
				),
				'comp'           => $comp['WetId'],
				'csv_fields'     => false,
			),
		);
		if ($nation && !is_numeric($nation))
		{
			$content['nm']['col_filter']['nation'] = $nation;
			if (!$show_all && $_GET['nation'] && $cat['nation'] && $_GET['nation'] != $cat['nation'])
			{
				$show_all = true;	// automatic show all cat's if cat has a nation and the given one does not match
			}
		}
		elseif (is_numeric($nation))
		{
			$content['nm']['col_filter'][] = '(fed_parent='.(int)$nation.' OR fed_id='.(int)$nation.')';
		}
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
			'license' => $this->license_labels,
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
					'cat'      => $_GET['cat'],
				);
				if($content['comp']) $comp = $this->comp->read($content['comp']);

				if ($_GET['athlete'] && ($athlete = $this->athlete->read($_GET['athlete'],'',$this->license_year,$comp['nation'])))
				{
					$nation = $content['nation'] = $comp['nation'] && $comp['nation'] == $athlete['nation'] && $athlete['fed_parent'] ?
						$athlete['fed_parent'] : $athlete['nation'];
				}
				$GLOBALS['egw']->session->appsession('registration','ranking',$content);
			}
			else
			{
				$content = $GLOBALS['egw']->session->appsession('registration','ranking');
			}
		}
		if($content['comp'] && !isset($comp))
		{
			$comp = $this->comp->read($content['comp']);
		}
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
		if (!isset($nation))
		{
			$nation = $content['nation'];
		}
		if (is_null($nation) && !$this->is_judge($comp))
		{
			if ($this->only_nation_register)
			{
				$nation = $this->only_nation_register;
			}
			else
			{
				list($nation) = @each($this->federation->get_user_grants());
			}
		}
		$select_options = array(
			'calendar' => $this->ranking_nations,
			'comp'     => $this->comp->names(array(
				'nation' => $calendar,
				'datum >= '.$this->db->quote(date('Y-m-d',time()-2*24*60*60)),	// all events starting 2 days ago or further in future
				'gruppen IS NOT NULL',
			),0,'datum ASC'),
			'nation' => $this->federation->get_competition_federations($comp['nation'],
				!$this->is_admin && !$this->is_judge($comp) ? $this->register_rights : null),	// limit non-judge to feds user has registration rights
		);
		if ($comp && !isset($select_options['comp'][$comp['WetId']]))
		{
			$select_options['comp'][$comp['WetId']] = $comp['name'];
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
				//echo "<h1>user not allowed to register nation '$nation'</h1>\n";
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
					$athlete = $this->athlete->read($athlete,'',$this->license_year,$comp['nation']);
				}
				if (!($cat = $this->cats->read($cat)) || !in_array($cat['rkey'],$comp['gruppen']) ||
					$cat['sex'] && $athlete['sex'] != $cat['sex'] ||
					$content['register'] && $athlete['license'] == 'n' && !$this->is_admin && !$this->is_judge($comp))
				{
					//_debug_array($cat);
					//_debug_array($comp);
					$msg = lang('Permission denied !!!');
				}
				elseif (!$this->registration_check($comp,$nation,$cat['GrpId']))
				{
					$msg = lang('Competition already has a result for this category!');
				}
				elseif($content['delete'])
				{
					$msg = $this->register($comp['WetId'],$cat['GrpId'],$athlete['PerId'],2) ?
						lang('%1, %2 deleted for category %3',strtoupper($athlete['nachname']), $athlete['vorname'], $cat['name']) :
						lang('Error: registration');
				}
				elseif($athlete['license'] == 's')
				{
					$msg = lang('Athlete is suspended !!!');
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
			$where = array(
				'WetId'  => $comp['WetId'],
				'GrpId'  => -1,
			);
			if ($nation)	// filter by given nation/federation
			{
				if (!$comp['nation'] || $nation == $comp['nation'])	// int. competition
				{
					$where['nation'] = $nation;
				}
				else		// national competition
				{
					$where[] = is_numeric($nation) ? 'fed_parent='.(int)$nation : 'nation='.$this->db->quote($nation).' AND fed_parent IS NULL';
				}
			}
			$starters =& $this->result->read($where,'',true,$comp['nation'] ? 'nation,fed_parent,GrpId,reg_nr' : 'nation,GrpId,reg_nr');

			$nat = '';
			$nat_starters = array();
			$prequal_lines = 0;
			// if nation/federation selected, show prequalified first
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
						if ($athlete['license'] == 'n') $readonlys[$register_button] = true;	// no register without license
					}
					if (count($athletes) > $prequal_lines)
					{
						$prequal_lines = count($athletes);
						$nat = lang('Prequalified');
					}
				}
			}
			// show the regular registered (not prequalified) starters
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
				$starter_nat_fed = !$comp['nation'] || $starter['nation'] != $comp['nation'] || !$starter['fed_parent'] ?
					$starter['nation'] : $starter['fed_parent'];
				if ($nat != $starter_nat_fed)
				{
					foreach($nat_starters as $i => $row)
					{
						if (is_numeric($nat) && ($fed = $this->federation->read($nat)))
						{
							$nat = $fed['fed_shortcut'] ? $fed['fed_shortcut'] : $fed['verband'];
						}
						$rows[] = array(
							'nation' => !$nation || $nat && $nat != $nation || !$nat && $i != $quota ? $nat :
								($i == $quota ? lang('Complimentary') : lang('Quota')),
						) + $row;
						$nat = '';
					}
					$nat_starters = array();
					$nat = $starter_nat_fed;
					// ToDo: quota for national competition depending on category and fed_parent
					$quota = $comp['host_quota'] && $comp['host_nation'] == $nation ? $comp['host_quota'] : $comp['quota'];
				}
				if ($nation && isset($prequalified[$starter['GrpId']][$starter['PerId']]))
				{
					continue;	// prequalified athlets are in an own block
				}
				// set a new column for an unknown/new rkey/cat
				if ($starter_nat_fed /*???*/ && !isset($cat2col[$starter['GrpId']]))
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
				$readonlys[$delete_button] = !($this->is_admin || $this->is_judge($comp) || in_array($starter['nation'],$this->register_rights));	// re-enable the button
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
		if (!$comp || $tmpl->sitemgr || count($starters) <= 1 || $nation && $nation != $comp['nation'] || !$this->acl_check($comp['nation'],EGW_ACL_RESULT,$comp))
		{
			$content['startlist'] =  false;
		}
		// save calendar, competition & nation between calls in the session
		$GLOBALS['egw']->session->appsession('registration','ranking',$preserv);
		$this->set_ui_state($perserv['calendar'],$preserv['comp'],$preserv['cat']);
		//_debug_array($content);
		$GLOBALS['egw_info']['flags']['app_header'] = lang('ranking').' - '.lang('Registration').
			($nation && $nation != 'NULL' ? ': '.$nation : '');
		return $tmpl->exec('ranking.uiregistration.index',$content,$select_options,$readonlys,$preserv);
	}

	/**
	 * Show a result list (from the ranking NOT the result service!)
	 *
	 * @return string
	 */
	function result()
	{
		return $this->lists(null,'','result');
	}

	/**
	 * Show a start list (from the ranking NOT the result service!)
	 *
	 * @return string
	 */
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
	 *
	 * @return string
	 */
	function lists($content=null,$msg='',$show='')
	{
		//echo "uiregistration::lists(,'$msg','$show') content="; _debug_array($content);

		$tmpl =& new etemplate('ranking.register.lists');

		if ($tmpl->sitemgr && !count($this->ranking_nations))
		{
			return lang('No rights to any nations, admin needs to give read-rights for the competitions of at least one nation!');
		}
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
			if ($content['upload'] && is_uploaded_file($content['file']['tmp_name']))
			{
				$msg = $this->upload(array('WetId'=>$content['comp'],'GrpId'=>$content['cat']),$content['file']['tmp_name']);
				// re-read the starters
				$starters =& $this->result->read($keys,'',true,$order);
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
			'no_upload' => !$comp || !$cat || !$this->acl_check($comp['nation'],EGW_ACL_RESULT,$comp),
		);
		// save calendar, competition & cat between calls in the session
		$GLOBALS['egw']->session->appsession('registration','ranking',$preserv);
		$this->set_ui_state($perserv['calendar'],$preserv['comp'],$preserv['cat']);

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
			$select_options['comp'] = array(
				$comp['WetId']	=> $comp['name']
			)+$select_options['comp'];
		}
		return $tmpl->exec('ranking.uiregistration.lists',$content,$select_options,$readonlys,$preserv);
	}

	/**
	 * Upload a result as csv file
	 *
	 * @param array $keys WetId, GrpId, route_order
	 * @param string $file uploaded file
	 * @param string/int error message or number of lines imported
	 */
	function upload($keys,$file)
	{
		if (!$keys || !$keys['WetId'] || !$keys['GrpId'] ||
			!($comp = $this->comp->read($keys['WetId'])) ||
			!$this->acl_check($comp['nation'],EGW_ACL_RESULT,$comp))
		{
			return lang('Permission denied !!!');
		}
		$csv = $this->parse_csv($keys,$file,true);

		if (!is_array($csv)) return $csv;

		return $this->import_ranking($keys,$csv);
	}
}
