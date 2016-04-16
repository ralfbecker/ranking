<?php
/**
 * EGroupware digital ROCK Rankings - cup storage object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2006-16 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$
 */

use EGroupware\Api;

/**
 * cup object
 */
class ranking_cup extends so_sql
{
	var $charset,$source_charset;
	/**
	 * reference to the category object
	 *
	 * @var ranking_category
	 */
	var $cats;

	/**
	 * Timestaps that need to be adjusted to user-time on reading or saving
	 *
	 * @var array
	 */
	var $timestamps = array('modified');

	/**
	 * constructor of the cup class
	 *
	 * @param string $source_charset
	 * @param Api\Db $db
	 * @return cup
	 */
	function __construct($source_charset='',$db=null)
	{
		parent::__construct('ranking','Serien',$db);	// call constructor of derived class

		if ($source_charset) $this->source_charset = $source_charset;

		$this->charset = translation::charset();

		foreach(array(
				'cats'  => 'ranking_category',
			) as $var => $class)
		{
			$egw_name = /*'ranking_'.*/$class;
			if (!isset($GLOBALS['egw']->$egw_name))
			{
				$GLOBALS['egw']->$egw_name = CreateObject('ranking.'.$class,$this->source_charset,$this->db);
			}
			$this->$var = $GLOBALS['egw']->$egw_name;
		}
		$this->non_db_cols = array('max_per_cat','nat_team_quota');
	}

	/**
	 * changes the data from the db-format to our work-format
	 *
	 * @param array $data if given works on that array and returns result, else works on internal data-array
	 * @return array
	 */
	function db2data($data=0)
	{
		if (($intern = !is_array($data)))
		{
			$data =& $this->data;
		}
		if (count($data) && $this->source_charset)
		{
			$data = translation::convert($data,$this->source_charset);
		}
		if ($data['gruppen'])
		{
			foreach(explode(',',$data['gruppen']) as $cat_str)
			{
				if (strstr($cat_str,'='))
				{
					list($cat2,$params2) = explode('=',$cat_str,2);
					$cat = strtoupper(($c = in_array($cat2, $this->cats->cat2old)) ? $c : $cat2);
					$params = explode('+',$params2);

					if ($params[0]) $data['nat_team_quota'][$cat] = $params[0];
					if ($params[1]) $data['max_per_cat'][$cat] = $params[1];
					if ($params[2]) $data['min_disciplines_per_cat'][$cat] = $params[2];
				}
			}
			$data['gruppen'] = $this->cats->cat_rexp2rkeys($data['gruppen']);
		}
		if ($data['presets']) $data['presets'] = (array)json_php_unserialize($data['presets']);

		if (isset($data['presets']) && isset($data['presets']['quali_preselected']))
		{
			$num = null;
			foreach(explode(',', $data['presets']['quali_preselected']) as $n => $pre)
			{
				if ($n === 0) $data['presets']['quali_preselected'] = array();
				unset($num);
				list($grp, $num) = explode(':', $pre);
				if (!isset($num))
				{
					$num = $grp;
					$grp = 0;
				}
				$data['presets']['quali_preselected'][] = array('cat' => $grp, 'num' => $num);
			}
		}

		if ($data['max_disciplines'])
		{
			$data['max_disciplines'] = json_decode($data['max_disciplines'], true);
		}

		return parent::db2data($intern ? null : $data);	// important to use null, if $intern!
	}

	/**
	 * changes the data from our work-format to the db-format
	 *
	 * @param array $data if given works on that array and returns result, else works on internal data-array
	 * @return array
	 */
	function data2db($data=0)
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
		if ($data['gruppen'])
		{
			if (!is_array($data['gruppen'])) $data['gruppen'] = explode(',',$data['gruppen']);

			foreach($data['gruppen'] as &$cat_rkey)
			{
				if ($data['nat_team_quota'][$cat_rkey] || $data['max_per_cat'][$cat_rkey])
				{
					$cat_rkey .= '='.$data['nat_team_quota'][$cat_rkey].
						($data['max_per_cat'][$cat_rkey] ? '+'.$data['max_per_cat'][$cat_rkey] :
							($data['min_disciplines_per_cat'][$cat_rkey] ? '+' : '')).
						($data['min_disciplines_per_cat'][$cat_rkey] ? '+'.$data['min_disciplines_per_cat'][$cat_rkey] : '');
				}
			}
			unset($data['nat_team_quota']);
			unset($data['max_per_cat']);
			unset($data['min_disciplines_per_cat']);

			$data['gruppen'] = implode(',',$data['gruppen']);
		}
		if (isset($data['presets']) && isset($data['presets']['quali_preselected']))
		{
			$to_store = array();
			foreach($data['presets']['quali_preselected'] as $n => $pre)
			{
				$to_store[$pre['cat']] = $pre['cat'].':'.(int)$pre['num'];
				// remove last 0 selected for all cats line
				if ($to_store[$pre['cat']] == ':0' && $n)
				{
					unset($to_store[$pre['cat']]);
				}
			}
			$data['presets']['quali_preselected'] = implode(',', $to_store);
		}
		if (is_array($data['presets'])) $data['presets'] = json_encode($data['presets']);

		if (isset($data['max_disciplines']))
		{
			if ($data['max_disciplines'] && ($data['max_disciplines']['lead'] > 0 ||
				$data['max_disciplines']['boulder'] > 0 || $data['max_disciplines']['speed'] > 0))
			{
				$data['max_disciplines'] = json_encode($data['max_disciplines']);
			}
			else
			{
				$data['max_disciplines'] = null;
			}
		}

		return parent::data2db($intern ? null : $data);	// important to use null, if $intern!
	}

	/**
	 * Search for cups
	 *
	 * reimplmented from so_sql to exclude some cols from search and to calc. year from rkey
	 */
	function search($criteria,$only_keys=True,$order_by='',$extra_cols='',$wildcard='',$empty=False,$op='AND',$start=false,$filter=null)
	{
		if (is_array($criteria))
		{
			unset($criteria['pkte']);	// is always set
			unset($criteria['split_by_places']);
			if ($criteria['nation'] == 'NULL') $criteria['nation'] = null;
		}
		if ($filter['nation'] == 'NULL') $filter['nation'] = null;

		if ($extra_cols && !is_array($extra_cols)) $extra_cols = array($extra_cols);
		$extra_cols[] = 'IF(LEFT(rkey,2)>80,1900,2000)+LEFT(rkey,2) AS year';

		return parent::search($criteria,$only_keys,$order_by,$extra_cols,$wildcard,$empty,$op,$start,$filter);
	}

	/**
	 * get the names of all or certain cups, eg. to use in a selectbox
	 *
	 * @param array/string $keys array with col => value pairs to limit name-list, like for so_sql.search
	 *	or string with nation
	 * @return array with all Cups of form SerId => name
	 */
	function names($keys=array(),$rkey_only=false)
	{
		if (!is_array($keys)) $keys = $keys ? array('nation' => ($keys == 'NULL' ? null : $keys)) : array();

		$names = array();
		foreach((array)$this->search(array(),False,'year DESC','','',true,'AND',false,$keys) as $data)
		{
			$names[$data['SerId']] = $data['rkey'].($rkey_only ? '' : ': '.$data['name']);
		}
		//error_log(__METHOD__.'('.array2string($keys).') returning '.array2string($names));
		return $names;
	}

	/**
	 * get max. number of comps. counting for $cat in $cup
	 *
	 * @param string $cat_rkey cat-rkey to check
	 * @param array $cup =null cup-array to use, default use internal data
	 * @return int number of competitions counting
	 */
	function get_max_comps($cat_rkey,$cup=null)
	{
		if (!is_array($cup))
		{
			$cup =& $this->data;
		}
		$max = $cup['max_per_cat'][$cat_rkey] ? (int) $cup['max_per_cat'][$cat_rkey] : $cup['max_serie'];

		if ($max <= 0)	// $max comps less then the total
		{
			if (!isset($GLOBALS['egw']->comp))
			{
				$GLOBALS['egw']->comp = CreateObject('ranking.competition',$this->source_charset,$this->db);
			}
			$cats = array($cat_rkey);
			// ToDo: add mgroups

			$wettks = $GLOBALS['egw']->comp->search(array(),true,'','','',false,'AND',false,array(
				'nation' => $cup['nation'],
				'serie'  => $cup['SerId'],
				$GLOBALS['egw']->comp->check_in_cats($cats),
			));
			$anz_wettk = count($wettks);
			//echo "<p>$sql: anz_wettk=$anz_wettk</p>\n";
			return ($max + $anz_wettk).($max?" (=$anz_wettk$max)":'');
		}
		return $max;
	}

	/**
	 * get min. number of disciplines required in $cat in $cup
	 *
	 * ToDo: cat-specific min. number cant be set in the UI
	 *
	 * @param string $cat_rkey cat-rkey to check
	 * @param array $cup =null cup-array to use, default use internal data
	 * @return int number of competitions counting
	 */
	function get_min_disciplines($cat_rkey,$cup=null)
	{
		if (!is_array($cup))
		{
			$cup =& $this->data;
		}
		return !empty($cup['min_disciplines_per_cat'][$cat_rkey]) ?
			$cup['min_disciplines_per_cat'][$cat_rkey] : $cup['min_disciplines'];
	}

	/**
	 * Read a competition, reimplemented to allow to pass WetId or rkey instead of the array
	 *
	 * @param mixed $keys array with keys, or WetId or rkey
	 * @param string/array $extra_cols string or array of strings to be added to the SELECT, eg. "count(*) as num"
	 * @param string $join ='' sql to do a join, added as is after the table-name, eg. ", table2 WHERE x=y" or
	 * @return array/boolean array with competition or false on error (eg. not found)
	 */
	function read($keys,$extra_cols='',$join='')
	{
		if ($keys && !is_array($keys))
		{
			$keys = is_numeric($keys) ? array('SerId' => (int) $keys) : array('rkey' => $keys);
		}
		return parent::read($keys,$extra_cols,$join);
	}

	/**
	 * saves the content of data to the db
	 *
	 * Reimplemented to automatic update modifier and modified time
	 *
	 * @param array $keys =null if given $keys are copied to data before saveing => allows a save as
	 * @param string|array $extra_where =null extra where clause, eg. to check an etag, returns true if no affected rows!
	 * @return int|boolean 0 on success, or errno != 0 on error, or true if $extra_where is given and no rows affected
	 */
	function save($keys=null,$extra_where=null)
	{
		if (is_array($keys) && count($keys)) $this->data_merge($keys);

		$this->data['modifier'] = $GLOBALS['egw_info']['user']['account_id'];
		$this->data['modified'] = $this->now;

		return parent::save(null,$extra_where);
	}
}