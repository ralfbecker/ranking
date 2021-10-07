<?php
/**
 * EGroupware digital ROCK Rankings - boulder measurement
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2011-19 by Ralf Becker <RalfBecker@digitalrock.de>
 */

use EGroupware\Api;
use EGroupware\Api\Framework;
use EGroupware\Api\Etemplate;

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
	 * @param array &$readonlys
	 * @param Etemplate $tmpl
	 */
	public static function measurement(array &$content, array &$sel_options, array &$readonlys, Etemplate $tmpl)
	{
		unset($readonlys);
		//error_log(__METHOD__."() user_agent=".Api\Html::$user_agent.', HTTP_USER_AGENT='.$_SERVER['HTTP_USER_AGENT']);
		if (Api\Header\UserAgent::mobile()) $GLOBALS['egw_info']['flags']['java_script'] .=
			'<meta name="viewport" content="width=525; user-scalable=false" />'."\n";

		foreach(array(
			'/ranking/js/boulder.js',	// init protocol
			'/ranking/sitemgr/digitalrock/dr_api.js',
			'/ranking/sitemgr/digitalrock/dr_list.css'
		) as $file)
		{
			$cache_buster = '?'.filemtime(EGW_SERVER_ROOT.$file);
			// Framework::validate_file|includeCSS does not work if template was submitted
			if (Api\Json\Response::isJSONResponse())
			{
				$method = substr($file, -3) == '.js' ? 'includeScript' : 'includeCSS';
				Api\Json\Response::get()->$method($GLOBALS['egw_info']['server']['webserver_url'].$file.$cache_buster);
			}
			else
			{
				$method = substr($file, -3) == '.js' ? 'validate_file' : 'includeCSS';
				Framework::$method($file.$cache_buster);
			}
		}
		$keys = self::query2keys($content['nm']);
		// if we have a startlist, add participants to sel_options
		if (ranking_result_bo::$instance->has_startlist($keys) && $content['nm']['route_status'] != STATUS_RESULT_OFFICIAL &&
			($rows = ranking_result_bo::$instance->route_result->search('',false,'start_order ASC','','','','AND',false,$keys+array(
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
					$content['try'] = (string)$row['try'.$content['nm']['boulder_n']];
					$content['zone'] = (string)$row['zone'.$content['nm']['boulder_n']];
					$content['top'] = (string)$row['top'.$content['nm']['boulder_n']];
				}
				$sel_options['PerId'][$row['PerId']] = ranking_result_bo::athlete2string($row, false);
			}
		}
		if (!ranking_result_bo::$instance->is_judge($content['comp'], false, $keys, $content['nm']['boulder_n']))
		{
			Api\Json\Response::get()->call('app.ranking.no_measurement', true);
		}
	}

	/**
	 * Update result of a participant
	 *
	 * @param int $PerId
	 * @param array $update
	 * @param ?array $state =null optional array with values for keys WetId, GrpId and route_order
	 * @param int $set_current =1 make $PerId the current participant of the route
	 */
	public static function ajax_update_result(int $PerId, array $update, array $state=null, int $set_current=1)
	{
		$comp = null;
		$query =& self::get_check_session($comp,$state);

		$response = Api\Json\Response::get();

		$keys = self::query2keys($query);
		//$response->alert(__METHOD__."($PerId, ".array2string($update).", $set_current) ".array2string($keys));

		// change zone$n === 'empty' --> ''
		if ($update['zone'.$set_current] === 'empty') $update['zone'.$set_current] = '';

		//error_log(__METHOD__."($PerId, ".array2string($update).", $set_current)");
		if (ranking_result_bo::$instance->save_result($keys,array($PerId => $update),$query['route_type'],$query['discipline']))
		{
			// search filter needs route_type to not give SQL error
			$filter = $keys+array('PerId' => $PerId,'route_type' => $query['route_type'], 'discipline' => $query['discipline']);
			list($new_result) = ranking_result_bo::$instance->route_result->search(array(),false,'','','',False,'AND',false,$filter);
			$msg = ranking_result_bo::athlete2string($new_result,true);
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
			ranking_result_bo::$instance->route->update($keys+array(
				'current_'.$set_current => $PerId,
				'route_modified' => time(),
				'route_modifier' => $GLOBALS['egw_info']['user']['account_id'],
			),false);
		}
		if (ranking_result_bo::$instance->error)
		{
			foreach(ranking_result_bo::$instance->error as $id => $data)
			{
				foreach($data as $error)
				{
					$errors[$error] = $error;
				}
			}
			$response->alert(lang('Error').': '.implode(', ',$errors));
			$msg = '';
		}
		elseif (ranking_result_bo::$instance->route->read($keys) &&
			($dsp_id=ranking_result_bo::$instance->route->data['dsp_id']) &&
			($frm_id=ranking_result_bo::$instance->route->data['frm_id']))
		{
			// add display update(s)
			$display = new ranking_display(ranking_result_bo::$instance->db);
			$display->activate($frm_id,$id,$dsp_id,$keys['GrpId'],$keys['route_order']);
		}
		//$response->alert(__METHOD__."($PerId, $height, '$plus', $set_current) $msg");
		$response->call('app.ranking.message', $msg);
		//$response->script('if (typeof resultlist != "undefined") resultlist.update();');
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
				$current_values = ranking_result_bo::$instance->route_result->results_by_id($keys);
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
					'try'.$record['boulder'] => $record['try'] ? (int)$record['try'] : '',
					'top'.$record['boulder'] => is_numeric($record['top']) ? (int)$record['top'] : '',
					'zone'.$record['boulder'] => is_numeric($record['bonus']) ? (int)$record['bonus'] : '',
				),
			);
			if (ranking_result_bo::$instance->save_result($keys, $update, $query['route_type'], $query['discipline'],
				null, 0, false, 'start_order ASC', $record['boulder']))
			{
				// search filter needs route_type to not give SQL error
				$filter = $keys+array(
					'PerId' => $record['PerId'],
					'route_type' => $query['route_type'],
					'discipline' => $query['discipline'],
				);
				list($new_result) = ranking_result_bo::$instance->route_result->search(array(),false,'','','',False,'AND',false,$filter);
				$msg = ranking_result_bo::athlete2string($new_result,true);
				if ($query['discipline'] == 'boulder')
				{
					$msg = ($record['try'] ? $record['try'].': ' : '').
						($record['top'] ? 't'.$record['top'].' ' : '').
						'b'.$record['bonus'].': '.$msg;
				}
			}
			elseif (ranking_result_bo::$instance->error === false)
			{
				$msg = lang('Permission denied!');
			}
			else
			{
				$msg = lang('Nothing to update');
			}
			$response[] = $record + array(
				'msg' => $msg,
				'stored' => ranking_result_bo::$instance->error !== false,
			);
		}
		Api\Json\Request::isJSONRequest(false);	// switch regular json_response handling off
		Header('Content-Type: application/json; charset=utf-8');
		echo json_encode($response);
		exit();
	}

	/**
	 * Load data of a given athlete
	 *
	 * @param int $PerId
	 * @param ?array $state =null optional array with values for keys WetId, GrpId and route_order
	 * @param ?array &$data =null on return athlete data for extending class
	 */
	public static function ajax_load_athlete(int $PerId, array $state=null, array &$data=null)
	{
		$comp = null;
		$query =& self::get_check_session($comp,$state);

		$response = Api\Json\Response::get();

		//$response->alert(__METHOD__."($PerId) ".array2string(self::query2keys($query)));
		$keys = self::query2keys($query);
		$keys['PerId'] = $PerId;

		if (empty($keys['route_type']))	// route_type is needed to get correct rank of previous heat / avoid SQL error!
		{
			if (!($route = ranking_result_bo::$instance->route->read($keys)))
			{
				throw new Api\Exception\WrongParameter('Route not found!');
			}
			$keys += array_intersect_key($route, array_flip(array('route_type', 'discipline', 'quali_preselected')));
		}

		if ((list($data) = ranking_result_bo::$instance->route_result->search(array(),false,'','','',False,'AND',false,$keys)))
		{
			//$response->alert(__METHOD__."($PerId, ".array2string($update).', '.array2string($state).') data='.array2string($data));
			foreach(array_keys($data) as $id => $key)
			{
				if ($key === 'result_plus' && (string)$data['result_plus'] === '')
				{
					$data['result_plus'] = '0';	// null or '' is NOT understood, must be '0'
				}
				// for boulder
				if (strpos($key,'zone') === 0)
				{
					$query['boulder_n'] = (int)substr($key,4);
				}
			}
			$query['PerId'] = $PerId;

			$athlete = ranking_result_bo::$instance->athlete->read($PerId);
			if (!($data['profile_url'] = ranking_result_bo::$instance->athlete->picture_url($athlete->rkey)))
			{
				$data['profile_url'] = Api\Image::find('ranking', 'transparent');
			}

			$data['athlete'] = ranking_result_bo::athlete2string($data);
			$data['is_judge'] = ranking_result_bo::$instance->is_judge($comp, false, $keys, $state['boulder_n']);
			$response->data($data);
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
	 * @param array& $comp =null on return competition array
	 * @param array $state =null optional array with values for keys WetId, GrpId and route_order
	 * @throws Api\Exception\WrongParameter
	 * @return array reference to ranking result session array
	 */
	public static function &get_check_session(&$comp=null,array $state=null)
	{
		$query =& Api\Cache::getSession('ranking', 'result');

		if ($state)	// merge optional state
		{
			foreach(array('WetId' => 'comp', 'GrpId' => 'cat', 'route_order' => 'route') as $from => $to)
			{
				if (isset($state[$from])) $query[$to] = $state[$from];
			}
		}

		if (!($comp = ranking_result_bo::$instance->comp->read($query['comp'])))
		{
			throw new Api\Exception\WrongParameter("Competition $query[comp] NOT found!");
		}
		if (!ranking_result_bo::$instance->is_admin && !ranking_result_bo::$instance->is_judge($comp, false, self::query2keys($query)) &&
			$query['discipline'] != 'selfscore')
		{
			throw new Api\Exception\WrongParameter(lang('Permission denied!'));
		}
		return $query;
	}

	/**
	 * Init static vars
	 */
	public static function init_static()
	{
		include_once(EGW_INCLUDE_ROOT.'/ranking/inc/class.ranking_result_bo.inc.php');
		if (!isset(ranking_result_bo::$instance))
		{
			new ranking_result_bo();
		}
	}
}
ranking_boulder_measurement::init_static();