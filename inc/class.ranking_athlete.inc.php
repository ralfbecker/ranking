<?php
/**
 * EGroupware digital ROCK Rankings - athlete storage object
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
 * Athlete object
 */
class ranking_athlete extends Api\Storage\Base
{
	var $charset,$source_charset;
	const ATHLETE_TABLE = 'Personen';
	const RESULT_TABLE = 'Results';
	const ATHLETE2FED_TABLE = 'Athlete2Fed';
	const FEDERATIONS_TABLE = 'Federations';
	const FEDERATIONS_JOIN = ' JOIN Athlete2Fed a2f ON Personen.PerId=a2f.PerId AND a2f.a2f_end=9999 JOIN Federations USING(fed_id) LEFT JOIN Athlete2Fed acl ON Personen.PerId=acl.PerId AND acl.a2f_end=-1';
	const LICENSE_TABLE = 'Licenses';

	const ACL_DENY_BIRTHDAY = 1;
	const ACL_DENY_EMAIL = 2;
	const ACL_DENY_PHONE = 4;
	const ACL_DENY_FAX = 8;
	const ACL_DENY_CELLPHONE = 16;
	const ACL_DENY_STREET = 32;
	const ACL_DENY_CITY = 64;

	/**
	 * Deny access to complete profile plus not showing names
	 */
	const ACL_DENY_ALL = 256;
	/**
	 * Deny access to complete profile
	 */
	const ACL_DENY_PROFILE = 128;
	/**
	 * Deny access to contact-data and birthday (only show year)
	 */
	const ACL_DEFAULT = self::ACL_DENY_BIRTHDAY|self::ACL_DENY_EMAIL|self::ACL_DENY_PHONE|self::ACL_DENY_FAX|self::ACL_DENY_CELLPHONE|self::ACL_DENY_STREET;
	/**
	 * Deny access to contact-data, birthday and city, but still show profil page
	 */
	const ACL_MINIMAL = self::ACL_DEFAULT|self::ACL_DENY_CITY;

	/**
	 * extra colums of the federation table (initialisied in init_static)
	 *
	 * @var array
	 */
	static $fed_cols;
	/**
	 * extra colums of the Athlete2Fed table used for read (initialisied in init_static)
	 *
	 * @var array
	 */
	static $a2f_cols;

	/**
	 * Federation cols, not longer stored in the athlete table
	 *
	 * @var array
	 */
	var $non_db_cols = array('verband','nation','fed_id','acl_fed_id','a2f_start','a2f_end');

	var $result_table = self::RESULT_TABLE;

	/**
	 * Instance of category object
	 *
	 * @var ranking_category
	 */
	var $cats;

	/**
	 * URL for athlete pictures (not configurable!)
	 *
	 * @var string
	 */
	var $picture_url = '/jpgs';

	/**
	 * Filesystem path for athlete pictures
	 *
	 * Set in __construct to value from site configuration or $_SERVER[DOCUMENT_ROOT]/jpgw (if nothing configured)
	 *
	 * @var string
	 */
	var $picture_path = '/var/lib/egroupware/ifsc-climbing.org/files/jpgs';

	/**
	 * Filesystem path to store athlete consent documents
	 *
	 * @var string
	 */
	var $consent_docs;

	var $acl2clear = array(
		self::ACL_DENY_BIRTHDAY  => array('geb_date'),
		self::ACL_DENY_EMAIL     => array('email'),
		self::ACL_DENY_PHONE     => array('tel'),
		self::ACL_DENY_FAX       => array('fax'),
		self::ACL_DENY_CELLPHONE => array('mobil'),
		self::ACL_DENY_STREET    => array('strasse','plz'),
		self::ACL_DENY_CITY      => array('ort'),
		self::ACL_DENY_PROFILE   => array('!','PerId','rkey','vorname','nachname','sex','nation','verband','license','acl','last_comp',
			'discipline','platz','pkt','WetId','GrpId'),		// otherwise they get no points in the ranking!
	);
	/**
	 * year we check the license for
	 *
	 * @var int
	 */
	var $license_year;

	/**
	 * Timestaps that need to be adjusted to user-time on reading or saving
	 *
	 * @var array
	 */
	var $timestamps = array('modified','recover_pw_time','last_login');

	/**
	 * Names for social media urls
	 *
	 * @var array
	 */
	static $social_links = array(
		'homepage', 'video_iframe',
	);

	/**
	 * Initialise the static vars of this class, called by including the class
	 */
	static function init_static()
	{
		self::$fed_cols = array(
			self::FEDERATIONS_TABLE.'.nation AS nation',
			self::FEDERATIONS_TABLE.'.verband AS verband',
			self::FEDERATIONS_TABLE.'.fed_id AS fed_id',
			'CASE WHEN acl.fed_id IS NULL THEN '.self::FEDERATIONS_TABLE.'.fed_parent ELSE acl.fed_id END AS fed_parent',
		);
		self::$a2f_cols = array(
			'a2f.a2f_start AS a2f_start',
			'a2f.a2f_end AS a2f_end',
			'acl.fed_id AS acl_fed_id',
		);
	}

	/**
	 * constructor of the athlete class
	 */
	function __construct($source_charset='',$db=null, $vfs_pdf_dir=null, $vfs_pdf_url=null)
	{
		if (is_null($db))
		{
			$db = ranking_so::get_rang_db();
		}
		parent::__construct('ranking',self::ATHLETE_TABLE,$db);	// call constructor of derived class

		if ($source_charset) $this->source_charset = $source_charset;

		$this->charset = Api\Translation::charset();

		foreach(array(
				'cats'  => 'ranking_category',
			) as $var => $class)
		{
			$egw_name = /*'ranking_'.*/$class;
			if (!isset($GLOBALS['egw']->$egw_name))
			{
				$GLOBALS['egw']->$egw_name = CreateObject('ranking.'.$class,$source_charset,$this->db,$vfs_pdf_dir,$vfs_pdf_url);
			}
			$this->$var =& $GLOBALS['egw']->$egw_name;
		}
		$config = Api\Config::read('ranking');
		$this->picture_path = empty($config['picture_path']) ? $_SERVER['DOCUMENT_ROOT'].'/jpgs' : $config['picture_path'];
		$this->consent_docs = empty($config['athlete_consent_docs']) ? 'ranking/athlete-consent-docs' : $config['athlete_consent_docs'];
		if ($this->consent_docs[0] !== '/') $this->consent_docs = $GLOBALS['egw_info']['server']['files_dir'].'/'.$this->consent_docs;

		$this->license_year = (int) date('Y');

		$GLOBALS['athlete'] = $this;
	}

	/**
	 * Get current age from given birthdate
	 *
	 * @param string $geb_date
	 * @return int years of NULL
	 */
	public static function age($geb_date)			// $geb_date als YYYY-MM-DD
	{
		$geb = explode('-',$geb_date);
		if (empty($geb_date) || count($geb) != 3) return NULL;

		$today = explode('-',date('Y-m-d'));
		$age = $today[0] - $geb[0] - ($today[1] < $geb[1] || $today[1] == $geb[1] && $today[2] < $geb[2]);

		return $age;
	}

	/**
	 * changes the data from the db-format to our work-format
	 *
	 * @param array $data if given works on that array and returns result, else works on internal data-array
	 * @return array
	 */
	function db2data($data=null)
	{
		if (($intern = !is_array($data)))
		{
			$data =& $this->data;
		}
		if (count($data) && $this->source_charset)
		{
			$data = Api\Translation::convert($data,$this->source_charset);
		}
		if ($data['practice'] && $data['practice'] > 100)
		{
			$data['practice'] = date('Y') - $data['practice'];
		}
		if ($data['geb_date'])
		{
			$data['geb_year'] = (int) $data['geb_date'];
			list($y, $m, $d) = explode('-', $data['geb_date']);
			list($ny, $nm, $nd) = explode('-', date('Y-m-d'));
			$data['age'] = $ny - $y - ($m > $nm || $m == $nm && $d > $nd);
		}
		// prefix all links without scheme with https://
		foreach(self::$social_links as $name)
		{
			if (!empty($data[$name]) && !preg_match('|^https?://|', $data[$name]))
			{
				$data[$name] = 'https://'.$data[$name];
			}
		}
		if ($data['acl'])
		{
			$acl = $data['acl'];
			// make sure all includes profile!
			if ($acl & self::ACL_DENY_ALL) $acl |= self::ACL_DENY_PROFILE;
			$data['acl'] = array();
			for($i = $n = 1; $i <= 16; ++$i, $n <<= 1)
			{
				if ($acl & $n) $data['acl'][] = $n;
			}
			// echo "<p>ranking_athlete::db2data($data[nachname], $data[vorname]) acl=$acl=".print_r($data['acl'],true)."</p>\n";

			// blank out the acl'ed fields, if user has no athletes rights
			if (is_object($GLOBALS['ranking_bo']) && !$GLOBALS['ranking_bo']->acl_check_athlete($data))
			{
				$data = $this->clear_data_by_acl($data, $acl);
			}
		}
		if (array_key_exists('license',$data) && !$data['license'])
		{
			$data['license'] = 'n';
		}
		return parent::db2data($intern ? null : $data);	// important to use null, if $intern!
	}

	/**
	 * Clear fields hidden by ACL
	 *
	 * @param array $data
	 * @param int $acl
	 * @return array
	 */
	function clear_data_by_acl(array $data, $acl)
	{
		foreach($this->acl2clear as $deny => $to_clear)
		{
			if ($acl & $deny)
			{
				foreach($to_clear[0] == '!' ? array_diff(array_keys($data),$to_clear) : $to_clear as $name)
				{
					if (substr($name, 0, 4) == 'reg_') continue;	// do not clear registration data, using this method too

					$data[$name] = $name == 'geb_date' && $data['geb_date'] ? (int)$data['geb_date'].'-01-01' : '';
				}
			}
		}
		return $data;
	}

	/**
	 * changes the data from our work-format to the db-format
	 *
	 * @param array $data if given works on that array and returns result, else works on internal data-array
	 * @return array
	 */
	function data2db($data=null)
	{
		if (($intern = !is_array($data)))
		{
			$data =& $this->data;
		}
		if ($data['rkey']) $data['rkey'] = strtoupper($data['rkey']);
		if ($data['nation'] && !is_array($data['nation']))
		{
			$data['nation'] = $data['nation'] == 'NULL' ? '' : strtoupper($data['nation']);
		}
		if (isset($data['acl']))
		{
			$acl = is_array($data['acl']) ? $data['acl'] : explode(',',$data['acl']);
			$data['acl'] = 0;
			foreach($acl as $n)
			{
				$data['acl'] |= $n;
			}
			//echo "<p>ranking_athlete::data2db() acl=".print_r($acl,true)."=$data[acl]</p>\n";
		}
		if ($data['practice'] && $data['practice'] < 100)
		{
			$data['practice'] = date('Y') - $data['practice'];
		}
		// hash password and reset recovery hash, time and failed login count
		if (!empty($data['password']) && stripos($data['password'], '{crypt}$') !== 0)
		{
			$data['password'] = Api\Auth::encrypt_ldap($data['password'], 'blowfish_crypt');
			$data['recover_pw_hash'] = $data['recover_pw_time'] = null;
			$data['login_failed'] = 0;
			error_log(__METHOD__."() password hashed ".array2string($data));
		}
		if (count($data) && $this->source_charset)
		{
			$data = Api\Translation::convert($data,$this->charset,$this->source_charset);
		}
		return parent::data2db($intern ? null : $data);	// important to use null, if $intern!
	}

	function init($arr=array())
	{
		parent::init($arr);

		// switching everything off, but the city
		// do NOT do it, if acl is already set, as we would loose ability to eg. search for an email address
		if (!is_array($arr) || !array_key_exists('acl', $arr))
		{
			$this->data['acl'] = self::ACL_DEFAULT;
		}
	}

	/**
	 * Federation join with variable table names and columns
	 *
	 * @param string $per_table ='Personen' table for athletes
	 * @param int $year =null year or sql expression for year to use
	 * @param string $f ='Federations' table for federations
	 * @return string sql with join
	 */
	function fed_join($per_table='Personen',$year=null,$f='Federations')
	{
		$join = strtr(self::FEDERATIONS_JOIN, array(
			'Personen' => $per_table,
			'a2f.a2f_end=9999' => is_null($year) ? 'a2f.a2f_end=9999' : "a2f.a2f_end >= $year AND $year >= a2f.a2f_start",
			'Federations' => $f ? $f : 'Federations',
		));
		//error_log(__METHOD__."('$per_table', '$year', '$f') returning '$join'");
		return $join;
	}

	/**
	 * Search for athletes, joins by default with the Result table to show the last competition and to filter by a category
	 *
	 * '*' and '?' are replaced with sql-wildcards '%' and '_'
	 *
	 * @param array|string $criteria array of key and data cols, OR a SQL query (content for WHERE), fully quoted (!)
	 * @param boolean $only_keys =true True returns only keys, False returns all cols
	 * @param string $order_by ='' fieldnames + {ASC|DESC} separated by colons ',', can also contain a GROUP BY (if it contains ORDER BY)
	 * @param string|array $_extra_cols ='' string or array of strings to be added to the SELECT, eg. "count(*) as num"
	 * @param string $wildcard ='' appended befor and after each criteria
	 * @param boolean $empty =false False=empty criteria are ignored in query, True=empty have to be empty in row
	 * @param string $op ='AND' defaults to 'AND', can be set to 'OR' too, then criteria's are OR'ed together
	 * @param mixed $start =false if != false, return only maxmatch rows begining with start, or array($start,$num)
	 * @param array $filter =null if set (!=null) col-data pairs, to be and-ed (!) into the query without wildcards
	 * @param mixed $join =true sql to do a join (as Api\Storage\Base::search), default true = add join for latest result
	 *	if numeric a special join is added to only return athlets competed in the given category (GrpId).
	 * @return array of matching rows (the row is an array of the cols) or False
	 */
	function &search($criteria,$only_keys=True,$order_by='',$_extra_cols='',$wildcard='',$empty=False,$op='AND',$start=false,$filter=null,$join=true)
	{
		//echo "<p>ranking_athlete::search(".print_r($criteria,true).",'$only_keys','$order_by','$extra_cols','$wildcard','$empty','$op','$start',".print_r($filter,true).",'$join')</p>\n";

		if ($only_keys === true) $only_keys = self::ATHLETE_TABLE.'.PerId';

		if ($join === true || is_numeric($join))
		{
			$cat = $join;
			$join = '';
		}
		// join in nation & federation
		$join .= self::FEDERATIONS_JOIN;
		if (!is_array($_extra_cols)) $_extra_cols = $_extra_cols ? explode(',', $_extra_cols) : array();
		$extra_cols = array_merge($_extra_cols, self::$fed_cols, self::$a2f_cols);

		// by default only show real athlets (nation and sex set)
		if (!isset($filter['sex']) && !(isset($criteria['sex']) && $op == 'AND'))
		{
			$filter[] = 'sex IS NOT NULL';
		}
		if (($n = strpos($order_by,'PerId')) !== false && (!$n || $order_by[$n-1] != '.'))
		{
			$order_by = str_replace('PerId',self::ATHLETE_TABLE.'.PerId',$order_by);
		}
		if ($order_by[0] == '_') $order_by = substr($order_by,1);	// cut of _ from eTemplate hack to allow sort and filter for same nm colum

		// handle nation, verband and fed_id, which are in the Federations or Athelte2Fed table
		foreach(array(
			'nation'  => self::FEDERATIONS_TABLE,
			'verband' => self::FEDERATIONS_TABLE,
			'fed_id'  => 'a2f',	//a2f is alias for self::ATHLETE2FED_TABLE,
		) as $name => $table)
		{
			if ($filter[$name])
			{
				if ($filter[$name] == 'NULL') $filter[$name] = null;
				$f = $table.'.'.$name.(is_null($filter[$name]) ? ' IS NULL' : '='.$this->db->quote($filter[$name]));
				if ($name == 'fed_id' && !is_null($filter[$name]))	// also filter for acl federation (SAC Regionalzentrum) and fed_parent
				{
					$year = (int)date('Y');
					$f = '('.$f.' OR '.str_replace('a2f.fed_id','acl.fed_id',$f).
						" OR (CASE WHEN fed_since >= $year THEN fed_parent_since ELSE fed_parent END)=".(int)$filter[$name].')';
				}
				$filter[] = $f;
			}
			unset($filter[$name]);
			if (is_array($criteria) && $criteria[$name])
			{
				if ($criteria[$name] == 'NULL') $criteria[$name] = null;
				$criteria[] = $table.'.'.$name.(is_null($criteria[$name]) ? ' IS NULL' : '='.$this->db->quote($criteria[$name]));
			}
			if (is_array($criteria)) unset($criteria[$name]);
			if (strpos($order_by,$name) !== false && ($name != 'fed_id' || strpos($order_by,'acl_fed_id') === false))
			{
				$order_by = str_replace($name,$table.'.'.$name,$order_by);
			}
		}
		$order_by .= ($order_by?',':'').'nachname,vorname';

		if ($cat === true || is_numeric($cat) || $join)
		{
			if (is_numeric($cat))	// add join to filter for a category
			{
				$cat = (int) $cat;
				$join .= " JOIN $this->result_table ON $this->result_table.GrpId=$cat AND $this->table_name.PerId=$this->result_table.PerId";

				// if cat uses an age-group only show athlets in that age-group
				$from_year = $to_year = null;
				if (($cat = $this->cats->read($cat)) && $this->cats->age_group($cat,date('Y-m-d'),$from_year,$to_year))
				{
					if ($from_year) $join .= " AND ".(int)$from_year." <= YEAR(geb_date)";
					if ($to_year) $join .= " AND YEAR(geb_date) <= ".(int)$to_year;
				}
				$extra_cols[] = "MAX($this->result_table.datum) AS last_comp";
				$order_by = "GROUP BY $this->table_name.PerId ORDER BY $order_by";

				$license_nation = $cat['nation'];
			}
			else	// LEFT JOIN to get latest competition
			{
				$extra_cols[] = "(SELECT MAX(datum) FROM $this->result_table WHERE $this->result_table.PerId=$this->table_name.PerId AND platz > 0) AS last_comp";
			}
			// get the license (license nation is set by: nation filter, category (numeric join arg), filter[license_nation] (highes precedence))
			if (is_array($filter) && array_key_exists('license_nation',$filter))	// pseudo filter, only specifies the nation, does NOT filter by it!
			{
				$license_nation = $filter['license_nation'];
				unset($filter['license_nation']);
			}
			$license_year = $this->license_year;
			if (isset($filter['license_year']))		// pseudo filter: explicit specified license year, does NOT filter by it!
			{
				$license_year = (int)$filter['license_year'];
				unset($filter['license_year']);
			}
			//echo "<p>license_year=$license_year, license_nation=$license_nation</p>\n";
			$join .= $this->license_join($license_nation, $license_year);
			$extra_cols[] = 'lic_status AS license';
			$extra_cols[] = 'l.GrpId AS license_cat';
			if ($filter['license'])
			{
				$filter[] = $filter['license'] === 'n' ? 'lic_status IS NULL' :
					$this->db->expression(self::LICENSE_TABLE,array('lic_status'=>$filter['license']));
				unset($filter['license']);
			}
		}
		if ($join && strstr($join,'PerId') !== false)	// this column would be ambigues
		{
			if (is_array($criteria) && $criteria['PerId'])
			{
				$criteria[] = $this->db->expression($this->table_name,$this->table_name.'.',array('PerId'=>$criteria['PerId']));
				unset($criteria['PerId']);
			}
			if ($filter['PerId'])
			{
				$filter[] = $this->db->expression($this->table_name,$this->table_name.'.',array('PerId'=>$filter['PerId']));
				unset($filter['PerId']);
			}
			$extra_cols[] = $this->table_name.'.PerId AS PerId';	// LEFT JOIN'ed Results.PerId is NULL if there's no result
		}
		return parent::search($criteria,$only_keys,$order_by,$extra_cols,$wildcard,$empty,$op,$start,$filter,$join);
	}

	/**
	 * LEFT JOIN license table
	 *
	 * @param string $license_nation
	 * @param int $license_year
	 * @return string
	 */
	public function license_join($license_nation, $license_year)
	{
		return ' LEFT JOIN '.self::LICENSE_TABLE.' l ON l.PerId='.self::ATHLETE_TABLE.'.PerId AND '.
				' l.nation='.$this->db->quote(!$license_nation || $license_nation == 'NULL' ? '' : $license_nation).' AND '.
				self::license_valid_sql($license_year);
	}

	/**
	 * SQL to check license is valid for a given year
	 *
	 * @param int $license_year
	 * @return string
	 */
	protected static function license_valid_sql($license_year)
	{
		return '(lic_year='.(int)$license_year.' OR lic_year<='.(int)$license_year.' AND '.(int)$license_year.'<=lic_until)';
	}

	/**
	 * Gets a distinct list of all values of a given column for a given nation (or all)
	 *
	 * @param string $column column-name, eg. 'nation', 'nachname', ...
	 * @param array|string $keys array with column-value pairs or string with nation to search, defaults to '' = all
	 * @return array with name as key and value
	 */
	function distinct_list($column,$keys='')
	{
		//echo "<p>ranking_athlete::distinct_list('$column',".print_r($keys,true),")</p>\n"; $start = microtime(true);

		static $cache = array();
		$cache_key = $column.'-'.serialize($keys);

		if (isset($cache[$cache_key]))
		{
			return $cache[$cache_key];
		}
		if (!in_array($column,array('nation','verband')) && !($column = array_search($column,$this->db_cols))) return false;

		if ($keys && !is_array($keys)) $keys = array('nation' => $keys);

		$values = array();

		if (in_array($column,array('nation','verband')) && (!$keys || !array_diff(array('nation','verband'),array_keys((array)$keys))))
		{
			$result = $this->db->select(self::FEDERATIONS_TABLE,'DISTINCT '.$column,$keys,__LINE__,__FILE__,false,'ORDER BY '.$column,'ranking');
		}
		else
		{
			$result = (array)$this->search($keys,'DISTINCT '.$column,$column,'','',true,'AND',false,null,'');
		}
		foreach($result as $data)
		{
			$val = $data[$column];
			$values[$val] = $val;
		}
		//echo "<p>ranking_athlete::distinct_list('$column',".print_r($keys,true),") took ".round(1000*(microtime(true)-$start))."ms</p>\n";
		return $cache[$cache_key] =& $values;
	}

	/**
	 * Generates a rkey for an athlete by using one letter of the first name, 2 from the last name the year and the sex
	 */
	function generate_rkey($data='')
	{
		if (!is_array($data)) $data =& $this->data;

		if ($data['rkey']) return $data['rkey'];

		$data['rkey'] = substr($data['vorname'],0,1).substr($data['nachname'],0,2).date('y').substr($data['sex'],0,1);

		// we convert some chars to 7bit ascii, the rest is removed by mb_convert_encoding(...,'7bit',...)
		static $to_ascii = array(
			'Ä' => 'A', 'ä' => 'a', 'á' => 'a', 'à' => 'a', 'À' => 'A', 'Á' => 'A',
			'Ö' => 'O', 'ö' => 'o',
			'Ü' => 'U', 'ü' => 'u',
			'ß' => 's',
			'é' => 'e', 'É' => 'E', 'è' => 'e', 'È' => 'E', 'ê' => 'e', 'Ê' => 'E',
			'ć' => 'c', 'Ć' => 'C', 'ĉ' => 'c', 'Ĉ' => 'C',
		);
		$data['rkey'] = mb_convert_encoding(strtoupper(str_replace(array_keys($to_ascii),array_values($to_ascii),$data['rkey'])),
			'7bit',Api\Translation::charset());

		$rkey = $data['rkey'];
		$n = 0;
		while ($this->not_unique() != 0)
		{
			$data['rkey'] = $rkey.$n++;
		}
		return $data['rkey'];
	}

	/**
	 * Checks if an athlete already has results, a result-service result recorded or is registered for a competition
	 *
	 * @param int|array $keys PerId or array with keys of the athlete to check, default null = use keys in data
	 * @return boolean
	 */
	function has_results($keys=null)
	{
		if (is_array($keys))
		{
			$data_backup = $this->data;
			if (!$this->read($keys))
			{
				$this->data = $data_backup;
				return false;
			}
		}
		$PerId = is_numeric($keys) ? $keys : $this->data['PerId'];
		if ($data_backup) $this->data = $data_backup;

		return $this->db->select('Results', 'COUNT(*)',array('PerId' => $PerId,'platz > 0'), __LINE__, __FILE__)->fetchColumn() ||
			$this->db->select('RouteResults','COUNT(*)',array('PerId' => $PerId), __LINE__, __FILE__)->fetchColumn() ||
			$this->db->select('Registration', 'COUNT(*)',array('PerId' => $PerId,'reg_deleted IS NULL'), __LINE__, __FILE__)->fetchColumn();
	}

	/**
	 * get the path of the picture
	 *
	 * @param string $rkey =null rkey of the athlete or null to use this->data[rkey]
	 * @param int $num =null 2 for action picture
	 * @return string/boolean the path or false if the rkey is empty (NOT if the picture does NOT exist!)
	 */
	function picture_path($rkey=null, $num=null)
	{
		if (is_null($rkey)) $rkey = $this->data['rkey'];

		return $rkey ? $this->picture_path.'/'.$rkey.($num ? '-'.(int)$num : '').'.jpg' : false;
	}

	/**
	 * attach a picture to the athlete
	 *
	 * @param string $fname filename of the picture to attach
	 * @param string $rkey =null rkey of the athlete or null to use this->data[rkey]
	 * @param int $num =null 2 for action picture
	 * @return boolean true on success, flase otherwise
	 */
	function attach_picture($fname,$rkey=null, $num=null)
	{
		$path = $this->picture_path($rkey, $num);

		//error_log(__METHOD__."('$fname', '$rkey') path=$path, file_exists($fname)=".array2string(file_exists($fname)).", is_readable($fname)=".array2string(is_readable($fname)).", is_writable($this->picture_path)=".array2string(is_writable($this->picture_path)).")");
		if (!$path || !file_exists($fname) || !is_readable($fname) || !is_writeable($this->picture_path)) return false;

		if (file_exists($path)) @unlink($path);

		return copy($fname,$path);
	}

	/**
	 * get the url of the picture
	 *
	 * @param string $rkey =null rkey of the athlete or null to use this->data[rkey]
	 * @param int $num =null 2 for action picture
	 * @return string/boolean the url or false if picture does not exist
	 */
	function picture_url($rkey=null, $num=null)
	{
		if (is_null($rkey)) $rkey = $this->data['rkey'];

		$url = $this->picture_url.'/'.$rkey.($num ? '-'.(int)$num : '').'.jpg';
		$path = $this->picture_path($rkey, $num);

		return file_exists($path) && is_readable($path) ? $url.'?'.filemtime($path) : false;
	}

	/**
	 * delete the picture
	 *
	 * @param int $num =null 2 for action picture
	 * @return boolean true on success, false otherwise
	 */
	function delete_picture($num=null)
	{
		return @delete($this->picture_path(null, $num));
	}

	/**
	 * Get path to store consent file
	 *
	 * Uses the ranking config "athlete_consent_docs" defaulting to "ranking/athlete-consent-docs" in files directory.
	 *
	 * @param int|array|NULL $athlete =null PerId or array of the athlete or null to use this->data[PerId]
	 * @return string full filesystem path
	 */
	function consent_dir($athlete=null)
	{
		return $this->consent_docs.'/'.(int)(is_array($athlete) ? $athlete['PerId'] : $athlete);
	}

	/**
	 * Get filesystem path of consent document for athlete
	 *
	 * @param string $fname filename of the picture to attach
	 * @param int|array|NULL $athlete =null PerId or array of the athlete or null to use this->data[PerId]
	 * @return string|NULL full path to consent document or null
	 */
	function consent_document($athlete=null)
	{
		$path = $this->consent_dir($athlete ? $athlete : $this->data);

		if (file_exists($path))
		{
			foreach(scandir($path, SCANDIR_SORT_DESCENDING) as $file)
			{
				$file = $path.'/'.$file;
				if (file_exists($file) && is_readable($file))
				{
					return $file;
				}
			}
		}
		return null;
	}

	/**
	 * Attach consent document for athlete
	 *
	 * Consent document are versions by prefixing them with a descending letter starting with "A".
	 *
	 * @param string|array $upload filename of the picture to attach or upload-array with tmp_name and name
	 * @param int|array|NULL $athlete =null PerId or array of the athlete or null to use this->data[PerId]
	 * @return boolean true on success, flase otherwise
	 */
	function attach_consent($upload, $athlete=null)
	{
		$path = $this->consent_dir($athlete ? $athlete : $this->data);

		$fname = is_array($upload) ? $upload['tmp_name'] : $upload;

		//error_log(__METHOD__."('$fname', .".array2string($athlete).") path=$path, file_exists($fname)=".array2string(file_exists($fname)).", is_readable($fname)=".array2string(is_readable($fname)).", is_writable($this->consent_docs)=".array2string(is_writable($this->consent_docs)).")");
		if (!file_exists($fname) || !is_readable($fname) ||
			!(file_exists($path) || mkdir($path, 0700, true)) ||
			!is_writable($path))
		{
			return false;
		}
		// we version the attached files by using a prefix starting with 'A'
		foreach(scandir($path, SCANDIR_SORT_DESCENDING) as $file)
		{
			if (in_array($file, array('.', '..'))) continue;

			$letter = chr(1+ord($file[0]));
			break;
		}
		if (empty($letter)) $letter = 'A';

		return copy($fname, $path.'/'.$letter.'-'.
			(is_array($upload) && !empty($upload['name']) ? $upload['name'] : 'consent'));
	}

	/**
	 * Age / year of last result before which profiles are hidden by default,
	 * if there is no explicit athlete consent
	 * In 2018: 2018-2 = 2016 --> no result or registration in 2016 or newer
	 */
	const PROFILE_DEFAULT_HIDDEN_AGE = 2;

	/**
	 * Check if athlete profile is hidden
	 *
	 * @param int|string|array $athlete array with athlete data, PerId or rkey
	 * @param string& $shown_msg =null message why profile is NOT hidden
	 * @return string|null string with reason the profile is hidden or null, if it is NOT hidden
	 */
	public function profile_hidden(array $athlete, &$shown_msg=null)
	{
		if (!is_array($athlete) && !($athlete = $this->read($athlete)))
		{
			return lang('Athlete NOT found !!!');
		}

		if (is_array($athlete['acl']))
		{
			$acl = 0;
			foreach($athlete['acl'] as $rights)
			{
				$acl |= $rights;
			}
		}
		else
		{
			$acl = (int)$athlete['acl'];
		}
		// check if profile is explcitly hidden
		if ($acl & self::ACL_DENY_PROFILE)
		{
			return lang('Sorry, the climber requested not to show his profile!');
		}

		// check if we have athlete consent to show his data
		$have_doc = $shown_msg = null;
		if (!empty($athlete['consent_ip']) && !empty($athlete['consent_time']) ||
			($have_doc = $this->consent_document($athlete)))
		{
			if ($have_doc)
			{
				$shown_msg = lang('A document with the athlete consent was uploaded.');
			}
			if (!empty($athlete['consent_ip']) && !empty($athlete['consent_time']))
			{
				$shown_msg = (!empty($shown_msg) ? $shown_msg.' ' : '').
					lang('Online consent of athlete on %1.', $athlete['consent_time']);
			}
			return null;
		}

		// policy when to show a profile, if we have no explicit consent
		$hide_before_year = date('Y') - self::PROFILE_DEFAULT_HIDDEN_AGE;
		// check results imported into ranking
		if (!isset($athlete['last_comp']))
		{
			$athlete['last_comp'] = $this->db->select($this->result_table, 'MAX(datum)', array(
				'PerId' => $athlete['PerId'],
			), __LINE__, __FILE__)->fetchColumn();
		}
		if (!empty($athlete['last_comp']) && (int)$athlete['last_comp'] >= $hide_before_year)
		{
			$shown_msg = lang('Athlete has a recent result from %1.', $athlete['last_comp']);
			return null;
		}
		// check results in result-service
		$last_result = $this->db->select('RouteResults', 'DATE(FROM_UNIXTIME(MAX(result_modified)))', array(
			'PerId' => $athlete['PerId'],
		), __LINE__, __FILE__)->fetchColumn();
		if (!empty($last_result) && (int)$last_result >= $hide_before_year)
		{
			$shown_msg = lang('Athlete has a recent result from %1.', $last_result);
			return null;
		}
		/* not considering registration a consent, therefore not checking it
		$last_registration = $this->db->select('Registration', 'DATE(MAX(reg_registered))', array(
			'PerId' => $athlete['PerId'],
			'reg_deleted IS NULL',
		), __LINE__, __FILE__)->fetchColumn();
		if (!empty($last_registration) && (int)$last_registration >= $hide_before_year)
		{
			$shown_msg = lang('Athlete has a recent registration from %1.', $last_registration);
			return null;
		}*/

		// new profiles are hidden by default, as long as they have no result
		if (empty($athlete['last_comp']) && empty($last_result) &&
			(int)$athlete['modified'] >= $hide_before_year)
		{
			return lang('New athlete profile with no result yet.');
		}

		// profile is hidden by default
		return lang('Historic athlete profile is hidden as we have no explicit consent from him/her to show the data.');
	}

	/**
	 * deletes athlete(s), see Api\Storage\Base
	 *
	 * reimplemented to delete the picture too, works only if one athlete (specified by rkey or internal data) is deleted!
	 *
	 * @param array $keys =null see Api\Storage\Base
	 * @return int deleted rows or 0 on error
	 */
	function delete($keys=null)
	{
		$Ok = parent::delete($keys);

		if ($Ok && (is_null($keys) || $keys['rkey']))
		{
			$this->delete_picture($keys['rkey']);
		}
		if ($Ok && (is_null($keys) || $keys['PerId']))
		{
			// delete licenses
			$this->db->delete(self::LICENSE_TABLE,array(
				'PerId' => is_null($keys) ? $this->data['PerId'] : $keys['PerId'],
			),__LINE__,__FILE__);
			// delete association with federations
			$this->db->delete(self::ATHLETE2FED_TABLE,array(
				'PerId' => is_null($keys) ? $this->data['PerId'] : $keys['PerId'],
			),__LINE__,__FILE__);
			// remove consent file(s)
			if (file_exists(($path = $this->consent_dir($keys))))
			{
				foreach(scandir($path, SCANDIR_SORT_DESCENDING) as $file)
				{
					if (in_array($file, array('.', '..'))) continue;

					unlink($path.'/'.$file);
				}
				rmdir($path);
			}
		}
		return $Ok;
	}

	/**
	 * reads row matched by key and puts all cols in the data array
	 *
	 * @param array $keys array with keys in form internalName => value, may be a scalar value if only one key
	 * @param string|array $_extra_cols string or array of strings to be added to the SELECT, eg. "count(*) as num"
	 * @param string $join numeric year, adds a license-column or sql to do a join, see Api\Storage\Base::read()
	 * @param string $nation =null nation for license-data
	 * @return array/boolean data if row could be retrived else False
	 */
	function read($keys,$_extra_cols='',$join='',$nation=null)
	{
		//echo "<p>".__METHOD__."(".array2string($keys).",$extra_cols,$join,$nation)</p>\n";
		if (is_numeric($year = $join))
		{
			$join = '';
		}
		// join in the federation information
		$join .= self::FEDERATIONS_JOIN;
		if (!is_array($_extra_cols)) $_extra_cols = $_extra_cols ? explode(',', $_extra_cols) : array();
		$extra_cols = array_merge($_extra_cols, self::$fed_cols, self::$a2f_cols);

		if (is_numeric($year))
		{
			$join .= $this->license_join($nation, $year);
			$extra_cols[] = 'lic_status AS license';
			$extra_cols[] = 'l.GrpId as license_cat';
		}
		$extra_cols[] = $this->table_name.'.PerId AS PerId';	// would be NULL if join fails!

		// get last competition
		$extra_cols[] = "(SELECT MAX(datum) FROM $this->result_table WHERE $this->result_table.PerId=$this->table_name.PerId) AS last_comp";

		return parent::read($keys,$extra_cols,$join);
	}

	/**
	 * Get the license of a given year
	 *
	 * @param int $year
	 * @param string $nation nation for a national license, or null for an international one
	 * @param int $PerId =null else use $this->data[PerId]
	 * @return string|boolean false on wrong parameter or string with license status ('n' for no license)
	 */
	function get_license($year,$nation,$PerId=null)
	{
		if (!(int)$year) return false;

		if ($nation == 'NULL') $nation = null;

		if (!($status = $this->db->select(self::LICENSE_TABLE,'lic_status',array(
			'PerId' => is_null($PerId) ? $this->data['PerId'] : $PerId,
			'nation' => (string)$nation,
			self::license_valid_sql($year),
		),__LINE__,__FILE__,false,'','ranking')->fetchColumn()))
		{
			$status = 'n';
		}
		//echo "<p>get_license($year,'$nation',$PerId) status='$status'</p>\n";
		return $status;
	}

	/**
	 * Set the license for a given year
	 *
	 * @param int $year
	 * @param string $status 'n' = none, 'a' = applied, 'c' = confirmed, 's' = suspended
	 * @param int $PerId =null else use $this->data[PerId]
	 * @param string $nation =null nation for a national license, or null for an international one
	 * @param int $GrpId =null category to apply for
	 * @return boolean|int false on wrong parameter or athlete not matching cat age-group, or number of affected rows
	 */
	function set_license($year,$status='c',$PerId=null,$nation=null,$GrpId=null)
	{
		//echo "<p>set_license($year,'$status',$PerId,'$nation')</p>\n";
		if (is_null($PerId)) $PerId = $this->data['PerId'];

		if (!(int)$PerId || $year <= 0) return false;

		if ($GrpId)	// if category given, check if athlete meets categories age-group
		{
			if ($PerId != $this->data['PerId'])
			{
				$backup = $this->data;
				$this->read($PerId);
			}
			$wrong_agegroup = !$this->cats->in_agegroup($this->data['geb_date'], $GrpId);
			if ($backup) $this->data = $backup;
			if ($wrong_agegroup) return false;
		}
		$where = array(
			'PerId' => $PerId,
			'nation' => !$nation || $nation == 'NULL' ? '' : $nation,
			self::license_valid_sql($year),
		);
		if (!in_array($status,array('a','c','s'))/* || $status == 'n'*/)
		{
			// if a German license from a previous year get deleted, end it via lic_until the year before
			if($nation == 'GER' && ($license=$this->db->select(self::LICENSE_TABLE,'*',$where,__LINE__,__FILE__,false,'','ranking')->fetch()) &&
				$license['lic_year'] < $year)
			{
				$this->db->update(self::LICENSE_TABLE,array(
					'lic_until' => $year-1
				),$where,__LINE__,__FILE__,'ranking');
			}
			else
			{
				$this->db->delete(self::LICENSE_TABLE,$where,__LINE__,__FILE__,'ranking');
			}
		}
		else
		{
			// add fake result to find athlete in category
			if ($GrpId)
			{
				$this->db->insert('Results', array(
					'platz' => 0,
					'pkt' => 0,
				),array(
					'PerId' => $PerId,
					'GrpId' => $GrpId,
					'WetId' => 0,
				), __LINE__, __FILE__, 'ranking');
			}
			switch($status)
			{
				case 'a': $what = 'applied'; break;
				case 'c': $what = 'confirmed'; break;
				case 's': $what = 'suspended'; break;
			}
			$data = array(
				'lic_status' => $status,
				'lic_'.$what => date('Y-m-d'),
				'lic_'.$what.'_by' => $GLOBALS['egw_info']['user']['account_id'],
			);
			if ($GrpId) $data['GrpId'] = $GrpId;

			if($this->db->select(self::LICENSE_TABLE,'PerId',$where,__LINE__,__FILE__,false,'','ranking')->fetch())
			{
				$this->db->update(self::LICENSE_TABLE,$data,$where,__LINE__,__FILE__,'ranking');
			}
			else
			{
				unset($where[0]);	// lic_year=... OR lic_year<=... AND ...<=lic_until
				$data += $where;
				$data['lic_year'] = $year;
				// for GER we need to check birthdate for license vality
				if ($nation == 'GER' && ($athlete = $this->read($PerId)))
				{
					// only older then 18, get an unlimited license, else it's valid only until 18
					$data['lic_until'] = $year-(int)$athlete['geb_date'] > 18 ? 9999 : (int)$athlete['geb_date']+18;
				}
				$this->db->insert(self::LICENSE_TABLE,$data,false,__LINE__,__FILE__,'ranking');
			}
		}
		if ($PerId == $this->data['PerId'])
		{
			$this->data['license'] = $status;
			$this->data['license_cat'] = $GrpId;
		}
		//echo "ranking_athlete::set_license($year,'$status',$PerId)"; _debug_array($this->data);
		return $this->db->affected_rows();
	}

	/**
	 * Merge the licenses from athlete $from to athlete $to
	 *
	 * If both have applied, confirmed or suspended licenses, the information in $to has precedence,
	 * while the status of suspended or confirmed is maintained.
	 *
	 * @param int $from
	 * @param int $to
	 * @return int number of merged licenses
	 */
	function merge_licenses($from,$to)
	{
		if (!(int)$from || !(int)$to)
		{
			return false;
		}

		$updated = 0;
		foreach($this->db->select(self::LICENSE_TABLE,'*',array('PerId' => array($from,$to)),__LINE__,__FILE__,false,'ORDER BY lic_year,PerId='.(int)$from,'ranking') as $row)
		{
			if ($row['PerId'] == $to)
			{
				$to_row = $row;
				continue;
			}
			// now we are in the from row and $to_row contains the last $to license row
			if ($to_row && $to_row['lic_year'] != $row['lic_year']) $to_row = null;	// different year

			// no license for to --> update PerId in from row
			if (!$to_row)
			{
				$this->db->update(self::LICENSE_TABLE,array('PerId'=>$to),array(
					'PerId' => $from,
					'lic_year' => $row['lic_year'],
				),__LINE__,__FILE__,'ranking');
				$updated += $this->db->affected_rows();
				continue;
			}
			$need_update = false;
			// merge license info from $to_row and $row and store it in $to_row
			foreach(array('a' => 'lic_applied','c' => 'lic_confirmed','s' => 'lic_suspended') as $status => $name)
			{
				if (!$to_row[$name] && $row[$name])
				{
					$to_row[$name] = $row[$name];
					$to_row[$name.'_by'] = $row[$name.'_by'];
					if ($to_row['lic_status'] != 's') $to_row['lic_status'] = $status;
					$need_update = true;
				}
			}
			if ($need_update)
			{
				$this->db->update(self::LICENSE_TABLE,$to_row,array(
					'PerId' => $to,
					'lic_year' => $to_row['lic_year'],
				),__LINE__,__FILE__,'ranking');
				$updated += $this->db->affected_rows();
			}
		}

		return $updated;
	}

	/**
	 * Return a list of federation names indexed by fed_id, evtl. of a given nation only
	 *
	 * @param string $nation =null
	 * @param boolean $only_national =false if true return only national federations (fed_parent=NULL)
	 * @return array
	 */
	function federations($nation=null,$only_national=false,$filter = array())
	{
		$feds = array();
		if ($nation) $filter['nation'] = $nation;
		if ($only_national) $filter[] = 'fed_parent IS NULL';

		foreach($this->db->select(self::FEDERATIONS_TABLE,'fed_id,verband,nation',$filter,__LINE__,__FILE__,false,
			'ORDER BY nation ASC,verband ASC','ranking') as $fed)
		{
			$feds[$fed['fed_id']] = (!$nation ? $fed['nation'].': ' : '').$fed['verband'];
		}
		return $feds;
	}

	/**
	 * Set the federation of an athlete, automatic record the old federation
	 *
	 * @param int $fed_id id of the federation
	 * @param int &$a2f_start start year of (new) federation, only used if the federation changes, always returned
	 * @param int $PerId =null default current athlete
	 * @param int $fed_parent =null explicit fed_id of parent (0 deletes)
	 * @return true if federation is set, null was already set, false on error
	 */
	function set_federation($fed_id,&$a2f_start,$PerId=null,$fed_parent=null)
	{
		if (is_null($PerId)) $PerId = $this->data['PerId'];
		//echo "<p>".__METHOD__."($fed_id,$a2f_start,$PerId,$fed_parent)</p>\n";
		if (!$PerId || !$fed_id) return false;

		if (!($a2f_start > 2000)) $a2f_start = (int)date('Y');

		if (!is_null($fed_parent))
		{
			// set or delete explicit fed_parent
			if ((int)$fed_parent > 0)
			{
				$this->db->insert(self::ATHLETE2FED_TABLE,array(
					'fed_id' => $fed_parent,
				),array(
					'PerId' => $PerId,
					'a2f_end' => -1,
					'a2f_start' => 0,
				),__LINE__,__FILE__,'ranking');
			}
			else
			{
				$this->db->delete(self::ATHLETE2FED_TABLE,array(
					'PerId' => $PerId,
					'a2f_end' => -1,
					'a2f_start' => 0,
				),__LINE__,__FILE__,'ranking');
			}
		}
		// read current (year=9999) federation
		if (!($fed = $this->db->select(self::ATHLETE2FED_TABLE,'*',array(
			'PerId' => $PerId,
			'a2f_end' => 9999,
		),__LINE__,__FILE__)->fetch()))
		{
			$a2f_start = 0;		// not found --> ignore start given by user and use default of 0
		}
		elseif($fed['fed_id'] == $fed_id)	// no change necessary --> ignore it
		{
			//echo "<p>fed not changed, setting old start of $fed[a2f_start]</p>\n";
			$a2f_start = $fed['a2f_start'];
			return null;
		}
		elseif($a2f_start && $a2f_start > $fed['a2f_start']) 	// federation changed and (valid) start given --> record old one
		{
			$this->db->update(self::ATHLETE2FED_TABLE,array('a2f_end' => $a2f_start-1),$fed,__LINE__,__FILE__);
		}
		else	// no (valid) start given, use current one
		{
			//echo "<p>no (valid) start ($a2f_start) given, using $fed[a2f_start] now</p>\n";
			$a2f_start = $fed['a2f_start'];
		}
		// store the new federation including start
		return !!$this->db->insert(self::ATHLETE2FED_TABLE,array(
			'fed_id' => $fed_id,
		),array(
			'PerId' => $PerId,
			'a2f_end' => 9999,
			'a2f_start' => $a2f_start,
		),__LINE__,__FILE__,'ranking');
	}

	/**
	 * saves the content of data to the db
	 *
	 * Reimplemented to save nation&federation ...
	 *
	 * @param array $keys if given $keys are copied to data before saveing => allows a save as
	 * @param string|array $extra_where =null extra where clause, eg. to check an etag, returns true if no affected rows!
	 * @param boolean &$set_fed =null on return true: fed changed, null: no change necessary, false: error setting fed
	 * @return int|boolean 0 on success, or errno != 0 on error, or true if $extra_where is given and no rows affected
	 */
	function save($keys=null,$extra_where=null,&$set_fed=null)
	{
		unset($extra_where);	// not used, but required by function signature
		if (is_array($keys) && count($keys)) $this->data_merge($keys);

		// we need to get the old values to update the links in customfields and for the tracking
		if ($this->data['PerId'])
		{
			$current = $this->data;
			$old = $this->read($this->data['PerId'], false);
			$this->data = $current;
		}
		// get fed_id from national federation, if fed_id is not given but nation
		if (!$this->data['fed_id'] && $this->data['nation'] &&
			($feds = $this->federations($this->data['nation'],true)))
		{
			$this->data['fed_id'] = key($feds);
		}
		$this->data['modifier'] = $GLOBALS['egw_info']['user']['account_id'];
		$this->data['modified'] = $this->now;

		if (!($err = parent::save()) && $this->data['fed_id'])
		{
			$set_fed = $this->set_federation($this->data['fed_id'],$this->data['a2f_start'],$this->data['PerId'],$this->data['acl_fed_id']);

			// send email notifications and do the history logging
			if (!is_object($this->tracking))
			{
				$this->tracking = new ranking_tracking($this);
			}

			$this->tracking->track($this->data,$old);

		}
		return $err;
	}

	/**
	 * get title for an athlete
	 *
	 * Is called as hook to participate in the linking. The format is determined by the link_title preference.
	 *
	 * @param int|string|array $athlete int/string id or array with athlete
	 * @param boolean $return_array true: array with values for 'id', 'label', 'title'
	 * @return string|boolean|array string with the title, null if contact does not exitst, false if no perms to view it
	 */
	function link_title($athlete, $return_array=false)
	{
		static $license_labels = null;
		if (!isset($license_labels)) $license_labels = ranking_bo::getInstance()->license_labels;

		if (!is_array($athlete) && $athlete)
		{
			$athlete = $this->read($athlete);
		}
		if (!is_array($athlete))
		{
			return $athlete;
		}
		$label = $athlete['nachname'].', '.$athlete['vorname'].' ('.$athlete['nation'].' '.$athlete['PerId'].')';

		return !$return_array ? $label : array(
			'id' => $athlete['PerId'],
			'label' => $label,
			'title' => ($athlete['geb_year'] ? (int)$athlete['geb_year'].': ' : '').
				($athlete['ort'] ? $athlete['ort'] : '').
				($athlete['ort'] && $athlete['verband'] ? ', ' : '').
				($athlete['verband'] ? $athlete['verband'] : '').
				', '.lang('License').' '.lang($license_labels[$athlete['license']]),
		);
	}

	/**
	 * get title for multiple contacts identified by $ids
	 *
	 * Is called as hook to participate in the linking. The format is determined by the link_title preference.
	 *
	 * @param array $ids array with contact-id's
	 * @return array with titles, see link_title
	 */
/*	function link_titles(array $ids)
	{
		$titles = array();
		if (($athletes =& $this->search(array('contact_id' => $ids),false)))
		{
			foreach($athletes as $athlete)
			{
				$titles[$athlete['id']] = $this->link_title($athlete);
			}
		}
		// we assume all not returned contacts are not readable for the user (as we report all deleted contacts to Link)
		foreach($ids as $id)
		{
			if (!isset($titles[$id]))
			{
				$titles[$id] = false;
			}
		}
		return $titles;
	}*/

	/**
	 * query db for athletes matching $pattern
	 *
	 * Is called as hook to participate in the linking
	 *
	 * @param string $pattern pattern to search
	 * @param array& $options =array() start, num_rows: limit search, on return total
	 *	GrpId: nummeric id of a category: return "error" attribute if athlete does NOT fullfils requirements for given cat
	 * @return array with id - title pairs of the matching entries
	 */
	function link_query($pattern, &$options=array())
	{
		$result = $criteria = $filter = array();
		foreach($options as $name => $value)
		{
			if (empty($value)) continue;
			if (isset($this->db_cols[$name]) ||
				in_array($name, array('license_nation', 'license_year')))
			{
				$filter[$name] = $value;
			}
			elseif(in_array($name, array('nation', 'fed_parent')))
			{
				$f = $this->db->expression(self::FEDERATIONS_TABLE, self::FEDERATIONS_TABLE.'.', array($name => $value));
				// for numeric ids / state federations also check SUI Regionalzentrum acl.fed_id
				if ($name == 'fed_parent' && is_numeric($value))
				{
					$f = '('.$f.' OR acl.fed_id='.(int)$value.')';
				}
				$filter[] = $f;
			}
		}
		if ($pattern)
		{
			// allow to prefix pattern with gender and nation, eg: "GER: Becker", "M: Becker" or "M: GER: Becker"
			$parts = null;
			preg_match_all('/(([^ :]+):?) */', $pattern, $parts);
			foreach($parts[2] as $n => $part)
			{
				if (substr($parts[1][$n], -1) != ':')
				{
					$criteria = implode(' ', array_slice($parts[1], $n));
					break;
				}
				if ($part[0] == '!')	// NOT id/license number
				{
					$filter[] = self::ATHLETE_TABLE.'.PerId != '.(int)substr($part,1);
				}
				// nation (can be 2 letters too: NC for New Caledonia, recogniced as NF by IFSC, but part of FRA for IOC!)
				elseif (preg_match('/^[A-Z]{2,3}$/i', $part))
				{
					$filter['nation'] = strtoupper($part);
				}
				else
				{
					$filter['sex'] = strtolower($part) == 'm' ? 'male' : 'female';
				}
				//error_log(__METHOD__."() pattern=$pattern, part=$part, filter=".array2string($filter));
			}
		}
		$this->columns_to_search = array('vorname', 'nachname', 'ort', 'verband', self::ATHLETE_TABLE.'.PerId');
		// cat and comp given to check registration requirements
		if ((int)$options['GrpId'] > 0 && (int)$options['WetId'] > 0)
		{
			$cat = $this->cats->read((int)$options['GrpId']);
			$comp = ranking_bo::getInstance ()->comp->read((int)$options['WetId']);
		}
		$start = isset($options['num_rows']) ? array((int)$options['start'], (int)$options['num_rows']) : false;
		if (($athletes = $this->search($criteria,false,'nachname,vorname,nation','','%',false,'AND',$start,$filter)))
		{
			foreach($athletes as $athlete)
			{
				if ($athlete['geb_year'])
				{
					$geb = $athlete['geb_year'];
					if ($athlete['geb_date'])
					{
						$geb = Api\DateTime::to($athlete['geb_date'], true);
					}
				}
				$result[$athlete['PerId']] = $this->link_title($athlete, true);

				if ($cat)
				{
					$result[$athlete['PerId']]['error'] = ranking_bo::getInstance()->error_register($athlete, $cat, $comp);
				}

				if ($options['license_nation'])
				{
					$result[$athlete['PerId']]['license'] = $athlete['license'];
				}
			}
		}
		$options['total'] = $this->total;
		//error_log(__METHOD__."('$pattern', ".array2string($options).' returning '.count($result).' results');
		return $result;
	}
}
ranking_athlete::init_static();
