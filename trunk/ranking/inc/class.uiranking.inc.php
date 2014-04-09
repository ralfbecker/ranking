<?php
/**
 * eGroupWare digital ROCK Rankings - rankings UI
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2006-14 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$
 */

class uiranking extends ranking_bo
{
	/**
	 * @var array $public_functions functions callable via menuaction
	 */
	var $public_functions = array(
		'index' => true,
	);

	/**
	 * Show a ranking
	 *
	 * @param array $content
	 * @param string $msg
	 */
	function index($content=null,$msg='')
	{
		$tmpl = new etemplate('ranking.ranking');

		if (!is_array($content))
		{
			$content = array(
				'nation'   => $_GET['nation'],
				'cup'      => $_GET['cup'],
				'cat'      => $_GET['cat'],
				'stand'    => $_GET['stand'],
			);
		}
		//_debug_array($content);

		$nation = $this->only_nation ? $this->only_nation : $content['nation'];
		if (!$nation)
		{
			list($nation) = each($this->ranking_nations);	// use the first nation
		}
		if ($this->only_nation) $tmpl->set_cell_attribute('nation','readonly',true);

		$cup = $content['cup'] ? $this->cup->read($content['cup']) : '';

		$select_options = array(
			'nation' => $this->ranking_nations,
			'cup'    => $this->cup->names($nation ? array('nation' => $nation) : array()),
			'cat'    => $this->cats->names($cup && $cup['nation'] == $nation ? array(
				'rkey' => $cup['gruppen'],
			) : array(
				'nation' => $nation,
				'rls > 0',
			),1),
			'stand_rkey' => $this->comp->names(array(
				'nation' => $nation,
				'serie'  => $cup['SerId'],
				'datum <= '.$this->db->quote(date('Y-m-d',time()+7*24*3600)),
				'datum > '.$this->db->quote((date('Y')-1).'-01-01'),
			),1),
		);
		$cat = $content['cat'] ? $this->cats->read($content['cat']) : '';
		// if no cat selected or cat invalid for the choosen nation and cup ==> use first cat from list
		if (!$cat || $nation != ($cat['nation'] ? $cat['nation'] : 'NULL') ||
			$cup && !in_array($cat['rkey'],$cup['gruppen']))
		{
			list($cat) = each($select_options['cat']);	// use the first cat
			if ($cat) $cat = $this->cats->read($cat);
		}
		// reset the stand, if nation or cup changed
		if ($content['old_nation'] != $nation || $content['old_cup'] != $cup['SerId'])
		{
			$content['stand'] = $content['stand_rkey'] = '';
		}
		$content = $preserv = array(
			'nation' => $nation,
			'cup'    => $cup ? $cup['SerId'] : '',
			'cat'    => $cat ? $cat['rkey'] : '',
			'stand_rkey' => $content['stand_rkey'],
			'stand'  => $cat && $content['stand_rkey'] ? $content['stand_rkey'] : $content['stand'],
		);
		$preserv += array(
			'old_nation' => $content['nation'],
			'old_cup'    => $content['cup'],
		);
		$content += array(
			'cat_data' => &$cat,
			'cup_data' => &$cup,
			'msg'      => $msg,
		);
		if ($cat)
		{
			if (!($content['ranking'] =& $this->ranking($cat,$content['stand'],$content['start'],
				$content['comp'],$content['pers'],$content['rls'],$content['ex_aquo'],$content['not_counting'],$cup)))
			{
				$content['msg'] = lang('No ranking defined or no results yet for category %1 !!!',$cat['name']);
			}
			else
			{
				$content['stand_rkey'] = $content['comp']['rkey'];
				// setup array-indices starting with 1
				$content['ranking'] = array_values($content['ranking']);
				array_unshift($content['ranking'],false);
			}
		}
		//_debug_array($content);
		$GLOBALS['egw_info']['flags']['app_header'] = lang('ranking').($cup ? ': '.$cup['name'] : '').
			($cat ? ': '.$cat['name'].($stand ? ': '.$stand : '') : '');
		$tmpl->exec('ranking.uiranking.index',$content,$select_options,$readonly,$preserv);
	}
}
