<?php
/**
 * eGroupWare digital ROCK Rankings - time measurement
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2007 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$ 
 */

class ranking_time_measurement
{
	/**
	 * Socket to the time control program
	 *
	 * @var resource
	 */
	private $socket;
	
	/**
	 * Constructor
	 *
	 * @return ranking_time_measurement
	 */
	function __construct($host=null,$port=null)
	{
		if (!is_null($host))
		{
			$this->open($host,$port);
		}
	}
	
	/**
	 * Open time control program socket
	 *
	 * @param string $host
	 * @param int $port
	 * @return boolean true on success, false otherwise
	 */
	function open($host,$port)
	{
		if (!($this->socket = stream_socket_client("tcp://$host:$port",$this->errno,$this->error)))
		{
			return false;
		}
		return true;
	}
	
	/**
	 * Check if we are successfull connected to the time controll programm
	 *
	 * @return boolean
	 */
	function is_connected()
	{
		return is_resource($this->socket) && !feof($this->socket);
	}
	
	/**
	 * Send a string to the time device
	 *
	 * @param string $str
	 * @return boolean true on success, false on error
	 */
	function send($str)
	{
		if (!$this->is_connected()) return false;
		
		if (fwrite($this->socket,$str."\n"))
		{
			return false;
		}
		return true;
	}
	
	function receive()
	{
		return fread($this->socket,256);
	}
	
	/**
	 * Close the connection to the time controll programm
	 */
	function close()
	{
		$this->send("close\n");
		fclose($this->socket);
		$this->socket = null;
	}
}