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
use fkooman\Json\Json;
use fkooman\OAuth\Server\Token;

use RestService\Http\HttpResponse;
use RestService\Http\IncomingHttpRequest;
use RestService\Http\HttpRequest;

$request = NULL;
$response = NULL;

try {
    $config = Config::fromIniFile(dirname(__DIR__) . DIRECTORY_SEPARATOR . "config" . DIRECTORY_SEPARATOR . "oauth.ini");

    $t = new Token($config);
    $request = HttpRequest::fromIncomingHttpRequest(new IncomingHttpRequest());
    $response = $t->handleRequest($request);
} catch (Exception $e) {
    $response = new HttpResponse(500, "application/json");
    $response->setContent(Json::encode(array("error" => $e->getMessage())));
}

if (NULL !== $response) {
    $response->sendResponse();
}
