<?php

require_once 'lib/SplClassLoader.php';
$c1 = new SplClassLoader("RestService", "extlib/php-rest-service/lib");
$c1->register();
$c2 =  new SplClassLoader("OAuth", "lib");
$c2->register();

use \RestService\Utils\Config as Config;
use \OAuth\PdoOAuthStorage as PdoOAuthStorage;

$config = new Config(dirname(__DIR__) . DIRECTORY_SEPARATOR . "config" . DIRECTORY_SEPARATOR . "oauth.ini");

$storage = new PdoOAuthStorage($config);
$sql = file_get_contents('schema/db.sql');
$storage->dbQuery($sql);
