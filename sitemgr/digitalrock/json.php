<?php
/**
 * Usage: http://digitalrock.de/json.php?route=xxx[&comp=yyy&cat=zzz][&debug=1]
 * 
 * @param route:
 * 	a) ha.09_arco: everything measured with old digital ROCK system
 *      ^    ^-- competitions name
 *      +-- route name (category letter + plus heat)
 *  b) -1 = general result
 *     0  = qualification
 *     1  = 2. qualification (if applicable)
 *     2  = further heats
 * @param comp (required only for b) competition number
 * @param cat  (required only for b) category number
 * @param debug 1: content-type: text/html
 *               2: additionally original route array
 */
// dont want html from functions.inc.php
$just_include = true;
$encoding = 'utf-8';
require('functions.inc.php'); // setzt $route nach $_GET['route']

ob_start();
$route = get_route($_GET['route'],false,$encoding);
ob_end_clean();

if (!isset($_GET['debug']) || !$_GET['debug'])
{
	header('Content-Type: application/json; charset='.$encoding);
}
else
{
	header('Content-Type: text/html; charset='.$encoding);
}

$json = json_encode($route);

if (isset($_GET['debug']) && $_GET['debug'])
{
	switch($_GET['debug'])
	{
		case 2:
			echo "<pre>".print_r($route,true)."</pre>\n";
			// fall through
		default:
			echo "<pre>".htmlspecialchars($json)."</pre>\n";
			break;
	}
}
else
{
	echo $json;
}
