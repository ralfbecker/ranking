<?php
/**
 * eGroupWare digital ROCK Rankings - route storage object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2007-10 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$
 */

require_once(EGW_INCLUDE_ROOT . '/ranking/inc/class.route_result.inc.php');

/**
 * route object
 */
class route extends so_sql
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

		$this->charset = is_object($GLOBALS['egw']->translation) ? $GLOBALS['egw']->translation->charset() : 'iso-8859-1';
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
		$this->db->select($this->table_name,'MAX(route_order)',array(
			'WetId' => $comp,
			'GrpId' => $cat,
		),__LINE__,__FILE__);

		return $this->db->next_record() ? $this->db->f(0) : null;
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
			/* dont know why this dont work: delete is in the query log, but it get's not executed, rows_effected=0
			if (!is_object($GLOBALS['egw']->route_result))
			{
				$GLOBALS['egw']->route_result = new route_result($this->source_charset,$this->db);
			}
			$GLOBALS['egw']->route_result->delete($keys);
			*/
			$this->db->delete('RouteResults',$keys,__LINE__,__FILE__);
			$this->db->delete('RelayResults',$keys,__LINE__,__FILE__);

			boresult::delete_export_route_cache($keys);
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
	 * @param boolean $split_two_quali_all=false should the diverse TWO_QUALI_ALL* types be read as they are or all as TWO_QUALI_ALL
	 * @return array/boolean data if row could be retrived else False
	 */
	function read($keys,$split_two_quali_all=false)
	{
		if (is_array($keys) && isset($keys['route_order']) && !is_null($keys['route_order']))
		{
			$keys['route_order'] = (string) $keys['route_order'];
		}
		$ret = parent::read($keys);

		if (!$ret && $keys['route_order'] == -1)		// general result not found --> return a default one
		{
			$keys['route_order'] = 0;
			if (($ret = parent::read($keys,$extra_cols,$join)))
			{
				$ret = $this->init(array(
					'WetId' => $ret['WetId'],
					'GrpId' => $ret['GrpId'],
					'route_order' => -1,
					'route_type'  => $ret['route_type'],
					'route_name'  => lang('General result'),
					'route_status'=> STATUS_STARTLIST,
				));
			}
		}
		if (!$split_two_quali_all && in_array($ret['route_type'],array(TWO_QUALI_ALL,TWO_QUALI_ALL_NO_STAGGER,TWO_QUALI_ALL_SEED_STAGGER)))
		{
			$ret['route_type'] = TWO_QUALI_ALL;
		}
		return $ret;
	}

	/**
	 * saves the content of data to the db
	 *
	 * Reimplemented to update modified, modifier and reset current_*, next_* if result offical.
	 *
	 * @param array $keys=null if given $keys are copied to data before saveing => allows a save as
	 * @param string|array $extra_where=null extra where clause, eg. to check an etag, returns true if no affected rows!
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
		if (isset($this->data['route_judges']) && is_array($this->data['route_judges']))
		{
			$this->data['route_judges'] = implode(',',$this->data['route_judges']);
		}
		$this->data['route_modified'] = time();
		$this->data['route_modifier'] = $GLOBALS['egw_info']['user']['account_id'];

		boresult::delete_export_route_cache($this->data);

		return parent::save(null,$extra_where);
	}

	/**
	 * Update only the given fields, if the primary key is not given, it will be taken from $this->data
	 *
	 * Reimplemented to delete the export-route cache
	 *
	 * @param array $fields
	 * @param boolean $merge=true if true $fields will be merged with $this->data (after update!), otherwise $this->data will be just $fields
	 * @return int|boolean 0 on success, or errno != 0 on error, or true if $extra_where is given and no rows affected
	 */
	function update($fields,$merge=true)
	{
		boresult::delete_export_route_cache($merge ? array_merge($this->data,fields) : $fields);

		return parent::update($fields,$merge);
	}
}