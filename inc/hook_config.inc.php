<?php
/**
 * eGroupWare digital ROCK Rankings - configuration
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2006-14 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$
 */

if (!is_object($GLOBALS['ranking_result_bo']))
{
	try {
		$GLOBALS['ranking_result_bo'] = new ranking_result_bo();
	}
	catch(Exception $e) {
		echo "<p style='color: red; font-size: 120%;'>".$e->getMessage()."</p>\n";
	}
}

function _options_from($options,$value,$add_not_set=true)
{
	if (!is_array($options) || !count($options)) return '<option>Select AND save the above first!</option>'."\n";

	if ($add_not_set) $html = '<option value="">-- Not set / switched off --</option>'."\n";
	foreach($options as $val => $label)
	{
		$html .= '<option value="'.htmlspecialchars($val).'"'.
			((string)$val === (string)$value ? ' selected="1"' : '').
			'>'.$label."</options>\n";
	}
	return $html;
}

function rock_import_calendar($config)
{
	return _options_from($GLOBALS['ranking_result_bo'] ? $GLOBALS['ranking_result_bo']->ranking_nations : null,$config['rock_import_calendar'],false);
}

function rock_import_comp($config)
{
	return _options_from($GLOBALS['ranking_result_bo'] ? $GLOBALS['ranking_result_bo']->comp->names(array(
		'nation' => $config['rock_import_calendar'],
		'datum >= '.$GLOBALS['ranking_result_bo']->db->quote(date('Y-m-d',time()-10*24*3600)),
		'gruppen IS NOT NULL',
	),0,'datum ASC') : null,$config['rock_import_comp']);
}

function rock_import_cat1($config)
{
	return _options_from($config['rock_import_comp'] && $GLOBALS['ranking_result_bo'] && ($comp = $GLOBALS['ranking_result_bo']->comp->read($config['rock_import_comp'])) ?
		$GLOBALS['ranking_result_bo']->cats->names(array('rkey' => $comp['gruppen']),0) : null,$config['rock_import_cat1']);
}

function rock_import_cat2($config)
{
	return _options_from($config['rock_import_comp'] && $GLOBALS['ranking_result_bo'] && ($comp = $GLOBALS['ranking_result_bo']->comp->read($config['rock_import_comp'])) ?
		$GLOBALS['ranking_result_bo']->cats->names(array('rkey' => $comp['gruppen']),0) : null,$config['rock_import_cat2']);
}

function _get_routes($comp,$cat)
{
	if (!$comp || !$cat || !$GLOBALS['ranking_result_bo']) return null;

	$routes = $GLOBALS['ranking_result_bo']->route->query_list('route_name','route_order',array(
		'WetId' => $comp,
		'GrpId' => $cat,
	),'route_order DESC');

	foreach($GLOBALS['ranking_result_bo']->order_nums as $route_order => $label)
	{
		if ($route_order == -1)
		{
			unset($routes[$route_order]);
		}
		elseif(isset($routes[$route_order]))
		{
			$routes[$route_order] .= "($label)";
		}
		else
		{
			$routes[$route_order] = "New $label";
		}
	}
	return $routes;
}

function rock_import_route1($config)
{
	return _options_from(_get_routes($config['rock_import_comp'],$config['rock_import_cat1']),$config['rock_import_route1'],false);
}

function rock_import_route2($config)
{
	return _options_from(_get_routes($config['rock_import_comp'],$config['rock_import_cat2']),$config['rock_import_route2'],false);
}
