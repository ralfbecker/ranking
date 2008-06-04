<?php
/**
 * eGroupWare digital ROCK Rankings - storage object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2006-8 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$
 */

class soranking
{
	/**
	 * configuration
	 *
	 * @var array
	 */
	var $config=array();
	/**
	 * db-object with connection to ranking database, might be different from eGW database
	 *
	 * @var egw_db
	 */
	var $db;
	/**
	 * @var pktsystem
	 */
	var $pkte;
	/**
	 * @var rls_system
	 */
	var $rls;
	/**
	 * @var category
	 */
	var $cats;
	/**
	 * @var cup
	 */
	var $cup;
	/**
	 * @var competition
	 */
	var $comp;
	/**
	 * @var athlete
	 */
	var $athlete;
	/**
	 * @var result
	 */
	var $result;
	/**
	 * @var route
	 */
	var $route;

	/**
	 * Constructor
	 */
	function soranking($extra_classes=array())
	{
		$c =& CreateObject('phpgwapi.config','ranking');
		$c->read_repository();
		$this->config = $c->config_data;
		unset($c);

		if ($this->config['ranking_db_host'] || $this->config['ranking_db_name'])
		{
			foreach(array('host','port','name','user','pass') as $var)
			{
				if (!$this->config['ranking_db_'.$var]) $this->config['ranking_db_'.$var] = $GLOBALS['egw_info']['server']['db_'.$var];
			}
			$this->db =& new egw_db();
			$this->db->connect($this->config['ranking_db_name'],$this->config['ranking_db_host'],
				$this->config['ranking_db_port'],$this->config['ranking_db_user'],$this->config['ranking_db_pass']);

			if (!$this->config['ranking_db_charset']) $this->db->Link_ID->SetCharSet($GLOBALS['egw_info']['server']['system_charset']);

		}
		else
		{
			$this->db =& $GLOBALS['egw']->db;
		}
		foreach(array(
				'pkte'    => 'pktsystem',
				'rls'     => 'rls_system',
				'cats'    => 'category',
				'cup'     => 'cup',
				'comp'    => 'competition',
				'athlete' => 'athlete',
				'result'  => 'result',
				'route'   => 'route',
				'route_result'  => 'route_result',
			)+$extra_classes as $var => $class)
		{
			$egw_name = $class;
			if (!isset($GLOBALS['egw']->$egw_name))
			{
				$GLOBALS['egw']->$egw_name = CreateObject('ranking.'.$class,$this->config['ranking_db_charset'],$this->db,$this->config['vfs_pdf_dir']);
			}
			$this->$var =& $GLOBALS['egw']->$egw_name;
		}
	}

	/**
	 * Get an instance of the ranking_so object
	 *
	 * @return ranking_so
	 */
	public static function getInstance()
	{
		if (!is_object($GLOBALS['soranking']))
		{
			$GLOBALS['soranking'] = new ranking_so();
		}
		return $GLOBALS['soranking'];
	}
}
