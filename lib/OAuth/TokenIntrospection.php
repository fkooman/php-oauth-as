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
use \RestService\Utils\Json as Json;

class TokenIntrospection
{
    private $_config;
    private $_logger;
    private $_storage;

    public function __construct(Config $c, Logger $l = NULL)
    {
        $this->_config = $c;
        $this->_logger = $l;

        $oauthStorageBackend = 'OAuth\\' . $this->_config->getValue('storageBackend');
        $this->_storage = new $oauthStorageBackend($this->_config);
    }

    public function handleRequest(HttpRequest $request)
    {
        $response = NULL;

        try {
            $requestMethod = $request->getRequestMethod();

            if ("GET" !== $requestMethod && "POST" !== $requestMethod) {
                throw new TokenIntrospectionException("method_not_allowed", "invalid request method");
            }
            $parameters = "GET" === $requestMethod ? $request->getQueryParameters() : $request->getPostParameters();

            $response = new HttpResponse(200, "application/json");
            $response->setHeader('Cache-Control', 'no-store');
            $response->setHeader('Pragma', 'no-cache');
            $response->setContent(Json::enc($this->_introspectToken($parameters)));
        } catch (TokenIntrospectionException $e) {
            $response = new HttpResponse($e->getResponseCode(), "application/json");
            $response->setContent(Json::enc(array("error" => $e->getMessage(), "error_description" => $e->getDescription())));
            if ("method_not_allowed" === $e->getMessage()) {
                $response->setHeader("Allow", "GET,POST");
            }
            if (NULL !== $this->_logger) {
                $this->_logger->logFatal($e->getLogMessage(TRUE) . PHP_EOL . $request . PHP_EOL . $response);
            }
        }

        return $response;
    }

    /**
     * Implementation of https://tools.ietf.org/html/draft-richer-oauth-introspection
     */
    private function _introspectToken(array $param)
    {
        $r = array();

        $token = Utils::getParameter($param, 'token');
        if (NULL === $token) {
            throw new TokenIntrospectionException("invalid_token", "the token parameter is missing");
        }
        $accessToken = $this->_storage->getAccessToken($token);
        if (FALSE === $accessToken) {
            // token does not exist
            $r['active'] = FALSE;
        } elseif (time() > $accessToken['issue_time'] + $accessToken['expires_in']) {
            // token expired
            $r['active'] = FALSE;
        } else {
            // token exists and did not expire
            $r['active'] = TRUE;
            $r['exp'] = intval($accessToken['issue_time'] + $accessToken['expires_in']);
            $r['iat'] = intval($accessToken['issue_time']);
            $r['scope'] = $accessToken['scope'];
            $r['client_id'] = $accessToken['client_id'];
            $r['sub'] = $accessToken['resource_owner_id'];
            $r['token_type'] = 'bearer';

            // as long as we have no RS registration we cannot set the audience...
            // $response['aud'] = 'foo';

            // add proprietary "x-entitlement"
            $resourceOwner = $this->_storage->getResourceOwner($accessToken['resource_owner_id']);
            if (isset($resourceOwner['entitlement'])) {
                $e = Json::dec($resourceOwner['entitlement']);
                if (0 !== count($e)) {
                    $r['x-entitlement'] = $e;
                }
            }

            // add proprietary "x-ext"
            if (isset($resourceOwner['ext'])) {
                $e = Json::dec($resourceOwner['ext']);
                if (0 !== count($e)) {
                    $r['x-ext'] = $e;
                }
            }
        }

        return $r;
    }
}
