<?php
/**************************************************************************\
* eGroupWare - digital ROCK Rankings - Competition Object                  *
* http://www.egroupware.org, http://www.digitalROCK.de                     *
* Written and (c) by Ralf Becker <RalfBecker@outdoor-training.de>          *
* --------------------------------------------                             *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

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
	 * @var array $attachment_prefixes prefixes of the rkey for the different attachments
	 */
	var $attachment_prefixes = array(
		'info'      => '',
		'startlist' => 'S',
		'result'    => 'R',
	);
	var $vfs_pdf_dir = '';

	/**
	 * constructor of the competition class
	 */
	function competition($source_charset='',$db=null,$vfs_pdf_dir='')
	{
		//$this->debug = 1;
		$this->so_sql('ranking','Wettkaempfe',$db);	// call constructor of extended class
		
		if ($source_charset) $this->source_charset = $source_charset;
		
		$this->charset = $GLOBALS['phpgw']->translation->charset();

		foreach(array(
				'cats'  => 'category',
			) as $var => $class)
		{
			$egw_name = /*'ranking_'.*/$class;
			if (!is_object($GLOBALS['egw']->$egw_name))
			{
				$GLOBALS['egw']->$egw_name =& CreateObject('ranking.'.$class,$this->source_charset,$this->db);
			}
			$this->$var =& $GLOBALS['egw']->$egw_name;
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
			$data = $GLOBALS['phpgw']->translation->convert($data,$this->source_charset);
		}
		list($data['gruppen'],$data['duration']) = explode('@',$data['gruppen']);
		if ($data['gruppen'])
		{
			$data['gruppen'] = $this->cats->cat_rexp2rkeys($data['gruppen']);
		}
		$data['pkt_bis'] = $data['pkt_bis']!='' ? intval(100 * $data['pkt_bis']) : 100;
		$data['feld_bis'] = $data['feld_bis']!='' ? intval(100 * $data['feld_bis']) : 100;

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
		if ($data['nation'] && !is_array($data['nation']))   $data['nation'] = $data['nation'] == 'NULL' ? '' : strtoupper($data['nation']);

		if (count($data) && $this->source_charset)
		{
			$data = $GLOBALS['phpgw']->translation->convert($data,$this->charset,$this->source_charset);
		}
		return $data;
	}

	/**
	 * Search for competitions
	 *
	 * reimplmented from so_sql unset/not use some columns in the search
	 */
	function search($criteria,$only_keys=True,$order_by='',$extra_cols='',$wildcard='',$empty=False,$op='AND',$start=false,$filter=null)
	{
		unset($criteria['pkte']);	// is allways set
		if (!$criteria['feld_pkte']) unset($criteria['feld_pkte']);
		unset($criteria['open']);
		if (!$criteria['serie']) unset($criteria['serie']);
		if ($criteria['rkey']) $criteria['rkey'] = strtoupper($criteria['rkey']);

		//$this->debug = 1;
		return so_sql::search($criteria,$only_keys,$order_by,$extra_cols,$wildcard,$empty,$op,$start,$filter);
	}

	/**
	 * get the names of all or certain competitions, eg. to use in a selectbox
	 *
	 * @param array $keys array with col => value pairs to limit name-list, like for so_sql.search
	 * @returns array with all Cups of form SerId => name
	 */
	function names($keys=array())
	{
		$names = array();
		foreach((array) $this->search(array(),False,'datum','','',false,'AND',false,$keys) as $data)
		{
			$names[$data['WetId']] = $data['rkey'].': '.$data['name'];
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
	 * @param int/array $keys WetId or array with keys of competition to check, default null = use keys in data
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
		$WetId = is_numeric($keys) ? $keys : $this->data['WetId'];
		if ($data_backup) $this->data = $data_backup;
		
		$this->db->select('Results','count(*)',array('WetId' => $WetId),__LINE__,__FILE__);
		
		return $this->db->next_record() && $this->db->f(0);
	}
	
	/**
	 * path of a pdf attachment of a certain type for the competition in data
	 *
	 * @param string $type 'info', 'startlist', 'result'
	 * @param string $rkey rkey to use, default ''=use the one from our internal data
	 * @return string the path
	 */	 
	function attachment_path($type,$rkey='')
	{
		if (!$rkey) $rkey = $this->data['rkey'];

		if (is_numeric(substr($rkey,0,2)))
		{
			$year = (int) $rkey + ((int) $rkey < 80 ? 2000 : 1900);
		}
		else
		{
			$year = substr($this->data['datum'],0,4);
		}
		return $this->vfs_pdf_dir.'/'.$year.'/'.$this->attachment_prefixes[$type].$rkey.'.pdf';
	}		
		
	/**
	 * Checks and returns links to the attached files
	 *
	 * @param array $keys to read/use a given competitions, default use the already read one
	 * @param boolean $return_link return links or arrays with vars for the link-function, default false=array
	 * @return boolean/array links for the keys: info, startlist, result or false on error
	 */
	function attachments($keys=null,$return_link=false)
	{
		if ($keys && !$this->read($keys)) return false;
		
		if (!is_object($GLOBALS['egw']->vfs))
		{
			$GLOBALS['egw']->vfs =& CreateObject('phpgwapi.vfs');
		}
		$attachments = false;
		foreach($this->attachment_prefixes as $type => $prefix)
		{
			$vfs_path = $this->attachment_path($type);
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
				$attachments[$type] = $return_link ? $GLOBALS['egw']->link('/index.php',$linkdata) : $linkdata;
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
		
		if (!is_object($GLOBALS['egw']->vfs))
		{
			$GLOBALS['egw']->vfs =& CreateObject('phpgwapi.vfs');
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
			if (!$GLOBALS['egw']->vfs->file_exists($vfs_dir = array(
					'string' => dirname($vfs_path),
					'relatives' => RELATIVE_ROOT,
				)) && !$GLOBALS['egw']->vfs->mkdir($vfs_dir)) 
			{
				$error_msg = lang("Can not create directory '%1' !!!",dirname($vfs_path));
				return false;
			}
			if (!$GLOBALS['egw']->vfs->mv(array(
					'from' => $path,
					'to'   => $vfs_path,
					'relatives' => array(RELATIVE_NONE|VFS_REAL,RELATIVE_ROOT),
				)))
			{
				$error_msg = lang("Can not move '%1' to %2 !!!",$path,$vfs_path);
				return false;
			}
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
		
		if (!is_object($GLOBALS['egw']->vfs))
		{
			$GLOBALS['egw']->vfs =& CreateObject('phpgwapi.vfs');
		}
		$vfs_path_arr = array(
			'string' => $this->attachment_path($type),
			'relatives' => RELATIVE_ROOT,
		);
		if (!$GLOBALS['egw']->vfs->file_exists($vfs_path_arr) || !$GLOBALS['egw']->vfs->rm($vfs_path_arr)) 
		{
			return false;
		}
		return true;
	}
	
	/**
	 * renames the attachments to a new rkey
	 *
	 * @param string $old_rkey
	 * @param array $keys to read/use a given competitions, default use the already read one
	 */
	function rename_attachments($old_rkey,$keys=null)
	{
		//echo "<p>competitions::rename_attachments('$old_rkey',".print_r($keys,true).") data[rkey]='".$this->data['rkey']."'</p>\n";
		if (!$old_rkey || $keys && !$this->read($keys)) return false;
		
		if (!is_object($GLOBALS['egw']->vfs))
		{
			$GLOBALS['egw']->vfs =& CreateObject('phpgwapi.vfs');
		}
		$ok = true;
		foreach($this->attachment_prefixes as $type => $prefix)
		{
			$old_path = $this->attachment_path($type,$old_rkey);
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
		return $ok;
	}
}