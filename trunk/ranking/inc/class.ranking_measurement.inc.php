<?php
/**
 * eGroupWare digital ROCK Rankings - lead measurement
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
 * Measurement plugin for lead competitions
 */
class ranking_measurement
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
		//echo "<p>nm[topo]=".array2string($content['nm']['topo'])."</p>\n";
		foreach(self::get_topos($content) as $n => $path)
		{
			$sel_options['topo'][$path] = array(
				'label' => lang('%1. topo',$n+1),
				'title' => $path,
			);
		}
		// no topo selected or topo no longer there, use the first one, if existing
		if ($sel_options['topo'] && !$content['nm']['topo'] || !isset($sel_options['topo'][$content['nm']['topo']]))
		{
			$content['nm']['topo'] = key($sel_options['topo']);
		}
		if (count($sel_options['topo']) <= 1)
		{
			$sel_options['topo'][$content['nm']['topo']] = ' ';
			$readonlys['nm[topo]'] = true;
		}
		$keys = self::query2keys($content['nm']);
		unset($keys['hold_topo']);
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
					$content['result_height'] = $row['result_height'];
					$content['result_plus']   = $row['result_plus'];
				}
				$sel_options['PerId'][$row['PerId']] = boresult::athlete2string($row, false);
			}
		}
		if ($content['nm']['topo'])
		{
			$content['transparent'] = egw::link(egw_vfs::download_url($content['nm']['topo']));
			if (($holds = self::get_holds(self::query2keys($content['nm']))))
			{
				$GLOBALS['egw_info']['flags']['java_script'] .= '<script type="text/javascript">
$j(document).ready(function(){
	show_handholds('.json_encode($holds).');
});
</script>'."\n";
			}
		}
	}

	/**
	 * Update result of a participant
	 *
	 * @param int $PerId
	 * @param int|float $height
	 * @param string $plus
	 * @param boolean $set_current=true make $PerId the current participant of the route
	 */
	public static function ajax_update_result($PerId,$height,$plus,$set_current=true)
	{
		$query =& self::get_check_session();

		$response = egw_json_response::get();

		$keys = self::query2keys($query);
		unset($keys['hold_topo']);
		//$response->alert(__METHOD__."($PerId, $height, '$plus', $set_current) ".array2string($keys));

		//error_log(__METHOD__."($PerId, $height, $plus, $set_current)");
		if (boresult::$instance->save_result($keys,array(
			$PerId => array(
				'result_height' => $height,
				'result_plus'   => $plus,
			)),$query['route_type'],$query['discipline']))
		{
			list($new_result) = boresult::$instance->route_result->search($keys+array('PerId' => $PerId),false);
			$msg = boresult::athlete2string($new_result,true);
		}
		else
		{
			$msg = lang('Nothing to update');
		}
		if (boresult::$instance->error)
		{
			foreach($this->error as $id => $data)
			{
				foreach($data as $field => $error)
				{
					$errors[$error] = $error;
				}
			}
			$response->addAlert(lang('Error').': '.implode(', ',$errors));
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
	}

	/**
	 * Load data of a given athlete
	 *
	 * @param string $request_id eTemplate request id
	 * @param int $PerId
	 */
	public static function ajax_load_athlete($request_id, $PerId)
	{
		$query =& self::get_check_session();

		$response = egw_json_response::get();

		//$response->alert(__METHOD__."($PerId) ".array2string(self::query2keys($query)));
		$keys = self::query2keys($query);
		unset($keys['hold_topo']);
		$keys['PerId'] = $PerId;

		if (list($data) = boresult::$instance->route_result->search($keys,false))
		{
			//$response->alert(__METHOD__."($PerId) ".array2string($data));
			$response->assign('exec[result_height]', 'value', $data['result_height'] && $data['result_plus'] != TOP_PLUS ? $data['result_height'] : '');
			$response->assign('exec[result_plus]', 'value', (int)$data['result_plus']);

			if ($data['result_plus'] == TOP_PLUS)
			{
				// todo scroll to top
				//$response->alert('Top');
			}
			else
			{
				$response->script('var holds=getHoldsByHeight('.($data['result_height'] ? $data['result_height'] : 1).'); holds[0].scrollIntoView(false); mark_holds(holds);');
			}
			$query['PerId'] = $PerId;

			$response->jquery('#msg', 'text', array(boresult::athlete2string($data)));
		}
	}

	/**
	 * Store a hold on server-side
	 *
	 * @param array $hold
	 * @throws egw_exception_wrong_parameter
	 */
	public static function ajax_save_hold(array $hold)
	{
		$query =& self::get_check_session();

		$response = egw_json_response::get();

		if (!($hold = self::save_hold(array_merge($hold,self::query2keys($query)))))
		{
			$response->alert(lang('Error storing handhold!'));
		}
		else
		{
			$response->script('add_handhold('.json_encode($hold).')');
		}
	}

	/**
	 * Delete hold on server-side
	 *
	 * @param int $hold
	 * @throws egw_exception_wrong_parameter
	 */
	public static function ajax_delete_hold($hold)
	{
		$query =& self::get_check_session();

		if (!(int)$hold || !self::delete_hold(array_merge(self::query2keys($query),array('hold_id' => $hold))))
		{
			$response = egw_json_response::get();
			$response->alert(lang('Error deleting handhold!'));
		}
	}

	/**
	 * Get session data and check if user has judge or admin rights
	 *
	 * @param array &$comp=null on return competition array
	 * @throws egw_exception_wrong_parameter
	 * @return array reference to ranking result session array
	 */
	public static function &get_check_session(&$comp=null)
	{
		$query =& egw_cache::getSession('ranking', 'result');

		if (!($comp = boresult::$instance->comp->read($query['comp'])))
		{
			throw new egw_exception_wrong_parameter("Competition $query[comp] NOT found!");
		}
		if (!boresult::$instance->is_admin && !boresult::$instance->is_judge($comp))
		{
			throw new egw_exception_wrong_parameter(lang('Permission denied!'));
		}
		return $query;
	}

	/**
	 * Load topo data from server
	 *
	 * @param string $path
	 */
	public static function ajax_load_topo($path)
	{
		$response = egw_json_response::get();

		$query =& egw_cache::getSession('ranking', 'result');
		$query['topo'] = $path;

		$holds = self::get_holds(self::query2keys($query));
		$response->script('show_handholds('.json_encode($holds).')');
	}

	/**
	 * Convert query array to holds keys
	 *
	 * @param array $query
	 * @return array
	 */
	private static function query2keys(array $query)
	{
		return array(
			'WetId' => $query['comp'],
			'GrpId' => $query['cat'],
			'route_order' => $query['route'],
			'hold_topo' => (int)substr(egw_vfs::basename($query['topo']),1),
		);
	}

	const HOLDS_TABLE = 'RouteHolds';

	/**
	 * Store a hold in the DB
	 *
	 * @param array $hold values for keys WetId, GrpId, route_order, topo or hold_id, plus data xpercent, ypercent, hold_height
	 * @return array|boolean
	 */
	public static function save_hold($hold)
	{
		$log = __METHOD__.'('.array2string($hold).')';

		foreach(array('xpercent','ypercent','height') as $name)
		{
			if (isset($hold[$name])) $hold['hold_'.$name] = round(100.0*$hold[$name]);	// we store 100th % or cm
		}
		$table_def = boresult::$instance->db->get_table_definitions('ranking',self::HOLDS_TABLE);
		$hold = array_intersect_key($hold,$table_def['fd']);

		if ($hold['hold_id'] > 0)
		{
			boresult::$instance->db->update(self::HOLDS_TABLE, $hold, array('hold_id'=>$hold['hold_id']), __LINE__, __FILE__, 'ranking');
		}
		else
		{
			$hold['hold_height'] = boresult::$instance->db->select(self::HOLDS_TABLE, 'MAX(hold_height)',
				array_intersect_key($hold,array_flip(array('WetId','GrpId','route_order','hold_topo'))),
				__LINE__, __FILE__, false, '', 'ranking')->fetchColumn()+100;

			boresult::$instance->db->insert(self::HOLDS_TABLE, $hold, false, __LINE__, __FILE__, 'ranking');
			$hold['hold_id'] = boresult::$instance->db->get_last_insert_id(self::HOLDS_TABLE, 'hold_id');
		}
		$hold = self::db2hold($hold);

		error_log($log.' returning '.array2string($hold['hold_id'] ? $hold : false));
		return $hold['hold_id'] ? $hold : false;
	}

	/**
	 * Convert db format to internal format
	 *
	 * @param array $hold
	 * @return array
	 */
	private static function db2hold(array $hold)
	{
		foreach(array('xpercent','ypercent','height') as $name)
		{
			if (isset($hold['hold_'.$name])) $hold[$name] = $hold['hold_'.$name]/100.0;	// we store 100th % or cm
		}
		return array_intersect_key($hold,array_flip(array('hold_id','xpercent','ypercent','height','hold_type')));
	}

	/**
	 * Get holds for a topo
	 *
	 * @param array $keys values for keys 'WetId', 'GrpId', 'route_order', 'topo_id'
	 * @return array of array with values for keys 'hold_id','xpercent','ypercent','height','hold_type'
	 */
	public static function get_holds(array $keys)
	{
		$holds = array();
		foreach(boresult::$instance->db->select(self::HOLDS_TABLE, array('hold_id','hold_xpercent','hold_ypercent','hold_height','hold_type'),
				array_intersect_key($keys,array_flip(array('WetId','GrpId','route_order','hold_topo'))),
				__LINE__, __FILE__, false, '', 'ranking') as $hold)
		{
			$holds[] = self::db2hold($hold);
		}
		return $holds;
	}

	/**
	 * Delete a hold from a topo
	 *
	 * @param array|int $hold integer hold_id or array with values for keys eg. 'hold_id' or 'WetId', 'GrpId', 'route_order' and 'hold_topo'
	 * @return int affected rows
	 */
	public static function delete_hold($hold)
	{
		boresult::$instance->db->delete(self::HOLDS_TABLE, is_array($hold) ? $hold : array('hold_id' => $hold),
			__LINE__, __FILE__, 'ranking');

		return boresult::$instance->db->affected_rows();
	}

	/**
	 * Save an uploaded topo for a given route
	 *
	 * @param array $route values for keys 'WetId', 'GrpId', 'route_order'
	 * @param array|string $topo path or etemplate file array of uploaded topo-image
	 * @param string &$file=null on return path of topo-image
	 * @return boolean true on success, false otherwise
	 */
	public static function save_topo(array $route, $topo, &$file=null)
	{
		$dir = self::get_topo_dir($route,true,true);	// true = check judge perms
		$name = egw_vfs::basename(is_array($topo) ? $topo['name'] : $topo);
		$num = 0;
		foreach(self::get_topos($route) as $path)
		{
			if (($n=(int)substr(egw_vfs::basename($path),1)) >= $num) $num = $n+1;
		}
		$file = $dir.'/'.(int)$route['route_order'].$num.'_'.$name;

		return egw_vfs::copy_uploaded($topo, $file, null, true);
	}

	/**
	 * Get topo directory for a given route AND create it if it does not yet exist and $create==true
	 *
	 * @param array $route
	 * @param boolean $create=true true create directory, if it does not exist
	 * @param boolean $check_perms=true check if user has necessary permissions to upload/delete topo - is a judge or admin
	 * @throws egw_exception_wrong_userinput
	 * @throws egw_exception_wrong_parameter
	 * @return string vfs directory name
	 */
	public static function get_topo_dir(array $route, $create=true, $check_perms=true)
	{
		$dir = boresult::$instance->config['vfs_pdf_dir'];
		if ($dir[0] != '/' || !egw_vfs::file_exists($dir) || !egw_vfs::is_dir($dir))
		{
			throw new egw_exception_wrong_userinput(lang('Wrong or NOT configured VFS directory').': '.$dir);
		}
		if (!($comp = boresult::$instance->comp->read($route['WetId'])))
		{
			throw new egw_exception_wrong_parameter("Competition $route[WetId] NOT found!");
		}
		if ($check_perms && !boresult::$instance->is_admin && !boresult::$instance->is_judge($comp))
		{
			throw new egw_exception_wrong_parameter(lang('Permission denied!'));
		}
		if (!($grp = boresult::$instance->cats->read($route['GrpId'])))
		{
			throw new egw_exception_wrong_parameter("Category $route[GrpId] NOT found!");
		}
		$dir .= '/'.(int)$comp['datum'].'/'.$comp['rkey'].'/'.$grp['rkey'];
		if (!egw_vfs::file_exists($dir) && !egw_vfs::mkdir($dir,0777,STREAM_MKDIR_RECURSIVE))
		{
			throw new egw_exception_wrong_userinput(lang('Can NOT create topo directory %1!',$dir));
		}
		return $dir;
	}

	/**
	 * Get all uploaded topos of a given route
	 *
	 * @param array $route values for keys 'WetId', 'GrpId', 'route_order'
	 * @return array of vfs pathes
	 */
	public static function get_topos(array $route)
	{
		$topos = egw_vfs::find(self::get_topo_dir($route,false),$p=array(
			'maxdepth' => 1,
			'name' => $route['route_order'].'?_*',
			'mime' => 'image',
		));
		return $topos;
	}

	/**
	 * Delete a topo specified by it's path
	 *
	 * @param array $route values for keys 'WetId', 'GrpId', 'route_order'
	 * @param string $topo
	 * return boolean true on success, false on error
	 */
	public static function delete_topo(array $route, $topo)
	{
		$dir = self::get_topo_dir($route, false, true);	// true = check judge perms

		if (strpos($topo, $dir.'/') === 0)	// topo is inside regular topo-dir
		{
			self::delete_hold(array(
				'WetId' => $route['WetId'],
				'GrpId' => $route['GrpId'],
				'route_order' => $route['route_order'],
				'hold_topo' => (int)substr(basename($topo),1),
			));
			return egw_vfs::unlink($topo);
		}
		return false;
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
ranking_measurement::init_static();