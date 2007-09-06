<?php
/**
 * eGroupWare digital ROCK Rankings - display user interface
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2007 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$ 
 */

require_once(EGW_INCLUDE_ROOT.'/ranking/inc/class.ranking_display_bo.inc.php');
require_once(EGW_INCLUDE_ROOT.'/etemplate/inc/class.uietemplate.inc.php');

class ranking_display_ui extends ranking_display_bo
{
	/**
	 * Functions callable from the UI
	 *
	 * @var array
	 */
	var $public_functions = array(
		'index' => true,
		'edit' => true,
		'display' => true,
	);
	
	/**
	 * Constructor
	 *
	 * @return ranking_display_ui
	 */
	function ranking_display_ui()
	{
		$this->ranking_display_bo();		// calling the constructor of the extended class
	}
	
	/**
	 * Controll for as display
	 *
	 * @param array $content=null
	 * @param string $msg
	 */
	function index($content=null,$msg='',$dsp_id=null)
	{
		if (!is_array($content))
		{
			if (!$dsp_id) $dsp_id = (int)$_GET['dsp_id'] > 0 ? (int)$_GET['dsp_id'] : ranking_display::defaultDisplay();
			if (!$msg) $msg = $_GET['msg'];
		}
		else
		{
			$dsp_id = $content['display']['dsp_id'];

			if (isset($content['rows']['action']))
			{
				list($action,$data) = each($content['rows']['action']);
				list($frm_id) = each($data);
				//echo "<p>action='$action', frm_id=$frm_id</p>\n";
				switch($action)
				{
					case 'up':
					case 'down':
						$add = $action == 'up' ? -1 : 1;
						if (($frm = $this->format->read($frm_id)))
						{
							$this->format->update_lines($frm_id,$frm['frm_line']+$add);

							$this->format->update(array(
								'frm_line' => $frm['frm_line']+$add,
								'frm_updated' => time(),
								'frm_id' => $frm_id,
							));
						}
						break;
						
					case 'delete':
						if ($this->format->delete(array(
							'frm_id' => $frm_id,
						)))
						{
							$this->format->update_lines(null,null,$dsp_id,$content['display']['WetId']);
							$msg = lang('Format deleted');
						}
						else
						{
							$msg = lang('Error deleting format!');
						}
						break;					
				}
			}
			if ($content['old_comp'] != $content['display']['WetId'])	// competition changed
			{
				if ($_GET['copy_formats'])
				{
					$this->format->copyall($content['display']['WetId'],$content['old_comp'],$dsp_id);
				}
				$this->display->update(array(
					'WetId'  => $content['display']['WetId'],
					'dsp_id' => $dsp_id,
					'dsp_current' => 'www.digitalROCK.de',
					'dsp_line' => 0,
					'frm_id' => 0,
				));
			}
		}
		if (!$this->display->read($dsp_id))
		{
			$msg = lang('Display #%1 not found!!!',$dsp_id);
		}
		elseif(!$this->display->check_access())
		{
			$this->popup_die();
		}
		else
		{
			if (!is_array($content) && (int)$_GET['frm_id'])
			{
				if (!$this->display->activate($_GET['frm_id'],$_GET['athlete']))
				{
					$msg = lang('Format #%1 not found!',$_GET['frm_id']);
				}
			}			
			$rows = $this->format->search(array(),false,'frm_line','','',false,'AND',false,$q=array(
				'dsp_id' => $this->display->dsp_clone_of ? $this->display->dsp_clone_of : $dsp_id,
				'WetId'  => $this->display->WetId,
			));
			if (!is_array($rows)) $rows = array();
			
			$id2line = array();
			$last_update = 0;
			foreach($rows as $row)
			{
				$id2line[$row['frm_id']] = $row['frm_line'];
				if ($last_update < $row['frm_updated']) $last_update = $row['frm_updated'];
			}
			foreach($rows as $n => $row)
			{
				if (!$n) $readonlys["action[up][$row[frm_id]]"] = true;

				if ($row['frm_id'] == $this->display->frm_id)
				{
					$this->display->frm_line = $row['frm_line'];
				}
				$rows[$n]['frm_go'] = $id2line[$row['frm_go_frm_id']];
			}
			$readonlys["action[down][$row[frm_id]]"] = true;
			//_debug_array($rows);
			array_unshift($rows,false);	// make the index 1-based
		}
		$sleep = $this->_calc_sleep_time($this->display);
		//$this->format->read($this->display->frm_id=9); $this->display->dsp_current = $this->format->get_content($nul,$line=20); $sleep = 99999999;
		$GLOBALS['egw_info']['flags']['java_script'] .= "<script>var timeout=window.setTimeout('xajax_doXMLHTTP(\"ranking.ranking_display_ui.ajax_update\",$dsp_id,$last_update);',$sleep);</script>\n";

		$content = array(
			'display' => $this->display->as_array(),
			'rows'    => $rows,
			'msg'     => $msg,
			'self'    => $GLOBALS['egw']->link('/index.php',array('menuaction'=>'ranking.ranking_display_ui.index','dsp_id'=>$dsp_id)),
		);
		// we might be just a clone of an other display (get or formats from that display)
		$content['dsp_id'] = $content['rows']['dsp_id'] = $this->display->dsp_clone_of ? $this->display->dsp_clone_of : $dsp_id;
		
		$preserv = array(
			'old_comp'=> $this->display->WetId,
		);
		$nations = $this->result->ranking_nations;
		if (isset($nations['NULL']))
		{
			$nations = array_merge(array(null),$this->result->ranking_nations);
			unset($nations['NULL']);
		}
		$sel_options = array(
			'dsp_id' => $this->display->query_list('dsp_name','dsp_id'),
			'WetId'  => $this->result->comp->names(array(
				'nation' => $nations,
				'datum < '.$this->result->db->quote(date('Y-m-d',time()+7*24*3600)),	// starting 5 days from now
				'datum > '.$this->result->db->quote(date('Y-m-d',time()-365*24*3600)),	// until one year back
				'gruppen IS NOT NULL',
			),0,'datum DESC'),
			'frm_heat' => $this->get_heats($this->display->WetId),
		);
		if ($this->display->WetId && !isset($sel_options['WetId'][$this->display->WetId]) && ($comp = $this->result->comp->read($this->display->WetId)))
		{
			$sel_options['WetId'][$this->display->WetId] = $comp['name'];
		}
		$GLOBALS['egw_info']['flags']['app_header'] = $this->display->dsp_name.
			($this->display->ip ? ' ('.$this->display->ip.($this->display->dsp_port?':'.$this->display->dsp_port:'').')' : '');

		$GLOBALS['egw_info']['flags']['include_xajax'] = true;

		//_debug_array($content);
		$tpl = new etemplate('ranking.display.index');
		$tpl->exec('ranking.ranking_display_ui.index',$content,$sel_options,$readonlys,$preserv,2);
	}
	
	/**
	 * Calculate how long the display controler can sleep, before the next update is neccessary
	 *
	 * @param ranking_display $display
	 * @return int timeout in ms
	 */
	function _calc_sleep_time(ranking_display $display)
	{
		// calculate how long the javascript timeout can be (in ms)
		if (!$display->dsp_timeout)	// timeout switched off --> fallback to 10s
		{
			$sleep = 9998;
		}
		else
		{
			$n = 0;
			while ($n++ < 5 && ($sleep = (int) (1000 * ($display->dsp_timeout - microtime(true)))) < 200)	// already expired --> try re-read
			{
				usleep(500);	// give the demon some time to update
				$display->read($display->dsp_id);
			}
			if ($n > 5) $sleep = 499;		// something went wrong
		}
		//if (500 > $sleep) $sleep = 500;		// guard the webserver against too rapid requests (=1/2s)
		if ($sleep > 7000) $sleep = 7000;	// in case an other user changes something
			
		return $sleep;
	}

	/**
	 * Check if the new competition already has formats and offer to copy the current ones if not
	 *
	 * @param int $dsp_id
	 * @param int $comp WetId
	 */
	function ajax_ask_copy_formats($dsp_id,$comp)
	{
		if (!$this->format->max_line(array(
			'dsp_id' => $dsp_id,
			'WetId'  => $comp,
		)))
		{
			$script = "if (confirm('".addslashes(lang('Copy current formats to the new competition, which does not have any formats yet?'))."')) document.eTemplate.action+='&copy_formats=1';";
		}
		$script .= 'document.eTemplate.submit();';

		$response =& new xajaxResponse();
		$response->addScript($script);

		return $response->getXML();
	}

	/**
	 * Update the display with the current content, line and evtl. changed format lines
	 *
	 * @param int $dsp_id
	 * @param int $last_updated timestamp to check for newer updates, or false to ignore the check
	 * @param ranking_display $display=null
	 */
	function ajax_update($dsp_id,$last_updated=false,ranking_display $display=null)
	{
		$GLOBALS['egw']->session->commit_session();		// stop this session from blocking other requests

		$response =& new xajaxResponse();

		if (is_null($display) && ($display = $this->display) && !$display->read($dsp_id))
		{
			$response->addAssign('exec[display][dsp_current]','value',lang('Display #%1 not found!!!',$dsp_id));
		}
		else
		{
			// check if we need to update the whole list, as a format is changed from outside (an other controller)
			if ($last_updated != ($lu = $this->format->last_updated(array(
				'dsp_id' => $display->dsp_clone_of ? $display->dsp_clone_of : $dsp_id,
				'WetId'  => $display->WetId,
			))) && $last_updated !== false)	// check has to be after the query, as we need the query result anyway!
			{
				$response->addScript("document.location='".$GLOBALS['egw']->link('/index.php',array(
					'menuaction' => 'ranking.ranking_display_ui.index',
					'dsp_id' => $dsp_id,
				))."';");
				return $response->getXML();
			}
			// calculate how long the javascript timeout can be (in ms)
			$sleep = $this->_calc_sleep_time($display);			
			$line = $display->frm_id && $this->format->read($display->frm_id) ? $this->format->frm_line : '';
			$response->addAssign('exec[display][frm_line]','value',$line);
			$response->addAssign('exec[display][dsp_current]','value',$display->dsp_current);
			$response->addScript("timeout=window.setTimeout('xajax_doXMLHTTP(\"ranking.ranking_display_ui.ajax_update\",$dsp_id,$lu);',$sleep);");
		}
		return $response->getXML();
	}
	
	
	/**
	 * Activate a line and update the display with the current content, line and evtl. changed format lines
	 *
	 * @param int $dsp_id
	 * @param int $line line to activate
	 */
	function ajax_activate($dsp_id,$line)
	{
		$GLOBALS['egw']->session->commit_session();		// stop this session from blocking other requests

		if (!$this->display->read($dsp_id))
		{
			$response =& new xajaxResponse();
			$response->addScript("document.getElementById('exec[display][dsp_current]').value='".addslashes(lang('Display #%1 not found!!!',$dsp_id))."';");
			return $response->getXML();
		}
		if (!$this->display->activate(array(
			'dsp_id'   => $this->display->dsp_clone_of ? $this->display->dsp_clone_of : $dsp_id,
			'WetId'    => $this->display->WetId,
			'frm_line' => $line,
		)))
		{
			$response =& new xajaxResponse();
			$response->addScript("document.getElementById('exec[display][dsp_current]').value='".addslashes(lang('Format #%1 not found!',$line))."';");
			return $response->getXML();
		}
		return $this->ajax_update($dsp_id,false,$this->display);
	}
	
	/**
	 * Edit one format line of a display
	 *
	 * @param array $content=null
	 * @param string $msg
	 */
	function edit(array $content=null,$msg='')
	{
		if (!is_array($content))
		{
			if ($_GET['frm_id'] && $this->format->read($_GET['frm_id']))
			{
				$dsp_id = $this->format->dsp_id;
			}
			else
			{
				$dsp_id = $_GET['dsp_id'] ? $_GET['dsp_id'] : 1;
			}
			if (!$this->display->read($dsp_id))
			{
				$msg = lang('Display #%1 not found!!!',$dsp_id);
			}
			elseif(!$this->display->check_access())
			{
				$this->popup_die();
			}
			else
			{
				if ($dsp_id != $this->format->dsp_id)
				{
					$this->format->init(array(
						'dsp_id' => $this->display->dsp_id,
						'WetId'  => $this->display->WetId,
					));
				}
			}
			$frm = $this->format->as_array();
		}
		else
		{
			$frm = $content;
			unset($frm['button']);
			$dsp_id = $frm['dsp_id'];

			list($button) = each($content['button']);

			switch($button)
			{
				case 'save':
				case 'apply':
					// re-arrange the lines if necessary
					if ($frm['frm_id'] && $frm['frm_line'] != $frm['old_line'] ||
						!$frm['frm_id'] && $frm['frm_line'])
					{
						$this->format->update_lines($frm['frm_id'],$frm['frm_line'],$dsp_id,$frm['WetId']);
					}
					// replace go-line with frm_id
					if ($frm['frm_go'] && ($go = $this->format->read(array(
						'dsp_id' => $frm['dsp_id'],
						'WetId'  => $frm['WetId'],
						'frm_line' => $frm['frm_go'],
						'frm_id!='.(int)$frm['frm_id'],
					))))
					{
						$frm['frm_go_frm_id'] = $go['frm_id'];
					}
					else
					{
						$frm['frm_go_frm_id'] = $frm['frm_go'] = null;
					}
					$max_line = $this->format->max_line($frm)+1;
					if (!$frm['frm_line'] || $frm['frm_line'] > $max_line) $frm['frm_line'] = $max_line;

					$this->format->init($frm);
					if (!($err = $this->format->save(array('frm_updates' => time()))))
					{
						$msg = lang('Format saved');
					}
					else
					{
						$msg = lang('Error saving format!').' ('.$err.')';
						$button = '';
					}
					break;
					
				case 'delete':
					if ($this->format->delete($frm))
					{
						$this->format->update_lines(null,null,$dsp_id,$frm['WetId']);
						$msg = lang('Format deleted');
					}
					else
					{
						$msg = lang('Error deleting format!');
						$button = '';
					}
					break;
			}
			$script = "opener.location.href='".addslashes($GLOBALS['egw']->link('/index.php',array(
				'menuaction' => 'ranking.ranking_display_ui.index',
				'msg'        => $msg,
				'dsp_id'     => '',	// we read the dsp_id from the selectbox of index, because of cloned displays
			)))."'+opener.document.getElementById('exec[display][dsp_id]').value;";
			if ($button == 'save' || $button == 'delete')	// close popup and update caller
			{
				echo "<html>\n<head>\n<script>$script window.close();</script>\n</head>\n</html>\n";
				$GLOBALS['egw']->framework->egw_exit();
			}
		}
		if ($frm['frm_go_frm_id'] && ($go = $this->format->read($frm['frm_go_frm_id'])))
		{
			$frm['frm_go'] = $go['frm_line']; 
		}
		$preserv = $content = $frm;
		$preserv['old_line'] = $frm['frm_line'];
		$content['msg'] = $msg;
		$readonlys['button[delete]'] = !$this->format->frm_id;
		
		if ($script) $GLOBALS['egw_info']['flags']['java_script'] .= "<script>$script</script>\n";
		
		$GLOBALS['egw_info']['flags']['app_header'] = $this->display->dsp_name.
			($this->display->ip ? ' ('.$this->display->ip.($this->display->dsp_port?':'.$this->display->dsp_port:'').')' : '');

		$tpl = new etemplate('ranking.display.edit');
		$tpl->exec('ranking.ranking_display_ui.edit',$content,array('frm_heat'=>$this->get_heats($frm['WetId'])),$readonlys,$preserv,2);
	}
	
	/**
	 * Add/edit a display
	 *
	 * @param array $content=null
	 */
	function display($content=null)
	{
		if (!$GLOBALS['egw_info']['user']['apps']['admin']) $this->popup_die();

		if (!is_array($content))
		{
			if ($_GET['dsp_id'] && !$this->display->read($_GET['dsp_id']))
			{
				$msg = lang('Display #%1 not found!!!',$_GET['dsp_id']);
			}
		}
		else
		{
			list($button) = @each($content['button']);
			unset($content['button']);
			
			switch($button)
			{
				case 'apply':
				case 'save':
					if (($err = $this->display->update($content)))
					{
						$msg = lang('Error saving display!');
						$button = '';
					}
					else
					{
						$msg = lang('Display saved');
					}
					$script = "opener.location = opener.location.href+'&msg=".addslashes($msg).
						"&dsp_id='+opener.document.getElementById('exec[display][dsp_id]').value;";
					if ($button == 'save')	// close popup and update caller
					{
						echo "<html>\n<head>\n<script>$script window.close();</script>\n</head>\n</html>\n";
						$GLOBALS['egw']->framework->egw_exit();
					}
					$GLOBALS['egw_info']['flags']['java_script'] .= "<script>$script</script>\n";
					break;
			}
		}
		$content = $this->display->as_array();
		$content['msg'] = $msg;
		$sel_options = array(
			'dsp_clone_of' => $this->display->displays(),
		);
		if ($this->display->dsp_id)
		{
			unset($sel_options['dsp_clone_of'][$this->display->dsp_id]);
		
			$GLOBALS['egw_info']['flags']['app_header'] = $this->display->dsp_name.
				($this->display->ip ? ' ('.$this->display->ip.($this->display->dsp_port?':'.$this->display->dsp_port:'').')' : '');
		}
		$tpl = new etemplate('ranking.display.display');
		$tpl->exec('ranking.ranking_display_ui.display',$content,$sel_options,$readonlys,array('dsp_id'=>$this->display->dsp_id),2);		
	}
	
	/**
	 * Displays an alert with a message and closes the popup after the confirmation
	 * 
	 * Does NOT return!
	 * 
	 * @param string $msg='Permission denied !!!'
	 */
	function popup_die($msg='Permission denied !!!')
	{
		echo "<html><head><script>\nalert('".addslashes(lang($msg))."'); window.close();\n</script></head></html>\n";
		$GLOBALS['egw']->common->egw_exit();
	}
}