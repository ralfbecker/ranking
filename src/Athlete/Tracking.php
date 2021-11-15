<?php
/**
 * EGroupware digital ROCK Rankings - history and notifications
 *
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @author Nathan Gray
 * @package ranking
 * @copyright (c) 2007-16 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Ranking\Athlete;

use EGroupware\Api;

/**
 * Change tracking for athletes
 */
class Tracking extends Api\Storage\Tracking
{
	/**
	 * Application we are tracking (required!)
	 *
	 * @var string
	 */
	var $app = 'ranking';
	/**
	 * Name of the id-field, used as id in the history log (required!)
	 *
	 * @var string
	 */
	var $id_field = 'PerId';
	/**
	 * Name of the field with the creator id, if the creator of an entry should be notified
	 *
	 * @var string
	 */
	var $creator_field = '';
	/**
	 * Name of the field with the id(s) of assigned users, if they should be notified
	 *
	 * @var string
	 */
	var $assigned_field = '';
	/**
	 * Translate field-names to 2-char history status
	 *
	 * @var array
	 */
	var $field2history = array(
		'rkey' => 'rkey',
		'nachname' => 'nachname',
		'vorname' => 'vorname',
		'sex' => 'sex',
		'strasse' => 'strasse',
		'plz' => 'plz',
		'ort' => 'ort',
		'tel' => 'tel',
		'fax' => 'fax',
		'geb_ort' => 'geb_ort',
		'geb_date' => 'geb_date',
		'practice' => 'practice',
		'groesse' => 'groesse',
		'gewicht' => 'gewicht',
		'lizenz' => 'lizenz',
		'kader' => 'kader',
		'anrede' => 'anrede',
		'bemerkung' => 'bemerkung',
		'hobby' => 'hobby',
		'sport' => 'sport',
		'profi' => 'profi',
		'email' => 'email',
		'homepage' => 'homepage',
		'mobil' => 'mobil',
		'acl' => 'acl',
		'freetext' => 'freetext',
		//'modified' => 'modified',
		//'modifier' => 'modifier',
		//'password' => 'password',
		//'recover_pw_hash' => 'recover_pw_hash',
		'recover_pw_time' => 'recover_pw_time',
		'last_login' => 'last_login',
		'login_failed' => 'login_failed',
		'facebook' => 'facebook',
		'twitter' => 'twitter',
		'instagram' => 'instagram',
		'youtube' => 'youtube',
		'video_iframe' => 'video_iframe',
		'consent_time' => 'consent_time',
		'consent_ip' => 'consent_ip'
	);
	/**
	 * Translate field-names to labels
	 *
	 * @note The order of these fields is used to determine the order for CSV export
	 * @var array
	 */
	var $field2label = array(
		'PerId' => 'Athlete ID',
		'rkey' => 'Key',
		'nachname' => 'Last name',
		'vorname' => 'First name',
		'sex' => 'Gender',
		'strasse' => 'Street',
		'plz' => 'Postalcode',
		'ort' => 'City',
		'tel' => 'Phone',
		'fax' => 'Fax',
		'geb_ort' => 'Place of birth',
		'geb_date' => 'Birthdate',
		'practice' => 'climbing since (years)',
		'groesse' => 'Height (cm)',
		'gewicht' => 'Weight (kg)',
		'lizenz' => 'License number',
		'kader' => 'Squad',
		'anrede' => 'Title',
		'bemerkung' => 'Notice',
		'hobby' => 'hobby',
		'sport' => 'sport',
		'profi' => 'Professional',
		'email' => 'email',
		'homepage' => 'homepage',
		'mobil' => 'mobil',
		'acl' => 'acl',
		'freetext' => 'freetext',
		'modified' => 'modified',
		'modifier' => 'modifier',
		'password' => 'password',
		'recover_pw_hash' => 'recover_pw_hash',
		'recover_pw_time' => 'recover_pw_time',
		'last_login' => 'last_login',
		'login_failed' => 'login_failed',
		'facebook' => 'facebook',
		'twitter' => 'twitter',
		'instagram' => 'instagram',
		'youtube' => 'youtube',
		'video_iframe' => 'video_iframe',
		'consent_time' => 'consent_time',
		'consent_ip' => 'consent_ip',
		// custom fields
		//'custom'             => 'custom fields'
	);

	/**
	 * Instance of the class calling us
	 *
	 * @var Base
	 */
	private $bo;

	/**
	 * Constructor
	 *
	 * @param Base $bo
	 */
	function __construct(Base $bo)
	{
		parent::__construct('ranking');	// add custom fields from infolog

		$this->bo = $bo;
	}

	/**
	 * Get the details of an entry
	 *
	 * @param array|object $data
	 * @param int|string $receiver nummeric account_id or email address
	 * @return array of details as array with values for keys 'label','value','type'
	 */
	function get_details($data,$receiver=null)
	{
		//error_log(__METHOD__.__LINE__.' Data:'.array2string($data));

		foreach($data as $name => $value)
		{
			//error_log(__METHOD__.__LINE__.' Key:'.$name.' val:'.array2string($value));
			$details[$name] = array(
				'label' => lang($this->field2label[$name]),
				'value' => $value,
			);
		}
		$details['freetext'] = array(
			'value' => $data['freetext'],
			'type'  => 'multiline',
		);
		//error_log(__METHOD__."(".array2string($data).", $receiver) returning ".array2string($details));
		return $details;
	}

	/**
	 * Get a notification-config value
	 *
	 * @param string $name
	 *  - 'copy' array of email addresses notifications should be copied too, can depend on $data
	 *  - 'lang' string lang code for copy mail
	 *  - 'sender' string send email address
	 * @param array $data current entry
	 * @param array $old = null old/last state of the entry or null for a new entry
	 * @return mixed
	 */
	function get_config($name,$data,$old=null)
	{
		unset($old);	// not used, but required function signature
		switch($name)
		{
			case 'copy':
				$config = array();

				break;
			case self::CUSTOM_NOTIFICATION:
				$_config = Api\Config::read('ranking');
				if(!$_config[self::CUSTOM_NOTIFICATION])
				{
					return '';
				}
				// Per-type notification
				$type_config = array();//$_config[self::CUSTOM_NOTIFICATION][$data['info_type']];
				$global = $_config[self::CUSTOM_NOTIFICATION]['~global~'];

				// Disabled
				//if(!$type_config['use_custom'] && !$global['use_custom']) return '';

				// Type or globabl
				$config = trim(strip_tags($type_config['message'])) != '' && $type_config['use_custom'] ? $type_config['message'] : $global['message'];
				break;
		}
		return $config;
	}
}
