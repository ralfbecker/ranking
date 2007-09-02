#!/usr/bin/php -qC
<?php
/**
 * eGroupWare digital ROCK Rankings - display "demon"
 *
 * @link http://www.egroupware.org
 * @package admin
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2007 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

// timeout for the select, aka display refresh rate, in microseconds 0.5s = 500000
define('DISPLAY_TIMEOUT',200000);
// max. time the false start was trigered before the start, to interpret it as false start
define('FALSE_START_DISTANCE',1.0);

ini_set('display_errors',true);
error_reporting(E_ALL & ~E_NOTICE);
// utf8 support
if (!extension_loaded('mbstring')) dl('mbstring.so');
if (ini_get('mbstring.func_overload') != 7) echo "mbstring.func_overload=7 required!!!\n\n";

//$_SERVER['argc']=3; $_SERVER['argv']=array('display.php','ralf','ralbec32');
if (isset($_SERVER['HTTP_HOST']))	// security precaution: forbit calling Timy demon as web-page
{
	die('<h1>timy.php must NOT be called as web-page --> exiting !!!</h1>');
}
elseif ($_SERVER['argc'] < 2)
{
	die_usage();
}

// Interface to timy device, eg. /dev/ttyUSB0 or host:port
$timy_interface = $_SERVER['argv'][1];
// Interface to display, eg. /dev/ttyUSB1 or host:port
$display_interfaces = array();
for ($n = 2; $n < $_SERVER['argc']; $n += ($n == 2 ? 2 : 1))
{
	$display_interfaces[] = $_SERVER['argv'][$n];
}
// port for the control connection, default localhost:19999, or eg. 2000 for a different localhost port
$control_addr = 'tcp://localhost:19999';
if ($_SERVER['argc'] > 3)
{
	$control_addr = is_numeric($_SERVER['argv'][3]) ? 'tcp://localhost:'.$_SERVER['argv'][3] : 'tcp://'.$_SERVER['argv'][3];
}

if (!function_exists('lang'))
{
	/**
	 * Replacement for eGW's lang function, no translation atm.
	 *
	 * @param string $str string to translate with replacements like %1
	 * @param string $args variable number of arguments
	 * @return string
	 */
	function lang($str,$args)
	{
		$args = func_get_args();
		$str = array_shift($args);
		
		return str_replace(array('%1','%2','%3','%4','%5','%6','%6'),$args,$str);
	}
}

// opening the Timy connections
if (strchr($timy_interface,':') !== false)
{
	list($host,$port) = explode(':',$timy_interface);

	$timy = fsockopen($host,$port);
}
else
{
	$timy = fopen($timy_interface,'r+');
	// switching echo off!
	system("stty 9600 -echo < $timy_interface");
}
if (!$timy)
{
	echo lang("Can't open connection to Timy at %1 !!!",$timy_interface)."\n";
	die_usage();
}

// opending the display connection(s)
$displays = array();
foreach($display_interfaces as $display_interface)
{
	if (strchr($display_interface,':') !== false && substr($display_interface,0,6) != 'php://')
	{
		list($host,$port) = explode(':',$display_interface);
	
		$display = fsockopen($host,$port);
	}
	else
	{
		$display = fopen($display_interface,'w');
		// switching echo off!
		system("stty 2400 -echo < $display_interface");
	}
	if (!$display)
	{
		echo lang("Can't open connection to displays at %1 !!!",$display_interface)."\n";
		die_usage();
	}
	$displays[] = $display;
}
// opening the controll socket
if (!($control = stream_socket_server($control_addr,$errnr,$error)))
{
	echo lang("Can't open control port (%1) %2 !!!",$control_addr,$error);
	die_usage();
}

// reading precision from Timy
$precision = 2;	// default 1/100 sec
fwrite($timy,"PRE?\n");
if (preg_match('/^PRE([0-4].)/',fgets($timy),$matches)) $precision = (int) $matches[1];
// reading rounding type from Timy
$rounding = 0;	// 0=floor (default), 1=ceil, 2=round
fwrite($timy,"RR?\n");
if (preg_match('/^RR([0-2].)/',fgets($timy),$matches)) $precision = (int) $matches[1];
echo "Timy configured for precision $precision (digits behind the dot) and rounding mode $rounding (0=floor, 1=ceil, 2=round)\n";

define('DIAG_DISPLAY',"\r%04d: %7.{$precision}lfs  %04d: %7.{$precision}lfs");
//gestopt: A016C        5:18.02
//laufend: B015.        7:45
//zwisch.: B015A        7:13.60
define('DISPLAY_FORMAT',($display_interface=='php://stdout'?"\n":'')."A%03d%s       %2.2s %s\nB%03d%s       %2.2s %s\n");

// event loop
global $times,$left_sequence, $right_sequence, $left_mstart, $right_mstart;
$client_bufs = $clients = array();
$time_buf = '';
stream_set_blocking($timy,0);
while(true)
{
	$read = $clients;
	$read[] = $timy;
	$read[] = $control;
	//echo "stream_select(array(".implode(', ',$read)."),null,null,".floor(DISPLAY_TIMEOUT).",".ceil(DISPLAY_TIMEOUT*1000000).")\n";
	
	// if we have a running time, we have to set a timeout for the next DISPLAY_TIMEOUT interval
	if (($start = $right_mstart ? $right_mstart : $left_mstart))
	{
		$now = microtime(true);
		$timeout = DISPLAY_TIMEOUT - ((1000000*($now - $start)) % DISPLAY_TIMEOUT);
		//echo "\nstart=$start, now=$now ==> timeout=$timeout\n";
	}
	else
	{
		$timeout = DISPLAY_TIMEOUT;
	}
	if (stream_select($read,$write=null,$except=null,floor($timeout/1000000),$timeout % 1000000))//floor(DISPLAY_TIMEOUT), ceil(DISPLAY_TIMEOUT*1000000)))
	{
		//echo "stream_select returned, with read: ".implode(', ',$read)."\n";
		// handle the streams
		foreach($read as $f)
		{
			if ($f === $timy)	// time device
			{
				$time_buf .= fgets($timy);
				if (substr($time_buf,-1) == "\n")
				{
					handle_time($time_buf);
					$time_buf = '';
				}
			}
			elseif($f === $control)	// control connection, accepting client connections
			{
				if (($client = stream_socket_accept($control,-1,$caddr)))
				{
					stream_set_blocking($client,0);
					echo "accepted control connection $client from '$caddr'.\n";
					$clients[] = $client;
				}
				else
				{
					echo "failed to accept client connection\n";
				}
			}
			elseif (in_array($f,$clients,true))	// client connection
			{
				if (feof($f))	// client died without sending close
				{
					handle_client($f,"close\n");
					continue;
				}
				$caddr = stream_socket_get_name($f,true);
				
				$client_bufs[$caddr] .= fgets($f);
				echo $caddr.': '.$client_bufs[$caddr];
				if (substr($client_bufs[$caddr],-1) == "\n")
				{
					handle_client($f,$client_bufs[$caddr]);
					unset($client_bufs[$caddr]);
				}
			}
			else
			{
				error_log("unrecognised stream returned from select!");
			}
		}
	}
	else
	{
		// refresh the display
		handle_display();
	}
}

$times = array();

/**
 * Handle input from the time device (complete lines)
 *
 * @param string $str
 */
function handle_time($str)
{
	global $timy,$times,$left_sequence, $right_sequence, $left_mstart, $right_mstart;
	static $left_fstart, $right_fstart, $left_false, $right_false;

	if (is_numeric($str{0})) return;	// ignore 1/10s timestamp of pc-timer mode
	
	echo "\n".$str;
	
	$t_str = trim(substr($str,10,13));
	list($h,$m,$s) = explode(':',$t_str);
	$time = 3600 * $h + 60 * $m + $s;
	
	$channel = null;
	if ($str{6} == 'C')
	{
		$channel = $str{7} + ($str{8} == 'M' ? 10 : 0);
		switch($str{0})
		{
			case 'c':	// time cleared
				$channel += 30;
				break;
			case 'd':	// disqualified
				$channel += 40;
				break;
		}
	}
	// set sequences ($str{0} == 'n') use channel 20 for right and 21 for left
	elseif ($str{0} == 'n')
	{
		$channel = 20 + (int)($str{5} != 'r');
	}
	// measured time (RT) use channel 22 for right and 23 for left
	elseif ($str{6} == 'R' && $str{7} == 'T')
	{
		$channel = 22 + (int)($str{5} != 'r');
	}
	$sequence = (int)substr($str,1,4);
	
	echo "$channel: $time ($sequence)\n\n";

	if (is_numeric($channel))	// $channel===null, matches 0 otherwise!
	switch($channel)
	{
		case 0:	// right start
			$right_mstart = microtime(true);
			$right_fstart = $time;
			if ($right_false && $right_false < $right_fstart && $right_fstart-$right_false < FALSE_START_DISTANCE)
			{
				notify_clients(true,'false',$time = timy_round($right_false-$right_fstart));
				$right_fstart = $right_mstart = null;
				$times[_sequence2startnr($sequence)] = $time;
				fwrite($timy,'DTP '.$sequence.'lSZ  '.$t_str."\n");	// print start-time
				fwrite($timy,'DTP'._sequence2startnr($sequence).': false right '.$time."\n");
			}
			else
			{
				notify_clients(true,'start',$time);
			}
			break;
		
		case 3:	// left start
			$left_mstart = $right_mstart ? $right_mstart : microtime(true);
			$left_fstart = $time;
			if ($left_false && $left_false < $left_fstart && $left_fstart-$left_false < FALSE_START_DISTANCE)
			{
				notify_clients(false,'false',$time = timy_round($left_false-$left_fstart));
				$left_fstart = $left_mstart = null;
				$times[_sequence2startnr($sequence)] = $time;
				fwrite($timy,'DTP '.$sequence.'rSZ  '.$t_str."\n");	// print start-time
				fwrite($timy,'DTP'._sequence2startnr($sequence).': false left '.$time."\n");
			}
			else
			{
				notify_clients($channel==0,'start',$time);
			}
			break;
		
		case 1:		// right stop
			$time = timy_round($time - $right_fstart);
			$times[_sequence2startnr($sequence)] += $time;
			notify_clients(true,'stop',$time);
			$right_fstart = $right_mstart = null;
			break;
			
		case 4:	// left stop
			$time = timy_round($time - $left_fstart);
			$times[_sequence2startnr($sequence)] += $time;
			notify_clients(false,'stop',$time);
			$left_fstart = $left_mstart = null;
			break;
/*
		case 22:	// right time (DUAL TIMER programm)
			$times[_sequence2startnr($sequence)] += $time;
			$right_fstart = $right_mstart = null;
			notify_clients(true,'stop',$time);
			break;

		case 23:	// left time (DUAL TIMER programm)
			$times[_sequence2startnr($sequence)] += $time;
			$left_fstart = $left_mstart = null;
			notify_clients(false,'stop',$time);
			break;
*/	
		case 2:	// right false start
			$right_false = $time;
			break;
			
		case 5:	// left false start
			$left_false = $time;
			break;
			
		case 20:	// right startnr
			$right_sequence = $sequence;
			$right_false = $right_fstart = $right_mstart = null;
			break;
		
		case 21:	// left startnr
			$left_sequence = $sequence;
			$left_false = $left_fstart = $left_mstart = null;
			break;
	}
	echo "left ($left_sequence): time={$times[_sequence2startnr($left_sequence)]}, fstart=$left_fstart, mstart=$left_mstart, false=$left_false; right ($right_sequence): time={$times[_sequence2startnr($right_sequence)]}, fstart=$right_fstart, mstart=$right_mstart, false=$right_false\n";
	
	handle_display();
}

/**
 * Refresh the display or diagnose display
 *
 */
function handle_display()
{
	global $times,$left_sequence, $right_sequence, $left_mstart, $right_mstart;
	global $displays,$precision;
	
	$now = microtime(true);

	$lsnr = _sequence2startnr($left_sequence);
	$ltime = number_format($times[$lsnr],$precision);
	$ltype = 'C';
	if ($left_mstart)	// running time
	{
		//$ltime = number_format($ltime + $now - $left_mstart,$precision);
		$ltime = number_format($ltime + $now - $left_mstart,1).str_repeat(' ',$precision-1);
		//$ltime = round($ltime + $now - $left_mstart).'.'.str_repeat(' ',$precision);
		$ltype = '.';
	}
	$lsec = substr($ltime,-3-$precision);		// 2-digit seconds plus precsion
	$lmin = substr('  '.$ltime,-3-$precision-2,2);	// 2-digit "minutes" hundred+thausend seconds

	$rsnr = _sequence2startnr($right_sequence);
	$rtime = number_format($times[$rsnr],$precision);
	$rtype = 'C';
	if ($right_mstart)	// running time
	{
		//$rtime = number_format($rtime + $now - $right_mstart,$precision);
		$rtime = number_format($rtime + $now - $right_mstart,1).str_repeat(' ',$precision-1);
		//$rtime = round($rtime + $now - $right_mstart).'.'.str_repeat(' ',$precision);
		$rtype = '.';
	}
	$rsec = substr($rtime,-3-$precision);	// 2-digit seconds plus precsion
	$rmin = substr('  '.$rtime,-3-$precision-2,2);	// 2-digit "minutes" hundred+thausend seconds

	if (DIAG_DISPLAY)
	{
		printf(DIAG_DISPLAY,$left_sequence,$ltime,$right_sequence,$rtime);
	}
//define('DISPLAY_FORMAT',"A%03d%s       %2.2s %s\nB%03d%s       %2.2s %s\n");
	$str = sprintf(DISPLAY_FORMAT,$rsnr,$rtype,$rmin,$rsec,$lsnr,$ltype,$lmin,$lsec);
	foreach($displays as $display)
	{
		fwrite($display,$str);
	}
}

/**
 * Handle a client request
 *
 * @param resource $client socket connection to client (non_blocking!)
 * @param string $str message send from client
 */
function handle_client($client,$str)
{
	global $timy,$clients,$times;
	// clients to notify on finished or aborted measurements
	global $left_notify, $right_notify;
	
	list($command,$side,$startnr,$time,$athlete) = explode(':',trim($str),5);
	switch($command)	// remove "\n"
	{
		case 'notify':
			if ($side != 'l')
			{
				if ($right_notify) handle_client($right_notify,"close\n");	// close old connection if exist
				$right_notify = $client;
			}
			if ($side != 'r')
			{
				if ($left_notify) handle_client($left_notify,"close\n");	// close old connection if exist
				$left_notify = $client;
			}
			break;
			
		case 'close':		
			unset($clients[array_search($client,$clients)]);
			fclose($client);
			if ($left_notify == $client) $left_notify = null;
			if ($right_notify == $client) $right_notify = null;
			break;
			
		case 'start':	// set startnr, time and athlete
			if ($startnr) fwrite($timy,'DTP'.$startnr.': '.$athlete."\n");
			$sequence = $startnr ? _get_free_sequence($startnr) : 0;
			fwrite($timy,sprintf("#%04d%s\n",$sequence,$side));
			if ($startnr) $times[$startnr] = $time;
			break;

		default:
			fwrite($timy,$str);
			break;
	}
}

function _get_free_sequence($snr)
{
	static $sequences;
	
	$n = 0;
	do 
	{
		$seq = $n*($n < 10 ? 1000 : 100) + $snr;
		++$n;
	}
	while (in_array($seq,$sequences));
	
	$sequences[] = $seq;
	
	return $seq;
}

/**
 * Notify subscribed clients
 *
 * @param boolean $right true: right, false: left
 * @param string $event eg. start, stop, false
 * @param double $time
 * @return int/boolean number of bytes written to client, false on eof
 */
function notify_clients($right,$event,$time)
{
	// clients to notify on finished or aborted measurements
	global $left_notify, $right_notify;
	global $precision;
	
	if ($right)
	{
		$client =& $right_notify;
	}
	else
	{
		$client =& $left_notify;	
	}
	if (!is_resource($client)) return;	// noone subscribed
	
	if (feof($client))	// client no longer listening
	{
		$client = null;
		return false;
	}
	return fwrite($client,($right ? 'r' : 'l').':'.$event.':'.$time."\n");
}

/**
 * Convert a sequence to a startnr
 *
 * @param int $s sequenz
 * @return int startnr
 */
function _sequence2startnr($s)
{
	return $s ? $s % 1000 : $s;
}

/**
 * Rounding like Timy (configurable in Timy)
 *
 * @param float $time
 * @return float
 */
function timy_round($time)
{
	global $rounding,$precision;	// 0=floor (default), 1=ceil, 2=round
	
	if ($rounding != 2)
	{
		$pot = pow(10,$precision);
		$time = $rounding == 1 ? ceil($pot*$time)/$pot : floor($pot*$time)/$pot;
	}
	return round($time,$precision);
}

function die_usage()
{
	$cmd = basename($_SERVER['argv'][0]);
	//die("Usage: $cmd account[@domain] password interface\n");
	die("Usage: $cmd <timy-interface, eg. /dev/ttyUSB0> [display-interface, eg. /dev/ttyUSB1, or host:port] [control-addr, default localhost:19999] [display2, ...]\n");
}
