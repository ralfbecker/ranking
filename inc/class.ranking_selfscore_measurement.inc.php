<?php
/**
 * EGroupware digital ROCK Rankings - selfscore boulder measurement
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2014-16 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$
 */

/**
 * Measurement plugin for selfscore boulder competitions
 */
class ranking_selfscore_measurement extends ranking_boulder_measurement
{
	/**
	 * Lead measurement specific code called from uiresult::index for show_result==4
	 *
	 * @param array &$content
	 * @param array &$sel_options
	 * @param array %$readonlys
	 */
	public static function measurement(array &$content, array &$sel_options, array &$readonlys, etemplate_new $tmpl)
	{
		//error_log(__METHOD__."() user_agent=".html::$user_agent.', HTTP_USER_AGENT='.$_SERVER['HTTP_USER_AGENT']);
		if (html::$ua_mobile) $GLOBALS['egw_info']['flags']['java_script'] .=
			'<meta name="viewport" content="width=525; user-scalable=false" />'."\n";

		// egw_framework::validate_file|includeCSS does not work if template was submitted
		if (egw_json_response::isJSONResponse())
		{
			egw_json_response::get()->includeScript($GLOBALS['egw_info']['server']['webserver_url'].'/ranking/sitemgr/digitalrock/dr_api.js');
			egw_json_response::get()->includeCSS($GLOBALS['egw_info']['server']['webserver_url'].'/ranking/sitemgr/digitalrock/dr_list.css');
		}
		else
		{
			// init protocol
			egw_framework::validate_file('/ranking/sitemgr/digitalrock/dr_api.js');
			egw_framework::includeCSS('/ranking/sitemgr/digitalrock/dr_list.css');
		}

		if ($_SERVER['REQUEST_METHOD'] == 'GET' && (int)$_GET['athlete'] > 0)
		{
			$content['nm']['PerId'] = (int)$_GET['athlete'];
		}

		$keys = self::query2keys($content['nm']);
		// if we have a startlist, add participants to sel_options
		if (ranking_result_bo::$instance->has_startlist($keys) && $content['nm']['route_status'] != STATUS_RESULT_OFFICIAL &&
			($rows = ranking_result_bo::$instance->route_result->search('',false,'start_order ASC','','','','AND',false,$keys+array(
				'route_type' => $content['nm']['route_type'],
				'discipline' => $content['nm']['discipline'],
			))))
		{
			$content['score'] = array();
			$score =& $content['score'];
			foreach($rows as $row)
			{
				if (!$content['nm']['PerId'] && !$row['result_rank'])
				{
					$content['nm']['PerId'] = $row['PerId'];	// set first not ranked competitor as current one
				}
				// set current result
				if ($content['nm']['PerId'] == $row['PerId'])
				{
					static $use2name = array(
						'b' => 'zone',
						't' => 'top',
						'f' => 'flash',
					);
					$use = $content['nm']['route_data']['selfscore_use'] ? explode('', $content['nm']['route_data']['selfscore_use']) : array('t');
					$not_used = array_diff_key($use2name, array_flip($use));
					$num_problems = $content['nm']['route_data']['route_num_problems'];
					$num_cols = $content['nm']['route_data']['selfscore_num'];
					for($n=$r=0; $r*$num_cols < $num_problems; ++$r)
					{
						for($c=0; $c < $num_cols; ++$c)
						{
							$col = etemplate_new::num2chrs($c-1);
							if ($n++ < $num_problems)
							{
								$score[$r.$col] = array(
									'n'     => $n.':',
								)+self::score2btf($row['score'][$n], $content['nm']['route_data']['selfscore_use']);
							}
							else	// surplus checkbox(es) in last row
							{
								$not_used = $use2name;
								/*$readonlys['score'][$r.$col.'[zone]'] =
									$readonlys['score'][$r.$col.'[top]'] =
									$readonlys['score'][$r.$col.'[flash]'] = true;*/
							}
							foreach($not_used as $name)
							{
								$readonlys['score'][$r.$col.'['.$name.']'] = true;
							}
						}
					}
				}
				list($bip, $sel_options['PerId'][$row['PerId']]) = explode(' ', ranking_result_bo::athlete2string($row, false), 2);
				$sel_options['PerId'][$row['PerId']] .= ' ('.$bip.')';
			}
			// sort athletes alphabetic
			$de_collator = new Collator('de_DE');
			$de_collator->asort($sel_options['PerId']);
		}
		if (!self::update_allowed($content['comp'], $content['nm']['route_data'], $content['nm']['PerId']))
		{
			egw_framework::set_extra('ranking', 'readonly', true);
		}
		// do not allow to select no category for athlets
		if ($GLOBALS['egw_info']['user']['account_lid'] == 'anonymous')
		{
			$tmpl->setElementAttribute('nm[cat]', 'empty_label', '');
		}
	}

	/**
	 * Convert a nummeric score to separate bonus/top/flash values for checkboxes
	 *
	 * @param int $score
	 * @param string $mode =null
	 * @return array values for keys 'zone', 'top' and 'flash'
	 */
	static function score2btf($score, $mode=null)
	{
		if (empty($mode)) $mode='t';
		$have_zone = strstr($mode, 'b') !== false;
		$no_top = (int)($mode[0] == 'b');
		$no_flash = ($mode[0] == 'b')+(bool)strstr($mode,'t');

		return array(
			'zone'  => (int)($have_zone && $score > 0),
			'top'   => (int)($score > $no_top),
			'flash' => (int)($score > $no_flash),
		);
	}

	/**
	 * Convert separate bonus/top/flash values of checkboxes to a nummeric score
	 *
	 * @param array $btf values for keys 'zone', 'top' and 'flash'
	 * @param string $mode =null
	 * @return int
	 */
	static function btf2score($btf, $mode=null)
	{
		if (empty($mode)) $mode='t';

		$score = 0;
		if ($btf['zone'] && $mode[0] == 'b') ++$score;
		if ($btf['top'] && strstr($mode, 't')) ++$score;
		if ($btf['flash'] && strstr($mode, 'f')) ++$score;

		return $score;
	}

	/**
	 * Update result of a participant
	 *
	 * @param int $PerId
	 * @param array $update
	 * @param array $state =null optional array with values for keys WetId, GrpId and route_order
	 */
	public static function ajax_update_result($PerId,$update,$state=null)
	{
		$comp = null;
		$query =& self::get_check_session($comp, $state);

		$response = egw_json_response::get();

		$keys = self::query2keys($query);
		//$response->alert(__METHOD__."($PerId, ".array2string($update).") ".array2string($keys));

		if (!($route = ranking_result_bo::$instance->route->read($keys)))
		{
			$response->alert(lang('Route not found!'));
			return;
		}

		$score = array();
		$num_problems = $route['route_num_problems'];
		$num_cols = $route['selfscore_num'];
		$num_tops = 0;
		foreach((array)$update as $row_col => $value)
		{
			$matches = null;
			if (preg_match('/^([0-9]+)([@A-Z]+)$/', $row_col, $matches))
			{
				$n = 1 + $matches[1]*$num_cols + boetemplate::chrs2num($matches[2]);

				if (0 < $n && $n <= $num_problems)
				{
					$num_tops += $score[$n] = self::btf2score($value, $route['selfscore_use']);
				}
			}
		}
		if ($num_tops > 0)
		{
			$to_update = array(
				'tops' => $num_tops+.0001,	// hack to allow to save 0 tops
				'zones' => $num_tops,
				'top_tries' => 0,
				'zone_tries' => $num_tops,
				'score' => $score,
			);
		}
		else	// allow to reset athlete to not climbed and ranked, eg. to delete him from startlist
		{
			$to_update = array(
				'tops' => null,
				'zones' => null,
				'top_tries' => null,
				'zone_tries' => null,
				'score' => null,
			);
		}
		//error_log(__METHOD__."($PerId, ".array2string($update).", ".array2string($state).")");
		if (ranking_result_bo::$instance->save_result($keys,array($PerId => $to_update),$query['route_type'],$query['discipline']))
		{
			// search filter needs route_type to not give SQL error
			$filter = $keys+array('PerId' => $PerId,'route_type' => $query['route_type'], 'discipline' => $query['discipline']);
			list($new_result) = ranking_result_bo::$instance->route_result->search(array(),false,'','','',false,'AND',false,$filter);
			$msg = ranking_result_bo::athlete2string($new_result,true);
		}
		else
		{
			$msg = lang('Nothing to update');
		}
		if (ranking_result_bo::$instance->error)
		{
			foreach(ranking_result_bo::$instance->error as $data)
			{
				foreach($data as $error)
				{
					$errors[$error] = $error;
				}
			}
			$response->alert(lang('Error').': '.implode(', ',$errors));
			$msg = '';
		}
		//$response->alert(__METHOD__."($PerId, $height, '$plus', $set_current) $msg");
		$response->data($msg);
	}

	/**
	 * Load data of a given athlete
	 *
	 * @param int $PerId
	 * @param array $update array with id => key pairs to update, id is the dom id and key the key into internal data
	 * @param array $state=null optional array with values for keys WetId, GrpId and route_order
	 * @param array &$data=null on return athlete data for extending class
	 */
	public static function ajax_load_athlete($PerId, array $state=null, array &$data=null)
	{
		$comp = null;
		$query =& self::get_check_session($comp,$state);

		$response = egw_json_response::get();

		//$response->alert(__METHOD__."($PerId) ".array2string(self::query2keys($query)));
		$keys = self::query2keys($query);
		$keys['PerId'] = $PerId;

		if (empty($keys['route_type']))	// route_type is needed to get correct rank of previous heat / avoid SQL error!
		{
			if (!($route = ranking_result_bo::$instance->route->read($keys)))
			{
				throw new egw_exception_wrong_parameter('Route not found!');
			}
			$keys += array_intersect_key($route, array_flip(array('route_type', 'discipline', 'quali_preselected')));
		}

		if ((list($data) = ranking_result_bo::$instance->route_result->search(array(), false, '','', '', False, 'AND', false, $keys)))
		{
			//$response->alert(__METHOD__."($PerId, ".array2string($update).', '.array2string($state).') data='.array2string($data));
			$num_problems = $route['route_num_problems'];
			$num_cols = $route['selfscore_num'];
			$score = array();
			for($n=$r=0; $r*$num_cols < $num_problems; ++$r)
			{
				for($c=0; $c < $num_cols; ++$c)
				{
					$col = boetemplate::num2chrs($c-1);
					if ($n++ < $num_problems)
					{
						$score[$r.$col] = array(
							'n'     => $n.':',
						)+self::score2btf($data['score'][$n], $route['selfscore_use']);
					}
				}
			}
			$response->data(array(
				'msg'   => ranking_result_bo::athlete2string($data),
				'update_allowed' => self::update_allowed($comp, $route, $PerId),
				'content' => $score,
			));
			$query['PerId'] = $PerId;
		}
		else
		{
			$response->data(array('msg' => ''));
		}
	}

	public static function update_allowed(array $comp, array $route, $PerId)
	{
		return ranking_result_bo::$instance->acl_check($comp['nation'],EGW_ACL_RESULT,$comp) ||
			ranking_result_bo::$instance->is_judge($comp,false,$route) ||
			ranking_result_bo::$instance->is_selfservice() == $PerId;
	}

	/**
	 * Get open selfscore competition and routes for given PerId
	 *
	 * @param array $athlete
	 * @return array of array with comp and route data merged
	 */
	public static function open(array $athlete)
	{
		$found = $WetIds = array();
		foreach((array)ranking_result_bo::$instance->route->search(null, $only_keys=false,'route_order ASC','','',False,'AND',false,array(
			'route_status' => 1,
			'discipline' => 'selfscore',
		)) as $route)
		{
			$WetIds[$route['WetId']][$route['GrpId']] = $route;
		}
		//error_log(__LINE__.': '.__METHOD__."(".array2string($athlete).") WetIds=".array2string($WetIds));
		if ($WetIds)
		{
			foreach((array)ranking_result_bo::$instance->comp->search(array('WetId' => array_keys($WetIds)), false) as $comp)
			{
				//error_log(__LINE__.': '.__METHOD__."() comp=".array2string($comp));
				// check if athlete is direct registered into a route
				foreach($WetIds[$comp['WetId']] as $route)
				{
					//error_log(__LINE__.': '.__METHOD__."() route=".array2string($route));
					if (($result = ranking_result_bo::$instance->route_result->read($keys=array_intersect_key($route, array_flip(array('WetId','GrpId','route_order')))+array(
						'PerId' => $athlete['PerId'],
					))))
					{
						$found[] = array_merge($comp, $route);
						continue 2;
					}
				}
				// check if comp open to athletes federation
				if (!ranking_result_bo::$instance->comp->open_comp_match($athlete, $comp)) continue;
				// check comp has category for athlete
				if (!($cats = ranking_result_bo::$instance->matching_cats($comp, $athlete))) continue;
				// check cats intersect with selfscore cats
				if (!($cats = array_intersect_key($cats, array_flip(array_keys($WetIds[$comp['WetId']]))))) continue;
				// check if athlete registered for comp and cat
				foreach(array_keys($cats) as $cat)
				{
					if (ranking_result_bo::$instance->result->has_registration($keys=array(
						'WetId' => $comp['WetId'],
						'GrpId' => $cat,
						'PerId' => $athlete['PerId'],
					)))
					{
						$route = $WetIds[$comp['WetId']][$cat];
						$keys['route_order'] = $route['route_order'];
						// check and if not include athlete in startlist of route
						if (!ranking_result_bo::$instance->has_results($keys))
						{
							if ($route['route_order']) continue;	// only add automatic to qualification
							$start_order = count($this->route_result->search(array_diff_key($keys, array('PerId'=>0)), true))+1;
							$this->route_result->init($keys+array(
								'start_order' => $start_order,
							));
							$this->route_result->save();
							if (!ranking_result_bo::$instance->has_results($keys)) continue;	// was not added
						}
						$found[] = array_merge($comp, $route);
					}
					//else error_log(__METHOD__."() $athlete[nachname], $athlete[vorname] ($athlete[nation]) NOT registed for $comp[name]");
				}
			}
		}
		return $found;
	}
}
