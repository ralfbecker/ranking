<?php
/**
 * EGroupware digital ROCK Rankings - athletes UI
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2006-18 by Ralf Becker <RalfBecker@digitalrock.de>
 */

class ranking_athlete_ui extends ranking_bo
{
	/**
	 * @var array $public_functions functions callable via menuaction
	 */
	var $public_functions = array(
		'index' => true,
		'edit'  => true,
		'licenseform' => true,
	);

	/**
	 * ACL labels including explenation titles
	 *
	 * @var array
	 */
	static $acl_labels = array(
		ranking_athlete::ACL_DEFAULT => array(
			'label' => 'Default for active competitors',
			'title' => 'Hide only contact data and birthday from public display.',
		),
		ranking_athlete::ACL_MINIMAL => array(
			'label' => 'Show profile page with results',
			'title' => 'Hide contact data, birthday and city from public display. Still shows optional data like hobbies!',
		),
		ranking_athlete::ACL_DENY_PROFILE => array(
			'label' => 'Hide complete profile page',
			'title' => 'Website visitors will be told that athlete asked to no longer show his profile page.'
		),
		'custom' => 'custom ACL right',
	);

	/**
	 * Labels for custom ACL settings
	 *
	 * @var array
	 */
	static $acl_deny_labels = array(
		ranking_athlete::ACL_DENY_BIRTHDAY	=> 'birthday, shows only the year',
		ranking_athlete::ACL_DENY_EMAIL		=> 'email',
		ranking_athlete::ACL_DENY_PHONE		=> 'phone',
		ranking_athlete::ACL_DENY_FAX		=> 'fax',
		ranking_athlete::ACL_DENY_CELLPHONE	=> 'cellphone',
		ranking_athlete::ACL_DENY_STREET    => 'street, postcode',
		ranking_athlete::ACL_DENY_CITY		=> 'city',
		ranking_athlete::ACL_DENY_PROFILE	=> 'complete profile',
	);

	/**
	 * Instance of etemplate
	 *
	 * @var etemplate
	 */
	var $tmpl;

	/**
	 * Minimum age for athletes
	 *
	 * @var int
	 */
	const MINIMUM_AGE = 3;

	/**
	 * Maximum height to resize portrait or action picture to
	 */
	const MAX_PORTRAIT_HEIGHT = 250;
	const MAX_ACTION_HEIGHT = 417;

	function __construct()
	{
		parent::__construct();

		$this->tmpl = new etemplate;
	}

	/**
	 * Edit an athlete
	 *
	 * Judges for federation/LV (not (inter-)national) competitions have an implicit add right
	 * hardcoded for the nation of the LV.
	 *
	 * @param array $content
	 * @param string $msg
	 */
	function edit($content=null,$msg='',$view=false)
	{
		// selfservice needs old idots mobile support currently, so disable new 16.1 mobile support
		if ($this->is_selfservice())
		{
			$GLOBALS['egw_info']['flags']['deny_mobile'] = true;
		}
		if ($_GET['rkey'] || $_GET['PerId'])
		{
			if (!in_array($license_nation = strip_tags($_GET['license_nation']),$this->license_nations))
			{
				list($license_nation) = each($this->license_nations);
			}
			if ($this->athlete->read($_GET,'',$this->license_year,$license_nation))
			{
				// read the athletes results
				$this->athlete->data['comp'] = $this->result->read(array('PerId' => $this->athlete->data['PerId'],'platz > 0'));
				if ($this->athlete->data['comp']) array_unshift($this->athlete->data['comp'],false);	// reindex with 1
				if (!$this->athlete->data['license_cat'] && (int)$_GET['license_cat'])
				{
					$this->athlete->data['license_cat'] = (int)$_GET['license_cat'];
				}
			}
			else
			{
				$msg .= lang('Entry not found !!!');
			}
		}
		$nations = $this->athlete->distinct_list('nation');

		// set and enforce nation ACL
		if (!is_array($content))	// new call
		{
			$content['license_nation'] = $license_nation;
			$content['license_year'] = $this->license_year;

			if (!$_GET['PerId'] && !$_GET['rkey'] || !$this->athlete->data['PerId'])
			{
				$this->athlete->init($_GET['preset']);
				if (isset($_GET['preset']) && isset($_GET['preset']['verband']) && !isset($_GET['preset']['fed_id']))
				{
					$this->athlete->data['fed_id'] = $this->federation->get_federation($_GET['preset']['verband'],$_GET['preset']['nation'],true);
				}
				// if user is judge on a LV competition, give him just here rights for whole nation
				if (is_numeric($this->only_nation_athlete) && ($fed = $this->federation->read($this->only_nation_athlete)))
				{
					$this->only_nation_athlete = $fed['nation'];
				}
				if ($this->only_nation_athlete) $this->athlete->data['nation'] = $this->only_nation_athlete;
				if (!in_array('NULL',$this->athlete_rights)) $nations = array_intersect_key($nations,array_flip($this->athlete_rights));
				//using athlete_rights_no_judge (and NOT athlete_rights) to check if we should look for federation rights, as otherwise judges loose their regular federation rights
				if (!$this->athlete_rights_no_judge && ($grants = $this->federation->get_user_grants()))
				{
					$feds_with_grants = array();
					foreach($grants as $fed_id => $rights)
					{
						if ($rights & EGW_ACL_ATHLETE)
						{
							$feds_with_grants[] = $fed_id;
						}
					}
					if ($feds_with_grants)
					{
						// if we have a/some feds the user is responsible for get the first (and only) nation
						$nations = $this->federation->query_list('nation','nation',array('fed_id' => $feds_with_grants));
						if (count($nations) != 1) throw new egw_exception_assertion_failed('Fed grants only implemented for a single nation!');
						list($this->only_nation_athlete) = each($nations);
						$this->athlete->data['nation'] = $this->only_nation_athlete;
						// SUI Regionalzentren
						if ($this->only_nation_athlete == 'SUI' && count($feds_with_grants) == 1)
						{
							list($this->athlete->data['acl_fed_id']) = $feds_with_grants;
							list($this->athlete->data['fed_id']) = @each($this->athlete->federations($this->only_nation_athlete,true));	// set the national federation
							unset($feds_with_grants);
						}
						// everyone else (eg. GER Landesverbände)
						else
						{
							list($this->athlete->data['fed_id']) = $feds_with_grants;
						}
					}
				}
			}
			if (!$nations)
			{
				egw_framework::window_close(lang('Permission denied'));
			}
			// we have no edit-rights for that nation
			if (!empty($this->athlete->data['PerId']) && !$this->acl_check_athlete($this->athlete->data))
			{
				$view = true;
			}
			$matches = null;
			$content['referer'] = preg_match('/menuaction=([^&]+)/',$_SERVER['HTTP_REFERER'],$matches) ?
				$matches[1] : 'ranking.ranking_athlete_ui.index';

			if ($content['referer'] == 'ranking.ranking_registration_ui.add' || $_GET['apply_license'])
			{
				$js = "document.getElementById('exec[apply_license]').click();";
			}
			if ($this->athlete->data['license'] == 's')
			{
				$msg = lang('Athlete is suspended !!!');
			}
			elseif (!$view && !$this->athlete->data['PerId'])
			{
				$msg = lang('Please use ONLY a first capital letter for names, do NOT capitalise the whole word!');
			}
		}
		else
		{
			$view = $content['view'];

			if (!$view && $this->only_nation_athlete) $content['nation'] = $this->only_nation_athlete;

			//echo "<br>ranking_athlete_ui::edit: content ="; _debug_array($content);
			$this->athlete->init($content['athlete_data']);
			// reload license, if nation or year changed
			if ($content['old_license_nation'] != $content['license_nation'])
			{
				$content['athlete_data']['license'] = $content['license'] = $this->athlete->get_license($content['license_year'],$content['license_nation']);
			}
			// restore some fields set by ranking_athlete::read, which are no real athlete fields
			foreach(array('comp','last_comp','license','license_cat') as $name)
			{
				$this->athlete->data[$name] = $content['athlete_data'][$name];
			}
			$old_geb_date = $this->athlete->data['geb_date'];

			$this->athlete->data_merge($content);
			// deal with custom ACL
			if ($content['acl'] === 'custom')
			{
				$this->athlete->data['acl'] = $content['custom_acl'];
			}
			else
			{
				$this->athlete->data['acl'] = array();
				for($acl=1; $acl <= ranking_athlete::ACL_DENY_PROFILE; $acl *= 2)
				{
					if ($content['acl'] & $acl) $this->athlete->data['acl'][] = $acl;
				}
				$content['custom_acl'] = $this->athlete->data['acl'];
			}
			$this->athlete->data['acl_fed_id'] = (int)$content['acl_fed_id']['fed_id'];
			//echo "<br>ranking_athlete_ui::edit: athlete->data ="; _debug_array($this->athlete->data);

			if (($content['save'] || $content['apply']) || $content['apply_license'])
			{
				if ($this->acl_check_athlete($this->athlete->data))
				{
					// if user is judge on a LV competition, give him just here rights for whole nation
					if (is_numeric($this->only_nation_athlete) && ($fed = $this->federation->read($this->only_nation_athlete)))
					{
						$content['nation'] = $this->athlete->data['nation'] = $fed['nation'];
					}
					if (!$this->athlete->data['rkey'])
					{
						$this->athlete->generate_rkey();
					}
					if (!$this->is_admin || !$content['password'] || $content['password'] != $content['password2'])
					{
						$this->athlete->data['password'] = $content['athlete_data']['password'];
					}
					$fed_changed = null;
					if ($this->is_admin && $content['password'] && $content['password'] != $content['password2'])
					{
						$msg .= lang('Both password do NOT match!');
					}
					elseif ($old_geb_date && !$this->athlete->data['geb_date'] && !$this->is_admin)
					{
						$msg .= lang("Use the ACL to hide the birthdate, you can't remove it !!!");
						$this->athlete->data['geb_date'] = $old_geb_date;
					}
					elseif($this->athlete->data['geb_date'] && ranking_athlete::age($this->athlete->data['geb_date']) < self::MINIMUM_AGE)
					{
						$msg .= lang("Athlets need to be at least %1 years old! Maybe wrong date format.",self::MINIMUM_AGE);
					}
					elseif ($this->athlete->not_unique())
					{
						$msg .= lang("Error: Key '%1' exists already, it has to be unique !!!",$this->athlete->data['rkey']);
					}
					elseif ($this->athlete->save(null, null, $fed_changed))
					{
						$msg .= lang('Error: while saving !!!');
					}
					else
					{
						// we have no edit-rights for that nation, but were allowed to add it as judge
						if (!$this->acl_check_athlete($this->athlete->data))
						{
							$view = true;
						}
						$msg .= lang('%1 saved',lang('Athlete'));

						if ($this->athlete->data['nation'] == 'GER' && $fed_changed)
						{
							$msg .= "\n".lang('License should be removed when federation was changed!');
						}
						foreach(array(
							'foto' => null,
							'foto2' => 2,
						) as $pic => $postfix)
						{
							$max_height = $pic == 'foto' ? self::MAX_PORTRAIT_HEIGHT : self::MAX_ACTION_HEIGHT;
							if (is_array($content[$pic]) && $content[$pic]['tmp_name'] && $content[$pic]['name'] && is_uploaded_file($content[$pic]['tmp_name']))
							{
								//_debug_array($content[$pic]);
								list($width,$height,$type) = getimagesize($content[$pic]['tmp_name']);
								if ($type != 2)
								{
									$msg .= ($msg ? ', ' : '') . lang('Uploaded picture is no JPEG !!!');
								}
								else
								{
									if ($height > $max_height && ($src = @imagecreatefromjpeg($content[$pic]['tmp_name'])))	// we need to scale the picture down
									{
										$dst_w = (int) round((float)$max_height * $width / $height);
										//echo "<p>{$content[$pic]['name']}: $width x $height ==> $dst_w x $max_height</p>\n";
										$dst = imagecreatetruecolor($dst_w,$max_height);
										if (imagecopyresampled($dst,$src,0,0,0,0,$dst_w,$max_height,$width,$height))
										{
											imagejpeg($dst,$content[$pic]['tmp_name']);
											$msg .= ($msg ? ', ' : '') . lang('Picture resized to %1 pixel',$dst_w.' x '.$max_height);
										}
										imagedestroy($src);
										imagedestroy($dst);
									}
									$msg .= ($msg ? ', ' : '') . ($this->athlete->attach_picture($content[$pic]['tmp_name'], null, $postfix) ?
										lang('Picture attached') : lang('Error attaching the picture'));
								}
							}
						}
						if ($content['apply_license'])
						{
							if ($content['athlete_data']['license'] == 's')
							{
								$msg .= ', '.lang('Athlete is suspended !!!');
								$required_missing = true;	// to not download the form
							}
							elseif(!$this->acl_check_athlete($this->athlete->data,EGW_ACL_ATHLETE,null,$content['license_nation']))
							{
								$msg .= ', '.lang('You are not permitted to apply for a license!');
								$required_missing = true;	// to not download the form
							}
							elseif ($content['athlete_data']['license'] != 'a')
							{
								// check for required data
								static $required_for_license = array(
									'vorname','nachname','nation','geb_date','sex',
									'verband','ort','strasse','plz',
									'email','tel','mobil'
								);
								foreach($required_for_license as $name)
								{
									if (!$this->athlete->data[$name])
									{
										if (!$required_missing) $msg .= ', '.lang('Required information missing, application rejected!');
										$this->tmpl->set_validation_error($name,lang('Field must not be empty !!!'));
										$content['tabs'] = 'contact';
										$required_missing = $name;
									}
								}
								if (!$required_missing)
								{
									if ($this->athlete->set_license($content['license_year'],'a',null,$content['license_nation'],$content['license_cat']))
									{
										$msg .= ', '.lang('Applied for a %1 license',$content['license_year'].' '.
											(!$content['license_nation'] || $content['license_nation'] == 'NULL' ?
											lang('international') : $content['license_nation']));
									}
									else
									{
										$msg .= ', '.lang('Athlete is NOT in agegroup of selected license category!');
										$required_missing = true;	// to not download the form
									}
								}
							}
							else
							{
								$msg .= ', '.lang('Someone already applied for a %1 license!',$content['license_year'].' '.
									(!$content['license_nation'] || $content['license_nation'] == 'NULL' ?
									lang('international') : $content['license_nation']));
							}
							// download form
							if (file_exists($this->license_form_name($content['license_nation'],$content['license_year'])) && !$required_missing)
							{
								$link = $GLOBALS['egw']->link('/index.php',array(
									'menuaction' => 'ranking.ranking_athlete_ui.licenseform',
									'PerId' => $this->athlete->data['PerId'],
									'license_year' => $content['license_year'],
									'license_nation' => $content['license_nation'],
									'license_cat' => $content['license_cat'],
								));
								$js .= "window.location='$link';";
							}
						}
						// change of license status, requires athlete rights for the license-nation
						elseif (($content['athlete_data']['license'] != $content['license'] ||
							$content['athlete_data']['license_cat'] != $content['license_cat']) &&
							$this->acl_check($content['license_nation'],EGW_ACL_ATHLETE))	// you need int. athlete rights
						{
							if (!$this->athlete->set_license($content['license_year'],$content['license'],null,$content['license_nation'],$content['license_cat']))
							{
								$msg .= ', '.lang('Athlete is NOT in agegroup of selected license category!');
							}
						}
					}
				}
				else
				{
					$msg .= lang('Permission denied !!!').' ('.$this->athlete->data['nation'].')';
				}
				$link = $GLOBALS['egw']->link('/index.php',array(
					'menuaction' => $content['referer'],//'ranking.ranking_athlete_ui.index',
					'msg' => $msg,
				)+($content['row'] ? array('row['.$content['row'].']' => $this->athlete->data['PerId']) : array()));
				if (!$required_missing && $this->is_selfservice() != $this->athlete->data['PerId'])
				{
					$js = "window.opener.location='$link'; $js";
				}
			}
			if ($content['pw_mail'])
			{
				try {
					$selfservice = new ranking_selfservice();
					$selfservice->password_reset_mail($this->athlete->data);
					$msg .= "\n".lang('An EMail with instructions how to (re-)set the password has been sent.');
				}
				catch (Exception $e) {
					$msg .= "\n".lang('Sorry, an error happend sending your EMail (%1), please try again later or %2contact us%3.',
						$e->getMessage(),'<a href="mailto:info@digitalrock.de">','</a>');
				}
			}
			if ($content['delete'])
			{
				$link = $GLOBALS['egw']->link('/index.php',array(
					'menuaction' => 'ranking.ranking_athlete_ui.index',
					'delete' => $this->athlete->data['PerId'],
				));
				$js = "window.opener.location='$link';";
			}
			if ($content['save'] || $content['delete'] || $content['cancel'])
			{
				if ($this->is_selfservice())
				{
					egw::redirect_link('/ranking/athlete.php');
				}
				echo "<html><head><script>\n$js;\nwindow.close();\n</script></head></html>\n";
				common::egw_exit();
			}
			if ($content['merge'] && $this->athlete->data['PerId'])
			{
				$to = is_array($content['merge_to']) ? $content['merge_to']['current'] : $content['merge_to'];
				if (!(int)$to)
				{
					$msg = lang('You need to select an other athlete first!');
				}
				else
				{
					try {
						$msg = lang('Athlete including %1 results merged.',$this->merge_athlete($this->athlete->data['PerId'],$to));
						$this->athlete->read($to);	// show the athlete we merged too
						// read the athletes results
						$this->athlete->data['comp'] = $this->result->read(array('PerId' => $this->athlete->data['PerId'],'platz > 0'));
						if ($this->athlete->data['comp']) array_unshift($this->athlete->data['comp'],false);	// reindex with 1
						$link = $GLOBALS['egw']->link('/index.php',array(
							'menuaction' => $content['referer'],//'ranking.ranking_athlete_ui.index',
							'msg' => $msg,
						));
						$js = "window.opener.location='$link';";
						unset($content['merge_to']);
					}
					catch(Exception $e) {
						$msg = $e->getMessage();
					}
				}
			}
		}
		if (true) $content = array(
			'acl' => !empty($content['acl']) ? $content['acl'] : $this->athlete->data['acl'],
		) + $this->athlete->data + array(
			'msg' => $msg,
			'is_admin' => $this->is_admin,
			'tabs' => $content['tabs'],
			'foto' => $this->athlete->picture_url().'?'.time(),
			'foto2' => $this->athlete->picture_url(null, 2).'?'.time(),
			'license_year' => $content['license_year'],
			'license_nation' => $content['license_nation'],
			'license_cat' => $content['license_cat'],
			'referer' => $content['referer'],
			'merge_to' => $content['merge_to'],
		);
		// initialise ACL selectbox
		$content['custom_acl'] = $this->athlete->data['acl'];
		if ($content['acl'] !== 'custom')
		{
			$content['acl'] = 0;
			foreach($this->athlete->data['acl'] as $acl)
			{
				$content['acl'] |= $acl;
			}
			if (in_array(ranking_athlete::ACL_DENY_PROFILE, $content['custom_acl']))
			{
				$content['acl'] = ranking_athlete::ACL_DENY_PROFILE;
			}
			elseif (!isset(self::$acl_labels[$content['acl']]))
			{
				$content['acl'] = 'custom';
			}
		}
		if ($this->athlete->data['password'] == $content['password'])
		{
			unset($content['password']);
		}
		switch($content['license_nation'])
		{
			case 'SUI':
				$content['license_msg'] = 'Wir haben die Lizenzanfrage für diese/n Athleten/in erhalten. Deinem Regionalzentrum wird Ende Saison eine Rechnung über den Gesamtbetrag der Lizenzkosten zugestellt.
Die Jahreslizenzen werden nicht mehr in Papierform abgegeben. Die Lizenz-Nummer jedes Athleten ist im digitalROCK erfasst und jederzeit überprüfbar.

Fortfahren?

Nous avons bien reçu la demande de licence pour cet/te athlète, ton centre régional recevra une facture du coût total des licences à la fin de la saison.
La licence annuelle ne sera plus imprimée sur papier. Le numéro de licence de chaque athlète est visible dans le système de digitalROCK.

Continuer';
				break;

			default:
				$content['license_msg'] = lang('You need to mail the downloaded AND signed form to the office! Please check if you filled out ALL fields (you may hide some via ACL from public viewing). Continue');

		}
		$content['acl_fed_id'] = array('fed_id' => $this->athlete->data['acl_fed_id']);
		$sel_options = array(
			'nation' => $nations,
			'sex'    => $this->genders,
			'acl'    => self::$acl_labels,
			'custom_acl' => self::$acl_deny_labels,
			'license'=> $this->license_labels,
			'fed_id' => !$content['nation'] ? array(lang('Select a nation first')) :
				$this->athlete->federations($content['nation'],false,$feds_with_grants ? array('fed_id' => $feds_with_grants) : array()),
			'license_nation' => ($license_nations = $this->license_nations),
			'license_cat' => $this->cats->names(array(
				'sex' => $content['sex'] ? ($content['sex']=='male'?'!female':'!male') : null,
				'nation' => $content['license_nation'] != 'NULL' ? $content['license_nation'] : null,
			),0),
		);
		$edit_rights = $this->acl_check_athlete($this->athlete->data);
		$readonlys = array(
			'delete' => !$this->athlete->data['PerId'] || !$edit_rights || $this->athlete->data['comp'],
			'nation' => !!$this->only_nation_athlete,
			'edit'   => $view || !$edit_rights,
			'pw_mail'=> !$content['email'],
			// show apply license only if status is 'n' or 'a' AND user has right to apply for license
			'apply_license' => in_array($content['license'],array('s','c')) ||
				!$this->acl_check_athlete($this->athlete->data,EGW_ACL_ATHLETE,null,$content['license_nation']),
			// to simply set the license field, you need athlete rights for the nation of the license
			'license'=> !$this->acl_check($content['license_nation'],EGW_ACL_ATHLETE,null,false,null,true),	// true=no judge rights
			'kader'=> !$this->acl_check($content['license_nation'],EGW_ACL_ATHLETE,null,false,null,true),	// true=no judge rights
			// for now disable merge, if user is no admin: !$this->is_admin || (can be removed later)
			'merge' => !$this->is_admin || !$edit_rights || !$this->athlete->data['PerId'],
			'merge_to' => !$this->is_admin || !$edit_rights || !$this->athlete->data['PerId'],
			'custom_acl' => !($this->is_admin || $edit_rights || !$this->athlete->data['PerId']) || $content['acl'] !== 'custom',
		);
		// only allow to set license-category when applying or having rights to change license
		$readonlys['license_cat'] = $readonlys['apply_license'] && $readonlys['license'];

		if (!$readonlys['merge_to'] && !$content['merge_to'])
		{
			$content['merge_to']['query'] = $content['sex'][0].':'.$content['nation'].':!'.$content['PerId'].': '.$content['nachname'];
		}
		if (count($license_nations) == 1)	// no selectbox, if no selection
		{
			$readonlys['license_nation'] = true;
		}
		// dont allow non-admins to change sex and nation, once it's been set
		if ($this->athlete->data['PerId'] && !$this->is_admin)
		{
			if ($this->athlete->data['nation']) $readonlys['nation'] = true;
			if ($this->athlete->data['sex']) $readonlys['sex'] = true;
		}
		if ($view)
		{
			foreach(array_keys($this->athlete->data) as $name)
			{
				$readonlys[$name] = true;
			}
			$readonlys['acl_fed_id[fed_id]'] = $readonlys['foto'] = $readonlys['foto2'] =
				$readonlys['delete'] = $readonlys['save'] = $readonlys['apply'] = true;
		}
		else
		{
			if (!$this->athlete->data['PerId'] || $this->athlete->data['last_comp'])
			{
				$readonlys['delete'] = true;
			}
			if ($content['nation'] != 'SUI' || !in_array('SUI',$this->athlete_rights))
			{
				$readonlys['acl_fed_id[fed_id]'] = !$this->is_admin;	// dont allow non-admins to set acl_fed_id for nations other then SUI
			}
			// forbid SUI RGZs to change Sektion
			if ($content['nation'] == 'SUI' && !in_array('SUI',$this->athlete_rights) && !$this->is_admin)
			{
				$readonlys['fed_id'] = $readonlys['a2f_start'] = true;
			}
			// forbid non-admins or users without competition edit rights to change
			// the name of an athlete who has not climbed for more then two years
			// (gard against federations "reusing" athlets)
			if ($this->athlete->data['PerId'] && $this->athlete->data['last_comp'] &&
				ranking_athlete::age($this->athlete->data['last_comp']) > 2 &&
				!($this->is_admin || array_intersect(array($this->athlete->data['nation'], 'NULL'), $this->edit_rights)))
			{
				$readonlys['vorname'] = $readonlys['nachname'] = true;
			}
			// forbid athlete selfservice to change certain fields
			if ($this->athlete->data['PerId'] && $this->is_selfservice() == $this->athlete->data['PerId'])
			{
				$readonlys['vorname'] = $readonlys['nachname'] = $readonlys['geb_date'] = true;
				$readonlys['fed_id'] = $readonlys['a2f_start'] = true;
				// 'til we have some kind of review mechanism
				$readonlys['tabs']['pictures'] = true;
			}
		}
		if ($js)
		{
			egw_framework::set_onload($js);
		}
		$GLOBALS['egw_info']['flags']['app_header'] = lang('ranking').' - '.lang($view ? 'view %1' : 'edit %1',lang('Athlete'));
		$this->tmpl->read('ranking.athlete.edit');
		// until xajax_doXMLHTTP is no longer used in edit template (loaded implict by minifying!)
		egw_framework::validate_file('/api/js/egw_json.js');
		$this->tmpl->exec('ranking.ranking_athlete_ui.edit',$content,
			$sel_options,$readonlys,array(
				'athlete_data' => $this->athlete->data,
				'view' => $view,
				'referer' => $content['referer'],
				'row' => (int)$_GET['row'] ? (int)$_GET['row'] : $content['row'],
				'license_year' => $content['license_year'],
				'license_nation' => $content['license_nation'],
				'license_cat' => $content['license_cat'],
				'license' => $content['license'],
				'old_license_nation' => $content['license_nation'],
				'acl_fed_id' => $content['acl_fed_id'],
				'custom_acl' => $content['custom_acl'],
			),2);
	}

	/**
	 * query athlets for nextmatch in the athlets list
	 *
	 * @param array &$query_in
	 * @param array &$rows returned rows/cups
	 * @param array &$readonlys eg. to disable buttons based on acl
	 */
	function get_rows(&$query_in,&$rows,&$readonlys)
	{
		//echo "ranking_athlete_ui::get_rows() query="; _debug_array($query_in);
		if (!$query_in['csv_export'])	// only store state if NOT called as csv export
		{
			$GLOBALS['egw']->session->appsession('ranking','athlete_state',$query_in);
		}
		$query = $query_in;

		foreach((array) $query['col_filter'] as $col => $val)
		{
			if ($val == 'NULL') $query['col_filter'][$col] = null;
		}
		$cat_filter = array(
			'nation' => array_keys($this->ranking_nations),
		);
		if ($query['col_filter']['sex'])
		{
			$cat_filter['sex'] = $query['col_filter']['sex'] == 'NULL' ? null : $query['col_filter']['sex'];
		}
		else
		{
			unset($query['col_filter']['sex']);	// no filtering
		}
		if ($query['filter2']) $query['col_filter']['license'] = $query['filter2'];

		foreach(array('vorname','nachname') as $name)
		{
			if ($query['col_filter']['nation'])
			{
				$filter = array('nation' => $query['col_filter']['nation']);
				if ($query['col_filter']['sex'])
				{
					$filter['sex'] = $query['col_filter']['sex'];
				}
				$sel_options[$name] =& $this->athlete->distinct_list($name,$filter);
			}
			else
			{
				$sel_options[$name] = array(1+$query['col_filter'][$name] => lang('Select a nation first'));
			}
			if (!isset($sel_options[$name][$query['col_filter'][$name]]))
			{
				$query['col_filter'][$name] = '';
			}
		}
		$sel_options['filter'] = array('' => lang('All')) + $this->cats->names($cat_filter,-1);
		$sel_options['fed_id'] = $this->athlete->federations($query['col_filter']['nation']);

		if($query['filter'] && ($cat = $this->cats->read($query['filter'])))
		{
			$license_nation = $cat['nation'];
		}
		elseif ($query['col_filter']['nation'])
		{
			$license_nation = $query['col_filter']['nation'];
			if (!in_array($license_nation,$this->license_nations))
			{
				list($license_nation) = each($this->license_nations);
			}
		}
		$query['col_filter']['license_nation'] = $license_nation;

		$total = $this->athlete->get_rows($query,$rows,$readonlys,(int)$query['filter'] ? (int)$query['filter'] : true);

		//_debug_array($rows);

		if ($query['col_filter']['nation'] == 'SUI')
		{
			$feds = $this->federation->federations($query['col_filter']['nation']);
		}
		$readonlys = array();
		foreach($rows as &$row)
		{
			if ($row['last_comp'] || !$this->acl_check_athlete($row))
			{
				$readonlys["delete[$row[PerId]]"] = true;
			}
			$readonlys["apply_license[$row[PerId]]"] = $row['license'] != 'n' ||
				!$this->acl_check_athlete($row,EGW_ACL_ATHLETE,null,$license_nation?$license_nation:'NULL');

			if ($feds && $row['fed_parent'] && isset($feds[$row['fed_parent']]))
			{
				$row['regionalzentrum'] = $feds[$row['fed_parent']];
			}
			if ($query['csv_export'])
			{
				$row['license_status'] = lang($this->license_labels[$row['license']]);
				$row['sex'] = lang($row['sex']);
			}
		}
		$sel_options['license_nation'] = $this->license_nations;
		$rows['sel_options'] =& $sel_options;
		$rows['license_nation'] = !$license_nation ? 'NULL' : $license_nation;
		$rows['license_year'] = $this->license_year;
		$rows['license_cat'] = $query['filter'];
		// dont show license column for nation without licenses or if a license-filter is selected
		$rows['no_license'] = $query['filter2'] != '' || !isset($sel_options['license_nation'][$rows['license_nation']]);
		// dont show license filter for a nation without license
		$query_in['no_filter2'] = !isset($sel_options['license_nation'][$rows['license_nation']]);

		if ($this->debug)
		{
			echo "<p>uiathles::get_rows(".print_r($query,true).") rows ="; _debug_array($rows);
			_debug_array($readonlys);
		}
		return $total;
	}

	/**
	 * List existing Athletes
	 *
	 * @param array $_content =null
	 * @param string $msg =''
	 */
	function index($_content=null,$msg='')
	{
		if ($_GET['delete'] || is_array($_content['nm']['rows']['delete']))
		{
			if (is_array($_content['nm']['rows']['delete']))
			{
				list($id) = each($_content['nm']['rows']['delete']);
			}
			else
			{
				$id = $_GET['delete'];
			}
			if (!$this->is_admin && $this->athlete->read(array('PerId' => $id)) && !$this->acl_check_athlete($this->athlete->data))
			{
				$msg = lang('Permission denied !!!');
			}
			elseif ($this->athlete->has_results($id))
			{
				$msg = lang('You need to delete the results first !!!');
			}
			else
			{
				$msg = $this->athlete->delete(array('PerId' => $id)) ?
					lang('%1 deleted',lang('Athlete')) : lang('Error: deleting %1 !!!',lang('Athlete'));
			}
		}
		$content = array(
			'msg' => $msg ? $msg : $_GET['msg'],
		);
		if (!is_array($content['nm'])) $content['nm'] = $GLOBALS['egw']->session->appsession('ranking','athlete_state');

		if (!is_array($content['nm']))
		{
			$content['nm'] = array(
				'get_rows'       =>	'ranking.ranking_athlete_ui.get_rows',
				'filter_no_lang' => True,
				'filter_label'   => lang('Category'),
				'filter2_label'  => 'License',
				'no_cat'         => True,// I  disable the cat-selectbox
				'order'          =>	'nachname',// IO name of the column to sort after (optional for the sortheaders)
				'sort'           =>	'ASC',// IO direction of the sort: 'ASC' or 'DESC'
				'csv_fields'     => false,
			);
			// only enable csv export, if user has at least for one nation athlete rights
			if (count($this->athlete_rights))
			{
				$content['nm']['csv_fields'] = array(
					'nachname' => lang('Name'),
					'vorname'  => lang('First name'),
					'geb_date' => lang('Birthday'),
					'sex'      => lang('Gender'),
					'plz'      => lang('Zip Code'),
					'ort'      => lang('City'),
					'strasse'  => lang('Street'),
					'email'    => lang('Email'),
					'tel'      => lang('Phone'),
					'mobil'    => lang('Cellphone'),
					'verband'  => lang('Sektion'),
					'regionalzentrum' => lang('Regionalzentrum'),
					'PerId'    => lang('License'),
					'license_status' => lang('Status'),
					'last_comp' => lang('Last competition'),
				);
			}
			if ($this->only_nation_athlete)
			{
				$content['nm']['col_filter']['nation'] = $this->only_nation_athlete;
			}
			// also set nation filter, if grants are from a single nation
			elseif (count($fed_nations = $this->federation->get_user_nations()) == 1)
			{
				$content['nm']['col_filter']['nation'] = array_pop($fed_nations);
			}
		}
		if (($readonlys['nm[rows][edit][0]'] = !count($this->athlete_rights)) && ($grants = $this->federation->get_user_grants()))
		{
			foreach($grants as $rights)
			{
				if ($rights & EGW_ACL_ATHLETE)
				{
					$readonlys['nm[rows][edit][0]'] = false;
					break;
				}
			}
		}
		$this->tmpl->read('ranking.athlete.index');
		$GLOBALS['egw_info']['flags']['app_header'] = lang('ranking').' - '.lang('Athletes');
		$this->set_ui_state();
		$this->tmpl->exec('ranking.ranking_athlete_ui.index',$content,array(
			'nation' => $this->athlete->distinct_list('nation'),
			'sex'    => array_merge($this->genders,array(''=>'')),	// no none
			'filter2'=> array('' => 'All')+$this->license_labels,
			'license'=> $this->license_labels,
			'license_cat' => $this->cats->names(array(), 0),
		),$readonlys);
	}

	/**
	 * Get the name of the license form, depending on nation and year
	 *
	 * Licenseforms are in PDF directory under following names:
	 * - $year/license_$year$nation_$catrkey.rtf (highest priority)
	 * - $year/license_$year$nation.rtf
	 *
	 * @param string $nation =null
	 * @param int $year =null defaults to $this->license_year
	 * @param int $GrpId =null category to apply for
	 * @return string path on server
	 */
	function license_form_name($nation=null, $year=null, $GrpId=null)
	{
		if (is_null($year)) $year = $this->license_year;
		if ($nation == 'NULL') $nation = null;

		$base = egw_vfs::PREFIX.$this->comp->vfs_pdf_dir;

		if (!(int)$GrpId || !($cat = $this->cats->read($GrpId)) ||
			!file_exists($file = $base.'/'.$year.'/license_'.$year.$nation.'_'.$cat['rkey'].'.rtf'))
		{
			$file = $base.'/'.$year.'/license_'.$year.$nation.'.rtf';
		}
		return $file;
	}

	/**
	 * Download the personaliced application form
	 *
	 * @param string $nation =null
	 * @param int $year =null defaults to $this->license_year
	 * @param int $GrpId =null category to apply for
	 */
	function licenseform($nation=null,$year=null,$GrpId=null)
	{
		if (is_null($year)) $year = $_GET['license_year'] ? $_GET['license_year'] : $this->license_year;
		if (is_null($nation) && $_GET['license_nation']) $nation = $_GET['license_nation'];
		if (is_null($GrpId) && $_GET['license_cat']) $GrpId = $_GET['license_cat'];

		if (!$this->athlete->read($_GET) ||
			!($form = file_get_contents($this->license_form_name($nation,$year,$GrpId))))
		{
			common::egw_exit();
		}
		$egw_charset = translation::charset();
		$replace = array();
		foreach($this->athlete->data as $name => $value)
		{
			if (is_array($value)) continue;
			// the rtf seems to use iso-8859-1
			$replace['$$'.$name.'$$'] = $value = translation::convert($value,$egw_charset,'iso-8859-1');
		}
		$file = 'License '.$year.' '.$this->athlete->data['vorname'].' '.$this->athlete->data['nachname'].'.rtf';
		html::content_header($file,'text/rtf');
		echo str_replace(array_keys($replace),array_values($replace),$form);
		common::egw_exit();
	}
}
