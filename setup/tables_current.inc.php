<?php

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
				'verband' => array('type' => 'varchar','precision' => '60'),
				'bemerkung' => array('type' => 'text'),
				'nation' => array('type' => 'varchar','precision' => '5'),
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
				'route_quota' => array('type' => 'int','precision' => '2')
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
				'result_top_time' => array('type' => 'int','precision' => '4'),
				'result_zone' => array('type' => 'int','precision' => '2'),
				'result_detail' => array('type' => 'varchar','precision' => '255'),
				'result_modified' => array('type' => 'int','precision' => '8'),
				'result_modifier' => array('type' => 'int','precision' => '4'),
				'start_order' => array('type' => 'int','precision' => '2'),
				'start_number' => array('type' => 'int','precision' => '2'),
				'result_rank' => array('type' => 'int','precision' => '2')
			),
			'pk' => array('WetId','GrpId','route_order','PerId'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		)
	);
