<?php

$data =  array(
  'work_item_a' => array(
    'is_php' => true,
    'name' => 'work item related to kimber_test!',
    'users' => array(
      'repository' => 'user',
      'foreign_alias' => 'work_items',
      'type' => 'one',
      'foreign_type' => 'many',
      'values' => array(
        'tester',
      ),
    ),
  ),

  'work_item_b' => array(
    'name' => 'work item related to another_test!',
  ),

  'work_item_c' => array(
    'name' => 'work item related to nothing',
  ),
);
