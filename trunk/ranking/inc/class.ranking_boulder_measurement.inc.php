<?php
/**
 * eGroupWare digital ROCK Rankings - boulder measurement
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2011 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$
 */

/**
 * Measurement plugin for boulder competitions
 */
class ranking_boulder_measurement
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
		unset($readonlys);
		//error_log(__METHOD__."() user_agent=".html::$user_agent.', HTTP_USER_AGENT='.$_SERVER['HTTP_USER_AGENT']);
		if (html::$ua_mobile) $GLOBALS['egw_info']['flags']['java_script'] .=
			'<meta name="viewport" content="width=525; user-scalable=false" />'."\n";

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
			foreach($rows as $row)
			{
				if (!$content['nm']['PerId'] && !$row['result_rank'])
				{
					$content['nm']['PerId'] = $row['PerId'];	// set first not ranked competitor as current one
				}
				// set current result
				if ($content['nm']['PerId'] == $row['PerId'])
				{
					if (!$content['nm']['boulder_n']) $content['nm']['boulder_n'] = 1;
					$content['top'] = (string)$row['top'.$content['nm']['boulder_n']];
					$content['zone'] = (string)$row['zone'.$content['nm']['boulder_n']];
				}
				$sel_options['PerId'][$row['PerId']] = boresult::athlete2string($row, false);
			}
		}
		// init protocol
		egw_framework::validate_file('/ranking/js/boulder.js');
	}

	/**
	 * Update result of a participant
	 *
	 * @param int $PerId
	 * @param array $update
	 * @param int $set_current=1 make $PerId the current participant of the route
	 * @param array $state=null optional array with values for keys WetId, GrpId and route_order
	 */
	public static function ajax_update_result($PerId,$update,$set_current=1, $state=null)
	{
		$comp = null;
		$query =& self::get_check_session($comp,$state);

		$response = egw_json_response::get();

		$keys = self::query2keys($query);
		//$response->alert(__METHOD__."($PerId, ".array2string($update).", $set_current) ".array2string($keys));

		// change zone$n === 'empty' --> ''
		if ($update['zone'.$set_current] === 'empty') $update['zone'.$set_current] = '';

		//error_log(__METHOD__."($PerId, ".array2string($update).", $set_current)");
		if (boresult::$instance->save_result($keys,array($PerId => $update),$query['route_type'],$query['discipline']))
		{
			// search filter needs route_type to not give SQL error
			$filter = $keys+array('PerId' => $PerId,'route_type' => $query['route_type'], 'discipline' => $query['discipline']);
			list($new_result) = boresult::$instance->route_result->search(array(),false,'','','',False,'AND',false,$filter);
			$msg = boresult::athlete2string($new_result,true);
			if ($query['discipline'] == 'boulder')
			{
				$msg = ($update['top'.$set_current] ? 't'.$update['top'.$set_current].' ' : '').
					'b'.$update['zone'.$set_current].': '.$msg;
			}
		}
		else
		{
			$msg = lang('Nothing to update');
		}
		// update current participant
		if ($set_current)
		{
			boresult::$instance->route->update($keys+array(
				'current_'.$set_current => $PerId,
				'route_modified' => time(),
				'route_modifier' => $GLOBALS['egw_info']['user']['account_id'],
			),false);
		}
		if (boresult::$instance->error)
		{
			foreach(boresult::$instance->error as $id => $data)
			{
				foreach($data as $error)
				{
					$errors[$error] = $error;
				}
			}
			$response->alert(lang('Error').': '.implode(', ',$errors));
			$msg = '';
		}
		elseif (boresult::$instance->route->read($keys) &&
			($dsp_id=boresult::$instance->route->data['dsp_id']) &&
			($frm_id=boresult::$instance->route->data['frm_id']))
		{
			// add display update(s)
			$display = new ranking_display($this->db);
			$display->activate($frm_id,$id,$dsp_id,$keys['GrpId'],$keys['route_order']);
		}
		//$response->alert(__METHOD__."($PerId, $height, '$plus', $set_current) $msg");
		$response->jquery('#msg', 'text', array($msg));
		$response->script('if (typeof resultlist != "undefined") resultlist.update();');
	}

	/**
	 * Update (multiple) protocol records
	 *
	 * @param array $record
	 */
	public static function ajax_protocol_update(array $record)
	{
		$response = array();
		foreach(func_get_args() as $record)
		{
			$comp = null;
			$query =& self::get_check_session($comp, $record+array('route_order' => $record['route']));
			$keys = self::query2keys($query);

			// if a state given, check if we are still in same state and reject update as "outdated"
			if ($record['state'])
			{
				$current_values = boresult::$instance->route_result->results_by_id($keys);
				foreach($record['state'] as $name => $value)
				{
					if ($current_values[$name] != $value)
					{
						$response[] = $record + array(
							'stored' => 'outdated',
						);
						continue 2;
					}
				}
			}

			$update = array(
				$record['PerId'] => array(
					'top'.$record['boulder'] => (int)$record['top'],
					'zone'.$record['boulder'] => $record['bonus'],
				),
			);
			if (boresult::$instance->save_result($keys, $update, $query['route_type'], $query['discipline']))
			{
				// search filter needs route_type to not give SQL error
				$filter = $keys+array(
					'PerId' => $record['PerId'],
					'route_type' => $query['route_type'],
					'discipline' => $query['discipline'],
				);
				list($new_result) = boresult::$instance->route_result->search(array(),false,'','','',False,'AND',false,$filter);
				$msg = boresult::athlete2string($new_result,true);
				if ($query['discipline'] == 'boulder')
				{
					$msg = ($record['top'] ? 't'.$record['top'].' ' : '').'b'.$record['bonus'].': '.$msg;
				}
			}
			else
			{
				$msg = lang('Nothing to update');
			}
			$response[] = $record + array(
				'msg' => $msg,
				'stored' => true,
			);
		}
		egw_json_request::isJSONRequest(false);	// switch regular json_response handling off
		Header('Content-Type: application/json; charset=utf-8');
		echo json_encode($response);
		common::egw_exit();
	}

	/**
	 * Load data of a given athlete
	 *
	 * @param int $PerId
	 * @param array $update array with id => key pairs to update, id is the dom id and key the key into internal data
	 * @param array $state=null optional array with values for keys WetId, GrpId and route_order
	 * @param array &$data=null on return athlete data for extending class
	 */
	public static function ajax_load_athlete($PerId,array $update, array $state=null, array &$data=null)
	{
		$comp = null;
		$query =& self::get_check_session($comp,$state);

		$response = egw_json_response::get();

		//$response->alert(__METHOD__."($PerId) ".array2string(self::query2keys($query)));
		$keys = self::query2keys($query);
		$keys['PerId'] = $PerId;

		if (empty($keys['route_type']))	// route_type is needed to get correct rank of previous heat / avoid SQL error!
		{
			if (!($route = boresult::$instance->route->read($keys)))
			{
				throw new egw_exception_wrong_parameter('Route not found!');
			}
			$keys += array_intersect_key($route, array_flip(array('route_type', 'discipline', 'quali_preselected')));
		}

		if ((list($data) = boresult::$instance->route_result->search(array(),false,'','','',False,'AND',false,$keys)))
		{
			//$response->alert(__METHOD__."($PerId, ".array2string($update).', '.array2string($state).') data='.array2string($data));
			foreach($update as $id => $key)
			{
				if ($key === 'result_plus' && (string)$data['result_plus'] === '')
				{
					$data['result_plus'] = '0';	// null or '' is NOT understood, must be '0'
				}
				$response->assign($id, 'value', (string)$data[$key]);
				// for boulder
				if (strpos($key,'zone') === 0)
				{
					$query['boulder_n'] = (int)substr($key,4);
				}
			}
			$query['PerId'] = $PerId;

			$response->jquery('#msg', 'text', array(boresult::athlete2string($data)));
		}
	}

	/**
	 * Convert query array to holds keys
	 *
	 * @param array $query
	 * @return array
	 */
	protected static function query2keys(array $query)
	{
		return array(
			'WetId' => $query['comp'],
			'GrpId' => $query['cat'],
			'route_order' => $query['route'],
		);
	}

	/**
	 * Get session data and check if user has judge or admin rights
	 *
	 * @param array &$comp=null on return competition array
	 * @param array $state=null optional array with values for keys WetId, GrpId and route_order
	 * @throws egw_exception_wrong_parameter
	 * @return array reference to ranking result session array
	 */
	public static function &get_check_session(&$comp=null,array $state=null)
	{
		$query =& egw_cache::getSession('ranking', 'result');

		if ($state)	// merge optional state
		{
			foreach(array('WetId' => 'comp', 'GrpId' => 'cat', 'route_order' => 'route') as $from => $to)
			{
				if (isset($state[$from])) $query[$to] = $state[$from];
			}
		}

		if (!($comp = boresult::$instance->comp->read($query['comp'])))
		{
			throw new egw_exception_wrong_parameter("Competition $query[comp] NOT found!");
		}
		if (!boresult::$instance->is_admin && !boresult::$instance->is_judge($comp, false, self::query2keys($query)) &&
			$query['discipline'] != 'selfscore')
		{
			throw new egw_exception_wrong_parameter(lang('Permission denied!'));
		}
		return $query;
	}

	/**
	 * Init static vars
	 */
	public static function init_static()
	{
		include_once(EGW_INCLUDE_ROOT.'/ranking/inc/class.boresult.inc.php');
		if (!isset(boresult::$instance))
		{
			new boresult();
		}
	}
}
ranking_boulder_measurement::init_static();