<?php

return array(
	'node1' => array(
		'id'		=> 1,
		'name'		=> 'a_root1',
		'parent_id'	=> null,
		'path'		=> '1:',
	),
	'node2' => array(
		'id'		=> 2,
		'name'		=> 'b_root2',
		'parent_id'	=> null,
		'path'		=> '2:',
	),
	'node3' => array(
		'id'		=> 3,
		'name'		=> 'a_root1_sublev1_1',
		'parent_id'	=> 1,
		'path'		=> '1:3:',
	),
	'node4' => array(
		'id'		=> 4,
		'name'		=> 'b_root1_sublev2',
		'parent_id'	=> 3,
		'path'		=> '1:3:4:',
	),
	'node5' => array(
		'id'		=> 5,
		'name'		=> 'c_root1_sublev3',
		'parent_id'	=> 4,
		'path'		=> '1:3:4:5:',
	),
	'node6' => array(
		'id'		=> 6,
		'name'		=> 'b_root2_sublev1',
		'parent_id'	=> 2,
		'path'		=> '2:6:',
	),
	'node7' => array(
		'id'		=> 7,
		'name'		=> 'a_root1_sublev1_2',
		'parent_id'	=> 1,
		'path'		=> '1:7:',
	),
	'node8' => array(
		'id'		=> 8,
		'name'		=> 'a_root1_sublev1_3',
		'parent_id'	=> 1,
		'path'		=> '1:8:',
	),
	'node9' => array(
		'id'		=> 9,
		'name'		=> 'a_root2_sublev1',
		'parent_id'	=> 2,
		'path'		=> '2:9:',
	),
);