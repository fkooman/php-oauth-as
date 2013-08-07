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

class Token
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

        // occasionally delete expired access tokens and authorization codes
        if (3 === rand(0,5)) {
            $this->_storage->deleteExpiredAccessTokens();
            $this->_storage->deleteExpiredAuthorizationCodes();
        }
    }

    public function handleRequest(HttpRequest $request)
    {
        $response = new HttpResponse(200, "application/json");
        try {
            if ("POST" !== $request->getRequestMethod()) {
                // method not allowed
                $response->setStatusCode(405);
                $response->setHeader("Allow", "POST");
            } else {
                $response->setHeader('Content-Type', 'application/json');
                $response->setHeader('Cache-Control', 'no-store');
                $response->setHeader('Pragma', 'no-cache');
                $response->setContent(Json::enc($this->_handleToken($request->getPostParameters(), $request->getBasicAuthUser(), $request->getBasicAuthPass())));
            }
        } catch (TokenException $e) {
            if ($e->getResponseCode() === 401) {
                $response->setHeader("WWW-Authenticate", 'Basic realm="OAuth Server"');
            }
            $response->setStatusCode($e->getResponseCode());
            $response->setHeader('Cache-Control', 'no-store');
            $response->setHeader('Pragma', 'no-cache');
            $response->setContent(Json::enc(array("error" => $e->getMessage(), "error_description" => $e->getDescription())));
            if (NULL !== $this->_logger) {
                $this->_logger->logFatal($e->getLogMessage(TRUE) . PHP_EOL . $request . PHP_EOL . $response);
            }
        }

        return $response;
    }

    private function _handleToken(array $post, $user = NULL, $pass = NULL)
    {
        // exchange authorization code for access token
        $grantType    = Utils::getParameter($post, 'grant_type');
        $code         = Utils::getParameter($post, 'code');
        $redirectUri  = Utils::getParameter($post, 'redirect_uri');
        $refreshToken = Utils::getParameter($post, 'refresh_token');
        $token        = Utils::getParameter($post, 'token');
        $clientId     = Utils::getParameter($post, 'client_id');
        $scope        = Utils::getParameter($post, 'scope');

        if (NULL !== $user && !empty($user) && NULL !== $pass && !empty($pass)) {
            // client provided authentication, it MUST be valid now...
            $client = $this->_storage->getClient($user);
            if (FALSE === $client) {
                throw new TokenException("invalid_client", "client authentication failed");
            }

            // check pass
            if ($pass !== $client['secret']) {
                throw new TokenException("invalid_client", "client authentication failed");
            }

            // if client_id in POST is set, it must match the user
            if (NULL !== $clientId && $clientId !== $user) {
                throw new TokenException("invalid_grant", "client_id inconsistency: authenticating user must match POST body client_id");
            }
            $hasAuthenticated = TRUE;
        } else {
            // client provided no authentication, client_id must be in POST body
            if (NULL === $clientId || empty($clientId)) {
                throw new TokenException("invalid_request", "no client authentication used nor client_id POST parameter");
            }
            $client = $this->_storage->getClient($clientId);
            if (FALSE === $client) {
                throw new TokenException("invalid_client", "client identity could not be established");
            }

            $hasAuthenticated = FALSE;
        }

        if ("user_agent_based_application" === $client['type']) {
            throw new TokenException("unauthorized_client", "this client type is not allowed to use the token endpoint");
        }

        if ("web_application" === $client['type'] && !$hasAuthenticated) {
            // web_application type MUST have authenticated
            throw new TokenException("invalid_client", "client authentication failed");
        }

        if (NULL === $grantType) {
            throw new TokenException("invalid_request", "the grant_type parameter is missing");
        }

        switch ($grantType) {
            case "authorization_code":
                if (NULL === $code) {
                    throw new TokenException("invalid_request", "the code parameter is missing");
                }
                // If the redirect_uri was present in the authorize request, it MUST also be there
                // in the token request. If it was not there in authorize request, it MUST NOT be
                // there in the token request (this is not explicit in the spec!)
                $result = $this->_storage->getAuthorizationCode($client['id'], $code, $redirectUri);
                if (FALSE === $result) {
                    throw new TokenException("invalid_grant", "the authorization code was not found");
                }
                if (time() > $result['issue_time'] + 600) {
                    throw new TokenException("invalid_grant", "the authorization code expired");
                }

                // we MUST be able to delete the authorization code, otherwise it was used before
                if (FALSE === $this->_storage->deleteAuthorizationCode($client['id'], $code, $redirectUri)) {
                    // check to prevent deletion race condition
                    throw new TokenException("invalid_grant", "this authorization code grant was already used");
                }

                $approval = $this->_storage->getApprovalByResourceOwnerId($client['id'], $result['resource_owner_id']);

                $token = array();
                $token['access_token'] = Utils::randomHex(16);
                $token['expires_in'] = intval($this->_config->getValue('accessTokenExpiry'));
                // we always grant the scope the user authorized, no further restrictions here...
                // FIXME: the merging of authorized scopes in the authorize function is a bit of a mess!
                // we should deal with that there and come up with a good solution...
                $token['scope'] = $result['scope'];
                $token['refresh_token'] = $approval['refresh_token'];
                $token['token_type'] = "bearer";
                $this->_storage->storeAccessToken($token['access_token'], time(), $client['id'], $result['resource_owner_id'], $token['scope'], $token['expires_in']);
                break;

            case "refresh_token":
                if (NULL === $refreshToken) {
                    throw new TokenException("invalid_request", "the refresh_token parameter is missing");
                }
                $result = $this->_storage->getApprovalByRefreshToken($client['id'], $refreshToken);
                if (FALSE === $result) {
                    throw new TokenException("invalid_grant", "the refresh_token was not found");
                }

                $token = array();
                $token['access_token'] = Utils::randomHex(16);
                $token['expires_in'] = intval($this->_config->getValue('accessTokenExpiry'));
                if (NULL !== $scope) {
                    // the client wants to obtain a specific scope
                    $requestedScope = new Scope($scope);
                    $authorizedScope = new Scope($result['scope']);
                    if ($requestedScope->isSubsetOf($authorizedScope)) {
                        // if it is a subset of the authorized scope we honor that
                        $token['scope'] = $requestedScope->getScope();
                    } else {
                        // if not the client gets the authorized scope
                        $token['scope'] = $result['scope'];
                    }
                } else {
                    $token['scope'] = $result['scope'];
                }

                $token['token_type'] = "bearer";
                $this->_storage->storeAccessToken($token['access_token'], time(), $client['id'], $result['resource_owner_id'], $token['scope'], $token['expires_in']);
                break;

            default:
                throw new TokenException("unsupported_grant_type", "the requested grant type is not supported");
        }

        return $token;
    }

}
