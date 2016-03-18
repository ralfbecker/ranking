<?php
/**
 * EGroupware digital ROCK Rankings - registration UI
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2006-16 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$
 */

class ranking_registration_ui extends ranking_bo
{
	/**
	 * functions callable via menuaction
	 *
	 * @var array
	 */
	var $public_functions = array(
		//'lists'     => true,
		'result'    => true,
		//'startlist' => true,
		'index'     => true,
		//'add'       => true,
	);

	/**
	 * query registrations for nextmatch
	 *
	 * @param array &$query
	 * @param array &$rows returned rows/cups
	 * @param array &$readonlys eg. to disable buttons based on acl
	 */
	function get_rows(&$query, &$rows, &$readonlys)
	{
		//error_log(__METHOD__."(".array2string($query).")");

		/*$this->registration->read($where,'',true,$comp['nation'] ? 'nation,acl_fed_id,fed_parent,acl.fed_id,GrpId' : 'nation,GrpId');
		/*echo "ranking_athlete_ui::get_rows() query="; _debug_array($query);
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
		$allow_no_license_register = $this->allow_no_license_registration($query['comp']);

		$readonlys = array();
		foreach($rows as &$row)
		{
			if ($row['license'] == 'n')	// athlete has NO license
			{
				if ($allow_no_license_register)	// admins or judges have to confirm the registration
				{
					if ($allow_no_license_register !== true) $row['confirm'] = "if(confirm('".addslashes($allow_no_license_register)."')) ";
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
		$rows['license_nation'] = $query['col_filter']['license_nation'];*/

		if ($query['comp']) $comp = $this->comp->read($query['comp']);

		if (!$comp || ($comp['nation']?$comp['nation']:'NULL') != $query['calendar'])
		{
			$query['comp'] = '';
		}
		$query['col_filter']['WetId'] = $query['comp'];
		$query['col_filter']['nation'] = $query['nation'];

		egw_cache::setSession('ranking', 'registration', $state=array(
			'calendar' => $query['calendar'],
			'comp'     => $query['comp'],
			'nation'   => $query['nation'],
			'col_filter' => $query['col_filter'],
		));
		//error_log(__METHOD__."() Cache::setSession('ranking', 'registration', ".array2string($state).")");
		$this->set_ui_state($query['calendar'], $query['comp'], $query['col_filter']['GrpId']);

		$total = $this->registration->get_rows($query, $rows, $readonlys, true);

		foreach($rows as &$row)
		{
			$row['id'] = $row['WetId'].':'.$row['GrpId'].':'.$row['PerId'];

			// add one of "is(Deleted|Confirmed|Registered|Preregisted)" classes
			foreach(ranking_registration::$states as $state)
			{
				if (!empty($row[ranking_registration::PREFIX.$state]))
				{
					if (!isset($row['state']))
					{
						$row['class'] .= ' is'.ucfirst($state);
						$row['state'] = lang($state);
					}
					$modifier = $row[ranking_registration::PREFIX.$state.ranking_registration::ACCOUNT_POSTFIX];
					$row['state_changed'] .= egw_time::to($row[ranking_registration::PREFIX.$state]).': '.lang($state).' '.
						($modifier ? lang('by').' '.common::grab_owner_name($modifier).' ' : '')."<br>\n";
				}
			}
			//error_log(__METHOD__."() ".array2string($row));
		}

		$rows['sel_options'] = array(
			'comp'     => $this->comp->names(array(
				'nation' => $query['calendar'],
				'datum >= '.$this->db->quote(date('Y-m-d',time()-2*24*60*60)),	// all events starting 2 days ago or further in future
				'gruppen IS NOT NULL',
			),0,'datum ASC'),
			'nation' => $this->federation->get_competition_federations($query['calendar'],
				$query['allow_register_everyone'] ? null : $this->register_rights),
			'GrpId' => $comp['gruppen'] ? $this->cats->names(array('rkey' => $comp['gruppen']),0) : array(lang('No categories defined!')),
		);

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
				$content = egw_cache::getSession('ranking', 'registration');
			}
			if ($_GET['msg']) $msg = $_GET['msg'];
		}
		$nation = $content['nation'];
		$comp   = $content['comp'];
		$cat    = $content['cat'];
		$show_all = $content['show_all'];

		if (!($comp = $this->comp->read($comp)) || 			// unknown competition
			!$this->registration_check($comp, $nation) ||	// no rights to register for that competition or nation
			!($cat  = $this->cats->read($cat ? $cat : $comp['gruppen'][0])) ||	// unknown category
			(!in_array($cat['rkey'],$comp['gruppen'])))		// cat not in this competition
		{
			$msg = lang('Permission denied !!!');
		}
		$cont = $preserv = array(
			'comp'     => $comp['WetId'],
			'nation'   => $nation,
			'nm'       => $content['nm'] ? $content['nm'] : array(
				'get_rows'       =>	'ranking.ranking_registration_ui.get_rows',
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
			$cont['nm']['col_filter']['nation'] = $nation;
			if (!$show_all && $_GET['nation'] && $cat['nation'] && $_GET['nation'] != $cat['nation'])
			{
				$show_all = true;	// automatic show all cat's if cat has a nation and the given one does not match
			}
		}
		elseif (is_numeric($nation))
		{
			$cont['nm']['col_filter']['fed_id'] = (int)$nation;
		}
		$cont += array(
			'comp_name' => $comp ? $comp['name'] : '',
			'cat'       => $cat['GrpId'],
			'show_all'  => $show_all,
			'msg'       => $msg,
		);
		// make (maybe changed) category infos avalible for nextmatch
		$cont['nm']['cat'] = $cat['GrpId'];
		if ($cat['sex'])
		{
			$cont['nm']['col_filter']['sex'] = $cat['sex'];
		}
		else
		{
			unset($cont['nm']['col_filter']['sex']);
		}
		$cont['nm']['show_all'] = $show_all;

		$select_options = array(
			'cat' => $comp['gruppen'] ? $this->cats->names(array('rkey' => $comp['gruppen']),0) : array(lang('No categories defined!')),
			'license' => $this->license_labels,
		);
		//_debug_array($cont);
		$GLOBALS['egw_info']['flags']['app_header'] = lang('ranking').' - '.lang('Register');
		$tmpl = new etemplate('ranking.register.add');
		$tmpl->exec('ranking.ranking_registration_ui.add',$cont,$select_options,null,$preserv,2);
	}

	/**
	 * Search for athletes to register
	 */
	public function ajax_search()
	{
		try {
			$state = egw_cache::getSession('ranking', 'registration');

			// show already registered first, then prequalified, then rest of category, then matches with error
			$results = array(lang('Already registered') => array(), lang('Prequalified') => array(), '' => array());

			// complile array of prequalified
			$prequalified = $registered = array();
			if ($state['comp'] && (int)$_REQUEST['GrpId'] > 0)
			{
				// add prequalified by competition result
				$prequalified = $this->national_prequalified($state['comp'], $state['nation']);
				foreach((array)$prequalified[(int)$_REQUEST['GrpId']] as $athlete)
				{
					error_log(__METHOD__."() prequalified athlete=".array2string($athlete));
					$prequalified[$athlete['PerId']] = $this->athlete->link_title($athlete, true);
				}

				// add registered and explicit prequalified stored in registration
				foreach($this->registration->read(array(
						'WetId' => $state['comp'],
						'GrpId' => $_REQUEST['GrpId'],
						ranking_registration::PREFIX.ranking_registration::DELETED.' IS NULL',
					)) as $athlete)
				{
					if ($athlete['reg_registered'])
					{
						$registered[$athlete['PerId']] = $this->athlete->link_title($athlete, true);
						unset($prequalified[$athlete['PerId']]);	// just in case
					}
					elseif($athlete['reg_prequalified'])
					{
						$prequalified[$athlete['PerId']] = $this->athlete->link_title($athlete, true);
					}
				}
				error_log(__METHOD__."(".array2string($_REQUEST).") registered=".count($registered).", prequalified=".count($prequalified));
			}
			$options = array(
				'GrpId' => (int)$_REQUEST['GrpId'],
				is_numeric($_REQUEST['nation']) ? 'fed_parent' : 'nation' => $_REQUEST['nation'],
				'sex' => $_REQUEST['sex'],
				'num_rows' => 100,
			);
			$links = egw_link::query('ranking', $_REQUEST['query'], $options);
			foreach($links as $id => $label)
			{
				if (($sort = (string)$label['error']))
				{
					// keep error as grouping
				}
				elseif (isset($registered[$id]))
				{
					$sort = lang('Already registered');
				}
				elseif (isset($prequalified[$id]))
				{
					$sort = lang('Prequalified');
				}
				$results[(string)$sort][] = array(
					'id' => $id,
					'group' => $sort ? $sort : ((int)$_REQUEST['GrpId'] ? lang('Selected category') : null),
					'label' => $label['label'],
					'title' => $label['title'],
				);
			}
			if (count($links) < $options['total'])
			{
				$results[] = array(array(
					'id' => 'more',
					'group' => lang('Only %1 of %2 entries displayed!', count($links), $options['total']),
					'label' => lang('Narrow your search'),
				));
			}
		}
		catch(Exception $e)
		{
			$results = array(array(array(
				'id' => 'err',
				'group' => lang('An error happened'),
				'label' => $e->getMessage(),
			)));
		}
		 // switch regular JSON response handling off
		egw_json_request::isJSONRequest(false);

		header('Content-Type: application/json; charset=utf-8');
		echo json_encode(call_user_func_array('array_merge', $results));
		common::egw_exit();
	}

	/**
	 * Register athlete(s)
	 *
	 * @param array $params values for following keys:
	 * - string    mode "register", "delete", "prequalify" or "confirmed"
	 * - int|array PerId
	 * @return array
	 */
	function ajax_register(array $params)
	{
		static $mode2ts = array(
			'delete'     => ranking_registration::DELETED,
			'register'   => ranking_registration::REGISTERED,
			'prequalify' => ranking_registration::PREQUALIFIED,
			'confirm'    => ranking_registration::CONFIRMED,
		);
		error_log(__METHOD__."(".array2string($params));

		$registered = 0;

		if (!isset($mode2ts[$params['mode']]))
		{
			$error = "Invalid mode '$params[mode]'!";
		}
		elseif (!($comp = $this->comp->read($params['WetId'])))
		{
			$error = lang('Competition NOT found !!!');
		}
		elseif (!($cat = $this->cats->read($params['GrpId'])))
		{
			$error = lang('Category NOT found !!!');
		}
		if (in_array($params['mode'], array('prequalify', 'confirm')) && !$this->acl_check_comp($comp))
		{
			$error = lang('Permission denied !!!');
		}
		else
		{
			$msg = '';
			foreach((array)$params['PerId'] as $id)
			{
				if (!($athlete = $this->athlete->read($id, '', (int)$comp['datum'])))
				{
					$error = lang('Athlete NOT found !!!');
					break;
				}
				if ($params['mode'] == 'register' && $athlete['license'] == 'n' &&
					is_string($question = $this->allow_no_license_registration($comp)))
				{
					if (empty($params['confirmed']) || $params['confirmed'] != $athlete['PerId']) break;
					unset($question);
				}
				// register the user
				try {
					if ($this->register($comp, $cat, $athlete, $mode2ts[$params['mode']], $msg))
					{
						$registered++;
						if ($msg) break;	// stop, if over quota warning for admins/jury
					}
					else
					{
						$error = lang('Error: registration');
						break;
					}
				}
				catch(Exception $e) {
					$error = $e->getMessage();
					break;
				}
			}
		}
		$msg = ($registered == 1 && $athlete ? $this->athlete->link_title($athlete) : $registered).
			' '.lang($mode2ts[$params['mode']]).'.'.($msg ? "\n$msg" : '');

		if ($error)
		{
			if ($athlete)
			{
				$error = $this->athlete->link_title($athlete)."\n\n".$error.
					($registered && $msg ? "\n\n".$msg : '');

				// tell client-side how many successful registered and therefore to remove from selection
				if ($registered) egw_json_response::get()->data(array(
					'registered' => $registered,
				));
			}
			egw_json_response::get()->call('egw.message', $error, 'error');
		}
		else
		{
			if ($registered) egw_json_response::get()->call('egw.refresh', $msg, 'ranking');

			// tell client-side how many successful registered and therefore to remove from selection,
			// plus evtl. question and concerned athlete
			egw_json_response::get()->data(array(
				'registered' => $registered,
			)+(!isset($question) ? array() : array(
				'question'  => $question,
				'PerId'     => $athlete['PerId'],
				'athlete'   => $this->athlete->link_title($athlete),
			)));
		}
	}

	/**
	 * Show the registrations of a competition and allow to register for it
	 *
	 * @param array $_content
	 */
	function index($_content=null)
	{
		$tmpl = new etemplate_new('ranking.registration');

		if (!is_array($_content))
		{
			if ($_GET['calendar'] || $_GET['comp'])
			{
				$state = array(
					'calendar' => $_GET['calendar'],
					'comp'     => $_GET['comp'],
					'nation'   => $_GET['nation'],
					'col_filter' => array('GrpId' => $_GET['cat']),
				);
				if($state['comp']) $comp = $this->comp->read($state['comp']);

				if ($_GET['athlete'] && ($athlete = $this->athlete->read($_GET['athlete'],'',$this->license_year,$comp['nm']['nation'])))
				{
					$nation = $state['nation'] = $comp['nation'] && $comp['nation'] == $athlete['nation'] && $athlete['fed_parent'] ?
						$athlete['fed_parent'] : $athlete['nation'];
				}
			}
			else
			{
				$state = (array)egw_cache::getSession('ranking', 'registration');
			}
			$state += array(
				'get_rows'       =>	'ranking.ranking_registration_ui.get_rows',
				'no_cat'         => true,
				'no_filter'      => true,
				'no_filter2'     => true,
				'order'          =>	'nachname',// IO name of the column to sort after (optional for the sortheaders)
				'sort'           =>	'ASC',// IO direction of the sort: 'ASC' or 'DESC'
				'row_id'         => 'id',
				'actions'        => self::get_actions(),
			);
		}
		else
		{
			$state = $_content['nm'];
		}
		error_log(__METHOD__."() state=".array2string($state));
		if($state['comp'] && !isset($comp))
		{
			$comp = $this->comp->read($state['comp']);
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
		elseif ($state['calendar'])
		{
			$calendar = $state['calendar'];
		}
		else
		{
			list($calendar) = each($this->ranking_nations);
		}
		if (!isset($nation))
		{
			$nation = $state['nation'];
		}
		if (is_null($nation) && !$this->is_judge($comp))
		{
			if ($this->only_nation_register && $this->only_nation_register != 'XYZ')
			{
				$nation = $this->only_nation_register;
			}
			// preselect the first nation/federation the user has register-rights for
			else
			{
				foreach($this->federation->get_user_grants() as $n => $r)
				{
					if ($r & EGW_ACL_REGISTER)
					{
						$nation = $n;
						break;
					}
				}
			}
		}
		// limit non-judge to feds user has registration rights
		$allow_register_everyone = $state['allow_register_everyone'] = $this->is_admin || $this->is_judge($comp,true) ||
			// national edit rights allow to register foreign athlets for national competition
			in_array($comp['nation'],$this->register_rights) && in_array($comp['nation'],$this->edit_rights) ||
			$comp && $this->acl_check_comp($comp);	// allow people with edit rights to a competition to register everyone
		//error_log(__METHOD__."() allow_register_everyone=".array2string($allow_register_everyone).', is_judge='.array2string($this->is_judge($comp)));

		$select_options = array(
			'calendar' => $this->ranking_nations,
			'comp'     => $this->comp->names(array(
				'nation' => $calendar,
				'datum >= '.$this->db->quote(date('Y-m-d',time()-2*24*60*60)),	// all events starting 2 days ago or further in future
				'gruppen IS NOT NULL',
			),0,'datum ASC'),
			'nation' => $this->federation->get_competition_federations($comp['nation'],$allow_register_everyone ? null : $this->register_rights),
			'sex' => $this->genders,
			'state' => ranking_registration::$state_filters,
		);
		if ($comp && !isset($select_options['comp'][$comp['WetId']]))
		{
			$select_options['comp'][$comp['WetId']] = $comp['name'];
		}
		// check if a valid competition is selected
		if ($comp)
		{
			$readonlys['download_all'] = true;

			/*
			if ($nation)	// read prequalified athlets
			{
				$prequalified = $this->national_prequalified($comp,$nation);
				//_debug_array($prequalified);
			}
			//_debug_array($cat2col);
			if (!$this->registration_check($comp,$nation))	// user allowed to register that nation
			{
				if ($this->date_over($comp['deadline'] ? $comp['deadline'] : $comp['datum']))
				{
					$msg = lang('Registration for this competition is over!');
				}
				else
				{
					$msg = lang('You are not allowed to register for %1!',
						is_numeric($nation) && ($fed = $this->federation->read($nation)) ? $fed['verband'] : $nation);
				}
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
					list($ids) = $content['register'] ? each($content['register']) : each($content['delete']);
					list($cat,$id) = explode('/', $ids);
					$athlete = $this->athlete->read($id, '', $this->license_year,$comp['nation']);
				}
				if (!($cat = $this->cats->read($cat)) || !in_array($cat['rkey'],$comp['gruppen']) ||
					$cat['sex'] && $athlete['sex'] != $cat['sex'])
				{
					//_debug_array($athlete);
					//_debug_array($cat);
					//_debug_array($comp);
					$msg = lang('Permission denied !!!');
				}
				elseif($content['register'] && $athlete['license'] == 'n' && !$this->allow_no_license_registration($comp))
				{
					$msg = lang('This athlete has NO license!').' '.lang('Use regular registration to apply for a license first.');
				}
				elseif (!$this->registration_check($comp,$nation,$cat['GrpId']))
				{
					$msg = lang('Competition already has a result for this category!');
				}
				elseif($content['delete'])
				{
					$msg = $this->register($comp['WetId'], $cat['GrpId'], $athlete, ranking_registration::DELETED) ?
						lang('%1, %2 deleted for category %3',strtoupper($athlete['nachname']), $athlete['vorname'], $cat['name']) :
						lang('Error: registration');
				}
				elseif($athlete['license'] == 's')
				{
					$msg = lang('Athlete is suspended !!!');
				}
				else // register
				{
					try {
						$msg = $this->register($comp['WetId'], $cat['GrpId'], $athlete, ranking_registration::REGISTERED,
							isset($prequalified[$cat['GrpId']][$athlete['PerId']])) ?
							lang('%1, %2 registered for category %3',strtoupper($athlete['nachname']), $athlete['vorname'], $cat['name']) :
							lang('Error: registration');
					}
					catch(egw_exception_wrong_userinput $e)
					{
						$msg = lang('Error').': '.$e->getMessage().'!';
					}
					// remember athlete to check later for over quota
					if ($comp['no_complimentary'] && !isset($prequalified[$cat['GrpId']][$athlete['PerId']]))
					{
						$check_athlete_over_quota = $athlete['PerId'];
					}
				}
			}
			// generate a startlist is no longer used
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
			elseif ($content['download'] || $content['download_all'])
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
				// only return non-deleted athletes
				ranking_registration::PREFIX.ranking_registration::DELETED.' IS NULL',
			);
			if ($nation)	// filter by given nation/federation
			{
				if (!$comp['nation'] || $nation == $comp['nation'])	// int. competition
				{
					$where['nation'] = $nation;
				}
				elseif(is_numeric($nation))
				{
					$where[] = '(fed_parent='.(int)$nation.' OR acl.fed_id='.(int)$nation.')';
				}
				elseif ($comp['nation'] != $nation)		// foreign participants in a national competition
				{
					$where['nation'] = $nation;
				}
				else
				{
					$where[] = 'nation='.$this->db->quote($nation).' AND fed_parent IS NULL';
				}
			}
			//$starters =& $this->result->read($where,'',true,$comp['nation'] ? 'nation,acl_fed_id,fed_parent,acl.fed_id,GrpId,reg_nr' : 'nation,GrpId,reg_nr');
			$starters =& $this->registration->read($where,'',true,$comp['nation'] ? 'nation,acl_fed_id,fed_parent,acl.fed_id,GrpId' : 'nation,GrpId');

			// mail all participants
			$mail_allowed = $starters && $allow_register_everyone;
			$content['no_mail'] = !$mail_allowed;
			if($mail_allowed && $content['mail']['button'])
			{
				$msg = $this->mail($content['mail'], $starters);
				unset($content['mail']['button']);
			}
			if ($mail_allowed && !$content['mail'])
			{
				$content['mail'] = $this->mail_defaults();
			}

			$nat = '';
			$nat_starters = array();
			$prequal_lines = 0;
			// if nation/federation selected, show prequalified first
			if ($nation && $prequalified)
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
						if (!$tmpl->sitemgr)
						{
							$readonlys[$registered ? $delete_button : $register_button] = false;	// re-enable the button
						}
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
			$max_quota = 0;
			foreach((array)$starters as $starter)
			{
				// download button only if there's a startlist (platz==0 && pkt>64)
				$download = 'download['.$starter['GrpId'].']';
				if ($starter['GrpId'] && (!isset($readonlys[$download]) || $readonlys[$download] || $starter['platz']))
				{
					// outside SiteMgr we always offer the download
					$readonlys['download_all'] = $readonlys[$download] = $tmpl->sitemgr && !$starter['platz'] && $starter['pkt'] < 64;
				}
				// new nation and data for the previous nation ==> write that data
				$starter_nat_fed = !$comp['nation'] || $starter['nation'] != $comp['nation'] ||
					!$starter['fed_parent'] && !$starter['acl_fed_id'] ?	// only use nation, if no RGZ set!
					$starter['nation'] : ($starter['acl_fed_id'] ? $starter['acl_fed_id'] : $starter['fed_parent']);
				if ($nat != $starter_nat_fed)
				{
					ksort($nat_starters);	// due to $quota < $max_quota, the rows might not be sorted by number/key
					foreach($nat_starters as $i => $row)
					{
						if (is_numeric($nat) && ($fed = $this->federation->read($nat)))
						{
							$nat = $fed['fed_shortcut'] ? $fed['fed_shortcut'] : $fed['verband'];
						}
						$rows[] = array(
							'nation' => !$nation || $nat && $nat != $nation || !$nat && $i != $max_quota ? $nat :
								($i == $max_quota ? lang('Complimentary') : lang('Quota')),
						) + $row;
						$nat = '';
					}
					$nat_starters = array();
					$nat = $starter_nat_fed;
					$max_quota = $this->comp->max_quota($starter_nat_fed,$comp);
				}
				$quota = $this->comp->quota($starter_nat_fed,$starter['GrpId'],$comp);

				if ($nation && isset($prequalified[$starter['GrpId']][$starter['PerId']]))
				{
					continue;	// prequalified athlets are in an own block
				}
				// set a new column for an unknown/new rkey/cat
				if ($starter_nat_fed && !isset($cat2col[$starter['GrpId']]))
				{
					$cat2col[$starter['GrpId']] = $tmpl->num2chrs(count($cat2col));
				}
				$col = $cat2col[$starter['GrpId']];
				// find first free line to add that starter
				for ($i = 0; isset($nat_starters[$i][$col]) || $i >= $quota && $i < $max_quota; ++$i) {}
				// check if newly registered athlete is over quota AND we have no complimentary list
				if ($check_athlete_over_quota && $starter['PerId'] == $check_athlete_over_quota && $i >= $quota)
				{
					if ($this->is_admin || $this->is_judge($comp))
					{
						$msg .= '. '.lang('No complimentary list (over quota)').'!';
					}
					else
					{
						$this->register($comp['WetId'],$starter['GrpId'],$starter['PerId'],2);	// delete starter again
						$msg = lang('No complimentary list (over quota)').' quota='.(int)$quota.'!';
						continue;
					}
				}
				$delete_button = 'delete['.$starter['GrpId'].'/'.$starter['PerId'].']';
				$nat_starters[$i][$col] = $starter+array(
					'cn' => strtoupper($starter['nachname']).', '.$starter['vorname'],
					'prequal' => $starter['prequal'],
					'class' => $nation && $i >= $quota ? 'complimentary' : 'registered',
					'delete_button' => $delete_button,
				);
				if (!$tmpl->sitemgr) $readonlys[$delete_button] = !$this->acl_check_athlete($starter,EGW_ACL_REGISTER,$comp);
			}
			$cats = array();
			foreach((array)$cat2col as $cat => $col)
			{
				$cats[$col] = $this->cats->read(array('GrpId' => $cat));
			}*/
		}
		else
		{
			$comp = '';
		}
		if (!$comp || !$nation)		// no register-button
		{
			$readonlys['register'] = true;
		}
		$cont = $preserv = array(
			'nm'       => array_merge($state, array(
				'calendar' => $calendar,
				'comp'     => $comp ? $comp['WetId'] : null,
				'nation'   => $nation,
			)),
			'no_mail'  => !isset($_content['no_mail']) || $_content['no_mail'],
			'mail'     => $_content['mail'],
		);
		/*$cont += array(
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
				$cont['startlist'][$col] = array(
					'num_routes' => 'num_routes['.$cat['GrpId'].']',
					'max_compl'  => 'max_compl['.$cat['GrpId'].']',
					'button'     => 'startlist['.$cat['GrpId'].']',
					'download'   => 'download['.$cat['GrpId'].']',
				);
			}
		}
		// dont show startlist options, if no comp selected, in sitemgr, no starters, a nation selected or no rights to generate a startlist
		// disabling all old starlist options, as we have the resultservice now
		//if (!$comp || $tmpl->sitemgr || count($starters) <= 1 || $nation && $nation != $comp['nation'] || !$this->acl_check($comp['nation'],EGW_ACL_RESULT,$comp))
		{
			$cont['startlist'] =  false;
		}*/
		// save calendar, competition & nation between calls in the session
		//_debug_array($cont);
		$GLOBALS['egw_info']['flags']['app_header'] = lang('Registration').
			(!$nation || $nation == 'NULL' ? '' : (': '.
			(is_numeric($nation) && ($fed || ($fed = $this->federation->read($nation))) ? $fed['verband'] : $nation)));

		return $tmpl->exec('ranking.ranking_registration_ui.index', $cont, $select_options, $readonlys, $preserv);
	}

	/**
	 * Return actions for start-/result-lists
	 *
	 * @return array
	 */
	static function get_actions()
	{
		$actions =array(
			'mail' => array(
				'caption' => 'Mail',
				'icon' => 'mail/navbar',
				//'onExecute' => 'javaScript:app.ranking.action_measure',
                'disableClass' => 'th',
				'allowOnMultiple' => true,
			),
			'confirm' => array(
				'caption' => 'Confirm',
				'icon' => 'check',
				'onExecute' => 'javaScript:app.ranking.register_action',
                'disableClass' => 'th',
				'allowOnMultiple' => true,
			),
			'delete' => array(
				'caption' => 'Delete',
				'onExecute' => 'javaScript:app.ranking.register_action',
                'disableClass' => 'noDelete',
				'allowOnMultiple' => true,
			),
		);
		return $actions;
	}

	/**
	 * Send mail to all participants
	 *
	 * @param array $data
	 * @param array $starters
	 * @return string succes or error message
	 */
	function mail(array $data, array $starters)
	{
		foreach(array('from', 'subject', 'body', 'button') as $key)
		{
			if (empty($data[$key]))
			{
				return lang('Required information missing').': '.lang($key);
			}
		}
		list($button) = each($data['button']);

		$success = $no_email = $errors = 0;
		foreach($starters as $starter)
		{
			if (!($athlete = $this->athlete->read($starter['PerId'])))
			{
				$errors++;
				continues;
			}
			//if ($athlete['rkey'] != 'RB' || $success > 1) continue;
			switch($button)
			{
				case 'all':
					break;
				case 'recent':
					if ($GLOBALS['egw']->db->from_timestamp($athlete['recover_pw_time']) > time()-7*86400) continue 2;
					// fall through
				case 'no_password':
					if (!empty($athlete['password'])) continue 2;
					break;
			}
			if (!preg_match('/'.url_widget::EMAIL_PREG.'/i', $athlete['email']))
			{
				$no_email++;
				continue;
			}
			static $selfservice = null;
			if (!isset($selfservice)) $selfservice = new ranking_selfservice();

			try {
				$selfservice->password_reset_mail($athlete, $data['subject'], $data['body'], $data['from']);
				$success++;
			}
			catch (Exception $e)
			{
				$errors++;
			}
		}
		unset($data['button']);
		// store current values, if different from old defaults
		if ($data != $this->mail_defaults())
		{
			$preferences = $GLOBALS['egw']->preferences;
			$preferences->add('ranking', 'mail_defaults', $data);
			$preferences->save_repository();
		}
		return lang('Mail to %1 participants send, %2 had no email-address, %3 failed.',
			$success, $no_email, $errors.($e ? ' ('.$e->getMessage().')' : ''));
	}

	/**
	 * Preset default mail content
	 *
	 * @return array values for keys 'from', 'subject' and 'body'
	 */
	function mail_defaults()
	{
		$data = $GLOBALS['egw_info']['user']['preferences']['ranking']['mail_defaults'];

		if (!is_array($data))
		{
			$data = array(
				'from' => $GLOBALS['egw_info']['user']['account_fullname'].' <'.$GLOBALS['egw_info']['user']['account_email'].'>',
			);
			list($data['subject'], $data['body']) = preg_split("/\r?\n/",
				file_get_contents(EGW_SERVER_ROOT.'/ranking/doc/reset-password-mail.txt'), 2);
		}
		return $data;
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
	 *
	function startlist()
	{
		return $this->lists(null,'','startlist');
	}*/

	/**
	 * Show/download the startlist or result of a competition for one or all categories
	 *
	 * @param array $content
	 * @param string $msg =''
	 * @param string $show ='' 'startlist','result' or '' for whatever is availible
	 *
	 * @return string
	 */
	function lists($content=null,$msg='',$show='')
	{
		$tmpl = new etemplate('ranking.register.lists');

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
				$content = egw_cache::getSession('ranking', 'registration');
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
				$starters =& $this->result->read($keys,'',true,$order);
			}
			/* if we have a startlist (not just starters) sort by startnumber
			elseif ($show == 'startlist' || !$show && $this->result->has_startlist($keys))
			{
				$keys[] = 'platz=0 AND pkt > 64';
				$show = 'startlist';
				$order = 'GrpId,pkt,nachname,vorname';
				$starters =& $this->result->read($keys,'',true,$order);
			}*/
			else	// sort by nation
			{
				$keys['platz'] = 0;
				$order = 'GrpId,nation,pkt,nachname,vorname';
				$starters =& $this->registration->read($keys,'',true,$order);
			}

			if ($content['download'] && $starters && count($starters))
			{
				html::content_header($comp['rkey'].'.csv','text/comma-separated-values');
				$name2csv = array(
					'WetId'    => 'comp',
					'GrpId'    => 'cat',
					'PerId'    => 'athlete',
					'platz'    => 'place',
					'category',
					$show == 'startlist' ? 'startnumber' : 'points',
					'nachname' => 'lastname',
					'vorname'  => 'firstname',
					'email'    => 'email',
					'nation'   => 'nation',
					'geb_date' => 'birthdate',
					'ranking',
					'ranking-points',
				);
				echo implode(';',$name2csv)."\n";
				$charset = translation::charset();
				$c['GrpId'] = 0;
				foreach($starters as $athlete)
				{
					if ($c['GrpId'] != $athlete['GrpId'])
					{
						$c = $this->cats->read($athlete['GrpId']);

						$stand = $comp['datum'];
						$nul = $test = $ranking = null;
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
					$csv_charset = $GLOBALS['egw_info']['user']['preferences']['common']['csv_charset'];
					if (empty($csv_charset)) $csv_charset = 'iso-8859-1';
					echo translation::convert(implode(';',$values), $charset, $csv_charset)."\n";
				}
				common::egw_exit();
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
		$cont = $preserv = array(
			'calendar' => $calendar,
			'comp'     => $comp['WetId'],
			'cat'      => $cat ? $cat['GrpId'] : '',
		);
		$cont += array(
			'rows'     => $rows,
			'msg'      => $msg,
			'result'   => $athlete['platz'] > 0,
			'no_upload' => !$comp || !$cat || !$this->acl_check($comp['nation'],EGW_ACL_RESULT,$comp),
		);
		// save calendar, competition & cat between calls in the session
		egw_cache::setSession('ranking', 'registration', $preserv);
		$this->set_ui_state($preserv['calendar'],$preserv['comp'],$preserv['cat']);

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
		return $tmpl->exec('ranking.ranking_registration_ui.lists',$cont,$select_options,$readonlys,$preserv);
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
