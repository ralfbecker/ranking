<?php
/**
 * EGroupware digital ROCK Rankings - registration UI
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2006-19 by Ralf Becker <RalfBecker@digitalrock.de>
 */

namespace EGroupware\Ranking\Registration;

use EGroupware\Api;
use EGroupware\Ranking\Base;
use EGroupware\Ranking\Selfservice;
use EGroupware\Ranking\Registration;

class Ui extends Base
{
	/**
	 * functions callable via menuaction
	 *
	 * @var array
	 */
	var $public_functions = array(
		'result'    => true,
		'index'     => true,
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

		$comp_rights = false;
		if ($query['comp'] && ($comp = $this->comp->read($query['comp'])))
		{
			$comp_rights = $this->acl_check_comp($comp);
		}
		if (!$comp || ($comp['nation']?$comp['nation']:'NULL') != $query['calendar'])
		{
			$query['comp'] = '';
			$comp = null;
		}
		$query['col_filter']['WetId'] = $query['comp'];
		$query['col_filter']['nation'] = $query['nation'];

		Api\Cache::setSession('ranking', 'registration', $state=array(
			'calendar' => $query['calendar'],
			'comp'     => $query['comp'],
			'nation'   => $query['nation'],
			'col_filter' => $query['col_filter'],
		));
		//error_log(__METHOD__."() Cache::setSession('ranking', 'registration', ".array2string($state).")");
		$this->set_ui_state($query['calendar'], $query['comp'], $query['col_filter']['GrpId']);

		if ($query['order'] == 'reg_fed')
		{
			$query['order'] = $default_order = ($query['calendar'] == 'NULL' ? 'nation' : 'acl_fed_id,fed_parent').',GrpId,reg_id';
		}
		$total = $this->registration->get_rows($query, $rows, $readonlys, true);
		if ($query['order'] == $default_order) $query['order'] = 'reg_fed';

		// limit non-judge to feds user has registration rights
		$allow_register_everyone = $this->is_admin || $this->is_judge($comp,true) ||
			// national edit rights allow to register foreign athlets for national competition
			in_array($comp['nation'],$this->register_rights) && in_array($comp['nation'],$this->edit_rights) ||
			$comp && $this->acl_check_comp($comp);	// allow people with edit rights to a competition to register everyone
		//error_log(__METHOD__."() allow_register_everyone=".array2string($allow_register_everyone).', is_judge='.array2string($this->is_judge($comp)));

		$sel_options = array(
			'comp'     => $this->comp->names(array(
				'nation' => $query['calendar'],
				'datum >= '.$this->db->quote(date('Y-m-d',time()-2*24*60*60)),	// all events starting 2 days ago or further in future
				'gruppen IS NOT NULL',
			), 3, 'datum ASC'),
			'nation' => !$comp ? array() : $this->federation->get_competition_federations($query['calendar'],
				$allow_register_everyone ? null : $this->register_rights),
			'GrpId' => $comp && $comp['gruppen'] ? $this->cats->names(array('rkey' => $comp['gruppen']),0) : array(lang('No categories defined!')),
			'acl_fed_id' => $query['calendar'] == 'SUI' ? $this->federation->federations('SUI', true, 'fed_shortcut') : array(),
			'fed_parent' => $query['calendar'] == 'GER' ? $this->federation->federations('GER', true, 'fed_shortcut') : array(),
		);
		if ($comp && !isset($sel_options['comp'][$comp['WetId']]))
		{
			$sel_options['comp'][$comp['WetId']] = Api\DateTime::to($comp['datum'], true).': '.$comp['name'];
		}

		$matches = null;
		$last_reg_fed = $last_cat = null;
		$reg_fed = !$comp || !$comp['nation'] ? 'nation' : ($comp['nation'] == 'SUI' ? 'acl_fed_id' : 'fed_parent');
		foreach($rows as &$row)
		{
			$row['id'] = 'reg::'.$row['reg_id'];

			$row['reg_fed'] = empty($row[$reg_fed]) || $reg_fed == 'nation' ? $row['nation'] :
				$sel_options[$reg_fed][$row[$reg_fed]];

			if ($query['order'] == 'reg_fed')
			{
				// first row in set is not necessary first row overall ($query[start])
				if (!$last_reg_fed && $query['start'])	// not first row
				{
					$last_reg_fed = $row['reg_fed'];
					$last_cat = $row['GrpId'];
				}
				if ($last_reg_fed !== $row['reg_fed'])
				{
					$last_reg_fed = $row['reg_fed'];
					$row['class'] .= ' startRegFed';
					$last_cat = null;
				}
				else
				{
					$row['reg_fed'] = '';
				}
				if ($last_cat !== $row['GrpId'])
				{
					$last_cat = $row['GrpId'];
				}
				else
				{
					$row['class'] .= ' hideCat';
				}
			}
			// show only Sektion for national competitions and remove it for international
			if ($query['calendar'] && $query['calendar'] != 'NULL')
			{
				$row['verband'] = preg_replace_callback('/^(Deutscher Alpenverein|Schweizer Alpen[ -]{1}Club) /',function($matches)
				{
					return $matches[1] == 'Deutscher Alpenverein' ? 'DAV ' : 'SAC ';
				},$row['verband']);
			}
			elseif (preg_match('/^(Deutscher Alpenverein|Schweizer Alpen[ -]{1}Club) /', $row['verband'], $matches))
			{
				$row['verband'] = $matches[1];
			}

			$replace = false;
			if ($comp_rights || $this->registration_check($comp, $row['nation'], null, $replace) ||
				$comp['nation'] && ($this->registration_check($comp, $row['fed_parent'], null, $replace) ||
					$this->registration_check($comp, $row['acl_fed_id'], null, $replace)))
			{
				if ($row['state'] == Registration::PREQUALIFIED)
				{
					$row['class'] .= ' allowRegister';
					if ($comp_rights) $row['class'] .= ' allowDelete';
				}
				elseif ($row['state'] == Registration::REGISTERED || $row['state'] == Registration::CONFIRMED)
				{
					$row['class'] .= ' allowDelete allowReplace';
				}
			}
			// if we have just replace rights
			elseif($replace && ($row['state'] == Registration::REGISTERED || $row['state'] == Registration::CONFIRMED))
			{
				$row['class'] .= ' allowReplace';
			}
			if ($comp_rights && $row['state'] == Registration::REGISTERED)
			{
				$row['class'] .= ' allowConfirm';
			}
			//error_log(__METHOD__."() ".array2string($row));
		}
		$rows['sel_options'] = $sel_options;

		$query['actions'] = self::get_actions($comp_rights || $this->is_judge($comp, true));

		// let client-side know which rights current user has for selected competition
		$replace = false;
		Api\Json\Response::get()->call('app.ranking.competition_rights', (int)$comp['WetId'], 0,
			($comp_rights ? self::ACL_EDIT : 0) |
			($comp_rights || $this->is_judge($comp, true) || $this->registration_check($comp, $query['nation'], null, $replace) ||
			// if no nation/federation filter is given, check if current user has registration rights for anything
			!$query['nation'] && ($this->register_rights || $this->federation->get_grants(null, self::ACL_REGISTER)) ? self::ACL_REGISTER : 0) |
			($this->is_judge($comp, true) ? 512 : 0) |
			($replace ? 8192 : 0));	// fake replace only rights

		$GLOBALS['egw_info']['flags']['app_header'] = lang('Registration').($comp ? ' - '.$comp['name'] : '');

		return $total;
	}

	/**
	 * Search for athletes to register
	 */
	public function ajax_search()
	{
		try {
			$state = Api\Cache::getSession('ranking', 'registration');

			// show already registered first, then prequalified, then rest of category, then matches with error
			$results = array(
				lang('Already registered') => array(),
				lang('Prequalified') => array(),
				lang('License confirmed or applied for') => array(),
				lang('This athlete has NO license!') => array(),
				'' => array(),
			);

			// compile array of prequalified
			$prequalified = $registered = array();
			if ($state['comp'] && (int)$_REQUEST['GrpId'] > 0)
			{
				// add registered and all prequalified stored in registration
				foreach($this->registration->read(array(
						'WetId' => $state['comp'],
						'GrpId' => $_REQUEST['GrpId'],
						Registration::PREFIX.Registration::DELETED.' IS NULL',
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
				//error_log(__METHOD__."(".array2string($_REQUEST).") registered=".count($registered).", prequalified=".count($prequalified));
			}
			$options = array(
				'GrpId' => (int)$_REQUEST['GrpId'],
				'WetId' => $state['comp'],
				// limit athletes to selected nation/federation
				is_numeric($_REQUEST['nation']) ? 'fed_parent' : 'nation' => $_REQUEST['nation'],
				'sex' => $_REQUEST['sex'],
				'num_rows' => 100,
			);
			if (($comp = $this->comp->read($state['comp'])))
			{
				$options += array(
					'license_nation' => $comp['nation'],
					'license_year' => (int)$comp['datum'],
				);
			}
			$links = Api\Link::query('ranking', $_REQUEST['query'], $options);
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
				elseif ($label['license'] == 'a' || $label['license'] == 'c')
				{
					$sort = lang('License confirmed or applied for');
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
		Api\Json\Request::isJSONRequest(false);

		header('Content-Type: application/json; charset=utf-8');
		echo json_encode(array_merge(...array_values($results)));
		exit;
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
			'delete'     => Registration::DELETED,
			'register'   => Registration::REGISTERED,
			'prequalify' => Registration::PREQUALIFIED,
			'confirm'    => Registration::CONFIRMED,
			'replace'    => Registration::REGISTERED,
		);

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
		$msg = '';
		foreach((array)$params['PerId'] as $id)
		{
			if (!($athlete = $this->athlete->read($id, '', (int)$comp['datum'], $comp['nation'])))
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
				if ($this->register($comp, $cat, $athlete, $mode2ts[$params['mode']], $msg, $params['reason'], $params['replace']))
				{
					$registered++;
					if ($msg) break;	// stop, if over quota warning for admins/jury
				}
				else
				{
					$error = lang('Error: registration');
					break;
				}
				if ($params['replace']) break;	// replace can only be one
			}
			catch(Exception $e) {
				$error = $e->getMessage();
				break;
			}
		}
		$msg_type = $msg ? 'info' : 'success';
		$msg = ($registered == 1 && $athlete ? $this->athlete->link_title($athlete) : $registered).
			' '.lang($mode2ts[$params['mode']]).'.'.($msg ? "\n\n".$msg : '');

		if ($error)
		{
			if ($athlete)
			{
				$error = $this->athlete->link_title($athlete)."\n\n".$error.
					($registered && $msg ? "\n\n".$msg : '');

				// tell client-side how many successful registered and therefore to remove from selection
				if ($registered) Api\Json\Response::get()->data(array(
					'registered' => $registered,
				));
			}
			Api\Json\Response::get()->call('egw.message', $error, 'error');
		}
		else
		{
			if ($registered) Api\Json\Response::get()->call('egw.refresh', $msg, 'ranking',
				null, null, null, null, null, $msg_type);

			// tell client-side how many successful registered and therefore to remove from selection,
			// plus evtl. question and concerned athlete
			Api\Json\Response::get()->data(array(
				'registered' => $registered,
				'replace'   => $params['replace'],
			)+(!isset($question) || $question === true ? array() : array(
				'question'  => $question,
				'PerId'     => $athlete['PerId'],
				'GrpId'     => $params['GrpId'],
				'WetId'     => $params['WetId'],
				'mode'      => $params['mode'],
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
		$tmpl = new Api\Etemplate('ranking.registration');

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
				$state = (array)Api\Cache::getSession('ranking', 'registration');
			}
			$state += array(
				'get_rows'       =>	'ranking.'.self::class.'.get_rows',
				'no_cat'         => true,
				'no_filter'      => true,
				'no_filter2'     => true,
				'order'          => 'reg_fed',
				'sort'           =>	'ASC',// IO direction of the sort: 'ASC' or 'DESC'
				'dataStorePrefix' => 'ranking_regi',
				'row_id'         => 'id',
				'actions'        => self::get_actions(),
				'default_cols'   => '!ort,nation,acl_fed_id,verband',
			);
		}
		elseif($_content['nm']['download'])
		{
			// post request would refresh whole framework, if no competition selected or no registration available
			// send a "204 No Content" to tell browser to do nothing
			if (!$_content['nm']['comp'] ||
				!$this->registration->has_registration(array('WetId' => $_content['nm']['comp'])))
			{
				header('HTTP/1.1 204 No Content');
				exit;
			}
			return $this->result($_content['nm'], '', 'registration');
		}
		else
		{
			$state = $_content['nm'];
		}
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
			$tmpl->disableElement('calendar');
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
			$calendar = key($this->ranking_nations);
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
					if ($r & self::ACL_REGISTER)
					{
						$nation = $n;
						break;
					}
				}
			}
		}

		$select_options = array(
			'calendar' => $this->ranking_nations,
			'comp'     => $this->comp->names(array(
				'nation' => $calendar,
				'datum >= '.$this->db->quote(date('Y-m-d',time()-2*24*60*60)),	// all events starting 2 days ago or further in future
				'gruppen IS NOT NULL',
			), 3, 'datum ASC'),
			'sex' => $this->genders,
			'state' => Registration::$state_filters,
		);
		$cont = $preserv = array(
			'nm'       => array_merge($state, array(
				'calendar' => $calendar,
				'comp'     => $comp ? $comp['WetId'] : null,
				'nation'   => $nation,
			)),
			'mail'     => $this->mail_defaults(),
		);
		//_debug_array($cont);
		return $tmpl->exec('ranking.'.self::class.'.index', $cont, $select_options, $readonlys, $preserv);
	}

	/**
	 * Return actions for start-/result-lists
	 *
	 * @param $allow_mail =false
	 * @return array
	 */
	static function get_actions($allow_mail=false)
	{
		$group = 0;
		$actions =array(
			'register' => array(
				'caption' => 'Register',
				'icon' => 'check',
				'onExecute' => 'javaScript:app.ranking.register_action',
                'enableClass' => 'allowRegister',
				'allowOnMultiple' => false,
				'group' => $group,
				'default' => true,
			),
			'replace' => array(
				'caption' => 'Replace',
				'icon' => 'edit',
				'onExecute' => 'javaScript:app.ranking.replace_action',
                'enableClass' => 'allowReplace',
				'allowOnMultiple' => false,
				'group' => $group,
			),
			'confirm' => array(
				'caption' => 'Confirm',
				'icon' => 'check',
				'onExecute' => 'javaScript:app.ranking.register_action',
                'enableClass' => 'allowConfirm',
				'allowOnMultiple' => false,
				'group' => $group,
			),
			'mail' => array(
				'caption' => 'Mail',
				'icon' => 'mail/navbar',
				//'onExecute' => 'javaScript:app.ranking.action_measure',
                'enabled' => $allow_mail,
				'allowOnMultiple' => true,
				'group' => $group=5,	// 5: behind clipboard
			),
			'delete' => array(
				'caption' => 'Delete',
				'onExecute' => 'javaScript:app.ranking.register_action',
                'enableClass' => 'allowDelete',
				'allowOnMultiple' => false,
				'group' => ++$group,
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
	function ajax_mail(array $data, $button, array $selection, $filters)
	{
		error_log(__METHOD__."(".array2string(func_get_args()));

		foreach(array('from', 'subject', 'body') as $key)
		{
			if (empty($data[$key]))
			{
				Api\Json\Response::get()->call('egw.message', lang('Required information missing').': '.lang($key), 'error');
				return;
			}
		}
		$filter = array(
			'WetId' => $filters['comp'],
			'state' => Registration::REGISTERED,
		);
		if ($button == 'selected')
		{
			if ($selection['all'])
			{
				$filter = array_merge($filter, $filters['col_filter'], array(
					'GrpId' => $filters['col_filter']['GrpId'],
					(is_numeric($filters['nation']) ? 'fed_parent' : 'nation') => $filters['nation'] ? $filters['nation'] : null,
				));
			}
			else
			{
				array_walk($selection['ids'], function(&$_id)
				{
					$_id = (int)substr($_id, 14);	// remove "ranking::reg::" prefix
				});
				$filter = array(
					'reg_id' => $selection['ids'],
					'state' => 'all',
				);
			}
		}
		$starters = $this->registration->search(array(), true, 'nachname',
			'recover_pw_time,password,email', '*', false, 'AND', false, $filter);

		$success = $no_email = $errors = 0;
		foreach($starters as $athlete)
		{
			switch($button)
			{
				case 'selected':
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
			if (!isset($selfservice)) $selfservice = new Selfservice();

			try {
				$selfservice->passwordResetMail($athlete, $data['subject'], $data['body'], $data['from']);
				$success++;
			}
			catch (Exception $e)
			{
				$errors++;
			}
		}
		// store current values, if different from old defaults
		if ($data != $this->mail_defaults())
		{
			$preferences = $GLOBALS['egw']->preferences;
			$preferences->add('ranking', 'mail_defaults', $data);
			$preferences->save_repository();
		}
		Api\Json\Response::get()->call('egw.message', lang('Mail to %1 participants send, %2 had no email-address, %3 failed.',
			$success, $no_email, $errors.($e ? ' ('.$e->getMessage().')' : '')), $errors ? 'error' : 'info');
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
	 * Show/download result of a competition for one or all categories
	 *
	 * @param array $content
	 * @param string $msg =''
	 * @param string $show ='' 'startlist','result' or '' for whatever is availible
	 *
	 * @return string
	 */
	function result($content=null,$msg='',$show='result')
	{
		$tmpl = new Api\Etemplate('ranking.register.lists');

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
				$content = Api\Cache::getSession('ranking', 'registration');
			}
		}
		elseif ($content['old_calendar'] && $content['old_calendar'] != $content['calendar'])
		{
			unset($content['comp']);
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
			$tmpl->disableElement('calendar');
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
			$calendar = key($this->ranking_nations);
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
			if ($show == 'result')
			{
				$show = 'result';
				$keys[] = 'platz > 0';
				$order = 'GrpId,platz,nachname,vorname';
				$starters = $this->result->read($keys,'',true,$order);
			}
			else	// sort by nation
			{
				$order = 'GrpId,nation,reg_id,nachname,vorname';
				$keys['state'] = Registration::REGISTERED;
				$starters = $this->registration->read($keys,'+plz,strasse,tel,mobil',true,$order);
			}

			if ($content['download'] && $starters && count($starters))
			{
				Api\Header\Content::type($comp['rkey'].'.csv','text/comma-separated-values');
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
				if ($show != 'result')
				{
					$name2csv += [
						'ort' => 'city',
						'plz' => 'postcode',
						'strasse' => 'street',
						'tel' => 'telephone',
						'mobil' => 'mobile',
						'reg_prequal_reason' => 'prequalified',
						'reg_registered' => 'registered',
						'reg_confirmed'  => 'confirmed',
						'reg_deleted'    => 'deleted',
						'state_changed'  => 'changed',
					];
				}
				echo implode(';',$name2csv)."\n";
				$charset = Api\Translation::charset();
				$c['GrpId'] = 0;
				foreach($starters as $athlete)
				{
					if ($c['GrpId'] != $athlete['GrpId'])
					{
						$c = $this->cats->read($athlete['GrpId']);

						$stand = $comp['datum'];
						$nul = $test = $ranking = null;
						try {
							$this->ranking($c,$stand,$nul,$test,$ranking,$nul,$nul,$nul);
						} catch(Exception $e) {
							_egw_log_exception($e);
						}
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
							case 'changed':
								$val = trim($athlete[$name]);
								break;
							case 'ranking-points':
								$val = isset($ranking[$athlete['PerId']]) ? sprintf('%1.2f',$ranking[$athlete['PerId']]['pkt']) : '';
								break;
							default:
								$val = $athlete[$name];
						}
						if (strchr($val,';') !== false || strchr($val, "\n") !== false)
						{
							$val = '"'.str_replace('"','',$val).'"';
						}
						$values[$csv] = $val;
					}
					// convert by default to iso-8859-1, as this seems to be the default of excel
					$csv_charset = $GLOBALS['egw_info']['user']['preferences']['common']['csv_charset'];
					if (empty($csv_charset)) $csv_charset = 'iso-8859-1';
					echo Api\Translation::convert(implode(';',$values), $charset, $csv_charset)."\n";
				}
				exit;
			}
			if ($content['upload'] && is_uploaded_file($content['file']['tmp_name']))
			{
				$msg = $this->upload(array('WetId'=>$content['comp'],'GrpId'=>$content['cat']),$content['file']['tmp_name']);
				// re-read the starters
				$starters = $this->result->read($keys,'',true,$order);
			}
			if (!$show || !$starters || !count($starters))
			{
				$msg = lang('Competition has not yet a result');
				$readonlys['download'] = true;
			}
			else
			{
				$c = $cat;
				$rows = array(false);
				foreach($starters as $athlete)
				{
					if (empty($c) || $athlete['GrpId'] != $c['GrpId'])
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
		$preserv['old_calendar'] = $cont['calendar'];
		$cont += array(
			'rows'     => $rows,
			'msg'      => $msg,
			'result'   => $athlete['platz'] > 0,
			'no_upload' => !$comp || !$cat || !$this->acl_check($comp['nation'],self::ACL_RESULT,$comp),
		);
		// save calendar, competition & cat between calls in the session
		Api\Cache::setSession('ranking', 'registration', $preserv);
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
		return $tmpl->exec('ranking.'.self::class.'.result',$cont,$select_options,$readonlys,$preserv);
	}

	/**
	 * Upload a result as csv file
	 *
	 * @param array $keys WetId, GrpId, route_order
	 * @param string $file uploaded file
	 * @param string|int error message or number of lines imported
	 */
	function upload($keys,$file)
	{
		if (!$keys || !$keys['WetId'] || !$keys['GrpId'] ||
			!($comp = $this->comp->read($keys['WetId'])) ||
			!$this->acl_check($comp['nation'],self::ACL_RESULT,$comp))
		{
			return lang('Permission denied !!!');
		}
		$csv = $this->parse_csv($keys,$file,true);

		if (!is_array($csv)) return $csv;

		return $this->import_ranking($keys,$csv);
	}
}
