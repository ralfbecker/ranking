<?php
/**
 * eGroupWare digital ROCK Rankings - beamer / videowall support
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2011 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$
 */

/**
 * Beamer / videowall support
 */
class ranking_beamer
{
	/**
	 * Functions callable via menuaction
	 */
	public $public_functions = array(
		'beamer' => true,
	);

	/**
	 * Configure beamer display
	 *
	 * @param array $content=null
	 * @param string $msg=''
	 */
	public static function beamer(array $content=null, $msg='')
	{
		$tmpl = new etemplate('ranking.result.beamer');

		if (!is_array($content) && !($content = egw_cache::getSession('ranking', 'beamer')))
		{
			$result_state = egw_cache::getSession('ranking','result');
			$content = array(
				'calendar' => $result_state['calendar'],
				'comp' => $result_state['comp'],
				'cat' => $result_state['cat'] ? array(3 => $result_state['cat']) : array(),
				'beamer' => 1,
			);
		}
		if($content['comp']) $comp = ranking_result_bo::$instance->comp->read($content['comp']);

		if (ranking_result_bo::$instance->only_nation)
		{
			$calendar = ranking_result_bo::$instance->only_nation;
			$tmpl->disable_cells('calendar');
		}
		elseif ($comp && !$content['calendar'])
		{
			$calendar = $comp['nation'] ? $comp['nation'] : 'NULL';
		}
		elseif ($content['calendar'])
		{
			$calendar = $content['calendar'];
		}
		else
		{
			list($calendar) = each(ranking_result_bo::$instance->ranking_nations);
		}
		if (!$comp || ($comp['nation'] ? $comp['nation'] : 'NULL') != $calendar)
		{
			//echo "<p>calendar changed to '$calendar', comp is '$comp[nation]' not fitting --> reset </p>\n";
			$comp = false;
			$content['cat'] = array();
		}
		$content['calendar'] = $calendar;

		$sel_options = array(
			'detail' => array(
				0 => lang('None'),
				1 => lang('More'),
			),
			'calendar' => ranking_result_bo::$instance->ranking_nations,
			'comp'     => ranking_result_bo::$instance->comp->names(array(
				'nation' => $calendar,
				'datum < '.ranking_result_bo::$instance->db->quote(date('Y-m-d',time()+10*24*3600)),	// starting 10 days from now
				'datum > '.ranking_result_bo::$instance->db->quote(date('Y-m-d',time()-365*24*3600)),	// until one year back
				'gruppen IS NOT NULL',
			),0,'datum DESC'),
			'cat'      => ranking_result_bo::$instance->cats->names(array('rkey' => $comp['gruppen']),0),
		);

		$routes = $cats = array();
		for($i = $n = 3; isset($content['cat'][$i]); ++$i)
		{
			if($content['cat'][$i])
			{
				$cats[$n] = $content['cat'][$i];
				$routes[$n] = $content['route'][$i];

				$sel_options["route[$n]"] = array(
					'-1' => lang('General result'),
				)+ranking_result_bo::$instance->route->query_list('route_name','route_order',array(
						'WetId' => $comp['WetId'],
						'GrpId' => $content['cat'][$i],
					),'route_order DESC');
				++$n;
			}
		}
		// compose beamer url
		$link = '/ranking/sitemgr/digitalrock/'.($content['startlist'] ? 's' : 'e').'liste.html';
		$params = array('comp' => $content['comp']);
		if ((string)$content['detail'] !== '') $params['detail'] = $content['detail'];
		if ($content['beamer'])
		{
			$params['beamer'] = 1;
			$params['rotate'] = '';	// required for automatic up-down scrolling
		}
		foreach($cats as $n => $cat)
		{
			if (!$cat || (string)$routes[$n] === '') continue;

			if (!isset($params['cat']))
			{
				$params['cat'] = $cat;
				$params['route'] = $routes[$n];
			}
			$rotate .= ($rotate ? ':' : '').'c='.$cat.',r='.$routes[$n];
		}
		if ($content['padding']) $params['padding'] = $content['padding'];
		$content['href'] = egw::link($link,$params).(strpos($rotate,':') !== false ? '&rotate='.$rotate : '');
		$parts = parse_url($content['href']);
		$content['href'] = $parts['path'].'?'.$parts['query'];

		$readonlys['go'] = (string)$routes[3] === '';

		if ((string)$routes[$n] !== '') $cats[++$n] = '';	// one new line
		$content['cat'] = $cats;
		$content['route'] = $routes;

		egw_cache::setSession('ranking', 'beamer', $content);

		$tmpl->exec('ranking.ranking_beamer.beamer',$content,$sel_options,$readonlys,$content,2);
	}

	/**
	 * Init static vars
	 */
	public static function init_static()
	{
		include_once(EGW_INCLUDE_ROOT.'/ranking/inc/class.ranking_result_bo.inc.php');
		if (!isset(ranking_result_bo::$instance))
		{
			new ranking_result_bo();
		}
	}
}
ranking_beamer::init_static();