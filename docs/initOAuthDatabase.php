<?php

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . "vendor" . DIRECTORY_SEPARATOR . "autoload.php";

use \RestService\Utils\Config as Config;
use \OAuth\PdoOAuthStorage as PdoOAuthStorage;

$config = new Config(dirname(__DIR__) . DIRECTORY_SEPARATOR . "config" . DIRECTORY_SEPARATOR . "oauth.ini");

$storage = new PdoOAuthStorage($config);
$sql = file_get_contents('schema/db.sql');
$storage->dbQuery($sql);
