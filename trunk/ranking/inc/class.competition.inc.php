<?php
/**
 * eGroupWare digital ROCK Rankings - competition storage object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2006 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$ 
 */

require_once(EGW_INCLUDE_ROOT . '/etemplate/inc/class.so_sql.inc.php');

/**
 * competition object
 */
class competition extends so_sql
{
	var $non_db_cols = array(	// fields in data, not (direct) saved to the db
		'durartion' => 'duration'
	);
	var $charset,$source_charset;
	/**
	 * reference to the category object
	 *
	 * @var category
	 */
	var $cats;
	/**
	 * @var array $attachment_prefixes prefixes of the rkey for the different attachments
	 */
	var $attachment_prefixes = array(
		'info'      => '',
		'startlist' => 'S',
		'result'    => 'R',
	);
	var $vfs_pdf_dir = '';
	var $result_table = 'Results';

	/**
	 * constructor of the competition class
	 */
	function competition($source_charset='',$db=null,$vfs_pdf_dir='')
	{
		//$this->debug = 1;
		$this->so_sql('ranking','Wettkaempfe',$db);	// call constructor of extended class
		
		if ($source_charset) $this->source_charset = $source_charset;
		
		$this->charset = $GLOBALS['egw']->translation->charset();

		foreach(array(
				'cats'  => 'category',
			) as $var => $class)
		{
			$egw_name = /*'ranking_'.*/$class;
			if (!isset($GLOBALS['egw']->$egw_name))
			{
				$GLOBALS['egw']->$egw_name = CreateObject('ranking.'.$class,$this->source_charset,$this->db);
			}
			$this->$var = $GLOBALS['egw']->$egw_name;
		}
		if ($vfs_pdf_dir) $this->vfs_pdf_dir = $vfs_pdf_dir;
	}

	/**
	 * initializes an empty competition
	 *
	 * reimplemented from so_sql to set some defaults
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
		if (!is_array($data))
		{
			$data =& $this->data;
		}
		if (count($data) && $this->source_charset)
		{
			$data = $GLOBALS['egw']->translation->convert($data,$this->source_charset);
		}
		if ($data['name'])
		{
			$data['name'] = strip_tags($data['name']);	// some use tags for calendar formatting
		}
		list($data['gruppen'],$data['duration']) = explode('@',$data['gruppen']);
		if ($data['gruppen'])
		{
			$data['gruppen'] = $this->cats->cat_rexp2rkeys($data['gruppen']);
		}
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
			$data['date_span'] = (int)$d.'. '.(date('m',$start) != $m ? lang(date('F',$m)) : '').
				($data['duration'] > 1 ? ' - '.(int)date('d',$end).'. ' : '').lang(date('F',$end)).' '.$y;
			//echo "<p>y=$y, m=$m, d=$d, duration=$data[duration], start=$start, end=$end=$data[date_end], span=$data[date_span]</p>\n";
		}
		return $data;
	}

	/**
	 * changes the data from our work-format to the db-format
	 * @param array $data if given works on that array and returns result, else works on internal data-array
	 * @return array
	 */
	function data2db($data=0)
	{
		if (!is_array($data))
		{
			$data =& $this->data;
		}
		if (is_array($data['gruppen']))
		{
			$data['gruppen'] = implode(',',$data['gruppen']);
		}
		if ($data['duration']) $data['gruppen'] .= '@' . $data['duration'];
		if ($data['pkt_bis'])  $data['pkt_bis']  = $data['pkt_bis']  == 100 ? '' : 100.0*$data['pkt_bis'];
		if ($data['feld_bis']) $data['feld_bis'] = $data['feld_bis'] == 100 ? '' : 100.0*$data['feld_bis'];
		if ($data['rkey'])     $data['rkey'] = strtoupper($data['rkey']);
		if ($data['nation'] && !is_array($data['nation']))   $data['nation'] = $data['nation'] == 'NULL' ? null : strtoupper($data['nation']);
		if (isset($data['pkte']) && !$data['pkte']) $data['pkte'] = null;
		if (isset($data['feld_pkte']) && !$data['feld_pkte']) $data['feld_pkte'] = null;
		if (isset($data['judges']) && is_array($data['judges']))
		{
			$data['judges'] = implode(',',$data['judges']);
		}
		if (count($data) && $this->source_charset)
		{
			$data = $GLOBALS['egw']->translation->convert($data,$this->charset,$this->source_charset);
		}
		return $data;
	}

	/**
	 * Read a competition, reimplemented to allow to pass WetId or rkey instead of the array
	 *
	 * @param mixed $keys array with keys, or WetId or rkey
	 * @param string/array $extra_cols string or array of strings to be added to the SELECT, eg. "count(*) as num"
	 * @param string $join='' sql to do a join, added as is after the table-name, eg. ", table2 WHERE x=y" or 
	 * @return array/boolean array with competition or false on error (eg. not found)
	 */
	function read($keys,$extra_cols='',$join='')
	{
		if ($keys && !is_array($keys))
		{
			$keys = is_numeric($keys) ? array('WetId' => (int) $keys) : array('rkey' => $keys);
		}
		return parent::read($keys,$extra_cols,$join);
	}

	/**
	 * Search for competitions
	 *
	 * reimplmented from so_sql unset/not use some columns in the search
	 */
	function search($criteria,$only_keys=True,$order_by='',$extra_cols='',$wildcard='',$empty=False,$op='AND',$start=false,$filter=null,$join='')
	{
		unset($criteria['pkte']);	// is allways set
		if (!$criteria['feld_pkte']) unset($criteria['feld_pkte']);
		unset($criteria['open']);
		if (!$criteria['serie']) unset($criteria['serie']);
		if ($criteria['rkey']) $criteria['rkey'] = strtoupper($criteria['rkey']);

		//$this->debug = 1;
		return so_sql::search($criteria,$only_keys,$order_by,$extra_cols,$wildcard,$empty,$op,$start,$filter,$join);
	}
	
	/**
	 * get the $num-last competition before $date in a given category, calendar and cup
	 *
	 * ToDo: Query is very slow, propably because of DISTINCT *
	 *
	 * @param string $date in 'Y-m-d' format
	 * @param array/int $cats (array of) cat-id's (GrpId)
	 * @param string $nation of the competition (calendar)
	 * @param int $cup=0 id (SerId) of cup or 0 for no cup (then comp.faktor has to be > 0)
	 * @param int $num=1 how many competitions back are searched, default 1 = last competition
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
		$ret = $this->search(array(),false,$this->table_name.'.datum DESC','','',false,
			'AND',array($num-1,1),$query,",$this->result_table WHERE $this->table_name.WetId=$this->result_table.WetId 
			AND $this->table_name.datum <= ".$this->db->quote($date));
		
		if ($this->debug) echo "<p>competition::last_comp('$date',".print_r($cats,true).",'$nation',$cup)=".$ret[0]['rkey']."</p>\n";
			
		return $ret ? $ret[0] : false;
	}
	
	/**
	 * get the next competition after $date in the _same_ year in a given category, calendar and cup
	 *
	 * ToDo: change function/query to work for non-MySQL DB's too
	 *
	 * @param string $date in 'Y-m-d' format
	 * @param array/string $cats (array of) cat-rkey's
	 * @param string $nation of the competition (calendar)
	 * @param int $cup=0 id (SerId) of cup or 0 for no cup (then comp.faktor has to be > 0)
	 * @return array/boolean array with competition or false on error (eg. none found)
	 */
	function next_comp_this_year($date,$cats,$nation,$cup=0)
	{
		// old competitions might have regular expressions of the cats attending them
		$or_query = array();
		foreach($cats as $rkey)
		{
			$or_query[] = 'find_in_set('.$this->db->quote($rkey).",IF(INSTR(gruppen,'@'),LEFT(gruppen,INSTR(gruppen,'@')-1),gruppen))";
			$or_query[] = $this->db->quote($rkey). " REGEXP IF(INSTR(gruppen,'@'),LEFT(gruppen,INSTR(gruppen,'@')-1),gruppen)";
		}
		$ret = $this->search(array(),false,'datum ASC','','',false,'AND',array(0,1),array(
			'nation' => $nation,
			'datum > '.$this->db->quote($date),
			'datum <= '.$this->db->quote((int)$date.'-12-31'),
			$cup ? 'serie='.(int)$cup : 'faktor > 0.0',
			$this->check_in_cats($cats),			
		));
		if ($this->debug) echo "<p>competition::next_comp_this_year('$date',".print_r($cats,true).",'$nation',$cup) = '$ret[rkey]'</p>\n";
		
		return $ret ? $ret[0] : false;
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
			$or_query[] = $this->db->quote($rkey). " REGEXP IF(INSTR(gruppen,'@'),LEFT(gruppen,INSTR(gruppen,'@')-1),gruppen)";
		}
		return '('.implode(' OR ',$or_query).')';
	}

	/**
	 * get the names of all or certain competitions, eg. to use in a selectbox
	 *
	 * @param array $keys array with col => value pairs to limit name-list, like for so_sql.search
	 * @param int $rkeys=0 0: WetId=>name, 1: rkey=>name, 2: rkey=>rkey: name
	 * @param string $sort='datum DESC' 
	 * @return array with comp-names as specified in $rkeys
	 */
	function names($keys=array(),$rkeys=0,$sort='datum DESC')
	{
		if (!preg_match('/^[a-z]+ ?(asc|desc)?$/i',$sort)) $sort = 'datum DESC';

		$names = array();
		foreach((array) $this->search(array(),'WetId,rkey,name',$sort,'','',false,'AND',false,$keys) as $data)
		{
			$names[$rkeys ? $data['rkey'] : $data['WetId']] = ($rkeys == 2 ? $data['rkey'].': ' : '').
				strip_tags($data['name']);
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
		$this->db->select($this->table_name,'DISTINCT nation','nation IS NOT NULL',__LINE__,__FILE__);
		
		$nations = array();
		while($this->db->next_record())
		{
			$nat = $this->db->f(0);
			$nations[$nat] = $nat;
		}
		return $nations;
	}
	
	/**
	 * checks if a competition already has results recorded
	 *
	 * @param int/array $keys WetId or array with keys of competition to check
	 * @param int $GrpId=null optional GrpId to only check for a certain category
	 * @return boolean 
	 */
	function has_results($keys,$GrpId=null)
	{
		if (is_array($keys) || !$keys['WetId'])
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
		
		return $has_results[$WetId][(string)$GrpId] = ExecMethod('ranking.result.has_results',$check);
	}
	
	/**
	 * path of a pdf attachment of a certain type for the competition in data
	 *
	 * @param string $type 'info', 'startlist', 'result'
	 * @param array $data competition
	 * @param string $rkey rkey to use, default ''=use the one from our internal data
	 * @return string the path
	 */	 
	function attachment_path($type,$data=null,$rkey='')
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
		return $this->vfs_pdf_dir.$data['nation'].'/'.$year.'/'.$this->attachment_prefixes[$type].$rkey.'.pdf';
	}		
		
	/**
	 * Checks and returns links to the attached files
	 *
	 * @param array $data a given competition, default use the already read one
	 * @param boolean $return_link return links or arrays with vars for the link-function, default false=array
	 * @return boolean/array links for the keys: info, startlist, result or false on error
	 */
	function attachments($data=null,$return_link=false)
	{
		if (!$data) $data =& $this->data;
			
		if (!isset($GLOBALS['egw']->vfs))
		{
			$GLOBALS['egw']->vfs = CreateObject('phpgwapi.vfs');
		}
		$attachments = false;
		foreach($this->attachment_prefixes as $type => $prefix)
		{
			$vfs_path = $this->attachment_path($type,$data);
			if ($GLOBALS['egw']->vfs->file_exists(array(
					'string' => $vfs_path,
					'relatives' => RELATIVE_ROOT,
				)))
			{
				$parts = explode('/',$vfs_path); 
				$file = array_pop($parts);
				$path = implode('/',$parts);
				$linkdata = array(
					'menuaction' => 'filemanager.uifilemanager.view',
					'path'       => base64_encode($path),
					'file'       => base64_encode($file),
				);
				if ($return_link)
				{
					$linkdata = $GLOBALS['egw']->link('/index.php',$linkdata);
					if ($linkdata{0} == '/')
					{
						$linkdata = ($_SERVER['HTTPS'] ? 'https://' : 'http://').$_SERVER['SERVER_NAME'].$linkdata;
					}
				}
				$attachments[$type] = $linkdata;
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
	 * @return boolean true on success, false otherwise
	 */
	function attach_files($files,&$error_msg,$keys=null)
	{
		if ($keys && !$this->read($keys)) return false;
		
		if (!isset($GLOBALS['egw']->vfs))
		{
			$GLOBALS['egw']->vfs = CreateObject('phpgwapi.vfs');
		}
		foreach($files as $type => $path)
		{
			if (!file_exists($path) || !is_readable($path)) 
			{
				$error_msg = lang("'%1' does not exist or is not readable by the webserver !!!",$path);
				return false;
			}
			$vfs_path = $this->attachment_path($type);
			
			// check and evtl. create the year directory
			$GLOBALS['egw']->vfs->override_acl = 1;		// acl is based on edit rights for the competition and NOT the vfs rights
			if (!$GLOBALS['egw']->vfs->file_exists($vfs_dir = array(
					'string' => dirname($vfs_path),
					'relatives' => RELATIVE_ROOT,
				)) && !$GLOBALS['egw']->vfs->mkdir($vfs_dir)) 
			{
				$error_msg = lang("Can not create directory '%1' !!!",dirname($vfs_path));
				$GLOBALS['egw']->vfs->override_acl = 0;
				return false;
			}
			if (!$GLOBALS['egw']->vfs->mv(array(
					'from' => $path,
					'to'   => $vfs_path,
					'relatives' => array(RELATIVE_NONE|VFS_REAL,RELATIVE_ROOT),
				)))
			{
				$error_msg = lang("Can not move '%1' to %2 !!!",$path,$vfs_path);
				$GLOBALS['egw']->vfs->override_acl = 0;
				return false;
			}
			$GLOBALS['egw']->vfs->override_acl = 0;
		}
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
		
		if (!isset($GLOBALS['egw']->vfs))
		{
			$GLOBALS['egw']->vfs = CreateObject('phpgwapi.vfs');
		}
		$vfs_path_arr = array(
			'string' => $this->attachment_path($type),
			'relatives' => RELATIVE_ROOT,
		);
		$GLOBALS['egw']->vfs->override_acl = 1;		// acl is based on edit rights for the competition and NOT the vfs rights
		$Ok = $GLOBALS['egw']->vfs->file_exists($vfs_path_arr) && $GLOBALS['egw']->vfs->rm($vfs_path_arr);
		$GLOBALS['egw']->vfs->override_acl = 0;

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
		
		if (!isset($GLOBALS['egw']->vfs))
		{
			$GLOBALS['egw']->vfs = CreateObject('phpgwapi.vfs');
		}
		$ok = true;
		$GLOBALS['egw']->vfs->override_acl = 1;		// acl is based on edit rights for the competition and NOT the vfs rights
		foreach($this->attachment_prefixes as $type => $prefix)
		{
			$old_path = $this->attachment_path($type,null,$old_rkey);
			$new_path = $this->attachment_path($type);
			//echo "$old_path --> $new_path<br>\n";

			if ($old_path != $new_path && $GLOBALS['egw']->vfs->file_exists(array(
					'string' => $old_path,
					'relatives' => RELATIVE_ROOT,
				)) && !$GLOBALS['egw']->vfs->mv(array(
					'from' => $old_path,
					'to'   => $new_path,
					'relatives' => array(RELATIVE_ROOT,RELATIVE_ROOT),
				)))
			{
				$ok = false;
			}
		}
		$GLOBALS['egw']->vfs->override_acl = 0;

		return $ok;
	}
}