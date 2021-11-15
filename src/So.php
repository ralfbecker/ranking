<?php
/**
 * EGroupware digital ROCK Rankings - storage object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2006-19 by Ralf Becker <RalfBecker@digitalrock.de>
 */

namespace EGroupware\Ranking;

use EGroupware\Api;
// not yet namespaced ranking_* classes
use ranking_result;
use ranking_route;
use ranking_route_result;
use ranking_display;

/**
 * EGroupware digital ROCK Rankings - storage object
 *
 * This is not a storage object itself: It created the DB connection to the (separate) rang database
 * and passes it to it's sub-objects which are private and get instanciated on demand by a __get() method.
 *
 * These sub-objects implement DB access to the various tables of the rang database.
 *
 * @property-read PktSystem $pkte
 * @property-read RlsSystem $rls
 * @property-read Category $cats
 * @property-read Cup $cup
 * @property-read Competition $comp
 * @property-read Athlete $athlete
 * @property-read ranking_result $result
 * @property-read Registration $registration
 * @property-read ranking_route $route
 * @property-read ranking_route_result $route_result
 * @property-read Federation $federation;
 * @property-read ranking_display $display;
 * @property-read Calculation $calc;
 */
class So
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
	 * @var Api\Db
	 */
	var $db;
	/**
	 * @var PktSystem
	 */
	private $pkte;
	/**
	 * @var RlsSystem
	 */
	private $rls;
	/**
	 * @var Category
	 */
	private $cats;
	/**
	 * @var Cup
	 */
	private $cup;
	/**
	 * @var Competition
	 */
	private $comp;
	/**
	 * @var Athlete
	 */
	private $athlete;
	/**
	 * @var ranking_result
	 */
	private $result;
	/**
	 * @var Registration
	 */
	private $registration;
	/**
	 * @var ranking_route
	 */
	private $route;
	/**
	 * @var ranking_route_result
	 */
	private $route_result;
	/**
	 * @var Federation
	 */
	private $federation;
	/**
	 * @var ranking_display
	 */
	private $display;
	/**
	 * @var Calculation
	 */
	private $calc;

	/**
	 * Error message
	 *
	 * @var array
	 */
	public $error = array();

	/**
	 * sub-objects, which get automatic instanciated by __get()
	 *
	 * @var array
	 */
	static $sub_classes = array(
		'pkte'    => PktSystem::class,
		'rls'     => RlsSystem::class,
		'cats'    => Category::class,
		'cup'     => Cup::class,
		'comp'    => Competition::class,
		'athlete' => Athlete::class,
		'result'  => 'ranking_result',
		'registration' => Registration::class,
		'route'   => 'ranking_route',
		'route_result'  => 'ranking_route_result',
		'federation' => Federation::class,
		'display' => 'ranking_display',
		'calc'    => Calculation::class,
	);


	/**
	 * Constructor
	 */
	function __construct()
	{
		$this->db = self::get_rang_db($this->config);
	}

	/**
	 * Get a db object connected to the ranking database
	 *
	 * @param array &$config=null
	 * @return Api\Db
	 */
	public static function get_rang_db(&$config=null)
	{
		if (is_null($config))
		{
			$config = Api\Config::read('ranking');
		}
		if ($config['ranking_db_host'] || $config['ranking_db_name'])
		{
			$defaults = !isset($GLOBALS['egw_setup']) ? $GLOBALS['egw_info']['server'] :
				$GLOBALS['egw_domain'][$GLOBALS['egw_setup']->ConfigDomain];

			foreach(array('host','port','name','user','pass') as $var)
			{
				if (!$config['ranking_db_'.$var]) $config['ranking_db_'.$var] = $defaults['db_'.$var];
			}
			$db = new Api\Db();
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
	 * Name of parent cat of all comptition cats
	 */
	const PARENT_CAT_NAME = 'Competitions';

	/**
	 * Get category by it's rkey (symbolic shortcut)
	 *
	 * @param string $rkey
	 * @param string $name ='' name to create category if not found
	 * @param int $parent =null parent for new categories, if not $global_parent
	 * @return int id or null if not found AND empty($name)
	 */
	public static function cat_rkey2id($rkey,$name='',$parent=null)
	{
		static $cats=null,$global_parent=null,$rkey2id=null;
		if (is_null($cats))
		{
			$cats = new Api\Categories(Api\Categories::GLOBAL_ACCOUNT, Api\Categories::GLOBAL_APPNAME);
			$global_parent = $cats->name2id(self::PARENT_CAT_NAME);
			if (!$global_parent)
			{
				$global_parent = $cats->add(array(
					'parent'  => 0,
					'name'    => self::PARENT_CAT_NAME,
					'description' => 'Do NOT change the name!',
				));
			}
			$rkey2id =& Api\Cache::getSession(__CLASS__, 'rkey2id', function() { return array(); });
		}
		if ($rkey === 'parent')
		{
			return $global_parent;
		}
		if ($rkey === 'NULL') $rkey = 'int';

		if (!isset($rkey2id[$rkey]))
		{
			foreach($cats->return_sorted_array(0, false, '', 'ASC', 'cat_name', true, $global_parent) as $cat)
			{
				if (!is_array($cat['data'])) $cat['data'] = unserialize($cat['data']);
				if (!$cat['data']) $cat['data'] = array();

				if (isset($cat['data']['rkey']) && $cat['data']['rkey'] == $rkey)
				{
					$rkey2id[$rkey] = $cat['id'];
				}
			}
			// create not found cat, if name is given
			if (!isset($rkey2id[$rkey]) && !empty($name))
			{
				$rkey2id[$rkey] = $cats->add(array(
					'parent'  => $parent > 0 ? $parent : $global_parent,
					'name'    => $name,
					'data'    => serialize(array('rkey' => $rkey)),
				));
			}
		}
		//echo "<p>".__METHOD__."('$rkey','$name',$parent) = ".array2string($rkey2id[$rkey])."</p>\n";
		return $rkey2id[$rkey];
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
				throw new Api\Exception\WrongParameter("NO sub-class '$name'!");
			}
			$class = self::$sub_classes[$name];

			if (!isset($this->$name))
			{
				$this->$name = new $class($this->config['ranking_db_charset'], $this->db,
					$this->config['vfs_pdf_dir'], $this->config['vfs_pdf_url']);
			}
		}
		return $this->$name;
	}
}
