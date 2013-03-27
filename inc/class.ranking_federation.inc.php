<?php
/**
 * EGroupware digital ROCK Rankings - federation storage object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2008-12 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$
 */

/**
 * Federation object
 */
class ranking_federation extends so_sql
{
	var $charset,$source_charset;
	const APPLICATION = 'ranking';
	const FEDERATIONS_TABLE = 'Federations';
	const ATHLETE2FED_TABLE = 'Athlete2Fed';
	/**
	 * Query the children of a federation
	 */
	const FEDERATION_CHILDREN = '(SELECT count(*) FROM Federations child WHERE child.fed_parent=Federations.fed_id)';
	const FEDERATION_ATHLETES = '(SELECT COUNT(*) FROM Athlete2Fed WHERE Athlete2Fed.fed_id=Federations.fed_id)';
	/**
	 * Contient values
	 */
	const EUROPE = 1;
	const ASIA = 2;
	const AMERICA = 4;
	const AFRICA = 8;
	const OCEANIA = 16;
	static $continents = array(
		self::EUROPE  => 'Europe',
		self::ASIA    => 'Asia',
		self::AMERICA => 'America',
		self::AFRICA  => 'Africa',
		self::OCEANIA => 'Oceania',
	);

	/**
	 * constructor of the federation class
	 */
	function __construct($source_charset='',$db=null)
	{
		$this->so_sql(self::APPLICATION,self::FEDERATIONS_TABLE,$db);   // call constructor of derived class

		if ($source_charset) $this->source_charset = $source_charset;

		$this->charset = $GLOBALS['egw']->translation->charset();
	}

	/**
	 * changes the data from your work-format to the db-format
	 *
	 * @param array $data if given works on that array and returns result, else works on internal data-array
	 */
	function data2db($data=null)
	{
		if (is_null($data))
		{
			$data = &$this->data;
		}
		if (isset($data['fed_url']) && $data['fed_url'] && substr($data['fed_url'],0,4) != 'http')
		{
			$data['fed_url'] = 'http://'.$data['fed_url'];
		}
		return parent::data2db($data);
	}

	/**
	 * Return a list of federation names indexed by fed_id, evtl. of a given nation only
	 *
	 * @param string $nation=null string to limit result to a given nation
	 * @param bool $only_direct_children=false true to return only direct children of given nation federation(s)
	 * @return array
	 */
	function federations($nation=null,$only_direct_children=false)
	{
		$feds = array();
		$where = $nation ? array('nation' => $nation) : array();
		if ($only_direct_children)
		{
			if ($nation)
			{
				$nations_feds = 'SELECT fed_id FROM '.self::FEDERATIONS_TABLE.' WHERE nation='.$this->db->quote($nation).' AND fed_parent IS NULL';
				$where[] = "fed_parent IN ($nations_feds)";
			}
			else
			{
				$where[] = "fed_parent IS NULL";
			}
		}
		foreach($this->db->select(self::FEDERATIONS_TABLE,'fed_id,verband,nation',$where,__LINE__,__FILE__,false,
			'ORDER BY nation ASC,verband ASC',self::APPLICATION) as $fed)
		{
			$feds[$fed['fed_id']] = (!$nation ? $fed['nation'].': ' : '').$fed['verband'];
		}
		return $feds;
	}

	/**
	 * searches db for rows matching searchcriteria
	 *
	 * '*' and '?' are replaced with sql-wildcards '%' and '_'
	 *
	 * For a union-query you call search for each query with $start=='UNION' and one more with only $order_by and $start set to run the union-query.
	 *
	 * @param array/string $criteria array of key and data cols, OR a SQL query (content for WHERE), fully quoted (!)
	 * @param boolean/string/array $only_keys=true True returns only keys, False returns all cols. or
	 *	comma seperated list or array of columns to return
	 * @param string $order_by='' fieldnames + {ASC|DESC} separated by colons ',', can also contain a GROUP BY (if it contains ORDER BY)
	 * @param string/array $extra_cols='' string or array of strings to be added to the SELECT, eg. "count(*) as num"
	 * @param string $wildcard='' appended befor and after each criteria
	 * @param boolean $empty=false False=empty criteria are ignored in query, True=empty have to be empty in row
	 * @param string $op='AND' defaults to 'AND', can be set to 'OR' too, then criteria's are OR'ed together
	 * @param mixed $start=false if != false, return only maxmatch rows begining with start, or array($start,$num), or 'UNION' for a part of a union query
	 * @param array $filter=null if set (!=null) col-data pairs, to be and-ed (!) into the query without wildcards
	 * @param string $join='' sql to do a join, added as is after the table-name, eg. "JOIN table2 ON x=y" or
	 *	"LEFT JOIN table2 ON (x=y AND z=o)", Note: there's no quoting done on $join, you are responsible for it!!!
	 * @param boolean $need_full_no_count=false If true an unlimited query is run to determine the total number of rows, default false
	 * @return boolean/array of matching rows (the row is an array of the cols) or False
	 */
	function &search($criteria,$only_keys=True,$order_by='',$extra_cols='',$wildcard='',$empty=False,$op='AND',$start=false,$filter=null,$join='')
	{
		if (!is_array($extra_cols))
		{
			$extra_cols = $extra_cols ? explode(',',$extra_cols) : array();
		}
		$extra_cols[] = self::FEDERATION_ATHLETES.' AS num_athletes';
		$extra_cols[] = self::FEDERATION_CHILDREN.' AS num_children';

		$order_by .= ($order_by ? ',' : '').'nation ASC,verband ASC';

		return parent::search($criteria,$only_keys,$order_by,$extra_cols,$wildcard,$empty,$op,$start,$filter,$join);
	}

	/**
	 * Apply (non-empty) data to given federations
	 *
	 * @param array $data data to merge (only non-empty fields)
	 * @param array $fed_ids federations to merge
	 * @param int|string number of federations modified or "none"
	 */
	function apply(array $data,array $fed_ids)
	{
		unset($data['fed_id']);		// to be on the save side
		unset($data['verband']);
		foreach((array)$data as $name => $value)
		{
			if ((string)$value == '') unset($data[$name]);
		}
		if (!$fed_ids || !$data)
		{
			//echo "<p>nothing to do: fed_ids=".array2string($fed_ids).", data=".array2string($data)."</p>\n";
			return lang('None');
		}
		$this->db->update(self::FEDERATIONS_TABLE,$data,array('fed_id' => $fed_ids),__LINE__,__FILE__,self::APPLICATION);

		return $this->db->affected_rows();
	}

	/**
	 * Merge selected federations into a specified one
	 *
	 * @param int $fed_id federation to merge into
	 * @param array $fed_ids federations to merge (can contain $fed_id)
	 * @param int|string number of federations modified or "none"
	 */
	function merge($fed_id,array $fed_ids)
	{
		if (($key = array_search($fed_id,(array)$fed_ids)) !== false)
		{
			unset($fed_ids[$key]);	// ignore $fed_id
		}
		if (!$fed_id || !$fed_ids)
		{
			//echo "<p>nothing to do: fed_id=$fed_id, fed_ids=".array2string($fed_ids)."</p>\n";
			return lang('None');
		}
		$this->db->update(self::ATHLETE2FED_TABLE,array('fed_id'=>$fed_id),array('fed_id'=>$fed_ids),__LINE__,__FILE__,self::APPLICATION);

		return $this->delete(array('fed_id' => $fed_ids));
	}

	/**
	 * Delete reimplemented to delete the grants in the ACL table too
	 *
	 * @param array $keys if given array with col => value pairs to characterise the rows to delete
	 * @return int affected rows, should be 1 if ok, 0 if an error
	 */
	function delete($keys=null)
	{
		if (!$keys) return 0;

		// get the fed_id's from the keys
		if (!is_array($keys))
		{
			$fed_ids = array($keys);
		}
		elseif(count($keys) == 1 && isset($keys['fed_id']))
		{
			$fed_ids = $keys['fed_id'];
		}
		elseif(($rows = $this->search($keys)))
		{
			foreach($rows as $row)
			{
				$fed_ids[] = $row['fed_id'];
			}
		}
		else
		{
			return 0;	// nothing to do
		}
		// delete the grants
		foreach($fed_ids as $fed_id)
		{
			$this->delete_grants($fed_id);
		}
		// todo: make all children top-level feds
		$this->db->update(self::FEDERATIONS_TABLE,array('fed_parent' => null),array('fed_id' => $fed_ids),__LINE__,__FILE__,self::APPLICATION);

		// delete all Athlete2Fed associations
		$this->db->delete(self::ATHLETE2FED_TABLE,array('fed_id' => $fed_ids),__LINE__,__FILE__,self::APPLICATION);

		// now let the parent delete the fed(s) itself
		return parent::delete($keys);
	}

	/**
	 * Return id or all fields of a federation specified by name and optional nation
	 *
	 * @param string $name
	 * @param string $nation=null
	 * @param boolean $id_only=false return only the integer id
	 * @return int|array|boolean integer id, array with all data or false if no federation is found
	 */
	function get_federation($name,$nation=null,$id_only=false)
	{
		$where = array('verband' => $name);
		if ($nation) $where['nation'] = $nation;

		$federation = $this->read($where);

		return $id_only && $federation ? $federation['fed_id'] : $federation;
	}

	/**
	 * Prefix to get an ACL location from a fed_id
	 */
	const ACL_LOCATION_PREFIX = '#';
	/**
	 * Available ACL grants of a federation
	 *
	 * Grants are valid for athlets of a federation including it's child federations!
	 *
	 * @var array
	 */
	static $grant_types = array(
		'athletes' => EGW_ACL_ATHLETE,	// edit athletes
		'register' => EGW_ACL_REGISTER,	// register athletes for national competitions
		'edit' => EGW_ACL_EDIT,		// edit competitions
		'read' => EGW_ACL_READ,		// read competitions
	);

	/**
	 * Read ACL grants of a federation
	 *
	 * @param int|string $fed_id=null default use fed_id (or nation, if no parent) of this object
	 * @return array
	 */
	function get_grants($fed_id=null)
	{
		if (is_null($fed_id))
		{
			$fed_id = $this->data['fed_parent'] ? $this->data['fed_id'] : $this->data['nation'];
		}
		$location = is_numeric($fed_id) ? self::ACL_LOCATION_PREFIX.$fed_id : $fed_id;

		foreach(self::$grant_types as $name => $right)
		{
			$grants[$name] = $GLOBALS['egw']->acl->get_ids_for_location($location,$right,self::APPLICATION);
		}
		//error_log(__METHOD__."('$fed_id') returning ".array2string($grants));
		return $grants;
	}

	/**
	 * Delete ACL grants of a federation
	 *
	 * @param int|string $fed_id=null default use fed_id (or nation, if no parent) of this object
	 */
	function delete_grants($fed_id=null)
	{
		if (is_null($fed_id))
		{
			$fed_id = $this->data['fed_parent'] ? $this->data['fed_id'] : $this->data['nation'];
		}
		//error_log(__METHOD__."('$fed_id')");
		$location = is_numeric($fed_id) ? self::ACL_LOCATION_PREFIX.$fed_id : $fed_id;

		$GLOBALS['egw']->acl->delete_repository(self::APPLICATION,$location,false);
	}

	/**
	 * Set ACL grants of a federation
	 *
	 * @param array $grants
	 * @param int|string $fed_id=null default use fed_id (or nation, if no parent) of this object
	 */
	function set_grants(array $grants,$fed_id=null)
	{
		if (is_null($fed_id))
		{
			$fed_id = $this->data['fed_parent'] ? $this->data['fed_id'] : $this->data['nation'];
		}
		//error_log(__METHOD__.'('.array2string($grants).",'$fed_id')");
		$location = is_numeric($fed_id) ? self::ACL_LOCATION_PREFIX.$fed_id : $fed_id;

		$this->delete_grants($fed_id);

		$accounts = array();
		foreach(self::$grant_types as $name => $right)
		{
			if (is_array($grants[$name])) $accounts = $accounts ? array_unique(array_merge($accounts,$grants[$name])) : $grants[$name];
		}
		foreach($accounts as $account)
		{
			$rights = 0;
			foreach(self::$grant_types as $name => $right)
			{
				if (in_array($account,$grants[$name])) $rights |= $right;
			}
			if ($rights)	// only write rights if there are some
			{
				//echo "$nation: $account = $rights<br>";
				$GLOBALS['egw']->acl->add_repository(self::APPLICATION,$location,$account,$rights);
			}
		}
	}

	/**
	 * Read federation ACL grants for the current user
	 *
	 * @return array with fed_id => rights pairs
	 */
	function get_user_grants()
	{
		static $grants;

		if (!is_null($grants)) return $grants;

		$grants = array();
		foreach($GLOBALS['egw']->acl->read() as $data)	// uses the users account and it's memberships
		{
			if ($data['appname'] != 'ranking' || $data['location'][0] != ranking_federation::ACL_LOCATION_PREFIX)
			{
				continue;
			}
			$grants[(int)substr($data['location'],1)] = $data['rights'];
		}
		// now include the direkt children (eg. sektionen from the landesverbände)
		if ($grants && ($children = $this->search(array('fed_parent' => array_keys($grants)),'fed_id,fed_parent')))
		{
			foreach($children as $child)
			{
				$grants[$child['fed_id']] = $grants[$child['fed_parent']];
			}
		}
		return $grants;
	}

	/**
	 * Get the nations the user has (at least one) federation grant for
	 *
	 */
	function get_user_nations()
	{
		static $nations;

		if (!is_null($nations)) return $nations;

		$nations = array();
		if (!($grants = $this->get_user_grants()))
		{
			return $nations;
		}
		foreach($this->db->select(self::FEDERATIONS_TABLE,'DISTINCT nation',array('fed_id' => array_keys($grants)),__LINE__,__FILE__) as $row)
		{
			$nations[] = $row['nation'];
		}
		return $nations;
	}

	/**
	 * Get the federations for a competition of a given nation, optinal limited to the one the user has registration rights for
	 *
	 * For international competitions we just return the nations, for national competitions we return
	 * fed_id => verband pairs of fed's which are the direct childs of the national federation
	 * PLUS the nations after them, to allow judges to register international participants
	 *
	 * @param string $nation 3-char nation code, null or NULL for international
	 * @param array $register_rights=null register_rights (array with 3-char nation codes) of current user
	 * 	to limit returned array to nations/federations the user as registration rights for, or null for all
	 * @return array with value => label pairs for a selectbox
	 */
	function get_competition_federations($nation,array $register_rights=null)
	{
		static $comp_feds;
		//echo "<p>get_competition_federations($nation,".array2string($register_rights).")</p>\n";
		if ($nation == 'NULL') $nation = null;
		if (is_null($comp_feds) && !($comp_feds = $GLOBALS['egw']->session->appsession('comp_feds','ranking'))) $comp_feds = array();

		if (!isset($comp_feds[(string)$nation]))
		{
			$feds = array();
			if ($nation)
			{
				$nations_feds = 'SELECT fed_id FROM '.self::FEDERATIONS_TABLE.' WHERE nation='.$this->db->quote($nation).' AND fed_parent IS NULL';
				foreach($this->search(array('nation' => $nation,"fed_parent IN ($nations_feds)"),'fed_id,verband','verband') as $fed)
				{
					$feds[$fed['fed_id']] = $nation.': '.$fed['verband'];
				}
				$feds[$nation] = $nation;	// show nation itself, directly under the national feds, above the international ones
			}
			foreach($this->search(array('fed_parent IS NULL'),'DISTINCT nation,verband,fed_nationname,fed_continent','nation') as $fed)
			{
				if ($fed['fed_continent'])	// remove all test feds
				{
					$feds[$fed['nation']] = $fed['nation'].': '.$fed['verband'].' ('.lang($fed['fed_nationname']).')';
				}
			}
			// store result in cache and cache in session, to not query it again from DB
			$comp_feds[(string)$nation] = $feds;
			$GLOBALS['egw']->session->appsession('comp_feds','ranking',$comp_feds);
		}
		else
		{
			$feds = $comp_feds[(string)$nation];
		}
		if (!is_null($register_rights))
		{
			foreach($this->get_user_grants() as $fed_id => $rights)
			{
				if ($rights & EGW_ACL_REGISTER)
				{
					$register_rights[$fed_id] = $fed_id;
				}
			}
			$feds = array_intersect_key($feds,array_flip($register_rights));
		}
		//_debug_array($feds);
		return $feds;
	}

	/**
	 * Get federation-contact information for a given athlete or fed_id's
	 *
	 * @param array $athlete_or_fed_ids athlete array (fed_id, fed_acl_id, fed_parent) or given array of fed_id's
	 * @param string $type='athletes'
	 * @return string html with comma-separated contact-names with mailto-links
	 */
	function get_contacts(array $athlete_or_fed_ids, $type='athletes')
	{
		if (isset($athlete_or_fed_ids['PerId']))
		{
			$fed_ids = array($athlete_or_fed_ids['fed_id'],$athlete_or_fed_ids['nation']);
			if ($athlete_or_fed_ids['fed_parent']) $fed_ids[] = $athlete_or_fed_ids['fed_parent'];
			if ($athlete_or_fed_ids['acl_fed_id']) $fed_ids[] = $athlete_or_fed_ids['acl_fed_id'];
		}
		else
		{
			$fed_ids = $athlete_or_fed_ids;
		}
		$contacts = array();
		foreach($fed_ids as $fed_id)
		{
			$grants = $this->get_grants($fed_id);
			if (!$grants[$type]) continue;
			//echo $fed_id; _debug_array($grants);
			foreach((array)$grants[$type] as $account_id)
			{
				if ($account_id < 0) $account_id = $GLOBALS['egw']->accounts->members($account_id,true);
				foreach((array)$account_id as $account_id)
				{
					if (($account = $GLOBALS['egw']->accounts->read($account_id)) && $account['account_email'])
					{
						//echo $account_id; _debug_array($account);
						$contacts[$account_id] = '<a href="mailto:'.$account['account_email'].'">'.
							$account['account_firstname'].' '.$account['account_lastname'].'</a>';
					}
				}
			}
		}
		return implode(", ", $contacts);
	}

	/**
	 * Get name of nation from 3 char shortcut
	 *
	 * @param string $nation
	 * @return string
	 */
	function get_nationname($nation)
	{
		static $nations;
		if (is_null($nations))
		{
			foreach($this->db->select($this->table_name, 'DISTINCT nation,fed_nationname', 'fed_parent IS NULL', __LINE__, __FILE__, false, '', 'ranking') as $row)
			{
				if (!isset($nations[$row['nation']]) || strlen($nations[$row['nation']]) < strlen($row['fed_nationname']))
				{
					$nations[$row['nation']] = $row['fed_nationname'];
				}
			}
		}
		//_debug_array($nations); exit;
		return $nations[$nation];
	}
}
