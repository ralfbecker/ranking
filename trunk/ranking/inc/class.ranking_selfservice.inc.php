<?php
/**
 * EGroupware digital ROCK Rankings - athletes selfservie: profile, registration
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2012 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$
 */

require_once(EGW_INCLUDE_ROOT.'/ranking/inc/class.boranking.inc.php');

/**
 * athletes selfservie: profile, registration
 */
class ranking_selfservice extends boranking
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
	 * @param string $action 'profile'
	 */
	function selfservice($PerId, $action)
	{
		echo "<style type='text/css'>
	body {
		margin: 10px !important;
	}
	p, td, body {
		font-size: 14px;
	}
	p.error {
		color: red;
	}
</style>\n";

		$athlete = array();
		unset($this->athlete->acl2clear[ranking_athlete::ACL_DENY_EMAIL]);	// otherwise anon user never get's email returned!
		if (($PerId || ($PerId = $this->is_selfservice())) && !($athlete = $this->athlete->read($PerId)))
		{
			throw new egw_exception_wrong_userinput("Athlete NOT found!");
		}
		static $nation2lang = array(
			'AUT' => 'de',
			'GER' => 'de',
			'SUI' => 'de',
		);
		$lang = $PerId && isset($nation2lang[$athlete['nation']]) ? $nation2lang[$athlete['nation']] : 'en';
		if (translation::$userlang !== $lang)
		{
			translation::$userlang = $lang;
			translation::init();
		}
		if ($athlete)
		{
			echo "<h1>$athlete[vorname] $athlete[nachname] ($athlete[nation])</h1>\n";
		}
		if ((!$athlete || !$this->acl_check_athlete($athlete) && !$this->is_selfservice() == $PerId) &&
			!($PerId = $this->selfservice_auth($athlete, $action)))
		{
			return;
		}
		list($action,$action_id) = explode('-', $action);
		switch($action)
		{
			case 'profile':
			case '':
				egw::redirect_link('/index.php',array(
					'menuaction' => 'ranking.ranking_athlete_ui.edit',
					'PerId' => $PerId,
				));
				break;

			case 'register':
				$this->selfservice_register($athlete, $action_id);
				break;

			case 'recovery':
			case 'password':
			case 'set':
				$this->selfservice_auth($athlete, $action);
				break;

			default:
				throw new egw_exception_wrong_parameter("Unknown action '$action'!");
		}
	}

	/**
	 * Do a selfservice registration for an already authorized athlete and a given WetId
	 *
	 * @param array $athlete
	 * @param int $WetId
	 */
	private function selfservice_register(array $athlete, $WetId)
	{
		//echo "<p>".__METHOD__."(array(PerId=>$athlete[PerId],nachname='$athlete[nachname]',vorname=$athlete[vorname]',...), $WetId)</p>\n";
		if (!$WetId || !($comp = $this->comp->read($WetId)))
		{
			throw egw_exception_wrong_parameter("Unknown competition ID $WetId!");
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
		// check available categories (matching sex and evtl. agegroup) and if athlete is already registered
		$cats = array();
		foreach($comp['gruppen'] as $rkey)
		{
			if (!($cat = $this->cats->read($rkey))) continue;
			if ($cat['sex'] && $cat['sex'] != $athlete['sex']) continue;
			if (!$this->cats->in_agegroup($athlete['geb_date'], $cat, (int)$comp['datum'])) continue;
			if ($this->comp->has_results($comp['WetId'],$cat['GrpId'])) continue;

			$cats[$cat['GrpId']] = $cat['name'];
		}
		if (!$cats)
		{
			die("<p class='error'>".lang('Competition has no categories you are allowed to register for!')."</p>\n");
		}
		asort($cats);

		$registered =& $this->result->read(array(
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
					if ($this->register($comp,(int)$GrpId,$athlete,$mode=in_array($GrpId,$registered)?2:0))
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
		echo "<p>".lang('Please check the categories you want to register for:')."</p>\n";
		echo "<form method='POST'>\n<table><tr valign='bottom'>\n";
		echo '<td>'.html::checkbox_multiselect('GrpId', $registered, $cats)."</td>\n";
		echo '<td>'.html::input('',lang('Register'),'submit')."</td>\n";
		echo "</tr></table>\n</form>\n";

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
	 * @param array $athlete
	 * @param string $action
	 * @return int PerId if we are authenticated for it nor null if not
	 */
	private function selfservice_auth(array &$athlete, $action)
	{
		if (!$athlete && isset($_POST['email']))
		{
			if (empty($_POST['email']) || !($athlete = $this->athlete->read(array('email' => $_POST['email']))))
			{
				echo "<p class='error'>".lang('EMail address NOT found!')."</p>\n";
				echo "<p>".lang('Maybe you have a different one registered. Try looking it up by using "Edit profile" on your profile page.')."</p>\n";
			}
			else
			{
				echo "<h1>$athlete[vorname] $athlete[nachname] ($athlete[nation])</h1>\n";
			}
		}

		$recovery_link = egw::link('/ranking/athlete.php', array(
			'PerId' => $athlete['PerId'],
			'action'  => 'recovery',
		));
		if ($athlete && empty($athlete['password']) || in_array($action,array('recovery','password','set')))
		{
			if (empty($athlete['password']) && !in_array($action,array('password','set')))
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
						elseif(($msg = auth::crackcheck($_POST['password'])))
						{
							echo "<p class='error'>".$msg."</p>\n";
						}
						else
						{
							// store new password
							if (!$this->athlete->update(array(
								'recover_pw_hash' => null,
								'recover_pw_time' => null,
								'password' => auth::encrypt_ldap($_POST['password'],'sha512_crypt'),
							)))
							{
								// store successful selfservice login
								$this->is_selfservice($athlete['PerId']);

								setcookie(self::EMAIL_COOKIE, $athlete['email'], strtotime('1year'), '/', $_SERVER['SERVER_NAME']);

								echo "<p><b>".lang('Your new password is now active.')."</b></p>\n";
								$link = egw::link('/index.php',array(
									'menuaction' => 'ranking.ranking_athlete_ui.edit',
									'PerId' => $athlete['PerId'],
								));
								echo "<p>".lang('You can now %1edit your profile%2 or register for a competition in the calendar.','<a href="'.$link.'">','</a>')."</p>\n";
								common::egw_exit();
							}
							else
							{
								echo "<p>".lang('An error happend, while storing your password!')."</p>\n";
							}
						}
						echo "<p>".lang('Please try again ...')."</p>\n";
					}
					$link = egw::link('/ranking/athlete.php', array(
						'PerId' => $athlete['PerId'],
						'action'  => 'set',
						'hash' => $athlete['recover_pw_hash'],
					));
					echo "<p>".lang("Please enter your new password:")."<br />\n".
						'('.lang('Your password need to be at least: %1 characters long, containing a capital letter, a number and a special character.',7).")</p>\n";
					echo "<form action='$link' method='POST'>\n<table>\n";
					echo "<tr><td>".lang('Password')."</td><td><input type='password' name='password' value='".htmlspecialchars($_POST['password'])."' /></td>".
						"<td><label><input type='checkbox' onclick=\"this.form.password.type=this.form.password2.type=this.checked?'text':'password';\">".lang('show password')."</label></td></tr>\n";
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
					common::egw_exit();
				}
				elseif (!$loged_in && !auth::compare_password($_POST['password'], $athlete['password'], 'crypt'))
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

					return $athlete['PerId'];	// we are now authenticated for $athlete['PerId']
				}
			}
			$link = egw::link('/ranking/athlete.php', array(
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
	 * @throws Exception on error
	 */
	public function password_reset_mail(array $athlete)
	{
		// create and store recovery hash and time
		$this->athlete->update(array(
			'recover_pw_hash' => md5(microtime(true).$_COOKIE['sessionid']),
			'recover_pw_time' => $this->athlete->now,
		));
		$link = egw::link('/ranking/athlete.php', array(
			'PerId' => $athlete['PerId'],
			'action'  => 'password',
			'hash' => $this->athlete->data['recover_pw_hash'],
		));
		if ($link[0] == '/') $link = 'https://'.$_SERVER['SERVER_NAME'].$link;

		$template = EGW_SERVER_ROOT.'/ranking/doc/reset-password-mail.txt';
		self::mail("$athlete[vorname] $athlete[$nachname] <$athlete[email]>",
			$athlete+array(
				'LINK' => preg_match('/\.txt$/',$template) ? $link : '<a href="'.$link.'">'.$link.'<a>',
				'SERVER_NAME' => $_SERVER['SERVER_NAME'],
				'RECOVERY_TIMEOUT' => self::RECOVERY_TIMEOUT/3600,	// in hours (not sec)
			), $template);
	}

	/**
	 * Sending a templated email
	 *
	 * @param string $email email address(es comma-separated), or rfc822 "Name <email@domain.com>"
	 * @param array $replacements name => value pairs, can be used as $$name$$ in template
	 * @param string $template filename of template, first line is subject, type depends on .txt extension
	 * @param string $from='digtal ROCK <info@digitalrock.de>'
	 * @throws Exception on error
	 */
	private static function mail($email, array $replacements, $template, $from='digtal ROCK <info@digitalrock.de>')
	{
		//$email = "$replacements[vorname] $replacements[nachname] <info@digitalrock.de>";
		$is_txt = preg_match('/\.txt$/', $template);
		if (!($body = file_get_contents($template)))
		{
			throw new egw_exception_wrong_parameter("Mail template '$template' not found!");
		}
		$replace = array();
		foreach($replacements as $name => $value)
		{
			$replace['$$'.$name.'$$'] = $value;
		}
		$body = strtr($body, $replace);
		list($subject,$body) = preg_split("/\r?\n/",$body,2);

		$mailer = new send();
		$mailer->IsHTML(!$is_txt);

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
			$mailer->AddAddress($address,$names[$n]);
		}
		$mailer->Subject = $subject;
		$mailer->Body = $body;

		$mailer->From = $from;
		if (preg_match('/"?(.+)"?<(.+)>,?/',$from,$matches))
		{
			$mailer->FromName = $matches[1];
			$mailer->From = $matches[2];
		}
		$mailer->Send();
	}
}
