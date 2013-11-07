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

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . "vendor" . DIRECTORY_SEPARATOR . "autoload.php";

use fkooman\Config\Config;

use RestService\Utils\Logger;
use RestService\Http\HttpRequest;
use RestService\Http\IncomingHttpRequest;
use OAuth\Authorize;
use RestService\Http\HttpResponse;

$logger = NULL;
$request = NULL;
$response = NULL;

try {
    $config = Config::fromIniFile(dirname(__DIR__) . DIRECTORY_SEPARATOR . "config" . DIRECTORY_SEPARATOR . "oauth.ini");
    $logger = new Logger($config->s('Log')->l('logLevel'), $config->getValue('serviceName'), $config->s('Log')->l('logFile'), $config->s('Log')->l('logMail', false));

    $a = new Authorize($config, $logger);
    $request = HttpRequest::fromIncomingHttpRequest(new IncomingHttpRequest());
    $response = $a->handleRequest($request);

} catch (Exception $e) {
    // internal server error, inform resource owner through browser
    $response = new HttpResponse(500);
    $loader = new \Twig_Loader_Filesystem(dirname(__DIR__) . DIRECTORY_SEPARATOR . "views");
    $twig = new \Twig_Environment($loader);
    $output = $twig->render("error.twig", array (
        "statusCode" => $response->getStatusCode(),
        "statusReason" => $response->getStatusReason(),
        "errorMessage" => $e->getMessage()
    ));
    $response->setContent($output);

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
