<?php
/**
 * EGroupware digital ROCK Rankings - athletes UI
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2006-19 by Ralf Becker <RalfBecker@digitalrock.de>
 */

use EGroupware\Api;
use EGroupware\Api\Framework;
use EGroupware\Api\Egw;
use EGroupware\Api\Vfs;


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
		/* dont want to make it to convienient to disable display of name
		ranking_athlete::ACL_DENY_ALL => array(
			'label' => 'Hide all: name and profile page',
			'title' => 'Name will not be shown to website visitors and they will be told that athlete asked to no longer show his profile page.'
		),*/
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
		ranking_athlete::ACL_DENY_ALL	    => 'all: profile and name',
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
		if ($_GET['rkey'] || $_GET['PerId'])
		{
			if (!in_array($license_nation = strip_tags($_GET['license_nation']),$this->license_nations))
			{
				$license_nation = key($this->license_nations);
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
				if (!$this->is_admin && !$this->athlete_rights_no_judge &&
					($grants = $this->federation->get_user_grants()))
				{
					$feds_with_grants = array();
					foreach($grants as $fed_id => $rights)
					{
						if ($rights & self::ACL_ATHLETE)
						{
							$feds_with_grants[] = $fed_id;
						}
					}
					if ($feds_with_grants)
					{
						// if we have a/some feds the user is responsible for get the first (and only) nation
						$nations = $this->federation->query_list('nation','nation',array('fed_id' => $feds_with_grants));
						if (count($nations) != 1) throw new Api\Exception\AssertionFailed('Fed grants only implemented for a single nation!');
						$this->only_nation_athlete = key($nations);
						$this->athlete->data['nation'] = $this->only_nation_athlete;
						// SUI Regionalzentren
						if ($this->only_nation_athlete == 'SUI' && count($feds_with_grants) == 1)
						{
							list($this->athlete->data['acl_fed_id']) = $feds_with_grants;
							$this->athlete->data['fed_id'] = key($this->athlete->federations($this->only_nation_athlete,true));	// set the national federation
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
				Framework::window_close(lang('Permission denied'));
			}
			// we have no edit-rights for that nation
			if (!empty($this->athlete->data['PerId']) && !$this->acl_check_athlete($this->athlete->data))
			{
				$view = true;
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

			$button = key($content['button']);
			unset($content['button']);

			$this->athlete->data_merge($content);
			// deal with custom ACL
			if ($content['acl'] === 'custom')
			{
				$this->athlete->data['acl'] = $content['custom_acl'];
			}
			else
			{
				$this->athlete->data['acl'] = array();
				for($acl=1; $acl <= ranking_athlete::ACL_DENY_ALL; $acl *= 2)
				{
					if ($content['acl'] & $acl) $this->athlete->data['acl'][] = $acl;
				}
				$content['custom_acl'] = $this->athlete->data['acl'];
			}
			$this->athlete->data['acl_fed_id'] = (int)$content['acl_fed_id']['fed_id'];
			//echo "<br>ranking_athlete_ui::edit: athlete->data ="; _debug_array($this->athlete->data);

			// download (latest) consent document
			if ($content['download_consent'] && ($path = $this->athlete->consent_document()) &&
				($content = fopen($path, 'r')))
			{
				Api\Header\Content::safe($content, $path);
				if (is_resource($content))
				{
					fpassthru($content);
					fclose($content);
				}
				else
				{
					echo $content;
				}
				exit;
			}

			if (in_array($button, ['save', 'apply', 'apply_license']))
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
						if ($content['upload_consent'])
						{
							$msg .= ($msg ? ', ' : '') . ($this->athlete->attach_consent($content['upload_consent']) ?
								lang('Consent document attached') : lang('Error attaching consent document'));
						}
						if ($button === 'apply_license')
						{
							if ($content['athlete_data']['license'] == 's')
							{
								$msg .= ', '.lang('Athlete is suspended !!!');
								$required_missing = true;	// to not download the form
							}
							elseif(!$this->acl_check_athlete($this->athlete->data,self::ACL_ATHLETE,null,$content['license_nation']))
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
										Api\Etemplate::set_validation_error($name,lang('Field must not be empty !!!'));
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
							if (!$required_missing && $this->license_form_name(
								$content['license_nation'], $content['license_year'],
								$content['license_cat'], $this->athlete->data['PerId']))
							{
								$this->licenseform($content['license_nation'], $content['license_year'],
									$content['license_cat'], $this->athlete->data['PerId']);
							}
						}
						// change of license status, requires athlete rights for the license-nation
						elseif (($content['athlete_data']['license'] != $content['license'] ||
							$content['athlete_data']['license_cat'] != $content['license_cat']) &&
							$this->acl_check($content['license_nation'],self::ACL_ATHLETE))	// you need int. athlete rights
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
			}
			if ($button === 'pw_mail')
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
			if ($button === 'delete' && $this->acl_check_athlete($this->athlete->data) &&
				!$this->athlete->has_results($this->athlete->data['WetId']))
			{
				if ($this->athlete->delete(array('PerId' => $this->athlete->data['WetId'])))
				{
					Api\Framework::refresh_opener(lang('%1 deleted', lang('Athlete')), 'ranking', $this->cup->data['SerId'], 'delete');
					Api\Framework::window_close();
				}
				else
				{
					$msg = lang('Error: deleting %1 !!!',lang('Athlete'));
					$button = 'apply';	// do not exit dialog
				}
			}
			if (in_array($button, ['save', 'delete', 'cancel']))
			{
				if ($this->is_selfservice())
				{
					Egw::redirect_link('/ranking/athlete.php');
				}
				Framework::window_close();
			}
		}
		$shown_msg = null;
		if (true) $content = array(
			'acl' => !empty($content['acl']) ? $content['acl'] : $this->athlete->data['acl'],
		) + $this->athlete->data + array(
			'msg' => $msg,
			'profile_status' => $this->athlete->profile_hidden($this->athlete->data, $shown_msg),
			'is_admin' => $this->is_admin,
			'tabs' => str_replace('ranking.athlete.edit.', '', $content['tabs']),
			'foto' => $this->athlete->picture_url().'?'.time(),
			'foto2' => $this->athlete->picture_url(null, 2).'?'.time(),
			'license_year' => $content['license_year'],
			'license_nation' => $content['license_nation'],
			'license_cat' => $content['license_cat'],
		);
		// give explicit message if profile is hidden or why it is shown
		if (empty($content['profile_status']))
		{
			$content['profile_status'] = lang('Profile shown').': '.$shown_msg;
		}
		elseif(empty($msg))
		{
			$content['msg'] = $content['profile_status'] = lang('Profile hidden').': '.$content['profile_status'];
		}
		// initialise ACL selectbox
		$content['custom_acl'] = $this->athlete->data['acl'];
		if ($content['acl'] !== 'custom')
		{
			$content['acl'] = 0;
			foreach($this->athlete->data['acl'] as $acl)
			{
				$content['acl'] |= $acl;
			}
			if (in_array(ranking_athlete::ACL_DENY_ALL, $content['custom_acl']))
			{
				//$content['acl'] = ranking_athlete::ACL_DENY_ALL;
				$content['acl'] = 'custom';
			}
			elseif (in_array(ranking_athlete::ACL_DENY_PROFILE, $content['custom_acl']))
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
			'button[delete]' => !$this->athlete->data['PerId'] || !$edit_rights || $this->athlete->has_results(),
			'nation' => !!$this->only_nation_athlete,
			'pw_mail'=> !$content['email'],
			// show apply license only if status is 'n' or 'a' AND user has right to apply for license
			'button[apply_license]' => in_array($content['license'],array('s','c')) ||
				!$this->acl_check_athlete($this->athlete->data,self::ACL_ATHLETE,null,$content['license_nation']),
			// to simply set the license field, you need athlete rights for the nation of the license
			'license'=> !$this->acl_check($content['license_nation'],self::ACL_ATHLETE,null,false,null,true),	// true=no judge rights
			'kader'=> !$this->acl_check($content['license_nation'],self::ACL_ATHLETE,null,false,null,true),	// true=no judge rights
			// for now disable merge, if user is no admin: !$this->is_admin || (can be removed later)
			'button[merge]' => !$this->is_admin || !$edit_rights || !$this->athlete->data['PerId'],
			'custom_acl' => !($this->is_admin || $edit_rights || !$this->athlete->data['PerId']) || $content['acl'] !== 'custom',
			'download_consent' => !$this->athlete->data['PerId'] || !$edit_rights && !$this->is_admin || !$this->athlete->consent_document(),
		);
		// only allow to set license-category when applying or having rights to change license
		$readonlys['license_cat'] = $readonlys['apply_license'] && $readonlys['license'];

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
				$readonlys['button[delete]'] = $readonlys['button[save]'] = $readonlys['button[apply]'] = true;
		}
		else
		{
			if (!$this->athlete->data['PerId'] || $this->athlete->data['last_comp'])
			{
				$readonlys['button[delete]'] = true;
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
			$this->setup_history($content, $sel_options, $readonlys);
		}
		$GLOBALS['egw_info']['flags']['app_header'] = lang('ranking').' - '.lang($view ? 'view %1' : 'edit %1',lang('Athlete'));
		$tmpl = new Api\Etemplate('ranking.athlete.edit');
		// selfservice needs old idots mobile support currently, so disable new 16.1 mobile support
		if ($this->is_selfservice())
		{
			$GLOBALS['egw_info']['flags']['deny_mobile'] = true;

			// need everything (eg. images), as we have no main-window running
			$GLOBALS['egw_info']['flags']['js_link_registry'] = true;

			// for self-service, do NOT close the window, but submit the form, which redirects back to athlete.php
			Api\Etemplate::setElementAttribute('button[cancel]', 'onclick', 'return true;');	// '' does NOT work
		}
		$tmpl->exec('ranking.ranking_athlete_ui.edit',$content,
			$sel_options,$readonlys,array(
				'athlete_data' => $this->athlete->data,
				'view' => $view,
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
	 * @param boolean $ids_only =false true: return only ids, not full rows
	 */
	function get_rows(&$query_in, &$rows, &$readonlys, $ids_only=false)
	{
		//echo "ranking_athlete_ui::get_rows() query="; _debug_array($query_in);
		if (!$query_in['csv_export'])	// only store state if NOT called as csv export
		{
			Api\Cache::setSession('athlete_state', 'ranking', $query_in);
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
				$license_nation = key($this->license_nations);
			}
		}
		$query['col_filter']['license_nation'] = $license_nation;

		$total = $this->athlete->get_rows($query, $rows, $readonlys,
			(int)$query['filter'] ? (int)$query['filter'] : true, false, $ids_only);

		//_debug_array($rows);

		if ($query['col_filter']['nation'] == 'SUI')
		{
			$feds = $this->federation->federations($query['col_filter']['nation']);
		}
		$readonlys = array();
		foreach($rows as &$row)
		{
			if ($ids_only)
			{
				$row = $row['PerId'];
				continue;
			}
			if (!$row['last_comp'] && $this->acl_check_athlete($row))
			{
				$row['class'] = 'AllowDelete';
			}
			if ($row['license'] == 'n' && $this->acl_check_athlete($row, self::ACL_ATHLETE, null, $license_nation ? $license_nation: 'NULL'))
			{
				$row['class'] .= ' ApplyLicense';
			}
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
		if ($ids_only) return $total;

		$sel_options['license_nation'] = $this->license_nations;
		$rows['sel_options'] =& $sel_options;
		$rows['license_nation'] = !$license_nation ? 'NULL' : $license_nation;
		$rows['license_year'] = $this->license_year;
		$rows['license_cat'] = $query['filter'];
		// dont show license column for nation without licenses or if a license-filter is selected
		$rows['no_license'] = $query['filter2'] != '' || !isset($sel_options['license_nation'][$rows['license_nation']]);
		// dont show license filter for a nation without license
		$query_in['no_filter2'] = !isset($sel_options['license_nation'][$rows['license_nation']]);

		// actions, specially license depends on filters
		$query_in['actions'] = $this->get_actions(array(
			'license_nation' => !$license_nation ? 'NULL' : $license_nation,
			'license_year' => $this->license_year,
			'license_cat' => $query['filter'],
		));

		return $total;
	}

	/**
	 * List existing Athletes
	 *
	 * @param array $_content =null
	 * @param string $msg =''
	 */
	function index($_content=null, $msg='')
	{
		if ($_content && $_content['nm']['action'] && ($_content['nm']['selected'] || $_content['nm']['select_all']))
		{
			try {
				$msg = $this->action($_content['nm']['action'], $_content['nm']['selected'], $_content['nm']['select_all']);
			}
			catch (Exception $ex) {
				Api\Framework::message($ex->getMessage(), 'error');
			}
		}
		if (!empty($msg)) Api\Framework::message($msg);

		$content = ['nm' => Api\Cache::getSession('athlete_state', 'ranking')];

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
				'default_cols' => '!kader',
				'dataStorePrefix' => 'ranking_athlete',
				'row_id'         => 'PerId',
			);
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

		$tmpl = new Api\Etemplate('ranking.athlete.index');
		$GLOBALS['egw_info']['flags']['app_header'] = lang('ranking').' - '.lang('Athletes');
		$this->set_ui_state();

		$tmpl->exec('ranking.ranking_athlete_ui.index',$content,array(
			'nation' => $this->athlete->distinct_list('nation'),
			'sex'    => array_merge($this->genders,array(''=>'')),	// no none
			'filter2'=> array('' => 'All')+$this->license_labels,
			'license'=> $this->license_labels,
			'license_cat' => $this->cats->names(array(), 0),
		));
	}

	/**
	 * Run given action on selected athletes
	 *
	 * @param string $action action to run
	 * @param array $selected PerId's
	 * @param boolean $select_all true: use all rows from current state/session
	 * @return string success message
	 * @throws Exception with error message
	 */
	protected function action($action, array $selected, $select_all)
	{
		$success = $failed = 0;
		if ($select_all && $action !== 'export')
		{
			// get the whole selection
			$query = Api\Cache::getSession('athlete_state', 'ranking');

			@set_time_limit(0);			// switch off the execution time limit, as it's for big selections to small
			$query['num_rows'] = -1;	// all
			$query['csv_export'] = true;
			$readonlys = null;
			$this->get_rows($query, $selected, $readonlys, true);	// true = only return the id's
		}
		foreach($selected as $id)
		{
			switch($action)
			{
				case 'delete':
					if ($this->athlete->read(array('PerId' => $id)) &&
						!$this->acl_check_athlete($this->athlete->data))
					{
						++$failed;
					}
					elseif ($this->athlete->has_results($id))
					{
						++$failed;
					}
					elseif (!$this->athlete->delete(array('PerId' => $id)))
					{
						++$failed;
					}
					else
					{
						++$success;
					}
					$action_msg = lang('deleted');
					break;

				case 'merge':
					if (!$this->is_admin)
					{
						throw new Execption(lang('Permission denied !!!'));
					}
					if (count($selected) !== 2)
					{
						throw new Exception(lang('Merges TWO athletes in the first selected one!'));
					}
					return lang('Athlete including %1 results merged.',
						$this->merge_athlete($selected[1], $id));

				case 'export';
					if (!($this->is_admin || count($this->athlete_rights) || $this->federation->get_user_grants()))
					{
						throw new Execption(lang('Permission denied !!!'));
					}
					$this->export($selected, $select_all);
			}
		}
		if ($failed)
		{
			throw new Exception(lang('%1 athlete(s) %2, %3 failed because of missing permissions or existing results!',
				$success, $action_msg, $failed));
		}
		return lang('%1 athlete(s) %2', $success, $action_msg);
	}

	/**
	 * Return actions for cup list
	 *
	 * @param array $cont values for keys license_(nation|year|cat)
	 * @return array
	 */
	function get_actions(array $cont)
	{
		$actions =array(
			'edit' => array(
				'caption' => 'Edit',
				'default' => true,
				'allowOnMultiple' => false,
				'url' => 'menuaction=ranking.ranking_athlete_ui.edit&PerId=$id',
				'popup' => '900x470',
				'group' => $group=0,
			),
			'add' => array(
				'caption' => 'Add',
				'url' => 'menuaction=ranking.ranking_athlete_ui.edit',
				'popup' => '900x470',
				'disabled' => !$this->is_admin && !$this->athlete_rights && !$this->federation->get_user_grants(),
				'group' => $group,
			),
			'license' => array(
				'caption' => 'Apply for license',
				'allowOnMultiple' => false,
				'url' => "menuaction=ranking.ranking_athlete_ui.edit&PerId=\$id&license_nation=$cont[license_nation]&license_year=$cont[license_year]&license_cat=$cont[license_cat]",
				'popup' => '900x470',
				'enableClass' => 'ApplyLicense',
				'group' => $group,
			),
			'export' => array(
				'caption' => 'CSV Export',
				'allowOnMultiple' => true,
				'disabled' => !$this->is_admin && !$this->athlete_rights && !$this->federation->get_user_grants(),
				'hint' => 'Download a CSV with selected athletes',
				'postSubmit' => true,	// download needs post submit (not Ajax) to work
				'group' => $group=3,
			),
			'merge' => array(
				'caption' => 'Merge',
				'allowOnMultiple' => 2,
				'disabled' => !$this->is_admin,
				'hint' => 'Merges TWO athletes in the first selected one!',
				'confirm' => 'ATTENTION: merging can NOT be undone! Really want to merge?',
				'group' => $group=4,
			),
			'delete' => array(
				'caption' => 'Delete',
				//'allowOnMultiple' => false,
				'confirm' => 'Delete this athlete',
				'enableClass' => 'AllowDelete',
				'group' => $group=5,
			),
		);

		$prefs =& $GLOBALS['egw_info']['user']['preferences']['ranking'];
		$actions['documents'] = ranking_merge::document_action(
			$prefs['document_dir'], ++$group, 'Insert in document', 'document_',
			$prefs['default_document']
		);

		return $actions;
	}

	/**
	 * Download selected athletes as CSV file
	 *
	 * @param array $selected
	 * @param boolean $select_all =false true: download whole selection
	 */
	function export(array $selected, $select_all=false)
	{
		// get the whole selection
		$query = Api\Cache::getSession('athlete_state', 'ranking');
		@set_time_limit(0);			// switch off the execution time limit, as it's for big selections to small

		$csv_fields = array(
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
		$charset = Api\Translation::charset();
		$csv_charset = $GLOBALS['egw_info']['user']['preferences']['common']['csv_charset'];
		if (empty($csv_charset)) $csv_charset = 'iso-8859-1';

		Api\Header\Content::type('athletes.csv','text/comma-separated-values');
		echo Api\Translation::convert(implode(';', $csv_fields), $charset, $csv_charset)."\n";

		$query['csv_export'] = true;	// to not store state;
		if (!$select_all)
		{
			$query['col_filter']['PerId'] = $selected;
		}
		$rows = $readonlys = null;
		$query['start'] = 0;
		$query['num_rows'] = 2;
		do {
			$total = $this->get_rows($query, $rows, $readonlys);
			foreach($rows as $key => $row)
			{
				if (!is_int($key)) continue;

				$values = [];
				foreach(array_keys($csv_fields) as $name)
				{
					$values[$name] = (string)$row[$name];
					if (strpos($values[$name], ';') !== false)
					{
						$values[$name] = '"'.str_replace('"', '""', $values[$name]).'"';
					}
				}
				echo Api\Translation::convert(implode(';', $values), $charset, $csv_charset)."\n";
				$query['start']++;
			}
		}
		while ($query['start'] < $total);
		exit;
	}

	/**
	 * Supported extensions for merge of athlete-license-form
	 *
	 * @var array[string]
	 */
	protected static $merge_extensions = ['.odt', '.docx', '.rtf'];

	/**
	 * Checks if a file with a supported extension for merge exists
	 *
	 * @param string $path without extension
	 * @param string|array $extensions =null default all allowed self::$merge_extensions
	 * @param string& $ext =null on return: extension for returned path
	 * @return full path incl. extension or null
	 */
	static function merge_file_exists($path, $extensions=null, &$ext=null)
	{
		foreach($extensions ? (array)$extensions : self::$merge_extensions as $ext)
		{
			if (file_exists($path.$ext)) return $path.$ext;
			//error_log(__METHOD__."('$path', ".array2string($extensions).") $path$ext NOT found");
		}
		return null;
	}

	/**
	 * Get the name of the license form, depending on nation and year
	 *
	 * Licenseforms are in PDF directory under following names:
	 * - $year/license_$year$nation_$catrkey.(odt|docx|rtf) (highest priority)
	 * - $year/license_$year$nation_minor.(odt|docx|rtf) (for minors: age today < 18)
	 * - $year/license_$year$nation.(odt|docx|rtf)
	 *
	 * @param string $nation =null
	 * @param int $year =null defaults to $this->license_year
	 * @param int $GrpId =null category to apply for
	 * @param int $PerId =null optional PerId to use minor-form
	 * @param string|array $extensions =null default all allowed self::$merge_extensions
	 * @param boolean $vfs_prefix =true return Vfs::PREFIX, default yes
	 * @param string& $ext =null on return: extension for returned path
	 * @return string|null full path on server or null if none found
	 */
	function license_form_name($nation=null, $year=null, $GrpId=null, $PerId=null, $extensions=null, $vfs_prefix=true, &$ext=null)
	{
		if (is_null($year)) $year = $this->license_year;
		if ($nation == 'NULL') $nation = null;

		$base = Vfs::PREFIX.$this->comp->vfs_pdf_dir;

		// if we have a PerId, check if athlete is a minor (to prefer minor-form over general/adult one)
		if (!empty($PerId) && ($PerId == $this->athlete->data['PerId'] || $this->athlete->read($PerId)))
		{
			$age = ranking_athlete::age($this->athlete->data['geb_date']);
			$minor = !empty($age) && $age < 18;
			//error_log(__METHOD__."('$nation', $year, $GrpId, $PerId) birthdate={$this->athlete->data['geb_date']} --> age=$age --> minor=".array2string($minor));
		}

		if ((!(int)$GrpId || !($cat = $this->cats->read($GrpId)) ||
				!($file=self::merge_file_exists($base.'/'.$year.'/license_'.$year.$nation.'_'.$cat['rkey'], $extensions, $ext))) &&
			(!$minor || !($file=self::merge_file_exists($base.'/'.$year.'/license_'.$year.$nation.'_minor', $extensions, $ext))))
		{
			$file = self::merge_file_exists($base.'/'.$year.'/license_'.$year.$nation, $extensions, $ext);
		}
		//error_log(__METHOD__."('$nation', $year, $GrpId, minor=$minor, ".array2string($extensions).") ext=$ext, returning ".array2string($file));
		return !isset($file) || $vfs_prefix ? $file : substr($file, strlen(Vfs::PREFIX));
	}

	/**
	 * Download the personaliced application form
	 *
	 * @param string $nation =null
	 * @param int $year =null defaults to $this->license_year
	 * @param int $GrpId =null category to apply for
	 * @param int $PerId =null PerId
	 */
	function licenseform($nation=null, $year=null, $GrpId=null, $PerId=null)
	{
		if (is_null($year)) $year = $_GET['license_year'] ? $_GET['license_year'] : $this->license_year;
		if (is_null($nation) && $_GET['license_nation']) $nation = $_GET['license_nation'];
		if (is_null($GrpId) && $_GET['license_cat']) $GrpId = $_GET['license_cat'];
		if (is_null($PerId)) $PerId = $_GET['PerId'];

		$ext = null;
		if ($this->athlete->read($PerId) &&
			($vfs_path = $this->license_form_name($nation, $year, $GrpId, $PerId, null, false, $ext)))
		{
			$merge = new ranking_merge();
			$file = 'License '.$year.' '.$this->athlete->data['vorname'].' '.
				$this->athlete->data['nachname'].$ext;
			// does NOT return, unless there is an error
			$err = $merge->download($vfs_path, $this->athlete->data['PerId'], $file);
		}
		header('HTTP/1.1 204 No Content');
		error_log(__METHOD__."('$nation', $year, $GrpId, $PerId) vfs_path=$vfs_path, merge-error: $err");
		exit();
	}

	protected function setup_history(&$content, &$sel_options, &$readonlys)
	{
		if (!$content['PerId'])
		{
			$readonlys['tabs']['history'] = true;
			return;
		}

		$content['history'] = array(
			'id'  => $content['PerId'],
			'app' => 'ranking',
			'status-widgets' => array(
				'sex' => $this->genders,
				'tel' => 'url-phone',
				'fax' => 'url-phone',
				'geb_date' => 'date',
				'email' => 'url-email',
				'homepage' => 'url',
				'mobil' => 'url-phone',
				'acl' => 'acl',
				'freetext' => 'freetext',
				'modified' => 'modified',
				'modifier' => 'modifier',
				'password' => 'password',
				'recover_pw_hash' => 'recover_pw_hash',
				'recover_pw_time' => 'recover_pw_time',
				'last_login' => 'last_login',
				'login_failed' => 'login_failed',
				'facebook' => 'facebook',
				'twitter' => 'twitter',
				'instagram' => 'instagram',
				'youtube' => 'youtube',
				'video_iframe' => 'video_iframe',
				'consent_time' => 'consent_time',
				'consent_ip' => 'consent_ip',
			),
		);
		$history_stati = array();
		$tracking = new ranking_tracking($this);
		foreach($tracking->field2history as $field => $history)
		{
			$history_stati[$history] = $tracking->field2label[$field];
		}
		unset($tracking);
		$sel_options['status'] = $history_stati;
	}
}
