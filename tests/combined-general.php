#!/usr/bin/env php
<?php

$_REQUEST['domain'] = php_uname('n') == 'RalfsMac.local' ? 'boulder.egroupware.org' :
	(substr(php_uname('n'), 0, 4) == 'fpm-' ? 'www.digitalrock.de' : 'default');
$GLOBALS['egw_info']['flags'] = array('currentapp' => 'login');
require(__DIR__.'/../../header.inc.php');

$result = new ranking_route_result();

$failed = $successful = 0;
foreach(scandir($fixtures=__DIR__.'/fixtures') as $file)
{
	if (!preg_match('/^'.basename(__FILE__, '.php').'.+\.php$/', $file)) continue;

	require($fixtures.'/'.$file);
	echo basename($file, '.php').': ';
	$r = $input;
	$result->do_combined_general_result($r, $quali_overall, array(), $qualification);

	if ($r == $results)
	{
		echo "Test successful :)\n";
		$successful++;
	}
	else
	{
		//var_dump('input', $input, 'result', $r, 'expected', $results);

		echo "\n";
		foreach($r as $n => $data)
		{
			if ($data['result_rank'] != $results[$n]['result_rank'])
			{
				echo "$n ({$results[$n]['PerId']}): rank $data[new_rank] != ".$results[$n]['result_rank'];
			}
			else
			{
				echo "$n ({$results[$n]['PerId']}): rank $data[new_rank] == ".$results[$n]['result_rank'];
			}
			if ($data != $results[$n])
			{
				echo ": ".json_encode(compute_array_diff($data, $results[$n]));
			}
			echo "\n";
		}
		echo "\nTest failed :(\n\n";
		$failed++;
	}
}
echo basename(__FILE__, '.php').": $successful Test successful, $failed Tests failed.\n";

exit($failed);

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