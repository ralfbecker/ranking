<?php
/**
 * eGroupWare digital ROCK Rankings - route storage object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2007 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$ 
 */

require_once(EGW_INCLUDE_ROOT . '/etemplate/inc/class.so_sql.inc.php');
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
	function route($source_charset='',$db=null)
	{
		//$this->debug = 1;
		$this->so_sql('ranking','Routes',$db);	// call constructor of extended class
		
		if ($source_charset) $this->source_charset = $source_charset;
		
		$this->charset = is_object($GLOBALS['egw']->translation) ? $GLOBALS['egw']->translation->charset() : 'iso-8859-1';
/*
		foreach(array(
				'athlete'  => 'athlete',
			) as $var => $class)
		{
			$egw_name = $class;
			if (!is_object($GLOBALS['egw']->$egw_name))
			{
				$GLOBALS['egw']->$egw_name =& CreateObject('ranking.'.$class,$source_charset,$this->db,$vfs_pdf_dir);
			}
			$this->$var =& $GLOBALS['egw']->$egw_name;
		}
*/
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

		if (($ret = parent::delete($keys)))
		{
			if (!is_object($GLOBALS['egw']->route_result))
			{
				$GLOBALS['egw']->route_result = new route_result($this->source_charset,$this->db);
			}
			$GLOBALS['egw']->route_result->delete($keys);
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
}