<?php
// 3 tied on first place, no head-to-head --> countback to seeding list (best one starts last --> higher start_order)
$input = array(
	array('PerId' => 2, 'quali_points' => 6, 'result_rank0' => 2, 'result_rank1' => 3, 'result_rank2' => 1, 'start_order' => 50),
	array('PerId' => 1, 'quali_points' => 6, 'result_rank0' => 1, 'result_rank1' => 2, 'result_rank2' => 3, 'start_order' => 100),
	array('PerId' => 3, 'quali_points' => 6, 'result_rank0' => 3, 'result_rank1' => 1, 'result_rank2' => 2, 'start_order' => 1),
);
$quali_overall = 0;

$results = array(
	array('PerId' => 1, 'quali_points' => 6, 'result_rank0' => 1, 'result_rank1' => 2, 'result_rank2' => 3, 'start_order' => 100, 'result_rank' => 1),
	array('PerId' => 2, 'quali_points' => 6, 'result_rank0' => 2, 'result_rank1' => 3, 'result_rank2' => 1, 'start_order' => 50, 'result_rank' => 2),
	array('PerId' => 3, 'quali_points' => 6, 'result_rank0' => 3, 'result_rank1' => 1, 'result_rank2' => 2, 'start_order' => 1, 'result_rank' => 3),
);