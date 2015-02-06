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

namespace fkooman\OAuth\Server;

use fkooman\Json\Json;
use fkooman\Http\Request;
use fkooman\Http\JsonResponse;
use fkooman\OAuth\Server\Exception\TokenIntrospectionException;

class TokenIntrospection
{
    /** @var fkooman\OAuth\Server\PdoStorage */
    private $storage;

    public function __construct(PdoStorage $storage)
    {
        $this->storage = $storage;
    }

    public function handleRequest(Request $request)
    {
        $response = new JsonResponse();

        try {
            $requestMethod = $request->getRequestMethod();

            if ("GET" !== $requestMethod && "POST" !== $requestMethod) {
                throw new TokenIntrospectionException("method_not_allowed", "invalid request method");
            }
            $parameters = "GET" === $requestMethod ? $request->getQueryParameters() : $request->getPostParameters();
            $response->setHeader('Cache-Control', 'no-store');
            $response->setHeader('Pragma', 'no-cache');
            $response->setContent($this->introspectToken($parameters));
        } catch (TokenIntrospectionException $e) {
            $response->setStatusCode($e->getResponseCode());
            $response->setContent(
                array(
                    "error" => $e->getMessage(),
                    "error_description" => $e->getDescription(),
                )
            );
            if ("method_not_allowed" === $e->getMessage()) {
                $response->setHeader("Allow", "GET,POST");
            }
        }

        return $response;
    }

    /**
     * Implementation of https://tools.ietf.org/html/draft-richer-oauth-introspection
     */
    private function introspectToken(array $param)
    {
        $r = array();

        $token = Utils::getParameter($param, 'token');
        if (null === $token) {
            throw new TokenIntrospectionException("invalid_token", "the token parameter is missing");
        }
        $accessToken = $this->storage->getAccessToken($token);
        if (false === $accessToken) {
            // token does not exist
            $r['active'] = false;
        } elseif (time() > $accessToken['issue_time'] + $accessToken['expires_in']) {
            // token expired
            $r['active'] = false;
        } else {
            // token exists and did not expire
            $r['active'] = true;
            $r['exp'] = intval($accessToken['issue_time'] + $accessToken['expires_in']);
            $r['iat'] = intval($accessToken['issue_time']);
            $r['scope'] = $accessToken['scope'];
            $r['client_id'] = $accessToken['client_id'];
            $r['sub'] = $accessToken['resource_owner_id'];
            $r['token_type'] = 'bearer';

            // as long as we have no RS registration we cannot set the audience...
            // $response['aud'] = 'foo';

            // add proprietary "x-entitlement"
            $resourceOwner = $this->storage->getResourceOwner($accessToken['resource_owner_id']);
            if (isset($resourceOwner['entitlement'])) {
                $e = Json::decode($resourceOwner['entitlement']);
                if (0 !== count($e)) {
                    $r['x-entitlement'] = implode(" ", $e);
                }
            }

            // add proprietary "x-ext"
            if (isset($resourceOwner['ext'])) {
                $e = Json::decode($resourceOwner['ext']);
                if (0 !== count($e)) {
                    $r['x-ext'] = $e;
                }
            }
        }

        return $r;
    }
}
