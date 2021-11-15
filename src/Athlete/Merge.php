<?php
/**
 * EGroupware digital ROCK Rankings - document merge
 *
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @author Nathan Gray
 * @package ranking
 * @copyright (c) 2007-19 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright 2011-19 Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Ranking\Athlete;

use EGroupware\Api;
use EGroupware\Ranking\Base;

/**
 * Document merge object for athletes
 */
class Merge extends Api\Storage\Merge
{
	/**
	 * Functions that can be called via menuaction
	 *
	 * @var array
	 */
	var $public_functions = array(
		'download_by_request'	=> true,
		'show_replacements'		=> true,
	);

	/**
	 * Extra placeholders that aren't in tracker fields2labels
	 *
	 * @var Array
	 */
	var $extra_placeholders = array(
		'age' => 'age',
		'geb_year' => 'geb_year',
		'verband' => 'verband',
		'last_comp' => 'last_comp'
	);

	/**
	 * Business object to pull records from
	 */
	protected $bo = null;

	/**
	 * Constructor
	 *
	 */
	function __construct()
	{
		parent::__construct();
		$this->bo = Base::getInstance();

		$this->date_fields += Record::$types['date-time'] +
			Record::$types['date'] + array('last_comp');

		$this->selects = array(
			'nation' => $this->bo->athlete->distinct_list('nation'),
			'sex'    => $this->bo->genders,
			'acl'    => Ui::$acl_labels,
			'custom_acl' => Ui::$acl_deny_labels,
			'license'=> $this->bo->license_labels,
			'fed_id' => $this->bo->athlete->federations(null, false, []),
			'license_nation' => $this->bo->license_nations,
			'license_cat' => $this->bo->cats->names([], 0),
		);

		// switch off handling of Api\Html formated content, if Api\Html is not used
		$this->parse_html_styles = Api\Storage\Customfields::use_html('ranking');

		// overwrite contacts call with own implementation
		$this->contacts = new class {
			function read($id)
			{
				$bo = Base::getInstance();
				if (($athlete = $bo->athlete->read($id)))
				{
					return [
						'email' => $athlete['email'],
						'n_fn'  => $athlete['vorname'].' '.$athlete['nachname'],
						'n_given' => $athlete['vorname'],
						'n_family' => $athlete['nachname'],
					];
				}
				return false;
			}
		};
	}

	/**
	 * Get ranking replacements
	 *
	 * @param int $id id of entry
	 * @param string &$content=null content to create some replacements only if they are use
	 * @return array|boolean
	 */
	protected function get_replacements($id,&$content=null)
	{
		if (!($replacements = $this->athlete_replacements($id, '', $content)))
		{
			return false;
		}
		return $replacements;
	}

	/**
	 * Get athlete replacements
	 *
	 * @param int $id id of entry
	 * @param string $prefix='' prefix like eg. 'erole'
	 * @return array|boolean
	 */
	public function athlete_replacements($id, $prefix='', &$content = '')
	{
		$record = new Record($id);
		$entry = array();

		// Convert to human friendly values
		$types = Record::$types;
		$selects = $this->selects;

		if($content && strpos($content, '$$#') !== FALSE)
		{
			$this->cf_link_to_expand($record->get_record_array(), $content, $entry);
		}

		// Use ACL as sum
		$_acl = 0;
		foreach($record->acl as $acl)
		{
			$_acl |= $acl;
		}
		$record->acl = $selects['acl'][$_acl] ? $_acl : 'custom';
		importexport_export_csv::convert($record, $types, 'ranking', $selects);

		$array = $record->get_record_array();

		// No customfields
		/*
		// Set any missing custom fields, or the marker will stay
		foreach((array)$this->bo->customfields as $name => $field)
		{
			if(!$array['#'.$name])
			{
				$array['#'.$name] = '';
			}
			// Format date cfs per user Api\Preferences
			if($array['#'.$name] && ($field['type'] == 'date' || $field['type'] == 'date-time'))
			{
				$this->date_fields[] = '#'.$name;
				$array['#'.$name] = Api\DateTime::to($array['#'.$name], $field['type'] == 'date' ? true : '');
			}
		}
		 *
		 */

		// Add markers
		$array['n_fn'] = $array['vorname'].' '.$array['nachname'];
		foreach($array as $key => &$value)
		{
			if(!$value)
			{
				$value = '';
			}
			$entry['$$'.($prefix ? $prefix.'/':'').$key.'$$'] = $value;
		}

		// Links
		$entry += $this->get_all_links('ranking', $id, $prefix, $content);

		// Add contact fields
		/*
		if($array['info_link'] && $array['info_link']['app'] && $array['info_link']['id'])
		{
			$entry+=$this->get_app_replacements($array['info_link']['app'], $array['info_link']['id'], $content, 'info_contact');
		}
		 *
		 */


		return $entry;
	}

	/**
	 * Generate table with replacements for the Api\Preferences
	 *
	 */
	public function show_replacements()
	{
		$GLOBALS['egw_info']['flags']['app_header'] = lang('ranking').' - '.lang('Replacements for inserting entries into documents');
		$GLOBALS['egw_info']['flags']['nonavbar'] = false;

		echo $GLOBALS['egw']->framework->header();

		echo "<table width='90%' align='center'>\n";
		echo '<tr><td colspan="4"><h3>'.lang('Athlete fields:')."</h3></td></tr>";

		$n = 0;
		$tracking = new Tracking($this->bo);
		$fields = $tracking->field2label + $this->extra_placeholders;

		foreach($fields as $name => $label)
		{
			if (in_array($name,array('password','custom'))) continue;	// dont show them

			if (!($n&1)) echo '<tr>';
			echo '<td>{{'.$name.'}}</td><td>'.lang($label).'</td>';
			if ($n&1) echo "</tr>\n";
			$n++;
		}

		if(isset($this->bo->customfields))
		{
			echo '<tr><td colspan="4"><h3>'.lang('Custom fields').":</h3></td></tr>";
			$contact_custom = false;
			foreach($this->bo->customfields as $name => $field)
			{
				echo '<tr><td>{{#'.$name.'}}</td><td colspan="3">'.$field['label'].($field['type'] == 'select-account' ? '*':'')."</td></tr>\n";
				if($field['type'] == 'select-account') $contact_custom = true;
			}
			if($contact_custom)
			{
				echo '<tr><td /><td colspan="3">* '.lang('Addressbook placeholders available'). '</td></tr>';
			}
		}

		echo '<tr><td colspan="4"><h3>'.lang('General fields:')."</h3></td></tr>";
		foreach($this->get_common_replacements() as $name => $label)
		{
			echo '<tr><td>{{'.$name.'}}</td><td colspan="3">'.$label."</td></tr>\n";
		}

		echo "</table>\n";

		echo $GLOBALS['egw']->framework->footer();
	}
}
