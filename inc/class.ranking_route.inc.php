<?php
/**
 * eGroupWare digital ROCK Rankings - route storage object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2007-17 by Ralf Becker <RalfBecker@digitalrock.de>
 */

use EGroupware\Api;

/**
 * route object
 */
class ranking_route extends Api\Storage\Base
{
	var $charset,$source_charset;

	/**
	 * constructor of the route class
	 */
	function __construct($source_charset='',$db=null)
	{
		//$this->debug = 1;
		parent::__construct('ranking','Routes',$db);	// call constructor of extended class

		if ($source_charset) $this->source_charset = $source_charset;

		$this->charset = Api\Translation::charset();
	}

	/**
	 * changes the data from the db-format to your work-format
	 *
	 * @param array $data =null if given works on that array and returns result, else works on internal data-array
	 * @return array
	 */
	function db2data($data=null)
	{
		if (($intern = !is_array($data)))
		{
			$data = &$this->data;
		}

		if (isset($data['route_judges']))
		{
			$data['route_judges'] = $data['route_judges'][0] === '[' ?
				json_decode($data['route_judges'], true) :	// new json-encoded format
				[explode(',', $data['route_judges'])];	// old comma-separated format
		}

		return parent::db2data($intern ? null : $data);
	}

	/**
	 * changes the data from your work-format to the db-format
	 *
	 * @param array $data =null if given works on that array and returns result, else works on internal data-array
	 * @return array
	 */
	function data2db($data=null)
	{
		if (($intern = !is_array($data)))
		{
			$data = &$this->data;
		}

		if (isset($data['route_judges']))
		{
			$data['route_judges'] = json_encode(array_diff($data['route_judges'], [null]));
		}
		return parent::data2db($intern ? null : $data);
	}

	/**
	 * Determine the highest existing route_order for $comp and $cat
	 *
	 * @param int $comp WetId
	 * @param int $cat GrpId
	 * @return int route_order or null
	 */
	function get_max_order($comp,$cat)
	{
		$max = $this->db->select($this->table_name,'MAX(route_order)',array(
			'WetId' => $comp,
			'GrpId' => $cat,
		),__LINE__,__FILE__)->fetchColumn();

		return $max !== false ? $max : null;
	}

	/**
	 * deletes row representing keys in internal data or the supplied $keys if != null
	 *
	 * @param array $keys if given array with col => value pairs to characterise the rows to delete
	 * @return int affected rows, should be 1 if ok, 0 if an error
	 */
	function delete($keys=null)
	{
		if (is_null($keys)) $keys = array_intersect_key($this->data,array('WetId'=>0,'GrpId'=>0,'route_order'=>0));

		// when deleting 1. quali, delete everything (specially general result!)
		if (!$keys['route_order']) unset($keys['route_order']);

		if (($ret = parent::delete($keys)))
		{
			$this->db->delete('RouteResults',$keys,__LINE__,__FILE__);
			$this->db->delete('RelayResults',$keys,__LINE__,__FILE__);

			ranking_result_bo::delete_export_route_cache($keys, null, null, true);
		}
		return $ret;
	}

	/**
	 * reads row matched by key and puts all cols in the data array
	 *
	 * Reimplemented to change the type of $keys['route_order'] to string, as it can be (int)0 and so_sql ignores (int)0.
	 * Reimplemented to return a default general result, if it does not exist.
	 *
	 * @param array $keys array with keys in form internalName => value, may be a scalar value if only one key
	 * @param boolean $split_two_quali_all =false should the diverse TWO_QUALI_ALL* types be read as they are or all as TWO_QUALI_ALL
	 * @return array/boolean data if row could be retrived else False
	 */
	function read($keys,$split_two_quali_all=false)
	{
		if (is_array($keys) && isset($keys['route_order']) && !is_null($keys['route_order']))
		{
			$keys['route_order'] = (string) $keys['route_order'];
		}
		$ret = parent::read($keys);

		if (!$ret && $keys['route_order'] < 0)		// general result not found --> return a default one
		{
			$route_order = $keys['route_order'];
			$keys['route_order'] = 0;
			if (($ret = parent::read($keys)))
			{
				$ret = $this->init(array(
					'WetId' => $ret['WetId'],
					'GrpId' => $ret['GrpId'],
					'route_order' => $route_order,
					'route_type'  => $ret['route_type'],
					'route_name'  => self::default_name($route_order),
					'route_status'=> STATUS_STARTLIST,
					'discipline'  => $ret['discipline'],
					'route_quota' => ranking_result_bo::default_quota($ret['discipline'], $route_order, $ret['route_type']),
				));
			}
		}
		if (!$split_two_quali_all && in_array($ret['route_type'],array(TWO_QUALI_ALL,TWO_QUALI_ALL_NO_STAGGER,TWO_QUALI_ALL_SEED_STAGGER)))
		{
			$ret['route_type'] = TWO_QUALI_ALL;
		}
		return $ret;
	}

	static function default_name($route_order)
	{
		switch($route_order)
		{
			case -3:
				$name = lang('Qualification').' '.lang('overall result');
				break;
			case -4:
				$name = lang('Group').' A '.lang('Qualification');
				break;
			case -5:
				$name = lang('Group').' B '.lang('Qualification');
				break;
			case -6:
				$name = lang('Final').' '.lang('Speed').' '.lang('overall result');
				break;
			default:
				$name = lang('General result');
				break;
		}
		return $name;
	}

	/**
	 * saves the content of data to the db
	 *
	 * Reimplemented to update modified, modifier and reset current_*, next_* if result offical.
	 *
	 * @param array $keys =null if given $keys are copied to data before saveing => allows a save as
	 * @param string|array $extra_where =null extra where clause, eg. to check an etag, returns true if no affected rows!
	 * @return int|boolean 0 on success, or errno != 0 on error, or true if $extra_where is given and no rows affected
	 */
	function save($keys=null,$extra_where=null)
	{
		if (is_array($keys) && count($keys)) $this->data_merge($keys);

		// unset current and next id's
		if ($this->data['route_status'] == STATUS_RESULT_OFFICIAL)
		{
			for($i = 1; $i <= 8; ++$i)
			{
				$this->data['current_'.$i] = $this->data['next_'.$i] = null;
			}
			$this->data['boulder_started'] = null;
		}
		$this->data['route_modified'] = time();
		$this->data['route_modifier'] = $GLOBALS['egw_info']['user']['account_id'];

		$err = parent::save(null,$extra_where);

		// if route-type changed in qualification change it in other heats too
		if (!$err && !$this->data['route_order'] && $this->db->select($this->table_name, 'COUNT(*)', array(
			'WetId' => $this->data['WetId'],
			'GrpId' => $this->data['GrpId'],
			'route_type != '.(int)$this->data['route_type'],
		), __LINE__, __FILE__)->fetchColumn())
		{
			$this->db->update($this->table_name, array(
				'route_type' => $this->data['route_type'],
			), array(
				'WetId' => $this->data['WetId'],
				'GrpId' => $this->data['GrpId'],
			), __LINE__, __FILE__);
		}
		ranking_result_bo::delete_export_route_cache($this->data, null, null, true);	// true = invalidate prev. heats

		return $err;
	}

	/**
	 * Update only the given fields, if the primary key is not given, it will be taken from $this->data
	 *
	 * Reimplemented to delete the export-route cache
	 *
	 * @param array $fields
	 * @param boolean $merge =true if true $fields will be merged with $this->data (after update!), otherwise $this->data will be just $fields
	 * @return int|boolean 0 on success, or errno != 0 on error, or true if $extra_where is given and no rows affected
	 */
	function update($fields,$merge=true)
	{
		ranking_result_bo::delete_export_route_cache($merge ? array_merge($this->data, $fields) : $fields);

		return parent::update($fields,$merge);
	}
}