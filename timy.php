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
define('DISPLAY_TIMEOUT',200000);	// baud 2400 --> 500000, 9600 --> 2000000
/**
 * Arco 2014:
 * - timy seriell (!) über serial/USB Converter mit Mac bzw. openSUSE 12.1 VM verbunden
 * - timy.php wird auf openSUSE 12.1 VM gestartet: timy.php /dev/ttyUSB0
 * - ssh root@172.16.163.137 # ssh von mac to VM
 * - ssh -A -p9922 -g -R10.40.8.211:19999:localhost:19999 root@farm.stylite.de # tunnelt in VM laufendes timy.php auf farm
 *   (auf farm ist in /etc/ssh/sshd_config GatewayPort=Yes gesetzt, damit 10.40.8.211 funktioniert)
 * - im resultservice wird Host: 10.40.8.211 und Port: 19999 eingestellt
 */
/**
 * Timy2 with "Speed Climbing" programm using RS232 via our Prologic Serial/USB converter
 * (Timy USB does NOT send times, like RS232 and needs "modprobe usbserial vendor=0x0c4a product=0x0889" to detect Timy as serial device)
 * ("modprobe usbserial vendor=0x0c4a product=0x088a" for Timy2, but Raspberry Pi seems to have problems with Timy2)
 *
 * Start and stop without false start used
 * n0001l                      <-- startnumber left
 * n0003r                      <-- startnumber right
 *  0003rC0  06:34:40.3983 00  <-- start right
 *  0001lC3  06:34:40.3983 00  <-- start left
 *  0001lC4  06:34:55.1950 00  <-- stop left time absolute
 *  0001lc4  00:00:14.7967 00  <-- stop left time diff to start
 *  0003rC1  06:34:57.9630 00  <-- stop right time absolute
 *  0003rc1  00:00:17.5647 00  <-- stop right time diff to start
 *
 * False start right side:
 * n0004l                      <-- startnumber left
 * n0006r                      <-- startnumber right
 *  0000rC2  06:35:29.3071 00  <-- false start right, because before rC0
 *  0006rC0  06:35:29.3762 00  <-- start right
 *  0004lC3  06:35:29.3762 00  <-- start left
 *  0000lC5  06:35:31.0879 00  <-- correct start, because after lC3
 */
/**
 * Programmierung Display:
 * - Taste drücken bis "br 09" kommt: br ist menupunkt (brightness), 09 ist wert
 * - Aendern des Menupunktes bzw Wertes wenn er blinkt Taste drücken
 * - Auf Menupunkt SE gehen und dort h9 (9 = 9600 baud einstellen, 2 = 2400 baud)
 * - Weiter menupunkt aendern bis Anzeige schwarz
 *
 * Nach Neustart des timy.php Kontrollprogramms, MUSS auch der Timy gelöscht werden
 * (da die Sequenznummern sich sonst überschneiden, da timy.php von vorne beginnt):
 * --> Neustart von Dualtimer Programm:
 * Liste Taste (oberhalb 7+8) blaettern bis programms, OK, F0 Change drücken, Dualtimer auswählen, OK
 * CLR zum Löschen der Zeiten drücken, danach beliebige Taste drücken
 *
 * Abbruch nach Sturz: Erneut auf [Start] drücken UND gestürztem Tn "Sturz" eintragen und aktualisieren
 */
// max. time the false start was trigered before the start, to interpret it as false start
define('FALSE_START_DISTANCE',1.0);
// time after start-signal still detected as false start
define('FALSE_START_AFTER', 0.1);

ini_set('display_errors',true);
error_reporting(E_ALL & ~E_NOTICE);
// utf8 support
if (!extension_loaded('mbstring')) dl('mbstring.so');
if (ini_get('mbstring.func_overload') != 7) echo "mbstring.func_overload=7 required!!!\n\n";

if (php_sapi_name() !== 'cli')	// security precaution: forbit calling Timy demon as web-page
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
	function lang($str)
	{
		$args = func_get_args();
		array_shift($args);	// remove $str

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
	system("stty 9600 -echo -crtscts < $timy_interface");
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
		system("stty 9600 -echo < $display_interface");
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
if (preg_match('/^PRE([0-4])/',$out=fgets($timy),$matches)) $precision = (int) $matches[1];
// reading rounding type from Timy
$rounding = 0;	// 0=floor (default), 1=ceil, 2=round
fwrite($timy,"RR?\n");
if (preg_match('/^RR([0-2])/',$out=fgets($timy),$matches)) $rounding = (int) $matches[1];
echo "Timy configured for precision $precision (digits behind the dot) and rounding mode $rounding (0=floor, 1=ceil, 2=round)\n";

define('DIAG_DISPLAY',"\r%04d: %7.{$precision}lfs  %04d: %7.{$precision}lfs");
//stoped:       A016C        5:18.02
//running:      B015.        7:45
//intermediate: B015A        7:13.60
define('DISPLAY_FORMAT',"A%03d%s       %2.2s:%s   \rB%03d%s       %2.2s:%s   \r");

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
	$write = $except = null;
	if (stream_select($read, $write, $except, floor($timeout/1000000), $timeout % 1000000))
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
					echo "\naccepted control connection $client from '$caddr'.\n";
					$clients[] = $client;
				}
				else
				{
					echo "\nfailed to accept client connection\n";
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
	static $left_fstart=null, $right_fstart=null, $left_false=null, $right_false=null;
	global $left_notify, $right_notify,$precision;

	if (is_numeric($str{0})) return;	// ignore 1/10s timestamp of pc-timer mode

	//echo "\n".$str;

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

	//echo "$channel: $time ($sequence)\n\n";

	if (is_numeric($channel))	// $channel===null, matches 0 otherwise!
	switch($channel)
	{
		case 3:	// left start (reported after right)
			if ($left_notify == $right_notify) break;	// both channel are used by same client --> ignore left/second start
			// fall through
		case 0:	// right start (reported first)
			$false_starts = array();
			if ($channel == 0 || $left_notify == $right_notify)
			{
				$right_mstart = microtime(true);
				$right_fstart = $time;
				if ($right_false && $right_false < $right_fstart && $right_fstart-$right_false < FALSE_START_DISTANCE)
				{
					$times[_sequence2startnr($sequence)] = $ftime = timy_round($right_false-$right_fstart);
					$right_fstart = $right_mstart = null;
					fwrite($timy,'DTP '.$sequence.'lSZ  '.$t_str."\n");	// print start-time
					fwrite($timy,'DTP'._sequence2startnr($sequence).': false right '.number_format($ftime,$precision)."\n");
					$false_starts['r'] = $ftime;
				}
				else
				{
					notify_clients('right','start',$time);
				}
			}
			if ($channel == 3 || $left_notify == $right_notify)
			{
				if ($channel != 3) $sequence = $left_sequence;
				$left_mstart = $right_mstart ? $right_mstart : microtime(true);
				$left_fstart = $time;
				if ($left_false && $left_false < $left_fstart && $left_fstart-$left_false < FALSE_START_DISTANCE)
				{
					$times[_sequence2startnr($sequence)] = $ftime = timy_round($left_false-$left_fstart);
					$left_fstart = $left_mstart = null;
					fwrite($timy,'DTP '.$sequence.'rSZ  '.$t_str."\n");	// print start-time
					fwrite($timy,'DTP'._sequence2startnr($sequence).': false left '.number_format($ftime,$precision)."\n");
					$false_starts['l'] = $ftime;
				}
				else
				{
					notify_clients('left','start',$time);
				}
			}
			if ($false_starts)
			{
				$which = count($false_starts) == 2 ? 'both' : (isset($false_starts['r']) ? 'right' : 'left');
				notify_clients($which,'false',$which != 'left' ? $false_starts['r'] : $false_starts['l'],$false_starts['l']);
			}
			break;

		case 1:		// right stop
			$time = timy_round($time - $right_fstart);
			$times[_sequence2startnr($sequence)] += $time;
			notify_clients('right','stop',$time);
			$right_fstart = $right_mstart = null;
			break;

		case 4:	// left stop
			$time = timy_round($time - $left_fstart);
			$times[_sequence2startnr($sequence)] += $time;
			notify_clients('left','stop',$time);
			$left_fstart = $left_mstart = null;
			break;
/*
		case 22:	// right time (DUAL TIMER programm)
			$times[_sequence2startnr($sequence)] += $time;
			$right_fstart = $right_mstart = null;
			notify_clients('right','stop',$time);
			break;

		case 23:	// left time (DUAL TIMER programm)
			$times[_sequence2startnr($sequence)] += $time;
			$left_fstart = $left_mstart = null;
			notify_clients('left','stop',$time);
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
	//echo "left ($left_sequence): time={$times[_sequence2startnr($left_sequence)]}, fstart=$left_fstart, mstart=$left_mstart, false=$left_false; right ($right_sequence): time={$times[_sequence2startnr($right_sequence)]}, fstart=$right_fstart, mstart=$right_mstart, false=$right_false\n";

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
	$ltime = is_numeric($times[$lsnr]) ? number_format($times[$lsnr],$precision) : '';
	$ltype = 'C';
	if ($left_mstart)	// running time
	{
		//$ltime = number_format($ltime + $now - $left_mstart,$precision);
		$ltime = number_format($ltime + $now - $left_mstart,1).str_repeat(' ',$precision-1);
		//$ltime = round($ltime + $now - $left_mstart).'.'.str_repeat(' ',$precision);
		// supresses tenth! $ltype = '.';
	}
	$lsec = substr('  '.$ltime,-3-$precision);		// 2-digit seconds plus precsion
	$lmin = substr('  '.$ltime,-3-$precision-2,2);	// 2-digit "minutes" hundred+thausend seconds

	$rsnr = _sequence2startnr($right_sequence);
	$rtime = is_numeric($times[$rsnr]) ? number_format($times[$rsnr],$precision) : '';
	$rtype = 'C';
	if ($right_mstart)	// running time
	{
		//$rtime = number_format($rtime + $now - $right_mstart,$precision);
		$rtime = number_format($rtime + $now - $right_mstart,1).str_repeat(' ',$precision-1);
		//$rtime = round($rtime + $now - $right_mstart).'.'.str_repeat(' ',$precision);
		// supresses tenth! $rtype = '.';
	}
	$rsec = substr('  '.$rtime,-3-$precision);	// 2-digit seconds plus precsion
	$rmin = substr('  '.$rtime,-3-$precision-2,2);	// 2-digit "minutes" hundred+thausend seconds

	if (DIAG_DISPLAY)
	{
		printf(DIAG_DISPLAY,$left_sequence,$ltime,$right_sequence,$rtime);
	}
	//define('DISPLAY_FORMAT',"A%03d%s       %2.2s:%s   \rB%03d%s       %2.2s:%s   \r");
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
	static $sequences = array();

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
 * @param string $which "left", "right" or "both"
 * @param string $event eg. start, stop, false
 * @param double $time
 * @param double $time2 =null left time if $which == 'both'
 * @return int/boolean number of bytes written to client, false on eof
 */
function notify_clients($which,$event,$time,$time2=null)
{
	// clients to notify on finished or aborted measurements
	global $left_notify, $right_notify;
	global $precision;

	if ($which !== 'left')	// right or both
	{
		$client =& $right_notify;
	}
	else	// left
	{
		$client =& $left_notify;
	}
	if (!is_resource($client)) return;	// noone subscribed

	if (feof($client))	// client no longer listening
	{
		$client = null;
		return false;
	}
	$str = $which{0}.':'.$event.':'.number_format($time,$precision).($which == 'both' ? ':'.number_format($time2,$precision) : '');

	//echo "notify_clients($which,$event,$time,$time2): $str\n";

	return fwrite($client,$str."\n");
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
