<?php
$vendorDir = '/usr/share/php';
$pearDir   = '/usr/share/pear';
$baseDir   = dirname(__DIR__);

require_once $vendorDir . '/Symfony/Component/ClassLoader/UniversalClassLoader.php';

use Symfony\Component\ClassLoader\UniversalClassLoader;

$loader = new UniversalClassLoader();
$loader->registerNamespaces(array(
    'fkooman\\OAuth\\Server'   => $baseDir . '/src',
    'fkooman\\Rest'            => $vendorDir,
    'fkooman\\OAuth\\Common'   => $vendorDir,
    'fkooman\\Json'            => $vendorDir,
    'fkooman\\Http'            => $vendorDir,
    'fkooman\\Config'          => $vendorDir,
    'Rhumsaa\\Uuid'            => $vendorDir,
    'Symfony\\Component\\Yaml' => $vendorDir,
));
$loader->registerPrefixes(array(
    'Twig_'               => array($pearDir, $vendorDir),
));

$loader->register();
