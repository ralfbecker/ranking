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

use EGroupware\Api;
use EGroupware\Ranking\Athlete;

/**
 * EGroupware digital ROCK Rankings - storage object
 *
 * This is not a storage object itself: It created the DB connection to the (separate) rang database
 * and passes it to it's sub-objects which are private and get instanciated on demand by a __get() method.
 *
 * These sub-objects implement DB access to the various tables of the rang database.
 *
 * @property-read ranking_pktsystem $pkte
 * @property-read ranking_rls_system $rls
 * @property-read ranking_category $cats
 * @property-read ranking_cup $cup
 * @property-read ranking_competition $comp
 * @property-read Athlete $athlete
 * @property-read ranking_result $result
 * @property-read ranking_registration $registration
 * @property-read ranking_route $route
 * @property-read ranking_route_result $route_result
 * @property-read ranking_federation $federation;
 * @property-read ranking_display $display;
 * @property-read ranking_calculation $calc;
 */
class ranking_so
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
	 * @var ranking_pktsystem
	 */
	private $pkte;
	/**
	 * @var ranking_rls_system
	 */
	private $rls;
	/**
	 * @var ranking_category
	 */
	private $cats;
	/**
	 * @var ranking_cup
	 */
	private $cup;
	/**
	 * @var ranking_competition
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
	 * @var ranking_registration
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
	 * @var ranking_federation
	 */
	private $federation;
	/**
	 * @var ranking_display
	 */
	private $display;
	/**
	 * @var ranking_calculation
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
		'pkte'    => 'ranking_pktsystem',
		'rls'     => 'ranking_rls_system',
		'cats'    => 'ranking_category',
		'cup'     => 'ranking_cup',
		'comp'    => 'ranking_competition',
		'athlete' => Athlete::class,
		'result'  => 'ranking_result',
		'registration' => 'ranking_registration',
		'route'   => 'ranking_route',
		'route_result'  => 'ranking_route_result',
		'federation' => 'ranking_federation',
		'display' => 'ranking_display',
		'calc'    => 'ranking_calculation',
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

			if (!isset($GLOBALS['egw']->$name))
			{
				switch($class)
				{
					case 'ranking_calculation':
						$GLOBALS['egw']->$name = new ranking_calculation($this);
						break;

					default:
						$GLOBALS['egw']->$name = CreateObject('ranking.'.$class, $this->config['ranking_db_charset'], $this->db,
							$this->config['vfs_pdf_dir'], $this->config['vfs_pdf_url']);
						break;
				}
			}
			$this->$name = $GLOBALS['egw']->$name;
		}
		return $this->$name;
	}
}
