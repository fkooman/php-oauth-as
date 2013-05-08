<?php

require_once __DIR__ . DIRECTORY_SEPARATOR . 'SplClassLoader.php';

$load = array(
    "RestService" => dirname(__DIR__) . DIRECTORY_SEPARATOR . "extlib" . DIRECTORY_SEPARATOR . "php-rest-service" . DIRECTORY_SEPARATOR . "lib",
    "OAuth" => dirname(__DIR__) . DIRECTORY_SEPARATOR . "lib"
);

foreach ($load as $k => $v) {
    $c = new SplClassLoader($k, $v);
    $c->register();
}
