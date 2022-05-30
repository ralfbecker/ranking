<?php
/**
 * EGroupware digital ROCK Rankings - athletes selfservice: profile, registration
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2012-21 by Ralf Becker <RalfBecker@digitalrock.de>
 */

namespace EGroupware\Ranking;

use EGroupware\Api;
use EGroupware\Api\Framework;
use EGroupware\Api\Egw;
use EGroupware\OpenID\Keys;
use \Exception;
use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT;
// not yet namespaced ranking_* classes
use \ranking_result_ui;
use \ranking_selfscore_measurement;

/**
 * athletes selfservice: profile, registration
 */
class Selfservice extends Base
{
	/**
	 * @var array $public_functions functions callable via menuaction
	 */
	var $public_functions = array(
		'index' => true,
	);

	/**
	 * Athlete selfservice: edit profile, register for competitions
	 *
	 * @param int $PerId
	 * @param string $_action 'profile'
	 */
	function process($PerId, $_action)
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
		list($action, $action_id) = explode('-', $_action, 2)+['', ''];
		if (!in_array($action, ['scorecard', 'apply', 'download']))
		{
			echo $GLOBALS['egw']->framework->header();
		}

		$athlete = array();
		unset($this->athlete->acl2clear[Athlete::ACL_DENY_EMAIL]);	// otherwise anon user never get's email returned!
		unset($this->athlete->acl2clear[Athlete::ACL_DENY_PROFILE]);	// same is true for a fully denied profile
		if ($action !== 'register' && ($PerId || ($PerId = $this->is_selfservice()) && $PerId !== 'new') &&
			!($athlete = $this->athlete->read($PerId, '', date('Y'), 'GER')))
		{
			throw new Api\Exception\WrongUserinput("Athlete NOT found!");
		}

		// allow switching between athletes with same email without asking passwords again
		if ($PerId && $this->is_selfservice() && $this->is_selfservice() != $PerId)
		{
			$this->is_selfservice(($authenticated = $this->athlete->read($this->is_selfservice())) &&
				!strcasecmp($authenticated['email'], $athlete['email']) ? $athlete['PerId'] : 0);
		}

		if ($athlete)
		{
			// check if we have multiple athletes with same email --> display athlete chooser
			if (!empty($athlete['email']) && $action !== 'confirm')
			{
				$athletes = $this->athlete->search('', false, 'vorname, nachname', '', '', false, 'AND', false, [
					'email' => $athlete['email'],
					'license_nation' => 'GER',
					'license_year' => date('Y'),
				]);
			}
			$this->athleteHeader($athlete, $action, !empty($athletes) && count($athletes) > 1 ? $athletes : null);
		}
		if (!in_array($action, ['register', 'confirm']) &&
			(!$athlete || !$this->acl_check_athlete($athlete) && $this->is_selfservice() != $PerId) &&
			!($PerId = $this->auth($athlete, $_action)))
		{
			$this->showFooter($nation2lang[$athlete['nation']]);
			exit;
		}
		$lang = $PerId && isset($nation2lang[$athlete['nation']]) ? $nation2lang[$athlete['nation']] : 'en';
		if (Api\Translation::$userlang !== $lang)
		{
			Api\Translation::$userlang = $GLOBALS['egw_info']['user']['preferences']['common']['lang'] = $lang;
			Api\Translation::init();
		}
		// check if athlete contented to store his data, if not show consent screen
		if (!in_array($action, ['logout', 'register', 'recovery', 'password', 'confirm']) &&
			(empty($athlete['consent_time']) || empty($athlete['consent_ip'])))
		{
			$this->consentDataStorage($athlete, $lang);
		}
		switch((string)$action)
		{
			case 'profile':
				Egw::redirect_link('/index.php',array(
					'menuaction' => 'ranking.'.Athlete\Ui::class.'.edit',
					'PerId' => $PerId,
					'cd' => 'no',
				));
				break;

			case 'register':
				$this->is_selfservice('new');
				Egw::redirect_link('/index.php',array(
					'menuaction' => 'ranking.'.Athlete\Ui::class.'.edit',
					'preset[PerId]' => 'new',
					'preset[nation]' => 'GER',
					'cd' => 'no',
				));
				break;

			case 'apply':
				$this->applyLicense($athlete, $action_id);
				break;

			case 'download':
				$this->downloadLicense($athlete);
				break;

			case 'confirm':
				$this->confirmLicenseRequest($athlete, $action_id);
				break;

			case '':
			case 'comp':
				$this->registerComp($athlete, $action_id);
				break;

			case 'scorecard':
				$this->scorecard($athlete, $action_id);
				break;

			case 'logout':
				$this->is_selfservice(0);
				Framework::redirect_link('/ranking/athlete.php', [  // redirect to remove action=logout
					'cd' => 'no',
				]);
				break;

			case 'recovery':
			case 'password':
				$this->auth($athlete, $action);
				break;

			case 'set':
				// setting is done in auth() call above
				break;

			default:
				throw new Api\Exception\WrongParameter("Unknown action '$action'!");
		}
		$this->showFooter($lang);
		// run egw destructor now explicit, in case a (notification) email is send via Egw::on_shutdown(),
		// as stream-wrappers used by Horde Smtp fail when PHP is already in destruction
		$GLOBALS['egw']->__destruct();
		exit;
	}

	/**
	 * Ask athlete to consent to data storage
	 *
	 * This will NOT return, unless athlete consents!
	 *
	 * @param array $athlete
	 * @param string $lang ='en' 'de' for DACH
	 */
	private function consentDataStorage(array $athlete, $lang='en')
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
				array(), '/ranking/athlete.php', array('PerId' => $athlete['PerId'], 'cd' => 'no'),
				'',	'style="display: contents"')."\n";

			echo Api\Html::form_1button('logout', lang('Logout'), '',
					'/ranking/athlete.php', array('action' => 'logout', 'cd' => 'no'));
			$this->showFooter($lang);
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
	private function showFooter($lang='en')
	{
		if (!file_exists($file=EGW_SERVER_ROOT.'/ranking/templates/default/selfservice_footer.'.$lang.'.html'))
		{
			$file = EGW_SERVER_ROOT.'/ranking/templates/default/selfservice_footer.html';
		}
		if (!file_exists($file) || !($content = file_get_contents($file)))
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
	private function scorecard(array $athlete, $action_id)
	{
		$this->defaultButtons($athlete);

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
	private function registerComp(array $athlete, $WetId=0)
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
			if ($this->date_over($comp['deadline'] ?: $comp['datum']))
			{
				die("<p class='error'>".lang('Registration for this competition is over!')."</p>\n");
			}
			if ($athlete['license'] == 's')
			{
				die("<p class='error'>".lang('Athlete is suspended !!!')."</p>\n");
			}
			$error = false;
			// check if competition requires a license
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
							Registration::DELETED : Registration::REGISTERED))
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
						_egw_log_exception($e);
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
				Api\Html::a_href($comp['name'], '/ranking/athlete.php', array('action' => 'comp-'.$comp['WetId'])).
				"</li>\n";
		}
		if ($found) echo "</ul>\n";

		$this->defaultButtons($athlete);
	}

	/**
	 * Show profile and logout buttons
	 *
	 * @param int|array $athlete
	 */
	private function defaultButtons($athlete)
	{
		// Edit profile and logout buttons
		echo "<div id='profile-logout-buttons'>" .
			Api\Html::form_1button('profile', lang('Edit Profile'), '', '/index.php', array(
				'menuaction' => 'ranking.' . Athlete\Ui::class . '.edit',
				'PerId' => is_array($athlete) ? $athlete['PerId'] : $athlete,
				'cd' => 'no',
			)) . "\n" .
			Api\Html::form_1button('logout', lang('Logout'), '',
				'/ranking/athlete.php', array('action' => 'logout')) .
			"</div>\n";

		// check if athlete has a license, if not allow to apply now
		if (!is_array($athlete) && !($athlete = $this->athlete->read(['PerId' => $athlete], '', date('Y'), 'GER')))
		{
			throw new Api\Exception\WrongUserinput("Athlete NOT found!");
		}
		if ($athlete['nation'] === 'GER')
		{
			if (empty($athlete['license']) || $athlete['license'] === 'n')
			{
				echo "<div id='apply-license'>" .
					"<p>" . lang('You have no national climber license. To apply for a license you are required to download the application form, sign and post it:') . "</p>\n";

				if (($payment = $this->usePayment($athlete)))
				{
					echo Api\Html::form_1button('license', lang('Athlete license'), [
						'PerId' => $athlete['PerId'],
						'year'  => date('Y'),
						'firstname' => $athlete['vorname'],
						'lastname'  => $athlete['nachname'],
					], $payment['url']);
				}
				else
				{
					echo Api\Html::form_1button('license', lang('Athlete license'), '', '/ranking/athlete.php', array(
						'PerId' => $athlete['PerId'],
						'action' => 'apply',
						'cd' => 'no',
					));
				}

				if (empty($athlete['geb_date']) || date('Y') - (int)$athlete['geb_date'] >= 18)
				{
					echo Api\Html::form_1button('license', lang('Team official license'), '', '/ranking/athlete.php', array(
						'PerId' => $athlete['PerId'],
						'action' => 'apply-GER_TOF',
						'cd' => 'no',
					));
				}
				echo "</div>\n";
			}
			else
			{
				switch($athlete['license'])
				{
					case 'r':
					case 'e':
					case 'a':
						// display [Download license button], in case athlete somehow missed or lost it
						$ui = new Athlete\Ui();
						if ($ui->license_form_name(
							$athlete['nation'], date('Y'),
							$athlete['license_cat'], $athlete['PerId']))
						{
							echo "<p>".lang('Did you already mailed your application form to %1? If not, you can download it again and do so now:',
								'DAV in München').' '.
								Api\Html::form_1button('download', lang('Download license application'), '', '/ranking/athlete.php', array(
									'PerId' => $athlete['PerId'],
									'action' => 'download',
									'cd' => 'no',
								))."</p>\n";
						}
						switch($athlete['license'])
						{
							case 'r':
								echo "<p>".lang('Your license request is waiting for confirmation by %1.', $athlete['verband'])."</p>\n";
								break;
							case 'e':
								echo "<p>".lang('Your license request is waiting for confirmation by %1.',
										self::getInstance()->federation->read($athlete['fed_parent'])['verband'])."</p>\n";
								break;
							case 'a':
								echo "<p>".lang('Your license request has been confirmed by %1 and is waiting now for your posted application to be confirmed by %2.', $athlete['verband'], 'DAV in München')."</p>\n";
								break;
						}
						break;

					case 'c':
						echo "<p>".lang('Your national license is valid.')."</p>\n";
						break;

					case 's':
						echo "<p class='error'>".lang('Your national license has been suspended!')."</p>\n";
						break;
				}
			}
		}
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
	 * Minimum PW length
	 */
	const PW_MIN_LENGTH = 7;
	/**
	 * PW strength, number or char classes, should fit with text:
	 */
	const PW_REQ_STRENGTH = 4;

	/**
	 * Athlete selfservice: password check and recovery
	 *
	 * @param array &$athlete
	 * @param string &$action
	 * @return int PerId if we are authenticated for it nor null if not
	 */
	private function auth(array &$athlete, &$action)
	{
		if (!$athlete && isset($_POST['email']))
		{
			if (empty($_POST['email']) ||
				!($athletes = $this->athlete->search('', false, 'vorname, nachname', '', '', false, 'AND', false, [
					'email' => $_POST['email'],
					'license_nation' => 'GER',
					'license_year' => date('Y'),
				])))
			{
				echo "<p class='error'>".lang('EMail address NOT found!')."<br/>\n";
				echo lang('Please contact your federation (%1), to have your EMail address added to your athlete profile, so we can mail you a password.',
					lang('or the organizer of the competition'))."<br/>\n";
				echo lang('Maybe you have a different one registered. Try looking it up by using "Edit profile" on your profile page.')."</p>\n";
			}
			else
			{
				$athlete = $athletes[0];
			}
		}
		else
		{
			$athletes = $athlete ? [$athlete] : [];
		}

		$recovery_link = Egw::link('/ranking/athlete.php', array(
			'PerId' => $athlete['PerId'],
			'action' => 'recovery',
			'cd' => 'no',
		));
		if ($athlete && empty($athlete['password']) || in_array($action,array('recovery','password','set')))
		{
			if (!$athlete && count($athletes))  // otherwise header is already output
			{
				$this->athleteHeader($athlete = $athletes[0], $action);
			}
			if (empty($athlete['password']) && !in_array($action, array('password','set','recovery','')))
			{
				echo "<p class='error'>".lang("You have not yet a password set!")."</p>\n";
			}
			if (empty($athlete['email']) || strpos($athlete['email'],'@') === false)
			{
				echo "<p>".lang('Please contact your federation (%1), to have your email address added to your athlete profile, so we can mail you a password.',
					$this->federation->get_contacts($athlete))."</p>\n";
			}
			elseif ($action == 'recovery')
			{
				// mail hash to athlete
				//echo "<p>*** TEST *** <a href='$link'>Click here</a> to set a password *** TEST ***</p>\n";
				try {
					$this->passwordResetMail($athlete);
					echo "<p>".lang('An EMail with instructions how to (re-)set the password has been sent.')."</p>\n".
						"<p>".lang('You have to act on the instructions in the next %1 hours, or %2request a new mail%3.',
							self::RECOVERY_TIMEOUT/3600,"<a href='$recovery_link'>","</a>")."</p>\n";
				}
				catch (Exception $e) {
					_egw_log_exception($e);
					echo "<p class='error'>".lang('Sorry, an error happend sending your EMail (%1), please try again later or %2contact us%3.',
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
						elseif(($msg = Api\Auth::crackcheck($_POST['password'], self::PW_REQ_STRENGTH, self::PW_MIN_LENGTH)))
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
								$athlete = $this->athlete->data;
								// store successful selfservice login
								$this->is_selfservice($athlete['PerId']);

								setcookie(self::EMAIL_COOKIE, $athlete['email'], strtotime('1year'), '/', $_SERVER['SERVER_NAME']);

								echo "<p><b>".lang('Your new password is now active.')."</b></p>\n";
								$this->defaultButtons($athlete);
								return $athlete['PerId'];
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
						'cd' => 'no',
					));
					echo "<p>".lang("Please enter your new password:")."<br />\n".
						'('.lang('Your password need to be at least: %1 characters long, containing a capital letter, a number and a special character.', self::PW_MIN_LENGTH).")</p>\n";
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
			// if we have multiple athletes for one email, iterate over them for login
			foreach($athletes as $k => $athlete2)
			{
				$athlete = $athlete2;
				if (!empty($_POST['password']))
				{
					if ($athlete['login_failed'] >= self::LOGIN_FAILURES &&
						($this->athlete->now - strtotime($athlete['last_login'])) < self::LOGIN_SUSPENDED)
					{
						$this->athleteHeader($athlete, $action);
						$this->athlete->update(array(
							'last_login' => $this->athlete->now,
							'login_failed=login_failed+1',
						));
						error_log(__METHOD__ . "($athlete[PerId], '$action') $athlete[login_failed] failed logins, last $athlete[last_login] --> login suspended");
						echo "<p class='error'>" . lang('Login suspended, too many unsuccessful tries!') . "</p>\n";
						echo "<p>" . lang('Try again after %1 minutes.', self::LOGIN_SUSPENDED / 60) . "</p>\n";
						$this->showFooter();
						exit;
					}
					// successful login
					elseif (Api\Auth::compare_password($_POST['password'], $athlete['password'], 'crypt'))
					{
						$this->athleteHeader($athlete, $action, $athletes);

						$this->athlete->update(array(
							'last_login' => $this->athlete->now,
							'login_failed' => 0,
						));
						error_log(__METHOD__ . "($athlete[PerId], '$action') successful login");
						// store successful selfservice login
						$this->is_selfservice($athlete['PerId']);

						setcookie(self::EMAIL_COOKIE, $athlete['email'], strtotime('1year'), '/', $_SERVER['SERVER_NAME']);

						return $athlete['PerId'];    // we are now authenticated for $athlete['PerId']
					}
					// failed login with last athlete, otherwise try next one
					elseif ($k === count($athletes)-1)
					{
						$this->athleteHeader($athlete, $action);

						$this->athlete->update(array(
							'last_login' => $this->athlete->now,
							'login_failed=login_failed+1',
						));
						error_log(__METHOD__ . "($athlete[PerId], '$action') wrong password, {$this->athlete->data['login_failed']} failure");
						echo "<p class='error'>" . lang('Password you entered is NOT correct!') . "</p>\n";
					}
				}
			}
			$link = Egw::link('/ranking/athlete.php', array(
				'PerId' => $athlete['PerId'],
				'action' => $action,
				'cd' => 'no',
			));
			echo "<form action='$link' method='POST'>\n";
			if (!$athlete)
			{
				echo "<p>".lang("If you have no athlete account yet and need to apply for a climbing license, you first need to register:")."\n";
				echo "<button type='submit' name='action' value='register'>".lang('Register / apply for climbing license')."</button></p><hr/>\n";

				echo "<p>".lang("Please enter your EMail address and password to register for competitions or edit your profile:")."</p>\n";
				$pw = htmlspecialchars(isset($_POST['email']) ? $_POST['email'] : $_COOKIE[self::EMAIL_COOKIE]);
				echo "<table>\n<tr><td>".lang('EMail')."</td><td><input type='text' name='email' value='$pw' size='32'/></td></tr>\n";
			}
			else
			{
				echo "<p>".lang("Please enter your password to log in or %1click here%2, if you forgot it.","<a href='$recovery_link'>","</a>")."</p>\n";
			}
			echo "<tr><td>".lang('Password')."</td><td><input type='password' name='password' size='32'/></td>\n";
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
	public function passwordResetMail(array $athlete, $subject=null, $body=null, $from='digtal ROCK <info@digitalrock.de>', $is_html=false)
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
				'action' => 'password',
				'hash' => $this->athlete->data['recover_pw_hash'],
				'cd' => 'no',
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

	/**
	 * Check if athlete trying to register already registered
	 *
	 * We test email, first- and lastname and birthday.
	 * If everything or everything but email matches, we reject registering the athlete again.
	 *
	 * @param array $data
	 * @param ?array& $matches on return matching fields
	 * @return ?array with already registered athlete
	 */
	public function checkAlreadyRegistered(array $data, array &$matches=null)
	{
		$what = array_intersect_key($data, array_flip(['email', 'vorname', 'nachname', 'geb_date']));
		$found = $this->athlete->search($what, array_keys($what), 'email,nachname,vorname', '', '', '', 'OR', false, [
			'nation' => $data['nation'],
			'sex'    => $data['sex'],
		]) ?: [];
		foreach($found as $athlete)
		{
			$matches = array_intersect_assoc($athlete, $what);
			// for the birthdate we also consider just a matching year, if one of the dates is 1st of Jan
			if (!isset($matches['geb_date']) && (substr($data['geb_date'], 4) === '-01-01' || substr($athlete['geb_date'], 4) === '-01-01') &&
				substr($data['geb_date'], 0, 4) === substr($athlete['geb_date'], 0, 4))
			{
				$matches['geb_date'] = $data['geb_date'];
			}
			// all compared fields match --> not allowed
			if (count($matches) === count($what) ||
				// all compared fields, but email match --> not allowed
				!isset($matches['email']) && count($matches) === count($what)-1)
			{
				return $athlete;
			}
		}
		return null;
	}

	/**
	 * Continue with applying for a license, after athlete data has been stored
	 *
	 * @param array $athlete
	 */
	public function continueRegister(array $athlete)
	{
		$this->is_selfservice($athlete['PerId']);

		Api\Framework::redirect_link('/ranking/athlete.php', [
			'PerId' => $athlete['PerId'],
			'action' => 'recovery',
			'cd' => 'no',
		]);
	}

	/**
	 * Flag for applyNotifyFederation to notify or not
	 *
	 * @var bool
	 */
	private static $notify;

	/**
	 * Apply for license: download PDF and notify federation
	 *
	 * @param array $athlete
	 * @param string|int $cat GrpId or rkey of category eg. "GER_TOF"
	 */
	protected function applyLicense(array $athlete, $cat=null)
	{
		try
		{
			if (($payment = $this->usePayment($athlete)))
			{
				try {
					$this->validateJWT($_REQUEST['jwt'], $payment, $athlete['PerId'], date('Y'));
				}
				catch (\Exception $e) {
					throw new \Exception(lang("There was an error with your payment") . ': ' . $e->getMessage().': '.$_REQUEST['jwt'],
						$e->getCode(), $e);
				}
			}
			self::$notify = true;
			Egw::on_shutdown(__CLASS__ . '::applyNotifyFederations', [$athlete, $cat ?: null]);

			$ui = new Athlete\Ui();
			if (($err = $ui->applyLicense([
					'license_year' => date('Y'),
					'license_nation' => $athlete['nation'], // national license
					'license_cat' => $cat && ($cat = $this->cats->read([(is_numeric($cat) ? 'GrpId' : 'rkey') => $cat])) ? $cat['GrpId'] : null,
				] + $athlete, 'r')))
			{
				throw new Api\Exception($err);
			}
		}
		catch (\Exception $e)
		{
			self::$notify = false;
			// add header now, as it was not done to allow download
			echo $GLOBALS['egw']->framework->header();
			$this->athleteHeader($athlete);
			echo "<p class='error'>".$e->getMessage()."</p>\n";
			$this->defaultButtons($athlete);
		}
	}

	/**
	 * Download the license form, does NOT return, if license-template exists and license is applied for, but not yet confirmed
	 *
	 * @param array $athlete
	 */
	protected function downloadLicense(array $athlete)
	{
		$ui = new Athlete\Ui();
		if (in_array($athlete['license'], ['r', 'a']) &&
			$ui->license_form_name(
			$athlete['nation'], date('Y'), $athlete['license_cat'], $athlete['PerId']))
		{
			$ui->licenseform($athlete['nation'], date('Y'),
				$athlete['license_cat'], $athlete['PerId']);
		}
	}

	/**
	 * Generate a token to verify license-request-confirmation by federation/Sektion or state-federation/LV
	 *
	 * @param int $PerId used as "sub" / related to
	 * @param array $claims additional claims
	 * @return string
	 */
	private static function confirmToken(int $PerId, array $claims)
	{
		$config = (new Keys())->jwtConfiguration();
		$now   = new \DateTimeImmutable();
		$builder = $config->builder()
			// Configures the issuer (iss claim)
			->issuedBy(self::issuer())
			// Configures the audience (aud claim)
			->permittedFor(self::issuer())
			// Configures the id (jti claim)
			//->identifiedBy('4f1g23a12aa')
			// Configures the time that the token was issue (iat claim)
			->issuedAt($now)
			// Configures the time that the token can be used (nbf claim)
			->canOnlyBeUsedAfter($now->modify('+1 minute'))
			// Configures the expiration time of the token (exp claim)
			->expiresAt($now->modify('+2 month'))
			// Configures claims
			->relatedTo($PerId);

		// Configures further claims
		foreach($claims as $name => $value)
		{
			if ($name === 'email')
			{
				$value = strtolower($value);
			}
			$builder->withClaim($name, (string)$value);
		}
		// Builds a new token
		return $builder->getToken($config->signer(), $config->signingKey());
	}

	/**
	 * Check token to verify license-request-confirmation by federation/Sektion or state-federation/LV
	 *
	 * @param string $jwt
	 * @param int $PerId
	 * @param array $check_claims claims to check and throw if they are missing or wrong
	 * @return ?JWT\Token\DataSet with claims or NULL
	 */
	private static function checkConfirmToken(string $jwt, int $PerId, array $check_claims=[])
	{
		$config = (new Keys())->jwtConfiguration();
		$token = $config->parser()->parse($jwt);
		assert($token instanceof JWT\Token\Plain);

		$config->setValidationConstraints(
			new JWT\Validation\Constraint\IssuedBy(self::issuer()),
			new JWT\Validation\Constraint\PermittedFor(self::issuer()),
			new JWT\Validation\Constraint\ValidAt(SystemClock::fromUTC(), new \DateInterval('PT60S')),
			new JWT\Validation\Constraint\SignedWith($config->signer(), $config->verificationKey()),
			new JWT\Validation\Constraint\RelatedTo((string)$PerId)
		);

		try
		{
			$config->validator()->assert($token, ...$config->validationConstraints());

			$claims = $token->claims();
			if ($check_claims)
			{
				self::checkClaims($claims, $check_claims);
			}
			return $claims;
		}
		catch (\Exception $e) {
			return null;
		}
	}

	/**
	 * Check claims of a JWT
	 *
	 * @param JWT\Token\DataSet $claims
	 * @param array $check_claims required claims as $name => $value pairs
	 * @return void
	 * @throws Exception
	 */
	private static function checkClaims(JWT\Token\DataSet $claims, array $check_claims)
	{
		foreach ($check_claims as $name => $value)
		{
			if ($name === 'email')
			{
				$value = strtolower($value);
			}
			if (!$claims->has($name) || $claims->get($name) != $value)
			{
				throw new \Exception("Missing or wrong '$name' claim!");
			}
		}
	}

	/**
	 * Return our scheme://host used as JWT issuer or audience
	 *
	 * @return string
	 */
	private static function issuer()
	{
		return Api\Header\Http::fullUrl('');
	}

	/**
	 * Get notification address(es) for license requests
	 *
	 * @param int $fed_id
	 * @return string[]
	 */
	private static function notifyLicenseRequestAddresses(int $fed_id)
	{
		$federation = Base::getInstance()->federation;
		$notify = $federation->getEmails($fed_id);

		// if sektion has no notification address add LV users email addresses
		if (empty($notify))
		{
			$notify = $federation->get_contacts([$fed_id], false);
		}
		return $notify;
	}

	/**
	 * Notify responsible person of athletes federations (Sektion and LV) to approve the request
	 *
	 * @param array $athlete
	 * @param ?int $GrpId for TOF license
	 */
	public static function applyNotifyFederations(array $athlete, int $GrpId=null)
	{
		if (self::$notify && ($fed = self::getInstance()->federation->read(['fed_id' => $athlete['fed_id']])))
		{
			// notify athlete federation (Sektion)
			self::applyNotifyFederation($athlete, $fed['fed_id'], 'confirm-license-mail', $GrpId,
				self::notifyLicenseRequestAddresses($fed['fed_parent']));

			// notify parent federation (LV)
			if ($fed['fed_parent'])
			{
				self::applyNotifyFederation($athlete, $fed['fed_parent'], 'confirm-license-mail-lv', $GrpId);
			}
		}
	}

	/**
	 * Find a mail template preferring html over txt and vfs /templates/ranking over doc dir in sources
	 *
	 * @param string $template basename of template
	 * @param bool& $is_html on return true for html, false for text
	 * @return string[] subject and body of mail
	 * @throws Api\Exception\WrongParameter if template not found
	 */
	private static function getTemplate($template, &$is_html)
	{
		foreach([Api\Vfs::PREFIX.'/templates/ranking', EGW_SERVER_ROOT.'/ranking/doc'] as $dir)
		{
			foreach(['.html', '.txt'] as $extension)
			{
				$path = $dir.'/'.$template.$extension;
				if (file_exists($path) && is_readable($path))
				{
					$is_html = $extension === '.html';

					return preg_split("/\r?\n/", file_get_contents($path), 2);
				}
			}
		}
		throw new Api\Exception\WrongParameter("Mail template '$path' not found!");
	}

	/**
	 * Notify responsible person of athletes federation to approve the request
	 *
	 * @param array $athlete
	 * @param int $fed_id federation to notify
	 * @param string $template
	 * @param ?int $GrpId for TOF license
	 * @param ?string|string[] $parent_contact contact-information of parent federation
	 */
	public static function applyNotifyFederation(array $athlete, int $fed_id, string $template, int $GrpId=null, $parent_contact=null)
	{
		list($subject, $body) = self::getTemplate($template, $is_html);

		$failed = $success = [];
		foreach(self::notifyLicenseRequestAddresses($fed_id) as $key => $email)
		{
			// generate confirm hash
			$link = Egw::link('/ranking/athlete.php', array(
				'PerId' => $athlete['PerId'],
				'action' => 'confirm-' . self::confirmToken($athlete['PerId'], [
					'fed_id' => $fed_id,
					'email' => $email,
					'GrpId' => $GrpId ?? '',
				]),
				'cd' => 'no',
			));
			if ($link[0] == '/') $link = 'https://' . $_SERVER['SERVER_NAME'] . $link;

			try {
				self::mail($email,
					$athlete + array(
						'LINK' => $link,
						'SERVER_NAME' => $_SERVER['SERVER_NAME'],
						'LV-EMAIL-ADDRESSES' => $parent_contact ? implode(', ', (array)$parent_contact) : '',
						'confirm' => '',    // remove markers allowing to cut out confirmation text
					), $subject, $body, "$athlete[vorname] $athlete[nachname] <$athlete[email]>", $is_html);
				$success[] = $email;
			}
			// catch errors to multiple email get send, even if one fails
			catch (\Exception $e) {
				$e = new \Exception("Error sending {basename($template} to $email for $athlete[vorname] $athlete[nachname] <$athlete[email]>: ".
					$e->getMessage(), $e->getCode(), $e);
				_egw_log_exception($e);
				$failed[$key] = $email;
				// todo: notify LV, if notification to sektion failed
			}
		}
		error_log(__METHOD__ . '(' . json_encode($athlete) . ", $fed_id, '$template', $GrpId)".
			($success ? ' success: '.implode(', ', $success) : '').
			($failed ? ' FAILED: '.implode(', ', $failed) : ''));
	}

	/**
	 * Link to confirm license request clicked by federation
	 *
	 * @param ?array $athlete
	 * @param string $token
	 */
	private function confirmLicenseRequest(array $athlete=null, string $token)
	{
		if (!$athlete || empty($token))
		{
			throw new Api\Exception\WrongParameter(($athlete ? '$athlete' : '$token').' must not be empty!');
		}
		if (!($claims = self::checkConfirmToken($token, $athlete['PerId'])))
		{
			throw new Api\Exception\WrongUserinput(lang('Invalid JsonWebToken').': '.lang('Wrong or missing athlete-ID!'));
		}
		if (!($fed = $this->federation->read(['fed_id' => $athlete['fed_id']])))
		{
			throw new Api\Exception\WrongParameter("Error reading athlete federation '$athlete[fed_id]'!");
		}
		$fed2status = [
			$fed['fed_id'] => 'e',      // Sektion confirmed
			$fed['fed_parent'] => 'a',  // LV confirmed
		];
		if (!$claims->has('fed_id') || !($fed_id=$claims->get('fed_id')) || !in_array($fed_id, array_keys($fed2status)) ||
			!($auth_fed = $this->federation->read(['fed_id' => $fed_id])))
		{
			throw new Api\Exception\WrongUserinput(lang('Invalid JsonWebToken').': '.lang("Missing or invalid federation-ID '%1'!", $fed_id));
		}
		if (!$claims->has('email'))
		{
			throw new Api\Exception\WrongUserinput(lang('Invalid JsonWebToken').': '.lang("Missing or invalid email '%1'!", ''));
		}
		$from = $claims->get('email');
		$GrpId = $claims->has('GrpId') ? (int)$claims->get('GrpId') : null;
		$set_status = $fed2status[$fed_id];
		foreach(self::notifyLicenseRequestAddresses($fed_id) as $email)
		{
			if (strtolower($email) === $from)
			{
				if (empty($auth_fed['fed_password']))
				{
					echo "<p class='error'>".lang('Password required to approve license request:').' '.lang('no password set!')."</p>\n";
					return;
				}
				if (in_array($athlete['license'], [$set_status, 'a', 'c']))
				{
					echo "<p>".lang('License request already approved.')."</p>\n";
					return;
				}
				if (($_SERVER['REQUEST_METHOD'] === 'GET' ||
						$_SERVER['REQUEST_METHOD'] === 'POST' && !password_verify($_POST['password'], $auth_fed['fed_password'])))
				{
					if ($_SERVER['REQUEST_METHOD'] === 'POST')
					{
						echo "<p class='error'>".lang('The entered password is invalid!')."</p>\n";
					}
					// show extra confirmation message, if given in mail
					list(, $body) = self::getTemplate($set_status === 'e' ? 'confirm-license-mail' : 'confirm-license-mail-lv', $is_html);
					list(, $confirm) = explode('$$confirm$$', $body);
					if (!empty(trim($confirm)))
					{
						if (!$is_html) echo "\n<b><pre style='white-space: pre-wrap'>";
						echo trim(strtr($confirm, array_combine(array_map(static function($name)
						{
							return '$$'.$name.'$$';
						}, array_keys($athlete)), array_values($athlete))));
						if (!$is_html) echo "</pre></b>\n";
					}
					echo "<p>".lang('Password required to approve license request:')."</p>\n";
					echo Api\Html::form(
							Api\Html::input('password', '', 'password')."\n".
							Api\Html::submit_button('approve', lang('Approve'))/*." ".
							Api\Html::submit_button('deny', lang('Deny'))*/,
							[], $_SERVER['REQUEST_URI']
						)."\n";
					return;
				}
				// ToDo: implement deny
				if (!$this->athlete->set_license(date('Y'), $set_status, $athlete['PerId'], $athlete['nation'], $GrpId))
				{
					echo "<p>".lang('License request already approved.')."</p>\n";
				}
				else
				{
					echo "<p>".lang('License request approved.')."</p>\n";
				}
				return;
			}
		}
		throw new Api\Exception\WrongUserinput("Invalid JsonWebToken for $athlete[vorname] $athlete[nachname] ($athlete[verband] federation #$athlete[fed_id])!");
	}

	/**
	 * Show header with athlete name (for action !== 'apply')
	 *
	 * @param array $athlete
	 * @param ?string $action to add as id "action-$action"
	 */
	private function athleteHeader(array $athlete, string $action=null, array $athletes=null)
	{
		if (in_array($action, ['download', 'apply']))
		{
			return; // apply does download
		}
		echo '<div id="selfservice">';

		$id = isset($action) ? "id='".htmlspecialchars('action-'.$action)."'" : '';
		if (is_array($athletes) && count($athletes) > 1)
		{
			echo "<h1>".Api\Html::form(
				Api\Html::select('PerId', $athlete['PerId'], array_combine(array_map(static function($athlete)
				{
					return $athlete['PerId'];
				}, $athletes), array_map(static function($athlete)
				{
					return $athlete['vorname'].' '.$athlete['nachname'].' ('.$athlete['nation'].')';
				}, $athletes)), true),
				'', $_SERVER['PHP_SELF'].'?cd=popup'
			)."</h1>\n";
		}
		else
		{
			echo "<h1 $id>$athlete[vorname] $athlete[nachname] ($athlete[nation])</h1>\n";
		}
	}

	/**
	 * Get payment details for given athlete's federations
	 *
	 * @param int|array $athlete
	 * @return array|null array with values for keys "url" and "key" or null, if there is no payment
	 * @throws Api\Exception\NotFound Athlete not found
	 */
	protected function usePayment($athlete)
	{
		$fed2payment = [];
		if (!file_exists($file=$GLOBALS['egw_info']['server']['files_dir'].'/ranking/payment.php') || !include($file))
		{
			return null;
		}
		if (is_scalar($athlete) && !($athlete = $this->athlete->read(['PerId' => $athlete])))
		{
			throw new Api\Exception\NotFound("Athlete NOT found!");
		}
		foreach(['fed_id', 'fed_parent'] as $fed)
		{
			if (!empty($athlete[$fed]) && !empty($fed2payment[$athlete[$fed]]))
			{
				return $fed2payment[$athlete[$fed]];
			}
		}
		return null;
	}

	/**
	 * Validate the payment JWT for the given athlete
	 *
	 * @param string $jwt
	 * @param array $payment array with values for keys "url" and "key"
	 * @param int $PerId
	 * @param int $year
	 * @throws \Exception
	 */
	protected function validateJWT(string $jwt, array $payment, int $PerId, int $year)
	{
		$config = JWT\Configuration::forSymmetricSigner(
			new JWT\Signer\Hmac\Sha256(),
			JWT\Signer\Key\InMemory::base64Encoded($payment['key'])
		);
		$token = $config->parser()->parse($jwt);
		assert($token instanceof JWT\Token\Plain);

		$config->setValidationConstraints(
			new JWT\Validation\Constraint\IssuedBy(preg_replace('#^(https?://[^/]+)/.*$#', '$1', $payment['url'])),
			new JWT\Validation\Constraint\PermittedFor('https://digitalrock.de'),
			new JWT\Validation\Constraint\ValidAt(SystemClock::fromUTC(), new \DateInterval('PT60S')),
			new JWT\Validation\Constraint\SignedWith($config->signer(), $config->verificationKey()),
			new JWT\Validation\Constraint\RelatedTo((string)$PerId)
		);

		$config->validator()->assert($token, ...$config->validationConstraints());

		$claims = $token->claims();
		if (!$claims->has('license-year') || $claims->get('license-year') != $year)
		{
			throw new Exception('Missing or wrong "license-year" claim!');
		}
	}
}