<?php
  /**************************************************************************\
  * eGroupWare - Setup                                                       *
  * http://www.eGroupWare.org                                                *
  * Created by eTemplates DB-Tools written by ralfbecker@outdoor-training.de *
  * --------------------------------------------                             *
  * This program is free software; you can redistribute it and/or modify it  *
  * under the terms of the GNU General Public License as published by the    *
  * Free Software Foundation; either version 2 of the License, or (at your   *
  * option) any later version.                                               *
  \**************************************************************************/

  /* $Id$ */

	$test[] = '0.9.13.001';
	function ranking_upgrade0_9_13_001()
	{
		$GLOBALS['phpgw_setup']->oProc->AlterColumn('Wettkaempfe','name',array(
			'type' => 'varchar',
			'precision' => '100',
			'nullable' => False
		));

		$GLOBALS['setup_info']['ranking']['currentver'] = '1.0.0.001';
		return $GLOBALS['setup_info']['ranking']['currentver'];
	}


	$test[] = '1.0.0.001';
	function ranking_upgrade1_0_0_001()
	{
		$GLOBALS['phpgw_setup']->oProc->AlterColumn('Serien','name',array(
			'type' => 'varchar',
			'precision' => '64'
		));
		$GLOBALS['phpgw_setup']->oProc->AlterColumn('Serien','gruppen',array(
			'type' => 'varchar',
			'precision' => '128'
		));

		$GLOBALS['setup_info']['ranking']['currentver'] = '1.0.0.002';
		return $GLOBALS['setup_info']['ranking']['currentver'];
	}


	$test[] = '1.0.0.002';
	function ranking_upgrade1_0_0_002()
	{
		$GLOBALS['phpgw_setup']->oProc->AddColumn('Gruppen','extra',array(
			'type' => 'varchar',
			'precision' => '40'
		));

		$GLOBALS['setup_info']['ranking']['currentver'] = '1.0.0.003';
		return $GLOBALS['setup_info']['ranking']['currentver'];
	}


	$test[] = '1.0.0.003';
	function ranking_upgrade1_0_0_003()
	{
		$GLOBALS['phpgw_setup']->oProc->DropColumn('Personen',array(
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
				'tid' => array('type' => 'char','precision' => '1','nullable' => True,'default' => 'n'),
				'owner' => array('type' => 'int','precision' => '4','nullable' => True,'default' => '1'),
				'access' => array('type' => 'varchar','precision' => '7','nullable' => True,'default' => 'public'),
				'cat_id' => array('type' => 'varchar','precision' => '32','nullable' => True)
			),
			'pk' => array('PerId'),
			'fk' => array(),
			'ix' => array('nachname'),
			'uc' => array('rkey')
		),'lid');
		$GLOBALS['phpgw_setup']->oProc->DropColumn('Personen',array(
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
				'owner' => array('type' => 'int','precision' => '4','nullable' => True,'default' => '1'),
				'access' => array('type' => 'varchar','precision' => '7','nullable' => True,'default' => 'public'),
				'cat_id' => array('type' => 'varchar','precision' => '32','nullable' => True)
			),
			'pk' => array('PerId'),
			'fk' => array(),
			'ix' => array('nachname'),
			'uc' => array('rkey')
		),'tid');
		$GLOBALS['phpgw_setup']->oProc->DropColumn('Personen',array(
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
				'access' => array('type' => 'varchar','precision' => '7','nullable' => True,'default' => 'public'),
				'cat_id' => array('type' => 'varchar','precision' => '32','nullable' => True)
			),
			'pk' => array('PerId'),
			'fk' => array(),
			'ix' => array('nachname'),
			'uc' => array('rkey')
		),'owner');
		$GLOBALS['phpgw_setup']->oProc->DropColumn('Personen',array(
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
				'cat_id' => array('type' => 'varchar','precision' => '32','nullable' => True)
			),
			'pk' => array('PerId'),
			'fk' => array(),
			'ix' => array('nachname'),
			'uc' => array('rkey')
		),'access');
		$GLOBALS['phpgw_setup']->oProc->DropColumn('Personen',array(
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
				'homepage' => array('type' => 'varchar','precision' => '60','nullable' => True)
			),
			'pk' => array('PerId'),
			'fk' => array(),
			'ix' => array('nachname'),
			'uc' => array('rkey')
		),'cat_id');
		$GLOBALS['phpgw_setup']->oProc->AlterColumn('Personen','anrede',array(
			'type' => 'varchar',
			'precision' => '40'
		));

		$GLOBALS['setup_info']['ranking']['currentver'] = '1.0.0.004';
		return $GLOBALS['setup_info']['ranking']['currentver'];
	}


	$test[] = '1.0.0.004';
	function ranking_upgrade1_0_0_004()
	{
		$GLOBALS['phpgw_setup']->oProc->AddColumn('Serien','presets',array(
			'type' => 'text'
		));

		$GLOBALS['setup_info']['ranking']['currentver'] = '1.0.0.005';
		return $GLOBALS['setup_info']['ranking']['currentver'];
	}
?>
