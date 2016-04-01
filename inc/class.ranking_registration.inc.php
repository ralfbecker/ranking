<?php
/**
 * EGroupware digital ROCK Rankings - registration storage object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2016 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$
 */

/**
 * registration storage object
 */
class ranking_registration extends so_sql
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

		$this->charset = translation::charset();
	}

	/**
	 * changes the data from the db-format to our work-format
	 *
	 * @param array $data if given works on that array and returns result, else works on internal data-array
	 * @return array
	 */
	function athlete_db2data($data=null)
	{
		static $athlete=null;
		if (!isset($athlete)) $athlete = ranking_bo::getInstance()->athlete;

		return $athlete->db2data($data);
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
	function &read($keys,$extra_cols='',$join=true,$order='')
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

				if (!$extra_cols)
				{
					$extra_cols = "nachname,vorname,nation,ort,verband,fed_url,".ranking_athlete::FEDERATIONS_TABLE.
						".fed_id AS fed_id,fed_parent,acl.fed_id AS acl_fed_id,geb_date,reg_id,".
						ranking_athlete::ATHLETE_TABLE.".PerId AS PerId";
				}
				else
				{
					$cols = $extra_cols;
					$extra_cols = '';
				}
			}
			if ($this->GrpId < 0) unset($filter['GrpId']);	// return all cats

			$ret = $athletes = $this->search(array(),$cols,false,$extra_cols,'',false,'AND',false,$filter);

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
			return $this->search(array(),$cols,$order ? $order : self::TABLE.'.GrpId,reg_id,nachname,vorname',$extra_cols,'',false,'AND',false,$filter,$join);

		}
		// result of single person
		return parent::read($keys,$extra_cols,$join !== true ? $join : '');
	}
/*
	function &comps_with_startlist($keys=array(),$registration_too=true)
	{
		//echo "<p>result::comps_with_startlist(".print_r($keys,true).")</p>\n";
		$keys['platz'] = 0;

		if ($registration_too)
		{
			$keys[] = $this->comp_table.'.datum >= '.$this->db->quote(date('Y-m-d'));
		}
		else
		{
			$keys[] = 'pkt > 64';
		}
		// nation is from the joined comp_table, it cant be quoted automatic
		if ($keys['nation'] == 'NULL') $keys['nation'] = null;
		$keys[] = $this->db->expression($this->comp_table,array('nation' => $keys['nation']));
		unset($keys['nation']);

		$comps = array();
		foreach ((array)$this->search(array(),"DISTINCT $this->table_name.WetId AS WetId",$this->comp_table.'.datum DESC','','',false,'AND',false,$keys,
			", $this->comp_table WHERE $this->table_name.WetId=$this->comp_table.WetId") as $row)
		{
			$comps[] = $row['WetId'];
		}
		return $comps;
	}
*/
	/**
	 * changes the data from the db-format to our work-format
	 *
	 * @param array $data if given works on that array and returns result, else works on internal data-array
	 * @return array
	 */
	function db2data($data=null)
	{
		if (!is_array($data))
		{
			$data =& $this->data;
		}
		if (count($data) && $this->source_charset)
		{
			$data = translation::convert($data,$this->source_charset);
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
		if (!is_array($data))
		{
			$data =& $this->data;
		}
		if (count($data) && $this->source_charset)
		{
			$data = translation::convert($data,$this->charset,$this->source_charset);
		}
		return $data;
	}

	/**
	 * Checks if there are any results (platz > 0) for the given keys
	 *
	 * @param array $keys with index WetId, PerId and/or GrpId
	 * @return boolean/int number of found results or false on error
	 *
	function has_results($keys)
	{
		$keys[] = 'platz > 0';

		if ($keys['GrpId'] < 0) unset($keys['GrpId']);	// < 0 means all

		return $this->db->select($this->table_name,'count(*)',$keys,__LINE__,__FILE__)->fetchColumn();
	}*/

	/**
	 * Checks if there are any startnumbers (platz == 0 && pkt > 64) for the given keys
	 *
	 * @param array $keys with index WetId, PerId and/or GrpId
	 * @return boolean/int number of found results or false on error
	 *
	function has_startlist($keys)
	{
		$keys[] = 'platz = 0 AND pkt >= 64';

		if ($keys['GrpId'] < 0) unset($keys['GrpId']);	// < 0 means all

		return $this->db->select($this->table_name,'count(*)',$keys,__LINE__,__FILE__)->fetchColumn();
	}*/

	/**
	 * Checks if there are any results incl. registrations or startlists for given keys
	 *
	 * @param array $keys with index WetId, PerId and/or GrpId
	 * @return boolean/int number of found registrations or false on error
	 */
	function has_registration($keys)
	{
		return $this->db->select($this->table_name,'count(*)',$keys,__LINE__,__FILE__)->fetchColumn();
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
	function search($criteria,$only_keys=True,$order_by='',$extra_cols='',$wildcard='',$empty=False,$op='AND',$start=false,$filter=null,$join=true)
	{
		// we require either a competition or reg_id's
		if (empty($filter['WetId']) && empty($filter['reg_id']))
		{
			$this->total = 0;
			return array();
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
		unset($filter['state']);

		if ($join === true)
		{
			$join = 'JOIN '.ranking_athlete::ATHLETE_TABLE.' USING(PerId)'.ranking_athlete::FEDERATIONS_JOIN;

			$extra_cols = array_merge($extra_cols ? explode(',', $extra_cols) : array(),
				explode(',', "nachname,vorname,sex,".ranking_athlete::FEDERATIONS_TABLE.".nation AS nation,ort,verband,fed_url,".ranking_athlete::FEDERATIONS_TABLE.
				".fed_id AS fed_id,fed_parent,acl.fed_id AS acl_fed_id,geb_date,".
				ranking_athlete::ATHLETE_TABLE.".PerId AS PerId"));

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

				if ($col == 'nation')	// nation is from the joined Federations table
				{
					if ($val)
					{
						$f = $this->db->expression(ranking_athlete::FEDERATIONS_TABLE,array(
							!is_numeric($val) ? 'nation' : 'fed_parent' => $val,
						));
						// for numeric ids / state federations also check SUI Regionalzentrum acl.fed_id
						if (is_numeric($val)) $f = '('.$f.$this->db->expression(ranking_athlete::FEDERATIONS_TABLE,' OR acl.',array(
							'fed_id' => $val,
						),')');
						$filter[] = $f;
					}
					unset($filter['nation']);
				}
				elseif (!isset($this->db_cols[$col]))	// assume it's from joined Athletes table
				{
					if ($val) $filter[] = $this->db->expression(ranking_athlete::ATHLETE_TABLE,array($col => $val));
					unset($filter[$col]);
				}
			}
		}
		return parent::search($criteria,$only_keys,$order_by,$extra_cols,$wildcard,$empty,$op,$start,$filter,$join);
	}

	/**
	 * Get competitiors prequalified for a given competition, because of a certain place in spec. competitions
	 *
	 * All categories with matching discipline and gender are used, not just categories from competition.
	 * Eg. youth champions are counting for adult world-cups, if youth-championship is selected.
	 *
	 * @param int|array $comp WetId or complete competition array
	 * @param int|array $cat GrpId or complete category array
	 * @return array of PerId's
	 */
	/*function prequalified($comp,$cat)
	{
		$boranking = ranking_bo::getInstance();
		if (!is_array($comp) || !isset($comp['prequal_comp']))
		{
			$comp = $boranking->comp->read(is_array($comp) ? $comp['WetId'] : $comp);
		}
		if (!is_array($cat)) $cat = $boranking->cats->read($cat);

		$prequals = array();
		if ($cat && $comp['WetId'] && $comp['prequal_comp'] > 0 && $comp['prequal_comps'])
		{
			$cats = array($cat['GrpId'] => true);
			foreach((array)$this->search(array(),'PerId,GrpId,platz,Wettkaempfe.name AS comp,Gruppen.name AS cat','','','',false,'AND',false,array(
				'WetId' => explode(',',$comp['prequal_comps']),
				'platz <= '.(int)$comp['prequal_comp'],
				'platz > 0',	// no registered
			),'JOIN Wettkaempfe USING(WetId) JOIN Gruppen USING(GrpId)') as $athlet)
			{
				if (!isset($cats[$athlet['GrpId']]) && $cat['discipline'])
				{
					$reg_cat = $boranking->cats->read($athlet['GrpId']);
					$cats[$athlet['GrpId']] = $reg_cat['discipline'] == $cat['discipline'] && $reg_cat['sex'] == $cat['sex'];
				}
				if ($cats[$athlet['GrpId']])
				{
					if (isset($prequals[$athlet['PerId']])) $prequals[$athlet['PerId']] .= "\n";
					$prequals[$athlet['PerId']] .= $athlet['platz'].'. '.$athlet['comp'].' ('.$athlet['cat'].')';
				}
			}
		}
		//echo "<p>".__METHOD__."(comp=$comp[rkey], cat=$cat[rkey]/$cat[discipline]) prequal_comp=$comp[prequal_comp], prequal_comps=".array2string($comp['prequal_comps']); _debug_array($prequals);
		return $prequals;
	}*/

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
	 * saves the content of data to the db
	 *
	 * reimplemented to set a modifier (modified timestamp is set automatically by the database anyway)
	 *
	 * @param array $keys =null if given $keys are copied to data before saveing => allows a save as
	 * @return int|boolean 0 on success, or errno != 0 on error, or true if $extra_where is given and no rows affected
	 */
	/*function save($keys=null)
	{
		if (is_array($keys) && count($keys)) $this->data_merge($keys);

		$this->data['modifier'] = $GLOBALS['egw_info']['user']['account_id'];
		$this->data['modified'] = time();

		return parent::save();
	}*/

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
			__LINE__,__FILE__,false,'GROUP BY WetId,GrpId') as $row)
		{
			$status[$row['WetId']][$row['GrpId']] = 4;
		}
		return $status;
	}
}