<?php
/**
 * EGroupware digital ROCK Rankings - resultservice accounting for competitions
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2009-16 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Ranking\Athlete;
use EGroupware\Ranking\Registration;
use EGroupware\Ranking\Category;

class ranking_accounting extends ranking_result_bo
{
	/**
	 * fed_id of SAC Nichtmitglieder
	 */
	const SAC_NON_MEMBER = 495;

	/**
	 * @var array $public_functions functions callable via menuaction
	 */
	var $public_functions = array(
		'index' => true,
	);

	/**
	 * query the start or result list
	 *
	 * @param array &$query_in
	 * @param array &$rows returned rows/cups
	 * @param array &$readonlys eg. to disable buttons based on acl
	 */
	function get_rows(&$query_in,&$rows,&$readonlys)
	{
		//echo "<p>uiresult::get_rows(".print_r($query_in,true).",,)</p>\n";
		unset($query_in['return']);	// no need to save
		$query = $query_in;
		unset($query['rows']);		// no need to save, can not unset($query_in['rows']), as this is $rows !!!

		if (empty($query['csv_export']))
		{
			Api\Cache::setSession('ranking', 'accounting', $query);
		}

		$query['col_filter']['WetId'] = $query['comp'];
		$query['col_filter']['route_order'] = 0;//array(0,1);

		switch (($order = $query['order']))
		{
			case 'start_order':
				$query['order'] = Category::sui_cat_sort(ranking_route_result::RESULT_TABLE.'.GrpId').',start_order';
				break;
		}
		if (!is_string($query['sort'])) $query['sort'] = 'ASC';
		$comp = $this->comp->read($query['comp']);

		// unset GrpId filter, if none (= 0) selected
		if (!$query['col_filter']['GrpId'])
		{
			unset($query['col_filter']['GrpId']);
		}
		// we use ranking_athlete::get_rows, to also get the license data (joined with the results table)
		$join = ' JOIN '.ranking_route_result::RESULT_TABLE.' ON '.Athlete::ATHLETE_TABLE.'.PerId='.ranking_route_result::RESULT_TABLE. '.PerId AND '.
			$this->db->expression(ranking_route_result::RESULT_TABLE,$query['col_filter']);

		// col_filter is only for license-date, other filters are already used in the above join
		$query['col_filter'] = array(
			'license_nation' => $query['calendar'],
			'license_year'   => (int)$comp['datum'],
		);

		if ($query['fees']['use_registration'])
		{
			$query['col_filter']['WetId'] = $query['comp'];
			$query['col_filter']['state'] = (int)$comp['selfregister'] == 1 ? Registration::CONFIRMED : Registration::REGISTERED;
			if ($query_in['col_filter']['GrpId']) $query['col_filter']['GrpId'] = $query_in['col_filter']['GrpId'];

			$query['order'] = strtr($query['order'], array(
				ranking_route_result::RESULT_TABLE.'.GrpId' => Registration::TABLE.'.GrpId',
				'start_order' => 'nachname,vorname',
			));
			$total = $this->registration->get_rows($query, $rows, $readonlys, true, false, false,
				Registration::TABLE.'.GrpId AS GrpId,'.Registration::TABLE.'.reg_id AS start_number');
		}
		else
		{
			$total = $this->athlete->get_rows($query,$rows,$readonlys,$join,false,false,'start_number,start_order,'.ranking_route_result::RESULT_TABLE.'.GrpId AS GrpId');
		}
		//echo $total; _debug_array($rows); //die('Stop');

		$rows['total'] = $rows['fed'] = 0.0;
		$fed_ids = array();
		foreach($rows as $k => &$row)
		{
			if (!is_int($k)) continue;

			// shorten DAV or SAC Sektion
			$row['verband'] = preg_replace('/^(Deutscher Alpenverein|Schweizer Alpen[ -]{1}Club) /','',$row['verband']);

			if ($row['geb_date']) $row['age'] = $comp['datum'] - $row['geb_date'];
			self::calc_fees($row,$row['total'],$row['fed'],$query['fees']);

			if (count($rows)-2 == $total)	// dont show sum of a partial display
			{
				$rows['total'] += $row['total'];
				$rows['fed'] += $row['fed'];
			}
			// for GER/DAV use fed_parent instead acl_fed_id
			if ($row['fed_parent'] && !in_array($row['fed_parent'], $fed_ids))
			{
				$fed_ids[] = $row['fed_parent'];
			}
			if ($row['acl_fed_id'] && !in_array($row['acl_fed_id'], $fed_ids))
			{
				$fed_ids[] = $row['acl_fed_id'];
			}
			$row['total'] = etemplate::number_format($row['total'],2);
			$row['fed'] = etemplate::number_format($row['fed'],2);
		}
		// for csv export add an extra line with the summs
		if ($query['csv_export'] && $rows['total'])
		{
			$rows[] = array(
				'start_order' => lang('Sum'),
				'total' => etemplate::number_format($rows['total'],2),
				'fed'   => etemplate::number_format($rows['fed'],2),
			);
		}
		$rows['license_year'] = (int)$comp['datum'];
		$feds = $this->federation->query_list('verband','fed_id',array('fed_id' => $fed_ids));
		foreach($feds as &$name)
		{
			$name = preg_replace(array(
				'/^(Deutscher Alpenverein|Schweizer Alpen[ -]{1}Club|SAC-Regionalzentrum|Landes(fach)?verband( Bergsport und Klettern)?) /',
				'/ (des DAV e.V.|für Sport- und Wettkampfklettern e.V.|Sektionenverband)$/',
			), '', $name);
		}
		$rows['sel_options']['acl_fed_id'] = $rows['sel_options']['fed_parent'] = $feds;

		switch($query['calendar'])
		{
			case 'SUI':
				$rows['no_fed_parent'] = true;
				break;

			case 'GER':
				$rows['no_acl_fed_id'] = true;
				break;

			default:
				$rows['no_fed_parent'] = $rows['no_acl_fed_id'] = true;
				break;
		}
		if ($query['fees']['use_registration'])
		{
			$rows['no_start_order'] = true;
		}
		//echo $total; _debug_array($rows); die('Stop');

		return $total;
	}

	/**
	 * Show a result / startlist
	 *
	 * @param array $content =null
	 */
	function index($content=null)
	{
		$tmpl = new Api\Etemplate('ranking.accounting.index');

		//_debug_array($content);exit;
		if (!is_array($content))
		{
			$content = array('nm' => Api\Cache::getSession('ranking', 'accounting'));
			if (!is_array($content['nm']) || !$content['nm']['get_rows'])
			{
				if (!is_array($content['nm'])) $content['nm'] = array();
				$content['nm'] += array(
					'get_rows'   => 'ranking.ranking_accounting.get_rows',
					'no_cat'     => true,
					'no_filter'  => true,
					'no_filter2' => true,
					'num_rows'   => 999,
					'order'      => 'start_order',
					'sort'       => 'ASC',
					'show_result'=> 1,
					'csv_fields' => false,
				);
			}
		}
		if ($content['save'])
		{
			self::save_fees($content['fees'], $content['nm']['calendar']);
		}
		// do CSV export using old nextmatch widget
		elseif ($content['nm']['rows']['download'])
		{
			$content['nm']['csv_fields'] = [
				'start_order'  => array('label' => lang('Startorder'),  'type' => 'int'),
				'start_number' => array('label' => lang('Startnumber'), 'type' => 'int'),
				'GrpId'        => array('label' => lang('Category'),    'type' => 'select'),
				'nachname'     => array('label' => lang('Lastname'),    'type' => 'text'),
				'vorname'      => array('label' => lang('Firstname'),   'type' => 'text'),
				'strasse'      => array('label' => lang('Street'),      'type' => 'text'),
				'plz'          => array('label' => lang('Postalcode'),  'type' => 'text'),
				'ort'          => array('label' => lang('City'),        'type' => 'text'),
				'geb_date'     => array('label' => lang('Birthdate'),   'type' => 'date'),
				'verband'      => array('label' => lang('Sektion'),     'type' => 'text'),
				'acl_fed_id'   => array('label' => lang('Regionalzentrum'),'type' => 'select'),
				'fed_parent'   => array('label' => lang('Landesverband'),'type' => 'select'),
				'license'      => array('label' => lang('License'),     'type' => 'select'),
				'email'        => array('label' => lang('EMail'),       'type' => 'text'),
				'total'        => array('label' => lang('Total'),       'type' => 'float', 'size' => '2'),
				'fed'          => array('label' => lang('Federation'),  'type' => 'float', 'size' => '2'),
			];
			Api\Etemplate\Export::csv($content['nm']);
		}
		elseif ($content['nm']['calendar'])
		{
			$content['fees'] = self::get_fees($content['nm']['calendar']);
		}
		$content['nm']['fees'] = $content['fees'];

		if ($this->only_nation)
		{
			$content['nm']['calendar'] = $this->only_nation;
			$tmpl->disable_cells('nm[calendar]');
		}
		if($content['nm']['comp'] && ($comp = $this->comp->read($content['nm']['comp'])) &&
			$comp['nation'] != ($content['nm']['calendar']=='NULL'?null:$content['nm']['calendar']))
		{
			unset($content['nm']['comp']);
			unset($comp);
		}
		//echo "<p>calendar='$calendar', comp={$content['nm']['comp']}=$comp[rkey]: $comp[name]</p>\n";
		if ($tmpl->sitemgr && $_GET['comp'] && $comp)	// no calendar and/or competition selection, if in sitemgr the comp is direct specified
		{
			$readonlys['nm[calendar]'] = $readonlys['nm[comp]'] = true;
		}
		$readonlys['fees'] = $content['fees']['no_save'] = !$this->is_admin && !in_array($content['nm']['calendar'],$this->edit_rights);

		$sel_options = array(
			'calendar' => $this->ranking_nations,
			'comp'     => $this->comp->names(array(
				'nation' => $content['nm']['calendar'],
				'datum < '.$this->db->quote(date('Y-m-d',time()+23*24*3600)),	// starting 23 days from now
				'datum > '.$this->db->quote(date('Y-m-d',time()-365*24*3600)),	// until one year back
				'gruppen IS NOT NULL',
			),0,'datum DESC'),
			'GrpId'      => $this->cats->names(array('rkey' => $comp['gruppen']),0,'SUI'),
			'license'    => $this->license_labels,
		);
		$GLOBALS['egw_info']['flags']['app_header'] = lang('Accounting').' '.$comp['name'];

		return $tmpl->exec('ranking.ranking_accounting.index',$content,$sel_options,$readonlys,array('nm' => $content['nm']));
	}

	/**
	 * Save the fees
	 *
	 * @param array $fees values for keys (member|non|entry|fed)_(til19|20plus)
	 * @param string $calendar calendar nation
	 */
	static function save_fees(array $fees,$calendar)
	{
		//_debug_array($fees);
		config::save_value('fees-'.$calendar,$fees,'ranking');
	}

	/**
	 * Get the fees
	 *
	 * @param string $calendar calendar nation
	 * @return array with values for keys (member|non|entry|fed)_(til19|20plus)
	 */
	static function get_fees($calendar)
	{
		$config = config::read('ranking');
		//_debug_array($config);

		$name = 'fees-'.$calendar;
		// support for old SUI name
		if (isset($config['fees']) && !isset($config[$name]))
		{
			$config[$name] = $config['fees'];
		}

		return isset($config[$name]) && is_array($config[$name]) ? $config[$name] : array();
	}

	/**
	 * Calculate the fees for an athlete
	 *
	 * @param array $athlete
	 * @param double &$total total fees
	 * @param double &$fed fees to the federation
	 * @param array $fees from self::get_fees
	 */
	static function calc_fees(array $athlete,&$total,&$fed,array $fees)
	{
		$postfix = $athlete['age'] > 19 ? '_20plus' : '_til19';

		if ($athlete['license'] == 'n')
		{
			if ($athlete['fed_id'] && $athlete['fed_id'] != self::SAC_NON_MEMBER)
			{
				$total = $fed = (double) $fees['member'.$postfix];
			}
			else
			{
				$total = $fed = (double) $fees['non'.$postfix];
			}
		}
		else
		{
			$total = $fed = (double) 0;
		}
		$total += (double) $fees['entry'.$postfix];
		$fed += (double) $fees['fed'.$postfix];
	}
}
