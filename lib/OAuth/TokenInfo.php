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

namespace OAuth;

use \RestService\Utils\Config as Config;
use \RestService\Http\HttpRequest as HttpRequest;
use \RestService\Http\HttpResponse as HttpResponse;
use \RestService\Utils\Logger as Logger;

class TokenInfo
{
    private $_config;
    private $_logger;

    private $_as;

    public function __construct(Config $c, Logger $l = NULL)
    {
        $this->_config = $c;
        $this->_logger = $l;

        $oauthStorageBackend = 'OAuth\\' . $this->_config->getValue('storageBackend');
        $storage = new $oauthStorageBackend($this->_config);

        $this->_as = new AuthorizationServer($storage, $this->_config);
    }

    public function handleRequest(HttpRequest $request)
    {
        $response = new HttpResponse();
        try {
            if ("GET" !== $request->getRequestMethod()) {
                // method not allowed
                $response->setStatusCode(405);
                $response->setHeader("Allow", "GET");
            } else {
                $result = $this->_as->tokenInfo($request->getQueryParameters());
                $response->setContentType("application/json");
                $response->setHeader('Content-Type', 'application/json');
                $response->setHeader('Cache-Control', 'no-store');
                $response->setHeader('Pragma', 'no-cache');

                $tokenInfo = array (
                    "audience" => $result['client_id'],
                    "client_id" => $result['client_id'],
                    "user_id" => $result['resource_owner_id'],
                    "resource_owner_id" => $result['resource_owner_id'],
                    "scope" => $result['scope'],
                    "expires_in" => $result['issue_time'] + $result['expires_in'] - time(),
                    "attributes" => $result['resource_owner_attributes']);
                $response->setContent(json_encode($tokenInfo));
            }
        } catch (TokenInfoException $e) {
            $response->setStatusCode(400);
            $response->setContentType("application/json");
            $response->setHeader('Cache-Control', 'no-store');
            $response->setHeader('Pragma', 'no-cache');
            $response->setContent(json_encode(array("error" => $e->getMessage(), "error_description" => $e->getDescription())));
            if (NULL !== $this->_logger) {
                $this->_logger->logFatal($e->getLogMessage(TRUE) . PHP_EOL . $request . PHP_EOL . $response);
            }
        }

        return $response;
    }

}
