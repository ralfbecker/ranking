<?php
/* $Id: cache.php,v 1.4 2009/06/05 09:30:20 ralf Exp $ */

$cache_dir  = '/var/lib/egroupware/digitalrock.de/tmp/digitalrock-cache';
$cache_time = 15*60;
$cache_log = false;

/**
 * Check if the page is already cached: use that cache or create one
 *
 * @param string|array $keys =null default $_GET || $_SERVER['QUERY_STRING']
 * @param int $cache_time =null how long to cache the page in sec, default $GLOBALS['cache_time']
 */
function do_cache($keys=null,$cache_time=null)
{
	if (!$GLOBALS['cache_dir'] || !$GLOBALS['cache_time'])
	{
		return;	// cache is switched off
	}
	if (!file_exists($GLOBALS['cache_dir']) && !mkdir($GLOBALS['cache_dir'],0777,true) || !is_writable($GLOBALS['cache_dir']))
	{
		error_log("Can't create or write to cache_dir='$GLOBALS[cache_dir]'");
		return;
	}
	if (is_null($keys))
	{
		$keys = count($_GET) > 1 || strpos($_SERVER['QUERY_STRING'],'=') !== false ? $_GET : $_SERVER['QUERY_STRING'];
	}
	if (is_array($keys))
	{
		ksort($keys);			// normalize the array by sorting it by key, to allow better cache hits
		$keys = http_build_query($keys);
	}
	if($GLOBALS['cache_log']) error_log($GLOBALS['cache_dir'].$_SERVER['PHP_SELF'].($keys ? '?'.$keys : ''));

	if (is_null($cache_time)) $cache_time = $GLOBALS['cache_time'];

	$cache_filename = $GLOBALS['cache_dir'].$_SERVER['PHP_SELF'].($keys ? '?'.$keys : '');

	// check if we have a non-empty cache file (empty ones are currently created by an other process
	if (file_exists($cache_filename) && filesize($cache_filename))
	{
		// if cache is "fresh" enough, use it
		if (time()-filectime($cache_filename) < $cache_time && ($f = fopen($cache_filename,'r')))
		{
			if($GLOBALS['cache_log']) error_log(__FUNCTION__.": using cache $cache_filename from ".date('Y-m-d H:i:s',filectime($cache_filename)));
			header('X-cached-from: '.date('Y-m-d H:i:s',filectime($cache_filename)));
			header('Content-type: text/html; charset=utf-8');
			fpassthru($f);
			fclose($f);
			exit;
		}
		// cache file is too old --> remove it
		if($GLOBALS['cache_log']) error_log(__FUNCTION__.": removing old cache $cache_filename from ".date('Y-m-d H:i:s',filectime($cache_filename)));
		unlink($cache_filename);
	}
	// create new cache
	if (($GLOBALS['cache_file'] = @fopen($cache_filename,'x')))
	{
		if($GLOBALS['cache_log']) error_log(__FUNCTION__.": creating new cache $cache_filename");
		register_shutdown_function('do_cache_shutdown');

		ob_start();
	}
	else
	{
		if($GLOBALS['cache_log']) error_log(__FUNCTION__.": ERROR creating new cache $cache_filename");
	}
}

function do_cache_shutdown()
{
	fwrite($GLOBALS['cache_file'],ob_get_flush());
	fclose($GLOBALS['cache_file']);
}
