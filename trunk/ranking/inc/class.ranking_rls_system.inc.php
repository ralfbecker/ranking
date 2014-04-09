<?php
/**
 * eGroupWare digital ROCK Rankings - rls (rang-listen-systeme) storage object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2006-14 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$
 */

/**
 * rls_system object, a rls defines how the ranking is calculated
 */
class ranking_rls_system extends so_sql
{
	var $charset,$source_charset;

	/**
	 * constructor of the rls_system class
	 */
	function __construct($source_charset='',$db=null)
	{
		parent::__construct('ranking','RangListenSysteme',$db);	// call constructor of derived class

		if ($source_charset) $this->source_charset = $source_charset;

		$this->charset = translation::charset();
	}

	/**
	 * changes the data from the db-format to our work-format
	 *
	 * @param array $data=null if given works on that array and returns result, else works on internal data-array
	 */
	function db2data($data=null)
	{
		if (!is_array($data))
		{
			$data =& $this->data;
		}
		if (count($data) && $this->source_charset)
		{
			$data = $GLOBALS['egw']->translation->convert($data,$this->source_charset);
		}
		return $data;
	}

	/**
	 * changes the data from our work-format to the db-format
	 *
	 * @param array $data=null if given works on that array and returns result, else works on internal data-array
	 */
	function data2db($data=null)
	{
		if (!is_array($data))
		{
			$data =& $this->data;
		}
		if (count($data) && $this->source_charset)
		{
			$data = $GLOBALS['egw']->translation->convert($data,$this->charset,$this->source_charset);
		}
		return $data;
	}

	/**
	 * returns array with all RlsSystems as RlsId => name pairs
	 *
	 * @return array
	 */
	function names()
	{
		$all = $this->search(array(),False,'rkey');

		if (!$all)
			return array();

		$arr = array();
		while (list($key,$data) = each($all))
		{
			$arr[$data['RlsId']] = $data['rkey'].': '.$data['name'];
		}
		return $arr;
	}
}