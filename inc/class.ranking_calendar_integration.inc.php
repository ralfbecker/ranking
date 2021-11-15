<?php
/**
 * Ranking Calendar Integration
 *
 * @link http://www.digitalrock.de
 * @package ranking
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2010-16 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Ranking\Competition;

/**
 * Ranking Calendar Integration
 *
 * Display competitions in calendar
 */
class ranking_calendar_integration
{
	const APP_NAME = 'ranking';
	const TABLE_NAME = 'Wettkaempfe';

	/**
	 * Return query for calendar's search union query
	 *
	 * @param array $data
	 * @param string $data['cols'] cols query is suppost to return to match calendar query
	 * @param int $data['start'] start-date ts in servertime
	 * @param int $data['end'] end-date ts in servertime
	 * @param int|array $data['users'] users for which to return data
	 * @param int $data['cat_id']
	 * @param string $data[	'filter'] filter-name: all (not rejected), accepted, unknown, tentative, rejected
	 * @param string $data[	'query']' pattern so search for, if unset or empty all matching entries are returned (no search)
	 * @return array values for keys 'selectes', ...
	 */
	static public function calendar_search_union(array $data)
	{
		//_debug_array($data);
		$config = Api\Config::read(self::APP_NAME);
		$db_name = $config['ranking_db_name'];

		$app_cols = array(
			'cal_id' => $GLOBALS['egw']->db->concat("'".self::APP_NAME."'",'WetId'),
			'cal_title' => 'CASE WHEN dru_bez THEN dru_bez ELSE name END',
			'cal_description' => 'name',
			'cal_start' => 'UNIX_TIMESTAMP(datum)',
			'cal_end' => "UNIX_TIMESTAMP(ADDDATE(datum,CASE WHEN INSTR(gruppen,'@') > 0 AND SUBSTR(gruppen,INSTR(gruppen,'@')+1) > 0 THEN SUBSTR(gruppen,INSTR(gruppen,'@')+1) ELSE 2 END))-1",
			//'cal_owner' => $GLOBALS['egw_info']['user']['account_id'],
			'cal_non_blocking' => 1,
			'cal_public' => 1,
			'cal_uid' => $GLOBALS['egw']->db->concat("'".self::APP_NAME.":comp-'",'WetId',"'-".$GLOBALS['egw_info']['server']['install_id']."'"),
			'cal_etag' => 'modified',
			//'cal_location' => 'info_location',
			'cal_category' => 'cat_id',
			'tz_id' => calendar_timezones::tz2id($GLOBALS['egw_info']['server']['server_timezone']),
			'cal_modified' => 'modified',
			'cal_modifier' => 'modifier',
			'cal_recurrence' => 0,
			//'cal_priority' => "CASE info_priority WHEN 3 THEN 3 ELSE info_priority+1 END",
			//'participants' => "CASE info_responsible WHEN '0' THEN info_owner ELSE info_responsible END",
			//'icons' => $GLOBALS['egw']->db->concat("'".self::APP_NAME.":'",'info_type'),
		);
		$where = array();

		// we use startdate also for end, as enddate in infolog is a due date
		if ($data['start']) $where[] = (int)$data['start'] . ' <= UNIX_TIMESTAMP(datum)';
		if ($data['end']) $where[] = 'UNIX_TIMESTAMP(datum) <= '.(int)$data['end'];
		if ($data['cat_id'] > 0) $where['cat_id'] = $GLOBALS['egw']->categories->return_all_children($data['cat_id']);

		// search infolog
		if ($data['query'])
		{
			if(!is_array($data['query']))
			{
				$pattern = ' '.$GLOBALS['egw']->db->capabilities[Api\Db::CAPABILITY_CASE_INSENSITIV_LIKE].' '.$GLOBALS['egw']->db->quote('%'.$data['query'].'%');
				$columns = array('rkey','name','dru_bez','gruppen');

				$where[] = '('.(is_numeric($data['query']) ? 'WetId='.(int)$data['query'].' OR ' : '').
					implode($pattern.' OR ',$columns).$pattern.') ';
			}
			else
			{
				foreach($data['query'] as $name => $value)
				{
					if (isset($app_cols[$name]))
					{
						$where[] = $app_cols[$name].' = '.$GLOBALS['egw']->db->quote($value);
					}
				}
			}
		}
		return array(
			'selects' => array(
				array(
					'table' => ($db_name ? $db_name.'.' : '').self::TABLE_NAME,
            		//'join' => '',
            		'cols' => self::union_cols($app_cols,$data['cols']),
					'where' => $where,
					'app' => self::APP_NAME,
					//'append' => '',
				)
			),
			'edit_link' => array(
				'edit' => array('menuaction' => 'ranking.'.Competition\Ui::class.'.edit'),
				'edit_id' => 'WetId',
				'edit_popup' => '900x400',
			),
			'is_private' => false,	// all public
			'icons' => false,	// no default application icon
		);
	}

	/**
	 * Return union cols constructed from application cols and required cols
	 *
	 * Every col not supplied in $app_cols get returned as NULL.
	 *
	 * @param array $app_cols required name => own name pairs
	 * @param string|array $required array or comma separated column names or table.*
	 * @param string $required_app ='calendar'
	 * @return string cols for union query to match ones supplied in $required
	 */
	static private function union_cols(array $app_cols,$required,$required_app='calendar')
	{
		// remove evtl. used DISTINCT, we currently dont need it
		if (($distinct = substr($required,0,9) == 'DISTINCT '))
		{
			$required = substr($required,9);
		}
		$return_cols = array();
		foreach(is_array($required) ? $required : explode(',',$required) as $cols)
		{
			if (substr($cols,-2) == '.*')
			{
				$cols = self::get_columns($required_app,substr($cols,0,-2));
			}
			elseif (strpos($cols,' AS ') !== false)
			{
				list(,$cols) = explode(' AS ',$cols);
			}
			foreach((array)$cols as $col)
			{
				if (substr($col,0,7) == 'egw_cal')	// remove table name
				{
					$col = preg_replace('/^egw_cal[a-z_]*\./','',$col);
				}
				if (isset($app_cols[$col]))
				{
					$return_cols[] = $app_cols[$col];
				}
				else
				{
					$return_cols[] = 'NULL';
				}
			}
		}
		return implode(',',$return_cols);
	}

	/**
	 * Get columns of given table, taking into account historically different column order of egw_cal table
	 *
	 * @param string $app
	 * @param string $table
	 * @return array of column names
	 */
	static private function get_columns($app,$table)
	{
		if ($table != 'egw_cal')
		{
			$table_def = $GLOBALS['egw']->db->get_table_definitions($app,$table);
			$cols = array_keys($table_def['fd']);
		}
		else
		{
			// special handling for egw_cal, as old databases have a different column order!!!
			$cols = Api\Cache::getInstance(__CLASS__,$table);

			if (is_null($cols))
			{
				$meta = $GLOBALS['egw']->db->metadata($table,true);
				$cols = array_keys($meta['meta']);
				Api\Cache::setInstance(__CLASS__,$table,$cols);
			}
		}
		return $cols;
	}
}
