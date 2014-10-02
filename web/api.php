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
use fkooman\Http\JsonResponse;
use fkooman\Http\IncomingRequest;
use fkooman\OAuth\Server\PdoStorage;

try {
    $config = Config::fromIniFile(
        dirname(__DIR__)."/config/oauth.ini"
    );
    $api = new Api(new PdoStorage($config));
    $request = Request::fromIncomingRequest(new IncomingRequest());
    $response = $api->handleRequest($request);
    $response->sendResponse();
} catch (Exception $e) {
    $response = new JsonResponse(500);
    $response->setContent(
        array(
            "error" => "internal_server_error",
            "error_description" => $e->getMessage(),
        )
    );
    $response->sendResponse();
}
