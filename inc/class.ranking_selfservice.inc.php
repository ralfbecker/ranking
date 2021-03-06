<?php
/**
 * EGroupware digital ROCK Rankings - athletes selfservie: profile, registration
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2012-18 by Ralf Becker <RalfBecker@digitalrock.de>
 */

use EGroupware\Api;
use EGroupware\Api\Framework;
use EGroupware\Api\Egw;

/**
 * athletes selfservie: profile, registration
 */
class ranking_selfservice extends ranking_bo
{
	/**
	 * @var array $public_functions functions callable via menuaction
	 */
	var $public_functions = array(
		'index' => true,
	);

	/**
	 * Athlete selfservie: edit profile, register for competitions
	 *
	 * @param int $PerId
	 * @param string $_action 'profile'
	 */
	function selfservice($PerId, $_action)
	{
		static $nation2lang = array(
			'AUT' => 'de',
			'GER' => 'de',
			'SUI' => 'de',
		);
		Framework::includeJS('.', 'selfservice', 'ranking');
		Framework::includeCSS('ranking', 'selfservice', false);
		Framework::$navbar_done = true;	// do NOT display navbar
		$_GET['cd'] = 'no';	// suppress framework
		if (substr($_GET['action'], 0, 10) != 'scorecard-') echo $GLOBALS['egw']->framework->header();

		$athlete = array();
		unset($this->athlete->acl2clear[ranking_athlete::ACL_DENY_EMAIL]);	// otherwise anon user never get's email returned!
		unset($this->athlete->acl2clear[ranking_athlete::ACL_DENY_PROFILE]);	// same is true for a fully denied profile
		if (($PerId || ($PerId = $this->is_selfservice())) && !($athlete = $this->athlete->read($PerId)))
		{
			throw new Api\Exception\WrongUserinput("Athlete NOT found!");
		}
		list($action,$action_id) = explode('-', $_action, 2);

		echo '<div id="selfservice">';

		if ($athlete)
		{
			echo "<h1 id='action-$action'>$athlete[vorname] $athlete[nachname] ($athlete[nation])</h1>\n";
		}
		if ((!$athlete || !$this->acl_check_athlete($athlete) && !$this->is_selfservice() == $PerId) &&
			!($PerId = $this->selfservice_auth($athlete, $action)))
		{
			$this->show_footer($nation2lang[$athlete['nation']]);
			return;
		}
		$lang = $PerId && isset($nation2lang[$athlete['nation']]) ? $nation2lang[$athlete['nation']] : 'en';
		if (Api\Translation::$userlang !== $lang)
		{
			Api\Translation::$userlang = $lang;
			Api\Translation::init();
		}
		// check if athlete contented to store his data, if not show consent screen
		if ($action !== 'logout' && (empty($athlete['consent_time']) || empty($athlete['consent_ip'])))
		{
			$this->consent_data_storage($athlete, $lang);
		}
		switch((string)$action)
		{
			case 'profile':
				Egw::redirect_link('/index.php',array(
					'menuaction' => 'ranking.ranking_athlete_ui.edit',
					'PerId' => $PerId,
					'cd' => 'no',
				));
				break;

			case '':
			case 'register':
				$this->selfservice_register($athlete, $action_id);
				break;

			case 'scorecard':
				$this->selfservice_scorecard($athlete, $action_id);
				break;

			case 'logout':
				$this->is_selfservice(0);
				$athlete = array();
				// fall through
			case 'recovery':
			case 'password':
			case 'set':
				$this->selfservice_auth($athlete, $action);
				break;

			default:
				throw new Api\Exception\WrongParameter("Unknown action '$action'!");
		}
		$this->show_footer($lang);
	}

	/**
	 * Ask athlete to consent to data storage
	 *
	 * This will NOT return, unless athlete consents!
	 *
	 * @param array $athlete
	 * @param string $lang ='en' 'de' for DACH
	 */
	private function consent_data_storage(array $athlete, $lang='en')
	{
		if (empty($_POST['consent']))
		{
			if (!file_exists($file=EGW_SERVER_ROOT.'/ranking/templates/default/consent_data_storage.'.$lang.'.html'))
			{
				$file = EGW_SERVER_ROOT.'/ranking/templates/default/consent_data_storage.html';
			}
			if (!($content = file_get_contents($file)))
			{
				$content = "<p><label>".Api\Html::checkbox('consent')." I agree to store my personal data.</label></p>";
			}
			// make athlete data available as replacements
			$replacements = array(
				'$$host$$' => $_SERVER['HTTP_HOST'],
				'$$date$$' => date('Y-m-d H:i:s'),
				'$$ip$$' => Api\Session::getuser_ip(),
			);
			foreach($athlete as $name => $value)
			{
				if (in_array($name, array('password'))) continue;
				if (is_array($value)) $value = implode(', ', $value);
				$replacements['$$'.$name.'$$'] = htmlspecialchars($value);
			}
			echo Api\Html::form(strtr($content, $replacements).
				Api\Html::submit_button('submit', 'Save', ''),
				array(), '/ranking/athlete.php', array('PerId' => $athlete['PerId']),
				'',	'style="display: contents"')."\n";

			echo html::form_1button('logout', lang('Logout'), '',
					'/ranking/athlete.php', array('action' => 'logout'));
			$this->show_footer($lang);
			exit;
		}
		// store athlete consent time and IP
		$this->athlete->update(array(
			'consent_time' => $this->athlete->now,
			'consent_ip' => Api\Session::getuser_ip(),
		));
	}

	/**
	 * Zeige einen Footer an
	 *
	 * @param string $lang ='en'
	 */
	private function show_footer($lang='en')
	{
		if (!file_exists($file=EGW_SERVER_ROOT.'/ranking/templates/default/selfservice_footer.'.$lang.'.html'))
		{
			$file = EGW_SERVER_ROOT.'/ranking/templates/default/selfservice_footer.html';
		}
		if (!($content = file_get_contents($file)))
		{
			$content = "<hr>\n".
				"| $\$host$$ is a service of EGroupware GmbH\n".
				"| <a href='https://www.digitalrock.de/kontakt.php' target='_blank'>contact / Impressum</a>\n".
				"| <a href='https://www.digitalrock.de/kontakt.php?privacy-policy' target='_blank'>privacy / Datenschutz</a> |\n";
		}
		$replacements = array(
			'$$host$$' => $_SERVER['HTTP_HOST'],
			'$$date$$' => date('Y-m-d H:i:s'),
			'$$ip$$' => Api\Session::getuser_ip(),
		);
		echo strtr($content, $replacements);
	}

	/**
	 * Selfservice scorecard
	 *
	 * @param array $athlete
	 * @param string $action_id WetId-GrpId-route_order
	 */
	private function selfservice_scorecard(array $athlete, $action_id)
	{
		$this->profile_logout_buttons($athlete);

		list($WetId, $GrpId, $route_order) = explode('-', $action_id);

		//egw::redirect_link('/index.php',
		$_GET = array(
			'menuaction' => 'ranking.ranking_result_ui.index',
			'comp' => $WetId,
			'cat' => $GrpId,
			'route' => $route_order,
			'athlete' => $athlete['PerId'],
			'show_result' => 4,
		);
		$result_ui = new ranking_result_ui();
		$result_ui->index(null, '', '', 2);
	}

	/**
	 * Selfservice for an already authorized athlete: either register for given WetId or show an index of possible actions
	 *
	 * @param array $athlete
	 * @param int $WetId =0
	 */
	private function selfservice_register(array $athlete, $WetId=0)
	{
		//echo "<p>".__METHOD__."(array(PerId=>$athlete[PerId],nachname='$athlete[nachname]',vorname=$athlete[vorname]',...), $WetId)</p>\n";
		if ($WetId)
		{
			if (!($comp = $this->comp->read($WetId)))
			{
				throw Api\Exception\WrongParameter("Unknown competition ID $WetId!");
			}
			echo "<p><b>$comp[name]</b></p>\n<p>".$this->comp->datespan()."</p>\n";

			if (!$comp['selfregister'])
			{
				die("<p class='error'>".lang('Competition does NOT allow self-registration!')."</p>\n");
			}
			if (!$this->comp->open_comp_match($athlete))
			{
				die("<p class='error'>".lang('Competition is NOT open to your federation!')."</p>\n");
			}
			if ($this->date_over($comp['deadline'] ? $comp['deadline'] : $comp['datum']))
			{
				die("<p class='error'>".lang('Registration for this competition is over!')."</p>\n");
			}
			if ($athlete['license'] == 's')
			{
				die("<p class='error'>".lang('Athlete is suspended !!!')."</p>\n");
			}
			$error = false;
			// check if competion requires a license
			if (empty($comp['no_license']))
			{
				if (!($athlete = $this->athlete->read($athlete['PerId'], '', (int)$comp['datum'], $comp['nation'])) ||
					$athlete['license'] == 'n')
				{
					echo "<p class='error'>".lang('This athlete has NO license!').' '.
						($comp['nation'] === 'SUI' ? '<a href="http://www.sac-cas.ch/wettkampfsport/swiss-climbing/baechli-swiss-climbing-cup/wettkampflizenz.html" target="_blank">'.
							'Informationen und Beantragung der Lizenz.'.'</a>' :
							lang('Please contact your federation (%1).', $this->federation->get_contacts($athlete))).
						"</p>\n";
					$error = true;
				}
			}
			// check available Api\Categories (matching sex and evtl. agegroup) and if athlete is already registered
			if (!($cats = $this->matching_cats($comp, $athlete)))
			{
				echo "<p class='error'>".lang('Competition has no categories you are allowed to register for!')."</p>\n";
				$error = true;
			}
			asort($cats);

			$registered = $this->registration->read(array(
				'WetId' => $WetId,
				'PerId' => $athlete['PerId'],
			));//,'',true,$comp['nation'] ? 'nation,acl_fed_id,fed_parent,acl.fed_id,GrpId,reg_nr' : 'nation,GrpId,reg_nr');
			if (!$registered) $registered = array();
			foreach($registered as &$data)
			{
				$data = $data['GrpId'];
			}
			if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['password']))
			{
				//_debug_array($_POST['GrpId']);
				foreach(array_merge(array_diff((array)$_POST['GrpId'],$registered),array_diff($registered,(array)$_POST['GrpId'])) as $GrpId)
				{
					try {
						if ($this->register($comp, (int)$GrpId, $athlete, ($mode=in_array($GrpId,$registered)) ?
							ranking_registration::DELETED : ranking_registration::REGISTERED))
						{
							echo "<p class='error'>".lang(!$mode?'%1, %2 registered for category %3':'%1, %2 deleted for category %3',
								strtoupper($athlete['nachname']), $athlete['vorname'], $cats[$GrpId]);
						}
						else
						{
							echo "<p class='error'>".lang('Error: registration')."</p>\n";
						}
					}
					catch(Exception $e) {
						die("<p class='error'>".$e->getMessage()."</p>\n");
					}
				}
				$registered = (array)$_POST['GrpId'];
			}
			if (!$error)
			{
				echo "<p>".lang('Please check the categories you want to register for:')."</p>\n";
				echo "<form method='POST'>\n<table><tr valign='bottom'>\n";
				echo '<td>'.Api\Html::checkbox_multiselect('GrpId', $registered, $cats)."</td>\n";
				echo '<td>'.Api\Html::input('',lang('Register'),'submit')."</td>\n";
				echo "</tr></table>\n</form>\n";
			}
		}
		// list selfscoring
		$selfscore_found = 0;
		foreach(ranking_selfscore_measurement::open($athlete) as $comp)
		{
			if (!$selfscore_found++) echo "<p>".lang('Open scorecards:')."</p>\n<ul>\n";
			echo "<li>".$this->comp->datespan($comp).': '.
				Api\Html::a_href($comp['name'], '/ranking/athlete.php', array('action' => 'scorecard-'.$comp['WetId'].'-'.$comp['GrpId'].'-'.$comp['route_order'])).
				"</li>\n";
		}
		if ($selfscore_found) echo "</ul>\n";
		// list other competitons with selfservice registration
		$found = 0;
		foreach((array)$this->comp->search('', false, 'datum', '', '*', false, 'AND', false, array(
			'datum > '.$this->db->quote(time(), 'date'),
			'datum <= '.$this->db->quote(strtotime('+6 month'), 'date'),
			'selfregister>0',
			'(deadline IS NULL OR deadline>='.$this->db->quote(time(), 'date').')',
			'WetId != '.(int)$WetId,
		)) as $comp)
		{
			if (!$this->comp->open_comp_match($athlete, $comp)) continue;	// comp not open to athletes federation
			if (!$this->matching_cats($comp, $athlete)) continue;	// no category for athlete

			if (!$found++) echo "<p>".lang('Further competitions you can register for:')."</p>\n<ul>\n";
			echo "<li>".$this->comp->datespan($comp).': '.
				Api\Html::a_href($comp['name'], '/ranking/athlete.php', array('action' => 'register-'.$comp['WetId'])).
				"</li>\n";
		}
		if ($found) echo "</ul>\n";

		$this->profile_logout_buttons($athlete);
	}

	/**
	 * Show profile and logout buttons
	 *
	 * @param int|array $athlete
	 */
	private function profile_logout_buttons($athlete)
	{
		// Edit profile and logout buttons
		echo "<div id='profile-logout-buttons'>".
			html::form_1button('profile', lang('Edit Profile'), '', '/index.php', array(
				'menuaction' => 'ranking.ranking_athlete_ui.edit',
				'PerId' => is_array($athlete) ? $athlete['PerId'] : $athlete,
				'cd' => 'no',
			))."\n".
			html::form_1button('logout', lang('Logout'), '',
				'/ranking/athlete.php', array('action' => 'logout')).
			"</div>\n";
	}

	/**
	 * Time in which athlets have to use the password-recovery-link in sec
	 */
	const RECOVERY_TIMEOUT = 14400;
	/**
	 * Number of unsuccessful logins, after which login get suspended
	 */
	const LOGIN_FAILURES = 3;
	/**
	 * How long login get suspended
	 */
	const LOGIN_SUSPENDED = 1800;
	/**
	 * Name of cookie holding last successful used selfregister email
	 */
	const EMAIL_COOKIE = 'digitalrock-selfregister-email';

	/**
	 * Athlete selfservice: password check and recovery
	 *
	 * @param array &$athlete
	 * @param string &$action
	 * @return int PerId if we are authenticated for it nor null if not
	 */
	private function selfservice_auth(array &$athlete, &$action)
	{
		if (!$athlete && isset($_POST['email']))
		{
			if (empty($_POST['email']) || !($athlete = $this->athlete->read(array(
				'email' => $_POST['email'],
				'acl' => null,	// otherwise default ACL will be add to query!
			))))
			{
				echo "<p class='error'>".lang('EMail address NOT found!')."<br/>\n";
				echo lang('Please contact your federation (%1), to have your EMail address added to your athlete profile, so we can mail you a password.',
					lang('or the organizer of the competition'))."<br/>\n";
				echo lang('Maybe you have a different one registered. Try looking it up by using "Edit profile" on your profile page.')."</p>\n";
			}
			else
			{
				echo "<h1>$athlete[vorname] $athlete[nachname] ($athlete[nation])</h1>\n";
			}
		}

		$recovery_link = Egw::link('/ranking/athlete.php', array(
			'PerId' => $athlete['PerId'],
			'action'  => 'recovery',
		));
		if ($athlete && empty($athlete['password']) || in_array($action,array('recovery','password','set')))
		{
			if (empty($athlete['password']) && !in_array($action,array('password','set','recovery')))
			{
				echo "<p class='error'>".lang("You have not yet a password set!")."</p>\n";
			}
			if (empty($athlete['email']) || strpos($athlete['email'],'@') === false)
			{
				echo "<p>".lang('Please contact your federation (%1), to have your EMail addressed added to your athlete profile, so we can mail you a password.',
					$this->federation->get_contacts($athlete))."</p>\n";
			}
			elseif ($action == 'recovery')
			{
				// mail hash to athlete
				//echo "<p>*** TEST *** <a href='$link'>Click here</a> to set a password *** TEST ***</p>\n";
				try {
					$this->password_reset_mail($athlete);
					echo "<p>".lang('An EMail with instructions how to (re-)set the password has been sent.')."</p>\n".
						"<p>".lang('You have to act on the instructions in the next %1 hours, or %2request a new mail%3.',
							self::RECOVERY_TIMEOUT/3600,"<a href='$recovery_link'>","</a>")."</p>\n";
				}
				catch (Exception $e) {
					echo "<p>".lang('Sorry, an error happend sending your EMail (%1), please try again later or %2contact us%3.',
						$e->getMessage(),'<a href="mailto:info@digitalrock.de">','</a>');
				}
			}
			elseif ($action == 'password' || $action == 'set')
			{
				if ($_GET['hash'] != $athlete['recover_pw_hash'])
				{
					echo "<p class='error'>".lang("The link you clicked or entered is NOT correct, maybe a typo!")."</p>\n";
					echo "<p>".lang("Try again or have a %1new mail send to you%2.","<a href='$recovery_link'>","</a>")."</p>\n";
				}
				elseif (($this->athlete->now - strtotime($athlete['recover_pw_time'])) > self::RECOVERY_TIMEOUT)
				{
					echo "<p class='error'>".lang('The link is expired, please have a %1new mail send to you%2.',"<a href='$recovery_link'>","</a>")."</p>\n";
				}
				else
				{
					if ($action == 'set' && $_SERVER['REQUEST_METHOD'] == 'POST')
					{
						if ($_POST['password'] != $_POST['password2'])
						{
							echo "<p class='error'>".lang('Both password do NOT match!')."</p>\n";
						}
						elseif(($msg = Api\Auth::crackcheck($_POST['password'])))
						{
							echo "<p class='error'>".$msg."</p>\n";
						}
						else
						{
							// store new password
							if (!$this->athlete->update(array(
								'password' => $_POST['password'],	// will be hashed in data_merge
							)))
							{
								// store successful selfservice login
								$this->is_selfservice($athlete['PerId']);

								setcookie(self::EMAIL_COOKIE, $athlete['email'], strtotime('1year'), '/', $_SERVER['SERVER_NAME']);

								echo "<p><b>".lang('Your new password is now active.')."</b></p>\n";
								$link = Egw::link('/index.php',array(
									'menuaction' => 'ranking.ranking_athlete_ui.edit',
									'PerId' => $athlete['PerId'],
								));
								return $athlete['PerId'];
								//echo "<p>".lang('You can now %1edit your profile%2 or register for a competition in the calendar.','<a href="'.$link.'">','</a>')."</p>\n";
								//common::egw_exit();
							}
							else
							{
								echo "<p>".lang('An error happend, while storing your password!')."</p>\n";
							}
						}
						echo "<p>".lang('Please try again ...')."</p>\n";
					}
					$link = Egw::link('/ranking/athlete.php', array(
						'PerId' => $athlete['PerId'],
						'action'  => 'set',
						'hash' => $athlete['recover_pw_hash'],
					));
					echo "<p>".lang("Please enter your new password:")."<br />\n".
						'('.lang('Your password need to be at least: %1 characters long, containing a capital letter, a number and a special character.',7).")</p>\n";
					echo "<form action='$link' method='POST'>\n<table>\n";
					echo "<tr><td>".lang('Password')."</td><td><input type='password' name='password' value='".htmlspecialchars($_POST['password'])."' /></td>".
						"<td><label><input type='checkbox' id='show_passwd'>".lang('show password')."</label></td></tr>\n";
					echo "<tr><td>".lang('Repeat')."</td><td><input type='password' name='password2' value='".htmlspecialchars($_POST['password2'])."' /></td>";
					echo "<td><input type='submit' value='".lang('Set password')."' /></td></tr>\n";
					echo "</table>\n</form>\n";
				}
			}
			else
			{
				echo "<p><a href='$recovery_link'>".lang('Click here to have a mail send to your stored EMail address with instructions how to set your password, register for competitions and edit your profile.')."</a></p>\n";
			}
		}
		else
		{
			if ($athlete && !empty($_POST['password']))
			{
				if ($athlete['login_failed'] >= self::LOGIN_FAILURES &&
					($this->athlete->now - strtotime($athlete['last_login'])) < self::LOGIN_SUSPENDED)
				{
					$this->athlete->update(array(
						'last_login' => $this->athlete->now,
						'login_failed=login_failed+1',
					));
					error_log(__METHOD__."($athlete[PerId], '$action') $athlete[login_failed] failed logins, last $athlete[last_login] --> login suspended");
					echo "<p class='error'>".lang('Login suspended, too many unsuccessful tries!')."</p>\n";
					echo "<p>".lang('Try again after %1 minutes.',self::LOGIN_SUSPENDED/60)."</p>\n";
					$this->show_footer();
					exit;
				}
				elseif (!Api\Auth::compare_password($_POST['password'], $athlete['password'], 'crypt'))
				{
					$this->athlete->update(array(
						'last_login' => $this->athlete->now,
						'login_failed=login_failed+1',
					));
					error_log(__METHOD__."($athlete[PerId], '$action') wrong password, {$this->athlete->data['login_failed']} failure");
					echo "<p class='error'>".lang('Password you entered is NOT correct!')."</p>\n";
				}
				else
				{
					$this->athlete->update(array(
						'last_login' => $this->athlete->now,
						'login_failed' => 0,
					));
					error_log(__METHOD__."($athlete[PerId], '$action') successful login");
					// store successful selfservice login
					$this->is_selfservice($athlete['PerId']);

					setcookie(self::EMAIL_COOKIE, $athlete['email'], strtotime('1year'), '/', $_SERVER['SERVER_NAME']);

					if ($action == 'logout') $action = '';
					return $athlete['PerId'];	// we are now authenticated for $athlete['PerId']
				}
			}
			$link = Egw::link('/ranking/athlete.php', array(
				'PerId' => $athlete['PerId'],
				'action'  => $action,
			));
			echo "<form action='$link' method='POST'>\n";
			if (!$athlete)
			{
				echo "<p>".lang("Please enter your EMail address and password to register for competitions or edit your profile:")."</p>\n";
				$pw = htmlspecialchars(isset($_POST['email']) ? $_POST['email'] : $_COOKIE[self::EMAIL_COOKIE]);
				echo "<table>\n<tr><td>".lang('EMail')."</td><td><input type='text' name='email' value='$pw' /></td></tr>\n";
			}
			else
			{
				echo "<p>".lang("Please enter your password to log in or %1click here%2, if you forgot it.","<a href='$recovery_link'>","</a>")."</p>\n";
			}
			echo "<tr><td>".lang('Password')."</td><td><input type='password' name='password' /></td>\n";
			echo "<td><input type='submit' value='".lang('Login')."' /></td></tr>\n";
			echo "</table>\n</form>\n";

			if (!$athlete)
			{
				echo "<p>".lang("If you do not yet have a password or you don't remember it, just enter your email address to get an email with instruction how to set a password.")."</p>\n";
			}
		}
	}

	/**
	 * Send password-reset-mail to athlete
	 *
	 * @param array $athlete
	 * @param string $subject =null default first line of ranking/doc/password-reset-mail.txt
	 * @param string $body =null 2.-last list of above file
	 * @param string $from ='digtal ROCK <info@digitalrock.de>'
	 * @param type $is_html =false
	 * @throws Api\Exception\WrongParameter
	 */
	public function password_reset_mail(array $athlete, $subject=null, $body=null, $from='digtal ROCK <info@digitalrock.de>', $is_html=false)
	{
		if (empty($subject) || empty($body))
		{
			$template = EGW_SERVER_ROOT.'/ranking/doc/reset-password-mail.txt';

			if (!file_exists($template) || !is_readable($template))
			{
				throw new Api\Exception\WrongParameter("Mail template '$template' not found!");
			}
			$is_html = !preg_match('/\.txt$/',$template);

			list($subject, $body) = preg_split("/\r?\n/", file_get_contents($template), 2);
		}
		// generate password reset, if requested in $body
		if (strpos($body, '$$LINK$$') !== false)
		{
			// create and store recovery hash and time
			$this->athlete->update(array(
				'recover_pw_hash' => Api\Auth::randomstring(32),
				'recover_pw_time' => $this->athlete->now,
			));
			$link = Egw::link('/ranking/athlete.php', array(
				'PerId' => $athlete['PerId'],
				'action'  => 'password',
				'hash' => $this->athlete->data['recover_pw_hash'],
			));
			if ($link[0] == '/') $link = 'https://'.$_SERVER['SERVER_NAME'].$link;
		}

		self::mail("$athlete[vorname] $athlete[nachname] <$athlete[email]>",
			$athlete+array(
				'LINK' => !$is_html ? $link : '<a href="'.$link.'">'.$link.'<a>',
				'SERVER_NAME' => $_SERVER['SERVER_NAME'],
				'RECOVERY_TIMEOUT' => self::RECOVERY_TIMEOUT/3600,	// in hours (not sec)
			), $subject, $body, $from, $is_html);
	}

	/**
	 * Sending a templated email
	 *
	 * @param string $email email address(es comma-separated), or rfc822 "Name <email@domain.com>"
	 * @param array $replacements name => value pairs, can be used as $$name$$ in template
	 * @param string $subject
	 * @param string $body
	 * @param string $from
	 * @param boolean $is_html =false $template is html
	 * @throws Exception on error
	 */
	private static function mail($email, array $replacements, $subject, $body, $from, $is_html=false)
	{
		//$email = "$replacements[vorname] $replacements[nachname] <info@digitalrock.de>";
		$replace = array();
		foreach($replacements as $name => $value)
		{
			if (in_array($name, array('password', 'recover_pw_hash', 'recover_pw_time', 'login_failed'))) continue;
			$replace['$$'.$name.'$$'] = $value;
		}

		$mailer = new Api\Mailer();

		$matches = null;
		if (preg_match_all('/"?(.+)"?<(.+)>,?/',$email,$matches))
		{
			$names = $matches[1];
			$addresses = $matches[2];
		}
		else
		{
			$addresses = preg_split('/, */',trim($email));
			$names = array();
		}
		foreach($addresses as $n => $address)
		{
			$mailer->AddAddress($address, $names[$n]);
		}
		$mailer->addHeader('Subject', strtr($subject, $replace));

		if ($is_html)
		{
			$mailer->setHtmlBody(strtr($body, $replace), null, false);
		}
		else
		{
			$mailer->setBody(strtr($body, $replace));
		}
		if (preg_match('/^\s*([^<]+)\s*<([^>]+)>\s*$/', $from, $matches))
		{
			$mailer->setFrom($matches[2], $matches[1]);
		}
		else
		{
			$mailer->setFrom($from);
		}
		$mailer->send();
	}
}
