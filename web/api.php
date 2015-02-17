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

require_once dirname(__DIR__).'/vendor/autoload.php';

use fkooman\Ini\IniReader;
use fkooman\OAuth\Server\ApiService;
use fkooman\OAuth\Server\PdoStorage;
use fkooman\Http\Exception\HttpException;
use fkooman\Http\Exception\InternalServerErrorException;
use fkooman\Rest\Plugin\Bearer\BearerAuthentication;
use fkooman\Http\Request;
use fkooman\Http\IncomingRequest;

set_error_handler(
    function ($errno, $errstr, $errfile, $errline) {
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    }
);

try {
    $iniReader = IniReader::fromFile(
        dirname(__DIR__).'/config/oauth.ini'
    );

    $db = new PDO(
        $iniReader->v('PdoStorage', 'dsn'),
        $iniReader->v('PdoStorage', 'username', false),
        $iniReader->v('PdoStorage', 'password', false)
    );

    $apiService = new ApiService(
        new PdoStorage($db)
    );

    // we want to automatically determine the 'introspect.php' URI based on
    // the current request URI. It is a bit of a hack, but it works assuming
    // a valid TLS certificate is configured on the introspect endpoint in
    // case the original request URI is over TLS...
    $request = Request::fromIncomingRequest(
        new IncomingRequest()
    );
    $baseUri = $request->getRequestUri()->getBaseUri();
    $introspectUri = $baseUri . $request->getAppRoot() . 'introspect.php';

    $apiService->registerBeforeEachMatchPlugin(
        new BearerAuthentication(
            $introspectUri,
            'OAuth Management API'
        )
    );

    $apiService->run()->sendResponse();
} catch (Exception $e) {
    if ($e instanceof HttpException) {
        error_log(
            sprintf(
                'M: %s, D: %s',
                $e->getMessage(),
                $e->getDescription()
            )
        );
        $response = $e->getJsonResponse();
    } else {
        // we catch all other (unexpected) exceptions and return a 500
        error_log($e->getTraceAsString());
        $e = new InternalServerErrorException($e->getMessage());
        $response = $e->getJsonResponse();
    }
    $response->sendResponse();
}
