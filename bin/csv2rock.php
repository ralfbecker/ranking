#!/usr/bin/php -qC
<?php
/**************************************************************************\
* eGroupWare - digital ROCK Rankings - filter to create rock-files         *
* http://www.egroupware.org, http://www.digitalROCK.de                     *
* Written and (c) by Ralf Becker <RalfBecker@outdoor-training.de>          *
* --------------------------------------------                             *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

if (isset($_SERVER['DOCUMENT_ROOT'])) die('Commanline only!!!');

$rock_db_path = '/usr/rock_db';

if ($_SERVER['argc'] <= 2)
{
	$rock_file = 'php://stdout';
}
else
{
	list($route,$comp) = explode('.',$_SERVER['argv'][2]);
	$rock_file = $rock_db_path.'/'.$comp.'/~'.$route.'.rte';
}

$csv_file = $_SERVER['argc'] <= 1 ? 'php://stdin' : $_SERVER['argv'][1];

if (!($f = @fopen($csv_file,'r')))
{
	echo "File '$csv_file' not found!!!\n";
	die("Usage: csv2rock [csv-file [route.wand]]\n");
}

if (!($csv_fields = fgetcsv($f,null,';')) || count($csv_fields) <= 1)
{
	die("File '$csv_file' is NO csv file!!!\n");
}
print_r($csv_fields);

if (!($fr = @fopen($rock_file,'w')))
{
	die("Can't open '$rock_file' for writing!!!\n");
}

if (in_array('boulder4',$csv_fields))
{
	// boulder competition
	$num_problems = in_array('boulder6',$csv_fields) ? 6 : (in_array('boulder5',$csv_fields) ? 5 : 4);
}

$max_place = 0;
while(($line = fgetcsv($f,null,';')))
{
	$lines[] = $line = array_combine($csv_fields,$line);
	if ($line['place'] > $max_place) $max_place = $line['place'];
}
fclose($f);

// sort by abs_platz
usort($lines,create_function('$a,$b','$aplace=$a["place"]?$a["place"]:9000+$a["startorder"]; $bplace=$b["place"]?$b["place"]:9000+$b["startorder"]; return ($aplace-$bplace) ? $aplace-$bplace : strcasecmp($a["lastname"].$a["firstname"],$b["lastname"].$b["firstname"]);'));
foreach($lines as $key => $line)
{
	$lines[$key]['abs_platz'] = 1 + $key;
}

usort($lines,create_function('$a,$b','return $a["startorder"]-$b["startorder"];'));
print_r($lines);

foreach($lines as $line)
{
	if (!$line['place']) $line['place'] = $max_place + 1;

	fwrite($fr,($line['startnumber']?$line['startnumber']:$line['startorder']).
		"\t0\t0\t0\t0\t".	// ToDo write the real result
		($line['place'] ? $line['place'] : $max_place+1)."\t".$line['abs_platz']."\n".
		$line["lastname"]."\n".$line['firstname']."\n\n".$line['nation']."\n\n".$line['athlete']."\n\n");
	
	// ToDo write sub-routes for each bouler-problem with boulder-result
}
fclose($fr);