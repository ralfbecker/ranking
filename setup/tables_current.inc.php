<?php
/**
 * EGroupware digital ROCK Rankings - setup
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2006-13 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$
 */

$phpgw_baseline = array(
	'Wettkaempfe' => array(
		'fd' => array(
			'WetId' => array('type' => 'auto','nullable' => False),
			'rkey' => array('type' => 'char','precision' => '8','nullable' => False),
			'name' => array('type' => 'varchar','precision' => '100','nullable' => False),
			'dru_bez' => array('type' => 'varchar','precision' => '20'),
			'datum' => array('type' => 'date','nullable' => False,'default' => '0000-00-00'),
			'pkte' => array('type' => 'int','precision' => '4'),
			'pkt_bis' => array('type' => 'float','precision' => '16'),
			'feld_pkte' => array('type' => 'int','precision' => '4'),
			'feld_bis' => array('type' => 'float','precision' => '16'),
			'faktor' => array('type' => 'float','precision' => '16'),
			'serie' => array('type' => 'int','precision' => '4'),
			'open' => array('type' => 'int','precision' => '4'),
			'pflicht' => array('type' => 'varchar','precision' => '3','default' => 'no'),
			'ex_pkte' => array('type' => 'varchar','precision' => '3','default' => 'no'),
			'nation' => array('type' => 'char','precision' => '5'),
			'gruppen' => array('type' => 'varchar','precision' => '255'),
			'homepage' => array('type' => 'varchar','precision' => '60'),
			'quota' => array('type' => 'int','precision' => '2'),
			'host_nation' => array('type' => 'varchar','precision' => '3'),
			'host_quota' => array('type' => 'int','precision' => '2'),
			'deadline' => array('type' => 'date'),
			'prequal_ranking' => array('type' => 'int','precision' => '2'),
			'prequal_comp' => array('type' => 'int','precision' => '2'),
			'prequal_comps' => array('type' => 'varchar','precision' => '64'),
			'judges' => array('type' => 'varchar','precision' => '64'),
			'discipline' => array('type' => 'varchar','precision' => '16'),
			'prequal_extra' => array('type' => 'varchar','precision' => '255'),
			'quota_extra' => array('type' => 'varchar','precision' => '255'),
			'no_complimentary' => array('type' => 'bool'),
			'cat_id' => array('type' => 'int','precision' => '4'),
			'modified' => array('type' => 'timestamp','nullable' => False,'default' => 'current_timestamp'),
			'modifier' => array('type' => 'int','precision' => '4'),
			'fed_id' => array('type' => 'int','precision' => '4'),
			'display_athlete' => array('type' => 'varchar','precision' => '20','comment' => 'nation, pc_city, federation, pc_city_nation. fed_parent, NULL=default'),
			'selfregister' => array('type' => 'int','precision' => '1','nullable' => False,'default' => '0','comment' => '0=no, 1=fed.to confirm, 2=register'),
			'open_comp' => array('type' => 'int','precision' => '1','nullable' => False,'default' => '0','comment' => '0=No, 1=national, 2=DACH, 3=int.'),
			'quali_preselected' => array('type' => 'varchar','precision' => '64','nullable' => False,'default' => '0','comment' => 'GrpId: number of preselected athletes, not climbing qualification'),
			'prequal_type' => array('type' => 'int','precision' => '1','nullable' => False,'default' => '0','comment' => '0=comp. date, 1=1.1.'),
			'continent' => array('type' => 'int','precision' => '1','comment' => '1=Europe, 2=Asia, 4=America, 8=Africa, 16=Oceania')
		),
		'pk' => array('WetId'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array('rkey')
	),
	'Serien' => array(
		'fd' => array(
			'SerId' => array('type' => 'auto','nullable' => False),
			'rkey' => array('type' => 'char','precision' => '8','nullable' => False),
			'name' => array('type' => 'varchar','precision' => '64'),
			'max_rang' => array('type' => 'int','precision' => '2'),
			'max_serie' => array('type' => 'int','precision' => '2'),
			'faktor' => array('type' => 'float','precision' => '16'),
			'pkte' => array('type' => 'int','precision' => '4','nullable' => False),
			'split_by_places' => array('type' => 'varchar','precision' => '12','nullable' => False,'default' => 'no'),
			'nation' => array('type' => 'char','precision' => '5'),
			'gruppen' => array('type' => 'varchar','precision' => '255'),
			'presets' => array('type' => 'text'),
			'modified' => array('type' => 'timestamp','nullable' => False,'default' => 'current_timestamp'),
			'modifier' => array('type' => 'int','precision' => '4'),
			'fed_id' => array('type' => 'int','precision' => '4'),
			'min_disciplines' => array('type' => 'int','precision' => '2'),
			'drop_equally' => array('type' => 'bool'),
			'continent' => array('type' => 'int','precision' => '1','comment' => '1=Europe, 2=Asia, 4=America, 8=Africa, 16=Oceania'),
			'comment' => array('type' => 'varchar','precision' => '1024'),
			'max_disciplines' => array('type' => 'varchar','precision' => '255','comment' => 'JSON:{disciplin:max,...}')
		),
		'pk' => array('SerId'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array('rkey')
	),
	'Gruppen' => array(
		'fd' => array(
			'GrpId' => array('type' => 'auto','nullable' => False),
			'rkey' => array('type' => 'char','precision' => '8','nullable' => False),
			'name' => array('type' => 'varchar','precision' => '40','nullable' => False),
			'nation' => array('type' => 'char','precision' => '5'),
			'serien_pat' => array('type' => 'char','precision' => '8'),
			'sex' => array('type' => 'varchar','precision' => '6'),
			'from_year' => array('type' => 'int','precision' => '2'),
			'to_year' => array('type' => 'int','precision' => '2'),
			'rls' => array('type' => 'int','precision' => '2','nullable' => False),
			'vor_rls' => array('type' => 'int','precision' => '2'),
			'vor' => array('type' => 'int','precision' => '2'),
			'extra' => array('type' => 'varchar','precision' => '40'),
			'discipline' => array('type' => 'varchar','precision' => '16','default' => 'lead')
		),
		'pk' => array('GrpId'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array('rkey')
	),
	'RangListenSysteme' => array(
		'fd' => array(
			'RlsId' => array('type' => 'auto','nullable' => False),
			'rkey' => array('type' => 'char','precision' => '8','nullable' => False),
			'name' => array('type' => 'varchar','precision' => '50','nullable' => False),
			'window_type' => array('type' => 'varchar','precision' => '12','nullable' => True),
			'window_anz' => array('type' => 'int','precision' => '2','nullable' => True),
			'min_wettk' => array('type' => 'int','precision' => '2','nullable' => True),
			'best_wettk' => array('type' => 'int','precision' => '2','nullable' => True),
			'end_pflicht_tol' => array('type' => 'int','precision' => '2','nullable' => True),
			'anz_digits' => array('type' => 'int','precision' => '2','nullable' => True)
		),
		'pk' => array('RlsId'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array('rkey')
	),
	'PktSysteme' => array(
		'fd' => array(
			'PktId' => array('type' => 'auto','nullable' => False),
			'rkey' => array('type' => 'char','precision' => '8','nullable' => False),
			'name' => array('type' => 'varchar','precision' => '50','nullable' => True),
			'anz_pkt' => array('type' => 'int','precision' => '4','nullable' => False)
		),
		'pk' => array('PktId'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array('rkey')
	),
	'Results' => array(
		'fd' => array(
			'PerId' => array('type' => 'int','precision' => '4','nullable' => False),
			'GrpId' => array('type' => 'int','precision' => '4','nullable' => False),
			'WetId' => array('type' => 'int','precision' => '4','nullable' => False),
			'platz' => array('type' => 'int','precision' => '2','nullable' => False),
			'pkt' => array('type' => 'int','precision' => '2','nullable' => False),
			'datum' => array('type' => 'date','nullable' => False,'default' => '0000-00-00'),
			'cup_platz' => array('type' => 'int','precision' => '2'),
			'cup_pkt' => array('type' => 'int','precision' => '2'),
			'modified' => array('type' => 'timestamp','default' => 'current_timestamp'),
			'modifier' => array('type' => 'int','precision' => '4')
		),
		'pk' => array('PerId','GrpId','WetId'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	),
	'Feldfaktoren' => array(
		'fd' => array(
			'WetId' => array('type' => 'int','precision' => '4','nullable' => False),
			'GrpId' => array('type' => 'int','precision' => '4','nullable' => False),
			'ff' => array('type' => 'float','precision' => '16','default' => '0.0000','nullable' => False)
		),
		'pk' => array('WetId','GrpId'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	),
	'PktSystemPkte' => array(
		'fd' => array(
			'PktId' => array('type' => 'int','precision' => '4','nullable' => False),
			'platz' => array('type' => 'int','precision' => '4','nullable' => False),
			'pkt' => array('type' => 'int','precision' => '4','nullable' => False)
		),
		'pk' => array('PktId','platz'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	),
	'Gruppen2Personen' => array(
		'fd' => array(
			'GrpId' => array('type' => 'int','precision' => '4','nullable' => False),
			'PerId' => array('type' => 'int','precision' => '4','nullable' => False),
			'old_key' => array('type' => 'varchar','precision' => '21','nullable' => True)
		),
		'pk' => array('GrpId','PerId'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	),
	'Personen' => array(
		'fd' => array(
			'PerId' => array('type' => 'auto','nullable' => False),
			'rkey' => array('type' => 'varchar','precision' => '8','nullable' => False),
			'nachname' => array('type' => 'varchar','precision' => '40','nullable' => False),
			'vorname' => array('type' => 'varchar','precision' => '40','nullable' => False),
			'sex' => array('type' => 'varchar','precision' => '6'),
			'strasse' => array('type' => 'varchar','precision' => '60'),
			'plz' => array('type' => 'varchar','precision' => '8'),
			'ort' => array('type' => 'varchar','precision' => '60'),
			'tel' => array('type' => 'varchar','precision' => '20'),
			'fax' => array('type' => 'varchar','precision' => '20'),
			'geb_ort' => array('type' => 'varchar','precision' => '60'),
			'geb_date' => array('type' => 'date'),
			'practice' => array('type' => 'int','precision' => '2'),
			'groesse' => array('type' => 'int','precision' => '2'),
			'gewicht' => array('type' => 'int','precision' => '2'),
			'lizenz' => array('type' => 'varchar','precision' => '6'),
			'kader' => array('type' => 'varchar','precision' => '5'),
			'anrede' => array('type' => 'varchar','precision' => '40'),
			'bemerkung' => array('type' => 'text'),
			'hobby' => array('type' => 'varchar','precision' => '60'),
			'sport' => array('type' => 'varchar','precision' => '60'),
			'profi' => array('type' => 'varchar','precision' => '40'),
			'email' => array('type' => 'varchar','precision' => '60'),
			'homepage' => array('type' => 'varchar','precision' => '60'),
			'mobil' => array('type' => 'varchar','precision' => '20'),
			'acl' => array('type' => 'int','precision' => '4','default' => '0'),
			'freetext' => array('type' => 'longtext'),
			'modified' => array('type' => 'timestamp','default' => 'current_timestamp'),
			'modifier' => array('type' => 'int','precision' => '4'),
			'password' => array('type' => 'varchar','precision' => '128'),
			'recover_pw_hash' => array('type' => 'varchar','precision' => '32'),
			'recover_pw_time' => array('type' => 'timestamp'),
			'last_login' => array('type' => 'timestamp'),
			'login_failed' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0'),
			'facebook' => array('type' => 'varchar','precision' => '64'),
			'twitter' => array('type' => 'varchar','precision' => '64'),
			'instagram' => array('type' => 'varchar','precision' => '64'),
			'youtube' => array('type' => 'varchar','precision' => '64'),
			'video_iframe' => array('type' => 'varchar','precision' => '128')
		),
		'pk' => array('PerId'),
		'fk' => array(),
		'ix' => array('nachname'),
		'uc' => array('rkey')
	),
	'Routes' => array(
		'fd' => array(
			'WetId' => array('type' => 'int','precision' => '4'),
			'GrpId' => array('type' => 'int','precision' => '4'),
			'route_order' => array('type' => 'int','precision' => '2'),
			'route_name' => array('type' => 'varchar','precision' => '80'),
			'route_judge' => array('type' => 'varchar','precision' => '80'),
			'route_status' => array('type' => 'int','precision' => '2'),
			'route_type' => array('type' => 'int','precision' => '2'),
			'route_modified' => array('type' => 'int','precision' => '8'),
			'route_modifier' => array('type' => 'int','precision' => '4'),
			'route_iso_open' => array('type' => 'varchar','precision' => '40'),
			'route_iso_close' => array('type' => 'varchar','precision' => '40'),
			'route_start' => array('type' => 'varchar','precision' => '64'),
			'route_result' => array('type' => 'varchar','precision' => '80'),
			'route_comments' => array('type' => 'text'),
			'route_quota' => array('type' => 'int','precision' => '2'),
			'route_num_problems' => array('type' => 'int','precision' => '2'),
			'route_observation_time' => array('type' => 'varchar','precision' => '40'),
			'route_climbing_time' => array('type' => 'varchar','precision' => '40'),
			'dsp_id' => array('type' => 'int','precision' => '4'),
			'frm_id' => array('type' => 'int','precision' => '4'),
			'route_time_host' => array('type' => 'varchar','precision' => '64'),
			'route_time_port' => array('type' => 'int','precision' => '4'),
			'dsp_id2' => array('type' => 'int','precision' => '4'),
			'frm_id2' => array('type' => 'int','precision' => '4'),
			'slist_order' => array('type' => 'int','precision' => '1','comment' => 'mode of startlist creation'),
			'current_1' => array('type' => 'int','precision' => '4','comment' => 'current climber, speed left, boulder #1'),
			'current_2' => array('type' => 'int','precision' => '4','comment' => 'current speed right, boulder #2'),
			'current_3' => array('type' => 'int','precision' => '4','comment' => 'current boulder #3'),
			'current_4' => array('type' => 'int','precision' => '4','comment' => 'current boulder #4'),
			'current_5' => array('type' => 'int','precision' => '4','comment' => 'current boulder #5'),
			'current_6' => array('type' => 'int','precision' => '4','comment' => 'current boulder #6'),
			'current_7' => array('type' => 'int','precision' => '4','comment' => 'current boulder #7'),
			'current_8' => array('type' => 'int','precision' => '4','comment' => 'current boulder #8'),
			'next_1' => array('type' => 'int','precision' => '4','comment' => 'next for boulder #1'),
			'next_2' => array('type' => 'int','precision' => '4','comment' => 'next for boulder #2'),
			'next_3' => array('type' => 'int','precision' => '4','comment' => 'next for boulder #3'),
			'next_4' => array('type' => 'int','precision' => '4','comment' => 'next for boulder #4'),
			'next_5' => array('type' => 'int','precision' => '4','comment' => 'next for boulder #5'),
			'next_6' => array('type' => 'int','precision' => '4','comment' => 'next for boulder #6'),
			'next_7' => array('type' => 'int','precision' => '4','comment' => 'next for boulder #7'),
			'next_8' => array('type' => 'int','precision' => '4','comment' => 'next for boulder #8'),
			'boulder_time' => array('type' => 'int','precision' => '4','comment' => 'boulder rotation time in sec'),
			'boulder_startet' => array('type' => 'int','precision' => '8','comment' => 'last boulder rotation started timestamp'),
			'route_judges' => array('type' => 'varchar','precision' => '255','comment' => 'judges for just that route'),
			'discipline' => array('type' => 'varchar','precision' => '16','comment' => 'lead, speed, boulder or NULL to use from category or competition'),
			'selfscore_num' => array('type' => 'int','precision' => '2','comment' => 'number or boulders per rows on scorecard'),
			'selfscore_points' => array('type' => 'int','precision' => '2','comment' => 'points per boulder distributed on all tops')
		),
		'pk' => array('WetId','GrpId','route_order'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	),
	'RouteResults' => array(
		'fd' => array(
			'WetId' => array('type' => 'int','precision' => '4'),
			'GrpId' => array('type' => 'int','precision' => '4'),
			'route_order' => array('type' => 'int','precision' => '2'),
			'PerId' => array('type' => 'int','precision' => '4'),
			'result_height' => array('type' => 'int','precision' => '4'),
			'result_plus' => array('type' => 'int','precision' => '2'),
			'result_time' => array('type' => 'int','precision' => '4'),
			'result_top' => array('type' => 'int','precision' => '4'),
			'result_zone' => array('type' => 'int','precision' => '4'),
			'result_modified' => array('type' => 'int','precision' => '8'),
			'result_modifier' => array('type' => 'int','precision' => '4'),
			'start_order' => array('type' => 'int','precision' => '2'),
			'start_number' => array('type' => 'int','precision' => '2'),
			'result_rank' => array('type' => 'int','precision' => '2'),
			'result_detail' => array('type' => 'text'),
			'start_order2n' => array('type' => 'int','precision' => '2','comment' => 'start order 2. route record format')
		),
		'pk' => array('WetId','GrpId','route_order','PerId'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	),
	'RelayResults' => array(
		'fd' => array(
			'WetId' => array('type' => 'int','precision' => '4'),
			'GrpId' => array('type' => 'int','precision' => '4'),
			'route_order' => array('type' => 'int','precision' => '2'),
			'team_id' => array('type' => 'int','precision' => '2'),
			'team_nation' => array('type' => 'varchar','precision' => '3'),
			'team_name' => array('type' => 'varchar','precision' => '64'),
			'start_order' => array('type' => 'int','precision' => '2'),
			'result_time' => array('type' => 'int','precision' => '4'),
			'result_rank' => array('type' => 'int','precision' => '2'),
			'PerId_1' => array('type' => 'int','precision' => '4'),
			'start_number_1' => array('type' => 'int','precision' => '2'),
			'result_time_1' => array('type' => 'int','precision' => '4'),
			'PerId_2' => array('type' => 'int','precision' => '4'),
			'start_number_2' => array('type' => 'int','precision' => '2'),
			'result_time_2' => array('type' => 'int','precision' => '4'),
			'PerId_3' => array('type' => 'int','precision' => '4'),
			'start_number_3' => array('type' => 'int','precision' => '2'),
			'result_time_3' => array('type' => 'int','precision' => '4'),
			'result_modified' => array('type' => 'int','precision' => '8'),
			'result_modifier' => array('type' => 'int','precision' => '4')
		),
		'pk' => array('WetId','GrpId','route_order','team_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	),
	'Displays' => array(
		'fd' => array(
			'dsp_id' => array('type' => 'auto'),
			'dsp_name' => array('type' => 'varchar','precision' => '80'),
			'WetId' => array('type' => 'int','precision' => '4'),
			'dsp_current' => array('type' => 'varchar','precision' => '255'),
			'frm_id' => array('type' => 'int','precision' => '4'),
			'dsp_ip' => array('type' => 'varchar','precision' => '64'),
			'dsp_port' => array('type' => 'int','precision' => '4'),
			'dsp_format' => array('type' => 'varchar','precision' => '255'),
			'dsp_rows' => array('type' => 'int','precision' => '4','default' => '1'),
			'dsp_cols' => array('type' => 'int','precision' => '4','default' => '20'),
			'dsp_remark' => array('type' => 'text'),
			'dsp_timeout' => array('type' => 'decimal','precision' => '12','scale' => '2'),
			'dsp_line' => array('type' => 'int','precision' => '4'),
			'dsp_athletes' => array('type' => 'varchar','precision' => '255'),
			'dsp_charset' => array('type' => 'varchar','precision' => '32'),
			'dsp_clone_of' => array('type' => 'int','precision' => '4'),
			'dsp_access' => array('type' => 'varchar','precision' => '255'),
			'dsp_etag' => array('type' => 'int','precision' => '4','default' => '0')
		),
		'pk' => array('dsp_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	),
	'DisplayFormats' => array(
		'fd' => array(
			'frm_id' => array('type' => 'auto'),
			'dsp_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'frm_line' => array('type' => 'int','precision' => '4','nullable' => False),
			'WetId' => array('type' => 'int','precision' => '4','nullable' => False),
			'GrpId' => array('type' => 'int','precision' => '4'),
			'route_order' => array('type' => 'int','precision' => '4'),
			'frm_updated' => array('type' => 'timestamp','nullable' => False,'default' => 'current_timestamp'),
			'frm_content' => array('type' => 'varchar','precision' => '255'),
			'frm_showtime' => array('type' => 'int','precision' => '4'),
			'frm_go_frm_id' => array('type' => 'int','precision' => '4'),
			'frm_max' => array('type' => 'int','precision' => '4')
		),
		'pk' => array('frm_id'),
		'fk' => array(),
		'ix' => array(array('dsp_id','WetId','frm_line')),
		'uc' => array()
	),
	'Federations' => array(
		'fd' => array(
			'fed_id' => array('type' => 'auto','nullable' => False),
			'verband' => array('type' => 'varchar','precision' => '80','nullable' => False),
			'nation' => array('type' => 'varchar','precision' => '3','nullable' => False),
			'fed_parent' => array('type' => 'int','precision' => '4'),
			'fed_aliases' => array('type' => 'text'),
			'fed_url' => array('type' => 'varchar','precision' => '128'),
			'fed_nationname' => array('type' => 'varchar','precision' => '80'),
			'fed_continent' => array('type' => 'int','precision' => '1','default' => '0'),
			'fed_shortcut' => array('type' => 'varchar','precision' => '20')
		),
		'pk' => array('fed_id'),
		'fk' => array(),
		'ix' => array('nation','fed_continent'),
		'uc' => array()
	),
	'Athlete2Fed' => array(
		'fd' => array(
			'PerId' => array('type' => 'int','precision' => '4','nullable' => False),
			'a2f_end' => array('type' => 'int','precision' => '2','nullable' => False,'default' => '9999'),
			'a2f_start' => array('type' => 'int','precision' => '2','nullable' => False,'default' => '0'),
			'fed_id' => array('type' => 'int','precision' => '4','nullable' => False)
		),
		'pk' => array('PerId','a2f_end','a2f_start'),
		'fk' => array(),
		'ix' => array('fed_id'),
		'uc' => array()
	),
	'Licenses' => array(
		'fd' => array(
			'PerId' => array('type' => 'int','precision' => '4','nullable' => False),
			'nation' => array('type' => 'varchar','precision' => '3','nullable' => False),
			'lic_year' => array('type' => 'int','precision' => '2','nullable' => False),
			'lic_status' => array('type' => 'varchar','precision' => '1','default' => 'c'),
			'lic_applied' => array('type' => 'date'),
			'lic_applied_by' => array('type' => 'int','precision' => '4'),
			'lic_confirmed' => array('type' => 'date'),
			'lic_confirmed_by' => array('type' => 'int','precision' => '4'),
			'lic_suspended' => array('type' => 'date'),
			'lic_suspended_by' => array('type' => 'int','precision' => '4'),
			'GrpId' => array('type' => 'int','precision' => '4','comment' => 'optional category for which license was applied'),
			'lic_until' => array('type' => 'int','precision' => '2','comment' => 'optional end-year, default only valid in current year')
		),
		'pk' => array('PerId','nation','lic_year'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	),
	'RouteHolds' => array(
		'fd' => array(
			'hold_id' => array('type' => 'auto','nullable' => False),
			'WetId' => array('type' => 'int','precision' => '4','nullable' => False),
			'GrpId' => array('type' => 'int','precision' => '4','nullable' => False),
			'route_order' => array('type' => 'int','precision' => '2','nullable' => False),
			'hold_topo' => array('type' => 'int','precision' => '2','nullable' => False),
			'hold_xpercent' => array('type' => 'int','precision' => '4','nullable' => False),
			'hold_ypercent' => array('type' => 'int','precision' => '4','nullable' => False),
			'hold_height' => array('type' => 'int','precision' => '4'),
			'hold_type' => array('type' => 'int','precision' => '2','nullable' => False,'default' => '0')
		),
		'pk' => array('hold_id'),
		'fk' => array(),
		'ix' => array(array('WetId','GrpId','route_order','hold_topo')),
		'uc' => array()
	)
);
