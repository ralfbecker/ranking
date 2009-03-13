<?php
/**
 * eGroupWare digital ROCK Rankings - athletes UI
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2006-9 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$
 */

require_once(EGW_INCLUDE_ROOT.'/ranking/inc/class.boranking.inc.php');
require_once(EGW_INCLUDE_ROOT.'/ranking/inc/class.athlete.inc.php');	// for ACL_DENY_*
require_once(EGW_INCLUDE_ROOT.'/etemplate/inc/class.etemplate.inc.php');

class uiathletes extends boranking
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

	function uiathletes()
	{
		$this->boranking();

		$this->tmpl =& new etemplate;

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
	 * Edit a cup
	 *
	 * @param array $content
	 * @param string $msg
	 */
	function edit($content=null,$msg='',$view=false)
	{
		$tabs = 'contact|profile|freetext|other|pictures|results';
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
				if (!$this->athlete_rights && ($grants = $this->federation->get_user_grants()))
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
				$matches[1] : 'ranking.uiathletes.index';

			if ($content['referer'] == 'ranking.uiregistration.add' || $_GET['apply_license'])
			{
				$js = "document.getElementById('exec[apply_license]').click();";
			}
			if ($this->athlete->data['license'] == 's')
			{
				$msg = lang('Athlete is suspended !!!');
			}
			else
			{
				$msg = lang('Please use ONLY a first capital letter for names, do NOT capitalise the whole word!');
			}
		}
		else
		{
			$view = $content['view'];

			if (!$view && $this->only_nation_athlete) $content['nation'] = $this->only_nation_athlete;

			//echo "<br>uiathletes::edit: content ="; _debug_array($content);
			$this->athlete->init($content['athlete_data']);
			// reload license, if nation or year changed
			if ($content['old_license_nation'] != $content['license_nation'])
			{
				$content['athlete_data']['license'] = $content['license'] = $this->athlete->get_license($content['license_year'],$content['license_nation']);
			}
			$this->athlete->data['license'] = $content['athlete_data']['license'];
			$old_geb_date = $this->athlete->data['geb_date'];

			$this->athlete->data_merge($content);
			$this->athlete->data['acl_fed_id'] = (int)$content['acl_fed_id']['fed_id'];
			//echo "<br>uiathletes::edit: athlete->data ="; _debug_array($this->athlete->data);

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
										$content[$tabs] = 'contact';
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
									'menuaction' => 'ranking.uiathletes.licenseform',
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
					'menuaction' => $content['referer'],//'ranking.uiathletes.index',
					'msg' => $msg,
				)+($content['row'] ? array('row['.$content['row'].']' => $this->athlete->data['PerId']) : array()));
				if (!$required_missing) $js = "window.opener.location='$link'; $js";
			}
			if ($content['delete'])
			{
				$link = $GLOBALS['egw']->link('/index.php',array(
					'menuaction' => 'ranking.uiathletes.index',
					'delete' => $this->athlete->data['PerId'],
				));
				$js = "window.opener.location='$link';";
			}
			if ($content['save'] || $content['delete'] || $content['cancel'])
			{
				echo "<html><head><script>\n$js;\nwindow.close();\n</script></head></html>\n";
				$GLOBALS['egw']->common->egw_exit();
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
							'menuaction' => $content['referer'],//'ranking.uiathletes.index',
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
			$tabs => $content[$tabs],
			'foto' => $this->athlete->picture_url().'?'.time(),
			'license_year' => $content['license_year'],
			'license_nation' => $content['license_nation'],
			'referer' => $content['referer'],
			'merge_to' => $content['merge_to'],
		);
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
		}
		if ($js)
		{
			if (!is_object($GLOBALS['egw']->js))
			{
				include_once(EGW_API_INC.'/class.javascript.inc.php');
				$GLOBALS['egw']->js =& new javascript();
			}
			$GLOBALS['egw']->js->set_onload($js);
		}
		$GLOBALS['egw_info']['flags']['app_header'] = lang('ranking').' - '.lang($view ? 'view %1' : 'edit %1',lang('Athlete'));
		$this->tmpl->read('ranking.athlete.edit');
		$this->tmpl->exec('ranking.uiathletes.edit',$content,
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
		//echo "uiathletes::get_rows() query="; _debug_array($query_in);
		$GLOBALS['egw']->session->appsession('ranking','athlete_state',$query=$query_in);

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

		$readonlys = array();
		foreach($rows as $row)
		{
			if ($row['last_comp'] || !$this->acl_check_athlete($row))
			{
				$readonlys["delete[$row[PerId]]"] = true;
			}
			$readonlys["apply_license[$row[PerId]]"] = $row['license'] != 'n' ||
				!$this->acl_check_athlete($row,EGW_ACL_ATHLETE,null,$license_nation?$license_nation:'NULL');
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
				'get_rows'       =>	'ranking.uiathletes.get_rows',
				'filter_no_lang' => True,
				'filter_label'   => lang('Category'),
				'filter2_label'  => 'License',
				'no_cat'         => True,// I  disable the cat-selectbox
				'order'          =>	'nachname',// IO name of the column to sort after (optional for the sortheaders)
				'sort'           =>	'ASC',// IO direction of the sort: 'ASC' or 'DESC'
				'csv_fields'     => false,
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
		$this->tmpl->exec('ranking.uiathletes.index',$content,array(
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
			$GLOBALS['egw']->common->egw_exit();
		}
		$egw_charset = $GLOBALS['egw']->translation->charset();
		foreach($this->athlete->data as $name => $value)
		{
			// the rtf seems to use iso-8859-1
			$value = $GLOBALS['egw']->translation->convert($value,$egw_charset,'iso-8859-1');
			$form = str_replace('$$'.$name.'$$',$value,$form);
		}
		include_once(EGW_API_INC.'/class.browser.inc.php');
		$browser = new browser();
		$file = 'License '.$year.' '.$this->athlete->data['vorname'].' '.$this->athlete->data['nachname'].'.rtf';
		$browser->content_header($file,'text/rtf');
		echo $form;
		$GLOBALS['egw']->common->egw_exit();
	}
}
