<?php
/**
 * EGroupware digital ROCK Rankings - lead measurement
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
use EGroupware\Api\Egw;
use EGroupware\Api\Vfs;

/**
 * Measurement plugin for lead competitions
 */
class ranking_measurement extends ranking_boulder_measurement
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
		// Framework::validate_file|includeCSS does not work if template was submitted
		if (Api\Json\Response::isJSONResponse())
		{
			Api\Json\Response::get()->includeScript($GLOBALS['egw_info']['server']['webserver_url'].
				'/ranking/js/jquery.scrollIntoView.min.js');
		}
		else
		{
			Framework::includeJS('/ranking/js/jquery.scrollIntoView.min.js');
		}
		//echo "<p>nm[topo]=".array2string($content['nm']['topo'])."</p>\n";
		foreach(self::get_topos($content) as $n => $path)
		{
			$sel_options['topo'][$path] = array(
				'label' => lang('%1. topo',$n+1),
				'title' => $path,
			);
		}
		// no topo selected or topo no longer there, use the first one, if existing
		if ($sel_options['topo'] && (!$content['nm']['topo'] || !isset($sel_options['topo'][$content['nm']['topo']])))
		{
			$content['nm']['topo'] = key($sel_options['topo']);
		}
		elseif (!$sel_options['topo'])
		{
			$content['nm']['topo'] = '';
		}
		if (count($sel_options['topo']) <= 1)
		{
			$sel_options['topo'][$content['nm']['topo']] = ' ';
			$readonlys['nm[topo]'] = true;
		}
		$keys = self::query2keys($content['nm']);
		unset($keys['hold_topo']);
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
					$content['result_height'] = $row['result_height'];
					$content['result_plus']   = $row['result_plus'];
					$content['result_time']   = $row['result_time'];
				}
				$sel_options['PerId'][$row['PerId']] = ranking_result_bo::athlete2string($row, false);
			}
		}
		if ($content['nm']['topo'])
		{
			$content['transparent'] = Egw::link(Vfs::download_url($content['nm']['topo']));
			$content['holds'] = self::get_holds(self::query2keys($content['nm']));
		}
	}

	/**
	 * Store a hold on server-side
	 *
	 * @param array $hold
	 * @throws Api\Exception\WrongParameter
	 */
	public static function ajax_save_hold(array $hold)
	{
		$query =& self::get_check_session();

		$response = Api\Json\Response::get();

		if (!($hold = self::save_hold(array_merge($hold,self::query2keys($query)))))
		{
			$response->alert(lang('Error storing handhold!'));
		}
		else
		{
			$response->call('app.ranking.add_handhold', $hold);
		}
	}

	/**
	 * Store a hold on server-side
	 *
	 * @param array $hold
	 * @throws Api\Exception\WrongParameter
	 */
	public static function ajax_renumber_holds(array $hold)
	{
		// save current hold, in case user changed something
		self::ajax_save_hold($hold);

		$last_height = $hold['height'];
		$query =& self::get_check_session();
		$response = Api\Json\Response::get();
		foreach(self::get_holds(self::query2keys($query)) as $h)
		{
			if ($h['height'] >= $hold['height'] && $h['hold_id'] != $hold['hold_id'])
			{
				$h['height'] = ++$last_height;

				if (!($h = self::save_hold(array_merge($h,self::query2keys($query)))))
				{
					$response->alert(lang('Error storing handhold!'));
					break;
				}
				$response->call('app.ranking.add_handhold', $h);
			}
		}
	}

	/**
	 * Delete hold on server-side
	 *
	 * @param int $hold
	 * @throws Api\Exception\WrongParameter
	 */
	public static function ajax_delete_hold($hold)
	{
		$query =& self::get_check_session();

		if (!(int)$hold || !self::delete_hold(array_merge(self::query2keys($query),array('hold_id' => $hold))))
		{
			$response = Api\Json\Response::get();
			$response->alert(lang('Error deleting handhold!'));
		}
	}

	/**
	 * Load topo data from server
	 *
	 * @param string $path
	 */
	public static function ajax_load_topo($path)
	{
		$response = Api\Json\Response::get();

		$query =& Api\Cache::getSession('ranking', 'result');
		$query['topo'] = $path;

		$holds = self::get_holds(self::query2keys($query));
		$response->call('app.ranking.show_handholds', $holds);
	}

	/**
	 * Convert query array to holds keys
	 *
	 * @param array $query
	 * @return array
	 */
	protected static function query2keys(array $query)
	{
		$keys = parent::query2keys($query);
		$keys['hold_topo'] = (int)substr(Vfs::basename($query['topo']),1);

		return $keys;
	}

	const HOLDS_TABLE = 'RouteHolds';

	/**
	 * Store a hold in the DB
	 *
	 * @param array $_hold values for keys WetId, GrpId, route_order, topo or hold_id, plus data xpercent, ypercent, hold_height
	 * @return array|boolean
	 */
	public static function save_hold($_hold)
	{
		$log = __METHOD__.'('.array2string($_hold).')';

		foreach(array('xpercent','ypercent','height') as $name)
		{
			if (isset($_hold[$name])) $_hold['hold_'.$name] = round(100.0*$_hold[$name]);	// we store 100th % or cm
		}
		$table_def = ranking_result_bo::$instance->db->get_table_definitions('ranking',self::HOLDS_TABLE);
		$hold = array_intersect_key($_hold,$table_def['fd']);

		if ($hold['hold_id'] > 0)
		{
			ranking_result_bo::$instance->db->update(self::HOLDS_TABLE, $hold, array('hold_id'=>$hold['hold_id']), __LINE__, __FILE__, 'ranking');
		}
		else
		{
			$hold['hold_height'] = ranking_result_bo::$instance->db->select(self::HOLDS_TABLE, 'MAX(hold_height)',
				array_intersect_key($hold,array_flip(array('WetId','GrpId','route_order','hold_topo'))),
				__LINE__, __FILE__, false, '', 'ranking')->fetchColumn()+100;

			ranking_result_bo::$instance->db->insert(self::HOLDS_TABLE, $hold, false, __LINE__, __FILE__, 'ranking');
			$hold['hold_id'] = ranking_result_bo::$instance->db->get_last_insert_id(self::HOLDS_TABLE, 'hold_id');
		}
		$holdout = self::db2hold($hold);

		error_log($log.' returning '.array2string($holdout['hold_id'] ? $holdout : false));
		return $holdout['hold_id'] ? $holdout : false;
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
		foreach(ranking_result_bo::$instance->db->select(self::HOLDS_TABLE, array('hold_id','hold_xpercent','hold_ypercent','hold_height','hold_type'),
				array_intersect_key($keys,array_flip(array('WetId','GrpId','route_order','hold_topo'))),
				__LINE__, __FILE__, false, 'ORDER BY hold_height ASC', 'ranking') as $hold)
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
		ranking_result_bo::$instance->db->delete(self::HOLDS_TABLE, is_array($hold) ? $hold : array('hold_id' => $hold),
			__LINE__, __FILE__, 'ranking');

		return ranking_result_bo::$instance->db->affected_rows();
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
		$name = Vfs::basename(is_array($topo) ? $topo['name'] : $topo);
		$num = 0;
		foreach(self::get_topos($route) as $path)
		{
			if (($n=(int)substr(Vfs::basename($path),1)) >= $num) $num = $n+1;
		}
		$file = $dir.'/'.(int)$route['route_order'].$num.'_'.$name;

		return Vfs::copy_uploaded($topo, $file, null, false);
	}

	/**
	 * Get topo directory for a given route AND create it if it does not yet exist and $create==true
	 *
	 * @param array $route
	 * @param boolean $create =true true create directory, if it does not exist
	 * @param boolean $check_perms =true check if user has necessary permissions to upload/delete topo - is a judge or admin
	 * @param array &$comp=null on return competition array
	 * @param array &$cat=null on return category array
	 * @throws Api\Exception\WrongUserinput
	 * @throws Api\Exception\WrongParameter
	 * @return string|boolean vfs directory name or false if directory does not exists and !$create
	 */
	public static function get_topo_dir(array $route, $create=true, $check_perms=true, &$comp=null, &$cat=null)
	{
		$dir = ranking_result_bo::$instance->config['vfs_topo_dir'];
		if ($dir[0] != '/' || !Vfs::file_exists($dir) || !Vfs::is_dir($dir))
		{
			throw new Api\Exception\WrongUserinput(lang('Wrong or NOT configured VFS directory').': '.$dir);
		}
		if (!($comp = ranking_result_bo::$instance->comp->read($route['WetId'])))
		{
			throw new Api\Exception\WrongParameter("Competition $route[WetId] NOT found!");
		}
		if ($check_perms && !ranking_result_bo::$instance->is_admin && !ranking_result_bo::$instance->is_judge($comp, true, $route))
		{
			throw new Api\Exception\WrongParameter(lang('Permission denied!'));
		}
		if (!($cat = ranking_result_bo::$instance->cats->read($route['GrpId'])))
		{
			throw new Api\Exception\WrongParameter("Category $route[GrpId] NOT found!");
		}
		$dir .= '/'.(int)$comp['datum'].'/'.$comp['rkey'].'/'.$cat['rkey'];
		if (!Vfs::file_exists($dir))
		{
			if (!$create) return false;

			if (!Vfs::mkdir($dir,0777,STREAM_MKDIR_RECURSIVE))
			{
				throw new Api\Exception\WrongUserinput(lang('Can NOT create topo directory %1!',$dir));
			}
		}
		return $dir;
	}

	/**
	 * Get all uploaded topos of a given route
	 *
	 * @param array $route values for keys 'WetId', 'GrpId', 'route_order'
	 * @param boolean $check_perms =true check if user has necessary permissions to upload/delete topo - is a judge or admin
	 * @param array &$comp=null on return competition array
	 * @param array &$cat=null on return category array
	 * @return array of vfs pathes
	 */
	public static function get_topos(array $route, $check_perms=true, &$comp=null, &$cat=null)
	{
		$topos = array();

		if (($dir = self::get_topo_dir($route,false, $check_perms, $comp, $cat)))
		{
			$topos = Vfs::find($dir,$p=array(
				'maxdepth' => 1,
				'name' => $route['route_order'].'?_*',
				'mime' => 'image',
			));
		}
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

		if ($dir && strpos($topo, $dir.'/') === 0)	// topo is inside regular topo-dir
		{
			self::delete_hold(array(
				'WetId' => $route['WetId'],
				'GrpId' => $route['GrpId'],
				'route_order' => $route['route_order'],
				'hold_topo' => (int)substr(basename($topo),1),
			));
			return Vfs::unlink($topo);
		}
		return false;
	}
}
