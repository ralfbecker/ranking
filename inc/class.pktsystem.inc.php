<?php
/**
 * eGroupWare digital ROCK Rankings - pktsystem storage object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2006 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$ 
 */

require_once(EGW_INCLUDE_ROOT . '/etemplate/inc/class.so_sql.inc.php');

/**
 * pktsystem object
 *
 */
class pktsystem extends so_sql
{
	/* var $public_functions = array(
		'init'	=> True,
		'read'	=> True,
		'save'	=> True,
		'delete'	=> True,
		'search'	=> True,
	) */	// set in so_sql
/* set by so_sql('ranking','rang.PktSysteme'):
	var $table_name = 'rang.PktSysteme';
	var $autoinc_id = 'PktId';
	var $db_key_cols = array('PktId' => 'PktId');
	var $db_data_cols = array(
		'rkey' => 'rkey', 'name' => 'name', 'anz_pkt' => 'anz_pkt'
	);
*/
/* not needed so far
	var $db_name_pkte = 'rang.PktSystemPkte';
	var $db_data_cols_pkte = array(
		'platz' => 'platz','pkt' => 'pkt'
	);
	var $pkte;
*/
	var $pkte_table = 'PktSystemPkte';

	/**
	 * pktsystem of the competition class
	 *
	 */
	function pktsystem($source_charset='',$db=null)
	{
		$this->so_sql('ranking','PktSysteme',$db);	// call constructor of derived class

/*    not needed so far
		$this->pkte =& new so_sql;
		$this->pkte->db_name = $this->db_name_pkte;
		$this->pkte->db_key_cols = $this->db_key_cols;
		$this->pkte->db_data_cols = $this->db_data_cols_pkte;
		$this->pkte->so_sql(); // call constructor again manually after setting up fields
*/
	}

	/**
	 * array with all PktSystems of form PktId => name
	 */
	function names()
	{
		$all = $this->search(array(),False,'rkey');

		if (!$all)
			return array();

		$arr = array();
		while (list($key,$data) = each($all))
		{
			$arr[$data['PktId']] = $data['rkey'].': '.$data['name'];
		}
		return $arr;
	}
	
	/**
	 * Get points per place of a given point-system
	 *
	 * @param int/string $PktId PktId or rkey
	 * @param array &$pkte on return array with points indexed by place
	 * @return double sum of all points
	 */
	function get_pkte ($PktId,&$pkte) 
	{
		if (!is_numeric($PktId) && $this->read(array('rkey'=>$PktId)))
		{
			$PktId = $this->data['PktId'];
		}
		$this->db->select($this->pkte_table,'platz,pkt',array('PktId'=>$PktId),__LINE__,__FILE__,false,'ORDER BY platz');
		while (($row = $this->db->row(true)))
		{
			$max_pkte += ($pkte[$row['platz']] = $row['pkt']);
		}
		//echo "<p>pktsystem::get_pkte($PktId,) = $max_pkte</p>\n";
		return $max_pkte;
	}
};