<?php
/**
 * EGroupware digital ROCK Rankings - competition storage object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2006-19 by Ralf Becker <RalfBecker@digitalrock.de>
 */

namespace EGroupware\Ranking;

use EGroupware\Api;
use EGroupware\Api\Vfs;

/**
 * competition object
 */
class Competition extends Api\Storage\Base
{
	var $non_db_cols = array(	// fields in data, not (direct) saved to the db
		'durartion' => 'duration'
	);
	var $charset,$source_charset;
	/**
	 * reference to the category object
	 *
	 * @var Category
	 */
	var $cats;
	/**
	 * @var array $attachment_prefixes prefixes of the rkey for the different attachments
	 */
	var $attachment_prefixes = array(
		'info'      => '',
		'startlist' => 'S',
		'result'    => 'R',
		'logo'      => 'l',
		'sponsors'  => 's',
		'info2'     => 'i',
	);
	var $vfs_pdf_dir = '';
	var $vfs_pdf_url = '';
	var $result_table = 'Results';
	/**
	 * Values for display_athlete column
	 */
	const NONE = 'none';
	const NATION = 'nation';
	const FEDERATION = 'federation';
	const PC_CITY = 'pc_city';
	const NATION_PC_CITY = 'nation_pc_city';
	const CITY = 'city';
	const PARENT_FEDERATION = 'fed_parent';
	const FED_AND_PARENT = 'fed_and_parent';

	var $selfregister_types = array(
		0 => 'Not allowed',
		1 => 'Federation needs to confirm',
		2 => 'Allowed without extra confirmation',
	);
	/**
	 * Constants for column open_comp
	 */
	const OPEN_NOT = 0;
	const OPEN_NATION = 1;
	const OPEN_DACH = 2;
	const OPEN_INT = 3;
	/**
	 * Labels for column open_comp
	 *
	 * @var array
	 */
	var $open_comp_types = array(
		self::OPEN_NOT    => 'No',
		self::OPEN_NATION => 'National',
		self::OPEN_DACH   => 'D,A,CH',
		self::OPEN_INT    => 'International',
	);
	var $prequal_types = array(
		0 => 'comp. date',
		1 => '1. January',
	);

	/**
	 * Timestaps that need to be adjusted to user-time on reading or saving
	 *
	 * @var array
	 */
	var $timestamps = array('modified');

	/**
	 * constructor of the competition class
	 *
	 * @param string $source_charset
	 * @param Api\Db $db
	 * @param string $vfs_pdf_dir
	 * @param string $vfs_pdf_url
	 */
	function __construct($source_charset='',$db=null,$vfs_pdf_dir='',$vfs_pdf_url='')
	{
		//$this->debug = 1;
		parent::__construct('ranking','Wettkaempfe',$db);	// call constructor of extended class

		if ($source_charset) $this->source_charset = $source_charset;

		$this->charset = Api\Translation::charset();
		$this->cats = new Category($source_charset, $this->db);

		if ($vfs_pdf_dir) $this->vfs_pdf_dir = $vfs_pdf_dir;
		if ($vfs_pdf_url) $this->vfs_pdf_url = $vfs_pdf_url;
	}

	/**
	 * Get default display_athlete value for a nation
	 *
	 * @param string $nation
	 * @return string self::(FEDERATION|NATION|CITY|PC_CITY|NATION_PC_CITY|FED_AND_PARENT|NONE) constants
	 */
	public static function nation2display_athlete($nation, $intern=false)
	{
		switch($nation)
		{
			default:
			case 'GER':
				return self::FED_AND_PARENT;

			case '':	// international
			case 'NULL':
				return self::NATION;

			case 'SUI':
				return $intern ? self::FEDERATION : self::CITY;
		}
	}

	/**
	 * initializes an empty competition
	 *
	 * reimplemented from Api\Storage\Base to set some defaults
	 *
	 * @param array $keys array with keys in form internalName => value
	 */
	function init($keys=array())
	{
		$this->data = array(
			'pkte'   => 'uiaa',
			'faktor' => 0.0,
		);

		$this->db2data();

		$this->data_merge($keys);
	}

	/**
	 * changes the data from the db-format to our work-format
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
			$data = Api\Translation::convert($data,$this->source_charset);
		}
		if ($data['name'])
		{
			$data['name'] = strip_tags($data['name']);	// some use tags for calendar formatting
		}
		list($data['gruppen'],$data['duration']) = explode('@',$data['gruppen']);
		$data['gruppen'] = $data['gruppen'] ? $this->cats->cat_rexp2rkeys($data['gruppen']) : array();
		$data['pkt_bis'] = $data['pkt_bis']!='' ? intval(100 * $data['pkt_bis']) : 100;
		$data['feld_bis'] = $data['feld_bis']!='' ? intval(100 * $data['feld_bis']) : 100;

		if ($data['judges']) $data['judges'] = explode(',',$data['judges']);

		if ($data['datum'])
		{
			// calculate an end-date as Y-m-d and a printable span like 1. - 3. Januar 2007
			list($y,$m,$d) = explode('-',$data['datum']);
			$start = mktime(12,0,0,(int)$m,(int)$d,(int)$y);
			$end = $start + ($data['duration']-1)*24*60*60;
			$data['date_end'] = date('Y-m-d',$end);
			$data['date_span'] = (int)$d.' '.(date('m',$end) != $m ? lang(date('F',$start)) : '').
				($data['duration'] > 1 ? ' - '.(int)date('d',$end).' ' : '').lang(date('F',$end)).' '.$y;
			//echo "<p>y=$y, m=$m, d=$d, duration=$data[duration], start=$start, end=$end=$data[date_end], span=$data[date_span]</p>\n";
		}
		foreach(array('prequal_extra','quota_extra') as $name)
		{
			if (isset($data[$name]) && $data[$name])
			{
				if (in_array($data[$name][0], ['[','{']))
				{
					$data[$name] = json_decode($data[$name], true);
				}
				else
				{
					$extra = array();
					foreach(explode(',', $data[$name]) as $pair)
					{
						list($cat,$num,$fed) = explode(':',$pair);
						$extra[] = array('cat' => $cat,'num' => $num,'fed' => $fed);
					}
					$data[$name] = $extra;
				}
			}
		}

		if (isset($data['quali_preselected']))
		{
			foreach(explode(',', $data['quali_preselected']) as $n => $pre)
			{
				if ($n === 0) $data['quali_preselected'] = array();
				unset($num);
				list($grp, $num) = explode(':', $pre);
				if (!isset($num))
				{
					$num = $grp;
					$grp = 0;
				}
				$data['quali_preselected'][] = array('cat' => $grp, 'num' => $num);
			}
		}
		return parent::db2data($intern ? null : $data);	// important to use null, if $intern!
	}

	/**
	 * changes the data from our work-format to the db-format
	 * @param array $data if given works on that array and returns result, else works on internal data-array
	 * @return array
	 */
	function data2db($data=0)
	{
		if (($intern = !is_array($data)))
		{
			$data =& $this->data;
		}
		if (is_array($data['gruppen']))
		{
			$data['gruppen'] = implode(',',$data['gruppen']);
		}
		if ($data['duration']) $data['gruppen'] .= '@' . $data['duration'];
		if ($data['pkt_bis'])  $data['pkt_bis']  = $data['pkt_bis']  == 100 ? '' : round($data['pkt_bis']/100,2);
		if ($data['feld_bis']) $data['feld_bis'] = $data['feld_bis'] == 100 ? '' : round($data['feld_bis']/100,2);
		if ($data['rkey'])     $data['rkey'] = strtoupper($data['rkey']);
		if ($data['nation'] && !is_array($data['nation']))   $data['nation'] = $data['nation'] == 'NULL' ? null : strtoupper($data['nation']);
		if (isset($data['pkte']) && !$data['pkte']) $data['pkte'] = null;
		if (isset($data['feld_pkte']) && !$data['feld_pkte']) $data['feld_pkte'] = null;
		if (isset($data['judges']) && is_array($data['judges']))
		{
			$data['judges'] = implode(',',$data['judges']);
		}
		foreach(array('prequal_extra','quota_extra') as $name)
		{
			if (isset($data[$name]) && is_array($data[$name]))
			{
				$data[$name] = json_encode(array_values(array_filter($data[$name], function($pair)
				{
					return ($pair['fed'] || $pair['cat']) && $pair['num'];
				})));
			}
		}
		if (isset($data['serie']) && (string)$data['serie'] === '0') $data['serie'] = null;
		if (count($data) && $this->source_charset)
		{
			$data = Api\Translation::convert($data,$this->charset,$this->source_charset);
		}

		if (isset($data['quali_preselected']))
		{
			$to_store = array();
			foreach($data['quali_preselected'] as $n => $pre)
			{
				$to_store[$pre['cat']] = $pre['cat'].':'.(int)$pre['num'];
				// remove last 0 selected for all cats line
				if ($to_store[$pre['cat']] == ':0' && $n)
				{
					unset($to_store[$pre['cat']]);
				}
			}
			$data['quali_preselected'] = implode(',', $to_store);
		}
		if ($data['prequal_comps'])
		{
			$data['prequal_comps'] = implode(',', $data['prequal_comps']);
		}
		foreach(['prequal_extra','quota_extra','gruppen'] as $name)
		{
			if (strlen($data[$name]) > $this->db->get_column_attribute($name, $this->table_name, $this->app, 'precision'))
			{
				if ($intern)	// change internal data back, before throwing
				{
					parent::data2db(null);
					$this->db2data();
				}
				throw new Api\Exception\WrongUserinput(lang("Too much data for column '%1' (%2)!", $name,
					$name === 'prequal_extra' ? lang('Prequalified competitiors for startlist') :
						($name === 'gruppen' ? lang('Catgories') : lang('Quota by federation or category'))));
			}
		}
		return parent::data2db($intern ? null : $data);	// important to use null, if $intern!
	}

	/**
	 * Get the number of athlets prequalified from the ranking, depending on the cat
	 *
	 * @param int $cat integer GrpId
	 * @param array $comp =null competition data, default use $this->data
	 * @return integer
	 */
	function prequal_ranking($cat=null,array $comp=null)
	{
		if (is_null($comp))
		{
			$comp =& $this->data;
		}
		if ($cat && is_array($comp['prequal_extra']))
		{
			foreach($comp['prequal_extra'] as $pair)
			{
				if ($pair['cat'] == $cat) return $pair['num'];
			}
		}
		return $comp['prequal_ranking'];
	}

	/**
	 * Get the quota, depending on federation/nation and cat
	 *
	 * @param int|string $fed integer fed_id or string 3-char nation
	 * @param int $cat integer GrpId
	 * @param array $comp =null competition data, default use $this->data
	 * @param array|null &$multi_cat on return matching rules for multiple categories: [{fed:$fed,cat:[$c1,$c2,...],num:$quota}, ...]
	 * @return integer
	 */
	function quota($fed=null,$cat=null,array $comp=null, array &$multi_cat=null)
	{
		$multi_cat = [];
		if (is_null($comp))
		{
			$comp =& $this->data;
		}
		if (($fed || $cat) && is_array($comp['quota_extra']))
		{
			foreach($comp['quota_extra'] as $data)
			{
				if (is_array($data['cat']))
				{
					if (count($data['cat']) > 1)
					{
						if ($cat && in_array($cat, $data['cat'], false)) $multi_cat[] = $data;
						continue;
					}
					$data['cat'] = $data['cat'][0];
				}
				if ($fed == $data['fed'] && $cat == $data['cat'])
				{
					$quota = $data['num'];
					break;	// exact match, no further check
				}
				if (!isset($quota) && ($fed && $fed == $data['fed'] && !$data['cat'] || $cat && $cat == $data['cat'] && !$data['fed']))
				{
					$quota = $data['num'];
				}
			}
		}
		if (!isset($quota))
		{
			$quota = $fed && $fed == $comp['host_nation'] ? $comp['host_quota'] : $comp['quota'];
		}
		//echo "<p>".__METHOD__."($fed,$cat,".array2string($comp).") = $quota</p>\n";
		return $quota;
	}

	/**
	 * Get the maximum quota for a federation
	 *
	 * @param int|string $fed integer fed_id or string 3-char nation
	 * @param array $comp =null competition data, default use $this->data
	 * @return int
	 */
	function max_quota($fed=null,array $comp=null)
	{
		if (is_null($comp))
		{
			$comp =& $this->data;
		}
		$quota = $fed && $fed == $comp['host_nation'] ? $comp['host_quota'] : $comp['quota'];

		foreach((array)$comp['quota_extra'] as $data)
		{
			if ($quota < $data['num'] && $fed == $data['fed'])
			{
				$quota = $data['num'];
			}
		}
		//echo "<p>".__METHOD__."($fed,".array2string($comp).") = $quota</p>\n";
		return $quota;
	}

	/**
	 * Read a competition, reimplemented to allow to pass WetId or rkey instead of the array
	 *
	 * Do some caching to avoid reading the same competition multiple times;
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
			// some caching in $this->data
			if (is_numeric($keys))
			{
				if ((int)$keys == $this->data['WetId']) return $this->data;
				$keys = array('WetId' => (int) $keys);
			}
			else
			{
				if ($keys === $this->data['rkey']) return $this->data;
				$keys = array('rkey' => $keys);
			}
		}
		if (!$keys)
		{
			return false;
		}
		return parent::read($keys,$extra_cols,$join);
	}

	/**
	 * Search for competitions
	 *
	 * reimplmented from Api\Storage\Base unset/not use some columns in the search
	 */
	function &search($criteria,$only_keys=True,$order_by='',$extra_cols='',$wildcard='',$empty=False,$op='AND',$start=false,$filter=null,$join='',$need_full_no_count=false)
	{
		if (is_array($criteria))
		{
			unset($criteria['pkte']);	// is allways set
			if (!$criteria['feld_pkte']) unset($criteria['feld_pkte']);
			unset($criteria['open']);
			if (!$criteria['serie']) unset($criteria['serie']);
			if ($criteria['rkey']) $criteria['rkey'] = strtoupper($criteria['rkey']);
		}
		//$this->debug = 1;
		return parent::search($criteria,$only_keys,$order_by,$extra_cols,$wildcard,$empty,$op,$start,$filter,$join,$need_full_no_count);
	}

	/**
	 * get the $num-last competition before $date in a given category, calendar and cup
	 *
	 * ToDo: Query is very slow, propably because of DISTINCT *
	 *
	 * @param string $date in 'Y-m-d' format
	 * @param array/int $cats (array of) cat-id's (GrpId)
	 * @param string $nation of the competition (calendar)
	 * @param int $cup =0 id (SerId) of cup or 0 for no cup (then comp.faktor has to be > 0)
	 * @param int $num =1 how many competitions back are searched, default 1 = last competition
	 * @return array/boolean array with competition or false on error (eg. none found)
	 */
	function last_comp($date,$cats,$nation,$cup=0,$num=1)
	{
		$query = array(
			'nation' => $nation,
		);
		if ($cup)
		{
			$query['serie'] = $cup;
		}
		else
		{
			$query[] = 'faktor > 0.0';
		}
		if ($cats)
		{
			$query[] = $this->db->expression($this->result_table,array('GrpId' => $cats));
		}
		$ret = $this->search(array(),$this->table_name.'.*',$this->table_name.'.datum DESC','','',false,
			'AND',array($num-1,1),$query,",$this->result_table WHERE $this->table_name.WetId=$this->result_table.WetId
			AND $this->result_table.platz > 0 AND $this->table_name.datum <= ".$this->db->quote($date));

		if ($this->debug) echo "<p>competition::last_comp('$date',".print_r($cats,true).",'$nation',$cup)=".$ret[0]['rkey']."</p>\n";
		//error_log(__METHOD__."('$date',".array2string($cats).",'$nation',$cup)=".$ret[0]['rkey']);
		return $ret ? $ret[0] : false;
	}

	/**
	 * get the next competition after $date in the _same_ year in a given category, calendar and cup
	 *
	 * ToDo: change function/query to work for non-MySQL DB's too
	 *
	 * @param string $date in 'Y-m-d' format
	 * @param array|string $cats (array of) cat-rkey's
	 * @param string $nation of the competition (calendar)
	 * @param int $cup =0 id (SerId) of cup or 0 for no cup (then comp.faktor has to be > 0)
	 * @param boolean $ignore_factor_0 =true true competition with a factor of 0 are ignored, false all comp. are returned
	 * @return array|boolean array with competition or false on error (eg. none found)
	 */
	function next_comp($date,$cats,$nation,$cup=0,$ignore_factor_0=true,$limit_this_year=false)
	{
		$where = array(
			'nation' => $nation,
			'datum > '.$this->db->quote($date),
		);
		if ($limit_this_year)
		{
			$where[] = 'datum <= '.$this->db->quote((int)$date.'-12-31');
		}
		if ($cup)
		{
			$where['serie'] = $cup;
		}
		elseif ($ignore_factor_0)
		{
			$where[] = 'faktor > 0.0';
		}
		if ($cats)
		{
			$where[] = $this->check_in_cats($cats);
		}
		$ret = $this->search(array(), false, 'datum ASC', '', '', false, 'AND', array(0,1), $where);

		if ($this->debug) error_log(__METHOD__."('$date',".print_r($cats,true).",'$nation',$cup) = ".array2string($ret ? $ret[0]['rkey'] : $ret));

		return $ret ? $ret[0] : false;
	}

	/**
	 * get the next competition after $date in the _same_ year in a given category, calendar and cup
	 *
	 * @param string $date in 'Y-m-d' format
	 * @param array|string $cats (array of) cat-rkey's
	 * @param string $nation of the competition (calendar)
	 * @param int $cup =0 id (SerId) of cup or 0 for no cup (then comp.faktor has to be > 0)
	 * @return array|boolean array with competition or false on error (eg. none found)
	 */
	function next_comp_this_year($date,$cats,$nation,$cup=0)
	{
		return $this->next_comp($date, $cats, $nation, $cup, true, true);
	}

	/**
	 * SQL to check if competition has one of the given cats
	 *
	 * old competitions might have regular expressions of the cats attending them
	 *
	 * @static
	 * @param array/string $cats cat-rkeys
	 * @return string
	 */
	function check_in_cats($cats)
	{
		$or_query = array();
		foreach((array)$cats as $rkey)
		{
			$or_query[] = 'find_in_set('.$this->db->quote($rkey).",IF(INSTR(gruppen,'@'),LEFT(gruppen,INSTR(gruppen,'@')-1),gruppen))";
			// old competitions might have regular expressions of the cats attending them
			$or_query[] = $this->db->quote($rkey). " REGEXP IF(INSTR(gruppen,'@'),LEFT(gruppen,INSTR(gruppen,'@')-1),gruppen)";
		}
		return '('.implode(' OR ',$or_query).')';
	}

	/**
	 * get the names of all or certain competitions, eg. to use in a selectbox
	 *
	 * @param array $keys array with col => value pairs to limit name-list, like for so_sql.search
	 * @param int $rkeys =0 0: WetId=>name, 1: rkey=>name, 2: rkey=>rkey: name, 3: WetId=>date: name
	 * @param string $sort ='datum DESC'
	 * @return array with comp-names as specified in $rkeys
	 */
	function names($keys=array(),$rkeys=0,$sort='datum DESC')
	{
		if (!preg_match('/^[a-z]+ ?(asc|desc)?$/i',$sort)) $sort = 'datum DESC';

		$names = array();
		foreach((array) $this->search(array(),'WetId,rkey,name,datum',$sort,'','',false,'AND',false,$keys) as $data)
		{
			$name = strip_tags($data['name']);
			switch($rkeys)
			{
				case 2:
					$name = $data['rkey'].': '.$name;
					break;
				case 3:
					$name = Api\DateTime::to($data['datum'], true).': '.$name;
					break;
			}
			$names[in_array($rkeys, [1, 2]) ? $data['rkey'] : $data['WetId']] = $name;
		}
		return $names;
	}

	/**
	 * get the nations of the competitions
	 *
	 * @return array with nations as key and value
	 */
	function nations()
	{


		$nations = array();
		foreach($this->db->select($this->table_name,'DISTINCT nation','nation IS NOT NULL',__LINE__,__FILE__) as $row)
		{
			$nat = $row['nation'];
			$nations[$nat] = $nat;
		}
		return $nations;
	}

	/**
	 * checks if a competition already has results recorded
	 *
	 * @param int/array $keys WetId or array with keys of competition to check
	 * @param int $GrpId =null optional GrpId to only check for a certain category
	 * @return boolean
	 */
	function has_results($keys,$GrpId=null)
	{
		if (is_array($keys))
		{
			$data_backup = $this->data;
			$keys = $this->read($keys);
			$this->data = $data_backup;

			if (!$keys)
			{
				return false;
			}
		}
		$WetId = !is_array($keys) ? $keys : $keys['WetId'];

		static $has_results = array();	// little bit of caching

		if (isset($has_results[$WetId][(string)$GrpId])) return $has_results[$WetId][(string)$GrpId];

		$check = array('WetId' => $WetId);
		if ($GrpId) $check['GrpId'] = $GrpId;

		return $has_results[$WetId][(string)$GrpId] = Base::getInstance()->result->has_results($check);
	}

	/**
	 * Image mime-types and file-name (extension) regular expression
	 *
	 * @var array
	 */
	static $image_types = array(
		'image/gif'  => '\.gif$',
		'image/jpeg' => '\.jpe?g$',
		'image/png'  => '\.png$',
	);

	/**
	 * Check if (uploaded) file contains an image (for web display)
	 *
	 * @param string $filename
	 * @param string $mime =null mime-type
	 * @return boolean|string false or default extension (incl. leading '.')
	 */
	static function is_image($filename,$mime=null)
	{
		$extension = false;
		foreach(self::$image_types as $mime_type => $file_regexp)
		{
			if (strtolower($mime) == $mime_type || preg_match('/'.$file_regexp.'$/i',$filename))
			{
				$extension = str_replace(array('\\','?','$'),'',$file_regexp);
				break;
			}
		}
		//echo "<p>".__METHOD__."('$filename','$mime') = '$extension'</p>\n";
		return $extension;
	}


	/**
	 * path of a pdf attachment of a certain type for the competition in data
	 *
	 * @param string $type 'info', 'startlist', 'result'
	 * @param array $data competition
	 * @param string $rkey rkey to use, default ''=use the one from our internal data
	 * @param string $extension =null extension of the file
	 * @return string the path
	 */
	function attachment_path($type,$data=null,$rkey='',$extension=null)
	{
		if (!$data) $data =& $this->data;
		if (!$rkey) $rkey = $data['rkey'];

		if (is_numeric(substr($rkey,0,2)))
		{
			$year = (int) $rkey + ((int) $rkey < 80 ? 2000 : 1900);
		}
		else
		{
			$year = substr($this->data['datum'],0,4);
		}
		if ($data['nation'] === 'NULL') $data['nation'] = '';

		$vfs_path = $this->vfs_pdf_dir.$data['nation'].'/'.$year.'/'.$this->attachment_prefixes[$type].$rkey;

		if ($type != 'logo' && $type != 'sponsors')
		{
			$extension = '.pdf';
		}
		elseif (!$extension)
		{
			foreach(self::$image_types as $ext_regexp)
			{
				$ext = str_replace(array('\\','?','$'),'',$ext_regexp);

				if (Vfs::stat($vfs_path.$ext))
				{
					$extension = $ext;
					break;
				}
			}
		}
		//echo "<p>".__METHOD__."('$type',,'$rkey','$extension') = '$vfs_path$extension'</p>\n";
		return $vfs_path.$extension;
	}

	/**
	 * Checks and returns links to the attached files
	 *
	 * @param array $data a given competition, default use the already read one
	 * @param boolean $return_link =false not used anymore
	 * @param boolean $only_pdf =true return only pdfs (default) or the logos too
	 * @param boolean|string $add_host =false true: return attachment links including a host, not just a path, default not
	 * 	or string with url to prefix it, eg. http://example.com (without trailing slash!)
	 * @return boolean/array links for the keys: info, startlist, result or false on error
	 */
	function attachments($data=null,$return_link=false,$only_pdf=true,$add_host=false)
	{
		unset($return_link);
		if (!$data) $data =& $this->data;

		$attachments = false;
		foreach(array_keys($this->attachment_prefixes) as $type)
		{
			if ($only_pdf && ($type == 'logo' || $type == 'sponsors')) continue;

			$vfs_path = $this->attachment_path($type,$data);
			if (Vfs::stat($vfs_path))
			{
				$attachments[$type] = Vfs::download_url($vfs_path);
				if ($add_host && $attachments[$type][0] == '/' ||
					// might need to replace domain with CDN domain
					is_string($add_host) && parse_url($attachments[$type], PHP_URL_HOST) == $_SERVER['HTTP_HOST'])
				{
					if ($add_host === true) $add_host = ($_SERVER['HTTPS'] ? 'https://' : 'http://').$_SERVER['HTTP_HOST'];

					if ($attachments[$type][0] != '/')
					{
						$parsed = parse_url($attachments[$type]);
						$attachments[$type] = $parsed['path'].($parsed['query'] ? '?'.$parsed['query'] : '');
					}
					$attachments[$type] = $add_host.$this->vfs_pdf_url.$attachments[$type];
				}
			}
		}
		return $attachments;
	}

	/**
	 * attaches one or more files as info, startlist or result
	 *
	 * @param array $files full path to files for the keys info, startlist and result
	 * @param string &$error_msg error-messaage if returning false
	 * @param array $keys to read/use a given competitions, default use the already read one
	 * @param string $extension ='.pdf' extension of file to attach
	 * @return boolean true on success, false otherwise
	 */
	function attach_files($files,&$error_msg,$keys=null,$extension='.pdf')
	{
		if ($keys && !$this->read($keys)) return false;

		Vfs::$is_root = true;		// Acl is based on edit rights for the competition and NOT the vfs rights
		foreach($files as $type => $path)
		{
			if (!file_exists($path) || !is_readable($path))
			{
				$error_msg = lang("'%1' does not exist or is not readable by the webserver !!!",$path);
				Vfs::$is_root = false;
				return false;
			}
			$vfs_path = $this->attachment_path($type,null,null,$extension);

			// check and evtl. create the year directory
			if (!Vfs::is_dir($dir = dirname($vfs_path)) &&
				!Vfs::mkdir($dir,0777,STREAM_MKDIR_RECURSIVE))
			{
				$error_msg = lang("Can not create directory '%1' !!!",$dir);
				Vfs::$is_root = false;
				return false;
			}
			$ok = false;
			if ((!($from_fp = fopen($path,'r')) || !($to_fp = Vfs::fopen($vfs_path,'w')) ||
				!($ok = stream_copy_to_stream($from_fp,$to_fp) !== false)))
			{
				$error_msg = lang("Can not move '%1' to %2 !!!",$path,$vfs_path);
			}
	 		if ($from_fp) fclose($from_fp);
	 		if ($to_fp)   fclose($to_fp);

	 		if (!$ok) return false;
		}
		Vfs::$is_root = false;

		return true;
	}

	/**
	 * removes an attached file of $type = info, startlist or result
	 *
	 * @param string $type 'info', 'startlist', 'result'
	 * @param array $keys to read/use a given competitions, default use the already read one
	 * @return boolean true on success, false otherwise
	 */
	function remove_attachment($type,$keys=null)
	{
		if ($keys && !$this->read($keys)) return false;

		Vfs::$is_root = true;		// Acl is based on edit rights for the competition and NOT the vfs rights
		$Ok = Vfs::remove($this->attachment_path($type));
		Vfs::$is_root = false;

		return $Ok;
	}

	/**
	 * renames the attachments to a new rkey
	 *
	 * @param string $old_rkey
	 * @param array $keys to read/use a given competitions, default use the already read one
	 * @return boolean true on success, false otherwise
	 */
	function rename_attachments($old_rkey,$keys=null)
	{
		//echo "<p>competitions::rename_attachments('$old_rkey',".print_r($keys,true).") data[rkey]='".$this->data['rkey']."'</p>\n";
		if (!$old_rkey || $keys && !$this->read($keys)) return false;

		$ok = true;
		Vfs::$is_root = true;		// Acl is based on edit rights for the competition and NOT the vfs rights
		foreach(array_keys($this->attachment_prefixes) as $type)
		{
			$old_path = $this->attachment_path($type,null,$old_rkey);
			$new_path = $this->attachment_path($type,null,null,self::is_image($old_path));
			//echo "$old_path --> $new_path<br>\n";

			if ($old_path != $new_path && Vfs::stat($old_path) &&
				!Vfs::rename($old_path,$new_path))
			{
				$ok = false;
			}
		}
		Vfs::$is_root = false;

		return $ok;
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

	/**
	 * Format competition date-span using datum and duration fields and user-prefs for date-format
	 *
	 * @param array $comp =null default use internal data
	 * @return string
	 */
	function datespan(array $comp=null)
	{
		if (is_null($comp))
		{
			$comp = $this->data;
		}
		if (!($format = $GLOBALS['egw_info']['user']['preferences']['common']['dateformat'])) $format = 'Y-m-d';

		list($y,$m,$d) = explode('-',$comp['datum']);

		// non-numeric duration, eg. "Feburar" or "end" --> return it with year appended
		if ($comp['duration'] && !is_numeric($comp['duration']))
		{
			return $comp['duration'].' '.$y;
		}
		// default duration since 2001 is 2 days, before it was 1 day
		$duration = $comp['duration'] ? $comp['duration'] : ($y >= 2001 ? 2 : 1);

		list($end_y,$end_m,$end_d) = explode('-',date('Y-m-d',mktime(0,0,0,$m,$d+$duration-1,$y)));
		//echo "format=$format, datum=$comp[datum], duration=$duration, end=$end_y-$end_m-$end_d";
		// end is in same month --> just replace d with d - $end_$
		if ($m == $end_m)
		{
			if ($duration > 1) $format = str_replace('d','d'.($duration > 2 ? ' - '.$end_d : ' / '.$end_d),$format);

			return date($format,mktime(0,0,0,$m,$d,$y));
		}
		// end is in different month
		$sep = $format[1];
		$fmts = explode($sep,$format);
		if (($year_first = strtolower($format[0]) == 'y'))
		{
			$year_fmt = array_shift($fmts);
		}
		else
		{
			$year_fmt = array_pop($fmts);
		}
		$dm_fmt = implode($sep,$fmts);
		$dm = date($dm_fmt.($year_first?'':$sep),mktime(0,0,0,$m,$d,$y)).' - '.
			date($dm_fmt.($year_first?$sep:''),mktime(0,0,0,$end_m,$end_d,$end_y));
		if ($year_fmt == 'y') $end_y = sprintf('%02d',$end_y % 100);

		return $year_first ? $end_y.$sep.$dm : $dm.$sep.$end_y;
	}

	/**
	 * Checks if given athlete is allowed to start, because of his federation and the open-ness of the comp
	 *
	 * @param array $athlete values for keys nation, fed_id, fed_parent, acl_fed_id
	 * @param array $comp =null default use internal data
	 * @return boolean true if athlete is allowed to register, false if not
	 */
	function open_comp_match(array $athlete, array $comp=null)
	{
		if (is_null($comp))
		{
			$comp = $this->data;
		}
		switch($comp['open_comp'])
		{
			case 3:	// international
				$ret = true;
				break;

			case 2: // D,A,CH
				$ret = in_array($athlete['nation'],array('GER','AUT','SUI'));
				break;

			case 1:	// national
				$ret = $athlete['nation'] == $comp['nation'];
				break;

			case 0:	// closed to comp. federation
				if ($comp['fed_id'])
				{
					$ret = $comp['fed_id'] == $athlete['fed_id'] ||
						$athlete['fed_parent'] && $athlete['fed_parent'] == $comp['fed_id'] ||	// Landesverband
						$athlete['acl_fed_id'] && $athlete['acl_fed_id'] == $comp['fed_id'];	// Regionalzentrum
					break;
				}
				else
				{
					$ret = !$comp['nation'] || $athlete['nation'] == $comp['nation'];
				}
				break;
		}
		//error_log(__METHOD__."({'$athlete[nachname] $athlete[vorname] ($athlete[nation])', fed_id=$athlete[fed_id], lv=$athlete[fed_parent], rz=$athlete[acl_fed_id]}, {'$comp[name]', nation='$comp[nation]', fed_id=$comp[fed_id], open_comp=$comp[open_comp]}) returning ".array2string($ret));
		return $ret;
	}

	/**
	 * Return number of preselected for a given category
	 *
	 * @param int $cat
	 * @param array $quali_preselected =null
	 * @return int
	 */
	function quali_preselected($cat, array $quali_preselected=null)
	{
		if (is_null($quali_preselected)) $quali_preselected = $this->data['quali_preselected'];

		$preselected = 0;
		foreach((array)$quali_preselected as $pre)
		{
			if ($pre['cat'] == $cat || !$pre['cat'])
			{
				$preselected = $pre['num'];
				break;
			}
		}
		//error_log(__METHOD__."($cat, ".array2string($quali_preselected).") returning $preselected");
		return $preselected;
	}
}