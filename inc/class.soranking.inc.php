<?php
/**
 * eGroupWare digital ROCK Rankings - storage object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2006-9 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$
 */

/**
 * eGroupWare digital ROCK Rankings - storage object
 *
 * This is not a storage object itself: It created the DB connection to the (separate) rang database
 * and passes it to it's sub-objects which are private and get instanciated on demand by a __get() method.
 *
 * These sub-objects implement DB access to the various tables of the rang database.
 */
class soranking
{
	var $debug;
	/**
	 * configuration
	 *
	 * @var array
	 */
	var $config;
	/**
	 * db-object with connection to ranking database, might be different from eGW database
	 *
	 * @var egw_db
	 */
	var $db;
	/**
	 * @var pktsystem
	 */
	private $pkte;
	/**
	 * @var rls_system
	 */
	private $rls;
	/**
	 * @var category
	 */
	private $cats;
	/**
	 * @var cup
	 */
	private $cup;
	/**
	 * @var competition
	 */
	private $comp;
	/**
	 * @var athlete
	 */
	private $athlete;
	/**
	 * @var result
	 */
	private $result;
	/**
	 * @var route
	 */
	private $route;
	/**
	 * @var route_result
	 */
	private $route_result;
	/**
	 * @var ranking_federation
	 */
	private $federation;
	/**
	 * @var ranking_display
	 */
	private $display;

	/**
	 * sub-objects, which get automatic instanciated by __get()
	 *
	 * @var unknown_type
	 */
	static $sub_classes = array(
		'pkte'    => 'pktsystem',
		'rls'     => 'rls_system',
		'cats'    => 'category',
		'cup'     => 'cup',
		'comp'    => 'competition',
		'athlete' => 'athlete',
		'result'  => 'result',
		'route'   => 'route',
		'route_result'  => 'route_result',
		'federation' => 'ranking_federation',
		'display' => 'ranking_display',
	);


	/**
	 * Constructor
	 *
	 * @param array $extra_classes
	 * @return soranking
	 */
	function __construct(array $extra_classes=array())
	{
		$this->db = self::get_rang_db($this->config);
	}

	/**
	 * Get a db object connected to the ranking database
	 *
	 * @param array &$config=null
	 * @return egw_db
	 */
	public static function get_rang_db(&$config=null)
	{
		if (is_null($config))
		{
			$c =& CreateObject('phpgwapi.config','ranking');
			$c->read_repository();
			$config = $c->config_data;
			unset($c);
		}
		if ($config['ranking_db_host'] || $config['ranking_db_name'])
		{
			foreach(array('host','port','name','user','pass') as $var)
			{
				if (!$config['ranking_db_'.$var]) $config['ranking_db_'.$var] = $GLOBALS['egw_info']['server']['db_'.$var];
			}
			$db = new egw_db();
			$db->connect($config['ranking_db_name'],$config['ranking_db_host'],
				$config['ranking_db_port'],$config['ranking_db_user'],$config['ranking_db_pass']);

			if (!$config['ranking_db_charset']) $db->Link_ID->SetCharSet($GLOBALS['egw_info']['server']['system_charset']);

		}
		else
		{
			$db = $GLOBALS['egw']->db;
		}
		return $db;
	}

	/**
	 * Getter for sub-classes, instanciates them on demand
	 *
	 * @param string $name
	 * @return mixed
	 */
	function __get($name)
	{
		//error_log(__METHOD__."('$name')");
		if ($this->$name === null)
		{
			if (!isset(self::$sub_classes[$name]))
			{
				if (!class_exists('egw_exception_wrong_parameter',true))
				{
					return false;	// eGW 1.4, can be removed if 1.4 support is no longer needed
				}
				throw new egw_exception_wrong_parameter("NO sub-class '$name'!");
			}
			$class = self::$sub_classes[$name];

			if (!isset($GLOBALS['egw']->$name))
			{
				$GLOBALS['egw']->$name = CreateObject('ranking.'.$class,$this->config['ranking_db_charset'],$this->db,$this->config['vfs_pdf_dir']);
			}
			$this->$name = $GLOBALS['egw']->$name;
		}
		return $this->$name;
	}
}
