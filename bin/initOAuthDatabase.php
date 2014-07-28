<?php

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . "vendor" . DIRECTORY_SEPARATOR . "autoload.php";

use fkooman\Config\Config;

use fkooman\OAuth\Server\PdoOAuthStorage;

$config = Config::fromIniFile(dirname(__DIR__) . DIRECTORY_SEPARATOR . "config" . DIRECTORY_SEPARATOR . "oauth.ini");

$storage = new PdoOAuthStorage($config);
$sql = file_get_contents('schema/db.sql');
$storage->dbQuery($sql);
