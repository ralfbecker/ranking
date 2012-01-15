<?php
/**
 * EGroupware digital ROCK Rankings - athletes UI
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2006-12 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$
 */

require_once(EGW_INCLUDE_ROOT.'/ranking/inc/class.boranking.inc.php');
require_once(EGW_INCLUDE_ROOT.'/ranking/inc/class.ranking_athlete.inc.php');	// for ACL_DENY_*

class ranking_athlete_ui extends boranking
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
	 * @var array $acl_deny_lables
	 */
	var $acl_deny_labels = array();
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
	const MINIMUM_AGE = 5;

	function __construct()
	{
		parent::__construct();

		$this->tmpl = new etemplate;

		$this->acl_deny_labels = array(
			ACL_DENY_BIRTHDAY	=> lang('birthday, shows only the year'),
			ACL_DENY_EMAIL		=> lang('email'),
			ACL_DENY_PHONE		=> lang('phone'),
			ACL_DENY_FAX		=> lang('fax'),
			ACL_DENY_CELLPHONE	=> lang('cellphone'),
			ACL_DENY_STREET		=> lang('street, postcode'),
			ACL_DENY_CITY		=> lang('city'),
			ACL_DENY_PROFILE	=> lang('complete profile'),
		);
	}

	/**
	 * Edit an athlete
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
				list($license_nation) = each($this->license_nations);
			}
			if ($this->athlete->read($_GET,'',$this->license_year,$license_nation))
			{
				// read the athletes results
				$this->athlete->data['comp'] = $this->result->read(array('PerId' => $this->athlete->data['PerId'],'platz > 0'));
				if ($this->athlete->data['comp']) array_unshift($this->athlete->data['comp'],false);	// reindex with 1
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
				if ($this->only_nation_athlete) $this->athlete->data['nation'] = $this->only_nation_athlete;
				if (!in_array('NULL',$this->athlete_rights)) $nations = array_intersect_key($nations,array_flip($this->athlete_rights));
				//using athlete_rights_no_judge (and NOT athlete_rights) to check if we should look for federation rights, as otherwise judges loose their regular federation rights
				if (!$this->athlete_rights_no_judge && ($grants = $this->federation->get_user_grants()))
				{
					$feds_with_grants = array();
					foreach($grants as $fed_id => $rights)
					{
						if ($rights && EGW_ACL_ATHLETE)
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
			// we have no edit-rights for that nation
			if (!$this->acl_check_athlete($this->athlete->data))
			{
				$view = true;
			}
			$content['referer'] = preg_match('/menuaction=([^&]+)/',$_SERVER['HTTP_REFERER'],$matches) ?
				$matches[1] : 'ranking.ranking_athlete_ui.index';

			if ($content['referer'] == 'ranking.uiregistration.add' || $_GET['apply_license'])
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
			foreach(array('comp','last_comp','license') as $name)
			{
				$this->athlete->data[$name] = $content['athlete_data'][$name];
			}
			$old_geb_date = $this->athlete->data['geb_date'];

			$this->athlete->data_merge($content);
			$this->athlete->data['acl_fed_id'] = (int)$content['acl_fed_id']['fed_id'];
			//echo "<br>ranking_athlete_ui::edit: athlete->data ="; _debug_array($this->athlete->data);

			if (($content['save'] || $content['apply']) || $content['apply_license'])
			{
				if ($this->acl_check_athlete($this->athlete->data))
				{
					if (!$this->athlete->data['rkey'])
					{
						$this->athlete->generate_rkey();
					}
					if ($old_geb_date && !$this->athlete->data['geb_date'] && !$this->is_admin)
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
					elseif ($this->athlete->save())
					{
						$msg .= lang('Error: while saving !!!');
					}
					else
					{
						$msg .= lang('%1 saved',lang('Athlete'));

						if (is_array($content['foto']) && $content['foto']['tmp_name'] && $content['foto']['name'] && is_uploaded_file($content['foto']['tmp_name']))
						{
							//_debug_array($content['foto']);
							list($width,$height,$type) = getimagesize($content['foto']['tmp_name']);
							if ($type != 2)
							{
								$msg .= ($msg ? ', ' : '') . lang('Uploaded picture is no JPEG !!!');
							}
							else
							{
								if ($height > 250 && ($src = @imagecreatefromjpeg($content['foto']['tmp_name'])))	// we need to scale the picture down
								{
									$dst_w = (int) round(250.0 * $width / $height);
									//echo "<p>{$content['foto']['name']}: $width x $height ==> $dst_w x 250</p>\n";
									$dst = imagecreatetruecolor($dst_w,250);
									if (imagecopyresampled($dst,$src,0,0,0,0,$dst_w,250,$width,$height))
									{
										imagejpeg($dst,$content['foto']['tmp_name']);
										$msg .= ($msg ? ', ' : '') . lang('Picture resized to %1 pixel',$dst_w.' x 250');
									}
									imagedestroy($src);
									imagedestroy($dst);
								}
								$msg .= ($msg ? ', ' : '') . ($this->athlete->attach_picture($content['foto']['tmp_name']) ?
									lang('Picture attached') : lang('Error attaching the picture'));
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
									$this->athlete->set_license($content['license_year'],'a',null,$content['license_nation']);
									$msg .= ', '.lang('Applied for a %1 license',$content['license_year'].' '.
										(!$content['license_nation'] || $content['license_nation'] == 'NULL' ?
										lang('international') : $content['license_nation']));
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
								));
								$js .= "window.location='$link';";
							}
						}
						// change of license status, requires athlete rights for the license-nation
						elseif ($content['athlete_data']['license'] != $content['license'] &&
							$this->acl_check($content['license_nation'],EGW_ACL_ATHLETE))	// you need int. athlete rights
						{
							$this->athlete->set_license($content['license_year'],$content['license'],null,$content['license_nation']);
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
		$content = $this->athlete->data + array(
			'msg' => $msg,
			'is_admin' => $this->is_admin,
			'tabs' => $content['tabs'],
			'foto' => $this->athlete->picture_url().'?'.time(),
			'license_year' => $content['license_year'],
			'license_nation' => $content['license_nation'],
			'referer' => $content['referer'],
			'merge_to' => $content['merge_to'],
		);
		switch($content['license_nation'])
		{
			case 'SUI':
				$content['license_msg'] = 'Wir haben die Lizenzanfrage für diese/n Athleten/in erhalten. Deinem Regionalzentrum wird Ende Saison eine Rechnung über den Gesamtbetrag der Lizenzkosten zugestellt.\nDie Lizenzen werden euch vor dem ersten Wettkampf und danach nach Bedarf zugeschickt.\n\nFortfahren?\n\nNous avons bien reçu la demande de licence pour cet/te athlète, ton centre régional recevra une facture du coût total des licences à la fin de la saison.\nLes licences vous seront envoyées avant la première compétition et ensuite selon demande.\n\nContinuer';
				break;

			default:
				$content['license_msg'] = lang('You need to mail the downloaded AND signed form to the office! Please check if you filled out ALL fields (you may hide some via ACL from public viewing). Continue');

		}
		$content['acl_fed_id'] = array('fed_id' => $this->athlete->data['acl_fed_id']);
		$sel_options = array(
			'nation' => $nations,
			'sex'    => $this->genders,
			'acl'    => $this->acl_deny_labels,
			'license'=> $this->license_labels,
			'fed_id' => !$content['nation'] ? array(lang('Select a nation first')) :
				$this->athlete->federations($content['nation'],false,$feds_with_grants ? array('fed_id' => $feds_with_grants) : array()),
			'license_nation' => ($license_nations = $this->license_nations),
		);
		$edit_rights = $this->acl_check_athlete($this->athlete->data);
		$readonlys = array(
			'delete' => !$this->athlete->data['PerId'] || !$edit_rights || $this->athlete->data['comp'],
			'nation' => !!$this->only_nation_athlete,
			'edit'   => $view || !$edit_rights,
			// show apply license only if status is 'n' or 'a' AND user has right to apply for license
			'apply_license' => in_array($content['license'],array('s','c')) ||
				!$this->acl_check_athlete($this->athlete->data,EGW_ACL_ATHLETE,null,$content['license_nation']),
			// to simply set the license field, you need athlete rights for the nation of the license
			'license'=> !$this->acl_check($content['license_nation'],EGW_ACL_ATHLETE),
			// for now disable merge, if user is no admin: !$this->is_admin || (can be removed later)
			'merge' => !$this->is_admin || !$edit_rights || !$this->athlete->data['PerId'],
			'merge_to' => !$this->is_admin || !$edit_rights || !$this->athlete->data['PerId'],
		);
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
			foreach($this->athlete->data as $name => $val)
			{
				$readonlys[$name] = true;
			}
			$readonlys['acl_fed_id[fed_id]'] = $readonlys['foto'] = $readonlys['delete'] = $readonlys['save'] = $readonlys['apply'] = true;
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
			// the name of an athlete who has not climbed for more then one year
			// (gard against federations "reusing" athlets)
			if ($this->athlete->data['PerId'] && ranking_athlete::age($this->athlete->data['last_comp']) > 1 &&
				!($this->is_admin || $this->edit_rights[$this->athlete->data['nation']] || $this->edit_rights['NULL']))
			{
				$readonlys['vorname'] = $readonlys['nachname'] = true;
			}
			// forbid athlete selfservice to change certain fields
			if ($this->is_selfservice() == $this->athlete->data['PerId'])
			{
				$readonlys['vorname'] = $readonlys['nachname'] = $readonlys['geb_date'] = true;
				$readonlys['fed_id'] = $readonlys['a2f_start'] = true;
				// 'til we have some kind of review mechanism
				$readonlys['tabs']['pictures'] = true;
			}
		}
		if ($js)
		{
			$GLOBALS['egw']->js->set_onload($js);
		}
		$GLOBALS['egw_info']['flags']['app_header'] = lang('ranking').' - '.lang($view ? 'view %1' : 'edit %1',lang('Athlete'));
		$this->tmpl->read('ranking.athlete.edit');
		$this->tmpl->exec('ranking.ranking_athlete_ui.edit',$content,
			$sel_options,$readonlys,array(
				'athlete_data' => $this->athlete->data,
				'view' => $view,
				'referer' => $content['referer'],
				'row' => (int)$_GET['row'] ? (int)$_GET['row'] : $content['row'],
				'license_year' => $content['license_year'],
				'license_nation' => $content['license_nation'],
				'old_license_nation' => $content['license_nation'],
				'acl_fed_id' => $content['acl_fed_id'],
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
	 * @param array $content
	 * @param string $msg
	 */
	function index($content=null,$msg='')
	{
		if ($_GET['delete'] || is_array($content['nm']['rows']['delete']))
		{
			if (is_array($content['nm']['rows']['delete']))
			{
				list($id) = each($content['nm']['rows']['delete']);
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
			foreach($grants as $fed_id => $rights)
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
		),$readonlys);
	}

	/**
	 * Get the name of the license form, depending on nation and year
	 *
	 * @param string $nation=null
	 * @param int $year=null defaults to $this->license_year
	 * @return string path on server
	 */
	function license_form_name($nation=null,$year=null)
	{
		if (is_null($year)) $year = $this->license_year;
		if ($nation == 'NULL') $nation = null;

		return $_SERVER['DOCUMENT_ROOT'].'/'.$year.'/license_'.$year.$nation.'.rtf';
	}

	/**
	 * Download the personaliced application form
	 *
	 * @param string $nation=null
	 * @param int $year=null defaults to $this->license_year
	 */
	function licenseform($nation=null,$year=null)
	{
		if (is_null($year)) $year = $_GET['license_year'] ? $_GET['license_year'] : $this->license_year;
		if (is_null($nation) && $_GET['license_nation']) $nation = $_GET['license_nation'];

		if (!$this->athlete->read($_GET) ||
			!($form = file_get_contents($this->license_form_name($nation,$year))))
		{
			common::egw_exit();
		}
		$egw_charset = $GLOBALS['egw']->translation->charset();
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

	/**
	 * Athlete selfservie: edit profile, register for competitions
	 *
	 * @param int $PerId
	 * @param string $action 'profile'
	 */
	function selfservice($PerId, $action)
	{
		unset($this->athlete->acl2clear[ACL_DENY_EMAIL]);	// otherwise anon user never get's email returned!
		if (!$PerId || !($athlete = $this->athlete->read($PerId)))
		{
			throw new egw_exception_wrong_userinput("Athlete NOT found!");
		}
		static $nation2lang = array(
			'AUT' => 'de',
			'GER' => 'de',
			'SUI' => 'de',
		);
		$lang = isset($nation2lang[$athlete['nation']]) ? $nation2lang[$athlete['nation']] : 'en';
		if (translation::$userlang !== $lang)
		{
			translation::$userlang = $lang;
			translation::init();
		}
		if (!$this->acl_check_athlete($athlete) && !$this->is_selfservice() == $PerId &&
			$this->selfservice_auth($athlete, $action) != $PerId)
		{
			return;
		}
		switch($action)
		{
			case 'profile':
				egw::redirect_link('/index.php',array(
					'menuaction' => 'ranking.ranking_athlete_ui.edit',
					'PerId' => $PerId,
				));
				break;

			default:
				throw new egw_exception_wrong_parameter("Unknown action '$action'!");
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
	 * Athlete selfservice: password check and recovery
	 *
	 * @param array $athlete
	 * @param string $action
	 * @return int PerId if we are authenticated for it nor null if not
	 */
	private function selfservice_auth(array $athlete, $action)
	{
		echo "<style type='text/css'>
	body {
		margin: 10px !important;
	}
	p, td {
		font-size: 14px;
	}
	p.error {
		color: red;
	}
</style>\n";
		echo "<h1>$athlete[vorname] $athlete[nachname] ($athlete[nation])</h1>\n";

		$recovery_link = egw::link('/ranking/athlete.php', array(
			'PerId' => $athlete['PerId'],
			'action'  => 'recovery',
		));
		if (empty($athlete['password']) || in_array($action,array('recovery','password','set')))
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
				// mail hash to athlete
				//echo "<p>*** TEST *** <a href='$link'>Click here</a> to set a password *** TEST ***</p>\n";
				try {
					$template = EGW_SERVER_ROOT.'/ranking/doc/reset-password-mail.txt';
					self::mail("$athlete[vorname] $athlete[$nachname] <$athlete[email]>",
						$athlete+array(
							'LINK' => preg_match('/\.txt$/',$template) ? $link : '<a href="'.$link.'">'.$link.'<a>',
							'SERVER_NAME' => $_SERVER['SERVER_NAME'],
							'RECOVERY_TIMEOUT' => self::RECOVERY_TIMEOUT/3600,	// in hours (not sec)
						), $template);
					echo "<p>".lang('An EMail with instructions how to (re-)set your password has been sent to you.')."</p>\n".
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
								echo "<h1>".lang('Your new password is now active.')."</h1>\n";
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
				echo "<p><a href='$recovery_link'>".lang('Click here to have a mail send to your stored EMail address with instructions how to set your password.')."</a></p>\n";
			}
		}
		else
		{
			if (!empty($_POST['password']))
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

					return $athlete['PerId'];	// we are now authenticated for $athlete['PerId']
				}
			}
			$link = egw::link('/ranking/athlete.php', array(
				'PerId' => $athlete['PerId'],
				'action'  => $action,
			));
			echo "<p>".lang("Please enter your password to log in or %1click here%2, if you forgot it.","<a href='$recovery_link'>","</a>")."</p>\n";
			echo "<form action='$link' method='POST'>\n";
			echo "<p>".lang('Password')." <input type='password' name='password' />\n";
			echo "<input type='submit' value='".lang('Login')."' /></p>\n";
			echo "</form>\n";
		}
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
