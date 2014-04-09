<?php
/**
 * eGroupWare digital ROCK Rankings - display format object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2007-10 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$
 */

class ranking_display_format extends so_sql2
{
	/**
	 * Reference to the global ranking_result_bo object
	 *
	 * @var ranking_result_bo
	 */
	var $result;
	/**
	 * all cols in data which are not (direct)in the db, for data_merge
	 *
	 * @var array
	 */
	var $non_db_cols = array('frm_heat');

	/**
	 * Constructor
	 *
	 * @param egw_db $db=null
	 * @return ranking_display_format
	 */
	function __construct(egw_db $db=null)
	{
		$this->result = ranking_result_bo::getInstance();

		if (is_null($db)) $db = $this->result->db;

		parent::__construct('ranking','DisplayFormats',$db);	// calling the constructor of so_sql for DisplayFormats table
	}

	/**
	 * Get the content of a format line (printed format string)
	 *
	 * @param int &$showtime on return show_time in sec
	 * @param int &$line sub-line
	 * @param boolean $next_line=false should be advance to the next line (before getting the content)
	 * @param int/array $athlete athlete data or per-id
	 * @param int $GrpId=null cat to use if format contains none
	 * @param int $route_order=null route to use if format contains none
	 * @param int $center=18 center shorter strings for a $center chars wide display by leftpadding them, only if dsp_lines==1
	 * @param int $dsp_lines=1 number of lines of the display
	 * @return string
	 */
	function get_content(&$showtime,&$line,$next_line=false,$athlete=null,$GrpId=null,$route_order=null,$center=18,$dsp_lines=1)
	{
		//echo "display_format::get_content(,line=$line,next_line=$next_line,$athlete,$GrpId,$route_order,$center,$dsp_lines)\n";
		$showtime = null;

		if ($next_line) $line++;

		if (!$this->GrpId && $GrpId)
		{
			$this->GrpId = $GrpId;
			$this->route_order = $route_order;
		}
		while(!isset($format))
		{
			$lines = explode("\r\n",$this->frm_content);
			$count_lines = count($lines)/$dsp_lines;

			if ($line >= $count_lines-1)				// last line or behind
			{
				$reread_list = $line == $count_lines-1;	// only read list once at the start of the list
				foreach($all=array_slice($lines,$dsp_lines*($count_lines-1),$dsp_lines) as $format)
				{
					$multiline_advanced = false;

					if (strpos($format,'%P') !== false || strpos($format,'%Q') !== false)		// result list
					{
						if (!($athlete = $this->_get_athlete('result_rank',1+$line-$count_lines,$reread_list)))
						{
							unset($format);
						}
						elseif ($dsp_lines > 1)		// on multiline we need to advance to the next line anyway
						{
							$line++;
							$multiline_advanced = true;
						}
					}
					elseif (strpos($format,'%S') !== false || strpos($format,'%s') !== false)	// startlist
					{
						if (!($athlete = $this->_get_athlete('start_order',1+$line-$count_lines,$reread_list)))
						{
							unset($format);
						}
						elseif ($dsp_lines > 1)		// on multiline we need to advance to the next line anyway
						{
							$line++;
							$multiline_advanced = true;
						}
					}
					elseif ($line >= $count_lines &&			// no list and behind the last line
							($dsp_lines == 1 || $line >= 99999 || !preg_match('/%[SsPQ]./',implode("\n",$all))))
					{
						unset($format);					// --> go to next format
					}
					if (isset($format))
					{
						list($format,$showtime) = explode('|',$format);	// separate showtime
						$str[] = $this->_print_line($format,$athlete);
					}
					else
					{
						break;	// no format select --> leave foreach loop
					}
					$reread_list = false;
				}
				if ($multiline_advanced) $line--;			// we need to go back one, as we are at the end of the format

				if ($str)									// do we have some content (on multiline we can have content AND !isset($format)
				{
					$str = implode("\n",$str);
					if (!isset($format))					// we run out of athlets in a multiline, but already had some content
					{
						$format = false;					// --> we need to display this first
						$line = 99999;						// --> the next "line"/display we need to go to the next format
					}
				}
			}
			if ($next_line && !isset($format) && $line >= $count_lines)		// no more lines --> advance to the next format
			{
				$line = 0;
				if ($this->frm_go_frm_id && !$this->read($this->frm_go_frm_id))
				{
					$showtime = 2;
					return lang('Format #%1 not found!',$this-frm_id);
				}
				continue;
			}
			if (!isset($format))
			{
				$format = implode("\n",array_slice($lines,$dsp_lines*(0 <= $line && $line < $count_lines ? $line : 0),$dsp_lines));
				list($format,$showtime) = explode('|',$format);	// separate showtime
				$str = $this->_print_line($format,$athlete);
			}
		}
		if (!(int)$showtime) $showtime = $this->frm_showtime;

		if ($center && $dsp_lines == 1 && ($pad=$center-strlen($str)) > 1)	// we center only single line displays!
		{
			$str = str_repeat(' ',floor($pad/2)).$str;
		}
		return $str;
	}

	/**
	 * Get the data of the athlete at startlist position $num
	 *
	 * @param string $list_type 'start_order' or 'result_rank'
	 * @param int $pos position in the list
	 * @param boolean $reread_list=true should we use the cached list or read it again
	 * @param int $athlete=null get this athlete (PerId) and NOT $pos
	 * @return array/boolean array with athlete data or false if not found in list
	 */
	function _get_athlete($list_type,$pos,$reread_list=true,$athlete=null,$GrpId=null,$route_order=null)
	{
		static $list_cache;
		if ($reread_list) $list_cache = null;
		if (!$_SERVER['HTTP_HOST']) echo "<p>ranking_display_format::_get_athlete('$list_type',$pos,$reread_list,$athlete) list_cache=$list_cache</p>\n";

		if ($pos < 0 || $this->frm_max && $pos >= $this->frm_max && $list_type == 'start_order' ||	// start_order stops direct if over max
			!($keys = $this->route_keys(true)) || !in_array($list_type,array('start_order','result_rank')) ||
			(is_null($list_cache) && !($list_cache = $this->result->route_result->search('',false,$list_type,'','*',false,'AND',false,$keys))))
		{
			return false;
		}
		if ($pos == 0 && $list_type == 'result_rank')	// remove non-ranked
		{
			foreach($list_cache as $key => $row)
			{
				if (!$row['result_rank']) unset($list_cache[$key]);
			}
			$list_cache = array_values($list_cache);
			//_debug_array($list_cache);
		}
		if ($pos >= count($list_cache))
		{
			return false;
		}
		if ($athlete)
		{
			foreach($list_cache as $row)
			{
				if ($row['PerId'] == $athlete) return $row;
			}
			return false;
		}
		// check if we have a max number to show only, but dont break as long as we have ex. aquos
		if ($this->frm_max && $pos >= $this->frm_max && $list_cache[$pos-1]['result_rank'] != $list_cache[$pos]['result_rank'])
		{
			return false;
		}
		return $list_cache[$pos];
	}

	/**
	 * get the content for a whole display line
	 *
	 * @param string $format
	 * 	1. char:    % = athlete, & = co athlete, $ = winner
	 *  2. char:   value type, see _get_value
	 *  last char: % = left, & = right, $ = center justified
	 * @param int/array $athlete=null
	 * @return string
	 */
	function _print_line($format,$athlete=null)
	{
		// show name & nation, instead rank, name & height for not yet ranked competitors
		if ($athlete && !is_array($athlete) && ($athlete = $this->_get_athlete('start_order',0,true,$athlete)) &&
			!$athlete['result_rank'])
		{
			$format = str_replace(array('%p%%V.........$%h$','%p%%V.......$%h$'),
				array('%V............%%L&','%V..........%%L&'),$format);
		}
		//echo "<p>ranking_display_format::_print_line('$format',$PerId)</p>\n";
		if (preg_match_all('/([%&$]{1})([a-zA-Z0-9]+)[^%&$#]*([%&$#]{1})/',$format,$parts))
		{
			list($fulls,$starts,$types,$ends) = $parts;

			foreach($types as $n => $type)
			{
				switch($starts[$n])
				{
					case '%':	// athlete himself
						break;
					case '&':	// co athlete, next in start-order
						$athlete = $this->_get_co($PerId,$WetId,$GrpId,$route_order);
						break;
					case '$':	// winner
						$athlete = $this->_get_winner($PerId,$WetId,$GrpId,$route_order);
						break;
				}
				$len = strlen($fulls[$n]);
				$str = $this->_get_value($type,$len,$athlete);
				//echo "<p>ranking_display_format::_get_value($type,$len,$athlete)='$str', strlen('$str')=".strlen($str)."</p>\n";
				if (($l = strlen($str)) > $len)
				{
					// shorten the too long string with a dot, if there's no space, dash or dot direct before, otherwise just cut it off
					$str = !in_array(substr($str,$len-2,1),array(' ','-','.')) ? substr($str,0,$len-1).'.' : substr($str,0,$len);
				}
				elseif (($pad = $len-$l))
				{
					switch($ends[$n])
					{
						case '%':	// left
							$str .= str_repeat(' ',$pad);
							break;
						case '&':	// right
							$str = str_repeat(' ',$pad).$str;
							break;
						case '$':	// centered
							$pad /= 2;
							$str = str_repeat(' ',floor($pad)).$str.str_repeat(' ',ceil($pad));
							//echo "<p>centered: pad=$len-($l=strlen(str)), pad/2=$pad, floor($pad)=".floor($pad).", ceil($pad)=".ceil($pad).", str='$str', strlen('$str')=".strlen($str)."</p>\n";
							break;
					}
				}
				$format = str_replace($fulls[$n],$str,$format);
			}
		}
		return $format;
	}

	/**
	 * get the value for type $type
	 *
	 * @param string $type
	 * @param int $len requested length of the value
	 * @param int/array $athlete=null
	 * @return string
	 */
	function _get_value($type,$len,&$athlete)
	{
		//echo "<p>ranking_display_format::_get_value('$type',$len,$PerId)</p>\n";
		switch($type{0})
		{
			case 'D':	// Date
				$format = $GLOBALS['egw_info']['user']['preferences']['common']['dateformat'];
				if ($len <= 8) $format = str_replace('Y','y',$format);
				// fall through
			case 'd':	// Time
				if ($type == 'd')
				{
					$format = $GLOBALS['egw_info']['user']['preferences']['common']['timeformat'] == 12 ? 'h:i a' : 'H:i:s';
				}
				return date($format,time()+$GLOBALS['egw_info']['user']['preferences']['common']['tzoffset']*3600);

			case 'W':	// comp long
			case 'w':	// comp short
				return $this->_get_comp_value($type,$len);

			case 'c':	// category
			case 'R':	// category: heat
			case 'r':	// heat
			case 'F':	// result offical line or "provisional result"
			case 'j':	// jury-namen
			case 'J':	// times (0=Isolation open, 1=Isolation close, 2=Begin heat)
				return $this->_get_heat_value($type,$len);

			case 'V':	// firstname lastname
			case 'v':	// firstname
			case 'N':	// lastname, firstname
			case 'n':	// lastname
			case 'O':	// city
			case 'L':	// nation 3-letter
			//case 'M':	// nation long
			case 'S':	// start number
			case 's':	// start order
			case 'P':	// place/rank (empty for ex aquo)
			case 'p':	// place/rank
			case 'Q':	// place/rank
			case 'h':	// height without comma (3-char)
			case 'H':	// height with 2 digits behind the comma (6-char)
			case 't':	// time
/*
			       P = Platzierung (zB. "1") \
			       Q = wie P nur kein ex aquo  \
			       b = Lizenznummer               /
			       S = Startnummer (zB. "1")     /
			       s = StartreihenfolgeNr       /
			       Z = Startzeit (zB. " 9.00") /
			       i = NatZeit (!ex aquo)    \
			       I = NatZeit (zB. "SSS.Z")   \
			       H = Kletterh�he (zB."100.50 " \
			       h = Kletterh�he (zB. "10.50 ")  \
			       p = Platzierung (zB. "1.")       > T.-Ergebnisse
			       q = Platzierung von ex aquo gemittelt
			       e = H�he in Prozent der Toph�he
			       B = Boulderwertung " 5t10 6z8 " | "t3 z2 "
			       U = UIAA-Pkte
			       z = Kletterzeit (zB. "MM:SS")   /
			       t = Kletterzeit (zB. "SS.Z")  /
			       A = NatH�he (zB. "100.50")  /
			       a = NatH�he (!ex aquo)    /
*/
				return $this->_get_athlete_value($type,$len,$athlete);
		};
		return '?'.$type.'?';
	}

	/**
	 * Get a competition specific value
	 *
	 * @param string $type
	 * @param int $len=null requested length of the value
	 * @return string
	 */
	function _get_comp_value($type,$len)
	{
		static $comp;

		if (!(int)$this->WetId || (!is_array($comp) || $comp['WetId'] != $this->WetId) && !($comp = $this->result->comp->read($this->WetId)))
		{
			return false;
		}
		switch($type{0})
		{
			case 'W':	// comp long
				return $comp['name'];

			case 'w':	// comp short
				return $comp['dru_bez'];
		}
		return '?'.$type.'?';
	}

	/**
	 * Get a heat specific value
	 *
	 * @param string $type
	 * @param int $len=null requested length of the value
	 * @return string
	 */
	function _get_heat_value($type,$len)
	{
		static $route;

		if (!($keys = $this->route_keys()) ||
			(!is_array($route) || $route['WetId'] != $this->WetId || $route['GrpId'] != $this->GrpId || $route['route_order'] != $this->route_order) &&
			!($route = $this->result->route->read($keys)))
		{
			return false;
		}
		switch($type{0})
		{
			case 'R':	// heat category
				$str = $route['route_name'].' ';
				// fall through
			case 'c':	// category
				$cat = $this->result->cats->read($this->GrpId);
				$str .= $cat['name'];
				return $str;

			case 'r':	// heat
				return $route['route_name'];

			case 'F':	// result offical line
				return $route['route_status'] == STATUS_RESULT_OFFICIAL ? $route['route_result'] : lang('provisional result');

			case 'j':	// jury-namen
				return $route['route_judge'];

			case 'J0':	// Isolation opens
				return $route['route_iso_open'];

			case 'J1':	// Isolation closes
				return $route['route_iso_close'];

			case 'J':
			case 'J2':	// Begin of heat
				return $route['route_start'];
		}
		return '?'.$type.'?';
	}

	/**
	 * Get a athlete specific value
	 *
	 * @param string $type
	 * @param int $len requested lenght of the value
	 * @param int/array &$athlete
	 * @return string
	 */
	function _get_athlete_value($type,$len,&$athlete)
	{
		//echo "<p>ranking_display_format::_get_athlete_value('$type',$len,$athlete)</p>\n";
		static $type2col = array(
			'v' => 'vorname',
			'n' => 'nachname',
			'L' => 'nation',
			'O' => 'ort',
			//'geb_date',
			//'birthyear',
			//'verband',
			'S' => 'start_number',
			's' => 'start_order',
		);
		// try reading athlete from the result, if $type contains result data and only an id is given or the array contains no result data
		if ($athlete && in_array($type{0},array('P','p','Q','h','H','S','t')) && (!is_array($athlete) || !isset($athlete['start_order'])))
		{
			if (($a = $this->_get_athlete('start_order',0,true,$athlete))) $athlete = $a;
		}
		// if only an athlete id given, read him from the athlets table
		if (!$athlete || !is_array($athlete) && !($athlete = $this->result->athlete->read($athlete)))
		{
			return false;
		}
		switch($type{0})
		{
			case 'V':
			case 'N':
				$lastname = $athlete['nachname'];
				if (strlen($lastname)+2 >= $len)	// we need at least 2 char for the first name
				{
					return $lastname;	// no space for the first name
				}
				$firstname = $athlete['vorname'];
				if (strlen($lastname)+strlen($firstname) >= $len)
				{
					$firstname = $firstname{0}.'.';
				}
				$str = $type{0} == 'V' ? $firstname.' '.$lastname : $lastname.', '.$firstname;
				if (strlen($str) == $len+1) $str_replace(array('. ',', '),array('.',','),$str);
				return $str;

			case 'h':
			case 'H':
				if (!$athlete['result_rank']) return '';
				return $athlete['result_plus'] == TOP_PLUS ? 'Top' : sprintf($type{0}=='H'?'%5.2lf%s':'%s%s',
					$athlete['result_height'],$athlete['result_plus'] ? ($athlete['result_plus']==1?'+':'-'):'');

			case 't':
				switch($type{1})
				{
					case '1':
					case 'l':
						$time = $athlete['result_time'];
						break;
					case '2':
					case 'r':
						$time = $athlete['result_time_r'];
						break;
					default:
						$time = $athlete['time_sum'];
						break;
				}
				return number_format($time,2);

			case 'P':
			case 'p':
			case 'Q':
				return $athlete['result_rank'] ? $athlete['result_rank'].'.' : '';

			default:
				if (isset($type2col[$type])) return $athlete[$type2col[$type]];
				break;
		}
		return '?'.$type.'?';
	}

	/**
	 * Get the keys of a route selected for this format
	 *
	 * @param $set_type_discipline=false set additionally route type and discipline
	 * @return array/false array with keys or false if keys are obviously not valid or set
	 */
	function route_keys($set_type_discipline=false)
	{
		if (!(int)$this->WetId || !(int)$this->GrpId || !is_numeric($this->route_order) || $this->route_order < -1)
		{
			return false;
		}
		$keys = array(
			'WetId' => $this->WetId,
			'GrpId' => $this->GrpId,
			'route_order' => $this->route_order,
		);
		if ($set_type_discipline)
		{
			if (!($route = $this->result->route->read($keys))) return false;

			$keys['route_type'] = $route['route_type'];

			if (!($comp = $this->result->comp->read($this->WetId)))
			{
				return false;
			}
			elseif ($comp['discipline'])
			{
				$keys['discipline'] = $comp['discipline'];
			}
			elseif (!($cat = $this->result->cats->read($this->GrpId)))
			{
				return false;
			}
			else
			{
				$keys['discipline'] = $comp['discipline'];
			}
		}
		return $keys;
	}

	/**
	 * Update line-numbers to be continues starting with 1, while ignoring a given frm_id
	 *
	 * You need to specify $dsp_id and $WetId, if they are not set in the internal data (there's no read before)!
	 *
	 * @param int $ignore_id=null if given, id to be ignored
	 * @param int $ignore_line=null if given, line-number to be skiped
	 * @param int $dsp_id=null display to use or null to use the display of the current format
	 * @param int $WetId=null competition to use or null to use the competition of the current format
	 * @return int number of lines updated
	 */
	function update_lines($ignore_id=null,$ignore_line=null,$dsp_id=null,$WetId=null)
	{
		if (is_null($dsp_id)) $dsp_id = $this->dsp_id;
		if (is_null($WetId))  $WetId = $this->WetId;

		if (!$dsp_id || !$WetId)
		{
			echo "<p>called display_format::update_lines($ignore_id,$ignore_line) with !dsp_id or !WetId!</p>\n".function_backtrace();
			return false;
		}
		$rows = $this->search(array(
			'dsp_id' => $dsp_id,
			'WetId'  => $WetId,
		),'frm_id,frm_line');

		$updated = 0;
		$line = 1;
		foreach($rows as &$row)
		{
			if ($ignore_id && $ignore_id == $row['frm_id']) continue;	// skit the given row/id
			if ($ignore_line && $line == $ignore_line) $line++;			// skip the given line

			if ($row['frm_line'] != $line)
			{
				//echo "<p>frm_id=$row[frm_id], dsp_id=$row[dsp_id], WetId=$row[WetId]: frm_line=$row[frm_line]-->$line</p>\n";
				$this->update(array(
					'frm_line' => $line,
					'frm_updated' => time(),
					'frm_id' => $row['frm_id'],
				));
				$updated++;
			}
			$line++;
		}
		return $updated;
	}

	/**
	 * Get the maximum line number of a given display (dsp_id) and competition (WetId), ignoring an optional given id
	 *
	 * @param array $frm=null if null, use $this->data
	 * @return int
	 */
	function max_line($frm=null)
	{
		if (is_null($frm)) $frm =& $this->data;

		$where = array(
			'dsp_id' => $frm['dsp_id'],
			'WetId'  => $frm['WetId'],
		);
		if ((int)$frm['frm_id']) $where[] = 'frm_id!='.(int)$frm['frm_id'];

		$this->db->select($this->table_name,'MAX(frm_line)',$where,__LINE__,__FILE__);

		return $this->db->next_record() ? (int)$this->db->f(0) : 0;
	}

	/**
	 * Get the lastest update time of a given display (dsp_id) and competition (WetId)
	 *
	 * @param array $frm=null if null, use $this->data
	 * @return int unix timestamp
	 */
	function last_updated($frm=null)
	{
		if (is_null($frm)) $frm =& $this->data;

		$this->db->select($this->table_name,'MAX(frm_updated)',array(
			'dsp_id' => $frm['dsp_id'],
			'WetId'  => $frm['WetId'],
		),__LINE__,__FILE__);

		return $this->db->next_record() ? (int)$this->db->from_timestamp($this->db->f(0)) : 0;
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
		if ($data['GrpId'])
		{
			$data['frm_heat'] = $data['GrpId'].':'.$data['route_order'];
		}
		if ($data['frm_updated'] && !is_numeric($data['frm_updated']))
		{
			$data['frm_updated'] = $this->db->from_timestamp($data['frm_updated']);
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
		if (isset($data['frm_heat']))
		{
			list($data['GrpId'],$data['route_order']) = explode(':',$data['frm_heat']);
		}
		return $data;
	}

	/**
	 * Copy all formats of the current competition (or $from_comp) to a new one
	 *
	 * @param int $to_comp
	 * @param int $from_comp=null use current competition if null
	 * @param int $dsp_id=null use current display if null
	 */
	function copyall($to_comp,$from_comp=null,$dsp_id=null)
	{
		if (is_null($from_comp)) $from_comp = $this->WetId;
		if (is_null($dsp_id)) $dsp_id = $this->dsp_id;
		//echo "<p>ranking_display_format::copyall($to_comp,$from_comp,$dsp_id)</p>\n";

		if (!(int)$to_comp || !(int)$from_comp || !(int)$dsp_id) return false;

		$rows = $this->search(array(),false,'frm_line','','',false,'AND',false,array(
			'dsp_id' => $dsp_id,
			'WetId'  => $from_comp,
		));

		if (!$rows) return false;

		$id2line = array();
		foreach($rows as $row)
		{
			$id2line[$row['frm_id']] = $row['frm_line'];
		}
		$line2id = $go2line = array();
		foreach($rows as $row)
		{
			unset($row['frm_heat']);
			unset($row['GrpId']);
			unset($row['route_order']);
			unset($row['frm_id']);
			$this->init($row);
			$this->WetId = $to_comp;
			$this->frm_updated = time();
			$this->save();
			if ($id2line[$this->frm_go_frm_id]) $go2line[$this->frm_id] = $id2line[$this->frm_go_frm_id];
			$line2id[$this->frm_line] = $this->frm_id;
		}
		// update the go's with the now know frm_id's
		foreach($go2line as $id => $line)
		{
			$this->update(array(
				'frm_go_frm_id' => $line2id[$line],
				'frm_id' => $id,
			),false);
		}
		return true;
	}
}