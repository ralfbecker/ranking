<?php
/**
 * eGroupWare digital ROCK Rankings - display object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2007 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$ 
 */

require_once(EGW_INCLUDE_ROOT.'/etemplate/inc/class.so_sql2.inc.php');

class ranking_display extends so_sql2
{
	/**
	 * Constructor
	 *
	 * @param egw_db $db=null
	 * @return display
	 */
	function __construct(egw_db $db=null)
	{
		if (is_null($db)) $db = $GLOBALS['boranking']->db;
		
		$this->so_sql('ranking','Displays',$db);	// calling the constructor of so_sql for Displays table
	}
	
	/**
	 * Output a string to the display
	 *
	 * @param string $str=null
	 * @return boolean true on success false on error
	 */
	function output($str=null)
	{
		static $dsp_format;
		static $fdisplay;
		static $egw_charset;
		
		if (is_null($egw_charset)) $egw_charset = $GLOBALS['egw']->translation->charset();

		if (is_null($dsp_format))			// evaluate the string to parse \033 or eg. \r
		{
			eval('$dsp_format="'.str_replace('"','',$this->dsp_format).'";');	// the str_replace is a security precaution!
		}
		if (!$fdisplay)
		{
			//echo "fsockopen($this->dsp_ip,$this->dsp_port)\n";
			if (!($fdisplay = fsockopen($this->dsp_ip,$this->dsp_port)))
			{
				echo lang("Can't open connection to display %1 (%2:%3)!!!",$this->dsp_name,$this->dsp_ip,$this->dsp_port)."\n";
				return false;
			}
		}
		if (is_null($str)) $str = $this->dsp_current;
		
		//echo "egw_charset='$egw_charset', dsp_charset='$this->dsp_charset'\n";
		if ($this->dsp_charset && strcasecmp($egw_charset,$this->dsp_charset))
		{
			switch(strtolower($this->dsp_charset))
			{
				case 'cp437':	// not supported by mbstring
					$str = str_replace(array('Á','È','ć','Š'),array('A','E','c','S'),$str);	// chars not in cp437
					$str = iconv($egw_charset,$this->dsp_charset,$str);
					break;

				default:
					$str = $GLOBALS['egw']->translation->convert($str,$egw_charset,$this->dsp_charset);
					break;
			}
		}
		// output content to display
		if (!fprintf($fdisplay,$dsp_format,$str))
		{
			echo lang('Error writing to display!!!')."\n";
			$fdisplay = null;	// try reconnect
			return false;
		}
		return true;
	}

	/**
	 * Activate a line and update the display with the current content, line and evtl. changed format lines
	 *
	 * @param int/array $frm_id id or keys of the format to activate
	 * @param int $athlete=null athlete for the active line
	 * @param int $dsp_id=null display id if not this display
	 */
	function activate($frm_id,$athlete=null,$dsp_id=null)
	{
		if ($dsp_id && $dsp_id != $this->dsp_id)
		{
			$backup = $this->data;
			if (!($this->read($dsp_id)))
			{
				$this->data = $backup;
				return false;
			}
		}
		if (is_object($GLOBALS['ranking_display_ui']))
		{
			$format =& $GLOBALS['ranking_display_ui']->format;
		}
		else
		{
			require_once(EGW_INCLUDE_ROOT.'/ranking/inc/class.ranking_display_format.inc.php');
			$format =& new ranking_display_format($this->db);
		}
		if (!$format->read($frm_id)) return false;

		$dsp = array(
			'frm_id'      => $format->frm_id,
			'dsp_id'      => $this->dsp_id,
		);
		if (!is_null($athlete) && $format->GrpId && is_numeric($format->route_order))
		{
			$this->dsp_athletes[$format->GrpId][$format->route_order] = $athlete;
			$dsp['dsp_athletes'] = $this->dsp_athletes;
		}
		$dsp['dsp_current'] = $format->get_content($showtime,$line=0,false,$this->dsp_athletes[$format->GrpId][$format->route_order]);
		$dsp['dsp_timeout'] = microtime(true) + $showtime;
		$dsp['dsp_line']    = $line;

		$this->update($dsp);

		if ($backup) $this->data = $backup;

		return true;
	}
	
	/**
	 * List the displays as user has access to
	 * 
	 * 
	 * @param int $account_id=null user to check if not the current one
	 * @return array dsp_id => dsp_name pairs
	 */
	function displays($account_id=null)
	{
		if (is_null($account_id)) $account_id = $GLOBALS['egw_info']['user']['account_id'];

		if (!$GLOBALS['egw_info']['user']['apps']['admin'])
		{
			$where[] = $this->db->concat("','",'dsp_access',"','").' LIKE '.$this->db->quote(','.$account_id.',');
		}
		return $this->query_list('dsp_name','dsp_id',$where,'dsp_id');
	}
	
	/**
	 * checks if the current user has access to the display (controller)
	 *
	 * @return boolean true if he has access, false otherwise
	 */
	function check_access()
	{
		return $GLOBALS['egw_info']['user']['apps']['admin'] || 
			is_array($this->dsp_access) && in_array($GLOBALS['egw_info']['user']['account_id'],$this->dsp_access);
	}
	
	/**
	 * changes the data from the db-format to your work-format
	 *
	 * it gets called everytime when data is read from the db
	 * This function needs to be reimplemented in the derived class
	 *
	 * @param array $data if given works on that array and returns result, else works on internal data-array
	 */
	function db2data(array $data=null)
	{
		if (!is_array($data))
		{
			$data = &$this->data;
		}
		if (isset($data['dsp_athletes']))
		{
			$data['dsp_athletes'] = unserialize($data['dsp_athletes']);
		}
		if (isset($data['dsp_access']))
		{
			$data['dsp_access'] = $data['dsp_access'] ? explode(',',$data['dsp_access']) : array();
		}
		return $data;
	}

	/**
	 * changes the data from your work-format to the db-format
	 *
	 * It gets called everytime when data gets writen into db or on keys for db-searches
	 * this needs to be reimplemented in the derived class
	 *
	 * @param array $data if given works on that array and returns result, else works on internal data-array
	 */
	function data2db(array $data=null)
	{
		if ($intern = !is_array($data))
		{
			$data = &$this->data;
		}
		if (isset($data['dsp_athletes']))
		{
			$data['dsp_athletes'] = serialize($data['dsp_athletes']);
		}
		if (isset($data['dsp_access']) && is_array($data['dsp_access']))
		{
			$data['dsp_access'] = implode(',',$data['dsp_access']);
		}
		return $data;
	}
}