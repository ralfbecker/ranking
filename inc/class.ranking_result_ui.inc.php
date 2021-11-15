<?php
/**
 * EGroupware digital ROCK Rankings - ResultService UI
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2007-19 by Ralf Becker <RalfBecker@digitalrock.de>
 */

use EGroupware\Api;
use EGroupware\Ranking\Athlete;
use EGroupware\Ranking\Selfservice;
use EGroupware\Ranking\Competition;
use EGroupware\Ranking\Category;

class ranking_result_ui extends ranking_result_bo
{
	/**
	 * @var array $public_functions functions callable via menuaction
	 */
	var $public_functions = array(
		'index' => true,
		'route' => true,
	);

	/**
	 * Edit or add a route / heat
	 *
	 * @param array $content =null
	 * @param string $msg =''
	 */
	function route($content=null,$msg='')
	{
		$tmpl = new Api\Etemplate('ranking.result.route');

		if (!is_array($content))
		{
			$content = $keys = array(
				'WetId' => $_GET['comp'],
				'GrpId' => $_GET['cat'],
				'route_order' => $_GET['route'] == '-2' ? -1 : $_GET['route'],	// -2 pairing speed --> show general result
			);
		}
		else
		{
			if ($content['discipline'] == 'selfscore')
			{
				$matches = null;
				if (!preg_match('/^([0-9]+)(\/([0-9]+))?(:([0-9]+))?(b?t?f?)?$/', $content['selfscore_mode'], $matches) ||
					!($matches[1] > 0))
				{
					Api\Etemplate::set_validation_error('selfscore_mode', 'Wrong format!');
					unset($content['button']);
				}
				else
				{
					$content['route_num_problems'] = (int)$matches[1];
					$content['selfscore_num'] = $matches[3] > 0 ? (int)$matches[3] : 10;
					$content['selfscore_points'] = !empty($matches[5]) ? (int)$matches[5] : null;
					$content['selfscore_use'] = $matches[6];
				}
			}

			if (isset($content['judges']))
			{
				$content['route_judges'] = [];
				foreach($content['judges'] as $n => $data)
				{
					$content['route_judges'][$n] = $data['ids'];
				}
			}
		}
		// read $comp, $cat, $discipline and check the permissions
		$comp = $cat = $discipline = null;
		if (!($ok = $this->init_route($content,$comp,$cat,$discipline)))
		{
			Api\Framework::window_close(lang('Permission denied !!!'));
		}
		elseif(is_string($ok))
		{
			$msg .= $ok;
		}
		$sel_options = array(
			'route_order' => $this->order_nums,
		);

		// disable selection of combined qualification event, for everything but combined qualification
		$tmpl->disableElement('comb_quali');
		if ($content['discipline'] == 'combined')
		{
			$content['route_type'] = THREE_QUALI_ALL_NO_STAGGER;
			$readonlys['route_type'] = true;

			if (0 <= $content['route_order'] && $content['route_order'] < 3)
			{
				try {
					$this->combined_quali_discipline2route($comp, $cat);
					$tmpl->disableElement('comb_quali');
				}
				catch (Api\Exception\WrongUserinput $ex) {
					unset($ex);
					$sel_options['comb_quali'] = array(
						'' => 'Select combined qualification or use registration'
					)+$this->combined_quali_comps($comp, $cat);
					$tmpl->disableElement('comb_quali', false);
				}
			}
			foreach($this->order_nums as $n => $label)
			{
				if ($n >= 0)
				{
					$dummy = null;
					$sel_options['route_order'][$n] = ($n >= 3 ? lang('Final') : lang('Qualification')).
						' '.lang($this->combined_order2discipline($n, $dummy, true));
				}
			}
		}
		if (!isset($content['slist_order']))
		{
			$content['slist_order'] = self::quali_startlist_default($discipline,$content['route_type'],$comp['nation']);
			// if we have a matching combined category in the competition, set it as additional cat to use for the startlist
			$content['add_cat'] = $this->cats->get_combined($cat['GrpId'], $comp['gruppen']);
		}
		// check if user has NO edit rights
		if (($view = !$this->acl_check($comp['nation'], self::ACL_RESULT, $comp, false,
			// allow register button for selfscore and route-judges
			$discipline == 'selfscore' ? $content : null)))
		{
			$readonlys['__ALL__'] = true;
			$readonlys['button[cancel]'] = false;
		}
		elseif ($content['button'] || $content['topos']['delete'] || $content['athlete']['register'])
		{
			if ($content['topos']['delete'])
			{
				$topo = key($content['topos']['delete']);
				unset($content['topos']);
				$button = 'delete_topo';
			}
			elseif ($content['athlete']['register'])
			{
				$button = 'register';
				unset($content['athlete']['register']);
			}
			elseif(!empty($content['button']))
			{
				$button = key($content['button']);
				unset($content['button']);
			}
			// reload the parent window
			$current = Api\Cache::getSession('ranking', 'result');
			$param = array(
				'menuaction' => 'ranking.ranking_result_ui.index',
				'ajax'  => 'true',		// avoid iframe
				'refresh' => time(),	// force a refresh (content-browser does not refresh same url)
				'comp'  => $content['WetId'],
				'cat'   => $content['GrpId'],
				// stay on pairing list for (combined) speed finals
				'route' => $current['route'] == -2 ? -2 : $content['route_order'],
			);
			if ($content['new_route'] || $button == 'startlist')
			{
				$param['show_result'] = $content['route_order'] >= 0 ? 0 : 2;
			}
			switch($button)
			{
				case 'save':
				case 'apply':
					if (isset($content['frm_line']))	// if a frm_line is given translate it back to a frm_id
					{
						$format = new ranking_display_format($this->db);
						$content['frm_id'] = $format->read(array(
							'dsp_id' => $content['dsp_clone_of'] ? $content['dsp_clone_of'] : $content['dsp_id'],
							'WetId'  => $content['WetId'],
							'frm_line' => $content['frm_line'],
						)) ? $format->frm_id : 0;
						$content['frm_id2'] = $format->read(array(
							'dsp_id' => $content['dsp_clone_of2'] ? $content['dsp_clone_of2'] : $content['dsp_id2'],
							'WetId'  => $content['WetId'],
							'frm_line' => $content['frm_line2'],
						)) ? $format->frm_id : 0;
					}
					//_debug_array($content);
					$err = $this->route->save($content);
					if ($err)
					{
						$msg = lang('Error: saving the heat!!!');
						$button = '';	// dont exit the window
						$refresh = false;
						break;
					}
					$msg = lang('Heat saved');
					if ($content['topo_upload'])
					{
						$topo_path = null;
						$msg .= ranking_measurement::save_topo($content, $content['topo_upload'], $topo_path) ?
							"\n".lang('Topo uploaded as %1.', $topo_path) : "\n".lang('Error uploading topo!');
					}
					$refresh = true;

					// if route is saved the first time, try getting a startlist (from registration or a previous heat)
					if (!$content['new_route']) break;

					unset($content['new_route']);	// no longer new
					$msg .= ', ';
					// fall-throught
				case 'startlist':
					//_debug_array($content);
					try {
						if ($this->has_results($content))
						{
							$msg .= lang('Error: heat already has a result!!!');
							$param['show_result'] = 1;
						}
						elseif (is_numeric($content['route_order']) &&
							($num = $this->generateStartlist($comp, $cat, $content['route_order'],
								$content['route_type'], $content['discipline'],
								$content['max_compl'] !== '' ? $content['max_compl'] : 999,
								$content['slist_order'], $content['add_cat'], $content['comb_quali'])))
						{
							$msg .= lang('Startlist generated');

							$to_set = array();
							$to_set['route_status'] = $content['route_status'] = STATUS_STARTLIST;	// set status to startlist
							/* RB 2013-10-06 commented as it does NOT allow to set quota=0 when creating a final
							   not sure why it was there in the first place
							if (!$content['route_quota'])
							{
								$content['route_quota'] = $to_set['route_quota'] =
									$this->default_quota($discipline,$content['route_order'],$content['quali_type'],$num);
							}*/
							if ($this->route->read($content,true)) $this->route->save($to_set);
						}
						else
						{
							$msg .= lang('Error: generating startlist!!!');
						}
					} catch (Api\Exception\WrongUserinput $e) {
						$msg = $e->getMessage();
						unset($button);	// to not exit for save
					}
					$refresh = true;
					break;

				case 'delete':
					//_debug_array($content);
					if ($content['route_order'] < $this->route->get_max_order($content['WetId'],$content['GrpId']))
					{
						$msg = lang('You can only delete the last heat, not one in between!');
						$button = '';	// dont exit the window
						$refresh = false;
					}
					elseif ($this->route->delete(array(
						'WetId' => $content['WetId'],
						'GrpId' => $content['GrpId'],
						'route_order' => $content['route_order'])))
					{
						$msg = lang('Heat deleted');
						$refresh = true;
					}
					else
					{
						$msg = lang('Error: deleting the heat!!!');
						$button = '';	// dont exit the window
						$refresh = false;
					}
					break;

				case 'upload':
					if ($content['new_route'])
					{
						if ($this->route->save($content) != 0)
						{
							$msg = lang('Error: saving the heat!!!');
							$button = '';	// dont exit the window
							$refresh = false;
							break;
						}
						$msg = lang('Heat saved').', ';
						unset($content['new_route']);
					}
					if (!($content['upload_options'] & 1) && $this->has_results($content))
					{
						$msg = lang('Error: route already has a result!!!');
						$param['show_result'] = 1;
					}
					elseif (!$content['file']['tmp_name'])
					{
						$msg .= lang('Error: no file to upload selected');
					}
					elseif (is_numeric($imported = $this->upload($content,$content['file']['tmp_name'],
						$content['upload_options'] & 2,$content['upload_options'] & 4)))
					{
						// set number of problems from csv file
						if ($content['route_num_problems'])
						{
							list($line1) = file($content['file']['tmp_name']);
							for($n = 3; $n <= 6; $n++)
							{
								if (strpos($line1,'boulder'.$n)) $num_problems = $n;
							}
							if ($num_problems && $num_problems != $content['route_num_problems'])
							{
								$content['route_num_problems'] = $num_problems;
								$need_save = true;
							}
						}
						// set the name from the csv file
						if (substr($content['file']['name'],0,strlen($cat['name'])+3) == $cat['name'].' - ' &&
							($name_from_file = str_replace('.csv','',substr($content['file']['name'],strlen($cat['name'])+3))) != $content['route_name'])
						{
							$content['route_name'] = $name_from_file;
							$need_save = true;
						}
						// save the route, if we set something above
						if ($need_save && $this->route->save($content) != 0)
						{
							$msg = lang('Error: saving the heat!!!');
							$button = '';	// dont exit the window
							$refresh = false;
							break;
						}
						$msg .= lang('%1 participants imported',$imported);
						$param['show_result'] = 1;
						$refresh = true;
					}
					else
					{
						$msg .= $imported;
					}
					break;

				case 'topo_upload':
					if ($content['topo_upload'])
					{
						$msg .= ranking_measurement::save_topo($content, $content['topo_upload'], $topo_path) ?
							lang('Topo uploaded as %1.', $topo_path) : lang('Error uploading topo!');
					}
					else
					{
						$msg .= lang('Error: no file to upload selected');
					}
					break;

				case 'delete_topo':
					$msg .= ranking_measurement::delete_topo($content, $topo) ?
						lang('Topo deleted.') : lang('Permission denied!');
					break;

				case 'ranking':
					$msg = $this->import_ranking($content, $content['import_cat'] === '0' ? null :
						($comp['fed_id'] ? $comp['fed_id'] : ($comp['nation'] != 'NULL' ? $comp['nation'] : null)),
						$content['import_cat']);
					break;

				case 'register':
					// check judge right or for selfscore route-judge rights
					if ($content['route_status'] == STATUS_RESULT_OFFICIAL ||
						!$this->acl_check($comp['nation'], self::ACL_RESULT, $comp, false,
							$content['discipline'] == 'selfscore' ? $content : null))
					{
						//error_log(__METHOD__.__LINE__."() route_status=$content[route_status], route_judges=".array2string($content['route_judges']).", comp=".array2string($comp).", is_judge()=".array2string($this->is_judge($comp, false)));
						$msg .= lang('Permission denied !!!');
						break;
					}
					$athlete = $content['athlete'];
					$required_misssing = strlen($athlete['vorname']) < 2 || strlen($athlete['nachname']) < 2 ||
						!$athlete['nation'] || !$athlete['fed_id'];
					if (!($athlete['PerId'] > 0) && $required_misssing)
					{
						$msg .= lang('You either need to search an athlete or enter required fields to add him!');
						break;
					}
					if ($content['athlete']['password_email'] && !$athlete['email'])
					{
						$msg .= "\n".lang('EMail required to send password email!');
						break;
					}
					$keys = array_intersect_key($content, array_flip(array('WetId', 'GrpId', 'route_order')));
					// meets registration requirements
					if ($athlete['license'] == 's')
					{
						$msg .= lang('Athlete is suspended !!!');
						break;
					}
					// check registration requirements
					if (!($cat = $this->cats->read($keys['GrpId'])))
					{
						$msg .= lang('Category NOT found !!!');
						break;
					}
					$wrong = array();
					if ($cat['sex'] && $cat['sex'] != $athlete['sex'])
					{
						$wrong['sex'] = lang('Gender');
					}
					if (!$this->cats->in_agegroup($athlete['geb_date'], $cat, (int)$comp['datum']))
					{
						$wrong['age'] = lang('Athlete is NOT in the age-group of that category').
							(!$athlete['geb_date'] ? ': '.lang('no birthdate') : '');
					}
					if (!$this->comp->open_comp_match($athlete))
					{
						$wrong['fed'] = lang('federation or nationality');
					}
					if ($wrong)
					{
						$msg .= lang('Athlete does NOT meet registration requirements: %1!',
							implode(', ', $wrong));
						break;
					}
					// temporary reset all ACL but deny-profile, so save does NOT remove birthdate, email and city data
					$this->athlete->acl2clear = array(Athlete::ACL_DENY_PROFILE => $this->athlete->acl2clear[Athlete::ACL_DENY_PROFILE]);
					// store email of existing athlete
					if ($athlete['PerId'] && $athlete['email'] && ($stored = $this->athlete->read($athlete['PerId'])) &&
						$athlete['email'] != $stored['email'])
					{
						$this->athlete->save(array('email' => $athlete['email']));
					}
					if (!($athlete['PerId'] > 0))
					{
						unset($athlete['PerId']);
						$this->athlete->init($athlete);
						$this->athlete->generate_rkey();
						if ($this->athlete->save())
						{
							$msg .= lang('Error: while saving !!!');
							break;
						}
						$msg .= lang('%1 saved',lang('Athlete'));
						$athlete = $this->athlete->data;
					}
					// check athlete not already registered
					if ($athlete['PerId'] > 0 && ($this->route_result->read($keys+array('PerId' => $athlete['PerId']))))
					{
						$msg .= "\n".lang('%1 is already registered!', $athlete['nachname'].', '.$athlete['vorname'].' ('.$athlete['nation'].')');
					}
					else	// register athlete
					{
						$start_order = count($this->route_result->search($keys, true))+1;
						$this->route_result->init($keys+array(
							'PerId' => $athlete['PerId'],
							'start_order' => $start_order,
						));
						$this->route_result->save();
						$msg .= ($msg ? "\n" : '').lang('%1 registered.', $athlete['nachname'].', '.$athlete['vorname'].' ('.$athlete['nation'].')');
					}
					// send password email
					if ($content['athlete']['password_email'] && $athlete['email'])
					{
						try {
							$selfservice = new Selfservice();
							$selfservice->passwordResetMail($athlete);
							$msg .= "\n".lang('An EMail with instructions how to (re-)set the password has been sent.');
						}
						catch (Exception $e) {
							$msg .= "\n".lang('Sorry, an error happend sending your EMail (%1), please try again later or %2contact us%3.',
								$e->getMessage(),'<a href="mailto:info@digitalrock.de">','</a>');
						}
					}
					$content['athlete'] = array('password_email' => $content['athlete']['password_email']);
					$refresh = true;
					break;
			}
			if (in_array($button,array('save','delete')))	// close the popup and refresh the parent
			{
				Api\Framework::refresh_opener($msg, 'ranking', $param);
				Api\Framework::window_close();
			}
		}
		if ($discipline == 'selfscore')
		{
			$content['selfscore_mode'] = $content['route_num_problems'].'/'.$content['selfscore_num'].
				($content['selfscore_points'] ? ':'.$content['selfscore_points'] : '').$content['selfscore_use'];
			// first call for selfscore: check password-email
			if ($_SERVER['REQUEST_METHOD'] == 'GET') $content['athlete']['password_email'] = true;
		}
		$tmpl->disableElement('selfscore_mode', $discipline != 'selfscore');

		if ($refresh) Api\Framework::refresh_opener ($msg, 'ranking', $param);
		if ($msg) Api\Framework::message($msg, strpos($msg, '!') ? 'error' : null);

		$readonlys['button[delete]'] = $content['new_route'] || $view;
		if (!isset($readonlys['route_type']))
		{
			$readonlys['route_type'] = !!$content['route_order'];	// can only be set in the first route/quali
		}
		$content += ($preserv = array(
			'WetId'       => $comp['WetId'],
			'GrpId'       => $cat['GrpId'],
			'route_order' => $content['route_order'],
			'discipline'  => $discipline,
			'no_display'  => true,		// we enable it later, for some cases (judge and display-rights)
		));
		if ($this->is_judge($comp) || $this->is_admin)
		{
			$content['topos'] = ranking_measurement::get_topos($preserv);
		}
		else
		{
			$readonlys['tabs']['measure'] = true;	// measurement tab only for judges
		}
		$tmpl->disableElement('route_num_problems',
			substr($discipline != 'combined' ? $discipline : $this->combined_order2discipline($content['route_order']), 0, 7) != 'boulder');

		$readonlys['discipline'] = !!$content['route_order'];	// for no only allow to set discipline in 1. quali

		foreach(array('new_route','route_type','route_order','dsp_id','frm_id','dsp_id2','frm_id2','selfscore_mode','route_judges') as $name)
		{
			$preserv[$name] = $content[$name];
		}
		$sel_options += array(
			'WetId' => array($comp['WetId'] => strip_tags($comp['name'])),
			'GrpId' => array($cat['GrpId']  => $cat['name']),
			'route_status' => $this->stati,
			'route_type' => isset($this->quali_types_dicipline[$discipline]) ?
				$this->quali_types_dicipline[$discipline] : $this->quali_types,
			'discipline' => $this->rs_disciplines,
			'upload_options' => array(
				1 => array(
					'label' => 'delete result',
					'title' => 'Delete an eventually existing result withour further confirmation',
				),
				2 => array(
					'label' => 'add athletes',
					'title' => 'add not existing athletes to the database, use with caution!',
				),
				3 => 'all above',
				5 => array(	// 1=delete result|4=ignore comp and heat
					'label' => 'ignore competition',
					'title' => 'imports a result from a different competition and heat, use with care',
				),
			),
			'slist_order' => self::slist_order_options($comp['serie']),
		);
		if (isset($content['route_type']) && !isset($sel_options['route_type'][$content['route_type']]))
		{
			$sel_options['route_type'][$content['route_type']] = isset($this->quali_types[$content['route-type']]) ?
				$this->quali_types[$content['route-type']] : lang('Unknown type').' #'.$content['route-type'];
		}
		// athlete selected in registration
		if ($content['athlete']['PerId'] > 0)
		{
			// temporary reset all ACL but deny-profile, so route-judge in registration get birthdate, email and city data
			$this->athlete->acl2clear = array(Athlete::ACL_DENY_PROFILE => $this->athlete->acl2clear[Athlete::ACL_DENY_PROFILE]);

			if (($athlete = $this->athlete->read(array('PerId' => $content['athlete']['PerId']))))
			{
				$keys = array_intersect_key($content, array_flip(array('WetId', 'GrpId', 'route_order')));
				if ($this->route_result->read($keys+array('PerId' => $content['athlete']['PerId'])))
				{
					$content['msg'] = lang('%1 is already registered!', $athlete['nachname'].', '.$athlete['vorname'].' ('.$athlete['nation'].')');
				}
				$content['athlete'] = $preserv['athlete'] = $athlete+array('password_email' => $content['athlete']['password_email']);
				$sel_options['fed_id'] = array($content['athlete']['fed_id'] => $content['athlete']['verband']);
				$sel_options['nation'] = array($content['athlete']['nation'] => $content['athlete']['nation']);
				$sel_options['sex'] = $this->genders;
				$readonlys['athlete'] = array(
					'vorname' => true,
					'nachname' => true,
					'ort' => true,
					'geb_date' => true,
					'fed_id' => true,
					'nation' => true,
					'sex' => true,
				);
			}
		}
		// registration and no athlete selected: fill nation and fed_id selectboxes
		if (!($content['athlete']['PerId']) && !$readonlys['tabs']['registration'])
		{
			if ($cat['sex'])
			{
				$sel_options['sex'] = array($cat['sex'] => $this->genders[$cat['sex']]);
				$content['athlete']['sex'] = $cat['sex'];
				//if ($cat['sex']) $readonlys['athlete']['sex'] = true;
			}
			else
			{
				$sel_options['sex'] = $this->genders;
				unset($sel_options['sex']['']);
			}
			if (!$comp['nation'] || $comp['open_comp'] == 3)	// 3: international open
			{
				$sel_options['nation'] = $this->athlete->distinct_list('nation');
			}
			elseif($comp['open_comp'] == 2)	// 2: D, A, CH
			{
				$sel_options['nation'] = array(
					'AUT' => 'AUT',
					'GER' => 'GER',
					'SUI' => 'SUI',
				);
			}
			else
			{
				$sel_options['nation'] = array(
					$comp['nation'] => $comp['nation'],
				);
			}
			if (empty($content['athlete']['nation']))
			{
				$content['athlete']['nation'] = $comp['nation'];
				$content['athlete']['fed_id'] = $comp['fed_id'];
			}
			if ($content['athlete']['nation'])
			{
				$sel_options['fed_id'] = $this->athlete->federations($content['athlete']['nation'], !$comp['nation']);
			}
			else
			{
				$sel_options['fed_id'] = array(lang('Select a nation first'));
			}
		}
		if ($content['route_order'] < 0)
		{
			unset($sel_options['route_status'][0]);
		}
		// cant delete general result or not yet saved routes
		$readonlys['button[startlist]'] = $readonlys['button[delete]'] =
			$content['route_order'] < 0 || $content['new_route'] || $content['route_status'] == STATUS_RESULT_OFFICIAL;
		// disable max. complimentary selection if no quali.
		$disable_compl = false;
		if ($content['route_order'] > (int)($content['route_type']==TWO_QUALI_HALF) || $content['route_order'] < 0)
		{
			$disable_compl = true;
			if ($readonlys['button[startlist]']) $content['no_startlist'] = true;	// disable empty startlist row
		}
		// hack for speedrelay to use startlist button for randomizing
		if ($discipline == 'speedrelay' && !$content['route_order'])
		{
			$disable_compl = true;
			$tmpl->set_cell_attribute('button[startlist]','label','Randomize startlist');
		}
		$tmpl->disableElement('max_compl', $disable_compl);
		$tmpl->disableElement('slist_order', $disable_compl);

		// no judge rights --> make everything readonly and disable all buttons but cancel
		if (!$this->acl_check($comp['nation'],self::ACL_RESULT,$comp))
		{
			$readonlys = array('__ALL__' => true, 'tabs' => $readonlys['tabs']);
			$readonlys['button[cancel]'] = false;
			$content['no_upload'] = true;

			// route judge is allowed to register athletes for selfscore
			if ($this->is_judge($comp, false, $content) && $content['discipline'] == 'selfscore' &&
				$content['route_status'] != STATUS_RESULT_OFFICIAL)
			{
				$readonlys['athlete'] = array(
					'PerId' => false,
					'vorname' => false,
					'nachname' => false,
					'email' => false,
					'ort' => false,
					'geb_date' => false,
					'fed_id' => false,
					'nation' => false,
					'password_email' => false,
					'sex' => false,
					'register' => false,
				);
				$content['tabs'] = 'registration';
			}
			else error_log(__METHOD__.': '.__LINE__.' no rights!');
		}
		else
		{
			if ($content['route_order'])
			{
				$readonlys['add_cat'] = true;
			}
			else
			{
				$sel_options['add_cat'] = array('' => lang('Additional category'));
				$sel_options['add_cat'] += $this->cats->names(array('rkey' => $comp['gruppen'],'sex' => $cat['sex'],'GrpId!='.(int)$cat['GrpId']),0);
			}
			if ($content['route_status'] != STATUS_RESULT_OFFICIAL || $content['new_route'] ||
				$content['route_order'] != -1 && $discipline != 'selfscore' || $discipline == 'speedrelay')
			{
				$readonlys['button[ranking]'] = $readonlys['import_cat'] = true;	// only offical results can be commited into the ranking
			}
			else
			{
				$readonlys['tabs']['registration'] = true;

				$sel_options['import_cat'] = array('' => lang('Into current category'));
				if ($comp['nation'] && $comp['nation'] != 'NULL' && $comp['open_comp'])
				{
					$sel_options['import_cat']['0'] = lang('without excluding non-members');
				}
				// filter by same gender and not identical
				$sel_options['import_cat'] += $this->cats->names(array('sex' => $cat['sex'],'GrpId!='.(int)$cat['GrpId']), -1,
					// sort by same nation first
					'nation'.($cat['nation']?'='.$this->db->quote($cat['nation']):' IS NULL').' DESC,'.
					// then by having an agegroup or not
					'(from_year IS NOT NULL OR to_year IS NOT NULL) '.
						(Category::age_group($cat, $comp['datum']) ?  'DESC' : 'ASC'), false);
				//add cats from other competitions with identical date
				if (($comps = $this->comp->names(array('datum' => $comp['datum'],'WetId!='.(int)$comp['WetId']), 0)))
				{
					foreach($comps as $id => $label)
					{
						if (($c = $this->comp->read($id)) && $c['gruppen'] &&
							($c_cats = $this->cats->names(array('rkey' => $c['gruppen'],'sex' => $cat['sex']),0)))
						{
							foreach($c_cats as $c_cat_id => $c_cat_name)
							{
								$sel_options['import_cat'][$c_cat_id.':'.$c['WetId']] = $c_cat_name.': '.$label;
							}
						}
					}
				}
			}
			if ($content['route_status'] == STATUS_RESULT_OFFICIAL || $content['route_order'] < 0)
			{
				$content['no_upload'] = $readonlys['button[upload]'] = true;	// no upload if result offical or general result
			}
			$display = new ranking_display($this->db);
			// display selection, only if user has rights on the displays
			if (($sel_options['dsp_id'] = $sel_options['dsp_id2'] = $display->displays()))
			{
				$content['no_display'] = false;
				foreach(array('','2') as $num)
				{
					if ($content['dsp_id'.$num] && $display->read($content['dsp_id'.$num]))
					{
						$preserv['dsp_clone_of'.$num] = $display->dsp_clone_of;

						if (is_null($format))
						{
							$format = new ranking_display_format($this->db);
						}
						if ($content['frm_id'.$num] && $format->read($content['frm_id'.$num]))
						{
							$content['frm_line'.$num] = $format->frm_line;
						}
						$content['max_line'.$num] = $format->max_line(array(
							'dsp_id' => $display->dsp_clone_of ? $display->dsp_clone_of : $display->dsp_id,
							'WetId'  => $content['WetId'],
						));
					}
					if (!$content['max_line'.$num]) $content['max_line'.$num] = 1;
				}
			}
			$content['judges'] = [
				'boulder' => strpos($content['discipline'], 'boulder') === 0 && $content['route_num_problems'] > 1,
				'note' => strpos($content['discipline'], 'boulder') === 0 && $content['route_num_problems'] > 1 ?
					lang('Setting judges only on the first boulder gives them rights for all boulders!') : null,
			];
			for($i = 0, $n = max(1, $content['route_num_problems']); $i < $n; $i++)
			{
				$content['judges'][] = ['num' => $i+1, 'ids' => $content['route_judges'][$i] ?? ''];
			}
		}

		//_debug_array($content);
		//_debug_array($sel_options);
		$GLOBALS['egw_info']['flags']['app_header'] = lang('Ranking').' - '.
			($content['new_route'] ? lang('Add heat') : lang('Edit heat'));
		$tmpl->exec('ranking.ranking_result_ui.route',$content,$sel_options,$readonlys,$preserv,2);
	}

	/**
	 * Return options for startlist order
	 *
	 * @param boolean $cup
	 * @return array
	 */
	private static function slist_order_options($cup)
	{
		$options = array(
			0  => 'random (keep existing startorder!)',
			9  => 'random (distribution ranking)',
			10 => 'random (distribution cup)',
			1  => 'ranking (unranked last)',
			5  => 'reverse ranking (unranked first)',
			2  => 'cup (unranked last)',
			6  => 'reverse cup (unranked first)',
		);
		if (!$cup)
		{
			unset($options[2]);
			unset($options[4]);
			unset($options[6]);
		}
		return $options;
	}

	/**
	 * query the start or result list
	 *
	 * @param array &$query_in
	 * @param array &$rows returned rows/cups
	 * @param array &$readonlys eg. to disable buttons based on acl
	 */
	function get_rows(&$query_in,&$rows,&$readonlys)
	{
		//echo "<p>uiresult::get_rows(".print_r($query_in,true).",,)</p>\n";
		unset($query_in['return']);	// no need to save
		$query = $query_in;
		unset($query['rows']);		// no need to save, can not unset($query_in['rows']), as this is $rows !!!
		Api\Cache::setSession('ranking', 'result', $query);

		$query['col_filter']['WetId'] = $query['comp'];
		$query['col_filter']['GrpId'] = $query['cat'];
		$query['col_filter']['route_order'] = $query['route'];
		// this is to transport the route_type to ranking_route_result::search's filter param
		$query['col_filter']['route_type'] = $query['route_type'];

		// for selfscore finals are boulder, so we need to check for boulder, if there is a selfscore quali
		if ($query['discipline'] == 'boulder' && $query['route'] == -1 &&
			($route = $this->route->read(array(
				'route_order'=> 0,
				'WetId' => $query['comp'],
				'GrpId' => $query['cat'],
			))))
		{
			$query['discipline'] = $route['discipline'];
		}
		$query['col_filter']['discipline'] = $query['discipline'];
		$query['col_filter']['comp_nation'] = $query['calendar'];
		$query['col_filter']['quali_preselected'] = $query['quali_preselected'];
		if (!empty($query['route_data']) && $query['route_data']['discipline'] === 'combined')
		{
			$query['col_filter']['combined'] = true;
		}
		// check if route_result object is instancated for relay or not
		if ($this->route_result->isRelay != ($query['discipline'] == 'speedrelay'))
		{
			$this->route_result->__construct($this->config['ranking_db_charset'],$this->db,null,
				$query['discipline'] == 'speedrelay');
		}
		// fix sorting, add eg. alphabetic sort behind result_rank
		self::process_sort($query,$this->route_result->isRelay);

		if($query['route'] == -2 && in_array($query['discipline'], array('speed','combined')) && strstr($query['template'],'speed_graph'))
		{
			$query['order'] = 'result_rank';
			$query['sort']  = 'ASC';
		}
		// for speed: skip 1/8 and 1/4 Final if there are less then 16 (8) starters
		if($query['route'] == -2 && strstr($query['template'],'speed_graph') &&
			(substr($query['discipline'],0,5) == 'speed' || $query['discipline'] == 'combined'))
		{
			$num_first_final = $this->route_result->get_count(array('route_order' => 2, 'WetId' => $query['comp'],'GrpId' => $query['cat']));
			$skip = $num_first_final >= 16 ? 0 : 1+($num_first_final < 8);
			if (!$skip) $rows['heat3'] = array(true);	// to not hide the 1/8-Final because of no participants yet
			if ($query['discipline'] == 'combined')
			{
				$query['col_filter']['route_order'] = -6;
				$num_first_final = self::COMBINED_FINAL_QUOTA;
				$skip = 0;
			}
			$query['num_rows'] = $num_first_final;	// dont need further quali-participants
		}
		//echo "<p align=right>order='$query[order]', sort='$query[sort]', start=$query[start]</p>\n";
		$extra_cols = $query['csv_export'] ? array('strasse','email') : array();

		// hack to fix combined general result(s) when comming from speed quali (looses boulder result otherwise)
		if ($query['col_filter']['route_order'] < 0 && $query['col_filter']['discipline'] == 'combined')
		{
			$query['col_filter']['route_type'] = THREE_QUALI_ALL_NO_STAGGER;
		}

		//error_log(__METHOD__."() query[col_filter]=".array2string($query['col_filter']).", extra_cols=".array2string($extra_cols));
		$total = $this->route_result->get_rows($query,$rows,$readonlys,$join='',false,false,$extra_cols);
		//error_log(__METHOD__."() num_first_final=$num_first_final, skip=$skip, count(rows)=".count($rows));
		//echo $total; _debug_array($rows);

		if (((int)$query['ranking'] & 3) && strstr($query['template'],'startlist') &&
			($cat = $this->cats->read($query['cat'])))
		{
			$comp = $this->comp->read($query['comp']);
			$stand = $comp['datum'];
			$nul = $test = $ranking = null;
			if (!$this->ranking($cat,$stand,$nul,$test,$ranking,$nul,$nul,$nul,$query['ranking']==2?$comp['serie']:'') && $query['ranking'] == 2 &&
				// if there no cup ranking yet (first competition) --> use the one from last year
				($last_cup = $this->cup->read($last_cup_rkey=str_replace('??',substr((int)$comp['datum']-1,-2),$cat['serien_pat']))))
			{
				$stand = ((int)$comp['datum']-1).'-12-31';
				$this->ranking($cat,$stand,$nul,$test,$ranking,$nul,$nul,$nul,$last_cup);
			}
		}
		// speedrelay quali startlist: add empty row to add new team
		if ($query['discipline'] == 'speedrelay' && !$query['route'] && !$query['show_result'])
		{
			$rows[] = array(
				'team_id' => 0,
				'start_order' => count($rows)+1,
				'class' => 'noPrint',	// dont print add-team-row
			);
			$readonlys['delete[0]'] = true;
			++$total;
		}
		if (empty($query['display_athlete']))
		{
			$query['display_athlete'] = Competition::nation2display_athlete($query['calendar'], true);	// true = internal use, not feed export
		}
		$need_start_number = false;
		$need_lead_time_column = false;
		$quota_line = false;
		foreach($rows as $k => $row)
		{
			if (!is_int($k)) continue;

			if ($row['result_time'] && $query['discipline'] == 'lead') $need_lead_time_column = true;

			if ($row['result_rank']) $rows[$k]['class'] .= ' noDelete';

			if (!($rows[$k]['profile_url'] = $this->athlete->picture_url($row['rkey'])))
			{
				$rows[$k]['profile_url'] = Api\Image::find('ranking', 'transparent');
			}
			$rows[$k]['id'] = $row[$this->route_result->id_col];
			/* not used anymore: results for setting on regular routes (no general result)
			if($query['route'] >= 0)
			{
				$rows['set'][$row[$this->route_result->id_col]] = $row;
				// disable input in for checked results
				if ($row['checked'])
				{
					foreach(array_keys($row) as $name)
					{
						if ($name != 'checked')
						{
							$readonlys['set['.$row[$this->route_result->id_col].']['.$name.']'] = true;
						}
					}
					// boulder top/zone is not set, if no result
					for($i = 1; $i <= $query['num_problems']; ++$i)
					{
						$readonlys['set['.$row['PerId'].'][top'.$i.']'] = $readonlys['set['.$row['PerId'].'][zone'.$i.']'] = true;
					}
					$readonlys['apply['.$row['PerId'].']'] = true;
				}
			}*/
			if (!$quota_line && $query['route_quota'] && $query_in['order'] == 'result_rank' && $query_in['sort'] == 'ASC' &&
				$row['result_rank'] > $query['route_quota'])	// only show quota line if sorted by rank ASC
			{
				$rows[$k]['quota_class'] = 'quota_line';
				$quota_line = true;
			}
			if ($ranking)
			{
				$rows[$k]['ranking_place'] = $ranking[$row['PerId']]['platz'];
				$rows[$k]['ranking_points'] = $ranking[$row['PerId']]['pkt'];
			}
			// for the speed graphic, we have to make the athlets availible by the startnumber of each heat
			if ($query['route'] == -2 && strstr($query['template'],'speed_graph') &&
				(substr($query['discipline'],0,5) == 'speed' || $query['discipline'] == 'combined'))
			{
				$first = $query['discipline'] === 'combined' ? 3 : 2;
				$last  = $query['discipline'] === 'combined' ? 5 : 6-$skip;
				for($suffix = $first; $suffix <= $last; ++$suffix)
				{
					// create pseudo row from general result to allow editing in pairing list
					$r = array_intersect_key($row, array_flip(['WetId','GrpId','PerId','nation','vorname','nachname','start_number','verband']));
					$r += [
						'route_order' => $suffix,
						'result' => $row['result'.$suffix],
						'start_order' => isset($row['start_order'.$suffix]) ? $row['start_order'.$suffix] : $row['start_order'],
						'result_rank' => $row['result_rank'.$suffix],
						//'row' => $row,
					] + (array)json_decode($row['result_detail'.($suffix == $first && !isset($row['result_detail'.$suffix]) ? '' : $suffix)], true);
					$r['result_time'] = $r['result_time_l'] ? $r['result_time_l'] : null; unset($r['result_time_l']);
					$r['eliminated'] = $r['false_start'] ? ranking_result_bo::ELIMINATED_FALSE_START : $r['eliminated_l'];
					unset($r['eliminated_l'], $r['false_start']);

					if (isset($row['start_order'.$suffix]))
					{
						$rows['heat'.($suffix+$skip)][$row['start_order'.$suffix]] = $r;
						unset($rows[$k]['result']);	// only used for winner and 3. place
						// make final or small final winners availible as winner1 and winner3
						if ($suffix+$skip >= 5 && $row['result'.$suffix] && in_array($rank=$row['result_rank'.$suffix], array(1, 3)))
						{
							// regular speed has small final in own heat, therefore are both on $row['result_rank'.$suffix] == 1
							if ($rank == 1 && isset($rows['winner'.$rank])) $rank = $row['result_rank'];
							if ($query['discipline'] !== 'combined' && $suffix == $last-1) $rank = 3;	// otherwise small final winner shown as 1st
							$rows['winner'.$rank] = $row;
							unset($rows['winner'.$rank]['PerId']);	// to disable editing
							unset($rows['winner'.$rank]['result']); // remove (wrong) time from winners
						}
					}
					elseif($suffix == 3 && $query['discipline'] == 'combined')
					{
						$rows['heat'.($suffix+$skip)][$row['start_order']] = $r;
					}
				}
				// we dont need original rows for speed-graph, in fact they confuse autorepeating in et2
				unset($rows[$k]);
				continue;
			}
			if ($query['pstambl'])
			{
				list($page_name,$target) = explode(',',$query['pstambl']);
				$rows[$k]['link'] = ',index.php?page_name='.$page_name.'&person='.$row['PerId'].'&cat='.$query['cat'].',,,'.$target;
			}

			// shorten DAV or SAC Sektion
			$rows[$k]['verband'] = preg_replace('/^(Deutscher Alpenverein|Schweizer Alpen[ -]{1}Club) /','',$row['verband']);

			if (!$need_start_number && $row['start_number']) $need_start_number = true;

			if ($query['discipline'] == 'speedrelay')
			{
				if ($query['route'] > 0)
				{
					$readonlys["set[$row[team_id]][team_nation]"] = $readonlys["set[$row[team_id]][team_name]"] = true;
				}
				if ($row['team_id'] && $query['template'] == 'ranking.result.index.rows_relay')
				{
					$PerIds = $titles = array();
					for($i = 1; $i <= 3; ++$i)
					{
						if ($row['PerId_'.$i] > 0) $PerIds[$row['PerId_'.$i]] = $row['PerId_'.$i];
					}
					if (count($PerIds) < 3) $titles[''] = lang('None');
					if ($PerIds) $titles += Api\Link::titles('ranking',$PerIds);
					$rows['sel_options']['set['.$row['team_id'].'][PerId_1]'] =
						$rows['sel_options']['set['.$row['team_id'].'][PerId_2]'] =
						$rows['sel_options']['set['.$row['team_id'].'][PerId_3]'] = $titles;

					$readonlys['set['.$row['team_id'].'][team_nation]'] = true;
				}
			}
			if ($row['ability_percent'] && $query['route'] == -1 && $query['discipline'] == 'lead')
			{
				foreach(array('result','result1') as $n)
				{
					$matches = null;
					if (preg_match('/^([0-9.]+)(\\+|-|&nbsp;)?$/',$row[$n],$matches))
					{
						$rows[$k][$n] = str_replace('.00','',number_format($matches[1]/100.0*$row['ability_percent'],2)).$matches[2];
					}
				}
			}
			// mark prequalified participants for qualification with "Prequalified" instead of start-order
			if ($query['quali_preselected'] && $query['route'] < 2 &&
				$row['ranking'] && $row['ranking'] <= $query['quali_preselected'])
			{
				$rows[$k]['start_order'] = lang('Prequalified');
			}

			if ($query['display_athlete'] == Competition::CITY) unset($row['plz']);
			if ($query['display_athlete'] == Competition::PARENT_FEDERATION && empty($row['acl_fed']) ||
				$query['display_athlete'] == Competition::FED_AND_PARENT)	// German Sektion and LV
			{
				if ($row['fed_parent'])
				{
					static $feds = array();
					if (!isset($feds[$row['fed_parent']]) && ($fed = $this->federation->read(array('fed_id' => $row['fed_parent']))))
					{
						$feds[$row['fed_parent']] = $fed['fed_shortcut'] ? $fed['fed_shortcut'] : $fed['verband'];
					}
					$rows[$k]['acl_fed'] = $feds[$row['fed_parent']];
				}
				if (empty($rows[$k]['acl_fed'])) $rows[$k]['acl_fed'] = $row['verband'];
			}
		}
		// disable lead time-column in print, if not used
		if (!$need_lead_time_column) $rows['lead_time_class'] = 'noPrint';

		// disable print-only start-number
		$rows['no_printnumber'] = !$need_start_number;
		$rows['no_start_number'] = !$need_start_number && $query['route_status'] == STATUS_RESULT_OFFICIAL;

		// report the set-values at time of display back to index() for calling ranking_result_bo::save_result
		Api\Cache::setSession('ranking', 'set_'.$query['comp'].'_'.$query['cat'].'_'.$query['route'], $rows['set']);

		// show previous heat only if it's counting
		$rows['no_prev_heat'] = $query['route'] < 2+(int)($query['route_type']==TWO_QUALI_HALF) ||
			$query['route_type'] == TWOxTWO_QUALI && $query['route'] == 4 ||
			$query['route_type'] == TWO_QUALI_GROUPS && $query['route'] < 4 ||
			$query['route_type'] == THREE_QUALI_ALL_NO_STAGGER && $query['route'] < 3 ||
			// combined uses previous heat / quali only for boulder (6) and lead (7) final
			$query['route_data']['discipline'] == 'combined' && $query['route'] < 6 ||
			$query['quali_preselected'] && $query['route'] == 2;	// no countback to quali for quali_preselected
		// speed finals need to show quali times
		$rows['quali_times'] = $query['discipline'] === 'speed' && $query['route'] >= 2;

		// which result to show
		$rows['ro_result'] = $query['route_status'] == STATUS_RESULT_OFFICIAL ? '' : 'onlyPrint';
		$rows['rw_result'] = $query['route_status'] == STATUS_RESULT_OFFICIAL ? 'displayNone' : 'noPrint';
		if (!in_array($query['discipline'], array('speed','boulder')))
		{
			$rows['route_type'] = self::$route_type2const[$query['route_type']];
		}
		$rows['speed_only_one'] = $query['route_type'] == ONE_QUALI && !$query['route'] ||
			// record format uses 2 lanes only for quali
			$query['route_type'] == TWO_QUALI_BESTOF && $query['route'];
		$rows['num_problems'] = $query['num_problems'];
		$rows['readonly'] = $query['readonly'];
		$rows['no_ranking'] = !$ranking;
		$rows['show_ability'] = self::needAbilityPercent($query['cat']);
		// disable unused update / start time measurement buttons
		$readonlys['update'] = ($rows['time_measurement'] = $query['time_measurement']) ||
			$query['route_status'] == STATUS_RESULT_OFFICIAL;
		$rows['discipline'] = $query['discipline'];
		// show first qualification in last column for 3 qualification routes
		$rows['first_quali_last'] = $query['discipline'] == 'combined' ||
			$query['route_type'] == THREE_QUALI_ALL_NO_STAGGER;
		$rows['quali_points'] = in_array($query['route_type'], array(TWO_QUALI_ALL,TWOxTWO_QUALI,THREE_QUALI_ALL_NO_STAGGER)) && $query['route'] != -6 ||
			$query['route_type'] == TWO_QUALI_GROUPS && in_array($query['route'], array(-4, -5));
		// display final points (multiplication of rank from 3 single discipline finals in combined)
		$rows['final_points'] = $query['discipline'] == 'combined' &&
			$query['route'] == -1 && isset($rows['route_names'][6]);
		// make div. print values available
		foreach(array('calendar','route_name','comp_name','comp_date','comp_logo','comp_sponsors','show_result','result_official','route_data') as $name)
		{
			$rows[$name] =& $query[$name];
		}
		// what columns to show for an athlete: can be set per comp. or has a national default
		switch($query['display_athlete'])
		{
			case Competition::NATION:
				$rows['no_ort'] = $rows['no_verband'] = $rows['no_acl_fed'] = true;
				break;
			default:
			case Competition::FEDERATION:
				$rows['no_ort'] = $rows['no_nation'] = $rows['no_acl_fed'] = true;
				break;
			case Competition::PC_CITY:
			case Competition::CITY:
				$rows['no_nation'] = $rows['no_verband'] = $rows['no_acl_fed'] = $rows['no_PerId'] = true;
				break;
			case Competition::NATION_PC_CITY:
				$rows['no_verband'] = $rows['no_acl_fed'] = $rows['no_PerId'] = true;
				break;
			case Competition::PARENT_FEDERATION:
				$rows['no_ort'] = $rows['no_verband'] = true;
				break;
			case Competition::FED_AND_PARENT:
				$rows['no_ort'] = $rows['no_nation'] = true;
				break;
		}
		switch($query['calendar'])
		{
			case 'SUI':
				$rows['acl_fed_label'] = 'Regionalzentrum';
				$rows['fed_label'] = 'Sektion';
				unset($rows['no_acl_fed']);
				break;
			case 'GER':
				$rows['acl_fed_label'] = 'LV';
				$rows['fed_label'] = 'DAV Sektion';
				break;
			default:
				$rows['fed_label'] = 'Federation';
				$rows['no_acl_fed'] = true;
		}
		// jury list --> switch extra columns on and all federation columns off
		$rows['no_jury_result'] = $rows['no_jury_time'] = $rows['no_remark'] = $query['ranking'] != 4;
		if ($query['ranking'] == 4)
		{
			$rows['no_ort'] = $rows['no_verband'] = $rows['no_acl_fed'] = true;
		}
		if ($query['route_type'] == TWO_QUALI_BESTOF)	// best of mode is quali AND final!
		{
			$rows['sum_or_bestof'] = lang('Best of');
			// 2. speed lane is only in quali for bestof
			$rows['show_second_lane'] = $query['route'] == 0;
		}
		elseif ($query['discipline'] == 'speed')
		{
			$rows['sum_or_bestof'] = lang('Sum');
			// 2. speed lane is only in two qualis (sum) and there also for final
			$rows['show_second_lane'] = $query['route_type'] != ONE_QUALI;
		}

		// disable checked and modified column for sitemgr or result display
		$rows['no_result_modified'] = $rows['no_check'] = !empty($GLOBALS['Common_BO']);

		return $total;
	}

	/**
	 * Check if cat is a paraclimbing one, using an ability percentage
	 *
	 * @param int $cat
	 * @return boolean
	 */
	static function needAbilityPercent($cat)
	{
		// enable for all categories, since 2012
		return 85 <= $cat && $cat <= 101 || $cat == 127;
		//return in_array($cat, array(95, 96));
	}

	/**
	 * Set options of the three athlete selectboxes, after nation changed
	 *
	 * @param int $comp
	 * @param int $cat
	 * @param int $team_id
	 * @param string $nation
	 */
	function ajax_set_athlets($comp,$cat,$team_id,$nation)
	{
		$response = Api\Json\Response::get();
		//$response->alert(__METHOD__."($comp,$cat,$team_id,'$nation')"); return;
		$starters = $this->get_registered(array(
			'WetId' => $comp,
			'GrpId' => $cat,
			'nation'=> $nation,
		));
		$id = 'exec[nm][rows][set]['.(int)$team_id.'][team_name]';
		$script = "document.getElementById('$id').value='$nation';";
		// delete existing options
		for($i = 1; $i <= 3; ++$i)
		{
			$id = 'exec[nm][rows][set]['.(int)$team_id.'][PerId_'.$i.']';
			$script .= "document.getElementById('$id').options.length=0;";
		}
		// set option "None" for last selectbox
		$label = lang('None');
		$script .= "selectbox_add_option('$id','$label','',false);";
		// add starter as options to all 3 selectboxes
		foreach($starters as $starter)
		{
			$label = $this->athlete->link_title($starter);
			$PerId = $starter['PerId'];
			for($i = 1; $i <= 3; ++$i)
			{
				$id = 'exec[nm][rows][set]['.(int)$team_id.'][PerId_'.$i.']';
				$script .= "selectbox_add_option('$id','$label','$PerId',false);";
			}
		}
		// set 3 different starter
		for($i = 1; $i <= 3; ++$i)
		{
			$starter = array_shift($starters);
			$PerId = $starter['PerId'];
			$id = 'exec[nm][rows][set]['.(int)$team_id.'][PerId_'.$i.']';
			$script .= "document.getElementById('$id').value = '$PerId';";
		}

		$response->script($script);
	}

	/**
	 * Return actions for start-/result-lists
	 *
	 * @param int|array $comp
	 * @param array $route
	 * @return array
	 */
	function get_actions($comp, $route)
	{
		if (!$comp || !$route) return array();

		$is_judge = $this->is_judge($comp, false, $route);	// incl. is_admin
		$is_route_judge = $is_judge || $this->is_judge($comp, false, $route + array('route_judges' => true));

		// we need to be at least a route-judge to do anything
		if (!$is_route_judge) return array();

		$actions =array(
			'edit' => array(
				'caption' => 'Edit',
				'default' => true,
				'onExecute' => 'javaScript:app.ranking.action_edit',
                'disableClass' => 'th',
				'allowOnMultiple' => false,
			),
			'measurement' => array(
				'caption' => 'Measurement',
				'onExecute' => 'javaScript:app.ranking.action_measure',
                'disableClass' => 'th',
				'icon' => 'bullet',
				'allowOnMultiple' => false,
			),
			'delete' => array(
				'caption' => 'Delete',
				'onExecute' => 'javaScript:app.ranking.action_delete',
                'disableClass' => 'noDelete',	// checks has result
				'allowOnMultiple' => false,
			),
		);

		// route-judges are not allowed to delete participants
		if (!$is_judge) $actions['delete']['enabled'] = false;

		// general result, does not allow edit or measurement
		if ($route['route_order'] < 0)
		{
			$actions['edit']['enabled'] = $actions['measurement']['enabled'] = $actions['delete']['enabled'] = false;
		}

		// speed does not (yet) implement measurement
		if (substr($route['discipline'], 0, 5) == 'speed')
		{
			$actions['measurement']['enabled'] = false;
		}

		return $actions;
	}

	/**
	 * Show a result / startlist
	 *
	 * @param array $content =null
	 * @param string $msg =''
	 * @param string $pstambl
	 * @param int $output_mode =0 2: popup, 4: ajax response, see Api\Etemplate::exec()
	 * @param boolean $update_checked =false
	 * @return type
	 */
	function index($content=null, $msg='', $pstambl='', $output_mode=0, $update_checked=false)
	{
		$tmpl = new Api\Etemplate('ranking.result.index');

		if ($tmpl->sitemgr && !count($this->ranking_nations))
		{
			return lang('No rights to any nations, admin needs to give read-rights for the competitions of at least one nation!');
		}
		if (!is_array($content))
		{
			$content = array('nm' => Api\Cache::getSession('ranking', 'result'));
			if (!is_array($content['nm']) || !$content['nm']['get_rows'])
			{
				if (!is_array($content['nm'])) $content['nm'] = array();
				$content['nm'] += array(
					'get_rows'   => 'ranking.ranking_result_ui.get_rows',
					'no_cat'     => true,
					'no_filter'  => true,
					'no_filter2' => true,
					'num_rows'   => 999,
					'order'      => 'start_order',
					'sort'       => 'ASC',
					'show_result'=> 1,
					'hide_header'=> true,
					'csv_fields' => array(
						'start_order'  => array('label' => lang('Startorder'),  'type' => 'int'),
						'start_number' => array('label' => lang('Startnumber'), 'type' => 'int'),
						'GrpId'        => array('label' => lang('Category'),    'type' => 'select'),
						'nachname'     => array('label' => lang('Lastname'),    'type' => 'text'),
						'vorname'      => array('label' => lang('Firstname'),   'type' => 'text'),
						'strasse'      => array('label' => lang('Street'),      'type' => 'text'),
						'plz'          => array('label' => lang('Postalcode'),  'type' => 'text'),
						'ort'          => array('label' => lang('City'),        'type' => 'text'),
						'geb_date'     => array('label' => lang('Birthdate'),   'type' => 'date'),
						'verband'      => array('label' => lang('Sektion'),     'type' => 'text'),
						'acl_fed'      => array('label' => lang('Regionalzentrum'),'type' => 'select'),
						'fed_parent'   => array('label' => lang('Landesverband'),'type' => 'select'),
						'email'        => array('label' => lang('EMail'),       'type' => 'text'),
					),
					//'row_id' => 'PerId',	// required for (not yet used) context menu, but stops input fields in NM from working!
				);
			}
			if ($_GET['calendar']) $content['nm']['calendar'] = $_GET['calendar'];
			if ($_GET['comp']) $content['nm']['comp'] = $_GET['comp'];
			if ($_GET['cat']) $content['nm']['cat'] = $_GET['cat'];
			if (is_numeric($_GET['route'])) $content['nm']['route'] = $_GET['route'];
			if ($_GET['msg']) $msg = $_GET['msg'];
			if (isset($_GET['show_result'])) $content['nm']['show_result'] = (int)$_GET['show_result'];

			$content['nm']['pstambl'] = $pstambl;
		}
		elseif ($content['nm']['show_result'] < 0)
		{
			$content['nm']['route'] = -1;
		}
		if($content['nm']['comp']) $comp = $this->comp->read($content['nm']['comp']);
		//echo "<p>calendar='$calendar', comp={$content['nm']['comp']}=$comp[rkey]: $comp[name]</p>\n";
		$disable_calendar = false;
		if ($tmpl->sitemgr && $_GET['comp'] && $comp)	// no calendar and/or competition selection, if in sitemgr the comp is direct specified
		{
			$readonlys['nm[calendar]'] = $readonlys['nm[comp]'] = true;
			$disable_calendar = true;
		}
		if ($this->only_nation)
		{
			$calendar = $this->only_nation;
			$disable_calendar = true;
		}
		elseif ($comp && (!$content['nm']['calendar'] || isset($_GET['comp'])))
		{
			$calendar = $comp['nation'] ? $comp['nation'] : 'NULL';
		}
		elseif ($content['nm']['calendar'])
		{
			$calendar = $content['nm']['calendar'];
		}
		else
		{
			$calendar = key($this->ranking_nations);
		}
		$tmpl->disableElement('nm[calendar]', $disable_calendar);
		if (!$comp || ($comp['nation'] ? $comp['nation'] : 'NULL') != $calendar)
		{
			//echo "<p>calendar changed to '$calendar', comp is '$comp[nation]' not fitting --> reset </p>\n";
			$comp = $cat = false;
			$content['nm']['route'] = '';	// dont show route-selection
		}
		if ($comp && (!($cat = $content['nm']['cat']) || !($cat = $this->cats->read($cat)) || !is_array($comp['gruppen']) || !in_array($cat['rkey'],$comp['gruppen'])))
		{
			$cat = false;
			$content['nm']['route'] = '';	// dont show route-selection
		}
		$keys = array(
			'WetId' => $comp['WetId'],
			'GrpId' => $cat['GrpId'],
			'route_order' => $content['nm']['route'] < 0 ? -1 : $content['nm']['route'],
		);
		if ($comp && ($content['nm']['old_comp'] != $comp['WetId'] ||		// comp changed or
			$cat && ($content['nm']['old_cat'] != $cat['GrpId'] || 			// cat changed or
			!($route = $this->route->read($keys)))))	// route not found and no general result
		{
			if ($content['nm']['show_result'] == 4 && is_numeric($keys['route_order']) &&
				($route = $this->route->read($keys)))
			{
				// cat changed in measurement and same route exists for new cat --> stay in measurement
			}
			else
			{
				$content['nm']['route'] = $keys['route_order'] = $this->route->get_max_order($comp['WetId'],$cat['GrpId']);
				if (!is_numeric($keys['route_order']) || !$this->has_startlist($keys))
				{
					if ($cat) $msg = lang('No startlist or result yet!');
					$content['nm']['show_result'] = '0';
				}
				elseif ($keys['route_order'] > 0)	// more then the quali --> show the general result
				{
					$content['nm']['route'] = $keys['route_order'] = -1;
				}
				else	// only quali --> show result if availible, else startlist
				{
					$content['nm']['show_result'] = $this->has_results($keys) ? '1' : '0';
				}
				if (is_numeric($keys['route_order'])) $route = $this->route->read($keys);
			}
		}
		elseif ($content['nm']['show_result'] == 4 && is_numeric($keys['route_order']))
		{
			// measurement
		}
		elseif ($comp && $cat && $keys['route_order'] >= 0 && !$this->has_startlist($keys))
		{
			$msg = lang('No startlist or result yet!');
			$content['nm']['show_result'] = '0';
		}
		// get discipline and check if relay
		$content['nm']['discipline'] = $comp['discipline'] ? $comp['discipline'] : $cat['discipline'];
		if (!empty($route['discipline'])) $content['nm']['discipline'] = $route['discipline'];
		if ($this->route_result->isRelay != ($content['nm']['discipline'] == 'speedrelay'))
		{
			$this->route_result->__construct($this->config['ranking_db_charset'],$this->db,null,
				$content['nm']['discipline'] == 'speedrelay');
		}
		// update route_type, in case it changed eg. by changing from a combined route/comp to a conventional one
		if ($route) $content['nm']['route_type'] = $route['route_type'];
		// set single discipline for combined depending our route_order/heat
		if ($content['nm']['discipline'] == 'combined' && $keys['route_order'] >= 0)
		{
			$content['nm']['discipline'] = $this->combined_order2discipline($keys['route_order'], $content['nm']['route_type']);
		}
		//echo "<p>calendar='$calendar', comp={$content['nm']['comp']}=$comp[rkey]: $comp[name], cat=$cat[rkey]: $cat[name], route={$content['nm']['route']}</p>\n";

		// check if user pressed a button and react on it
		$button = !empty($content['button']) ? key($content['button']) : null;
		unset($content['button']);
		if (!$button && $content['nm']['rows']['delete'])
		{
			$id = key($content['nm']['rows']['delete']);
			$button = 'delete';
		}
		if (!$button && $content['nm']['action'] && $content['nm']['selected'])
		{
			$button = $content['nm']['action']; unset($content['nm']['action']);
			$id = $content['nm']['selected']; unset($content['nm']['selected']);
		}
		// Apply button in a result-row pressed --> only update that row
		if(!$button && isset($content['nm']['rows']['apply']))
		{
			$PerId = key($content['nm']['rows']['apply']);
			$content['nm']['rows']['set'] = array(
				$PerId => $content['nm']['rows']['set'][$PerId],
			);
			// for speed only one lane got submitted
			if ($content['nm']['discipline'] == 'speed')
			{
				foreach(is_array($content['nm']['rows']['apply'][$PerId]) ?	// right lane
					array('result_time','eliminated') : array('result_time_r','eliminated_r') as $name)
				{
					unset($content['nm']['rows']['set'][$PerId][$name]);
				}
			}
			unset($content['nm']['rows']['apply']);
			$button = 'apply';
		}
		if ($button && $comp && $cat && is_numeric($content['nm']['route']))
		{
			//echo "<p align=right>$comp[rkey] ($comp[WetId]), $cat[rkey]/$cat[GrpId], {$content['nm']['route']}, button=$button</p>\n";
			switch($button)
			{
				case 'apply':
					$old = Api\Cache::getSession('ranking', 'set_'.$content['nm']['comp'].'_'.$content['nm']['cat'].'_'.$content['nm']['route']);
					if (is_array($content['nm']['rows']['set']) &&
						$this->save_result($keys,$content['nm']['rows']['set'],$content['nm']['route_type'],$content['nm']['discipline'],$old,
							$this->comp->quali_preselected($content['nm']['cat'], $comp['quali_preselected']),
							$update_checked, $content['nm']['order'].' '.$content['nm']['sort']))
					{
						$msg = lang('Heat updated');
					}
					else
					{
						$msg = lang('Nothing to update');
					}
					if ($this->error)
					{
						foreach($this->error as $PerId => $data)
						{
							foreach($data as $field => $error)
							{
								$tmpl->set_validation_error("nm[rows][set][$PerId][$field]",$error);
								$errors[$error] = $error;
							}
						}
						$msg = lang('Error').': '.implode(', ',$errors);
					}
					break;

				case 'download':
					$this->download($keys);
					break;

				case 'delete':
					if (!is_numeric($id) ||
						!$this->delete_participant($keys+array($this->route_result->id_col => $id)))
					{
						$msg = $this->has_results($keys+array($this->route_result->id_col => $id)) ?
							lang('Has already a result!') : lang('Permission denied !!!');
						//error_log(__METHOD__."() id=$id, acl_check('$comp[nation], self::ACL_RESULT, \$comp)=".array2string($this->acl_check($comp['nation'],self::ACL_RESULT,$comp)));
					}
					else
					{
						$msg = lang('Participant deleted');
					}
					break;
			}
		}
		unset($content['nm']['rows']);

		// create new view
		$sel_options = array(
			'calendar' => $this->ranking_nations,
			'comp'     => $this->comp->names(array(
				'nation' => $calendar,
				'datum < '.$this->db->quote(date('Y-m-d',time()+23*24*3600)),	// starting 23 days from now
				'datum > '.$this->db->quote(date('Y-m-d',time()-365*24*3600)),	// until one year back
				'gruppen IS NOT NULL',
			), 3, 'datum DESC'),
			'cat'      => $this->cats->names(array('rkey' => $comp['gruppen']),0),
			'route'    => $comp && $cat ? $this->route->query_list('route_name','route_order',array(
				'WetId' => $comp['WetId'],
				'GrpId' => $cat['GrpId'],
			),'route_order DESC') : array(),
			'result_plus' => $this->plus_labels((int)$comp['datum'], $comp['nation'], $content['nm']['discipline']),
			'show_result' => array(
				0 => lang('Startlist'),
				1 => lang('Resultlist'),
			),
			'eliminated' => $this->eliminated_labels,
			'eliminated_r' => $this->eliminated_labels,
			'ranking' => array(
				1 => 'display ranking',
			) + ($comp['serie'] ? array(2 => 'display cup') : array()) + array(
				4 => 'Show jurylist',
			),
			'try' => array(0 => ' '),
		);
		if ($comp && !isset($sel_options['comp'][$comp['WetId']]))
		{
			$sel_options['comp'][$comp['WetId']] = Api\DateTime::to($comp['datum'], true).': '.$comp['name'];
		}
		if ($content['nm']['route'] < 2) unset($sel_options['eliminated'][0]);
		unset($sel_options['eliminated_r'][0]);
		if (is_array($route)) $content += $route;
		$content['nm']['route_data'] = $route;
		$content['nm']['calendar'] = $calendar;
		$content['nm']['comp']     = $content['nm']['old_comp']= $comp ? $comp['WetId'] : null;
		$content['nm']['cat']      = $content['nm']['old_cat'] = $cat ? $cat['GrpId'] : null;
		if (empty($content['nm']['route_type'])) $content['nm']['route_type'] = $route['route_type'];
		$content['nm']['route_status'] = $route['route_status'];
		$content['nm']['num_problems'] = $route['route_num_problems'];
		$content['nm']['time_measurement'] = $route['route_time_host'] && $route['route_status'] != STATUS_RESULT_OFFICIAL;
		$this->set_ui_state($calendar,$comp['WetId'],$cat['GrpId']);

		// make competition and category data availible for print
		$content['comp'] = $comp;
		$content['cat']  = $cat;
		$content['nm']['comp_name'] = $comp['name'];
		$content['nm']['comp_date'] = $comp['date_span'];
		$content['nm']['quali_preselected'] = $this->comp->quali_preselected($cat['GrpId'], $comp['quali_preselected']);
		$content['nm']['display_athlete'] = $comp['display_athlete'];

		unset($content['nm']['comp_logo']);
		unset($content['nm']['comp_sponsors']);
		$docroot_base_path = '/'.(int)$comp['datum'].'/'.($calendar != 'NULL' ? $calendar.'/' : '');
		foreach((array)$this->comp->attachments($comp,true,false) as $type => $linkdata)
		{
			if ($type == 'logo' || $type == 'sponsors')
			{
				$content['nm']['comp_'.$type] = substr($linkdata, 0, 4) == 'http' ? $linkdata : Api\Egw::link($linkdata);
				// check if images are directly reachable from the docroot --> use them direct
				if (file_exists($_SERVER['DOCUMENT_ROOT'].$docroot_base_path.basename($linkdata)))
				{
					$content['nm']['comp_'.$type] = $docroot_base_path.basename($linkdata);
				}
			}
		}
		$content['msg'] = $msg;

		if (count($sel_options['route']) > 1)	// more then 1 heat --> include a general result
		{
			$this->add_general_result_options($sel_options['route'], $sel_options['show_result'], $route['route_type'], $route['discipline']);
			// if general result is suppressed, go for first in list, as that is what is displayed to user anyway
			if (!isset($sel_options['route'][$content['nm']['route']]))
			{
				$content['nm']['route'] = key($sel_options['route']);
			}
		}
		elseif ($content['nm']['route'] == -1)	// general result with only one heat --> show quali if exist
		{
			$keys['route_order'] = $content['nm']['route'] = '0';
			if (!($route = $this->route->read($keys))) $keys['route_order'] = $content['nm']['route'] = '';
		}
		// add measurement for judges and admins, if on a regular lead route (not on general result)
		if ($comp && $cat && ($this->is_judge($comp,false,$route) || $this->is_admin || $content['nm']['discipline'] == 'selfscore') &&
			$content['nm']['route'] != -1 && in_array($content['nm']['discipline'], array('lead','boulder','boulder2018','selfscore')))
		{
			$sel_options['show_result'][4] = lang('Measurement');
		}
		elseif($content['nm']['show_result'] == 4)
		{
			$content['nm']['show_result'] = 0;	// switch measurement off, if eg. no cat selected
		}
		//_debug_array($sel_options);

		// enabling download for general result too (if we have at least a quali startlist)
		$readonlys['button[download]'] = !($this->has_startlist($keys) || $keys['route_order'] == -1 && $this->has_startlist(array('route_order'=>0)+$keys));
		$content['no_route_selection'] = !$cat; 	// no cat selected
		$onclick = "egw.open_link('ranking.ranking_result_ui.route&comp={$content['nm']['comp']}&cat={$content['nm']['cat']}','result_route','700x500','ranking')";
		if (!$this->acl_check($comp['nation'],self::ACL_RESULT,$comp))	// no judge
		{
			$readonlys['button[new]'] = true;
			$readonlys['button[edit]'] = !$this->is_judge($comp, false, $route);

			if (!is_numeric($keys['route_order']) || !$sel_options['route']) $content['no_route_selection'] = true;	// no route yet
		}
		elseif (!is_numeric($keys['route_order']) || !$sel_options['route'])	// no route yet
		{
			$readonlys['nm[route]'] = $readonlys['button[edit]'] = true;
		}
		elseif ($comp && $cat)	// check if the highest heat has a result
		{
			$last_heat = $keys;
			$last_heat['route_order'] = $this->route_result->get_max_order($comp['WetId'],$cat['GrpId']);
			if (!$this->is_admin && !$this->has_results($last_heat) && ($last_heat['route_order'] >= 3 || !in_array($route['route_type'],array(TWO_QUALI_ALL,TWOxTWO_QUALI))))
			{
				$last_heat = $this->route->read($last_heat);
				$onclick = "alert('".
					addslashes(lang("You can only create a new heat, if the previous one '%1' has a result!",$last_heat['route_name'])).
					"'); return false;";
			}
		}
		$tmpl->setElementAttribute('button[new]', 'onclick', $onclick);
		// check if the type of the list to show changed: startlist, result or general result
		// --> set template and default order
		if ($content['nm']['show_result'] == 2 && $content['nm']['old_show'] != 2)
		{
			$content['nm']['route'] = -1;
		}
		elseif ($content['nm']['show_result'] == 3 && $content['nm']['old_show'] != 3)
		{
			$content['nm']['route'] = -2;
		}
		elseif ($content['nm']['show_result'] == 4)
		{
			// measurement code is in extra class
			switch($content['nm']['discipline'])
			{
				case 'lead':
					ranking_measurement::measurement($content, $sel_options, $readonlys, $tmpl);
					$content['measurement_template'] = 'ranking.result.measurement';
					break;
				case 'selfscore':
					ranking_selfscore_measurement::measurement($content, $sel_options, $readonlys, $tmpl);
					$tmpl->setElementAttribute('button[apply]', 'class', 'scorecard_button');
					$content['measurement_template'] = 'ranking.result.selfscore_measurement';
					break;
				case 'boulder':
				case 'boulder2018':
					ranking_boulder_measurement::measurement($content, $sel_options, $readonlys, $tmpl);
					$content['measurement_template'] = 'ranking.result.boulder_measurement';
					break;
			}
			// measurement need to store nm, as it does NOT call get_rows!
			Api\Cache::setSession('ranking', 'result', $content['nm']);
		}
		elseif ($content['nm']['show_result'] || $content['nm']['route'] < 0)
		{
			$content['nm']['show_result'] = $content['nm']['route'] < 0 ? ($content['nm']['route'] == -2 ? 3 : 2) : 1;
		}

		// need to reload route, if eg. general result was selected by show_result=2
		if (!$route || $route['route_order'] != $content['nm']['route'])
		{
			$keys['route_order'] = $content['nm']['route'];
			$route = $this->route->read($keys);
		}
		$content['nm']['is_judge'] = $this->is_judge($comp);	// is a (full, not just route-)judge
		$tmpl->setElementAttribute('nm[rows]', 'actions', $this->get_actions($comp, $route));

		// no startlist, no rights at all or result offical -->disable all update possebilities
		if (($readonlys['button[apply]'] =
			!($content['nm']['discipline'] == 'speedrelay' && !$keys['route_order']) && !$this->has_startlist($keys) ||
			!$this->acl_check($comp['nation'],self::ACL_RESULT,$comp,false,$route) &&
			!($content['nm']['discipline'] == 'selfscore' && $this->is_selfservice() && $content['nm']['show_result'] == 4) ||
			$route['route_status'] == STATUS_RESULT_OFFICIAL ||
			$content['nm']['route'] < 0 || $content['nm']['show_result'] > 1 && $content['nm']['show_result'] != 4))
		{
			$sel_options['result_plus'] = $this->plus;
			$content['nm']['readonly'] = true;
		}
		else
		{
			unset($content['nm']['readonly']);
		}
		//echo "<p>route_order={$content['nm']['route']}, show_result={$content['nm']['show_result']}: readonly=".array2string($content['nm']['readonly'])."</p>\n";

		if ($content['nm']['route'] < 0)	// general result --> hide show_route selection
		{
			$sel_options['show_result'] = array(2 => ' ');
			$readonlys['nm[show_result]'] = true;
		}
		if ($content['nm']['discipline'] == 'speedrelay')
		{
			// todo show only nations registered for the competitiion
			//$sel_options['team_nation'] = $this->federation->query_list('nation','nation');
			$sel_options['team_nation'] = $this->get_registered(array(
				'WetId' => $comp['WetId'],
				'GrpId' => $cat['GrpId'],
			),true);
		}
		if ((string)$content['nm']['old_show'] !== (string)$content['nm']['show_result'])
		{
			if ($content['nm']['route'] < 0)	// general result
			{
				$content['nm']['order'] = 'result_rank';
			}
			else
			{
				$content['nm']['order'] = $content['nm']['show_result'] ? 'result_rank' : 'start_order';
			}
			$content['nm']['sort'] = 'ASC';
			$content['nm']['old_show'] = $content['nm']['show_result'];
		}
		// dont show ranking in result of via sitemgr
		$tmpl->disableElement('nm[ranking]', $content['nm']['show_result'] || $tmpl->sitemgr || !$this->has_startlist($keys));
		// do we show the start- or result-list?
		$content['no_list'] = (string)$content['nm']['route'] === '' || $content['nm']['show_result'] == 4;
		$content['nm']['template'] = $this->_template_name($content['nm']['show_result'],
			$content['nm']['discipline'], $content['discipline'], $comp['datum']);
		// quota, to get a quota line for _official_ result-lists --> get_rows sets css-class quota_line on the table-row _below_
		$content['nm']['route_quota'] = $content['nm']['show_result'] && $route['route_status'] == STATUS_RESULT_OFFICIAL ? $route['route_quota'] : 0;

		// should we show the result offical footer?
		$content['nm']['result_official'] = $content['nm']['show_result'] && $route['route_status'] == STATUS_RESULT_OFFICIAL;

		// create a nice header
		$content['nm']['route_name'] = $GLOBALS['egw_info']['flags']['app_header'] =
			trim(/*lang('Ranking').' - '.*/(!$comp || !$cat ? lang('Resultservice') :
			($content['nm']['show_result'] == '0' && $route['route_status'] == STATUS_UNPUBLISHED ||
			 $content['nm']['show_result'] != '0' && $route['route_status'] != STATUS_RESULT_OFFICIAL ? lang('provisional').' ' : '').
			(isset($sel_options['show_result'][(int)$content['nm']['show_result']]) ? $sel_options['show_result'][(int)$content['nm']['show_result']].' ' : '').
			($cat ? (isset($sel_options['route'][$content['nm']['route']]) ? $sel_options['route'][$content['nm']['route']].' ' : '').$cat['name'] : '')));

		// for speed with two qualification rename "Startorder" to "Lane A"
		if (!$route['route_order'] && $content['nm']['discipline'] == 'speed' &&
			in_array($content['nm']['route_type'], array(TWO_QUALI_SPEED,TWO_QUALI_BESTOF)))
		{
			// eTemplate2: $tmpl->setElementAttribute('start_order', 'label', lang('Lane A'));
			$content['start_order_label'] = lang('Lane A');
		}
		else
		{
			$content['start_order_label'] = lang('Start- order');
		}
		$preserv = array(
			'nm' => $content['nm'],
		);
		// fake call to get_rows()
		if (!$content['no_list'])
		{
			$this->get_rows($content['nm'], $content['nm']['rows'], $readonlys);
			array_unshift($content['nm']['rows'], false, false, false);	// 3 header rows
		}
		//_debug_array($content); exit;
		return $tmpl->exec('ranking.ranking_result_ui.index', $content, $sel_options, $readonlys, $preserv, $output_mode);
	}

	/**
	 * Add diverse general- and qualification-results to route-names
	 *
	 * @param array& $route route-names read from db
	 * @param array& $show_result startlist, result selection
	 * @param int $route_type (ONE|TWO)_QUALI*
	 * @param string $discipline lead, boulder, speed, ...
	 */
	protected function add_general_result_options(array &$route, array &$show_result, $route_type, $discipline)
	{
		// make sure real heats are sorted to the end of the array
		uksort($route, function($a, $b)
		{
			if ($a < 0) $a += 100;
			if ($b < 0) $b += 100;

			return $b - $a;
		});

		// for speed include pairing graph (-2)
		if (substr($discipline, 0, 5) == 'speed' && $route_type != THREE_QUALI_ALL_NO_STAGGER)
		{
			$show_result[3] = lang('Pairing speed final');
			$route = array(-2 => lang('Pairing speed final'))+$route;
		}

		// add qualification result (-3)
		if (self::is_two_quali_all($route_type) || $route_type == TWO_QUALI_HALF || $route_type == THREE_QUALI_ALL_NO_STAGGER)
		{
			$qualis = 2+(int)($route_type == THREE_QUALI_ALL_NO_STAGGER);
			// add Group A/B result (-4/-5) above the 4 qualifications
			if ($route_type == TWO_QUALI_GROUPS)
			{
				$groupA = isset($route[-5]) ? $route[-5] : ranking_route::default_name(-5);
				$groupB = isset($route[-4]) ? $route[-4] : ranking_route::default_name(-4);
				unset($route[-4], $route[-5]);
				$route = array_slice($route, 0, -4, true) + array(-4 => $groupB, -5 => $groupA) + array_slice($route, -4, null, true);
				$qualis = 6;	// 4 qualis + 2 groups
			}

			// add overall qualification result above all qualification and below next heat eg. 1/2-final
			$quali = isset($route[-3]) ? $route[-3] : ranking_route::default_name(-3);
			unset($route[-3]);
			$route = array_slice($route, 0, -$qualis, true) + array(-3 => $quali) + array_slice($route, -$qualis, null, true);

			// count real heats, not general results
			for($num_routes=0; isset($route[$num_routes]); ++$num_routes)
			{

			}
			if ($num_routes <= 2 || $route_type == TWO_QUALI_GROUPS && $num_routes <= 4) return;	// dont show general result yet
		}

		// add overall speed final and pairing for combined
		if ($discipline == 'combined' && isset($route[3]))
		{
			$speed_final = isset($route[-6]) ? $route[-6] : ranking_route::default_name(-6);
			unset($route[-6]);
			$route = array_slice($route, 0, -7, true) + array(
				-6 => $speed_final,
				-2 => lang('Pairing speed final'),
			) + array_slice($route, -7, null, true);
			$show_result[3] = lang('Pairing speed final');
		}

		// add general result (-1) on top of list
		$label =  isset($route[-1]) ? $route[-1] : ranking_route::default_name(-1);
		unset($route[-1]);
		$route = array(-1 => $label) + $route;
		$show_result[2] = ranking_route::default_name(-1);
	}

	/**
	 * Uncheck a boulder result-row
	 *
	 * @param array $keys values for keys WetId, GrpId, route, PerId
	 */
	function ajax_uncheck_result(array $keys)
	{
		$this->index(array(
			'nm' => array(
				'comp' => $keys['WetId'],
				'old_comp' => $keys['WetId'],
				'cat' => $keys['GrpId'],
				'old_cat' => $keys['GrpId'],
				'route' => $keys['route'],
				'rows' => array(
					'apply' => array($keys['PerId'] => 'pressed'),
					'set' => array(
						$keys['PerId'] => array('checked' => false),
					),
				),
				'show_result' => '1',
			),
		), '', '', 4, true);
	}

	/**
	 * Update a result of a single participant
	 *
	 * @param array $keys
	 * @param int PerId
	 * @param array $set
	 * @param boolean $update_checked =false
	 * @return string
	 */
	function ajax_update(array $keys, $PerId, array $set, $update_checked=false)
	{
		//$start = microtime(true);
		$response = Api\Json\Response::get();

		if (!($comp = $this->comp->read($keys['WetId'])))
		{
			$response->call('egw.message', lang('Error').': '.lang('Competition not found!'), 'error');
			return;
		}
		if (!($route = $this->route->read($keys)))
		{
			$response->call('egw.message', lang('Error').': '.lang('Route not found!'), 'error');
			return;
		}
		if ($route['discipline'] == 'combined')
		{
			$route['discipline'] = $this->combined_order2discipline($route['route_order'], $route['route_type']);
			$combined = true;
		}
		$query = Api\Cache::getSession('ranking', 'result');
		// set query by $parametes, if we have no session, eg. just a json request to set data
		if (empty($query) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') === 0)
		{
			$query = array(
				'calendar' => empty($comp['nation']) ? 'NULL' : $comp['nation'],
				'comp' => $comp['WetId'],
				'cat'  => $route['GrpId'],
				'num_rows' => 999,
				'show_result' => 1,
				'discipline' => $route['discipline'],
				'route_type' => $route['route_type'],
				'route_data' => $route,
			);
		}
		$order_by = $query['order'].' '.$query['sort'];
		if (!preg_match('/^[a-z0-9_]+ (asc|desc)$/i', $order_by)) $order_by = 'start_order ASC';

		//error_log(__METHOD__."(".array2string($keys).", $PerId, ".array2string($set).", $update_checked) order_by=$order_by");
		if ($route['route_status'] == STATUS_RESULT_OFFICIAL)
		{
			$msg = lang('Result already official!');
		}
		elseif ($this->save_result($keys, array($PerId => $set), $route['route_type'],  $route['discipline'], null,
			$this->comp->quali_preselected($keys['GrpId'], $comp['quali_preselected']), $update_checked, $order_by))
		{
			// search filter needs route_type to not give SQL error
			$filter = $keys+array('PerId' => $PerId,'route_type' => $route['route_type'], 'discipline' => $route['discipline'], 'combined' => $combined);
			list($new_result) = $this->route_result->search(array(),false,'','','',false,'AND',false,$filter);
			$msg = ranking_result_bo::athlete2string($new_result,true);
		}
		elseif ($this->error)
		{
			$msg = implode("\n - ", call_user_func_array('array_merge_recursive', $this->error));
		}
		else
		{
			$msg = lang('Nothing to update');
		}

		// return full data of all participants
		$data = array('msg' => $msg);
		$this->get_rows($query, $data['content'], $data['readonlys']);
		array_unshift($data['content'], false, false, false);	// 3 header rows
		$response->data($data);
	}

	/**
	 * Get co-participant for given PerId/team_id and route (specified by $keys)
	 *
	 * @param array $keys
	 * @param int $id PerId or team_id
	 * @param boolean &$id_isnt_co=null true if id is NOT the co, false otherwise
	 * @return NULL|int
	 */
	protected function get_co(array $keys,$id,&$id_isnt_co=null)
	{
		if (!($results = $this->route_result->search($keys,false,'start_order')))
		{
			//error_log(__METHOD__."(".array2string($keys).",$id) returning NULL (no results)");
			return null;
		}
		foreach($results as $n => $result)
		{
			if ($result[$this->route_result->id_col] == $id)
			{
				if (($id_isnt_co = ($result['start_order'] & 1 == 1)))
				{
					$co = $results[$n+1];
				}
				else
				{
					$co = $results[$n-1];
				}
				//error_log(__METHOD__."(".array2string($keys).",{$this->route_result->id_col}=$id,".array2string($id_isnt_co).") returning ".array2string($co[$this->route_result->id_col]));
				return $co[$this->route_result->id_col];
			}
		}
		//error_log(__METHOD__."(".array2string($keys).",{$this->route_result->id_col}=$id) returning NULL (id NOT found)".array2string($results));
		return null;
	}

	/**
	 * Get the template name depending on show_result and discipline
	 *
	 * @param int $show_result 0=startlist, 1=result, 2=general result
	 * @param string $discipline 'lead', 'boulder', 'speed'
	 * @param string $discipline2 'combined' even if heat has single discipline
	 * @param string $date =null competition date (currently used to distinguish 2018 combined system from 2019+ one)
	 * @return string
	 */
	function _template_name($show_result, $discipline='lead', $discipline2=null, $date=null)
	{
		if ($show_result == 3)
		{
			return $discipline2 !== 'combined' ? 'ranking.result.index.speed_graph' :
				((int)$date > 2018 ? 'ranking.result.index.speed_graph8' : 'ranking.result.index.speed_graph6');
		}
		if ($show_result == 2)
		{
			switch($discipline)
			{
				case 'speedrelay':
					return 'ranking.result.index.rows_relay_general';
				default:
					return 'ranking.result.index.rows_general';
			}
		}
		if ($show_result)
		{
			switch($discipline)
			{
				default:
				case 'lead':    return 'ranking.result.index.rows_lead';
				case 'speed':   return 'ranking.result.index.rows_speed';
				case 'selfscore':
				case 'boulder2018':
				case 'boulder': return 'ranking.result.index.rows_boulder';
				case 'speedrelay': return 'ranking.result.index.rows_relay_speed';
			}
		}
		// startlist
		switch($discipline)
		{
			case 'speedrelay':
				return 'ranking.result.index.rows_relay';
			default:
				return 'ranking.result.index.rows_startlist';
		}
	}

	/**
	 * Start the time measurement for $PerId
	 *
	 * @param string $request_id
	 * @param int $PerId
	 * @return string
	 */
	function ajax_time_measurement($request_id,$PerId)
	{
		//$start = microtime(true);
		$response = Api\Json\Response::get();

		if (!($request = Api\Etemplate\Request::read($request_id)))
		{
			$response->alert(lang('Result form is timed out, please reload the form by clicking on the application icon.'));
			return $this->_stop_time_measurement($response);
		}
		$keys = array(
			'WetId' => $request->preserv['nm']['comp'],
			'GrpId' => $request->preserv['nm']['cat'],
			'route_order' => $request->preserv['nm']['route'],
		);
		if (!($route = $this->route->read($keys)) ||
			!($old_result = $this->route_result->read($keys+array('PerId'=>$PerId))) ||
			!($PerId < 0 && $route['route_order'] >= 2) && !($athlete = $this->athlete->read($PerId)))
		{
			$response->alert("internal error: ".__FILE__.': '.__LINE__);
			return $this->_stop_time_measurement($response);
		}
		$timy = new ranking_time_measurement($route['route_time_host'],$route['route_time_port']);

		if (!$timy->is_connected())
		{
			$response->alert(lang("Can't connect to time controll program at '%1': %2",$route['route_time_host'].':'.$route['route_time_port'],$timy->error));
			return $this->_stop_time_measurement($response);
		}
		// allow the request to run max. 15min and close the session, to not block other request from that session
		set_time_limit(900);
		$GLOBALS['egw']->session->commit_session();

		// check if we measure two participants (quali on two routes or final) or just one (quali on one route)
		if ($route['route_order'] >= 2 || $route['route_type'] != ONE_QUALI)
		{
			if ($athlete && (string)$old_result['eliminated_r'] === '')	// real athlete, no wildcard and not eliminated
			{
				$side = '';		// we do both sides
				$side1 = 'l';
				$side2 = 'r';
			}
			else	// wildcard left
			{
				$side = $side2 = 'r';
				$athlete = null;
			}
			// find out the other participant
			if ($route['route_order'] < 2 && $route['route_type'] == TWO_QUALI_BESTOF)
			{
				$side1 = 'l';
				$side2 = 'r';
				list($old_other) = $this->route_result->search($keys+array('start_order2n'=>$old_result['start_order']),false);
				$other_sorder = null;
			}
			elseif ($route['route_order'] < 2 && $old_result['result_time'])	// quali and already measured
			{
				$side1 = 'r';
				$side2 = 'l';
				$other_sorder = $old_result['start_order'] + 1;
			}
			else
			{
				$other_sorder = $old_result['start_order'] + ($route['route_order'] >= 2 ? ($old_result['start_order']&1 ? 1 : -1) : -1);
			}
			if ($other_sorder) list($old_other) = $this->route_result->search($keys+array('start_order'=>$other_sorder),false);
			if (!$old_other)
			{
				if ($route['route_order'] < 2)
				{
					// last participant starting on right
					$side1 = $side = $other_sorder ? 'r' : 'l';
				}
				else
				{
					// other participant not found --> error
					$response->alert(lang("Can't find co-participant!"));
					$timy->close();
					return $this->_stop_time_measurement($response);
				}
			}
			elseif ($old_other['PerId'] > 0 && (string)$old_other['elimitated'] === '')	// real other participant and not eliminated
			{
				if (!($other_athlete = $this->athlete->read($other_PerId=$old_other['PerId'])))
				{
					// other participant not found --> error
					$response->alert(lang("Can't find co-participant!"));
					$timy->close();
					return $this->_stop_time_measurement($response);
				}
			}
			elseif ($old_other['PerId'] < 0)	// wildcard as other participant
			{
				$side = $side1;
				$old_other = null;
			}
		}
		else
		{
			$side1 = $side = 'l';	// only one side, maybe this should be configurable in future
		}
		$startnr = $old_result['start_number'] ? $old_result['start_number'] : $old_result['start_order'];
		if ($athlete)
		{
			$timy->send("start:$side1:$startnr:".$old_result['time_sum'].':'.$athlete['nachname'].', '.$athlete['vorname'].' ('.$athlete['nation'].')');
		}
		elseif ($route['route_order'] >= 2 || $route['route_type'] != ONE_QUALI && $route['route_type'] != TWO_QUALI_BESTOF)	// two routes with only one climber, set other to 0
		{
			$timy->send("start:l:0");
		}
		if ($old_other)
		{
			$time = is_numeric($old_result['time_sum']) ? $old_result['time_sum'] : '';
			$other_snr = $old_other['start_number'] ? $old_other['start_number'] : $old_other['start_order'];
			$timy->send("start:$side2:$other_snr:".$old_other['time_sum'].':'.$other_athlete['nachname'].', '.$other_athlete['vorname'].' ('.$other_athlete['nation'].')');
		}
		elseif ($route['route_order'] >= 2 || $route['route_type'] != ONE_QUALI && $route['route_type'] != TWO_QUALI_BESTOF)	// two routes with only one climber, set other to 0
		{
			$s = $side1 == 'l' ? 'r' : 'l';
			$timy->send("start:$s:0");
		}
		$timy->send('notify:'.$side);

		if(($dsp_id=$route['dsp_id']) && ($frm_id=$route['frm_id']))
		{
			// add display update(s)
			$display = new ranking_display($this->db);
			if ($route['dsp_id2'] && $route['frm_id2'])
			{
				$dsp_id2 = $route['dsp_id2'];
				$frm_id2 = $route['frm_id2'];
			}
			else
			{
				$dsp_id2 = $route['dsp_id'];
				$frm_id2 = $route['frm_id'];
			}
			$display->activate($frm_id,$PerId,$dsp_id,$keys['GrpId'],$keys['route_order']);
			if ($other_athlete) $display->activate($frm_id2,$other_PerId,$dsp_id2,$keys['GrpId'],$keys['route_order']);
		}
		//error_log("***** waiting for Timy responses ...");
		$stop = $ranking_changed = false;
		while (!$stop)
		{
			if (!($str = $timy->receive()) && !$timy->is_connected()) break;

			list($event_side,$event,$time,$time2) = explode(':',$str);
			error_log("timy->receive()=".$str);

			switch($event)
			{
				case 'start':
					if (!$side && $event_side == 'l') break;	// ignore 2. start event
					if (is_object($display))
					{
						$display->activate($frm_id,$PerId,$dsp_id,$keys['GrpId'],$keys['route_order']);
						if ($other_athlete) $display->activate($frm_id2,$other_PerId,$dsp_id2,$keys['GrpId'],$keys['route_order']);
					}
					break;

				case 'stop':
					$result = $event_side == 'l' || $route['route_order'] >= 2 && $route['route_type'] == TWO_QUALI_BESTOF ?
						'result_time' : 'result_time_r';
					$got_result = $event_side;
					if ($event_side == $side1)	// side1
					{
						$this->save_result($keys,array($PerId=>array(
							$result => $time,
						)),$route['route_type'],'speed');
						$new_result = $this->route_result->read($keys+array('PerId'=>$PerId));
						$response->assign("exec[nm][rows][set][$PerId][$result]",'value',$time);
						$response->assign("set[$PerId][time_sum]",'innerHTML',$new_result['time_sum']);
						if ($new_result && $new_result['result_rank'] != $old_result['result_rank'])	// the ranking has changed
						{
							$ranking_changed = true;
						}
						if (is_object($display)) $display->activate($frm_id,$PerId,$dsp_id,$keys['GrpId'],$keys['route_order']);
					}
					else	// other participant
					{
						$this->save_result($keys,array($other_PerId=>array(
							$result => $time,
						)),$route['route_type'],'speed');
						$new_other_result = $this->route_result->read($keys+array('PerId'=>$other_PerId));
						$response->assign("exec[nm][rows][set][$other_PerId][$result]",'value',$time);
						$response->assign("set[$other_PerId][time_sum]",'innerHTML',$new_other_result['time_sum']);
						if ($new_other_result && $new_other_result['result_rank'] != $old_other['result_rank'])	// the ranking has changed
						{
							$ranking_changed = true;
						}
						if (is_object($display)) $display->activate($frm_id2,$other_PerId,$dsp_id2,$keys['GrpId'],$keys['route_order']);
					}
					if ($side || isset($new_result) && isset($new_other_result))	// all athletes measured
					{
						if ($ranking_changed)
						{
							$this->_update_ranks($keys,$response,$request);
						}
						$stop = true;
					}
					break;

				case 'false':
					if ($event_side != 'r')	// left (or both) false start
					{
						$this->save_result($keys,array($athlete['PerId']=>array(
							'false_start' => ++$athlete['false_start'],
						)),$route['route_type'],'speed');
						$new_result = $this->route_result->read($keys+array('PerId'=>$athlete['PerId']));
						$ranking_changed = $new_result['result_rank'] != $athlete['result_rank'];
						$response->assign("exec[nm][rows][set][$athlete[PerId]][false_start]", 'value', $athlete['false_start']);
					}
					if ($event_side != 'l')	// right (or both) false start
					{
						$this->save_result($keys,array($other_athlete['PerId']=>array(
							'false_start' => ++$other_athlete['false_start'],
						)),$route['route_type'],'speed');
						$new_result = $this->route_result->read($keys+array('PerId'=>$other_athlete['PerId']));
						$ranking_changed = $ranking_changed || $new_result['result_rank'] != $other_athlete['result_rank'];
						$response->assign("exec[nm][rows][set][$other_athlete[PerId]][false_start]", 'value', $other_athlete['false_start']);
					}
					if ($ranking_changed) $this->_update_ranks ($keys, $response, $request);
					$response->alert(lang('False start %1: %2',$event_side != 'r' ? lang('left') : lang('right'),
						$event_side != 'b' ? $time : $time2).($event_side == 'b' ? ', '.lang('right').': '.$time : ''));
					//if (is_object($display)) $display->activate($frm_id,$PerId,$dsp_id,$keys['GrpId'],$keys['route_order']);
					$stop = true;
					break;
			}
		}
		//error_log("***** closing connection to Timy: stop=$stop");
		$timy->close();

		if (!$stop)
		{
			switch ($got_result)	// we have only one result --> set other one to fall
			{
				case 'l':
					$fallen = $other_athlete+$old_other;
					$fallen_postfix = '_r';
					break;
				case 'r':
					$fallen = $athlete+$old_result;
					$fallen_postfix = '';
					break;
			}
			if ($fallen)
			{
				$this->save_result($keys,array($fallen['PerId']=>array(
					'eliminated'.$fallen_postfix => '1',
				)),$route['route_type'],'speed');
				$response->assign("exec[nm][rows][set][$fallen[PerId]][eliminated$fallen_postfix]", 'value', '1');
				$this->_update_ranks($keys, $response, $request);
				return $this->_stop_time_measurement($response,lang('Set "%1" to %2.', $fallen['nachname'].', '.$fallen['vorname'].
					' ('.($fallen['start_number']?$fallen['start_number']:$fallen['start_order']).')', lang('Fall')));
			}
			return $this->_stop_time_measurement($response,lang('Measurement aborted!'));
		}
		//error_log("processing of ajax_time_measurement took ".sprintf('%4.2lf s',microtime(true)-$start));
		return $this->_stop_time_measurement($response,lang('Time measured'));
	}

	function _update_ranks(array $keys, Api\Json\Response $response, Api\Etemplate\Request $request)
	{
		//error_log("content[order]=".$request->content['nm']['order'].", changes[order]=".$request->changes['nm']['order']);
		$order = $request->changes['nm']['order'] ? $request->changes['nm']['order'] : $request->content['nm']['order'];

		if ($order != 'result_rank')	// --> update only the rank-values
		{
			foreach($this->route_result->search($keys,false,'','','',false,'AND',false,$keys) as $data)
			{
				$response->assign("set[{$data[$this->route_result->id_col]}][result_rank]",'innerHTML',(string)$data['result_rank']);
				if (isset($data['time_sum']))
				{
					$response->assign("set[{$data[$this->route_result->id_col]}][time_sum]",'innerHTML',(string)$data['time_sum']);
				}
			}
		}
		else							// --> submit the form to reload the page
		{
			$response->script('document.eTemplate.submit();');
		}
	}

	/**
	 * Stop the running time measurement ON CLIENT SIDE
	 *
	 * @access private
	 * @param Api\Json\Response $response response object with preset responses
	 * @return string
	 */
	function _stop_time_measurement(Api\Json\Response $response,$msg = '')
	{
		$response->call('set_style_by_class', 'td', 'ajax-loader', 'display', 'none');
		$response->jquery('#msg', 'text', array($msg));
	}
}
