<?php

/**
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

is_defined('BASEPATH') || define('BASEPATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
require_once BASEPATH . "lib" . DIRECTORY_SEPARATOR . "_autoload.php";

use \RestService\Http\HttpResponse as HttpResponse;
use \RestService\Utils\Config as Config;
use \RestService\Http\IncomingHttpRequest as IncomingHttpRequest;
use \RestService\Http\HttpRequest as HttpRequest;
use \OAuth\Token as Token;
use \RestService\Utils\Logger as Logger;
use \RestService\Utils\Json as Json;

$logger = NULL;
$request = NULL;
$response = NULL;

try {
    // ensure paths
    $cfgVars = array(
        'rootdir' => BASEPATH,
        'libdir' => BASEPATH . DIRECTORY_SEPARATOR . 'lib',
        'wwwdir' => __DIR__
    );

    $config = new Config(BASEPATH . "config" . DIRECTORY_SEPARATOR . "oauth.ini", $cfgVars);
    $logger = new Logger($config->getSectionValue('Log', 'logLevel'),
        $config->getValue('serviceName'),
        $config->getSectionValue('Log', 'logFile'),
        $config->getSectionValue('Log', 'logMail', FALSE));

    $t = new Token($config, $logger);
    $request = HttpRequest::fromIncomingHttpRequest(new IncomingHttpRequest());
    $response = $t->handleRequest($request);
} catch (Exception $e) {
    $response = new HttpResponse(500, "application/json");
    $response->setContent(Json::enc(array("error" => $e->getMessage())));
    if (NULL !== $logger) {
        $logger->logFatal($e->getMessage() . PHP_EOL . $request . PHP_EOL . $response);
    }
}

if (NULL !== $logger) {
    $logger->logDebug($request);
}
if (NULL !== $logger) {
    $logger->logDebug($response);
}
if (NULL !== $response) {
    $response->sendResponse();
}
