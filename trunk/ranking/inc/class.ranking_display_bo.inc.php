<?php
/**
 * eGroupWare digital ROCK Rankings - display business object/logic
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2007 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$ 
 */

require_once(EGW_INCLUDE_ROOT.'/ranking/inc/class.ranking_display.inc.php');
require_once(EGW_INCLUDE_ROOT.'/ranking/inc/class.ranking_display_format.inc.php');
require_once(EGW_INCLUDE_ROOT.'/ranking/inc/class.boresult.inc.php');

class ranking_display_bo
{
	/**
	 * Instance of boresults
	 *
	 * @var boresult
	 */
	var $result;
	/**
	 * Instance of the display object
	 *
	 * @var ranking_display
	 */
	var $display;
	/**
	 * Instance of the format object
	 *
	 * @var ranking_display_format
	 */
	var $format;

	/**
	 * Constructor of the bo display class
	 *
	 * @return ranking_display_bo
	 */
	function ranking_display_bo()
	{
		if (!is_object($GLOBALS['boresult']))
		{
			$GLOBALS['boresult'] =& new boresult();
		}
		$this->result =& $GLOBALS['boresult'];
		
		$this->display = new ranking_display($this->result->db);			// initialising the display class
		$this->format = new ranking_display_format($this->result->db);		// initialising the display format class
	}
	
	/**
	 * Returns the "heats" Category - Heat pairs as array(cat_id:route_order => label)
	 *
	 * @param int $WetId
	 * @return array
	 */
	function get_heats($WetId)
	{
		static $cats;

		if (!($rows = $this->result->route->search(array('WetId' => $WetId),'GrpId,route_order,route_name','route_order>=0,route_order DESC,GrpId')))
		{
			return array();
		}
		$heats = array();
		foreach($rows as $row)
		{
			if (is_null($cats))
			{
				$cats = $this->result->cats->query_list('name','GrpId');
			}
			$heats[$row['GrpId'].':'.$row['route_order']] = $row['route_name'].' '.$cats[$row['GrpId']];
		}
		return $heats;
	}
}