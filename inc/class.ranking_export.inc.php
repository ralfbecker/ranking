<?php
/**
 * EGroupware digital ROCK Rankings - XML/JSON export logic
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2011-16 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$
 */

/**
 * XML/JSON export logic
 *
 * @link http://www.digitalrock.de/egroupware/ranking/README
 *
 * Exported data is cached in a configurable cache specified by Install-ID in ranking configuration
 * to allow two instances to share a ranking database.
 */
class ranking_export extends ranking_result_bo
{
	/**
	 * Expires time for 404 Not found
	 */
	const EXPIRES_404_NOT_FOUND = 900;

	/**
	 * Default expires time if nothing is explicitly set
	 */
	const EXPORT_DEFAULT_EXPIRES = 7200;

	/**
	 * Disable caching for following development systems
	 *
	 * @var array
	 */
	public static $ignore_caching_hosts = array(
		'boulder.outdoor-training.de', 'ralfsmacbook.local','ralfsmac.local', 'localhost', 'mserver.egroupware.org'
	);

	/**
	 * Get result for export specified via URL
	 *
	 * Used for JSON and XML alike, as it returns raw data.
	 *
	 * @param string &$root_tag=null on return root tag for xml export
	 * @return array
	 */
	public static function export(&$root_tag=null)
	{
		try
		{
			if (isset($_GET['person']))
			{
				$export = new ranking_export();
				$result = $export->export_profile($_GET['person'], $_GET['cat']);
				$root_tag = 'profile';
			}
			elseif (isset($_GET['cat']) && (!isset($_GET['comp']) && !isset($_GET['type']) || $_GET['type'] === 'ranking'))
			{
				$export = new ranking_export();
				$result = $export->export_ranking($_GET['cat'], isset($_GET['date']) ? $_GET['date'] : $_GET['comp'], $_GET['cup']);
				$root_tag = 'ranking';
			}
			elseif (in_array($_GET['type'], array('nat_team_ranking','sektionenwertung','regionalzentren')))
			{
				$export = new ranking_export();
				$result = $export->export_aggregated($_GET['type'], $_GET['date'], $_GET['comp'], $_GET['cup'], $_GET['cat']);
				$root_tag = 'aggregated';
			}
			elseif (isset($_GET['term']))
			{
				$export = new ranking_export();
				$result = $export->export_athlete_search($_GET['term']);
				$root_tag = 'athletes';
			}
			elseif (isset($_GET['nation']) || !isset($_GET['comp']))
			{
				$export = new ranking_export();
				$result = $export->export_calendar($_GET['nation'], $_GET['year'], $_GET['filter']);
				$root_tag = 'calendar';
			}
			elseif (isset($_GET['comp']) && $_GET['type'] == 'starters')
			{
				$export = new ranking_export();
				$result = $export->export_starters($_GET['comp']);
				$root_tag = 'starters';
			}
			elseif (isset($_GET['comp']) && !isset($_GET['cat']) || isset($_GET['filter']))
			{
				$export = new ranking_export();
				$result = $export->export_results($_GET['comp'], $_GET['num'], $_GET['all'], $_GET['filter']);
				$root_tag = 'results';
			}
			else
			{
				$result = self::export_route($_GET['comp'],$_GET['cat'],isset($_GET['route']) ? $_GET['route'] : $_GET['type']);
				$root_tag = 'route';
			}
		}
		catch(Exception $e)
		{
			egw_session::cache_control(self::EXPIRES_404_NOT_FOUND);		// allow to cache for 15min
			header("HTTP/1.1 404 Not Found");
			echo "<html>\n<head>\n\t<title>Error ".$e->getMessage()."</title>\n</head>\n";
			echo "<body>\n\t<h1>".$e->getMessage()."</h1>\n";
			echo "<p>The requested ressource was not found on this server.<br>\n<br>\n";
			echo 'URI: ' . $_SERVER['REQUEST_URI'] . "</p>\n";
			echo "</body></html>\n";
			_egw_log_exception($e);
			exit;
		}
		return $result;
	}

	/**
	 * Page used for all IFSC urls
	 */
	const IFSC_BASE_PAGE = '/index.php/world-competition';

	/**
	 * Return base url
	 *
	 * @param boolean $use_egw =false do we need egroupware or website
	 * @return string with schema and host (no path or trailing slash)
	 */
	static function base_url($use_egw=false)
	{
		switch($_SERVER['HTTP_HOST'])
		{
//			case 'ralfsmacbook.local':
			case 'www.ifsc-climbing.org':
			case 'ifsc.egroupware.net':
			case 'egw.ifsc-climbing.org':
				$host = $use_egw ? 'egw.ifsc-climbing.org' : 'www.ifsc-climbing.org';	// use CDN urls
				break;

			default:
				$host = $_SERVER['HTTP_HOST'];
				break;
		}
		return 'http://'.$host;
	}

	/**
	 * Get URL for a result
	 *
	 * Currently /result.php is used for every HTTP_HOST not www.ifsc-climbing.org,
	 * for which /index.php?page_name=result is used.
	 *
	 * @param int $comp
	 * @param int $cat
	 * @return string
	 */
	static function result_url($comp, $cat)
	{
		static $base=null;
		if (is_null($base))
		{
			$base = self::base_url();
			if ($base == 'http://www.ifsc-climbing.org')
			{
				$base .= self::IFSC_BASE_PAGE.'#!comp=';
			}
			else
			{
				$link = egw::link('/ranking/sitemgr/digitalrock/eliste.html#comp=');
				$base = ($link[0] == '/' ? $base : '').$link;
			}
		}
		return $base.$comp.'&cat='.$cat;
	}

	/**
	 * Get URL for athlete profile
	 *
	 * Currently /pstambl.php is used for every HTTP_HOST not www.ifsc-climbing.org,
	 * for which /index.php?page_name=pstambl is used.
	 *
	 * @param array $athlete
	 * @param int $cat =null
	 * @return string
	 */
	static function profile_url($athlete,$cat=null)
	{
		static $base=null;
		if (is_null($base))
		{
			$base = self::base_url();
			if ($base == 'http://www.ifsc-climbing.org')
			{
				//$base .= self::IFSC_BASE_PAGE.'#!person=';
				$base .= '/index.php?option=com_ifsc&view=athlete&id=';
			}
			else
			{
				$link = egw::link('/ranking/sitemgr/digitalrock/pstambl.html#person=');
				$base = ($link[0] == '/' ? $base : '').$link;
			}
		}
		return $base.$athlete['PerId'].($cat ? '&cat='.$cat : '');
	}

	/**
	 * Get URL for a ranking
	 *
	 * Currently /result.php is used for every HTTP_HOST not www.ifsc-climbing.org,
	 * for which /index.php?page_name=result is used.
	 *
	 * @param int|array $cat
	 * @param int $cup =null
	 * @param int $comp =null
	 * @return string
	 */
	static function ranking_url($cat, $cup=null, $comp=null)
	{
		static $base=null;
		if (is_null($base))
		{
			$base = self::base_url();
			if ($base == 'http://www.ifsc-climbing.org')
			{
				$base .= self::IFSC_BASE_PAGE.'#!type=ranking&cat=';
			}
			else
			{
				$base .= '/ranglist.php?type=ranking&cat=';
			}
		}
		if (is_array($cat)) $cat = $cat['GrpId'];
		if (is_array($cup)) $cup = $cup['SerId'];
		if (is_array($comp)) $comp = $comp['WetId'];

		return $base.$cat.($cup ? '&cup='.$cup : '').($comp ? '&comp='.$comp : '');
	}

	/**
	 * Expires header time for proxys/cdn if there's not yet a startlist or general result
	 *
	 * We cant invalidate proxys
	 *
	 * @var int
	 */
	const EXPORT_ROUTE_NO_STARTLIST = 300;
	/**
	 * Livetime of cache entries
	 *
	 * Can be fairly high, as cache is kept consistent, by calls from ranking_result_bo::save_result to immediatly update the cache
	 * and calls to ranking_result_bo::delete_export_route_cache() to invalidate it from route and route_result classes for deletes or updates.
	 *
	 * @var int
	 */
	const EXPORT_ROUTE_TTL = 86400;
	/**
	 * Expires header time for proxys/cdn on a running (not offical result) heat
	 *
	 * We cant invalidate proxys
	 *
	 * @var int
	 */
	const EXPORT_ROUTE_RUNNING_EXPIRES = 10;
	/**
	 * Expires header time for proxys/cdn on a recently offical result
	 *
	 * @var int
	 */
	const EXPORT_ROUTE_RECENT_OFFICAL_EXPIRES = 300;
	/**
	 * Timeout for recently official
	 *
	 * @var int
	 */
	const EXPORT_ROUTE_RECENT_TIMEOUT = 14440;	// 4h
	/**
	 * Expires header time for proxys/cdn on an older offical result
	 *
	 * @var int
	 */
	const EXPORT_ROUTE_OFFICAL_EXPIRES = 86400;

	/**
	 * Where to cache: egw_cache::Instance or Install-ID of instance to use
	 *
	 * @var string
	 */
	static public $cache_level;

	/**
	 * Read where to cache export data from ranking configuraton
	 */
	static protected function set_cache_level()
	{
		$config = config::read('ranking');

		self::$cache_level = $config['export_cache_level'] ? $config['export_cache_level'] : egw_cache::INSTANCE;
	}

	/**
	 * Set some data in the cache
	 *
	 * @param string $location location name for data
	 * @param mixed $data
	 * @param int $expiration =0 expiration time in seconds, default 0 = never
	 * @return boolean true if data could be stored, false otherwise
	 */
	static public function setCache($location,$data,$expiration=0)
	{
		if (!isset(self::$cache_level)) self::set_cache_level();

		return egw_cache::setCache(self::$cache_level, 'ranking', $location, $data, $expiration);
	}

	/**
	 * Get some data from the cache
	 *
	 * @param string|array $location location(s) name for data
	 * @param callback $callback =null callback to get/create the value, if it's not cache
	 * @param array $callback_params =array() array with parameters for the callback
	 * @param int $expiration =0 expiration time in seconds, default 0 = never
	 * @return mixed NULL if data not found in cache (and no callback specified) or
	 * 	if $location is an array: location => data pairs for existing location-data, non-existing is not returned
	 */
	static public function getCache($location,$callback=null,array $callback_params=array(),$expiration=0)
	{
		if (!isset(self::$cache_level)) self::set_cache_level();

		return egw_cache::getCache(self::$cache_level, 'ranking', $location, $callback, $callback_params,$expiration);
	}

	/**
	 * Unset some data in the cache
	 *
	 * @param string $location location name for data
	 * @return boolean true if data was set, false if not (like isset())
	 */
	static public function unsetCache($location)
	{
		if (!isset(self::$cache_level)) self::set_cache_level();

		return egw_cache::unsetCache(self::$cache_level, 'ranking', $location);
	}

	/**
	 * Delete export route cache for given route and additionaly the general result
	 *
	 * @param int|array $comp WetId or array with values for WetId, GrpId and route_order
	 * @param int $cat =null GrpId
	 * @param int $route_order =null
	 * @param boolean $previous_heats =false also invalidate previous heats, eg. if new heats got created to include them in route_names
	 */
	public static function delete_route_cache($comp, $cat=null, $route_order=null, $previous_heats=false)
	{
		if (is_array($comp))
		{
			$cat = $comp['GrpId'];
			$route_order = $comp['route_order'];
			$comp = $comp['WetId'];
		}
		self::unsetCache($loc='route:'.$comp.':'.$cat.':'.$route_order);
		//error_log(__METHOD__."($comp, $cat, $route_order, $previous_heats) unsetInstance('$loc')");
		self::unsetCache($loc='route:'.$comp.':'.$cat.':-1');
		//error_log(__METHOD__."($comp, $cat, $route_order, $previous_heats) unsetInstance('$loc')");
		self::unsetCache($loc='route:'.$comp.':'.$cat.':');	// used if no route is specified!
		//error_log(__METHOD__."($comp, $cat, $route_order, $previous_heats) unsetInstance('$loc')");

		if ($previous_heats)
		{
			while($route_order-- > 0)
			{
				self::unsetCache($loc='route:'.$comp.':'.$cat.':'.$route_order);
				//error_log(__METHOD__."($comp, $cat, $route_order, $previous_heats) unsetInstance('$loc')");
			}
		}
	}

	/**
	 * Export route for xml or json access, cached access
	 *
	 * Get's called from save_result with $update_cache===true, to keep the cache updated
	 *
	 * @param int $comp
	 * @param int|string $cat GrpId or rkey
	 * @param int|string $heat =-1 string 'result' to use result from ranking
	 * @param boolean $update_cache =false false: read result from cache, false: update cache, before using it
	 * @return array
	 */
	public static function export_route($comp,$cat,$heat=-1,$update_cache=false)
	{
		static $instance=null;

		// normalise cat, we only want to cache one - the numeric - id
		if (!is_numeric($cat))
		{
			$rkey = strtolower($cat);
			$cat_rkey2id = self::getCache('cat_rkey2id');
			if (!isset($cat_rkey2id[$rkey]))
			{
				if (!isset($instance)) $instance = new ranking_export();
				if (!($cat_arr = $instance->cats->read($rkey)))
				{
					throw new Exception(lang('Category NOT found !!!'));
				}
				$cat_rkey2id[$rkey] = $cat_arr['GrpId'];
				self::setCache('cat_rkey2id', $cat_rkey2id);
			}
			$cat = $cat_rkey2id[$rkey];
		}
		// can we use the cached data und do we have it?
		$location = 'route:'.$comp.':'.$cat.':'.$heat;
		// switch caching off for speed-cli.php, as it can not (un)set the cache,
		// because of permissions of /tmp/egw_cache only writable by webserver-user
		// for all other purposes caching is ok and should be enabled
		if (in_array($_SERVER['HTTP_HOST'], self::$ignore_caching_hosts) ||
			$update_cache || !($data = self::getCache($location)))
		{
			if (!isset($instance)) $instance = new ranking_export();

			if ($heat === 'result')
			{
				$data = $instance->_export_result($comp, $cat);
			}
			else
			{
				try {
					$data = $instance->_export_route($comp, $cat, $heat);
					if (count($data['route_names']) == 1 &&
							(!isset($heat) || (string)$heat === '') && $data['route_order'] != 0 ||
						!$data['participants'])
					{
						$data = $instance->_export_route($comp, $cat, 0);
						$no_general_result_yet = true;
					}
				}
				catch(Exception $e) {
					// try if we have a result in ranking
					if (!isset($heat) || (string)$heat === '')
					{
						$data = $instance->_export_result($comp, $cat);
					}
					else
					{
						throw $e;
					}
				}
			}
			// setting expires depending on result offical and how long it is offical
			$data['expires'] = !$data['participants'] ? self::EXPORT_ROUTE_NO_STARTLIST :	// no startlist yet
				(!isset($data['route_result']) ? self::EXPORT_ROUTE_RUNNING_EXPIRES :
				(time()-$data['last_modified'] > self::EXPORT_ROUTE_RECENT_TIMEOUT ?
					self::EXPORT_ROUTE_OFFICAL_EXPIRES : self::EXPORT_ROUTE_RECENT_OFFICAL_EXPIRES));

			// if general result was requested, but we have only a single quali currently
			// we need to limit expires to EXPORT_ROUTE_NO_STARTLIST=300, to get next startlist
			// even if current quali is official!
			if ($no_general_result_yet && $data['expires'] > self::EXPORT_ROUTE_NO_STARTLIST)
			{
				$data['expires'] = self::EXPORT_ROUTE_NO_STARTLIST;
			}

			self::setCache($location, $data, self::EXPORT_ROUTE_TTL);

			// update general result too?
			if ($update_cache && $heat > 0)
			{
				self::setCache('route:'.$comp.':'.$cat.':-1',
					self::$instance->_export_route($comp, $cat, -1), self::EXPORT_ROUTE_TTL);
			}
		}
		if (!empty($_GET['upcaseNames']))
		{
			$strtoupper = function_exists('mb_strtoupper') ? 'mb_strtoupper' : 'strtoupper';
			foreach($data['participants'] as &$athlete)
			{
				$athlete['lastname'] = $strtoupper($athlete['lastname']);
			}
		}
		return $data;
	}

	/**
	 * Export route for xml or json access, cache-free version
	 *
	 * @param int $comp
	 * @param int|string|array $cat
	 * @param int $heat =-1
	 * @return array
	 */
	protected  function _export_route($comp,$cat,$heat=-1)
	{
		//$start = microtime(true);

		if (!is_array($cat) && !($cat = $this->cats->read($cat)))
		{
			throw new Exception(lang('Category NOT found !!!'));
		}

		//echo "<pre>".print_r($cat,true)."</pre>\n";
		if (!($comp = $this->comp->read($comp)))
		{
			throw new Exception(lang('Competition NOT found !!!'));
		}
		if (!($discipline = $comp['discipline']))
		{
			$discipline = $cat['discipline'];
		}
		if (!isset($heat) || (string)$heat === '') $heat = -1;	// General result

		if (!($route = $this->route->read(array(
			'WetId' => $comp['WetId'],
			'GrpId' => $cat['GrpId'],
			'route_order' => $heat,
		))))
		{
			throw new Exception(lang('Route NOT found !!!'));
		}
		if (!empty($route['discipline'])) $discipline = $route['discipline'];

		//printf("<p>reading route+result took %4.2lf s</p>\n",microtime(true)-$start);

		//echo "<pre>".print_r($route,true)."</pre>\n";exit;

		// append category name to route name
		$route['route_name'] .= ' '.$cat['name'];
		$route['comp_name'] = $comp['name'];
		$route['comp_date'] = $comp['datum'];
		$route['nation'] = $comp['nation'];
		$route['display_athlete'] = $comp['display_athlete'] ? $comp['display_athlete'] :
			ranking_competition::nation2display_athlete($comp['nation']);

		// set quali_preselected, if set for category
		if (($route['quali_preselected'] = $this->comp->quali_preselected($cat['GrpId'], $comp['quali_preselected'])) &&
			($heat == 0 || $heat == 1))
		{
			try {
				$ranking = $this->export_ranking($cat, $comp['datum'], $comp['serie']);
			}
			catch(Exception $e) {
				unset($e);
				$ranking = null;	// ignore no ranking defined or no result yet
			}
		}

		if ($this->route_result->isRelay != ($discipline == 'speedrelay'))
		{
			$this->route_result->__construct($this->config['ranking_db_charset'],$this->db,null,
					$discipline == 'speedrelay');
		}
		$query = array(
			'order' => 'result_rank',
			'sort'  => 'ASC',
			'route' => $heat,
			'discipline' => $discipline,
		);
		self::process_sort($query,$this->route_result->isRelay);

		if (!($result = $this->route_result->search(array(),false,$query['order'].' '.$query['sort'],'','',false,'AND',false,$filter=array(
			'WetId' => $comp['WetId'],
			'GrpId' => $cat['GrpId'],
			'route_order' => $heat,
			'discipline'  => $discipline,
			'route_type'  => $route['route_type'],
			'quali_preselected' => $this->comp->quali_preselected($cat['GrpId'], $comp['quali_preselected']),
		)))) $result = array();
		//_debug_array($filter); _debug_array($result);

		// return route_names as part of route, not as participant
		if (isset($result['route_names']))
		{
			// no qualification overall or group results
			unset($result['route_names'][-3], $result['route_names'][-4], $result['route_names'][-5]);
			$route['route_names'] = $result['route_names'];
			unset($result['route_names']);
		}
		else
		{
			$route['route_names'] = $this->route->query_list('route_name','route_order',array(
				'WetId' => $comp['WetId'],
				'GrpId' => $cat['GrpId'],
				'route_order >= -1',	// no qualification overall or group results
			),'route_order');
		}
		// if we have more then one route, add general result, in case is has never been stored to database
		if (count($route['route_names']) > 1 && !isset($route['route_names']['-1']))
		{
			$route['route_names']['-1'] = lang('General Result');
		}
		else
		{
			$general_result = $heat == -1 ? $route : $this->route->read(array(
				'WetId' => $comp['WetId'],
				'GrpId' => $cat['GrpId'],
				'route_order' => -1,
			));
		}
		// mark whole category offical/finished if general result is offical or competition date 10+ days over
		$route['category_offical'] = $general_result && ($general_result['route_status'] == STATUS_RESULT_OFFICIAL ||
			egw_time::to($comp['datum'],'ts') - time() > 10*24*3600);

		switch($discipline)
		{
			default:
			case 'lead':
				$num_current = 1;
				break;
			case 'boulder':
				$num_current = $route['route_num_problems'];
				break;
			case 'speed':
			case 'speedrelay':
				$num_current = 2;
				break;
		}
		for($i = 1; $i <= $num_current; ++$i)
		{
			$route['current'][] = $route['current_'.$i] ? $route['current_'.$i] : null;
			unset($route['current_'.$i]);
		}
		// remove empty/null values from route
		foreach($route as $name => $value)
		{
			if ((string)$value === '') unset($route[$name]);
		}
		$last_modified = (int)$route['route_modified'];

		// make sure route_result is set, if result is offical (and not if not)
		if ($route['route_status'] == STATUS_RESULT_OFFICIAL)
		{
			if (empty($route['route_result'])) $route['route_result'] = 'official';
		}
		else
		{
			unset($route['route_result']);
		}

		// remove not needed route attributes
		$route = array_diff_key($route,array_flip(array(
			'route_type',
			'dsp_id','frm_id','dsp_id2','frm_id2',
			'user_timezone_read',
			'route_modified','route_modifier',	// we have single last_modified
			'route_time_host','route_time_port',
			'route_status',	// 'route_result' is set if result is official
			'slist_order',
			'next_1','next_2','next_3','next_4','next_5','next_6','next_7','next_8',
			'RelayResults.*',	// not needed and gives warning in xml export
		)));
		if (!in_array($discipline, array('boulder','selfscore'))) unset($route['route_num_problems']);

		if ($discipline == 'speedrelay')	// fetch athlete names
		{
			foreach($result as $key => $row)
			{
				$ids[] = $row['PerId_1'];
				$ids[] = $row['PerId_2'];
				if (!empty($row['PerId_3'])) $ids[] = $row['PerId_3'];
			}
			foreach($this->athlete->search(array('PerId' => $ids),false) as $athlete)
			{
				$athletes[$athlete['PerId']] = self::athlete_attributes($athlete, $comp['nation'], $cat['GrpId']);
			}
			//echo "<pre>".print_r($athletes,true)."</pre>\n";die('Stop');
		}

		$tn = $unranked = $statistics = array();
		foreach($result as $key => $row)
		{
			if (isset($row['quali_points']) && $row['quali_points'])
			{
				$row['quali_points'] = number_format($row['quali_points'],2);
			}
			if ($row['result_modified'] > $last_modified) $last_modified = $row['result_modified'];

			if ($heat == -1)	// rename result to result0 for general result
			{
				$row['result0'] = $row['result'];
				unset($row['result']);
			}
			// use english names
			$row += self::athlete_attributes($row, $comp['nation'], $cat['GrpId']);

			if ($ranking)	// add ranking to athlete
			{
				foreach($ranking['participants'] as $participant)
				{
					if ($participant['PerId'] == $row['PerId'])
					{
						$row['ranking'] = $participant['result_rank'];
						break;
					}
				}
			}

			// remove &nbsp; in boulderheight results
			if ($discipline == 'boulderheight')
			{
				$row = str_replace('&nbsp;', ' ', $row);
			}

			// remove &nbsp; in lead results
			if ($discipline == 'lead')
			{
				if (isset($row['quali_points']))
				{
					for($i = 0; $i <= 1; ++$i)
					{
						if(isset($row['result'.$i]))
						{
							$row['result'.$i] = str_replace('&nbsp;',' ',$row['result'.$i]);
						}
					}
				}
				if(isset($row['result']))
				{
					list($row['result']) = explode('&nbsp;',$row['result']);
					$row['time'] = $row['result_time_l'];
				}
				for($i = 0; $i <= 5; ++$i)
				{
					if(isset($row['result'.$i]))
					{
						list($row['result'.$i]) = explode('&nbsp;',$row['result'.$i]);
					}
				}
			}
			// remove single boulder meaningless in general result, or not existing boulder
			if ($discipline == 'boulder')
			{
				for($i = 1; $i <= ranking_route_result::MAX_BOULDERS; ++$i)
				{
					if ($heat == -1 || $i > $route['route_num_problems'])
					{
						unset($row['boulder'.$i]);
					}
				}
			}
			// statistics
			if ($row['result_rank'])
			{
				switch($discipline)
				{
					case 'selfscore':
						for($i = 1; $i <= $route['route_num_problems']; $i++)
						{
							foreach(ranking_selfscore_measurement::score2btf($row['score'][$i], $route['selfscore_use']) as $key => $val)
							{
								if ($val) ++$statistics[$i][$key == 'zone' ? 'bonus' : $key];
							}
						}
						break;
					case 'boulder':
						for($i = 1; $i <= $route['route_num_problems']; $i++)
						{
							foreach(array('bonus' => 'zone', 'top' => 'top') as $name => $row_name)
							{
								if ($row[$row_name.$i])
								{
									++$statistics[$i][$name];
									if (!isset($statistics[$i][$name.'_min']) || $statistics[$i][$name.'_min'] > $row[$row_name.$i])
									{
										$statistics[$i][$name.'_min'] = $row[$row_name.$i];
									}
									if (!isset($statistics[$i][$name.'_max']) || $statistics[$i][$name.'_max'] < $row[$row_name.$i])
									{
										$statistics[$i][$name.'_max'] = $row[$row_name.$i];
									}
									$statistics[$i][$name.'_avg'] += $row[$row_name.$i];
								}
							}
						}
						break;
				}
			}
			// for speed show time_sum as result, plus result_l and result_r
			if (isset($row['time_sum']))
			{
				$row['result_l'] = $row['result'];
				$row['result'] = $row['time_sum'];
				unset($row['time_sum']);	// identical to result
			}
			if ($discipline == 'speedrelay')
			{
				unset($row['lastname']);
				unset($row['firstname']);
				unset($row['federation']);
				$athletes[$row['PerId_1']]['start_number'] = $row['start_number_1'];
				if ($heat > -1) $athletes[$row['PerId_1']]['result_time'] = $row['result_time_1'];
				$athletes[$row['PerId_2']]['start_number'] = $row['start_number_2'];
				if ($heat > -1) $athletes[$row['PerId_2']]['result_time'] = $row['result_time_2'];
				$row['athletes'] = array(
					$athletes[$row['PerId_1']],
					$athletes[$row['PerId_2']],
				);
				if (!empty($row['PerId_3']))
				{
					$athletes[$row['PerId_3']]['start_number'] = $row['start_number_3'];
					if ($heat > -1) $athletes[$row['PerId_3']]['result_time'] = $row['result_time_3'];
					$row['athletes'][] = $athletes[$row['PerId_3']];
				}
				unset($row['time_sum']);	// identical to result
			}
			// always return result attribute
			if ($heat != -1 && !isset($row['result'])) $row['result'] = '';

			// remove not needed attributes
			$row = array_diff_key($row,array_flip(array(
				// remove keys, they are already in route
				'GrpId','WetId','route_order','route_type','discipline','acl_fed',
				'geb_date',	// we still have birthyear
				// remove renamed values
				'vorname','nachname','verband','plz','ort',
				'general_result','org_rank','result_modifier',
				'RouteResults.*','result_detail',
				// speed single route use: result, result_l, result_r
				'result_time','result_time_l','result_time_r',
				// speed general result use: result*, result_rank*
				'result_time2','result_time3','result_time4','result_time5','result_time6',
				'start_order2','start_order3','start_order4','start_order5','start_order6',
				// lead general result
				'result_height','result_height1','result_height2','result_height3','result_height4','result_height5',
				'result_plus','result_plus1','result_plus2','result_plus3','result_plus4','result_plus5',
				// boulder general result
				'top','top1','top2','top3','top4','top5','top6','top7','top8',
				'zone','zone1','zone2','zone3','zone4','zone5','zone6','zone7','zone8',
				'result_top','result_top1','result_top2','result_top3','result_top4','result_top5','result_top6','result_top7','result_top8',
				'result_zone','result_zone1','result_zone2','result_zone3','result_zone4','result_zone5','result_zone6','result_zone7','result_zone8',
				// teamrelay
				'PerId_1','PerId_2','PerId_3',
				'start_number_1','start_number_2','start_number_3',
				'result_time_1','result_time_2','result_time_3',
				'RelayResults.*',	// not needed and gives warning in xml export
			)));

			ksort($row);
			if ($row['result_rank'])
			{
				$tn[$row[$this->route_result->id_col]] = $row;
			}
			else
			{
				$unranked[$row[$this->route_result->id_col]] = $row;
			}
		}
		$participants = array_merge($tn,$unranked);

		$ret = $route+array(
			'discipline'    => $discipline,
			'participants'  => $participants,
			'last_modified' => $last_modified,
		);
		if ($statistics)
		{
			switch($discipline)
			{
				case 'boulder':
					foreach($statistics as &$stats)
					{
						foreach(array('top', 'bonus') as $name)
						{
							if ($stats[$name])
							{
								$stats[$name.'_avg'] = round($stats[$name.'_avg'] / $stats[$name], 1);
							}
							else
							{
								$stats[$name] = 0;
								$stats[$name.'_min'] = $stats[$name.'_max'] = $stats[$name.'_avg'] = '';
							}
						}
					}
			}
			$ret['statistics'] = $statistics;
			$ret['dr_statistic'] = filemtime(EGW_SERVER_ROOT.'/ranking/js/dr_statistics.js');
		}

		// for official results add ranking and cup links
		if ($this->result->has_results(array(
			'WetId' => $comp['WetId'],
			'GrpId' => $cat['GrpId'],
		)))
		{
			$ret += $this->see_also_result($comp, $cat);
		}
		// add other categories incl. ranking ones
		$ret['categorys'] = array_values($this->_route_categories($comp, $this->_result_categories($comp)));

		unset($ret['last_modified']);	// to NOT cause etag change by not contained data
		$ret['etag'] = md5(serialize($ret));

		return $ret;
	}

	/**
	 * Get result-service categories for a given competition
	 *
	 * @param int|array $comp
	 * @param array $categories =array()
	 * @return array GrpId => array with keys GrpId and name
	 */
	protected function _route_categories($comp, $categories=array())
	{
		foreach($this->route->search(array(
			'WetId' => is_array($comp) ? $comp['WetId'] : $comp,
		)) as $route)
		{
			if (!isset($categories[$route['GrpId']]) || $categories[$route['GrpId']]['route_order'] < $route['route_order'])
			{
				$categories[$route['GrpId']] = array(
					'GrpId' => $route['GrpId'],
					'route_order' => $route['route_order'],
				);
			}
		}
		if ($categories)
		{
			foreach($this->cats->names(array('GrpId' => array_keys($categories)), 0) as $id => $name)
			{
				$categories[$id]['name'] = $name;
			}
		}
		return $categories;
	}

	/**
	 * Livetime of cache entries of calendar data
	 *
	 * We use a fairly low time, as we cant invalidate the cache,
	 * because of filter value
	 *
	 * @var int
	 */
	const EXPORT_CALENDAR_TTL = 300;
	/**
	 * Livetime of cache entries of calendar data of previous years
	 *
	 * @var int
	 */
	const EXPORT_CALENDAR_OLD_TTL = 86400;

	/**
	 * colum --> export name conversation (or false to suppress in export)
	 *
	 * @var array
	 */
	public static $rename_comp = array(
		'gruppen' => 'cats',
		'dru_bez' => 'short',
		'datum'   => 'date',
		'serie' => 'cup',
		'pflicht' => false, 'ex_pkte' => false,	'open' => false,	// currently not used
		'pkte' => false,	// not interesting for calendar
		'pkt_bis' => false,
		'feld_pkte' => false,
		'feld_bis' => false,
		'faktor' => false, // 'factor'
		'quota' => false,
		'host_quota' => false,
		'prequal_ranking' => false,
		'prequal_comp' => false,
		'prequal_comps' => false,
		'judges' => false,
		'no_complimentary' => false,
		'modifier' => false,
		'quota_extra' => false, //'extra_quotas',
		'prequal_extra' => false, //'extra_prequals',
		'user_timezone_read' => false,
		'quali_preselected' => false,
	);
	public static $rename_cat = array(
		'serien_pat' => false,	// not interesting
		'vor_rls' => false,
		'vor' => false,
		'rls' => false,
		'GrpIds' => false,	// only intersting for combined, so not for calendar
		'extra' => false,	// what's that anyway?
	);
	public static $rename_cup = array(
		'gruppen' => 'cats',
		'faktor' => false,	// currently not used
		'split_by_places' => false,	// not interesting for calendar
		'pkte' => false,
		'max_rang' => false,
		'max_serie' => false,
		'presets' => false,
	);
	/**
	 * Categories to permanently exclude from calendar
	 *
	 * @var array
	 */
	public static $calendar_exclude = array(
		3,		// ICC_LIZ
		8, 9,	// X_*_ADR
		10,		// X_PASS_A
		21,		// X_COACH
		22,		// X_DROCK
		120,	// ICC-TOF: Team Officals
	);

	/**
	 * Export a competition calendar for the given year and nation(s)
	 *
	 * @param array|string $nations
	 * @param int $year =null default current year
	 * @param array $filter =null eg. array('fed_id' => 123)
	 * @return array or competitions
	 * @todo turn rename_key call in explicit column-list with from AS to
	 */
	public function export_calendar($nations,$year=null,array $filter=null)
	{
		if (!(int)$year) $year = (int)date('Y');
		$location = 'calendar:'.json_encode(array(
			'nation' => $nations,
			'year' => $year,
			'filter' => $filter,
		));
		if (!in_array($_SERVER['HTTP_HOST'], self::$ignore_caching_hosts) &&
			($data = self::getCache($location)))
		{
			return $data;
		}

		$where = self::process_filter($filter);

		if (!is_array($nations)) $nations = explode(',',$nations);
		if (($key = array_search('',$nations)) !== false)
		{
			unset($nations[$key]);
			$sql = 'nation IS NULL';
		}
		if ($nations)
		{
			$sql = '('.($sql ? $sql.' OR ' : '').$this->db->expression($this->comp->table_name,array('nation'=>$nations)).')';
		}
		$where[] = $sql;

		// get available years using given calendar filter
		$years = array();
		foreach($this->comp->search(null,"DISTINCT DATE_FORMAT(datum,'%Y') AS year",'datum DESC','','','','AND',false,$where) as $data)
		{
			$years[] = $data['year'];
		}
		$where[] = 'datum LIKE '.$this->db->quote((int)$year.'%');

		//error_log(__METHOD__."('$nation', $year, ".array2string($filter).') --> where='.array2string($where));
		$competitions = $this->comp->search(null,false,'datum ASC','','','','AND',false,$where);
		$cats = $cups = $ids = $rkey2cat = $GrpId2cat = $id2cup = array();
		foreach($competitions as &$comp)
		{
			$comp = self::rename_key($comp, self::$rename_comp);
			if ($comp['cats'] && ($d = array_diff($comp['cats'], $cats))) $cats = array_merge($cats, $d);
			if ($comp['cup'] && !in_array($comp['cup'],$cups)) $cups[] = $comp['cup'];
			$ids[] = $comp['WetId'];
		}
		//_debug_array($competitions); die('STOP');

		if ($cats && ($cats = $this->cats->search(null,false,'rkey','','','','AND',false,array('rkey' => $cats))))
		{
			foreach($cats as &$cat)
			{
				$cat = self::rename_key($cat, self::$rename_cat);
				$rkey2cat[$cat['rkey']] =& $cat;
				$GrpId2cat[$cat['GrpId']] =& $cat;
			}
			//_debug_array($cats); die('STOP');
		}
		if ($cups && ($cups = $this->cup->search(null,false,'rkey','','','','AND',false,array('SerId' => $cups))))
		{
			foreach($cups as &$cup)
			{
				$cup = self::rename_key($cup, self::$rename_cup);
				$id2cup[$cup['SerId']] =& $cup;
			}
			//_debug_array($cups); die('STOP');
		}
		// query status (existence for start list or result)
		$status = $this->route_result->result_status($ids, $this->result->result_status($ids, $this->registration->registration_status($ids)));
		unset($cat);
		//_debug_array($status); die('Stop');
		foreach($competitions as &$comp)
		{
			// add cat id, name, status and url
			if (isset($comp['cats']) && is_array($comp['cats']) ||
				isset($status[$comp['WetId']]) && is_array($status[$comp['WetId']]))
			{
				foreach($comp['cats'] as $key => &$cat)
				{
					$c = $rkey2cat[$cat];
					if (in_array($c['GrpId'], self::$calendar_exclude))
					{
						unset($comp['cats'][$key]);
						continue;
					}
					$cat = array(
						'GrpId' => $c['GrpId'],
						'name' => $c['name'],
						'status' => $status[$comp['WetId']][$c['GrpId']],
					);
					if (isset($cat['status']))
					{
						$cat['url'] = $this->result_url($comp['WetId'], $c['GrpId']);
						unset($status[$comp['WetId']][$c['GrpId']]);
					}
				}
				$comp['cats'] = array_values($comp['cats']);	// reindex, in case excluded cat was deleted

				// include cats with result, but not mentioned in gruppen column
				foreach((array)$status[$comp['WetId']] as $id => $stat)
				{
					if (in_array($id, self::$calendar_exclude)) continue;
					$c =& $GrpId2cat[$id];
					if (isset($c) || ($c = $this->cats->read($id)) && ($c = self::rename_key($c, self::$rename_cat)))
					{
						$comp['cats'][] = array(
							'GrpId' => $id,
							'name' => $c['name'],
							'status' => $stat,
							'url' => $this->result_url($comp['WetId'], $id),
						);
					}
					unset($c);
				}
			}
			// add cup name
			if ($comp['cup'])
			{
				$comp['cup'] = array(
					'SerId' => $comp['cup'],
					'name'  => $id2cup[$comp['cup']]['name'],
				);
			}
			if (($attachments = $this->comp->attachments($comp, true, true, self::base_url(true))))
			{
				$comp += $attachments;
			}
		}
		$data = array(
			'competitions' => $competitions,
			'cats' => $cats,
			'cups' => $cups,
			'years' => $years,
		);
		$data['expires'] = $year < date('Y') ? self::EXPORT_CALENDAR_OLD_TTL : self::EXPORT_CALENDAR_TTL;
		unset($data['last_modified']);	// to NOT cause etag change by not contained data
		$data['etag'] = md5(serialize($data));

		self::setCache($location, $data, $data['expires']);

		return $data;
	}

	/**
	 * Process a competition filter into an array to pass into select query
	 *
	 * - multiple values are comma-separated
	 * - leading exclemation mark negates expression
	 *
	 * @param array $filter allowed keys are all column-names and alias from self::$rename_comp
	 * @return array
	 */
	private function process_filter(array $filter=null)
	{
		$where = array();
		if ($filter)
		{
			foreach($filter as $name => $val)
			{
				if (($n = array_search($name, self::$rename_comp))) $name = $n;

				if (isset($this->comp->db_cols[$name]))
				{
					if ((string)$val === '' && $this->comp->table_def['fd'][$name]['nullable'] !== false)
					{
						$where[] = $name.' IS NULL';
					}
					elseif ($val[0] == '!' || strpos($val, ',') !== false)
					{
						$not = '';
						if ($val[0] == '!')
						{
							$not = 'NOT ';
							$val = substr($val, 1);
						}
						$val = explode(',', $val);
						if ($this->comp->table_def['fd'][$name]['type'] !== 'varchar')
						{
							$val = array_diff($val, array(''));	// remove empty vales as they would give SQL error
						}
						if (!$val) $val = $this->comp->table_def['fd'][$name]['nullable'] !== false ? null : '';
						$where[] = $this->db->expression($this->comp->table_name, $not, array($name => $val));
					}
					else
					{
						$where[$name] = $val;
					}
				}
			}
		}
		return $where;
	}

	/**
	 * Livetime of cache entries of recent ranking data
	 *
	 * We use a fairly low time, as we cant invalidate the cache easyly.
	 *
	 * @var int
	 */
	const EXPORT_RANKING_TTL = 300;
	/**
	 * Livetime of cache entries of ranking data longer then duration of last-competition in the past
	 *
	 * @var int
	 */
	const EXPORT_RANKING_OLD_TTL = 86400;

	/**
	 * Get location for ranking cache
	 *
	 * @param int|string|array $cat
	 * @param string $date
	 * @param string $cup
	 * @return string
	 */
	protected static function export_ranking_location($cat,$date=null,$cup=null)
	{
		if (empty($date)) $date = '.';

		return 'ranking:'.json_encode(array(
			'cat' => is_array($cat) ? $cat['GrpId'] : $cat,
			'date' => $date,
			'cup' => $cup,
		));
	}

	/**
	 * Export a (cup) ranking
	 *
	 * @param int|string|array $cat id or rkey or category as array
	 * @param string $date =null date of ranking, default today
	 * @param int|string $cup id or rkey of cup to generate a cup-ranking
	 * @param boolean $force_cache =false true use cache even for $ignore_caching_hosts
	 * @return array or athletes
	 */
	public function export_ranking($cat,$date=null,$cup=null,$force_cache=false)
	{
		//error_log(__METHOD__."(cat=".array2string($cat).", '$date', '$cup', $force_cache)");
		if (empty($date)) $date = '.';
		$location = self::export_ranking_location($cat, $date, $cup);
		if (($force_cache || !in_array($_SERVER['HTTP_HOST'], self::$ignore_caching_hosts)) &&
			($data = self::getCache($location)))
		{
			return $data;
		}
		if ($cup && !($cup = $this->cup->read($cup)))
		{
			throw new Exception(lang('Cup not found!!!'));
		}
		if (!is_array($cat) && !($cat = $this->cats->read($cat)))
		{
			throw new Exception(lang('Category NOT found !!!'));
		}
		$overall = count($cat['GrpIds']) > 1;

		$comps = array();
		$start = $comp = $pers = $rls = $ex_aquo = $not_counting = $max_comp = null;
		if (!($ranking = $this->calc->ranking($cat, $date, $start, $comp, $pers, $rls, $ex_aquo, $not_counting, $cup, $comps, $max_comp)))
		{
			throw new Exception(lang('No ranking defined or no results yet for category %1 !!!',$cat['name']));
		}
		$rows = array();
		foreach($ranking as $athlete)
		{
			$data = self::athlete_attributes($athlete, $cat['nation'], $cat['GrpId']) + array(
				'result_rank' => $athlete['platz'],
				'points' => $athlete['pkt'],
			);
			foreach($athlete['results'] as $WetId => $result)
			{
				$data['result'.$WetId] = $result;
			}
			$rows[] = $data;
		}
		// sort competitions by date
		uasort($comps, function($a,$b){return strcmp($a['datum'],$b['datum']);});
		unset($data);
		$route_names = $cats = array();
		foreach($comps as $result_id => &$data)
		{
			if (empty($data['dru_bez']))
			{
				$parts = preg_split('/ ?- ?/', $data['name']);
				list($data['dru_bez']) = explode('/', array_pop($parts));
			}
			if ($overall)
			{
				list(,$GrpId) = explode('_', $result_id);
				if (!isset($cats[$GrpId])) $cats[$GrpId] = $this->cats->read($GrpId);
				$discipline = ' ('.strtoupper($cats[$GrpId]['discipline'][0]).')';
			}
			// HACK: appending a space, to force JSON to keep order given here
			else
			{
				$result_id .= ' ';
			}
			$route_names[$result_id] = $data['dru_bez'].$discipline."\n".
				implode('.', array_reverse(explode('-', $data['datum'])));
		}
		unset($data);
		$data = array(
			'cat' => array(
				'GrpId' => $cat['GrpId'],
				'name' => $cat['name'],
			),
			'cup' => $cup ? array(
				'SerId' => $cup['SerId'],
				'name' => $cup['name'],
			) : null,
			'start' => $start,
			'end' => $date,
			'max_comp' => $max_comp,
			'min_disciplines' => $not_counting['min_disciplines'],
			'drop_equally' => $not_counting['drop_equally'],
			'max_disciplines' => $not_counting['max_disciplines'],
			'nation' => $cat['nation'],
			'participants' => $rows,
			'route_name' => $comp ? $comp['name'].' ('.implode('.', array_reverse(explode('-', $comp['datum']))).')' : '',
			'route_names' => $route_names,
			'route_result' => implode('.', array_reverse(explode('-', $date))),
			'route_order' => -1,
			'discipline' => 'ranking',
			'display_athlete' => ranking_competition::nation2display_athlete($cat['nation']),
		);
		if ($comp)	// comp not set if date given
		{
			$data['comp'] = array(
				'WetId' => $comp['WetId'],
				'name' => $comp['name'],
				'date' => $comp['datum'],
			);
		}
		if ($cup)
		{
			$data['comp_name'] = $cup['name'];
			if ($cup['comment'])
			{
				$data['see_also'][] = array(
					'name' => $cup['comment'],
				);
			}
		}
		else
		{
			$data['comp_name'] = $cat['nation'] ? 'Ranking' : 'World Ranking';
		}
		$data['comp_name'] .= ': '.$cat['name'];

		// calculate expiration date based on date of ranking and duration of last competition
		$date_ts = egw_time::to($date, 'ts');
		//error_log(__METHOD__."() comp=".array2string($comp)." (time()=".time()." - date_ts=$date_ts)/86400 = ".(time()-$date_ts)/86400);
		if (substr($data['end'], -6) == '-12-31' || (time()-$date_ts)/86400 > $comp['duration']+1)	// +1 to compensate for timezones
		{
			$data['expires'] = self::EXPORT_RANKING_OLD_TTL;
			//error_log(__METHOD__."() using old expires time ".$data['expires']);
		}
		else
		{
			$data['expires'] = self::EXPORT_RANKING_TTL;
			//error_log(__METHOD__."() running competition expires time ".$data['expires']);
		}
		// get next competition (not limited to this year (cup is limited by definition))
		if ($data['expires'] == self::EXPORT_RANKING_OLD_TTL &&
			($next_comp = $this->comp->next_comp($comp['datum'],
				$cat['mgroups'] ? $cat['mgroups'] : $cat['rkey'],	// for overall we have to use contained categories
				$comp['nation'], $cup?$cup['SerId']:0, true, false)))
		{
			$next_comp_ts = egw_time::to($next_comp['datum'], 'ts');

			if (time() > $next_comp_ts)	// waiting for next result
			{
				$data['expires'] = self::EXPORT_RANKING_TTL;
				//error_log(__METHOD__."() waiting for next result ".$data['expires']);
			}
			// next result due in less then expires seconds --> set shorter time
			elseif (time()+$data['expires'] > $next_comp_ts)
			{
				$data['expires'] = max($next_comp_ts - time(), self::EXPORT_RANKING_TTL);
			}
		}
		unset($data['last_modified']);	// to NOT cause etag change by not contained data
		$data['etag'] = md5(serialize($data));

		self::setCache($location, $data, $data['expires']);

		return $data;
	}

	/**
	 * Cache and expires time for results from a running competition
	 */
	const EXPORT_RESULTS_RUNNING_EXPIRES = 300;
	/**
	 * Cache and expires time for results from a finished/historic competition
	 */
	const EXPORT_RESULTS_HISTORIC_EXPIRES = 86400;

	/**
	 * Delete results cached from a competition
	 *
	 * Used to invalidate cache of results AND rankings feeds
	 *
	 * @param array $comp
	 * @param int $cat GrpId of result to invalidate
	 * @ToDo invalidate profiles of participants
	 * @ToDo invalidate aggregated rankings (multiple categories!)
	 */
	public static function delete_results_cache(array $comp, $cat=null)
	{
		// results feed is independent of category, because it contains all categories of the competition
		$location = 'results:'.json_encode(array('.', null, null, null));
		self::unsetCache($location);
		//error_log(__METHOD__."(".array2string($comp).") unsetInstance('ranking', '$location')");

		if ($comp['nation'])
		{
			$location = 'results:'.json_encode(array($comp['nation'], null, null, null));
			self::unsetCache($location);
			//error_log(__METHOD__."(".array2string($comp).") unsetInstance('ranking', '$location')");
		}
		if ($comp['fed_id'])
		{
			$location = 'results:'.json_encode(array($comp['nation'], null, null, array('fed_id' => $comp['fed_id'])));
			self::unsetCache($location);
			//error_log(__METHOD__."(".array2string($comp).") unsetInstance('ranking', '$location')");
		}

		// invalidate ranking feeds for current date '.' (also for cup-ranking, if comp belongs to one)
		if ($cat)
		{
			// invalidate general result feeds, as they contain see-also link to ranking
			self::delete_route_cache($comp['WetId'], $cat);

			// current ranking
			self::unsetCache($location=self::export_ranking_location($cat, '.'));
			//error_log(__METHOD__."(".array2string($comp).") unsetInstance('ranking', '$location')");

			if ($comp['serie'])
			{
				// current cup ranking
				self::unsetCache($location=self::export_ranking_location($cat, '.', $comp['serie']));
				//error_log(__METHOD__."(".array2string($comp).") unsetInstance('ranking', '$location')");
			}
			/* ToDo: aggregated ranking is from mulitple categories
			switch($comp['nation'])
			{
				case 'GER':	// sektionen wertung
					'nat_team_ranking';
				case 'SUI':	// regionalzentren wertung
					break;
				default:	// international: national team ranking
					self::unsetCache($location=self::export_aggregated_location('nat_team_ranking', '.', $comp['WetId'], null, $cat));
					//error_log(__METHOD__."(".array2string($comp).") unsetInstance('ranking', '$location')");
					if ($comp['serie'])
					{
						self::unsetCache($location=self::export_aggregated_location('nat_team_ranking', '.', $comp['WetId'], $comp['serie'], $cat));
						//error_log(__METHOD__."(".array2string($comp).") unsetInstance('ranking', '$location')");
					}
			}*/
		}
	}

	/**
	 * Export results from all categories of a competition
	 *
	 * @param string|int $comp WetId or rkey of competition or '.' or 3-char calendar nation for latest result
	 * @param int $num =null how many results to return, default 8 for 2 categories (or less), otherwise 3
	 * @param string $nation =null if set, return all results of that nation
	 * @param array $_filter =null eg. array('fed_id' => 123)
	 * @return array
	 */
	public function export_results($comp, $num=null, $nation=null, array $_filter=null)
	{
		$location = 'results:'.json_encode(func_get_args());
		if (!in_array($_SERVER['HTTP_HOST'], self::$ignore_caching_hosts) &&
			($data = self::getCache($location)))
		{
			return $data;
		}

		if ($comp == '.' || !(int)$comp && strlen($comp) == 3)
		{
			$calendar = $comp == '.' ? null : $comp;
			unset($comp);
		}
		else
		{
			if (!($comp = $this->comp->read($comp)))
			{
				throw new Exception(lang('Competition NOT found !!!'));
			}
			$calendar = $comp['nation'];
		}
		$filter = self::process_filter($_filter);
		$filter['nation'] = $calendar;
		$filter[] = $this->comp->table_name.'.datum <= '.$this->db->quote(time(), 'date');
		// show all competitions of previous year
		$filter[] = $this->comp->table_name.'.datum >= '.$this->db->quote((date('Y')-1).'-01-01');
		$filter[] = 'platz > 0';
		$join = 'JOIN '.$this->result->result_table.' USING(WetId)';

		$comps = array();
		foreach($this->comp->search(null, 'DISTINCT WetId,name,'.$this->comp->table_name.'.datum AS datum,gruppen,nation,quota,rkey,display_athlete', 'datum DESC', '', '', '', 'AND', false, $filter, $join) as $c)
		{
			if (!isset($comp) || $comp['WetId'] == $c['WetId'])
			{
				$comp = $c;
			}
			unset($c['gruppen']);	// not needed/wanted
			unset($c['duration']);
			unset($c['date_end']);
			$comps[] = self::rename_key($c, self::$rename_comp);
		}
		//_debug_array($comps);
		if (!($cats = $this->result->read(array(
			'WetId' => $comp['WetId'],
			'platz > 0',
		))))
		{
			throw new Exception(lang('No result yet !!!'));
		}
		$cats_by_id = array();
		foreach($cats as $cat)
		{
			$cat['url'] = self::result_url($comp['WetId'], $cat['GrpId']);
			$cats_by_id[$cat['GrpId']] = $cat;
		}
		//_debug_array($comp);
		if (!isset($num) || !($num > 0))
		{
			$num = count($cats_by_id) <= 2 ? 8 : 3;
		}
		$result_filter = 'platz <= '.(int)$num;
		if ($nation)
		{
			$result_filter = '('.$result_filter.' OR nation='.$this->db->quote($nation).')';
		}
		$last_modified = null;
		foreach($this->result->read(array(
			'WetId' => $comp['WetId'],
			'GrpId' => array_keys($cats_by_id),
			$result_filter,
		)) as $result)
		{
			$modified = egw_time::createFromFormat(egw_time::DATABASE, $result['modified'], egw_time::$server_timezone);
			if (($ts = $modified->format('U')) > $last_modified) $last_modified = $ts;
			$cats_by_id[$result['GrpId']]['results'][] = array(
				'result_rank' => $result['platz'],
			)+self::athlete_attributes($result, $comp['nation'], $result['GrpId']);
		}
		unset($comp['gruppen']);

		$data = self::rename_key($comp, self::$rename_comp)+array(
			'nation' => $comp['nation'],
			'categorys' => array_values($cats_by_id),
			'competitions' => $comps,
			'last_modified' => $last_modified,
			'display_athlete' => $comp['display_athlete'] ? $comp['display_athlete'] :
				ranking_competition::nation2display_athlete($comp['nation']),
		);
		// add see also links to national team ranking and combined ranking
		if (!$comp['nation'] && $comp['quota'] && (
			(int)$comp['datum'] >= 2005 && preg_match('/^[0-9]{2}_[^I]+/',$comp['rkey']) ||	// world cup from 2005 on
			(int)$comp['datum'] >= 2010 && preg_match('/^[0-9]{2}EY[SC]/',$comp['rkey'])))	// european youth from 2010 on
		{
			// all valid Combinations
			$valid_cats = array(
				'combined (lead &amp; boulder)' => array(1,2,5,6),
				'lead' => array(1,2),
				'boulder' => array(5,6),
				'speed'    => array(23,24),
				'youth lead' => array(15,16,17,18,19,20),
				'youth boulder' => array(79,80,81,82,83,84),
				'youth speed' => array(56,57,58,59,60,61),
			);
			if ((int)$comp['datum'] >= 2008)
			{
				$valid_cats['overall'] = array(1,2,5,6,23,24);
			}
			if ((int)$comp['datum'] >= 2009)	// no more combined ranking from 2009 on, only overall
			{
				unset($valid_cats['combined (lead &amp; boulder)']);
			}

			$cats = array_keys($cats_by_id);
			foreach($valid_cats as $name => $vcats)
			{
				if ((count($icats=array_intersect($cats, $vcats)) == count($vcats) ||
					($name == 'overall' && count($icats) > 2))	// show overall if we have more then 2 cats
					&& ($name != 'overall' || $comp['WetId'] != 991))	// temporary disabling 2009 Word Championship
				{
					$data['see_also'][] = array(
						'url' => '#!type=nat_team_ranking&comp='.$comp['WetId'].'&cat='.implode(',',$vcats),
						'name' => 'National Team Ranking '.strtoupper($name),
					);
				}
			}
			if ((int)$comp['datum'] >= 2008 && !in_array($comp['WetId'], array(991,1439)))	// disabling display of combined ranking for 2009+2012 Word Championship
			{
				/* combined ranking
				$valid_cats = array(
					'MEN' => array(
						'GrpId' => 45,
						'GrpIds' => array(1,6,23),
					),
					'WOMEN' => array(
						'GrpId' => 42,
						'GrpIds' => array(2,5,24),
					),
				);
				foreach($valid_cats as $name => $cat_data)
				{
					if (count(array_intersect($cats, $cat_data['GrpIds'])) > 1)
					{
						$data['see_also'][] = array(
							'url' => $this->ranking_url($cat_data['GrpId']).'&comp='.$comp['WetId'],
							'name' => 'Combined Ranking '.$name,
						);
					}
				}*/
			}
		}

		$comp_ts = egw_time::to($comp['datum'], 'ts');
		$data['expires'] = (time()-$comp_ts)/86400 > $comp['duration']+1 ?	// +1 to compensate for different timezones
			self::EXPORT_RESULTS_HISTORIC_EXPIRES : self::EXPORT_RESULTS_RUNNING_EXPIRES;

		// get next competition (not limited to this year or counting comp / faktor > 0)
		if ($data['expires'] == self::EXPORT_RESULTS_HISTORIC_EXPIRES &&
			($next_comp = $this->comp->next_comp($comp['datum'], null, $comp['nation'], 0, false, false)))
		{
			$next_comp_ts = egw_time::to($next_comp['datum'], 'ts');

			if (time() > $next_comp_ts)	// waiting for next result
			{
				$data['expires'] = self::EXPORT_RESULTS_RUNNING_EXPIRES;
			}
			// next result due in less then expires seconds --> set shorter time
			elseif (time()+$data['expires'] > $next_comp_ts)
			{
				$data['expires'] = max($next_comp_ts - time(), self::EXPORT_RESULTS_RUNNING_EXPIRES);
			}
		}
		unset($data['last_modified']);	// to NOT cause etag change by not contained data
		$data['etag'] = md5(serialize($data));

		self::setCache($location, $data, $data['expires']);

		//_debug_array($ret); exit;
		return $data;
	}

	/**
	 * Export result from ranking (NOT result service) using identical format as _export_route
	 *
	 * @param int $comp
	 * @param int $cat
	 * @return array
	 */
	protected function _export_result($comp, $cat)
	{
		if (!is_array($cat) && !($cat = $this->cats->read($cat)))
		{
			throw new Exception(lang('Category NOT found !!!'));
		}

		if (!($comp = $this->comp->read($comp)))
		{
			throw new Exception(lang('Competition NOT found !!!'));
		}
		if (!($discipline = $comp['discipline']))
		{
			$discipline = $cat['discipline'];
		}
		$modified = egw_time::createFromFormat(egw_time::DATABASE, $comp['modified'], egw_time::$server_timezone);
		$last_modified = $modified->format('U');

		$results = array();
		foreach($this->result->read(array(
			'WetId' => $comp['WetId'],
			'GrpId' => $cat['GrpId'],
			'platz > 0',
		)) as $result)
		{
			$modified = egw_time::createFromFormat(egw_time::DATABASE, $result['modified'], egw_time::$server_timezone);
			if (($ts = $modified->format('U')) > $last_modified) $last_modified = $ts;
			$results[] = array(
				'result_rank' => $result['platz'],
				'result' => $result['pkt'],
			)+self::athlete_attributes($result, $comp['nation'], $result['GrpId']);
		}
		$ret = array(
			'WetId'         => $comp['WetId'],
			'GrpId'         => $cat['GrpId'],
			'route_name'    => $cat['name'],
			'route_result'  => $comp['date_span'],
			'comp_name'     => $comp['name'],
			'nation'        => $comp['nation'],
			'discipline'    => $discipline,
			'participants'  => $results,
			'last_modified' => $last_modified,
			'display_athlete' => $comp['display_athlete'] ? $comp['display_athlete'] :
				ranking_competition::nation2display_athlete($comp['nation']),
		);
		$ret += $this->see_also_result($comp, $cat, 'result');

		// add other categories incl. result-service ones
		$ret['categorys'] = array_values($this->_result_categories($comp, $this->_route_categories($comp)));

		unset($ret['last_modified']);	// to NOT cause etag change by not contained data
		$ret['etag'] = md5(serialize($ret));

		return $ret;
	}

	/**
	 * Get result (from ranking) categories for a given competition
	 *
	 * @param int|array $comp
	 * @param array $categories =array()
	 * @return array GrpId => array with keys GrpId and name
	 */
	protected function _result_categories($comp, $categories=array())
	{
		foreach($this->result->search(array(
			'WetId' => is_array($comp) ? $comp['WetId'] : $comp,
			'platz > 0',
		), 'DISTINCT GrpId') as $route)
		{
			if (!isset($categories[$route['GrpId']]))
			{
				$categories[$route['GrpId']] = array(
					'GrpId' => $route['GrpId'],
				);
			}
		}
		if ($categories)
		{
			foreach($this->cats->names(array('GrpId' => array_keys($categories)), 0) as $id => $name)
			{
				$categories[$id]['name'] = $name;
			}
		}
		return $categories;
	}

	/**
	 * Add see also links to results
	 * - ranking, if faktor > 0
	 * - cup ranking, if cup set
	 *
	 * @param array $comp
	 * @param int $cat
	 * @param string $type =null "result"
	 * @return array with array of links ("name", "url") for key "see_also", empty array otherwise
	 */
	public function see_also_result(array $comp, $cat, $type=null)
	{
		$see_also = array();
		if ((double)$comp['faktor'] > 0)
		{
			$see_also[] = array(
				'name' => 'Ranking'.' '.'after'.' '.$comp['name'],
				'url' => self::ranking_url($cat, null, $comp),
			);
		}
		if ($comp['serie'] && ($cup = $this->cup->read($comp['serie'])))
		{
			$see_also[] = array(
				'name' => $cup['name'].' '.'after'.' '.$comp['name'],
				'url' => self::ranking_url($cat, $cup, $comp),
			);
		}
		// Dt. Landesmeisterschaft --> Landeswertung verlinken
		if ($type !== 'result' && $comp['nation'] == 'GER' &&
			($fed = $this->federation->read($comp['fed_id'])) && $fed['fed_parent'] == 1)
		{
			$see_also[] = array(
				'name' => 'Wertung '.$fed['verband'],
				'url' => self::result_url($comp['WetId'], $cat['GrpId']).'&type=result',
			);
		}
		return $see_also ? array('see_also' => $see_also) : array();
	}

	/**
	 * Cache and expires time for starters / registration
	 */
	const EXPORT_STARTERS_EXPIRES = 900;

	/**
	 * Export starters / registration from all categories of a competition
	 *
	 * @param string|int $comp WetId or rkey of competition
	 * @return array
	 */
	public function export_starters($comp)
	{
		$location = 'starters:'.$comp;
		if (!in_array($_SERVER['HTTP_HOST'], self::$ignore_caching_hosts) && is_numeric($comp) &&
			($data = self::getCache($location)))
		{
			return $data;
		}

		if (!($comp = $this->comp->read($comp)))
		{
			throw new Exception(lang('Competition NOT found !!!'));
		}
		// try again with numeric id
		$location = 'starters:'.$comp['WetId'];
		if (!in_array($_SERVER['HTTP_HOST'], self::$ignore_caching_hosts) &&
			($data = self::getCache($location)))
		{
			return $data;
		}
		//_debug_array($comps);
		$cats = $athletes = $federations = array();
		$last_modified = null;
		foreach($this->registration->read(array(
			'WetId' => $comp['WetId'],
			'GrpId' => -1,
			'state' => ranking_registration::ALL,
		), '', true, ($comp['nation'] ? 'acl_fed_id,fed_parent' : 'nation').',GrpId,reg_id') as $result)
		{
			if (!empty($result['reg_deleted']))
			{
				$modified = egw_time::createFromFormat(egw_time::DATABASE, $result['reg_deleted'], egw_time::$server_timezone);
				if (($ts = $modified->format('U')) > $last_modified) $last_modified = $ts;
				continue;	// use deleted only for timestamp/etag
			}
			if (empty($result['reg_registered'])) continue;	// dont list just prequalified

			$modified = egw_time::createFromFormat(egw_time::DATABASE, $result['reg_registered'], egw_time::$server_timezone);
			if (($ts = $modified->format('U')) > $last_modified) $last_modified = $ts;

			if (!isset($cats[$result['GrpId']]) && ($cat = $this->cats->read($result['GrpId'])))
			{
				$cats[$result['GrpId']] = array(
					'GrpId' => $cat['GrpId'],
					'name'  => $cat['name'],
					'rkey'  => $cat['rkey'],
				);
			}

			$fed_id = !$comp['nation'] ? $result['nation'] :
				($result['acl_fed_id'] ? $result['acl_fed_id'] :
				($result['fed_parent'] ? $result['fed_parent'] : $result['fed_id']));
			$federations[$fed_id] = $fed_id;

			$athletes[] = self::athlete_attributes($result, $comp['nation'], $result['GrpId'])+array(
				'cat' => $result['GrpId'],
				'reg_fed_id' => $fed_id,
				'order' => $result['reg_id'],
			);
		}
		if (!$cats)
		{
			throw new Exception(lang('No starters yet !!!'));
		}

		// we need to sort categories by GrpId (as the athletes) for starters display to work!
		uasort($cats, function($a, $b)
		{
			return $a['GrpId'] - $b['GrpId'];
		});

		unset($comp['gruppen']);

		$data = self::rename_key($comp, self::$rename_comp)+array(
			'nation' => $comp['nation'],
			'categorys' => array_values($cats),
			'athletes' => $athletes,
			'last_modified' => $last_modified,
		);
		if ($comp['nation'])
		{
			$data['federations'] = array_values($this->federation->query_list(array(
				'fed_id' => 'fed_id',
				'shortcut' => 'fed_shortcut',
				'name' => 'verband',
			), 'fed_id', array(
				'fed_id' => array_values($federations)
			), 'verband'));
		}
		$data['expires'] = self::EXPORT_STARTERS_EXPIRES;

		unset($data['last_modified']);	// to NOT cause etag change by not contained data
		$data['etag'] = md5(serialize($data));

		self::setCache($location, $data, $data['expires']);

		//_debug_array($data); exit;
		return $data;
	}

	/**
	 * Rename athlete columns for export
	 *
	 * @var array
	 */
	public static $rename_athlete = array(
		'PerId' => 'PerId',
		'rkey' => 'rkey',
		'nachname' => 'lastname',
		'vorname' => 'firstname',
		'sex' => 'gender',
		'nation' => 'nation',
		'verband' => 'federation',
		'fed_url' => 'fed_url',
		'geb_ort' => 'birthplace',
		'geb_date' => 'birthdate',	// set automatic to just year
		'age' => 'age',
		//'geb_year' => 'birthyear',
		'practice' => 'practice',
		'groesse' => 'height',
		'gewicht' => 'weight',
		'lizenz' => 'license',
		'kader' => 'squad',
		'anrede' => 'title',
		'hobby' => 'hobbies',
		'sport' => 'other_sports',
		'profi' => 'professional',
		'freetext' => 'freetext',
		// commented usually disabled fields, to never export them
		//'email' => 'email',
		'homepage' => 'homepage',
		'facebook' => 'facebook',
		'twitter'  => 'twitter',
		'instagram'=> 'instagram',
		'youtube'  => 'youtube',
		'video_iframe' => 'video_iframe',
		//'strasse' => 'street',
		//'plz' => 'postcode',
		'ort' => 'city',
		//'tel' => 'phone',
		//'fax' => 'fax',
		//'mobile' => 'mobile',
		//'bemerkung' => 'remark',
		'last_comp' => 'last_comp',
		'fed_id' => false,
		'fed_parent' => false,
		'acl_fed_id' => 'acl_fed_id',
		'modified' => 'last_modified',
	);

	/**
	 * Cache and expires time for athlete profiles
	 */
	const EXPORT_PROFILE_EXPIRES = 900;
	/**
	 * Cache and expires time for historic (not in ranking and not last_comp more then one year ago) athlete profiles
	 */
	const EXPORT_PROFILE_HISTORIC_EXPIRES = 86400;
	/**
	 * Time without any profile change or result to consider profile historic
	 */
	const EXPORT_PROFILE_HISTORIC_TIME = 31536000;	// 31536000s=1y

	/**
	 * Home many weights to provide to limit results to this number of competitions
	 */
	const WEIGHT_LIMITS = 20;

	/**
	 * Export starters / registration from all categories of a competition
	 *
	 * @param string|int $athlete numeric id or rkey of athlete
	 * @param string|int $cat =null numeric id or rkey of category, to calculate ranking and cup rankings
	 * @return array
	 */
	public function export_profile($athlete, $cat=null)
	{
		$location = 'profile:'.$athlete.':'.$cat;
		if (!in_array($_SERVER['HTTP_HOST'], self::$ignore_caching_hosts) && is_numeric($athlete) &&
			($data = self::getCache($location)))
		{
			return $data;
		}
		if (!($athlete = $this->athlete->read($athlete, array('fed_url'))))
		{
			throw new Exception(lang('Athlete NOT found !!!'));
		}
		//_debug_array($athlete); exit;

		$data = self::rename_key($athlete, self::$rename_athlete, true);
		// athlete requested not to show his profile
		// --> no results, no ranking, regular profile data already got removed by athlete->db2data called by read
		if ($athlete['acl'] & 128)
		{
			$data['freetext'] = $data['error'] = lang('Sorry, the climber requested not to show his profile!');
			$data['last_modified'] = egw_time::to($athlete['modified'], 'ts');
		}
		else
		{
			foreach(array(
				'photo' => null,
				'photo2' => 2,
			) as $pic => $postfix)
			{
				$data[$pic] = $this->athlete->picture_url(null, $postfix);
				if ($data[$pic][0] == '/' || parse_url($data[$pic], PHP_URL_HOST) == $_SERVER['HTTP_HOST'])
				{
					$data[$pic] = self::base_url(true).($data[$pic][0] == '/' ? $data[$pic] :
						preg_replace('|^https?://[^/]+|', '', $data[$pic]));
				}
			}
			$last_modified = egw_time::to($athlete['modified'], 'ts');
			// fetch results and calculate their weight, to allow clients to easyly show N best competitions
			if (($results = $this->result->read(array(
				'PerId' => $athlete['PerId'],
				'platz > 0',
			))))
			{
				$year = (int)date('Y');
				foreach($results as $result)
				{
					if ($last_modified < ($ts = egw_time::to($result['modified'], 'ts')))
					{
						$last_modified = $ts;
					}
					$data['results'][] = array(
						'rank' => $result['platz'],
						'date' => $result['datum'],
						'name' => $result['name'],
						'url' => self::result_url($result['WetId'], $result['GrpId']),
						'nation' => $result['nation'],
						'WetId' => $result['WetId'],
						'cat_name' => $result['cat_name'],
						'GrpId' => $result['GrpId'],
					);
					$data['categorys'][$result['GrpId']] = array(
						'GrpId' => (int)$result['GrpId'],
						'name' => $result['cat_name'],
					);
					if (empty($cat)) $cat = $result['GrpId'];
				}
				$data['categorys'] = array_values($data['categorys']);
			}

			unset($data['last_modified']);
			$data['last_modified'] = $last_modified;

			// if category given fetch ranking
			if ($cat && ($cat = $this->cats->read($cat)))
			{
				$data['GrpId'] = $cat['GrpId'];
				$data['cat_name'] = $cat['name'];
				list($year, $month) = explode('-', date('Y-m'));
				if ($month <= 3) $year--;

				foreach(array(
					'ranking' => '.',
					'cup' => $month <= 3 ? $year.'-12-31' : '.',
					'cup-1' => ($year-1).'-12-31',
					'cup-2' => ($year-2).'-12-31',
				) as $name => $date)
				{
					if ($name != 'ranking')
					{
						if (empty($cat['serien_pat'])) break;	// no cup defined
						$cup = str_replace('??', sprintf("%02d", (int)($date=='.'?$year:$date) % 100), $cat['serien_pat']);
					}
					if (!$cup || ($cup = $this->cup->read($cup)))
					{
						try {
							$ranking = $this->export_ranking($cat, $date, $cup['SerId'], true);
							foreach($ranking['participants'] as $participant)
							{
								if ($participant['PerId'] == $athlete['PerId']) break;
							}
							$data['rankings'][] = array(
								'rank' => $participant['PerId'] == $athlete['PerId'] ? $participant['result_rank'] : '',
								'name' => $cup ? $cup['name'] : ($cat['nation'] ? lang('Ranking') : 'Worldranking'),
								'SerId' => $cup['SerId'],
								'url' => self::ranking_url($cat['GrpId'], $cup ? $cup['SerId'] : null),
								'date' => $ranking['end'],
								'points' => $participant['PerId'] == $athlete['PerId'] ? $participant['points'] : '',
							);
						}
						catch(Exception $e) {
							// ignore not existing rankings
							unset($e);
						}
					}

				}
			}
		}
		// historic profiles: athlete not in ranking and last modification more then 1 year ago
		$data['expires'] = !$data['rankings'][0]['rank'] && time()-$data['last_modified'] > 365*86400 ?
			self::EXPORT_PROFILE_HISTORIC_EXPIRES : self::EXPORT_PROFILE_EXPIRES;

		unset($data['last_modified']);	// to NOT cause etag change by not contained data
		$data['etag'] = md5(serialize($data));

		self::setCache($location, $data, $data['expires']);
		// if cached without category, also cache with default category
		if (substr($location, -1) == ':' && $cat)
		{
			self::setCache($location.$cat['GrpId'], $data, $data['expires']);
		}

		//_debug_array($data); exit;
		return $data;
	}

	/**
	 * Search athlettes by name for autocomplete
	 *
	 * @param string $pattern
	 * @return array of array with keys "value" and "label"
	 */
	public function export_athlete_search($pattern)
	{
		$found = array();
		foreach($this->athlete->search(array(
				'nachname' => $pattern.'*',
				'vorname'  => $pattern.'*',
			),
			array('PerId', 'vorname', 'nachname', ranking_athlete::FEDERATIONS_TABLE.'.nation'),
			'nachname, vorname', '', '', false, 'OR', array(0, 50)) as $row)
		{
			$found[] = array(
				'value' => (int)$row['PerId'],
				'label' => mb_strtoupper($row['nachname']).' '.$row['vorname'].' ('.$row['nation'].')',
			);
		}
		return $found;
	}

	/**
	 * Location for caching of an aggregated ranking: national team ranking, sektionen wertung, ...
	 *
	 * @param string $type 'nat_team_ranking', 'sektionenwertung', 'regionalzentren'
	 * @param string $date ='.'
	 * @param int $comp =null
	 * @param int $cup =null
	 * @param int|string $cat =null (multiple comma-separated) cat id(s)
	 * @param array &$filter=null on return filter
	 * @throws Exception
	 * @return string
	 */
	public static function export_aggregated_location($type, $date='.', $comp=null, $cup=null, $cat=null, &$filter=null)
	{
		if (empty($date)) $date = '.';

		$filter = array();
		if ($comp) $filter['WetId'] = (int)$comp;
		if ($cup) $filter['SerId'] = (int)$cup;
		if ($cat) $filter['GrpId'] = array_map('intval', explode(',', $cat));

		$location = 'aggregated:'.json_encode($filter+array('type' => $type, 'date' => $date));
		//error_log(__METHOD__."(".array2string(func_get_args()).") returning '$location'");
		return $location;
	}

	/**
	 * Export an aggregated ranking: national team ranking, sektionen wertung, ...
	 *
	 * @param string $type 'nat_team_ranking', 'sektionenwertung', 'regionalzentren'
	 * @param string $date ='.'
	 * @param int $comp =null
	 * @param int $cup =null
	 * @param int|string $cat =null (multiple comma-separated) cat id(s)
	 * @throws Exception
	 * @return array
	 */
	public function export_aggregated($type, $date='.', $comp=null, $cup=null, $cat=null)
	{
		if (empty($date)) $date = '.';

		// normalize rkey for cup to numeric cup-id
		if ($cup && !is_numeric($cup) && ($c = $this->cup->read($cup)))
		{
			$cup = $c['SerId'];
		}
		$filter = null;
		$location = self::export_aggregated_location($type, $date, $comp, $cup, $cat, $filter);
		if (!in_array($_SERVER['HTTP_HOST'], self::$ignore_caching_hosts) &&
			($data = self::getCache($location)))
		{
			return $data;
		}

		switch($type)
		{
			case 'nat_team_ranking':
				// only defined for a given comp or cup!
				if (!$filter['WetId'] && !$filter['SerId'])
				{
					throw new Exception ("National team ranking only defined for competitions or cups!");
				}
				$date_comp = null;
				$feds = $this->calc->nat_team_ranking($comp, $cat, $cup, $date, $date_comp);
				$name = 'National Team Ranking';
				$aggregated_name = 'Nation';
				break;
			case 'sektionenwertung':
				$filter['nation'] = 'GER';
				$by = 'fed_id';
				$best_results = isset($filter['GrpId']) ? 2 : 1;
				$use_cup_points = false;
				$name = isset($filter['GrpId']) || isset($filter['WetId']) ? 'Sektionenwertung' : 'Sektionenrangliste';
				$aggregated_name = 'DAV Sektion';
				break;
			case 'regionalzentren':
				$filter['nation'] = 'SUI';
				$filter['GrpId'] = array(34,35,37,38,46,47,68,69);	// only U12-18
				$by = 'acl_fed_id';
				$best_results = 2;
				$use_cup_points = true;
				$name = 'Regionalzentrumswertung';
				$aggregated_name = 'Regionalzentrum';
				break;
			default:
				throw new Exception("Unknown type '$type'!");
		}
		if (!isset($feds))
		{
			$feds = $this->calc->aggregated($date, $filter, $best_results, $by, $use_cup_points, null, null, $date_comp);
		}
		// extract params, competititons and categorys
		$params = $feds['params'];
		unset($feds['params']);
		$filter_used = $params['filter'];
		unset($params['filter']);
		$competitions = $feds['competitions'];
		unset($feds['competitions']);
		$categorys = $feds['categorys'];
		unset($feds['categorys']);

		foreach($feds as &$fed)
		{
			foreach($fed['counting'] as &$res)
			{
				$res = self::rename_key($res, array(
					'platz'    => 'rank',
					'pkt'      => 'points',
					'vorname'  => 'firstname',
					'nachname' => 'lastname',
				));
			}
		}
		$data = $params+array(
			'nation' => $filter['nation'],
			'comp_filter' => $comp,
			'comp_name' => $date_comp['name'],
			'comp_date' => $date_comp['datum'],
			'comp_date_span' => $date_comp['date_span'],
			'cat_filter' => implode(',', (array)$filter_used['GrpId']),
			'cup_filter' => $cup,
			'name' => $name,
			'aggregated_name' => $aggregated_name,
			'federations' => $feds,
			'competitions' => $competitions,
			'categorys' => $categorys,
		);
		// calculate expiration date based on date of ranking and duration of last competition
		$date_ts = egw_time::to($data['end'], 'ts');
		//error_log(__METHOD__."() comp=".array2string($date_comp)." (time()=".time()." - date_ts=$date_ts)/86400 = ".(time()-$date_ts)/86400);
		if (substr($data['end'], -6) == '-12-31' || (time()-$date_ts)/86400 > $date_comp['duration'])
		{
			$data['expires'] = self::EXPORT_RANKING_OLD_TTL;
			//error_log(__METHOD__."() using old expires time ".$data['expires']);
		}
		else
		{
			$data['expires'] = self::EXPORT_RANKING_TTL;
			//error_log(__METHOD__."() running competition expires time ".$data['expires']);
		}
		// get next competition (for cup only)
		if ($data['expires'] == self::EXPORT_RANKING_OLD_TTL && $cup &&
			($next_comp = $this->comp->next_comp($date_comp['datum'], $cat['rkey'], $date_comp['nation'], $cup['SerId'], true, false)))
		{
			$next_comp_ts = egw_time::to($next_comp['datum'], 'ts');

			if (time() > $next_comp_ts)	// waiting for next result
			{
				$data['expires'] = self::EXPORT_RANKING_TTL;
				//error_log(__METHOD__."() waiting for next result ".$data['expires']);
			}
			// next result due in less then expires seconds --> set shorter time
			elseif (time()+$data['expires'] > $next_comp_ts)
			{
				$data['expires'] = max($next_comp_ts - time(), self::EXPORT_RANKING_TTL);
			}
		}
		unset($data['last_modified']);	// to NOT cause etag change by not contained data
		$data['etag'] = md5(serialize($data));
		//_debug_array($data); exit;
		self::setCache($location, $data);

		return $data;
	}

	/**
	 * Rename array keys or remove value if no new key given
	 *
	 * @param array $arr
	 * @param array $rename array with from => to or from => false pairs
	 * @param boolean $filter =false true return only fields given in $rename, false return whole $arr
	 * @return array
	 */
	public static function rename_key(array $arr, array $rename, $filter=false)
	{
		$ret = $filter ? array() : $arr;
		foreach($rename as $from => $to)
		{
			if ($to && isset($arr[$from])) $ret[$to] =& $arr[$from];
			if ($from !== $to) unset($ret[$from]);
		}
		return $ret;
	}

	/**
	 * Set athlete specific attributes taking into account calendar nation
	 *
	 * @param array $athlete
	 * @param string $nation
	 * @param int $cat GrpId for profile url
	 * @return array
	 */
	protected static function athlete_attributes($athlete, $nation, $cat)
	{
		$data = array(
			'PerId' => $athlete['PerId'],
			'firstname' => $athlete['vorname'],
			'lastname' => $athlete['nachname'],
			'birthyear' => substr($athlete['geb_date'], 0, 4),
			'nation' => $athlete['nation'],
			'federation' => $athlete['verband'],
			'city' => $athlete['ort'],
			'fed_url' => $athlete['fed_url'],
			'url' => self::profile_url($athlete, $cat),
		);
		switch ($nation)
		{
			case 'GER':
				$data['federation'] = preg_replace("/^(DAV|Sektion|Deutscher|Dt|Alpenverein|[:., ])*/i", '', $athlete['verband']);
				break;

			case 'SUI':
				$data['federation'] = preg_replace('/^Schweizer Alpen[ -]{1}Club /', '', $athlete['verband']);
				if ($athlete['acl_fed_id'])
				{
					static $rgzs=null;
					if (!isset($rgzs))
					{
						$rgzs = ranking_bo::getInstance()->athlete->federations('SUI');
					}
					$data['rgz'] = preg_replace('/^SAC-Regionalzentrum /', '', $rgzs[$athlete['acl_fed_id']]);
				}
				break;
		}
		return $data;
	}
}
