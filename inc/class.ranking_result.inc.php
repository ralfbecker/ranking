<?php
/**
 * EGroupware digital ROCK Rankings - result storage object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2006-12 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$
 */

/**
 * result object
 */
class ranking_result extends so_sql
{
	var $non_db_cols = array(	// fields in data, not (direct) saved to the db
	);
	var $result_table  = 'Results';
	var $athlete_table = 'Personen';
	var $cat_table     = 'Gruppen';
	var $comp_table    = 'Wettkaempfe';
	var $ff_table      = 'Feldfaktoren';
	var $pkte_table    = 'PktSystemPkte';
	var $charset,$source_charset;

	/**
	 * constructor of the competition class
	 */
	function __construct($source_charset='',$db=null,$vfs_pdf_dir='')
	{
		//$this->debug = 1;
		parent::__construct('ranking',$this->result_table,$db);	// call constructor of extended class

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
		static $athlete;
		if (!isset($athlete)) $athlete = boranking::getInstance()->athlete;

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
	 * @param string/array $extra_cols string or array of strings to be added to the SELECT, eg. "count(*) as num"
	 * @param string/boolean $join=true true for the default join or sql to do a join, added as is after the table-name, eg. ", table2 WHERE x=y" or
	 * @param string $order='' order clause or '' for the default order depending on the keys given
	 * @return array/boolean data if row could be retrived else False
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
			unset($filter[$key]);
		}
		if ($this->WetId && $this->GrpId && !$this->PerId)	// read complete result of comp. WetId for cat GrpId
		{
			$cols = false;
			if ($join === true)
			{
				$join = 'JOIN '.ranking_athlete::ATHLETE_TABLE.' USING(PerId)'.ranking_athlete::FEDERATIONS_JOIN;

				if (!$extra_cols)
				{
					$extra_cols = "nachname,vorname,nation,ort,verband,fed_url,".ranking_athlete::FEDERATIONS_TABLE.".fed_id AS fed_id,fed_parent,acl.fed_id AS acl_fed_id,geb_date,pkt & 63 AS reg_nr,$this->athlete_table.PerId AS PerId";
				}
				else
				{
					$cols = $extra_cols;
					$extra_cols = '';
				}
			}
			if ($this->GrpId < 0) unset($keys['GrpId']);	// return all cats

			return $this->search($keys,$cols,$order ? $order : $this->result_table.'.GrpId,platz,nachname,vorname',$extra_cols,'',false,'AND',false,$filter,$join);
		}
		elseif (!$this->WetId && !$this->GrpId && $this->PerId)	// read list comps of one athlet
		{
			if ($join === true)
			{
				$join = ",$this->cat_table,$this->comp_table WHERE $this->result_table.GrpId=$this->cat_table.GrpId AND $this->result_table.WetId=$this->comp_table.WetId";
				$extra_cols = "$this->comp_table.name AS name,$this->comp_table.rkey AS rkey,$this->cat_table.name AS cat_name,$this->cat_table.rkey AS cat_rkey";
			}
			return $this->search($keys,false,$order ? $order : $this->result_table.'.datum DESC,platz',$extra_cols,'',false,'AND',false,$filter,$join);
		}
		elseif ($this->WetId && !$this->GrpId)	// read cat-list of competition
		{
			if ($join === true)
			{
				$join = ",$this->cat_table WHERE $this->result_table.GrpId=$this->cat_table.GrpId";
				$extra_cols = 'name,rkey';
			}
			return $this->search($keys,"DISTINCT $this->result_table.GrpId AS GrpId",$order ? $order : 'rkey,name',$extra_cols,'',false,'AND',false,$filter,$join);
		}
		// result of single person
		return parent::read($keys,$extra_cols,$join !== true ? $join : '');
	}

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
	 */
	function has_results($keys)
	{
		$keys[] = 'platz > 0';

		if ($keys['GrpId'] < 0) unset($keys['GrpId']);	// < 0 means all

		return $this->db->select($this->table_name,'count(*)',$keys,__LINE__,__FILE__)->fetchColumn();
	}

	/**
	 * Checks if there are any startnumbers (platz == 0 && pkt > 64) for the given keys
	 *
	 * @param array $keys with index WetId, PerId and/or GrpId
	 * @return boolean/int number of found results or false on error
	 */
	function has_startlist($keys)
	{
		$keys[] = 'platz = 0 AND pkt >= 64';

		if ($keys['GrpId'] < 0) unset($keys['GrpId']);	// < 0 means all

		return $this->db->select($this->table_name,'count(*)',$keys,__LINE__,__FILE__)->fetchColumn();
	}

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
	 * @param array/string $criteria array of key and data cols, OR a SQL query (content for WHERE), fully quoted (!)
	 * @param boolean $only_keys True returns only keys, False returns all cols
	 * @param string $order_by fieldnames + {ASC|DESC} separated by colons ','
	 * @param string/array $extra_cols string or array of strings to be added to the SELECT, eg. "count(*) as num"
	 * @param string $wildcard appended befor and after each criteria
	 * @param boolean $empty False=empty criteria are ignored in query, True=empty have to be empty in row
	 * @param string $op defaults to 'AND', can be set to 'OR' too, then criteria's are OR'ed together
	 * @param int/boolean $start if != false, return only maxmatch rows begining with start
	 * @param array $filter if set (!=null) col-data pairs, to be and-ed (!) into the query without wildcards
	 * @param string $join='' sql to do a join, added as is after the table-name, eg. ", table2 WHERE x=y" or
	 *	"LEFT JOIN table2 ON (x=y)", Note: there's no quoting done on $join!
	 * @return array of matching rows (the row is an array of the cols) or False
	 */
	/* not yet needed
	function search($criteria,$only_keys=True,$order_by='',$extra_cols='',$wildcard='',$empty=False,$op='AND',$start=false,$filter=null,$join='')
	{
		//echo "<p>result::search(".print_r($criteria,true).",'$only_keys','$order_by',".print_r($extra_cols,true).",'$wildcard','$empty','$op',$start,".print_r($filter,true).",'$join')</p>\n";
		//$this->debug = 1;
		return parent::search($criteria,$only_keys,$order_by,$extra_cols,$wildcard,$empty,$op,$start,$filter,$join);
	}*/

	/**
	 * Get competitiors prequalified for a given competition, because of a certain place in spec. competitions
	 *
	 * All categories with matching discipline and gender are used, not just categories from competition.
	 * Eg. youth champions are counting for adult world-cups, if youth-championship is selected.
	 *
	 * @param int/array $comp WetId or complete competition array
	 * @param int/array $cat GrpId or complete category array
	 * @return array of PerId's
	 */
	function prequalified($comp,$cat)
	{
		if (!is_array($comp) || !isset($comp['prequal_comp']))
		{
			$comp = $this->read($comp);
		}
		if (!isset($boranking)) $boranking = boranking::getInstance();
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
	}

	/**
	 * return result-list for a cup-ranking of $cup as of $stand
	 *
	 * @param array $cup
	 * @param array $cats array of integer cat-id's (GrpId)
	 * @param string $stand date as 'Y-m-d'
	 * @param array $allowed_nations=null array with 3-digit nation-codes to limit the nations to return, default all
	 * @param boolean $use_0_point_results=false should we return results with 0 points (place > 30)
	 * @return array ordered by PerId and number of points of arrays with full athlete-data, platz, pkt, WetId, GrpId
	 */
	function &cup_results(array $cup,$cats,$stand,$allowed_nations,$use_0_point_results=false)
	{
		$results = array();
		foreach($this->db->query("SELECT $this->athlete_table.*,".
			ranking_athlete::FEDERATIONS_TABLE.".nation,verband,fed_url,(CASE WHEN r.cup_platz IS NOT NULL THEN r.cup_platz ELSE r.platz END) AS platz,r.cup_pkt/100.0 AS pkt,r.WetId,r.GrpId,COALESCE(w.discipline,c.discipline) AS discipline".
			" FROM $this->result_table r,$this->comp_table w,$this->cat_table c,$this->athlete_table ".ranking_athlete::FEDERATIONS_JOIN.
			" WHERE r.WetId=w.WetId AND $this->athlete_table.PerId=r.PerId AND r.platz > 0".
			' AND r.GrpId '.(count($cats) == 1 ? '='.(int)$cats[0] : ' IN ('.implode(',',$cats).')').
			' AND r.GrpID=c.GrpId'.
			' AND w.serie='.(int) $cup['SerId'].
			($use_0_point_results ? '' : ' AND r.cup_pkt > 0').
			' AND w.datum <= '.$this->db->quote($stand).
			' ORDER BY r.PerId,r.GrpId,r.cup_pkt DESC,w.datum DESC',__LINE__,__FILE__) as $row)
		{
			$results[] = $this->athlete_db2data($row);
		}
		return $results;
	}

	/**
	 * return result-list for a ranking from $start to $stand
	 *
	 * @param array $cats array of integer cat-id's (GrpId)
	 * @param string $stand date as 'Y-m-d'
	 * @param string $start date as 'Y-m-d'
	 * @param int $from_year=0 start-year of age-group, default 0 = no age-group
	 * @param int $to_year=0 end-year of age-group, default 0 = no age-group
	 * @return array ordered by PerId and number of points of arrays with full athlete-data, platz, pkt, WetId, GrpId
	 */
	function &ranking_results($cats,$stand,$start,$from_year=0,$to_year=0)
	{
		$results = array();
		foreach($this->db->query($sql="SELECT $this->athlete_table.*,".
			ranking_athlete::FEDERATIONS_TABLE.'.nation,verband,fed_url,r.platz,r.pkt/100.0 AS pkt,r.WetId,r.GrpId,COALESCE(w.discipline,c.discipline) AS discipline'.
			" FROM $this->result_table r,$this->comp_table w,$this->cat_table c,$this->athlete_table ".ranking_athlete::FEDERATIONS_JOIN.
			" WHERE r.WetId=w.WetId AND $this->athlete_table.PerId=r.PerId AND r.pkt > 0 AND r.platz > 0".
			' AND r.GrpId '.(count($cats)==1 ? '='.(int) $cats[0] : ' IN ('.implode(',',$cats).')').
			' AND r.GrpID=c.GrpId'.
			' AND '.$this->db->quote($start).' <= w.datum AND w.datum <= '.$this->db->quote($stand).
			($from_year && $to_year ? ' AND NOT ISNULL(geb_date) AND '.
			(int) $from_year.' <= YEAR(geb_date) AND YEAR(geb_date) <= '.(int) $to_year : '').
			' ORDER BY r.PerId,r.pkt DESC',__LINE__,__FILE__) as $row)
		{
			$results[] = $this->athlete_db2data($row);
		}
		return $results;
	}

	/**
	 * Return results for an aggregated ranking
	 *
	 * @param array $filter
	 * @param string $extra_cols
	 * @param string $join
	 * @param string $append='' eg. ORDER BY ...
	 * @return array
	 */
	function &aggregated_results(array $filter, $extra_cols, $join, $append)
	{
		$results = array();
		foreach($this->db->select($this->result_table, $this->result_table.'.*,'.$extra_cols, $filter, __LINE__, __FILE__,
			false, $append, 'ranking', 0, $join) as $row)
		{
			$result = $this->athlete_db2data($row);
			foreach(explode(',', $extra_cols) as $col)
			{
				$name = $col;
				list($col,$name) = explode(' AS ', $col);
				if (!isset($result[$name]) && isset($row[$name])) $result[$name] = $row[$name];
			}
			$results[] = $result;
		}
		return $results;
	}

	/**
	 * Save a calculated fieldfactor
	 *
	 * @param int/array $comp
	 * @param int/array $cat
	 * @param double $factor
	 */
	function save_feldfactor($comp,$cat,$factor)
	{
		$this->db->insert($this->ff_table,array(
			'ff' => $factor,
		),array(
			'WetId' => is_array($comp) ? $comp['WetId'] : $comp,
			'GrpId' => is_array($cat) ? $cat['GrpId'] : $cat,
		),__LINE__,__FILE__);
	}

	/**
	 * Merge the resultservice results from athlete $from to athlete $to
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
		// delete GrpId=0 and WetId=0 records from $from used to ease finding new athletes in their category
		// gives SQL error because of doublicate primary key, if not deleted
		$this->db->delete($this->table_name, array(
			'PerId' => $from,
			'GrpId' => 0,
			'WetId' => 0,
		), __LINE__, __FILE__);

		$this->db->update($this->table_name,array('PerId'=>$to),array('PerId'=>$from),__LINE__,__FILE__,'ranking');

		return $this->db->affected_rows();
	}

	/**
	 * saves the content of data to the db
	 *
	 * reimplemented to set a modifier (modified timestamp is set automatically by the database anyway)
	 *
	 * @param array $keys=null if given $keys are copied to data before saveing => allows a save as
	 * @return int|boolean 0 on success, or errno != 0 on error, or true if $extra_where is given and no rows affected
	 */
	function save($keys=null)
	{
		if (is_array($keys) && count($keys)) $this->data_merge($keys);

		$this->data['modifier'] = $GLOBALS['egw_info']['user']['account_id'];
		$this->data['modified'] = time();

		return parent::save();
	}

	/**
	 * Check the status / existance of start list or result for all categories of given competitions
	 *
	 * @param int|array $comps
	 * @return array of WetId => GrpId => status: 0=result, 3=startlist, 4=starters
	 */
	function result_status($comps)
	{
		$status = array();
		foreach($this->db->select($this->table_name,'WetId,GrpId,MAX(platz) AS platz,MAX(pkt) AS pkt',array('WetId' => $comps),
			__LINE__,__FILE__,false,'GROUP BY WetId,GrpId') as $row)
		{
			$status[$row['WetId']][$row['GrpId']] = $row['platz'] ? 0 : ($row['pkt'] > 64 ? 3 : 4);
		}
		return $status;
	}
}