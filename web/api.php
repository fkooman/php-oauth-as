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

require_once dirname(__DIR__)."/vendor/autoload.php";

use fkooman\Config\Config;
use fkooman\OAuth\Server\Api;
use fkooman\Http\Request;
use fkooman\Http\IncomingRequest;
use fkooman\OAuth\Server\PdoStorage;
use fkooman\Http\Exception\HttpException;
use fkooman\Http\Exception\InternalServerErrorException;

try {
    $config = Config::fromIniFile(
        dirname(__DIR__)."/config/oauth.ini"
    );
    $api = new Api(new PdoStorage($config), 'http://localhost/php-oauth-as/introspect.php');
    $request = Request::fromIncomingRequest(new IncomingRequest());
    $response = $api->run($request);
    $response->sendResponse();
} catch (Exception $e) {
    if ($e instanceof HttpException) {
        $response = $e->getJsonResponse();
    } else {
        // we catch all other (unexpected) exceptions and return a 500
        error_log($e->getTraceAsString());
        $e = new InternalServerErrorException($e->getMessage());
        $response = $e->getJsonResponse();
    }
    $response->sendResponse();
}
