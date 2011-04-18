<?php

$data = array();

for ($i = 0; $i < 10; ++$i)
{
  $data[$i] = array(
    'name' => 'project ' . $i,
    'some_value' => ($i * 3),
  );
}

$data['real_project_1'] = array(
  'name' => 'real project 1!',
  'some_value' => '90',
  'manager' => array(
    'repository' => 'user',
    'foreign_alias' => 'managed_projects',
    'values' => array(
      'mr_admin',
    ),
  ),
  'users' => array(
    'repository' => 'user', 
    'foreign_alias' => 'projects', 
    'values' => array(
      'mr_admin',
      'joe',
      'bob',
    ),
  ),
);
