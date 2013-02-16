<?php
/**
 * EGroupware digital ROCK Rankings webservice access: xml
 *
 * Usage: http://www.digitalrock.de/egroupware/json.php?comp=yyy&cat=zzz[&route=xxx][&debug=1]
 *
 * @param comp competition number
 * @param cat  category number or rkey
 * @param route -1 = general result (default)
 *     0  = qualification
 *     1  = 2. qualification (if applicable)
 *     2  = further heats
 * @param debug 1: content-type: text/html
 *               2: additionally original route array
 *
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2010 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$
 */

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'currentapp'	=> 'sitemgr-link',	// anonymous should have NO ranking access
		'nonavbar'		=> True,
		'noheader'      => True,
		'autocreate_session_callback' => 'check_anon_access',
));
include('../header.inc.php');

/**
 * Check if we allow anon access and with which creditials
 *
 * @param array &$anon_account anon account_info with keys 'login', 'passwd' and optional 'passwd_type'
 * @return boolean true if we allow anon access, false otherwise
 */
function check_anon_access(&$anon_account)
{
	$anon_account = array(
		'login'  => 'anonymous',
		'passwd' => 'anonymous',
		'passwd_type' => 'text',
	);
	return true;
}

try
{
	if(isset($_GET['cat']) && !isset($_GET['comp']))
	{
		$export = new ranking_export();
		$result = $export->export_ranking($_GET['cat'], $_GET['date'], $_GET['cup']);
		$root_tag = 'ranking';
	}
	elseif (isset($_GET['nation']) || !isset($_GET['comp']))
	{
		$export = new ranking_export();
		$result = $export->export_calendar($_GET['nation'], $_GET['year'], $_GET['filter']);
		$root_tag = 'calendar';
	}
	else
	{
		$result = ranking_export::export_route($_GET['comp'],$_GET['cat'],$_GET['route']);
		$root_tag = 'route';
	}
}
catch(Exception $e)
{
	header("HTTP/1.1 404 Not Found");
	echo "<html>\n<head>\n\t<title>Error ".$e->getMessage()."</title>\n</head>\n";
	echo "<body>\n\t<h1>".$e->getMessage()."</h1>\n";
	echo "<p>The requested ressource was not found on this server.<br>\n<br>\n";
	echo 'URI: ' . $_SERVER['REQUEST_URI'] . "</p>\n";
	echo "</body></html>\n";
	exit;
}

$encoding = translation::charset();

$xml = new XMLWriter();
if (!isset($_GET['debug']) || !$_GET['debug'])
{
	$xml->openURI('php://output');
	header('Content-Type: application/xml; charset='.$encoding);
	if ($result['etag']) header('Etag: "'.$result['etag'].'"');
}
else
{
	$xml->openMemory();
	header('Content-Type: text/html; charset='.$encoding);
}
$xml->setIndent(true);
$xml->setIndentString("\t");
$xml->startDocument('1.0',$encoding);
$xml->startElement($root_tag);

foreach($result as $name => &$value)
{
	if (!is_array($value))
	{
		if ((string)$value !== '') $xml->writeElement($name,(string)$value);
	}
	elseif($value)
	{
		if (substr($name,-1) == 's') $name = substr($name,0,-1);
		$xml->startElement($name.(in_array($name, array('cat','cup','comp')) ? '' : 's'));
		foreach($value as $id => &$val)
		{
			if (!is_array($val))
			{
				if (!is_string($val)) $val = (string)$val;
				if ($name == 'route_name' || $name == 'cat' || $name == 'comp' || $name == 'cup')
				{
					$xml->startElement($name == 'route_name' ? $name : $id);
					if ($name == 'route_name') $xml->writeAttribute('route',$id);
					$xml->text($val);
					$xml->endElement();
				}
				else
				{
					//if (strlen($val) > 2)
					$xml->writeElement($name,$val);
				}
			}
			else	// only participants / arrays get here
			{
				$xml->startElement($name);
				write_array($xml,$val);
				$xml->endElement();
			}
		}
		$xml->endElement();
	}
}
$xml->endElement();

function write_array($xml,$arr)
{
	//error_log(__METHOD__.'(,'.array2string($participant).')');
	foreach($arr as $name => &$value)
	{
		if (!is_array($value))
		{
			//error_log(__METHOD__.'(,'.array2string($participant).') name='.array2string($name).', value='.array2string($value));
			$xml->writeElement($name,(string)$value);
		}
		elseif($name == 'cup')
		{
			$xml->startElement($name);
			write_array($xml,$value);
			$xml->endElement();
		}
		elseif($value)
		{
			if (substr($name,-1) == 's') $name = substr($name,0,-1);
			$xml->startElement($name.'s');
			foreach($value as $id => &$val)
			{
				if (is_array($val))
				{
					$xml->startElement($name);
					write_array($xml,$val);
					$xml->endElement();
				}
				else
				{
					$xml->writeElement($name,(string)$val);
				}
			}
			$xml->endElement();
		}
	}
}

if (isset($_GET['debug']) && $_GET['debug'])
{
	switch($_GET['debug'])
	{
		case 2:
			echo "<pre>".print_r($result,true)."</pre>\n";
			// fall through
		default:
			echo "<pre>".htmlspecialchars($xml->outputMemory(true));
			break;
	}
}
