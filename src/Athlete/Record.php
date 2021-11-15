<?php
/**
 * EGroupware - Ranking - importexport
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @subpackage importexport
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray
 */

namespace EGroupware\Ranking\Athlete;

use EGroupware\Ranking\Base;

/**
 * Compatibility layer for iface_egw_record needed for importexport, but also
 * used by merge to translate and format values
 */
class Record implements \importexport_iface_egw_record
{
	private $identifier = '';
	private $record = array();
	/**
	 * @var \EGroupware\Ranking\Athlete
	 */
	private static $bo;

	// Used in conversions
	static $types = array(
		'select' => array('nation', 'license_nation', 'sex', 'license', 'acl', 'custom_acl', 'fed_id', 'license_cat'),
		'select-account' => array('modifier'),
		'date-time' => array(
			'modified',
			'recover_pw_time',
			'last_login',
			'consent_time'
		),
		'date' => array( 'geb_date' ),
		'links' => array(),
	);

	/**
	 * constructor
	 * reads record from backend if identifier is given.
	 *
	 * @param string $_identifier
	 */
	public function __construct( $_identifier='' )
	{
		$this->identifier = $_identifier;

		if(self::$bo == null) self::$bo = Base::getInstance()->athlete;

		if($_identifier)
		{
			$rec = self::$bo->read($this->identifier);
			if (is_array($rec)) $this->set_record($rec);
		}
	}

	/**
	 * magic method to set attributes of record
	 *
	 * @param string $_attribute_name
	 */
	public function __get($_attribute_name)
	{
		return $this->record[$_attribute_name];
	}

	/**
	 * magig method to set attributes of record
	 *
	 * @param string $_attribute_name
	 * @param data $data
	 */
	public function __set($_attribute_name, $data)
	{
		$this->record[$_attribute_name] = $data;
	}

	/**
	 * converts this object to array.
	 * @abstract We need such a function cause PHP5
	 * dosn't allow objects do define it's own casts :-(
	 * once PHP can deal with object casts we will change to them!
	 *
	 * @return array complete record as associative array
	 */
	public function get_record_array()
	{
		return $this->record;
	}

	/**
	 * gets title of record
	 *
	 *@return string tiltle
	 */
	public function get_title()
	{
		return self::$bo->link_title($this->record);
	}

	/**
	 * sets complete record from associative array
	 *
	 * @return void
	 */
	public function set_record(array $_record)
	{
		$this->record = $_record;
	}

	/**
	 * gets identifier of this record
	 *
	 * @return string identifier of current record
	 */
	public function get_identifier()
	{
		return $this->identifier;
	}

	/**
	 * Gets the URL icon representitive of the record
	 * This could be as general as the application icon, or as specific as a contact photo
	 *
	 * @return string Full URL of an icon, or appname/icon_name
	 */
	public function get_icon()
	{
		return 'ranking/navbar';
	}

	/**
	 * saves record into backend
	 *
	 * @return string identifier
	 */
	public function save ( $_dst_identifier )
	{
		$data = $this->record;
		if ($_dst_identifier)
		{
			$data['PerId'] = $_dst_identifier;
		}
		static::$bo->save($data);
	}

	/**
	 * copys current record to record identified by $_dst_identifier
	 *
	 * @param string $_dst_identifier
	 * @return string dst_identifier
	 */
	public function copy ( $_dst_identifier )
	{
		unset($_dst_identifier);	// not used, but required by function signature
	}

	/**
	 * moves current record to record identified by $_dst_identifier
	 * $this will become moved record
	 *
	 * @param string $_dst_identifier
	 * @return string dst_identifier
	 */
	public function move ( $_dst_identifier )
	{
		unset($_dst_identifier);	// not used, but required by function signature
	}

	/**
	 * delets current record from backend
	 *
	 */
	public function delete ()
	{

	}

	/**
	 * destructor
	 *
	 */
	public function __destruct()
	{

	}
}
