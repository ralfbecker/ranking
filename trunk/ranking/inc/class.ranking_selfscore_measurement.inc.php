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

		$keys = self::query2keys($content['nm']);
		// if we have a startlist, add participants to sel_options
		if (boresult::$instance->has_startlist($keys) && $content['nm']['route_status'] != STATUS_RESULT_OFFICIAL &&
			($rows = boresult::$instance->route_result->search('',false,'start_order ASC','','','','AND',false,$keys+array(
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
				$sel_options['PerId'][$row['PerId']] = boresult::athlete2string($row, false);
			}
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

		if (!($route = boresult::$instance->route->read($keys)))
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
		if (boresult::$instance->save_result($keys,array($PerId => $to_update),$query['route_type'],$query['discipline']))
		{
			// search filter needs route_type to not give SQL error
			$filter = $keys+array('PerId' => $PerId,'route_type' => $query['route_type'], 'discipline' => $query['discipline']);
			list($new_result) = boresult::$instance->route_result->search(array(),false,$order_by='',$extra_cols='',$wildcard='',$empty=False,$op='AND',$start=false,$filter);
			$msg = boresult::athlete2string($new_result,true);
		}
		else
		{
			$msg = lang('Nothing to update');
		}
		if (boresult::$instance->error)
		{
			foreach(boresult::$instance->error as $id => $data)
			{
				foreach($data as $field => $error)
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
}
