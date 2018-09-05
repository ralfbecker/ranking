<?php
// 2 tied on second place, head-to-head allowed but unsuccessful, because of ex aquo --> broken by seeding list
$input = array(
	array('PerId' => 1, 'quali_points' => 1, 'result_rank0' => 1, 'result_rank1' => 1, 'result_rank2' => 1, 'start_order' => 100),
	array('PerId' => 2, 'quali_points' => 24, 'result_rank0' => 2, 'result_rank1' => 3, 'result_rank2' => 4, 'start_order' => 50),
	array('PerId' => 3, 'quali_points' => 24, 'result_rank0' => 3, 'result_rank1' => 2, 'result_rank2' => 4, 'start_order' => 1),
	array('PerId' => 4, 'quali_points' => 120, 'result_rank0' => 4, 'result_rank1' => 5, 'result_rank2' => 6, 'start_order' => 10),
);
$quali_overall = 0;

$results = array(
	array('PerId' => 1, 'quali_points' => 1, 'result_rank0' => 1, 'result_rank1' => 1, 'result_rank2' => 1, 'start_order' => 100, 'result_rank' => 1),
	array('PerId' => 2, 'quali_points' => 24, 'result_rank0' => 2, 'result_rank1' => 3, 'result_rank2' => 4, 'start_order' => 50, 'result_rank' => 2),
	array('PerId' => 3, 'quali_points' => 24, 'result_rank0' => 3, 'result_rank1' => 2, 'result_rank2' => 4, 'start_order' => 1, 'result_rank' => 3),
	array('PerId' => 4, 'quali_points' => 120, 'result_rank0' => 4, 'result_rank1' => 5, 'result_rank2' => 6, 'start_order' => 10, 'result_rank' => 4),
);