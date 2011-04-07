<?php

include dirname(__FILE__) . '/../../bootstrap/unit.php';

$t = new lime_test(11, new lime_output_color());

$repositoriesPath = dirname(__FILE__) . '/../../config/data/';
$loaderClass = 'flavaFlatPhpDataLoader';
$loaderService = new $loaderClass();

$ffd = new flavaFlatDataService($repositoriesPath, $loaderService, true, true);


$work_items = $ffd
  ->getRepository('work_item')
  ->execute()
;

$t->is(is_array($work_items), true, 'repo returns an array');
$t->is(count($work_items), 3, 'repo has correct amount of records');


$work_item_a = $ffd
  ->getRepository('work_item')
  ->getRecord('work_item_a')
  ->execute()
;

$t->is(is_array($work_item_a), true, 'repo record fetch returns an array');
$t->is(array_key_exists('is_php', $work_item_a), true, 'is_php flag exists in array');
$t->is(array_key_exists('project', $work_item_a), true, 'relational project key exists');
$t->is(is_array($work_item_a['project']), true, 'project is array');
$t->is($work_item_a['project']['kimber_test']['name'], true, 'project has name data');



$projects = $ffd
  ->getRepository('project')
  ->execute()
;

$t->is(is_array($projects), true, 'repo returns an array');
$t->is(count($projects), 2, 'repo has correct amount of records');


$projects = $ffd
  ->getRepository('project')
  ->getRecord('kimber_test')
  ->execute()
;

$t->is(is_array($projects), true, 'repo returns an array');
$t->is(array_key_exists('work_items', $projects), true, 'relational work_items key exists');
//$t->is(in_array('repository', $ffd['work_items']), null, 'relation definition does not exist');
