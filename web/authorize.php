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
use fkooman\OAuth\Server\Authorize;
use fkooman\Http\Request;
use fkooman\Http\Response;
use fkooman\Http\IncomingRequest;

try {
    $config = Config::fromIniFile(
        dirname(__DIR__)."/config/oauth.ini"
    );
    $authorize = new Authorize($config);
    $request = Request::fromIncomingRequest(new IncomingRequest());
    $response = $authorize->handleRequest($request);
    $response->sendResponse();
} catch (Exception $e) {
    // internal server error, inform resource owner through browser
    $response = new Response(500);
    $loader = new Twig_Loader_Filesystem(
        dirname(__DIR__)."/views"
    );
    $twig = new Twig_Environment($loader);
    $output = $twig->render(
        "error.twig",
        array(
            "statusCode" => $response->getStatusCode(),
            "statusReason" => $response->getStatusReason(),
            "errorMessage" => $e->getMessage(),
        )
    );
    $response->setContent($output);
    $response->sendResponse();
}
