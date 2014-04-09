<?php
/**
 * EGroupware digital ROCK Rankings - selfscore boulder measurement
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2014 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id4$
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
	public static function measurement(array &$content, array &$sel_options, array &$readonlys)
	{
		egw_framework::validate_file('/ranking/sitemgr/digitalrock/dr_api.js');
		egw_framework::includeCSS('/ranking/sitemgr/digitalrock/dr_list.css');

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
					$num_problems = $content['nm']['route_data']['route_num_problems'];
					$num_cols = $content['nm']['route_data']['selfscore_num'];
					for($n=$r=0; $r*$num_cols < $num_problems; ++$r)
					{
						for($c=0; $c < $num_cols; ++$c)
						{
							$col = boetemplate::num2chrs($c-1);
							if ($n++ < $num_problems)
							{
								$score[$r.$col] = array(
									'num' => $n.': %s',
									'top' => (int)$row['score'][$n],
								);
							}
							else	// surplus checkbox in last row
							{
								$readonlys['score'][$r.$col.'[top]'] = true;
							}
						}
					}
				}
				$sel_options['PerId'][$row['PerId']] = ranking_result_bo::athlete2string($row, false);
			}
		}
		if (!self::update_allowed($content['comp'], $content['nm']['route_data'], $content['nm']['PerId']))
		{
			egw_framework::set_extra('ranking', 'readonly', true);
		}
	}

	/**
	 * Update result of a participant
	 *
	 * @param int $PerId
	 * @param array $update
	 * @param array $state=null optional array with values for keys WetId, GrpId and route_order
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
					$score[$n] = (int)(bool)$value;
					++$num_tops;
				}
			}
		}
		$to_update = array(
			'tops' => $num_tops,
			'zones' => $num_tops,
			'top_tries' => $num_tops,
			'zone_tries' => $num_tops,
			'score' => $score,
		);

		//error_log(__METHOD__."($PerId, ".array2string($update).", $set_current)");
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
		$response->jquery('#msg', 'text', array($msg));
		$response->script('if (typeof resultlist != "undefined") resultlist.update();');
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

		if (list($data) = ranking_result_bo::$instance->route_result->search(array(),false,$order_by='',$extra_cols='',$wildcard='',$empty=False,$op='AND',$start=false,$keys))
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
					if ($n++ < $num_problems && $data['score'][$n])
					{
						$score[$r.$col] = (int)$data['score'][$n];
					}
				}
			}
			$response->call('set_scorecard', $score, self::update_allowed($comp, $route, $PerId));
			$query['PerId'] = $PerId;

			$response->jquery('#msg', 'text', array(ranking_result_bo::athlete2string($data)));
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
				foreach($cats as $cat => $name)
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
