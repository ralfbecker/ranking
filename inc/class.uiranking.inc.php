<?php
/**
 * eGroupWare digital ROCK Rankings - rankings UI
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2006-18 by Ralf Becker <RalfBecker@digitalrock.de>
8 */

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
	 * @param array $_content
	 * @param string $msg
	 */
	function index($_content=null,$msg='')
	{
		$tmpl = new etemplate('ranking.ranking');

		if (!is_array($_content))
		{
			$_content = array(
				'nation'   => $_GET['nation'],
				'cup'      => $_GET['cup'],
				'cat'      => $_GET['cat'],
				'stand'    => $_GET['stand'],
			);
		}
		//_debug_array($_content);

		$nation = $this->only_nation ? $this->only_nation : $_content['nation'];
		if (!$nation)
		{
			list($nation) = each($this->ranking_nations);	// use the first nation
		}
		if ($this->only_nation) $tmpl->set_cell_attribute('nation','readonly',true);

		$cup = $_content['cup'] ? $this->cup->read($_content['cup']) : null;

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
				'serie'  => $cup ? $cup['SerId'] : null,
				'datum <= '.$this->db->quote(date('Y-m-d',time()+7*24*3600)),
				'datum > '.$this->db->quote((date('Y')-1).'-01-01'),
			),1),
		);
		$cat = $_content['cat'] ? $this->cats->read($_content['cat']) : '';
		// if no cat selected or cat invalid for the choosen nation and cup ==> use first cat from list
		if (!$cat || $nation != ($cat['nation'] ? $cat['nation'] : 'NULL') ||
			$cup && !in_array($cat['rkey'],$cup['gruppen']) && array_intersect($cat['mgroups'], $cup['gruppen']) != $cat['mgroups'])
		{
			list($cat) = each($select_options['cat']);	// use the first cat
			if ($cat) $cat = $this->cats->read($cat);
		}
		// reset the stand, if nation or cup changed
		if ($_content['old_nation'] != $nation || $_content['old_cup'] != $cup['SerId'])
		{
			$_content['stand'] = $_content['stand_rkey'] = '';
		}
		$content = $preserv = array(
			'nation' => $nation,
			'cup'    => $cup ? $cup['SerId'] : '',
			'cat'    => $cat ? $cat['rkey'] : '',
			'stand_rkey' => $_content['stand_rkey'],
			'stand'  => $cat && $_content['stand_rkey'] ? $_content['stand_rkey'] : $_content['stand'],
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
			($cat ? ': '.$cat['name'].($content['stand'] ? ': '.$content['stand'] : '') : '');
		$tmpl->exec('ranking.uiranking.index', $content, $select_options, array(), $preserv);
	}
}
