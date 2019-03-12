<?php
/**
 * EGroupware digital ROCK Rankings - pktsystem storage object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2006-19 by Ralf Becker <RalfBecker@digitalrock.de>
 */

use EGroupware\Api;

/**
 * pktsystem object
 *
 */
class ranking_pktsystem extends Api\Storage\Base
{
	var $pkte_table = 'PktSystemPkte';

	/**
	 * pktsystem of the competition class
	 *
	 */
	function __construct($source_charset='',$db=null)
	{
		unset($source_charset);	// not used, but required by function signature

		parent::__construct('ranking','PktSysteme',$db);	// call constructor of derived class
	}

	/**
	 * array with all PktSystems of form PktId => name
	 */
	function names()
	{
		$all = $this->search(array(),False,'rkey');

		if (!$all)
		{
			return array();
		}
		$arr = array();
		foreach($all as $data)
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
		foreach($this->db->select($this->pkte_table,'platz,pkt',array('PktId'=>$PktId),__LINE__,__FILE__,false,'ORDER BY platz') as $row)
		{
			$max_pkte += ($pkte[$row['platz']] = $row['pkt']);
		}
		//echo "<p>pktsystem::get_pkte($PktId,) = $max_pkte</p>\n";
		return $max_pkte;
	}
}