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
		'nocachecontrol'=> 'public',
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

$result = ranking_export::export($root_tag);
$encoding = translation::charset();

$xml = new XMLWriter();
if (!isset($_GET['debug']) || !$_GET['debug'])
{
	header('Content-Type: application/xml; charset='.$encoding);
	egw_session::cache_control(isset($result['expires']) ? $result['expires'] : ranking_export::EXPORT_DEFAULT_EXPIRES);

	if (isset($result['etag']))
	{
		if ($result['etag'][0] != '"') $result['etag'] = '"'.$result['etag'].'"';
		header('Etag: '.$result['etag']);
	}
	if (isset($_SERVER['HTTP_IF_MATCH']) && $_SERVER['HTTP_IF_MATCH'] === $result['etag'])
	{
		header('HTTP/1.1 304 Not Modified');
		common::egw_exit();
	}
	$xml->openURI('php://output');
}
else
{
	$xml->openMemory();
	header('Content-Type: text/html; charset='.$encoding);
}
$xml->setIndent(true);
$xml->setIndentString("\t");
$xml->startDocument('1.0',$encoding);

if (isset($result[0]))
{
	$result = array($root_tag => $result);
}
else
{
	$xml->startElement($root_tag);
}
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
				if ($name == 'statistic') $xml->writeAttribute('boulder',$id);
				write_array($xml,$val);
				$xml->endElement();
			}
		}
		$xml->endElement();
	}
}
if (!isset($result[$root_tag])) $xml->endElement();

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
