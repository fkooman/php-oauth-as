<?php

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . "vendor" . DIRECTORY_SEPARATOR . "autoload.php";

use fkooman\Config\Config;
use fkooman\OAuth\Server\PdoOAuthStorage;

try {
    $config = Config::fromIniFile(dirname(__DIR__) . DIRECTORY_SEPARATOR . "config" . DIRECTORY_SEPARATOR . "oauth.ini");
    $storage = new PdoOAuthStorage($config);
    $storage->initDatabase();
} catch (Exception $e) {
    echo $e->getMessage() . PHP_EOL;
    exit(1);
}
