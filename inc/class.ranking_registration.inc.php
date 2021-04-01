<?php
/**
 * EGroupware digital ROCK Rankings - registration storage object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2016-19 by Ralf Becker <RalfBecker@digitalrock.de>
 */

use EGroupware\Api;

/**
 * registration storage object
 */
class ranking_registration extends Api\Storage\Base
{
	/**
	 * Table name for registration
	 */
	const TABLE = 'Registration';

	/**
	 * column prefix
	 */
	const PREFIX = 'reg_';

	/**
	 * State-names used together with PREFIX and ACCOUNT_POSTFIX as column-names
	 */
	const DELETED = 'deleted';
	const CONFIRMED = 'confirmed';
	const REGISTERED = 'registered';
	const PREQUALIFIED = 'prequalified';
	/**
	 * Array with available state names, sorted by most relevant first
	 *
	 * @var array
	 */
	static $states = array(
		self::DELETED, self::CONFIRMED, self::REGISTERED, self::PREQUALIFIED
	);
	const TO_CONFIRM = 'to-confirm';
	const ALL = 'all';
	const NOT_DELETED = '';

	/**
	 * State filters supported by search
	 *
	 * @var array
	 */
	static $state_filters = array(
		self::NOT_DELETED  => 'Not deleted',
		self::REGISTERED   => 'Registered',
		self::CONFIRMED    => 'Confirmed',
		self::TO_CONFIRM   => 'To confirm',
		self::PREQUALIFIED => 'Prequalified',
		self::DELETED      => 'Deleted',
		self::ALL          => 'All',
	);

	/**
	 * Postfix for modifier
	 */
	const ACCOUNT_POSTFIX = '_by';

	var $charset,$source_charset;

	/**
	 * constructor of the competition class
	 */
	function __construct($source_charset='',$db=null,$vfs_pdf_dir='')
	{
		unset($vfs_pdf_dir);	// not used, but required by function signature
		//$this->debug = 1;
		parent::__construct('ranking', self::TABLE, $db);	// call constructor of extended class

		if ($source_charset) $this->source_charset = $source_charset;

		$this->charset = Api\Translation::charset();
	}

	/**
	 * reads row matched by keys and puts all cols in the data array
	 *
	 * a) only WetId: List of cats with results for the given comp.
	 * b) WetId and GrpId: List of Athlets = result or startlist
	 * c) WetId, GrpId and PerId: result of a single athlet
	 * d) only PerId: List of results of a person
	 *
	 * @param array $keys array with keys in form internalName => value, may be a scalar value if only one key
	 * @param string|array $extra_cols string or array of strings to be added to the SELECT, eg. "count(*) as num"
	 * @param string|boolean $join =true true for the default join or sql to do a join, added as is after the table-name, eg. ", table2 WHERE x=y" or
	 * @param string $order ='' order clause or '' for the default order depending on the keys given
	 * @return array|boolean data if row could be retrived else False
	 */
	function read($keys,$extra_cols='',$join=true,$order='')
	{
		//echo "<p>result::read(".print_r($keys,true).",'$extra_cols','$join','$order')</p>\n";
		if ($order && !preg_match('/^[a-z_,. ]+$/i',$order)) $order = '';

		$filter = $keys;
		if (isset($filter['nation']))	// nation is from the joined athlete table, cant be quoted automatic
		{
			unset($filter['nation']);

			if ($join) $filter[] = $this->db->expression(ranking_athlete::FEDERATIONS_TABLE,array('nation' => $keys['nation']));

			unset($keys['nation']);
		}
		foreach(array('WetId','GrpId','PerId') as $key)
		{
			if ($keys[$key])
			{
				$this->$key = $keys[$key];
			}
			else
			{
				unset($keys[$key]);
			}
		}
		if ($this->WetId && !$this->PerId)	// read complete result of comp. WetId for cat GrpId or all cats
		{
			$cols = false;
			if ($join === true)
			{
				$join = 'JOIN '.ranking_athlete::ATHLETE_TABLE.' USING(PerId)'.ranking_athlete::FEDERATIONS_JOIN;

				if (!$extra_cols || is_string($extra_cols) && $extra_cols[0] === '+')
				{
					$extra_cols = "nachname,vorname,nation,ort,verband,fed_url,".ranking_athlete::FEDERATIONS_TABLE.
						".fed_id AS fed_id,fed_parent,acl.fed_id AS acl_fed_id,geb_date,acl,reg_id,email,".
						ranking_athlete::ATHLETE_TABLE.".PerId AS PerId".
						($extra_cols ? ','.substr($extra_cols, 1) : '');
				}
				else
				{
					$cols = $extra_cols;
					$extra_cols = '';
				}
			}
			if ($this->GrpId < 0) unset($filter['GrpId']);	// return all cats

			$ret = $athletes = $this->search(array(),$cols,$order,$extra_cols,'',false,'AND',false,$filter);

			// return 2-dim array by category and athlete
			if (!$this->GrpId)
			{
				$ret = array();
				foreach($athletes as $athlete)
				{
					$ret[$athlete['GrpId']][$athlete['PerId']] = $athlete;
				}
			}
			return $ret;
		}
		elseif($this->WetId && $this->PerId && !$this->GrpId)
		{
			return $this->search(array(), false,$order ? $order : self::TABLE.'.GrpId,reg_id,nachname,vorname',$extra_cols,'',false,'AND',false,$filter,$join);

		}
		// result of single person
		return parent::read($keys,$extra_cols,$join !== true ? $join : '');
	}

	/**
	 * changes the data from the db-format to our work-format
	 *
	 * We use athlete' db2data to observe it's ACL, as we join in athlete table!
	 *
	 * @param array $data if given works on that array and returns result, else works on internal data-array
	 * @return array
	 */
	function db2data($data=null)
	{
		static $athlete=null;
		if (!isset($athlete)) $athlete = ranking_bo::getInstance()->athlete;

		if (!is_array($data))
		{
			$data =& $this->data;
		}
		return $athlete->db2data($data);
	}

	/**
	 * changes the data from our work-format to the db-format
	 *
	 * @param array $data if given works on that array and returns result, else works on internal data-array
	 * @return array
	 */
	function data2db($data=null)
	{
		if (!is_array($data))
		{
			$data =& $this->data;
		}
		if (count($data) && $this->source_charset)
		{
			$data = Api\Translation::convert($data,$this->charset,$this->source_charset);
		}
		return $data;
	}

	/**
	 * Checks if there are any results incl. registrations or startlists for given keys
	 *
	 * @param array $keys with index WetId, PerId and/or GrpId
	 * @return boolean|int number of found registrations or false on error
	 */
	function has_registration($keys)
	{
		$keys[] = 'reg_registered IS NOT NULL AND reg_deleted IS NULL';

		return $this->db->select($this->table_name, 'COUNT(*)', $keys,
			__LINE__, __FILE__, false, '', 'ranking')->fetchColumn();
	}

	/**
	 * Check if there are prequalified athletes for given competition
	 *
	 * @param int $WetId
	 * @return int number of prequalified athletes or 1 for the marker from update_prequalified, if there are none
	 */
	function check_prequalified($WetId)
	{
		return $this->db->select($this->table_name, 'COUNT(*)', array(
			'WetId' => $WetId,
			'reg_prequalified IS NOT NULL AND reg_prequalified_by IS NULL'.
			// for 2016 we need to ignore already registered AND prequalified athletes
			(date('Y') == 2016 ? ' AND reg_registered IS NULL' : '')
		), __LINE__, __FILE__, false, '', 'ranking')->fetchColumn();
	}

	/**
	 * Add prequalified athletes for given competition
	 *
	 * If there are no prequalified athletes, we add a marker so check_prequalified finds something next time.
	 *
	 * @param int $WetId
	 * @param int& $deleted =null number of no longer prequalified and not registered and therefore deleted
	 * @param array& $unprequalified =null list by categoriy of registered athletes with no longer valid prequalification removed
	 * @param int& $changed =null number or changed/added prequalifications
	 * @return int false if there are no prequalified, or number of prequalified
	 */
	function update_prequalified($WetId, &$deleted=null, array &$unprequalified=null, &$changed=null)
	{
		$deleted = $changed = 0;
		$unprequalified = array();

		// check to update prequalified only after 1. Jan in the year of the competition
		// as ranking is not valid before and prequalifying competitons might not be updated
		if (!($comp = ranking_bo::getInstance()->comp->read($WetId)) ||
			(int)$comp['datum'] != date('Y'))
		{
			return false;
		}

		if (($prequalified = ranking_bo::getInstance()->prequalified($WetId)))
		{
			//error_log(__METHOD__."($WetId) prequalified=".array2string($prequalified));
			foreach($prequalified as $GrpId => $athletes)
			{
				foreach($athletes as $PerId => $reason)
				{
					$matches = null;
					// try updating first, in case is athlete is already registered or manually prequalified
					$this->db->update($this->table_name, array(
						'reg_prequalified' => time(),
						'reg_prequalified_by' => null,
						'reg_prequal_reason' => $reason,
					), array(
						'WetId' => $WetId,
						'GrpId' => $GrpId,
						'PerId' => $PerId,
						'reg_deleted IS NULL',
					), __LINE__, __FILE__, 'ranking');
					// if no row affected, insert it
					if (!$this->db->affected_rows())
					{
						$this->db->insert($this->table_name, array(
							'WetId' => $WetId,
							'GrpId' => $GrpId,
							'PerId' => $PerId,
							'reg_prequalified' => time(),
							'reg_prequal_reason' => $reason,
						), false, __LINE__, __FILE__, 'ranking');
					}
					elseif($this->db->Type === 'mysqli' &&
						preg_match_all ('/(\S[^:]+): (\d+)/', mysqli_info ($this->db->Link_ID), $matches))
					{
						$info = array_combine ($matches[1], $matches[2]);
						$changed += $info['Changed'];
					}
				}

				// remove prequalification from everyone no longer (automatic) prequalified
				$where = array(
					'WetId' => $WetId,
					'GrpId' => $GrpId,
					'reg_deleted IS NULL',
					'reg_prequalified IS NOT NULL',
					'reg_prequalified_by IS NULL',
				);
				if ($athletes)
				{
					$where[] = $this->db->expression($this->table_name, 'NOT '.$this->table_name.'.', array('PerId' => array_keys($athletes)));
				}
				// first delete everyone with no registration yet
				$this->db->update($this->table_name, array(
					'reg_prequalified' => null,
					'reg_prequal_reason' => 'Prequalification removed',
					'reg_deleted' => time(),
				), array_merge($where, array(
					'reg_registered IS NULL',
				)), __LINE__, __FILE__, 'ranking');
				$deleted += $this->db->affected_rows();

				// then query no longer prequalifed, but already registered ones
				if (($unqualified = $this->search(null, true, '', '', '', false, 'AND', false, $where, true)))
				{
					// generate a list with name and nation
					$unprequalified[$GrpId] = array_map(function($athlete)
					{
						return strtoupper($athlete['nachname']).' '.$athlete['vorname'].' ('.$athlete['nation'].')';
					}, $unqualified);

					// then remove just the prequalification from everyone already registered
					$this->db->update($this->table_name, array(
						'reg_prequalified' => null,
						'req_prequalified_reason' => 'Prequalification removed '.Api\DateTime::to('now', 'Y-m-d H:i:s'),
					), $where, __LINE__, __FILE__, 'ranking');
				}
			}
		}
		return count(call_user_func_array('array_merge', $prequalified));
	}

	/**
	 * searches db for rows matching searchcriteria
	 *
	 * '*' and '?' are replaced with sql-wildcards '%' and '_'
	 *
	 * @param array|string $criteria array of key and data cols, OR a SQL query (content for WHERE), fully quoted (!)
	 * @param boolean $only_keys True returns only keys, False returns all cols
	 * @param string $order_by fieldnames + {ASC|DESC} separated by colons ','
	 * @param string|array $extra_cols string or array of strings to be added to the SELECT, eg. "count(*) as num"
	 * @param string $wildcard appended befor and after each criteria
	 * @param boolean $empty False=empty criteria are ignored in query, True=empty have to be empty in row
	 * @param string $op defaults to 'AND', can be set to 'OR' too, then criteria's are OR'ed together
	 * @param int|boolean $start if != false, return only maxmatch rows begining with start
	 * @param array $filter if set (!=null) col-data pairs, to be and-ed (!) into the query without wildcards
	 * @param string $join ='' sql to do a join, added as is after the table-name, eg. ", table2 WHERE x=y" or
	 *	"LEFT JOIN table2 ON (x=y)", Note: there's no quoting done on $join!
	 * @return array of matching rows (the row is an array of the cols) or False
	 */
	function &search($criteria,$only_keys=True,$order_by='',$extra_cols='',$wildcard='',$empty=False,$op='AND',$start=false,$filter=null,$join=true,$need_full_no_count=false)
	{
		// we require either a competition or reg_id's
		if (empty($filter['WetId']) && empty($filter['reg_id']))
		{
			$this->total = 0;
			$ret = [];
			return $ret;
		}
		switch($filter['state'])
		{
			case self::ALL:
				break;	// nothing to do
			case self::DELETED:
				$filter[] = self::PREFIX.self::DELETED.' IS NOT NULL';
				break;
			case self::CONFIRMED:
				$filter[] = '('.self::PREFIX.self::DELETED.' IS NULL AND '.self::PREFIX.self::CONFIRMED.' IS NOT NULL)';
				break;
			case self::TO_CONFIRM:
				$filter[] = '('.self::PREFIX.self::DELETED.' IS NULL AND '.
					self::PREFIX.self::REGISTERED.' IS NOT NULL AND '.self::PREFIX.self::CONFIRMED.' IS NULL)';
				break;
			case self::REGISTERED:
				$filter[] = '('.self::PREFIX.self::DELETED.' IS NULL AND '.self::PREFIX.self::REGISTERED.' IS NOT NULL)';
				break;
			case self::PREQUALIFIED:
				$filter[] = '('.self::PREFIX.self::DELETED.' IS NULL AND '.self::PREFIX.self::PREQUALIFIED.' IS NOT NULL)';
				break;
			default:
				$filter[] = self::PREFIX.self::DELETED.' IS NULL';
				break;
		}
		// check if we need to generate prequalified athletes
		if (isset($filter['state']) && $filter['state'] != self::REGISTERED &&
			$this->check_prequalified($filter['WetId']) === 0)
		{
			$this->update_prequalified($filter['WetId']);
		}
		unset($filter['state']);

		// remove "reg::" prefix from our artificial "id" column
		if (isset($filter['id']))
		{
			$filter['reg_id'] = $filter['id'];
			array_walk($filter['reg_id'], function(&$val)
			{
				list(,$val) = explode('::', $val);
			});
			unset($filter['id']);
		}

		if ($join === true)
		{
			$join = 'JOIN '.ranking_athlete::ATHLETE_TABLE.' USING(PerId)'.ranking_athlete::FEDERATIONS_JOIN;

			// use fed_id, if there is no fed_parent or a German region
			$ger_regions = implode(',', array_keys(ranking_bo::getInstance()->federation->regions('GER')));
			$fed_parent = "CASE WHEN fed_parent IS NULL OR fed_parent IN ($ger_regions) THEN Federations.fed_id ELSE fed_parent END";
			$extra_cols = str_replace('fed_parent', $fed_parent.' AS fed_parent',
				array_merge($extra_cols ? explode(',', $extra_cols) : array(),
					explode(',', "nachname,vorname,sex,".ranking_athlete::FEDERATIONS_TABLE.".nation AS nation,ort,verband,fed_url,".ranking_athlete::FEDERATIONS_TABLE.
					".fed_id AS fed_id,fed_parent,acl.fed_id AS acl_fed_id,geb_date,acl,".
					ranking_athlete::ATHLETE_TABLE.".PerId AS PerId")));
			$order_by = str_replace('fed_parent', $fed_parent, $order_by);

			if (isset($filter['license_nation']))
			{
				$join .= ranking_bo::getInstance()->athlete->license_join($filter['license_nation'], $filter['license_year']);
				$extra_cols[] = 'lic_status AS license';
				$extra_cols[] = 'l.GrpId AS license_cat';
				unset($filter['license_nation'], $filter['license_year']);
			}
			foreach($filter as $col => $val)
			{
				if (is_int($col)) continue;

				if ($col == 'nation' || $col == 'fed_parent')	// nation is from the joined Federations table
				{
					if ($val)
					{
						$f = $this->db->expression(ranking_athlete::FEDERATIONS_TABLE,array(
							!is_numeric($val) ? 'nation' : 'fed_parent' => $val,
						));
						// for numeric ids / state federations also check SUI Regionalzentrum acl.fed_id
						if (is_numeric($val))
						{
							$f = '('.$f.$this->db->expression(ranking_athlete::FEDERATIONS_TABLE,
								' OR '.ranking_athlete::FEDERATIONS_TABLE.'.', ['fed_id' => $val],
								' OR acl.', ['fed_id' => $val]).')';
						}
						$filter[] = $f;
					}
					unset($filter[$col]);
				}
				elseif (!isset($this->db_cols[$col]))	// assume it's from joined Athletes table
				{
					if ($val) $filter[] = $this->db->expression(ranking_athlete::ATHLETE_TABLE,array($col => $val));
					unset($filter[$col]);
				}
			}
		}
		if (($rows = parent::search($criteria,$only_keys,$order_by,$extra_cols,$wildcard,$empty,$op,$start,$filter,$join)))
		{
			foreach($rows as &$row)
			{
				// add one of "is(Deleted|Confirmed|Registered|Preregisted)" classes
				foreach(ranking_registration::$states as $state)
				{
					if (!empty($row[ranking_registration::PREFIX.$state]))
					{
						if (!isset($row['state']))
						{
							$row['class'] .= ' is'.ucfirst($state);
							$row['state'] = $state;
						}
						// we allways want prequalified class, to show registered ones also as prequalified (italics)
						elseif($state == 'prequalified')
						{
							$row['class'] .= ' is'.ucfirst($state);
						}
						$modifier = $row[ranking_registration::PREFIX.$state.ranking_registration::ACCOUNT_POSTFIX];
						$row['state_changed'] .= Api\DateTime::to($row[ranking_registration::PREFIX.$state]).': '.lang($state).' '.
							($modifier ? lang('by').' '.Api\Accounts::username($modifier).' ' : '')."\n".
							($state == 'prequalified' ? $row['reg_prequal_reason'] : '');
					}
				}
				// always add prequal reason, as it contains also a note when it got removed
				if (empty($row[ranking_registration::PREFIX.ranking_registration::PREQUALIFIED]) && !empty($row['reg_prequal_reason']))
				{
					$row['state_changed'] .= "\n".$row['reg_prequal_reason'];
				}
			}
		}
		return $rows;
	}

	/**
	 * Merge the registrations from athlete $from to athlete $to
	 *
	 * @param int $from
	 * @param int $to
	 * @return int number of merged results
	 */
	function merge($from,$to)
	{
		if (!(int)$from || !(int)$to)
		{
			return false;
		}
		$this->db->update(self::TABLE, array('PerId'=>$to), array('PerId'=>$from), __LINE__, __FILE__, 'ranking');

		return $this->db->affected_rows();
	}

	/**
	 * Check the status / existance of (not deleted) registration for all categories of given competitions
	 *
	 * @param int|array $comps
	 * @param array $status =array() evtl. set registration
	 * @return array of WetId => GrpId => status: 4=starters / registration
	 */
	function registration_status($comps, $status=array())
	{
		foreach($this->db->select(self::TABLE,'WetId,GrpId',array('WetId' => $comps,'reg_registered IS NOT NULL AND reg_deleted IS NULL'),
			__LINE__, __FILE__, false, 'GROUP BY WetId,GrpId', 'ranking') as $row)
		{
			$status[$row['WetId']][$row['GrpId']] = 4;
		}
		return $status;
	}
}