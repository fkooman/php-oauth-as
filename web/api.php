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
use Guzzle\Http\Client;

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

    // HTTP CLIENT
    $disableServerCertCheck = $iniReader->v('disableServerCertCheck', false, false);

    $client = new Client(
        '',
        array(
            'ssl.certificate_authority' => !$disableServerCertCheck
        )
    );

    // we want to automatically determine the 'introspect.php' URI based on
    // the current request URI. It is a bit of a hack, but it works assuming
    // a valid TLS certificate is configured on the introspect endpoint in
    // case the original request URI is over TLS...
    $request = Request::fromIncomingRequest(
        new IncomingRequest()
    );

    $apiService->registerOnMatchPlugin(
        new BearerAuthentication(
            dirname($request->getAbsRoot()) . '/introspect.php',
            'OAuth Management API',
            $client
        )
    );

    $apiService->run($request)->sendResponse();
} catch (Exception $e) {
    error_log($e->getMessage());
    ApiService::handleException($e)->sendResponse();
}
