<?php
/**
 * eGroupWare digital ROCK Rankings - setup
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2006-8 by Ralf Becker <RalfBecker@digitalrock.de>
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
			'gruppen' => array('type' => 'varchar','precision' => '80'),
			'homepage' => array('type' => 'varchar','precision' => '60'),
			'quota' => array('type' => 'int','precision' => '2'),
			'host_nation' => array('type' => 'varchar','precision' => '3'),
			'host_quota' => array('type' => 'int','precision' => '2'),
			'deadline' => array('type' => 'date'),
			'prequal_ranking' => array('type' => 'int','precision' => '2'),
			'prequal_comp' => array('type' => 'int','precision' => '2'),
			'prequal_comps' => array('type' => 'varchar','precision' => '64'),
			'judges' => array('type' => 'varchar','precision' => '64'),
			'discipline' => array('type' => 'varchar','precision' => '8')
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
			'gruppen' => array('type' => 'varchar','precision' => '128'),
			'presets' => array('type' => 'text')
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
			'discipline' => array('type' => 'varchar','precision' => '8','default' => 'lead')
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
			'datum' => array('type' => 'date','default' => '0000-00-00','nullable' => False)
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
			'nachname' => array('type' => 'varchar','precision' => '20','nullable' => False),
			'vorname' => array('type' => 'varchar','precision' => '20','nullable' => False),
			'sex' => array('type' => 'varchar','precision' => '6'),
			'strasse' => array('type' => 'varchar','precision' => '35'),
			'plz' => array('type' => 'varchar','precision' => '8'),
			'ort' => array('type' => 'varchar','precision' => '35'),
			'tel' => array('type' => 'varchar','precision' => '20'),
			'fax' => array('type' => 'varchar','precision' => '20'),
			'geb_ort' => array('type' => 'varchar','precision' => '35'),
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
			'email' => array('type' => 'varchar','precision' => '40'),
			'homepage' => array('type' => 'varchar','precision' => '60'),
			'mobil' => array('type' => 'varchar','precision' => '20'),
			'acl' => array('type' => 'int','precision' => '4','default' => '0'),
			'freetext' => array('type' => 'longtext')
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
			'frm_id2' => array('type' => 'int','precision' => '4')
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
			'result_detail' => array('type' => 'varchar','precision' => '255')
		),
		'pk' => array('WetId','GrpId','route_order','PerId'),
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
		'ix' => array('nation'),
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
			'lic_suspended_by' => array('type' => 'int','precision' => '4')
		),
		'pk' => array('PerId','nation','lic_year'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	)
);
