<?php
/**
 * Usage: http://digitalrock.de/xml.php?route=xxx[&comp=yyy&cat=zzz][&debug=1]
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
$encoding = 'utf-8';	// XMLWriter seems to have problems with other encodings
require('functions.inc.php'); // setzt $route nach $_GET['route']

ob_start();
$route = get_route($_GET['route'],false,$encoding);
ob_end_clean();

// translate some of the names
static $names = array(
	'bezeichnung' => 'description',
	'frei_str' => 'official',
	'isolation' => 'isolation',
	'start' => 'start',
	'quote' => 'quota',
	'GrpId' => 'category',
	'changes' => 'changes',
	'route_type' => 'type',
	'last_modified' => 'modified',
	'num_problems' => 'problems',
	'teilnehmer' => 'participant',
	'jury' => 'judge',
	'route_names' => 'route_name',
);
$xml = new XMLWriter();
if (!isset($_GET['debug']) || !$_GET['debug'])
{
	$xml->openURI('php://output');
	header('Content-Type: application/xml; charset='.$encoding);
}
else
{
	$xml->openMemory();
	header('Content-Type: text/html; charset='.$encoding);
}
$xml->setIndent(true);
$xml->setIndentString("\t");
$xml->startDocument('1.0',$encoding);
$xml->startElement('route');
foreach($_GET as $name => $value)
{
	$xml->writeAttribute($name,$value);
}
require_once('/usr/share/egroupware/phpgwapi/inc/common_functions.inc.php');
foreach($route as $name => &$value)
{
	if (!isset($names[$name])) continue;
	$name = $names[$name];
	
	if (!is_array($value))
	{
		if ((string)$value !== '') $xml->writeElement($name,(string)$value);
	}
	elseif($value)
	{
		$xml->startElement($name == 'route_names' ? 'route_names' : $name.'s');
		foreach($value as $id => &$val)
		{
			if (!is_array($val))
			{
				if (!is_string($val)) $val = (string)$val;
				if ($val !== '')
				{
					if ($name == 'route_names' || $name == 'route_name')
					{
						$xml->startElement('route_name');
						$xml->writeAttribute('route',$id);
						$xml->text($val);
						$xml->endElement();
					}
					else
					{
						if (strlen($val) > 2) $xml->writeElement($name,$val);
					}
				}
			}
			else	// only participants get here
			{
				$xml->startElement($name);
				write_participant($xml,$val);
				$xml->endElement();
			}
		}
		$xml->endElement();
	}
}
$xml->endElement();

function write_participant($xml,$participant)
{
	if (isset($participant['PerId']))
	{
		$xml->writeAttribute('athlete',$participant['PerId']);
		unset($participant['PerId']);
	}
	else
	{
		list($athlete) = explode('+',$participant['key']);
		$xml->writeAttribute('athlete',$athlete);
	}
	$xml->writeAttribute('key',$participant['key']);
	unset($participant['key']);

	foreach($participant as $name => &$value)
	{
		switch($name)
		{
			case 'result_detail':
			case 'RouteResults.*':
				break;	// ignore
			default:
				if (!is_array($value))
				{
					if ((string)$value !== '') $xml->writeElement($name,(string)$value);
				}
				elseif($value)
				{
					foreach($value as $n => &$val)
					{
						if (trim($val) !== '' && $val[0] != '?')
						{
							$xml->writeElement($name.($n ? $n : ''),trim($val));
						}
					}
				}
				break;
		}
	}
}

if (isset($_GET['debug']) && $_GET['debug'])
{
	switch($_GET['debug'])
	{
		case 2:
			echo "<pre>".print_r($route,true)."</pre>\n";
			// fall through
		default:
			echo "<pre>".htmlspecialchars($xml->outputMemory(true));
			break;
	}
}
