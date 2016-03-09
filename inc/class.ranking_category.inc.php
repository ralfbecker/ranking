<?php
/**
 * eGroupWare digital ROCK Rankings - category storage object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2006-16 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$
 */

/**
 * category object
 */
class ranking_category extends so_sql
{
	/**
	 * @var array $data['rkey']2old maps new to old category-names
	 */
	var $cat2old = array(
		// int. Cats
		'ICC_F'   => 'WOMEN',
		'ICC_FB'  => 'BWOMEN',
		'ICC_FS'  => 'SWOMEN',
		'ICC_FX'  => 'AWOMEN',
		'ICC_M'   => 'MEN',
		'ICC_MB'  => 'BMEN',
		'ICC_MS'  => 'SMEN',
		'ICC_MO'  => 'OMEN',
		'ICC_MX'  => 'AMEN',
		'ICC_F_J' => 'W_JUNIOR',
		'ICC_F_A' => 'W_JUG_A',
		'ICC_F_B' => 'W_JUG_B',
		'ICC_M_J' => 'M_JUNIOR',
		'ICC_M_A' => 'M_JUG_A',
		'ICC_M_B' => 'M_JUG_B',
		// german Cats
		'GER_F'   => 'DAMEN',
		'GER_FB'  => 'BDAMEN',
		'GER_FS'  => 'SDAMEN',
		'GER_M'   => 'HERREN',
		'GER_MB'  => 'BHERREN',
		'GER_MS'  => 'SHERREN',
		'GER_F_X' => 'MAEDELS',
		'GER_M_A' => 'JUGEND_A',
		'GER_M_B' => 'JUGEND_B',
		'GER_M_J' => 'JUNIOR',
		// swiss Cats
		'SUI_F'   => 'SUI_D_1',
		'SUI_F_2' => 'SUI_D_2',
		'SUI_F_3' => 'SUI_D_3',
		'SUI_M'   => 'SUI_H_1',
		'SUI_M_2' => 'SUI_H_2',
		'SUI_M_3' => 'SUI_H_3',
		'SUI_F_J' => 'SUI_D_J',
		'SUI_F_A' => 'SUI_D_A',
		'SUI_F_B' => 'SUI_D_B',
		'SUI_F_X' => 'SUI_D_AB',
		'SUI_F_M' => 'SUI_D_M',
		'SUI_M_J' => 'SUI_H_J',
		'SUI_M_A' => 'SUI_H_A',
		'SUI_M_B' => 'SUI_H_B',
		'SUI_M_M' => 'SUI_H_M',
	);
	/**
	 * @var array $cache result from search() without params = all cats
	 */
	var $cache = null;
	var $charset,$source_charset;

	/**
	 * Members of overall ranking groups, not yet in database, identical to icc.inc.php file
	 *
	 * @var array
	 */
	static $mgroups = array(
		'ICC_MX' => array(
			1 => 'ICC_M',
			6 => 'ICC_MB',
			23 => 'ICC_MS',
		),
		 'ICC_FX' => array(
		 	2 => 'ICC_F',
			5 => 'ICC_FB',
			24 => 'ICC_FS',
		),
	);

	/**
	 * SQL for results column, counting results from all *Results tables for given GrpId
	 *
	 * @var string
	 */
	var $results_col;

	/**
	 * constructor of the category class
	 */
	function __construct($source_charset='',$db=null)
	{
		parent::__construct('ranking','Gruppen',$db);	// call constructor of derived class

		if ($source_charset) $this->source_charset = $source_charset;

		$this->charset = $GLOBALS['egw']->translation->charset();

		// get counts from all *Results tables
		foreach(array('Results','RouteResults','RelayResults') as $table)
		{
			$counts[] = "(SELECT COUNT(*) FROM $table WHERE $table.GrpId=Gruppen.GrpId)";
		}
		$this->results_col = implode('+',$counts).' AS results';
	}

	/**
	 * deletes row representing keys in internal data or the supplied $keys if != null
	 *
	 * @param array|int $keys =null if given array with col => value pairs to characterise the rows to delete, or integer autoinc id
	 * @return int|boolean affected rows, should be 1 if ok, 0 if an error, false if not found or category has results
	 */
	function delete($keys=null)
	{
		if (!$this->read($keys,array($this->results_col)) || $this->data['results'])
		{
			return false;	// not found or permission denied as Grp has results
		}
		return parent::delete($keys);
	}

	/**
	 * changes the data from the db-format to our work-format
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
		// set meta-groups for overall ranking categories
		if (isset(self::$mgroups[$data['rkey']]))
		{
			$data['GrpIds'] = array_keys(self::$mgroups[$data['rkey']]);
			$data['mgroups'] = self::$mgroups[$data['rkey']];
		}
		else
		{
			$data['GrpIds'] = array($data['GrpId']);
		}

		return $data;
	}

	/**
	 * changes the data from our work-format to the db-format
	 * @param array $data if given works on that array and returns result, else works on internal data-array
	 * @return array
	 */
	function data2db($data=null)
	{
		if (!is_array($data))
		{
			$data =& $this->data;
		}
		if ($data['rkey'] && !is_array($data['rkey']))
		{
			$data['rkey'] = strtoupper($data['rkey']);
		}
		if ($data['nation'] && !is_array($data['nation']))
		{
			$data['nation'] = $data['nation'] == 'NULL' ? '' : strtoupper($data['nation']);
		}
		if (count($data) && $this->source_charset)
		{
			$data = translation::convert($data,$this->charset,$this->source_charset);
		}
		return $data;
	}

	/**
	 * Search for cups
	 *
	 * reimplmented from so_sql to exclude some cols from search and do some caching
	 */
	function search($criteria,$only_keys=True,$order_by='',$extra_cols='',$wildcard='',$empty=False,$op='AND',$start=false,$filter=null)
	{
		//echo "<p>".__METHOD__."(".print_r($criteria,true).",'$only_keys','$order_by','$extra_cols','$wildcard','$empty','$op','$start',".print_r($filter,true).",'$join')</p>\n";

		if (is_array($criteria))
		{
			unset($criteria['rls']);	// is always set
			unset($criteria['vor_rls']);
		}
		if ($this->cache)
		{
			switch (count($criteria)+count($filter))
			{
				case 0:
					return $this->cache;

				case 1:
					$ret = false;
					list($key,$val) = count($criteria) ? each($criteria) : each($filter);
					foreach($this->cache as $cat)
					{
						if (is_array($val) && in_array($cat[$key],$val) ||
							!is_array($val) && $cat[$key] == $val)
						{
							$ret[] = $cat;
						}
					}
					return $ret;
			}
		}
		$filter[] = "rkey NOT LIKE 'X\\_%'";	// dont show old dR internal cats

		$ret =& parent::search($criteria,$only_keys,$order_by,$extra_cols,$wildcard,$empty,$op,$start,$filter);

		if (!$this->cache && count($criteria)+count($filter) == 0)
		{
			$this->cache =& $ret;
		}
		return $ret;
	}

	/**
	 * read a category, reimplemented to use the cache
	 *
	 * @param array $keys array with keys in form internalName => value, may be a scalar value if only one key
	 * @param string|array $extra_cols ='' string or array of strings to be added to the SELECT, eg. "count(*) as num"
	 * @param string $join ='' sql to do a join, added as is after the table-name, eg. ", table2 WHERE x=y" or
	 * @return array|boolean data if row could be retrived else False
	 */
	function read($keys,$extra_cols='',$join='')
	{
		// replace only key with new one if neccessary
		if ((!is_array($keys) && !is_numeric($keys) || is_array($keys) && $keys['rkey']) &&
			($new_rkey = array_search(is_array($keys) ? $keys['rkey'] : $keys,$this->cat2old)))
		{
			$keys['rkey'] = $new_rkey;
		}
		if (!is_array($keys))
		{
			// some caching in $this->data
			if (is_numeric($keys))
			{
				if ((int)$keys == $this->data['GrpId']) return $this->data;
				$keys = array('GrpId' => (int) $keys);
			}
			else
			{
				if ($keys === $this->data['rkey']) return $this->data;
				$keys = array('rkey' => $keys);
			}
		}
		if (!$extra_cols && !$join && $this->cache && count($keys) == 1 && ($keys['GrpId'] || $keys['rkey']))
		{
			list($key,$val) = each($keys);
			foreach($this->cache as $cat)
			{
				if ($cat[$key] == $val)
				{
					return $this->data = $cat;
				}
			}
			return false;
		}
		return parent::read($keys,$extra_cols,$join);
	}

	/**
	 * Sort categories as swiss federation wants it
	 *
	 * all not explicitly named cats are sorted by their GrpId
	 */
	static function sui_cat_sort($col='GrpId')
	{
		$fix_startorder = array(
			38 => 1,	// SUI_F_M 	U14 Damen
			37 => 2,	// SUI_M_M 	U14 Herren
			47 => 3,	// SUI_F_B 	U16 Damen
			35 => 4,	// SUI_M_B 	U16 Herren
			46 => 5,	// SUI_F_A 	U18 Damen
			34 => 6,	// SUI_M_A 	U18 Herren
			44 => 10,	// SUI_F_3 	Open Damen
			43 => 11,	// SUI_M_3 	Open Herren
			30 => 12,	// SUI_F 	Elite Damen
			33 => 13,	// SUI_M 	Elite Herren
		);
		$sql_sort = "CASE $col";
		foreach($fix_startorder as $grp_id => $sort)
		{
			$sql_sort .= ' WHEN '.$grp_id.' THEN '.$sort;
		}
		$sql_sort .= " ELSE $col END";

		return $sql_sort;
	}

	/**
	 * get the names of all or certain categories, eg. to use in a selectbox
	 * @param array $keys array with col => value pairs to limit name-list, like for so_sql.search
	 * @param int $rkeys -1: GrpId=>rkey:name 0: GrpId=>name, 1: rkey=>name, 2: rkey=>rkey:name, default 2
	 * @param string $sort ='rkey'
	 * @param boolean $check_sort =true false: not running $sort throught preg, only to be used for constants!
	 * @returns array with all Cups in the from specified in $rkeys
	 */
	function &names($keys=array(),$rkeys=2,$sort='',$check_sort=true)
	{
		if ($sort == 'SUI')
		{
			$sort = self::sui_cat_sort();
		}
		elseif (!$sort || $check_sort && !preg_match('/^[a-z]+ ?(asc|desc)?$/i',$sort))
		{
			$sort = $rkeys == 0 || $rkeys == 1 ? 'name' : 'rkey';
		}
		if (isset($keys['nation']))
		{
			if (!is_array($keys['nation']) && $keys['nation'] == 'NULL')
			{
				$keys['nation'] = null;
			}
			if (is_array($keys['nation']) && ($k = array_search('NULL',$keys['nation'])) !== false)
			{
				$keys['nation'][$k] = null;
			}
		}
		if (!is_null($keys['sex']) && !$keys['sex'])
		{
			unset($keys['sex']);
		}
		elseif ($keys['sex'] == 'NULL')
		{
			$keys['sex'] = null;
		}
		// implement "!(fe)male" to include non-gender cats
		elseif($keys['sex'][0] == '!')
		{
			$keys[] = "COALESCE(sex,'both') != ".$this->db->quote(substr($keys['sex'], 1));
			unset($keys['sex']);
		}
		$names = array();
		foreach((array)$this->search(array(),False,$sort,'','',false,'AND',false,$keys) as $data)
		{
			switch($rkeys)
			{
				case -1:
					$names[$data['GrpId']] = $data['rkey'] . ': ' . $data['name'];
					break;
				case 0:
					$names[$data['GrpId']] = $data['name'];
					break;
				case 1:
					$names[$data['rkey']] = $data['name'];
					break;
				default:
					$names[$data['rkey']] = $data['rkey'] . ': ' . $data['name'];
					break;
			}
		}
		//echo "<p>".__METHOD__."(".print_r($keys,true).") = <pre>".print_r($names,true)."</pre>\n";
		return $names;
	}

	/**
	 * converts a regular expression or comma-separated rkey-list, into an array of rkeys
	 *
	 * @param string $_rexp eg. 'SUI_M,SUI_F', 'SUI_[MF]', ...
	 * @param string|boolean $nation nation to limit the categories too
	 * @return array of rkey's
	 */
	function cat_rexp2rkeys($_rexp)
	{
		if (empty($_rexp)) return array();

		$rexp = preg_replace('/=[^,]*/','',$_rexp);	// removes cat specific counts

		if (!$this->all_names)
		{
			$this->all_names = $this->names();
		}
		$cats = array();
		foreach(array_keys((array) $this->all_names) as $rkey)
		{
			if (stristr( ",".$rexp.",",",".$rkey."," ) || $rexp && preg_match( '/^'.$rexp.'$/i',$rkey ) ||
				 		(isset($this->cat2old[$rkey]) && (stristr( ",".$rexp.",",",".$this->cat2old[$rkey]."," ) ||
				 		$rexp && preg_match( '/^'.$rexp.'$/i',$this->cat2old[$rkey] ))))
					{
						$cats[] = $rkey;
					}
		}
		//echo "<p>".__METHOD__."('$rexp')=".print_r($cats,true)."</p>\n";
		return $cats;
	}

	/**
	 * calculate the age-group ($from_year, $to_year) of a category for $stand
	 *
	 * @param array $cat category array, as eg. returned from read
	 * @param string $stand date as 'Y-m-d' (only year is used)
	 * @param int &$from_year on return starting year
	 * @param int &$to_year on return finishing year
	 * @return boolean true if cat uses an age_group, false otherwise
	 */
	static function age_group(array $cat,$stand,&$from_year=null,&$to_year=null)
	{
		if (($from_year = $cat['from_year']) < 0) // neg. is age not year
		{
			$from_year += $stand;
		}
		if (($to_year = $cat['to_year']) < 0)
		{
			$to_year += $stand;
		}
		if ($to_year && $from_year > $to_year)
		{
			$y = $from_year; $from_year = $to_year; $to_year = $y;
		}
		//echo "".__METHOD__."(,'$stand',from=$from_year, to=$to_year)"; _debug_array($cat);
		return $cat['from_year'] || $cat['to_year'];
	}

	/**
	 * Check if a given birthdate is in the age-group of a category
	 *
	 * @param int|string $birthdate birthdate Y-m-d or birthyear
	 * @param int|array $cat GrpId or category array
	 * @param int|string $year =null year or date to check, default current year
	 * @return boolean true if $birthdate is in the agegroup or category does NOT use age-groups
	 */
	function in_agegroup($birthdate,$cat,$year=null)
	{
		static $cats=array();	// some caching of cats, to not read them multiple times

		if (!is_array($cat))
		{
			if (!isset($cats[$cat]))
			{
				$cats[$cat] = $this->read($cat);
			}
			$cat = $cats[$cat];
		}
		if (is_null($year)) $year = (int)date('Y');

		$from_year = $to_year = null;
		if (!self::age_group($cat,$year,$from_year,$to_year))
		{
			$ret = true;
			//$reason = ' (cat does not use age-groups)';
		}
		elseif (!$birthdate)
		{
			// consider no birthdate as an adult of 20 years,
			// to allow registration into adults categories requiring 16+ age
			$ret = $this->in_agegroup($year-20, $cat, $year);
		}
		else
		{
			$ret = (!$from_year || $from_year <= (int)$birthdate) && (!$to_year || (int)$birthdate <= $to_year);
		}
		//echo "<p>".__METHOD__."($birthdate,".array2string($cat).",$year) from_year=$from_year, to_year=$to_year --> returning ".array2string($ret)."$reason</p>\n";
		return $ret;
	}
}