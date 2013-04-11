<?php

require_once 'lib/SplClassLoader.php';
$c1 = new SplClassLoader("RestService", "extlib/php-rest-service/lib");
$c1->register();
$c2 =  new SplClassLoader("OAuth", "lib");
$c2->register();

use \RestService\Utils\Config as Config;
use \OAuth\PdoOAuthStorage as PdoOAuthStorage;
use \OAuth\ClientRegistration as ClientRegistration;
use \RestService\Utils\Json as Json;

$config = new Config(dirname(__DIR__) . DIRECTORY_SEPARATOR . "config" . DIRECTORY_SEPARATOR . "oauth.ini");
$storage = new PdoOAuthStorage($config);

if ($argc !== 2) {
        echo "ERROR: please specify file with client registration information" . PHP_EOL;
        die();
}

$registrationFile = $argv[1];
if (!file_exists($registrationFile) || !is_file($registrationFile) || !is_readable($registrationFile)) {
        echo "ERROR: unable to read client registration file" . PHP_EOL;
        die();
}

$registration = Json::dec(file_get_contents($registrationFile));

foreach ($registration as $r) {
    // first load it in ClientRegistration object to check it...
    $cr = ClientRegistration::fromArray($r);
    if (FALSE === $storage->getClient($cr->getId())) {
        // does not exist yet, install
        echo "Adding '" . $cr->getName() . "'..." . PHP_EOL;
        $storage->addClient($cr->getClientAsArray());
    }
}
