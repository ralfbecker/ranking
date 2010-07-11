<?php
/**
 * eGroupWare digital ROCK Rankings - setup
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2006-10 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$
 */

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


function ranking_upgrade1_0_0_002()
{
	$GLOBALS['phpgw_setup']->oProc->AddColumn('Gruppen','extra',array(
		'type' => 'varchar',
		'precision' => '40'
	));

	$GLOBALS['setup_info']['ranking']['currentver'] = '1.0.0.003';
	return $GLOBALS['setup_info']['ranking']['currentver'];
}


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


function ranking_upgrade1_0_0_004()
{
	$GLOBALS['phpgw_setup']->oProc->AddColumn('Serien','presets',array(
		'type' => 'text'
	));

	$GLOBALS['setup_info']['ranking']['currentver'] = '1.0.0.005';
	return $GLOBALS['setup_info']['ranking']['currentver'];
}


function ranking_upgrade1_0_0_005()
{
	$GLOBALS['phpgw_setup']->oProc->AlterColumn('Wettkaempfe','gruppen',array(
		'type' => 'varchar',
		'precision' => '80'
	));

	$GLOBALS['setup_info']['ranking']['currentver'] = '1.0.0.006';
	return $GLOBALS['setup_info']['ranking']['currentver'];
}


function ranking_upgrade1_0_0_006()
{
	$GLOBALS['phpgw_setup']->oProc->AddColumn('Personen','mobil',array(
		'type' => 'varchar',
		'precision' => '20'
	));
	$GLOBALS['phpgw_setup']->oProc->AddColumn('Personen','acl',array(
		'type' => 'int',
		'precision' => '4',
		'default' => '0'
	));

	$GLOBALS['setup_info']['ranking']['currentver'] = '1.0.0.007';
	return $GLOBALS['setup_info']['ranking']['currentver'];
}


function ranking_upgrade1_0_0_007()
{
	$GLOBALS['phpgw_setup']->oProc->AddColumn('Wettkaempfe','quota',array(
		'type' => 'int',
		'precision' => '2'
	));
	$GLOBALS['phpgw_setup']->oProc->AddColumn('Wettkaempfe','host_nation',array(
		'type' => 'varchar',
		'precision' => '3'
	));
	$GLOBALS['phpgw_setup']->oProc->AddColumn('Wettkaempfe','host_quota',array(
		'type' => 'int',
		'precision' => '2'
	));
	$GLOBALS['phpgw_setup']->oProc->AddColumn('Wettkaempfe','deadline',array(
		'type' => 'date'
	));
	$GLOBALS['phpgw_setup']->oProc->AddColumn('Wettkaempfe','prequalified',array(
		'type' => 'varchar',
		'precision' => '100'
	));

	$GLOBALS['setup_info']['ranking']['currentver'] = '1.0.0.008';
	return $GLOBALS['setup_info']['ranking']['currentver'];
}


function ranking_upgrade1_0_0_008()
{
	$GLOBALS['phpgw_setup']->oProc->DropColumn('Wettkaempfe',array(
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
			'deadline' => array('type' => 'date')
		),
		'pk' => array('WetId'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array('rkey')
	),'prequalified');
	$GLOBALS['phpgw_setup']->oProc->AddColumn('Wettkaempfe','prequal_ranking',array(
		'type' => 'int',
		'precision' => '2'
	));
	$GLOBALS['phpgw_setup']->oProc->AddColumn('Wettkaempfe','prequal_comp',array(
		'type' => 'int',
		'precision' => '2'
	));
	$GLOBALS['phpgw_setup']->oProc->AddColumn('Wettkaempfe','prequal_comps',array(
		'type' => 'varchar',
		'precision' => '64'
	));

	$GLOBALS['setup_info']['ranking']['currentver'] = '1.0.0.009';
	return $GLOBALS['setup_info']['ranking']['currentver'];
}


function ranking_upgrade1_0_0_009()
{
	$GLOBALS['phpgw_setup']->oProc->AddColumn('Wettkaempfe','judges',array(
		'type' => 'varchar',
		'precision' => '64'
	));

	$GLOBALS['setup_info']['ranking']['currentver'] = '1.0.0.010';
	return $GLOBALS['setup_info']['ranking']['currentver'];
}


function ranking_upgrade1_0_0_010()
{
	$GLOBALS['phpgw_setup']->oProc->AddColumn('Personen','freetext',array(
		'type' => 'longtext'
	));

	$GLOBALS['setup_info']['ranking']['currentver'] = '1.0.0.011';
	return $GLOBALS['setup_info']['ranking']['currentver'];
}


function ranking_upgrade1_0_0_011()
{
	$GLOBALS['egw_setup']->oProc->CreateTable('Routes',array(
		'fd' => array(
			'WetId' => array('type' => 'int','precision' => '4'),
			'GrpId' => array('type' => 'int','precision' => '4'),
			'route_order' => array('type' => 'int','precision' => '2'),
			'route_name' => array('type' => 'varchar','precision' => '80'),
			'route_judge' => array('type' => 'varchar','precision' => '80'),
			'route_state' => array('type' => 'int','precision' => '2'),
			'route_type' => array('type' => 'int','precision' => '2')
		),
		'pk' => array('WetId','GrpId','route_order'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	));

	return $GLOBALS['setup_info']['ranking']['currentver'] = '1.3.001';
}


function ranking_upgrade1_3_001()
{
	$GLOBALS['egw_setup']->oProc->CreateTable('RouteResults',array(
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
			'start_number' => array('type' => 'int','precision' => '2')
		),
		'pk' => array('WetId','GrpId','route_order','PerId'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	));

	return $GLOBALS['setup_info']['ranking']['currentver'] = '1.3.002';
}


function ranking_upgrade1_3_002()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('Routes','route_modified',array(
		'type' => 'int',
		'precision' => '8'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('Routes','route_modifier',array(
		'type' => 'int',
		'precision' => '4'
	));

	return $GLOBALS['setup_info']['ranking']['currentver'] = '1.3.003';
}


function ranking_upgrade1_3_003()
{
	$GLOBALS['egw_setup']->oProc->RenameColumn('Routes','route_state','route_status');
	$GLOBALS['egw_setup']->oProc->AddColumn('Routes','route_iso_open',array(
		'type' => 'varchar',
		'precision' => '40'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('Routes','route_iso_close',array(
		'type' => 'varchar',
		'precision' => '40'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('Routes','route_start',array(
		'type' => 'varchar',
		'precision' => '64'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('Routes','route_result',array(
		'type' => 'varchar',
		'precision' => '80'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('Routes','route_comments',array(
		'type' => 'text'
	));

	return $GLOBALS['setup_info']['ranking']['currentver'] = '1.3.004';
}


function ranking_upgrade1_3_004()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('Routes','route_quota',array(
		'type' => 'int',
		'precision' => '2'
	));

	return $GLOBALS['setup_info']['ranking']['currentver'] = '1.3.005';
}


function ranking_upgrade1_3_005()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('RouteResults','result_rank',array(
		'type' => 'int',
		'precision' => '2'
	));

	return $GLOBALS['setup_info']['ranking']['currentver'] = '1.3.006';
}


function ranking_upgrade1_3_006()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('Wettkaempfe','discipline',array(
		'type' => 'varchar',
		'precision' => '8'
	));

	return $GLOBALS['setup_info']['ranking']['currentver'] = '1.3.007';
}


function ranking_upgrade1_3_007()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('Gruppen','discipline',array(
		'type' => 'varchar',
		'precision' => '8',
		'default' => 'lead'
	));

	return $GLOBALS['setup_info']['ranking']['currentver'] = '1.3.008';
}


function ranking_upgrade1_3_008()
{
	$GLOBALS['egw_setup']->oProc->AlterColumn('RouteResults','result_zone',array(
		'type' => 'int',
		'precision' => '4'
	));

	return $GLOBALS['setup_info']['ranking']['currentver'] = '1.3.009';
}


function ranking_upgrade1_3_009()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('Routes','route_num_problems',array(
		'type' => 'int',
		'precision' => '2'
	));

	return $GLOBALS['setup_info']['ranking']['currentver'] = '1.3.010';
}


function ranking_upgrade1_3_010()
{
	$GLOBALS['egw_setup']->oProc->RenameColumn('RouteResults','result_top_time','result_time');
	$GLOBALS['egw_setup']->oProc->AlterColumn('RouteResults','result_zone',array(
		'type' => 'int',
		'precision' => '4'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('RouteResults','result_detail',array(
		'type' => 'varchar',
		'precision' => '255'
	));

	return $GLOBALS['setup_info']['ranking']['currentver'] = '1.3.011';
}


function ranking_upgrade1_3_011()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('Routes','route_observation_time',array(
		'type' => 'varchar',
		'precision' => '40'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('Routes','route_climbing_time',array(
		'type' => 'varchar',
		'precision' => '40'
	));

	return $GLOBALS['setup_info']['ranking']['currentver'] = '1.3.012';
}


function ranking_upgrade1_3_012()
{
	$GLOBALS['egw_setup']->oProc->CreateTable('Displays',array(
		'fd' => array(
			'dsp_id' => array('type' => 'auto'),
			'dsp_name' => array('type' => 'varchar','precision' => '80'),
			'WetId' => array('type' => 'int','precision' => '4'),
			'dsp_current' => array('type' => 'varchar','precision' => '255'),
			'frm_id' => array('type' => 'int','precision' => '4'),
			'dsp_ip' => array('type' => 'varchar','precision' => '64'),
			'dsp_port' => array('type' => 'int','precision' => '4'),
			'dsp_format' => array('type' => 'varchar','precision' => '128'),
			'dsp_rows' => array('type' => 'int','precision' => '4','default' => '1'),
			'dsp_cols' => array('type' => 'int','precision' => '4','default' => '20'),
			'dsp_remark' => array('type' => 'text'),
			'dsp_timeout' => array('type' => 'decimal','precision' => '12','scale' => '2'),
			'dsp_line' => array('type' => 'int','precision' => '4'),
			'dsp_athletes' => array('type' => 'varchar','precision' => '255'),
			'dsp_charset' => array('type' => 'varchar','precision' => '32'),
			'dsp_clone_of' => array('type' => 'int','precision' => '4'),
			'dsp_access' => array('type' => 'varchar','precision' => '255'),
		),
		'pk' => array('dsp_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	));

	return $GLOBALS['setup_info']['ranking']['currentver'] = '1.3.013';
}


function ranking_upgrade1_3_013()
{
	$GLOBALS['egw_setup']->oProc->CreateTable('DisplayFormats',array(
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
	));

	return $GLOBALS['setup_info']['ranking']['currentver'] = '1.3.014';
}

function ranking_upgrade1_3_014()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('Routes','dsp_id',array(
		'type' => 'int',
		'precision' => '4'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('Routes','frm_id',array(
		'type' => 'int',
		'precision' => '4'
	));

	return $GLOBALS['setup_info']['ranking']['currentver'] = '1.4';
}

function ranking_upgrade1_4()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('Routes','route_time_host',array(
		'type' => 'varchar',
		'precision' => '64'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('Routes','route_time_port',array(
		'type' => 'int',
		'precision' => '4'
	));

	return $GLOBALS['setup_info']['ranking']['currentver'] = '1.4.001';
}


function ranking_upgrade1_4_001()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('Routes','dsp_id2',array(
		'type' => 'int',
		'precision' => '4'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('Routes','frm_id2',array(
		'type' => 'int',
		'precision' => '4'
	));

	return $GLOBALS['setup_info']['ranking']['currentver'] = '1.4.002';
}


function ranking_upgrade1_4_002()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('Displays','dsp_etag',array(
		'type' => 'int',
		'precision' => '4',
		'default' => '0'
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('Displays','dsp_format',array(
		'type' => 'varchar',
		'precision' => '255'
	));

	return $GLOBALS['setup_info']['ranking']['currentver'] = '1.4.003';
}

/**
 * Create Federations and Athlete2Fed tables
 *
 * @return string new version-number
 */
function ranking_upgrade1_4_003()
{
	$GLOBALS['egw_setup']->oProc->CreateTable('Federations',array(
		'fd' => array(
			'fed_id' => array('type' => 'auto','nullable' => False),
			'verband' => array('type' => 'varchar','precision' => '80','nullable' => False),
			'nation' => array('type' => 'varchar','precision' => '3','nullable' => False),
			'fed_parent' => array('type' => 'int','precision' => '4'),
			'fed_aliases' => array('type' => 'text')
		),
		'pk' => array('fed_id'),
		'fk' => array(),
		'ix' => array('nation','fed_parent'),
		'uc' => array()
	));

	$GLOBALS['egw_setup']->oProc->CreateTable('Athlete2Fed',array(
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
	));

	return $GLOBALS['setup_info']['ranking']['currentver'] = '1.5.001';
}

/**
 * Fill Federations and Athlete2Fed tables
 *
 * @return string new version-number
 */
function ranking_upgrade1_5_001()
{
	foreach($GLOBALS['egw_setup']->db->select('Personen','DISTINCT nation,verband',"sex IS NOT NULL AND nation IS NOT NULL AND nation!=''",__LINE__,__FILE__,false,'','ranking') as $row)
	{
		$GLOBALS['egw_setup']->db->insert('Federations',$row,false,__LINE__,__FILE__,'ranking');
		$fed_id = $GLOBALS['egw_setup']->db->get_last_insert_id('Federations','fed_id');

		foreach($GLOBALS['egw_setup']->db->select('Personen','PerId',$row,__LINE__,__FILE__,false,'','ranking') as $PerId)
		{
			$GLOBALS['egw_setup']->db->insert('Athlete2Fed',array(
				'PerId'  => $PerId,
				'fed_id' => $fed_id,
			),false,__LINE__,__FILE__,'ranking');
		}
	}

	return $GLOBALS['setup_info']['ranking']['currentver'] = '1.5.002';
}

function ranking_upgrade1_5_002()
{
	$GLOBALS['egw_setup']->oProc->DropColumn('Personen',array(
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
	),'verband');
	$GLOBALS['egw_setup']->oProc->DropColumn('Personen',array(
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
	),'nation');

	return $GLOBALS['setup_info']['ranking']['currentver'] = '1.5.003';
}


function ranking_upgrade1_5_003()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('Federations','fed_url',array(
		'type' => 'varchar',
		'precision' => '128'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('Federations','fed_nationname',array(
		'type' => 'varchar',
		'precision' => '80'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('Federations','fed_continent',array(
		'type' => 'int',
		'precision' => '1',
		'default' => '0'
	));

	return $GLOBALS['setup_info']['ranking']['currentver'] = '1.5.004';
}


function ranking_upgrade1_5_004()
{
	/* done by RefreshTable() anyway
	$GLOBALS['egw_setup']->oProc->AddColumn('Federations','fed_shortcut',array(
		'type' => 'varchar',
		'precision' => '20'
	));*/
	$GLOBALS['egw_setup']->oProc->RefreshTable('Federations',array(
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
	));

	return $GLOBALS['setup_info']['ranking']['currentver'] = '1.5.005';
}


function ranking_upgrade1_5_005()
{
	$GLOBALS['egw_setup']->oProc->CreateTable('Licenses',array(
		'fd' => array(
			'PerId' => array('type' => 'int','precision' => '4','nullable' => False),
			'nation' => array('type' => 'varchar','precision' => '3','nullable' => False),
			'lic_year' => array('type' => 'int','precision' => '2','nullable' => False),
			'lic_status' => array('type' => 'char','precision' => '1','default' => 'c'),
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
	));

	$GLOBALS['egw_setup']->db->query('INSERT INTO Licenses (PerId,nation,lic_year,lic_status,lic_applied,lic_confirmed,lic_suspended) '.
		"SELECT PerId,'',-WetId,CASE pkt WHEN 1 THEN 'a' WHEN 2 THEN 'c' ELSE 's' END,
			CASE pkt WHEN 1 THEN datum ELSE NULL END,
			CASE pkt WHEN 2 THEN datum ELSE NULL END,
			CASE pkt WHEN 3 THEN datum ELSE NULL END FROM Results WHERE GrpId=0",__LINE__,__FILE__);
	$GLOBALS['egw_setup']->db->delete('Results',array('GrpId' => 0),__LINE__,__FILE__);
/*
CREATE TABLE `Licenses` (
`PerId`                  INTEGER NOT NULL,
`nation`                 VARCHAR(3) NOT NULL,
`lic_year`               SMALLINT NOT NULL,
`lic_status`             VARCHAR(1) DEFAULT 'c',
`lic_applied`            DATE,
`lic_applied_by`         INTEGER,
`lic_confirmed`          DATE,
`lic_confirmed_by`       INTEGER,
`lic_suspended`          DATE,
`lic_suspended_by`       INTEGER,
PRIMARY KEY (`PerId`, `nation`, `lic_year`)
)CHARACTER SET utf8
INSERT INTO Licenses (PerId,nation,lic_year,lic_status,lic_applied,lic_confirmed,lic_suspended)
	SELECT PerId,'',-WetId,CASE pkt WHEN 1 THEN 'a' WHEN 2 THEN 'c' ELSE 's' END,
		CASE pkt WHEN 1 THEN datum ELSE NULL END,
		CASE pkt WHEN 2 THEN datum ELSE NULL END,
		CASE pkt WHEN 3 THEN datum ELSE NULL END
	FROM Results WHERE GrpId=0
DELETE FROM Results WHERE GrpId=0
*/
	return $GLOBALS['setup_info']['ranking']['currentver'] = '1.5.006';
}


function ranking_upgrade1_5_006()
{
/*
ALTER TABLE Wettkaempfe ADD Column prequal_extra VARCHAR(255)
ALTER TABLE Wettkaempfe ADD Column quota_extra VARCHAR(255)
ALTER TABLE Wettkaempfe ADD Column no_complimentary TINYINT
*/
	$GLOBALS['egw_setup']->oProc->AddColumn('Wettkaempfe','prequal_extra',array(
		'type' => 'varchar',
		'precision' => '255'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('Wettkaempfe','quota_extra',array(
		'type' => 'varchar',
		'precision' => '255'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('Wettkaempfe','no_complimentary',array(
		'type' => 'bool'
	));

	return $GLOBALS['setup_info']['ranking']['currentver'] = '1.5.007';
}

/**
 * Adding culumns with explicit points and place in a cut
 *
 * @return string '1.7.001'
 */
function ranking_upgrade1_5_007()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('Results','cup_platz',array(
		'type' => 'int',
		'precision' => '2'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('Results','cup_pkt',array(
		'type' => 'int',
		'precision' => '2'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('Results','modified',array(
		'type' => 'timestamp',
		'default' => 'current_timestamp'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('Results','modifier',array(
		'type' => 'int',
		'precision' => '4'
	));

	$european_nations = array(
		'ALB','AND','ARM','AUT','AZE','BLR','BEL','BIH','BUL',
		'CRO','CYP','CZE','DEN','EST','ESP','FIN','FRA','GBR',
		'GEO','GER','GRE','HUN','IRL','ISL','ISR','ITA','LAT',
		'LIE','LTU','LUX','MDA','MKD','MLT','MON','NED','NOR',
		'POL','POR','ROU','RUS','SRB','SLO','SMR','SUI','SVK',
		'SWE','TUR','UKR'
	);
	$db = $GLOBALS['egw_setup']->db;
	$pkte = array();
	foreach($db->select('Wettkaempfe','WetId,datum,serie,Serien.nation,Serien.faktor,Serien.pkte,Serien.rkey AS serie_rkey','',
		__LINE__,__FILE__,false,'','ranking',0,' JOIN Serien ON serie=SerId') as $wettk)
	{
		if (!isset($pkte[$wettk['pkte']]))
		{
			foreach($db->select('PktSystemPkte','*',array('PktId' => $wettk['pkte']),__LINE__,__FILE__) as $row)
			{
				$pkte[$wettk['pkte']][$row['platz']] = $row['pkt'];
			}
		}
		$allowed_nations = false;
		if (empty($wettk['nation']) && (int)$wettk['datum'] >= 2006 && preg_match('/_(EYC|EYS)$/',$wettk['serie_rkey']))
		{
			$allowed_nations = $european_nations;
		}
		$results = $ex_aquo = array();
		$last_GrpId = null;
		foreach($db->select('Results','Results.PerId,GrpId,platz,nation',array('WetId' => $wettk['WetId']),
			__LINE__,__FILE__,false,' ORDER BY GrpId,platz','ranking',0,
			' JOIN Personen USING(PerId) '.
			' JOIN Athlete2Fed ON Personen.PerId=Athlete2Fed.PerId AND a2f_end=9999 JOIN Federations USING(fed_id)') as $row)
		{
			if (!$row['platz'] || $row['platz'] == 999) continue;	// not placed or disqualified

			if ($allowed_nations)
			{
				if (!in_array($row['nation'],$allowed_nations)) continue;	// ignore results of NOT allowed nations

				if ($last_GrpId != $row['GrpId'])
				{
					$abs_platz = $ex_platz = $last_platz = 1;
					$last_GrpId = $row['GrpId'];
				}
				$row['cup_platz'] = $ex_platz = $last_platz == $row['platz'] ? $ex_platz : $abs_platz;
				$last_platz = $row['platz'];
				$abs_platz++;
			}
			$results[$row['GrpId']][$row['PerId']] = $row;
			$ex_aquo[$row['GrpId']][$allowed_nations ? $ex_platz : $row['platz']]++;
		}
		foreach($results as $GrpId => &$rows)
		{
			foreach($rows as $PerId => $row)
			{
				$platz = isset($row['cup_platz']) ? $row['cup_platz'] : $row['platz'];
				$update = array('cup_platz' => $row['cup_platz']);
				if (isset($pkte[$wettk['pkte']][$platz]))
				{
					// int. competitions from 2009 on use averaged points (rounded down)
					if (empty($wettk['nation']) && (int)$wettk['datum'] >= 2009 && $ex_aquo[$row['GrpId']][$platz] > 1)
					{
						for($n = 0; $n < $ex_aquo[$row['GrpId']][$platz]; ++$n)
						{
							$update['cup_pkt'] += $pkte[$wettk['pkte']][$platz+$n];
						}
						$update['cup_pkt'] = 100 * $wettk['faktor'] * (int)floor($update['cup_pkt'] / $ex_aquo[$row['GrpId']][$platz]);
					}
					else
					{
						$update['cup_pkt'] = 100 * $wettk['faktor'] * $pkte[$wettk['pkte']][$platz];
					}
				}
				$db->update('Results',$update,array(
					'WetId' => $wettk['WetId'],
					'GrpId' => $row['GrpId'],
					'PerId' => $row['PerId'],
				),__LINE__,__FILE__,'ranking');
			}
		}
	}
	return $GLOBALS['setup_info']['ranking']['currentver'] = '1.7.001';
}

/**
 * Add column to store starlist creation modus
 *
 * ALTER TABLE `Routes` ADD `slist_order` TINYINT( 4 ) NULL
 *
 * @return string
 */
function ranking_upgrade1_7_001()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('Routes','slist_order',array(
		'type' => 'int',
		'precision' => '1',
		'comment' => 'mode of startlist creation'
	));

	return $GLOBALS['setup_info']['ranking']['currentver'] = '1.7.002';
}

/**
 * Add cat_id, modified and modifier colums to Wettkaempfe table
 * 
 * ALTER TABLE `Wettkaempfe` ADD `cat_id` INTEGER
 * ALTER TABLE `Wettkaempfe` ADD `modified` TIMESTAMP NOT NULL
 * ALTER TABLE `Wettkaempfe` ADD `modifier` INTEGER
 * 
 * @param egw_db $db=null
 * @return string
 */
function ranking_upgrade1_7_002(egw_db $db=null)
{
	if (is_null($db))
	{
		$rang_db = ranking_so::get_rang_db();
		// update separate rang db too
		if ($rang_db !== $GLOBALS['egw']->db) ranking_upgrade1_7_002($rang_db);
	}
	else
	{
		unset($GLOBALS['egw_setup']->oProc->m_odb);
		$GLOBALS['egw_setup']->oProc->m_odb = ranking_so::get_rang_db();
	}
	$GLOBALS['egw_setup']->oProc->m_odb->query_log = '/tmp/query.log';
	$GLOBALS['egw_setup']->oProc->AddColumn('Wettkaempfe','cat_id',array(
		'type' => 'int',
		'precision' => '4'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('Wettkaempfe','modified',array(
		'type' => 'timestamp',
		'nullable' => False,
		'default' => 'current_timestamp'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('Wettkaempfe','modifier',array(
		'type' => 'int',
		'precision' => '4'
	));
	
	$nations = array(
		'int' => array(
			'int_adult'   => '^[0-9]{2,2}_(WC|WM|EM|EC|LC|TR|AM|AC|SM|LM|NAC){1,1}.*',
			'int_youth'   => '^[0-9]{2,2}(EYC|EYS|_Y|_JWM|_NAC){1,1}.*',
			'int_masters' => '^[0-9]{2,2}_[^WERASL]{1}.*',
		),
		'GER' => array(
			'ger_boulder' => '^[0-9]{2,2}_B.*',
			'ger_lead'    => '^[0-9]{2,2}_D.*',
			'ger_speed'   => '^[0-9]{2,2}_S.*',
			'ger_youth'   => '^[0-9]{2,2}[_J]{1,1}[^WL]+.*',
			'ger_state'   => '^[0-9]{2,2}[_J]*LM[0-9_]{1,1}.*',
		),
		'SUI' => array(
			'sui_adult' => '^[0-9]{2,2}_[^RY].*',
			'sui_youth' => '^[0-9]{2,2}_[Y].*',
			'sui_local' => '^[0-9]{2,2}_RG_.*',
		),
		'XYZ' => array(
			'xyz' => '^.*',
		),
	);
	foreach($nations as $nation => $cats)
	{
		$nat_parent = ranking_so::cat_rkey2id($nation,$nation.' competitions');
		
		foreach($GLOBALS['egw_setup']->oProc->m_odb->select('Wettkaempfe','WetId,rkey,gruppen',array(
			'nation' => $nation != 'int' ? $nation : null,
		),__LINE__,__FILE__,false,'','ranking') as $row)
		{
			unset($cat_id);
			foreach($cats as $rkey => $pattern)
			{
				if (preg_match('/'.$pattern.'/i',$row['rkey']))
				{
					$cat_id = ranking_so::cat_rkey2id($rkey,str_replace('_',' ',$rkey),$nat_parent);
					break;
				}
			}
			if (!$cat_id)
			{
				$cat_id = $nat_parent;
				$rkey = $nation.' competitions';
			}
			echo "<p>$nation: $row[rkey] ($row[WetId]) --> $rkey ($cat_id)</p>\n";
			$GLOBALS['egw_setup']->oProc->m_odb->query('UPDATE Wettkaempfe SET cat_id='.(int)$cat_id.' WHERE WetId='.(int)$row['WetId'],__LINE__,__FILE__);
		}
	}
	
	$GLOBALS['egw_setup']->oProc->m_odb->query_log = false;

	if (!is_null($db))
	{
		unset($GLOBALS['egw_setup']->oProc->m_odb);
		$GLOBALS['egw_setup']->oProc->m_odb = $GLOBALS['egw']->db;
	}
	return $GLOBALS['setup_info']['ranking']['currentver'] = '1.7.003';
}

/**
 * Create RouteTeamResults table
 *
 * @return string new version-number
 */
function ranking_upgrade1_7_003()
{
	$GLOBALS['egw_setup']->oProc->CreateTable('RelayResults',array(
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
			'result_modifier' => array('type' => 'int','precision' => '4'),
		),
		'pk' => array('WetId','GrpId','route_order','team_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	));
	return $GLOBALS['setup_info']['ranking']['currentver'] = '1.7.004';
}

/**
 * Add speedrelay discipline to categories table
 * 
 * @return string
 */
function ranking_upgrade1_7_004()
{
/*
	$GLOBALS['egw_setup']->oProc->AlterColumn('Gruppen','discipline',array(
		'type' => 'varchar',
		'precision' => '10',
		'default' => 'lead'
	));
*/
	$GLOBALS['egw_setup']->oProc->query("ALTER TABLE  `Gruppen` CHANGE  `discipline`  `discipline` ENUM(  'lead',  'boulder',  'speed',  'speedrelay' ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL",__LINE__,__FILE__);

	return $GLOBALS['setup_info']['ranking']['currentver'] = '1.7.005';
}


/**
 * Adding columns for current climber, next climber, boulder rotation time
 * 
 * @return string
 */
function ranking_upgrade1_7_005()
{
/*
   ALTER TABLE `Routes` ADD `current_1` INTEGER;
   ALTER TABLE `Routes` ADD `current_2` INTEGER;
   ALTER TABLE `Routes` ADD `current_3` INTEGER;
   ALTER TABLE `Routes` ADD `current_4` INTEGER;
   ALTER TABLE `Routes` ADD `current_5` INTEGER;
   ALTER TABLE `Routes` ADD `current_6` INTEGER;
   ALTER TABLE `Routes` ADD `current_7` INTEGER;
   ALTER TABLE `Routes` ADD `current_8` INTEGER;
   ALTER TABLE `Routes` ADD `next_1` INTEGER;
   ALTER TABLE `Routes` ADD `next_2` INTEGER;
   ALTER TABLE `Routes` ADD `next_3` INTEGER;
   ALTER TABLE `Routes` ADD `next_4` INTEGER;
   ALTER TABLE `Routes` ADD `next_5` INTEGER;
   ALTER TABLE `Routes` ADD `next_6` INTEGER;
   ALTER TABLE `Routes` ADD `next_7` INTEGER;
   ALTER TABLE `Routes` ADD `next_8` INTEGER;
   ALTER TABLE `Routes` ADD `boulder_time` INTEGER;
   ALTER TABLE `Routes` ADD `boulder_startet` BIGINT;
*/
	$GLOBALS['egw_setup']->oProc->AddColumn('Routes','current_1',array(
		'type' => 'int',
		'precision' => '4',
		'comment' => 'current climber, speed left, boulder #1'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('Routes','current_2',array(
		'type' => 'int',
		'precision' => '4',
		'comment' => 'current speed right, boulder #2'
	));
	for($i = 3; $i <= 8; ++$i)
	{
		$GLOBALS['egw_setup']->oProc->AddColumn('Routes','current_'.$i,array(
			'type' => 'int',
			'precision' => '4',
			'comment' => 'current boulder #'.$i
		));
	}
	for($i = 1; $i <= 8; ++$i)
	{
		$GLOBALS['egw_setup']->oProc->AddColumn('Routes','next_'.$i,array(
			'type' => 'int',
			'precision' => '4',
			'comment' => 'next for boulder #'.$i
		));
	}
	$GLOBALS['egw_setup']->oProc->AddColumn('Routes','boulder_time',array(
		'type' => 'int',
		'precision' => '4',
		'comment' => 'boulder rotation time in sec'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('Routes','boulder_startet',array(
		'type' => 'int',
		'precision' => '8',
		'comment' => 'last boulder rotation started timestamp'
	));

	return $GLOBALS['setup_info']['ranking']['currentver'] = '1.7.006';
}

