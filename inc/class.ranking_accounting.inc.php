<?php
/**
 * eGroupWare digital ROCK Rankings - resultservice accounting for SAC
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2009 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$
 */

require_once(EGW_INCLUDE_ROOT.'/ranking/inc/class.boresult.inc.php');

class ranking_accounting extends boresult
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

	function __construct()
	{
		$start = microtime(true);
		$this->boresult();
		error_log("boresult constructor took ".sprintf('%4.2lf s',microtime(true)-$start));
	}

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
		$GLOBALS['egw']->session->appsession('accounting','ranking',$query);

		$query['col_filter']['WetId'] = $query['comp'];
		$query['col_filter']['route_order'] = array(0,1);

		switch (($order = $query['order']))
		{
			case 'start_order':
				$query['order'] = 'GrpId,start_order';
				break;
		}
		$comp = $this->comp->read($query['comp']);

		//echo "<p align=right>order='$query[order]', sort='$query[sort]', start=$query[start]</p>\n";
//		$total = $this->route_result->get_rows($query,$rows,$readonlys);
		// we use athlete::get_rows, to also get the license data (joined with the results table)
		$join = ' JOIN '.route_result::RESULT_TABLE.' ON '.athlete::ATHLETE_TABLE.'.PerId='.route_result::RESULT_TABLE. '.PerId AND '.
			$this->db->expression(route_result::RESULT_TABLE,$query['col_filter']);
		// col_filter is only for license-date, other filters are already used in the above join
		$query['col_filter'] = array(
			'license_nation' => $query['calendar'],
			'license_year'   => (int)$comp['datum'],
		);

		$total = $this->athlete->get_rows($query,$rows,$readonlys,$join,false,false,'start_number,start_order,GrpId');
		//echo $total; _debug_array($rows); //die('Stop');

		$rows['total'] = $rows['fed'] = 0.0;
		$acl_feds = array();
		foreach($rows as $k => &$row)
		{
			if (!is_int($k)) continue;

			// shorten DAV or SAC Sektion
			$row['verband'] = preg_replace('/^(Deutscher Alpenverein|Schweizer Alpen[ -]{1}Club) /','',$row['verband']);

			$row['age'] = $comp['datum'] - $row['geb_date'];
			self::calc_fees($row,$row['total'],$row['fed'],$query['fees']);

			if (count($rows)-2 == $total)	// dont show sum of a partial display
			{
				$rows['total'] += $row['total'];
				$rows['fed'] += $row['fed'];
			}
			if ($row['acl_fed_id'] && !in_array($row['acl_fed_id'],$acl_feds))
			{
				$acl_feds[] = $row['acl_fed_id'];
			}
		}
		$rows['license_year'] = (int)$comp['datum'];
		$acl_feds = $this->federation->query_list('verband','fed_id',array('fed_id' => $acl_feds));
		foreach($acl_feds as $fed_id => &$name)
		{
			$name = preg_replace('/^(Deutscher Alpenverein|Schweizer Alpen[ -]{1}Club|SAC-Regionalzentrum) /','',$name);
		}
		$rows['sel_options']['acl_fed_id'] = $acl_feds;
		//echo $total; _debug_array($rows); die('Stop');

		return $total;
	}

	/**
	 * Show a result / startlist
	 *
	 * @param array $content=null
	 * @param string $msg=''
	 */
	function index($content=null,$msg='',$pstambl='')
	{
		$tmpl = new etemplate('ranking.accounting.index');

		//_debug_array($content);exit;
		if (!is_array($content))
		{
			$content = array('nm' => $GLOBALS['egw']->session->appsession('accounting','ranking'));
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
					'csv_fields' => array(
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
						'license'      => array('label' => lang('License'),     'type' => 'select'),
						'total'        => array('label' => lang('Total'),       'type' => 'float', 'size' => '2'),
						'fed'          => array('label' => lang('Federation'),  'type' => 'float', 'size' => '2'),
					)
				);
			}
			if ($_GET['calendar']) $content['nm']['calendar'] = $_GET['calendar'];
			if ($_GET['comp']) $content['nm']['comp'] = $_GET['comp'];
			if ($_GET['cat']) $content['nm']['cat'] = $_GET['cat'];
			if (is_numeric($_GET['route'])) $content['nm']['route'] = $_GET['route'];
			if ($_GET['msg']) $msg = $_GET['msg'];
			if (isset($_GET['show_result'])) $content['nm']['show_result'] = (int)$_GET['show_result'];

			// currently only used by SUI
			$content['nm']['calendar'] = 'SUI';
		}
		if (is_array($content['fees']) && $content['fees']['save'])
		{
			unset($content['fees']['save']);
			self::save_fees($content['fees'],$content['nm']['comp']);
		}
		else
		{
			$content['fees'] = self::get_fees($content['nm']['comp']);
		}
		$content['nm']['fees'] = $content['fees'];

		if($content['nm']['comp']) $comp = $this->comp->read($content['nm']['comp']);
		//echo "<p>calendar='$calendar', comp={$content['nm']['comp']}=$comp[rkey]: $comp[name]</p>\n";
		if ($tmpl->sitemgr && $_GET['comp'] && $comp)	// no calendar and/or competition selection, if in sitemgr the comp is direct specified
		{
			$readonlys['nm[calendar]'] = $readonlys['nm[comp]'] = true;
		}
		if ($this->only_nation)
		{
			$calendar = $this->only_nation;
			$tmpl->disable_cells('nm[calendar]');
		}
		elseif ($comp && !$content['nm']['calendar'])
		{
			$calendar = $comp['nation'] ? $comp['nation'] : 'NULL';
		}
		elseif ($content['nm']['calendar'])
		{
			$calendar = $content['nm']['calendar'];
		}
		else
		{
			list($calendar) = each($this->ranking_nations);
		}
		$readonlys['fees'] = $content['fees']['no_save'] = !$this->is_admin && !in_array($content['nm']['calendar'],$this->edit_rights);

		$sel_options = array(
			'calendar' => $this->ranking_nations,
			'comp'     => $this->comp->names(array(
				'nation' => $calendar,
				'datum < '.$this->db->quote(date('Y-m-d',time()+14*24*3600)),	// starting 14 days from now
				'datum > '.$this->db->quote(date('Y-m-d',time()-365*24*3600)),	// until one year back
				'gruppen IS NOT NULL',
			),0,'datum DESC'),
			'GrpId'      => $this->cats->names(array('rkey' => $comp['gruppen']),0),
			'license'    => $this->license_labels,
		);
		$GLOBALS['egw_info']['flags']['app_header'] = lang('Accounting').' '.$comp['name'];

		return $tmpl->exec('ranking.ranking_accounting.index',$content,$sel_options,$readonlys,array('nm' => $content['nm']));
	}

	/**
	 * Save the fees
	 *
	 * @param array $fees values for keys (member|non|entry|fed)_(til19|20plus)
	 * @param int $comp=null competition
	 */
	static function save_fees(array $fees,$comp=null)
	{
		//_debug_array($fees);
		config::save_value('fees',$fees,'ranking');
	}

	/**
	 * Get the fees
	 *
	 * @param int $comp=null competition
	 * @return array with values for keys (member|non|entry|fed)_(til19|20plus)
	 */
	static function get_fees($comp=null)
	{
		$config = config::read('ranking');
		//_debug_array($config);

		return isset($config['fees']) && is_array($config['fees']) ? $config['fees'] : array();
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
