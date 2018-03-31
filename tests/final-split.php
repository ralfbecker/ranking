#!/usr/bin/env php
<?php

$_REQUEST['domain'] = 'boulder.egroupware.org';
$GLOBALS['egw_info']['flags'] = array('currentapp' => 'login');
require(__DIR__.'/../../header.inc.php');

$result = new ranking_route_result();

foreach(scandir(__DIR__) as $file)
{
	if (!preg_match('/^'.basename(__FILE__, '.php').'.+\.php$/', $file)) continue;

	require(__DIR__.'/'.$file);
	echo basename($file, '.php').': ';
	$r = $input;
	$result->boulder2018_final_tie_breaking($r, ['WetId' => 0, 'GrpId' => 0, 'route_order' => 0], 5);

	if ($r == $results)
	{
		echo "Test successful :)\n";
	}
	else
	{
		//var_dump('input', $input, 'result', $r, 'expected', $results);

		echo "\n";
		foreach($r as $n => $data)
		{
			if ($data['new_rank'] != $results[$n]['new_rank'])
			{
				echo "$n ({$results[$n]['PerId']}): rank $data[new_rank] != ".$results[$n]['new_rank'];
			}
			else
			{
				echo "$n ({$results[$n]['PerId']}): rank $data[new_rank] == ".$results[$n]['new_rank'];
			}
			if ($data != $results[$n])
			{
				echo ": ".json_encode(compute_array_diff($data, $results[$n]));
			}
			echo "\n";
		}
		echo "\nTest failed :(\n\n";
	}
}

function compute_array_diff($got, $expected)
{
	$diff = array();
	foreach(array_merge(array_keys($got), array_keys($expected)) as $name)
	{
		if ($got[$name] != $expected[$name])
		{
			if (is_array($got[$name]))
			{
				$diff[$name] = compute_array_diff($got[$name], $expected[$name]);
			}
			else
			{
				$diff[$name]['got'] = $got[$name];
				$diff[$name]['expected'] = $expected[$name];
			}
		}
	}
	return $diff;
}