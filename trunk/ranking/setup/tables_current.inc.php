<?php

	$phpgw_baseline = array(
		'rang.Wettkaempfe' => array(
			'fd' => array(
				'WetId' => array('type' => 'auto','nullable' => False),
				'rkey' => array('type' => 'char','precision' => '8','nullable' => False),
				'name' => array('type' => 'varchar','precision' => '60','nullable' => False),
				'dru_bez' => array('type' => 'varchar','precision' => '20','nullable' => True),
				'datum' => array('type' => 'date','default' => '0000-00-00','nullable' => False),
				'pkte' => array('type' => 'int','precision' => '4','nullable' => True),
				'pkt_bis' => array('type' => 'float','precision' => '16','nullable' => True),
				'feld_pkte' => array('type' => 'int','precision' => '4','nullable' => True),
				'feld_bis' => array('type' => 'float','precision' => '16','nullable' => True),
				'faktor' => array('type' => 'float','precision' => '16','nullable' => True),
				'serie' => array('type' => 'int','precision' => '4','nullable' => True),
				'open' => array('type' => 'int','precision' => '4','nullable' => True),
				'pflicht' => array('type' => 'varchar','precision' => '3','nullable' => True,'default' => 'no'),
				'ex_pkte' => array('type' => 'varchar','precision' => '3','nullable' => True,'default' => 'no'),
				'nation' => array('type' => 'char','precision' => '5','nullable' => True),
				'gruppen' => array('type' => 'varchar','precision' => '60','nullable' => True),
				'homepage' => array('type' => 'varchar','precision' => '60','nullable' => True)
			),
			'pk' => array('WetId'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array('rkey')
		),
		'rang.Serien' => array(
			'fd' => array(
				'SerId' => array('type' => 'auto','nullable' => False),
				'rkey' => array('type' => 'char','precision' => '8','nullable' => False),
				'name' => array('type' => 'varchar','precision' => '50','nullable' => True),
				'max_rang' => array('type' => 'int','precision' => '2','nullable' => True),
				'max_serie' => array('type' => 'int','precision' => '2','nullable' => True),
				'faktor' => array('type' => 'float','precision' => '16','nullable' => True),
				'pkte' => array('type' => 'int','precision' => '4','nullable' => False),
				'split_by_places' => array('type' => 'varchar','precision' => '12','default' => 'no','nullable' => False),
				'nation' => array('type' => 'char','precision' => '5','nullable' => True),
				'gruppen' => array('type' => 'varchar','precision' => '60','nullable' => True)
			),
			'pk' => array('SerId'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array('rkey')
		),
		'rang.Gruppen' => array(
			'fd' => array(
				'GrpId' => array('type' => 'auto','nullable' => False),
				'rkey' => array('type' => 'char','precision' => '8','nullable' => False),
				'name' => array('type' => 'varchar','precision' => '40','nullable' => False),
				'nation' => array('type' => 'char','precision' => '5','nullable' => True),
				'serien_pat' => array('type' => 'char','precision' => '8','nullable' => True),
				'sex' => array('type' => 'varchar','precision' => '6','nullable' => True),
				'from_year' => array('type' => 'int','precision' => '2','nullable' => True),
				'to_year' => array('type' => 'int','precision' => '2','nullable' => True),
				'rls' => array('type' => 'int','precision' => '2','nullable' => False),
				'vor_rls' => array('type' => 'int','precision' => '2','nullable' => True),
				'vor' => array('type' => 'int','precision' => '2','nullable' => True)
			),
			'pk' => array('GrpId'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array('rkey')
		),
		'rang.RangListenSysteme' => array(
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
		'rang.PktSysteme' => array(
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
		'rang.Results' => array(
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
		'rang.Feldfaktoren' => array(
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
		'rang.PktSystemPkte' => array(
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
		'rang.Gruppen2Personen' => array(
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
		'rang.Personen' => array(
			'fd' => array(
				'PerId' => array('type' => 'auto','nullable' => False),
				'rkey' => array('type' => 'varchar','precision' => '8','nullable' => False),
				'nachname' => array('type' => 'varchar','precision' => '20','nullable' => False),
				'vorname' => array('type' => 'varchar','precision' => '20','nullable' => False),
				'sex' => array('type' => 'varchar','precision' => '6','nullable' => True),
				'strasse' => array('type' => 'varchar','precision' => '35','nullable' => True),
				'plz' => array('type' => 'varchar','precision' => '8','nullable' => True),
				'ort' => array('type' => 'varchar','precision' => '35','nullable' => True),
				'tel' => array('type' => 'varchar','precision' => '20','nullable' => True),
				'fax' => array('type' => 'varchar','precision' => '20','nullable' => True),
				'geb_ort' => array('type' => 'varchar','precision' => '35','nullable' => True),
				'geb_date' => array('type' => 'date','nullable' => True),
				'practice' => array('type' => 'int','precision' => '2','nullable' => True),
				'groesse' => array('type' => 'int','precision' => '2','nullable' => True),
				'gewicht' => array('type' => 'int','precision' => '2','nullable' => True),
				'lizenz' => array('type' => 'varchar','precision' => '6','nullable' => True),
				'kader' => array('type' => 'varchar','precision' => '5','nullable' => True),
				'anrede' => array('type' => 'varchar','precision' => '20','nullable' => True),
				'verband' => array('type' => 'varchar','precision' => '60','nullable' => True),
				'bemerkung' => array('type' => 'text','nullable' => True),
				'nation' => array('type' => 'varchar','precision' => '5','nullable' => True),
				'hobby' => array('type' => 'varchar','precision' => '60','nullable' => True),
				'sport' => array('type' => 'varchar','precision' => '60','nullable' => True),
				'profi' => array('type' => 'varchar','precision' => '40','nullable' => True),
				'email' => array('type' => 'varchar','precision' => '40','nullable' => True),
				'homepage' => array('type' => 'varchar','precision' => '60','nullable' => True),
				'lid' => array('type' => 'varchar','precision' => '32','nullable' => True),
				'tid' => array('type' => 'char','precision' => '1','nullable' => True,'default' => 'n'),
				'owner' => array('type' => 'int','precision' => '4','nullable' => True,'default' => '1'),
				'access' => array('type' => 'varchar','precision' => '7','nullable' => True,'default' => 'public'),
				'cat_id' => array('type' => 'varchar','precision' => '32','nullable' => True)
			),
			'pk' => array('PerId'),
			'fk' => array(),
			'ix' => array('nachname'),
			'uc' => array('rkey')
		)
	);
