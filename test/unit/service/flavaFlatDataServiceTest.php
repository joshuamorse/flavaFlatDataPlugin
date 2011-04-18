<?php

include dirname(__FILE__) . '/../../bootstrap/unit.php';

$t = new lime_test(20, new lime_output_color());

$repositoriesPath = dirname(__FILE__) . '/../../config/data/';
$loaderClass = 'flavaFlatPhpDataLoader';
$loaderService = new $loaderClass();

$ffd = new flavaFlatDataService($repositoriesPath, $loaderService);

$t->info('get a repository');
  $users = $ffd
    ->getRepository('user')
    ->execute()
  ;

  $t->is(is_array($users), true, 'repo returns an array');
  $t->is(count($users), 4, 'repo has correct amount of records');


$t->info('get a single record');
  $user = $ffd
    ->getRepository('user')
    ->getRecord('mr_admin')
    ->execute()
  ;

  $t->is(is_array($user), true, 'service fetches single record');
  $t->is($user['name'], 'mr admin', 'repo has correct info');


$t->info('get a single filtered record');
  $user = $ffd
    ->getRepository('user')
    ->filter('name', '==', 'joe')
    ->execute()
  ;

  $user = current($user);

  $t->is(is_array($user), true, 'service fetches single record');
  $t->is($user['name'], 'joe', 'repo has correct info');


$t->info('get a repository defined by integer IDs');

  $projects = $ffd
    ->getRepository('project')
    ->execute();
  ;

  $t->is(is_array($projects), true, 'repo is an array');
  $t->is(count($projects), 11, 'repo has correct number of records');


$t->info('get a record by id');

  $project = $ffd
    ->getRepository('project')
    ->getRecord(2)
    ->execute();
  ;

  $t->is(is_array($project), true, 'record is an array');
  $t->is($project['name'], 'project 2', 'repo has correct info');


$t->info('some more filtering');

  $projects = $ffd
    ->getRepository('project')
    ->filter('some_value', '<', '30')
    ->execute();
  ;

  $t->is(is_array($projects), true, 'record is an array');
  $t->is(count($projects), 10, 'filter returns correct amount of records');

  $projects = $ffd
    ->getRepository('project')
    ->filter('some_value', '<', '10')
    ->execute();
  ;

  $t->is(is_array($projects), true, 'record is an array');
  $t->is(count($projects), 4, 'filter returns correct amount of records');

  $projects = $ffd
    ->getRepository('project')
    ->filter('some_value', '>', '1000')
    ->execute();
  ;

  $t->is(is_array($projects), true, 'record is an array');
  $t->is(count($projects), 0, 'filter returns correct amount of records');


$t->info('local relations');
  $user = $ffd
    ->getRepository('user')
    ->getRecord('mr_admin')
    ->execute();
  ;

  $t->is(is_array($user), true, 'record is an array');
  $t->is(count($user['projects']), 4, 'relation has correct amount of records');


$t->info('foreign relations');
  $user = $ffd
    ->getRepository('user')
    ->getRecord('mr_admin')
    ->execute();
  ;

  $t->is(is_array($user), true, 'record is an array');
  $t->is(count($user['projects']), 4, 'relation has correct amount of records');
