#!/usr/bin/env php

use EGroupware\Ranking\Calculation;

<?php

$_REQUEST['domain'] = php_uname('n') == 'RalfsMac.local' ? 'boulder.egroupware.org' :
	(substr(php_uname('n'), 0, 4) == 'fpm-' ? 'www.digitalrock.de' : 'default');
$GLOBALS['egw_info']['flags'] = array('currentapp' => 'login');
require(__DIR__.'/../../header.inc.php');

$result = new Calculation();

//$GLOBALS['egw_info']['server']['temp_dir'] = __DIR__;
//Calculation::$dump_ranking_results = true;
$failed = $successful = 0;
foreach(scandir($fixtures=__DIR__.'/fixtures') as $file)
{
	if (!preg_match('/^'.basename(__FILE__, '.php').'.+\.php$/', $file)) continue;

	require($fixtures.'/'.$file);
	echo basename($file, '.php').': ';
	$stand = $input['stand'];
	$start = $ret_pers = $rls = $ret_ex_aquo = $not_counting = $max_comp = null;
	$rang = $result->ranking($input['cat'], $stand, $start, $input['comp'], $ret_pers, $rls, $ret_ex_aquo, $not_counting, $input['cup'], $input['comps'], $max_comp, $input['results']);
	$r = array(
		'stand' => $stand,
		'rang' => $rang,
		'ret_pers' => $ret_pers,
		'ret_ex_aquo' => $ret_ex_aquo,
		'not_counting' => $not_counting,
		'rls'   => $rls,
		'max_comp' => $max_comp,
	);

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
			if ($n == 'rang')
			{
				foreach($data as $key => $d)
				{
					if ($d['PerId'] != $results[$n][$key]['PerId'])
					{
						echo "$n/$key ({$d['PerId']}): athlete $d[PerId] != ".$results[$n][$key]['PerId']."\n";
					}
					elseif ($d['platz'] != $results[$n][$key]['platz'])
					{
						echo "$n/$key ({$d['PerId']}): rank $d[platz] != ".$results[$n][$key]['platz']."\n";
					}
					else
					{
						echo "$n/$key ({$d['PerId']}): rank $d[platz] == ".$results[$n][$key]['platz']."\n";
					}
				}
			}
			if ($data != $results[$n])
			{
				if (is_array($data))
				{
					echo "$n: ".json_encode(compute_array_diff($data, $results[$n]))."\n";
				}
				else
				{
					echo "$n: ".array2string($data)." != ".array2string($results[$n])."\n";
				}
			}
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