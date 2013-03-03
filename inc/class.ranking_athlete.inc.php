<?php
/**
 * EGroupware digital ROCK Rankings - athlete storage object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2006-13 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$
 */

/**
 * Athlete object
 */
class ranking_athlete extends so_sql
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
	const ACL_DENY_PROFILE = 128;

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
	 * @var category
	 */
	var $cats;

	var $picture_url = '/jpgs';
	var $picture_path = '../../../jpgs';
	var $acl2clear = array(
		self::ACL_DENY_BIRTHDAY  => array('geb_date'),
		self::ACL_DENY_EMAIL     => array('email'),
		self::ACL_DENY_PHONE     => array('tel'),
		self::ACL_DENY_FAX       => array('fax'),
		self::ACL_DENY_CELLPHONE => array('mobil'),
		self::ACL_DENY_STREET    => array('strasse','plz'),
		self::ACL_DENY_CITY      => array('ort'),
		self::ACL_DENY_PROFILE   => array('!','PerId','rkey','vorname','nachname','sex','nation','verband','license','acl','last_comp',
			'platz','pkt','WetId','GrpId'),		// otherwise they get no points in the ranking!
	);
	/**
	 * year we check the license for
	 *
	 * @var int
	 */
	var $license_year;
	/**
	 * Reference to the boranking instance
	 *
	 * @var boranking
	 */
	var $boranking;

	/**
	 * Timestaps that need to be adjusted to user-time on reading or saving
	 *
	 * @var array
	 */
	var $timestamps = array('modified','recover_pw_time','last_login');

	/**
	 * Initialise the static vars of this class, called by including the class
	 */
	static function init_static()
	{
		self::$fed_cols = array(
			self::FEDERATIONS_TABLE.'.nation AS nation',
			self::FEDERATIONS_TABLE.'.verband AS verband',
			self::FEDERATIONS_TABLE.'.fed_id AS fed_id',
//			self::FEDERATIONS_TABLE.'.fed_parent AS fed_parent',
			'CASE WHEN acl.fed_id IS NULL THEN '.self::FEDERATIONS_TABLE.'.fed_parent ELSE acl.fed_id END AS fed_parent',
		);
		self::$a2f_cols = array(
//			self::ATHLETE2FED_TABLE.'.a2f_start AS a2f_start',
//			self::ATHLETE2FED_TABLE.'.a2f_end AS a2f_end',
			'a2f.a2f_start AS a2f_start',
			'a2f.a2f_end AS a2f_end',
			'acl.fed_id AS acl_fed_id',
		);
	}

	/**
	 * constructor of the athlete class
	 */
	function __construct($source_charset='',$db=null)
	{
		if (is_null($db))
		{
			$db = ranking_so::get_rang_db();
		}
		parent::__construct('ranking',self::ATHLETE_TABLE,$db);	// call constructor of derived class

		if ($source_charset) $this->source_charset = $source_charset;

		$this->charset = translation::charset();

		foreach(array(
				'cats'  => 'category',
			) as $var => $class)
		{
			$egw_name = /*'ranking_'.*/$class;
			if (!isset($GLOBALS['egw']->$egw_name))
			{
				$GLOBALS['egw']->$egw_name = CreateObject('ranking.'.$class,$source_charset,$this->db,$vfs_pdf_dir);
			}
			$this->$var =& $GLOBALS['egw']->$egw_name;
		}
		$this->picture_path = $_SERVER['DOCUMENT_ROOT'].'/jpgs';

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
			$data = translation::convert($data,$this->source_charset);
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
		if ($data['acl'])
		{
			$acl = $data['acl'];
			$data['acl'] = array();
			for($i = $n = 1; $i <= 16; ++$i, $n <<= 1)
			{
				if ($acl & $n) $data['acl'][] = $n;
			}
			// echo "<p>ranking_athlete::db2data($data[nachname], $data[vorname]) acl=$acl=".print_r($data['acl'],true)."</p>\n";

			// blank out the acl'ed fields, if user has no athletes rights
			if (is_object($GLOBALS['boranking']) && !$GLOBALS['boranking']->acl_check_athlete($data))
			{
				foreach($this->acl2clear as $deny => $to_clear)
				{
					if ($acl & $deny)
					{
						foreach($to_clear[0] == '!' ? array_diff(array_keys($data),$to_clear) : $to_clear as $name)
						{
							$data[$name] = $name == 'geb_date' && $data['geb_date'] ? (int)$data['geb_date'] : '';
						}
					}
				}
			}
		}
		if (array_key_exists('license',$data) && !$data['license'])
		{
			$data['license'] = 'n';
		}
		return parent::db2data($intern ? null : $data);	// important to use null, if $intern!
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
		if (count($data) && $this->source_charset)
		{
			$data = translation::convert($data,$this->charset,$this->source_charset);
		}
		return parent::data2db($intern ? null : $data);	// important to use null, if $intern!
	}

	function init($arr=array())
	{
		parent::init($arr);

		// switching everything off, but the city
		$this->data['acl'] = array(
			self::ACL_DENY_BIRTHDAY,
			self::ACL_DENY_CELLPHONE,
			self::ACL_DENY_EMAIL,
			self::ACL_DENY_FAX,
			self::ACL_DENY_PHONE,
			self::ACL_DENY_STREET,
		);
	}

	/**
	 * Search for athletes, joins by default with the Result table to show the last competition and to filter by a category
	 *
	 * '*' and '?' are replaced with sql-wildcards '%' and '_'
	 *
	 * @param array/string $criteria array of key and data cols, OR a SQL query (content for WHERE), fully quoted (!)
	 * @param boolean $only_keys=true True returns only keys, False returns all cols
	 * @param string $order_by='' fieldnames + {ASC|DESC} separated by colons ',', can also contain a GROUP BY (if it contains ORDER BY)
	 * @param string/array $extra_cols='' string or array of strings to be added to the SELECT, eg. "count(*) as num"
	 * @param string $wildcard='' appended befor and after each criteria
	 * @param boolean $empty=false False=empty criteria are ignored in query, True=empty have to be empty in row
	 * @param string $op='AND' defaults to 'AND', can be set to 'OR' too, then criteria's are OR'ed together
	 * @param mixed $start=false if != false, return only maxmatch rows begining with start, or array($start,$num)
	 * @param array $filter=null if set (!=null) col-data pairs, to be and-ed (!) into the query without wildcards
	 * @param mixed $join=true sql to do a join (as so_sql::search), default true = add join for latest result
	 *	if numeric a special join is added to only return athlets competed in the given category (GrpId).
	 * @return array of matching rows (the row is an array of the cols) or False
	 */
	function &search($criteria,$only_keys=True,$order_by='',$extra_cols='',$wildcard='',$empty=False,$op='AND',$start=false,$filter=null,$join=true)
	{
		//echo "<p>ranking_athlete::search(".print_r($criteria,true).",'$only_keys','$order_by','$extra_cols','$wildcard','$empty','$op','$start',".print_r($filter,true).",'$join')</p>\n";

		if ($only_keys === true) $only_keys = self::ATHLETE_TABLE.'.PerId';

		if ($join === true || is_numeric($join))
		{
			$cat = $join;
			$join = '';
		}
		if ($extra_cols) $extra_cols = explode(',',$extra_cols);

		// join in nation & federation
		$join .= self::FEDERATIONS_JOIN;
		if (!is_array($extra_cols)) $extra_cols = $extra_cols ? explode(',',$extra_cols) : array();
		$extra_cols = array_merge($extra_cols,self::$fed_cols,self::$a2f_cols);

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
					$f = '('.$f.' OR '.str_replace('a2f.fed_id','acl.fed_id',$f).' OR fed_parent='.(int)$filter[$name].')';
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
			$join .= ' LEFT JOIN '.self::LICENSE_TABLE.' l ON l.PerId='.self::ATHLETE_TABLE.'.PerId AND lic_year='.(int)$license_year.' AND l.nation='.
				$this->db->quote(!$license_nation || $license_nation == 'NULL' ? '' : $license_nation);
			$extra_cols[] = 'lic_status AS license';
			$extra_cols[] = 'l.GrpId AS license_cat';
			if ($filter['license'])
			{
				$filter[] = !$filter['license'] ? 'lic_status IS NULL' :
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
		return so_sql::search($criteria,$only_keys,$order_by,$extra_cols,$wildcard,$empty,$op,$start,$filter,$join);
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

		static $cache;
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
			'7bit',translation::charset());

		$rkey = $data['rkey'];
		$n = 0;
		while ($this->not_unique() != 0)
		{
			$data['rkey'] = $rkey.$n++;
		}
		return $data['rkey'];
	}

	/**
	 * checks if an athlete already has results or a result-service result recorded
	 *
	 * @param int/array $keys PerId or array with keys of the athlete to check, default null = use keys in data
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

		return $this->db->select('Results','COUNT(*)',array('PerId' => $PerId,'platz > 0'),__LINE__,__FILE__)->fetchColumn() ||
			$this->db->select('RouteResults','COUNT(*)',array('PerId' => $PerId),__LINE__,__FILE__)->fetchColumn();
	}

	/**
	 * get the path of the picture
	 *
	 * @param string $rkey=null rkey of the athlete or null to use this->data[rkey]
	 * @return string/boolean the path or false if the rkey is empty (NOT if the picture does NOT exist!)
	 */
	function picture_path($rkey=null)
	{
		if (is_null($rkey)) $rkey = $this->data['rkey'];

		return $rkey ? $this->picture_path.'/'.$rkey.'.jpg' : false;
	}

	/**
	 * attach a picture to the athlete
	 *
	 * @param string $fname filename of the picture to attach
	 * @param string $rkey=null rkey of the athlete or null to use this->data[rkey]
	 * @return boolean true on success, flase otherwise
	 */
	function attach_picture($fname,$rkey=null)
	{
		$path = $this->picture_path($rkey);

		if (!$path || !file_exists($fname) || !is_readable($fname) || !is_writeable($this->picture_path)) return false;

		if (file_exists($path)) @unlink($path);

		return copy($fname,$path);
	}

	/**
	 * get the url of the picture
	 *
	 * @param string $rkey=null rkey of the athlete or null to use this->data[rkey]
	 * @return string/boolean the url or false if picture does not exist
	 */
	function picture_url($rkey = null)
	{
		if (is_null($rkey)) $rkey = $this->data['rkey'];

		$url = $this->picture_url.'/'.$this->data['rkey'].'.jpg';
		$path = $this->picture_path($rkey);

		return file_exists($path) && is_readable($path) ? $url : false;
	}

	/**
	 * delete the picture
	 *
	 * @return boolean true on success, false otherwise
	 */
	function delete_picture()
	{
		return @delete($this->picture_path());
	}

	/**
	 * deletes athlete(s), see so_sql
	 *
	 * reimplemented to delete the picture too, works only if one athlete (specified by rkey or internal data) is deleted!
	 *
	 * @param array $keys=null see so_sql
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
		}
		return $Ok;
	}

	/**
	 * reads row matched by key and puts all cols in the data array
	 *
	 * @param array $keys array with keys in form internalName => value, may be a scalar value if only one key
	 * @param string/array $extra_cols string or array of strings to be added to the SELECT, eg. "count(*) as num"
	 * @param string $join numeric year, adds a license-column or sql to do a join, see so_sql::read()
	 * @return array/boolean data if row could be retrived else False
	 */
	function read($keys,$extra_cols='',$join='',$nation=null)
	{
		//echo "<p>".__METHOD__."(".array2string($keys).",$extra_cols,$join,$nation)</p>\n";
		if (is_numeric($year = $join))
		{
			$join = '';
		}
		// join in the federation information
		$join .= self::FEDERATIONS_JOIN;
		if (!is_array($extra_cols)) $extra_cols = $extra_cols ? explode(',',$extra_cols) : array();
		$extra_cols = array_merge($extra_cols,self::$fed_cols,self::$a2f_cols);

		if (is_numeric($year))
		{
			$join .= ' LEFT JOIN '.self::LICENSE_TABLE.' ON '.self::LICENSE_TABLE.".PerId=$this->table_name.PerId AND lic_year=".
				(int)$year.' AND '.self::LICENSE_TABLE.'.nation='.$this->db->quote(!$nation || $nation == 'NULL' ? '' : $nation);
			$extra_cols[] = 'lic_status AS license';
			$extra_cols[] = self::LICENSE_TABLE.'.GrpId as license_cat';
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
	 * @param int $PerId=null else use $this->data[PerId]
	 * @return string|boolean false on wrong parameter or string with license status ('n' for no license)
	 */
	function get_license($year,$nation,$PerId=null)
	{
		if (!(int)$year) return false;

		if ($nation == 'NULL') $nation = null;

		if (!($status = $this->db->select(self::LICENSE_TABLE,'lic_status',array(
			'PerId' => is_null($PerId) ? $this->data['PerId'] : $PerId,
			'nation' => (string)$nation,
			'lic_year' => $year,
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
	 * @param int $PerId=null else use $this->data[PerId]
	 * @param string $nation=null nation for a national license, or null for an international one
	 * @param int $GrpId=null category to apply for
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
			'lic_year' => $year,
		);
		if (!in_array($status,array('a','c','s'))/* || $status == 'n'*/)
		{
			$this->db->delete(self::LICENSE_TABLE,$where,__LINE__,__FILE__,'ranking');
		}
		else
		{
			// add fake result to find athlete in category
			$this->db->insert('Results', array(
				'platz' => 0,
				'pkt' => 0,
			),array(
				'PerId' => $PerId,
				'GrpId' => $GrpId,
				'WetId' => 0,
			), __LINE__, __FILE__, 'ranking');

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
				'GrpId' => (int)$GrpId ? $GrpId : null,
			);
			/*if($this->db->select(self::LICENSE_TABLE,'PerId',$where,__LINE__,__FILE__,false,'','ranking')->fetch())
			{
				$this->db->update(self::LICENSE_TABLE,$data,$where,__LINE__,__FILE__,'ranking');
			}
			else*/
			{
				$this->db->insert(self::LICENSE_TABLE,$data,$where,__LINE__,__FILE__,'ranking');
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
	 * @param string $nation=null
	 * @param boolean $only_national=false if true return only national federations (fed_parent=NULL)
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
	 * @param int $PerId=null default current athlete
	 * @param int $fed_parent=null explicit fed_id of parent (0 deletes)
	 * @return true if federation is set (or was already), false on error
	 */
	function set_federation($fed_id,&$a2f_start,$PerId=null,$fed_parent=null)
	{
		if (is_null($PerId)) $PerId = $this->data['PerId'];
		//echo "<p>".__METHOD__."($fed_id,$a2f_start,$PerId,$fed_parent)</p>\n";
		if (!$PerId || !$fed_id) return false;

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
			return true;
		}
		elseif($a2f_start && $a2f_start > $fed['a2f_start']) 	// federation changed and (valid) start given --> record old one
		{
			if (!($a2f_start > 2000)) $a2f_start = (int)date('Y');

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
	 * @param string|array $extra_where=null extra where clause, eg. to check an etag, returns true if no affected rows!
	 * @return int|boolean 0 on success, or errno != 0 on error, or true if $extra_where is given and no rows affected
	 */
	function save($keys=null,$extra_where=null)
	{
		if (is_array($keys) && count($keys)) $this->data_merge($keys);

		// get fed_id from national federation, if fed_id is not given but nation
		if (!$this->data['fed_id'] && $this->data['nation'] &&
			($feds = $this->federations($this->data['nation'],true)))
		{
			list($this->data['fed_id']) = each($feds);
		}
		$this->data['modifier'] = $GLOBALS['egw_info']['user']['account_id'];
		$this->data['modified'] = $this->now;

		if (!($err = parent::save()) && $this->data['fed_id'])
		{
			$this->set_federation($this->data['fed_id'],$this->data['a2f_start'],$this->data['PerId'],$this->data['acl_fed_id']);
		}
		return $err;
	}

	/**
	 * get title for an athlete
	 *
	 * Is called as hook to participate in the linking. The format is determined by the link_title preference.
	 *
	 * @param int/string/array $athlete int/string id or array with athlete
	 * @return string/boolean string with the title, null if contact does not exitst, false if no perms to view it
	 */
	function link_title($athlete)
	{
		if (!is_array($athlete) && $athlete)
		{
			$athlete = $this->read($athlete);
		}
		if (!is_array($athlete))
		{
			return $athlete;
		}
		return $athlete['nachname'].', '.$athlete['vorname'].' ('.$athlete['nation'].' '.$athlete['PerId'].')';
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
		// we assume all not returned contacts are not readable for the user (as we report all deleted contacts to egw_link)
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
	 * @return array with id - title pairs of the matching entries
	 */
	function link_query($pattern)
	{
		$result = $criteria = array();
		if ($pattern)
		{
			// allow to prefix pattern with gender and nation, eg: "GER: Becker", "M: Becker" or "M: GER: Becker"
			if (strpos($pattern,':') !== false)
			{
				$parts = preg_split('/: ?/',$pattern);
				$pattern = array_pop($parts);
				foreach($parts as $part)
				{
					if ($part[0] == '!')	// NOT id/license number
					{
						$filter[] = self::ATHLETE_TABLE.'.PerId != '.(int)substr($part,1);
					}
					elseif (strlen($part) == 3)	// nation
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
			foreach(array('vorname','nachname','ort','verband') as $col)
			{
				$criteria[$col] = $pattern;
			}
		}
		if (($athletes = $this->search($criteria,false,'nachname,vorname,nation','','%',false,'OR',false,$filter)))
		{
			foreach($athletes as $athlete)
			{
				if ($athlete['geb_year'])
				{
					$geb = $athlete['geb_year'];
					if ($athlete['geb_date'])
					{
						$geb = explode('-',$athlete['geb_date']);
						$geb = $GLOBALS['egw']->common->dateformatorder($geb[0],$geb[1],$geb[2],true);
					}
				}
				$result[$athlete['PerId']] = array(
					'label' => $this->link_title($athlete),
					'title' => ($athlete['geb_year'] ? $geb.': ' : '').
						($athlete['ort'] ? $athlete['ort'] : '').
						($athlete['ort'] && $athlete['verband'] ? ', ' : '').
						($athlete['verband'] ? $athlete['verband'] : ''),
				);
			}
		}
		return $result;
	}
}
ranking_athlete::init_static();
