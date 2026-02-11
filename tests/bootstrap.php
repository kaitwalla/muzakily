<?php

/*
|--------------------------------------------------------------------------
| Test Bootstrap
|--------------------------------------------------------------------------
|
| This file sets up the testing environment before Laravel loads.
| It ensures tests use a separate database from development.
|
*/

// Force testing environment BEFORE Laravel loads .env
$_ENV['APP_ENV'] = 'testing';
$_SERVER['APP_ENV'] = 'testing';

// Force test database to prevent wiping dev data
$_ENV['DB_DATABASE'] = 'muzakily_test';
$_SERVER['DB_DATABASE'] = 'muzakily_test';

require __DIR__ . '/../vendor/autoload.php';
