<?php
/**
 * eGroupWare digital ROCK Rankings - federation storage object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2008 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$
 */

require_once(EGW_INCLUDE_ROOT . '/etemplate/inc/class.so_sql.inc.php');

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
		$this->so_sql(self::APPLICATION,self::FEDERATIONS_TABLE,$db);	// call constructor of derived class

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
	 * @param string $nation=null
	 * @return array
	 */
	function federations($nation=null)
	{
		$feds = array();
		$where = $nation ? array('nation' => $nation) : array();
		foreach($this->db->select(self::FEDERATIONS_TABLE,'fed_id,verband,nation',$where,__LINE__,__FILE__,false,
			'ORDER BY nation ASC,verband ASC','ranking') as $fed)
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
}
