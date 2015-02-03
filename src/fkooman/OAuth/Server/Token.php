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

use fkooman\Ini\IniReader;
use fkooman\OAuth\Common\Scope;
use fkooman\Http\Request;
use fkooman\Http\JsonResponse;
use fkooman\OAuth\Server\Exception\TokenException;

class Token
{
    /** @var fkooman\Ini\IniReader */
    private $iniReader;

    /** @var fkooman\OAuth\Server\PdoStorage */
    private $storage;

    public function __construct(IniReader $c)
    {
        $this->iniReader = $c;
        $this->storage = new PdoStorage($this->iniReader);

        // occasionally delete expired access tokens and authorization codes
        if (3 === rand(0, 5)) {
            $this->storage->deleteExpiredAccessTokens();
            $this->storage->deleteExpiredAuthorizationCodes();
        }
    }

    public function handleRequest(Request $request)
    {
        $response = new JsonResponse();
        try {
            if ("POST" !== $request->getRequestMethod()) {
                // method not allowed
                $response->setStatusCode(405);
                $response->setHeader("Allow", "POST");
            } else {
                $response->setHeader('Content-Type', 'application/json');
                $response->setHeader('Cache-Control', 'no-store');
                $response->setHeader('Pragma', 'no-cache');
                $response->setContent(
                    $this->handleToken(
                        $request->getPostParameters(),
                        $request->getBasicAuthUser(),
                        $request->getBasicAuthPass()
                    )
                );
            }
        } catch (TokenException $e) {
            if ($e->getResponseCode() === 401) {
                $response->setHeader("WWW-Authenticate", 'Basic realm="OAuth Server"');
            }
            $response->setStatusCode($e->getResponseCode());
            $response->setHeader('Cache-Control', 'no-store');
            $response->setHeader('Pragma', 'no-cache');
            $response->setContent(
                array(
                    "error" => $e->getMessage(),
                    "error_description" => $e->getDescription(),
                )
            );
        }

        return $response;
    }

    private function handleToken(array $post, $user = null, $pass = null)
    {
        // exchange authorization code for access token
        $grantType    = Utils::getParameter($post, 'grant_type');
        $code         = Utils::getParameter($post, 'code');
        $redirectUri  = Utils::getParameter($post, 'redirect_uri');
        $refreshToken = Utils::getParameter($post, 'refresh_token');
        $token        = Utils::getParameter($post, 'token');
        $clientId     = Utils::getParameter($post, 'client_id');
        $scope        = Utils::getParameter($post, 'scope');

        if (null !== $user && !empty($user) && null !== $pass && !empty($pass)) {
            // client provided authentication, it MUST be valid now...
            $client = $this->storage->getClient($user);
            if (false === $client) {
                throw new TokenException("invalid_client", "client authentication failed");
            }

            // check pass
            if ($pass !== $client->getSecret()) {
                throw new TokenException("invalid_client", "client authentication failed");
            }

            // if client_id in POST is set, it must match the user
            if (null !== $clientId && $clientId !== $user) {
                throw new TokenException(
                    "invalid_grant",
                    "client_id inconsistency: authenticating user must match POST body client_id"
                );
            }
            $hasAuthenticated = true;
        } else {
            // client provided no authentication, client_id must be in POST body
            if (null === $clientId || empty($clientId)) {
                throw new TokenException(
                    "invalid_request",
                    "no client authentication used nor client_id POST parameter"
                );
            }
            $client = $this->storage->getClient($clientId);
            if (false === $client) {
                throw new TokenException("invalid_client", "client identity could not be established");
            }

            $hasAuthenticated = false;
        }

        if ("user_agent_based_application" === $client->getType()) {
            throw new TokenException(
                "unauthorized_client",
                "this client type is not allowed to use the token endpoint"
            );
        }

        if ("web_application" === $client->getType() && !$hasAuthenticated) {
            // web_application type MUST have authenticated
            throw new TokenException("invalid_client", "client authentication failed");
        }

        if (null === $grantType) {
            throw new TokenException("invalid_request", "the grant_type parameter is missing");
        }

        switch ($grantType) {
            case "authorization_code":
                if (null === $code) {
                    throw new TokenException("invalid_request", "the code parameter is missing");
                }
                // If the redirect_uri was present in the authorize request, it MUST also be there
                // in the token request. If it was not there in authorize request, it MUST NOT be
                // there in the token request (this is not explicit in the spec!)
                $result = $this->storage->getAuthorizationCode($client->getId(), $code, $redirectUri);
                if (false === $result) {
                    throw new TokenException("invalid_grant", "the authorization code was not found");
                }
                if (time() > $result['issue_time'] + 600) {
                    throw new TokenException("invalid_grant", "the authorization code expired");
                }

                // we MUST be able to delete the authorization code, otherwise it was used before
                if (false === $this->storage->deleteAuthorizationCode($client->getId(), $code, $redirectUri)) {
                    // check to prevent deletion race condition
                    throw new TokenException("invalid_grant", "this authorization code grant was already used");
                }

                $approval = $this->storage->getApprovalByResourceOwnerId($client->getId(), $result['resource_owner_id']);

                $token = array();
                $token['access_token'] = Utils::randomHex(16);
                $token['expires_in'] = intval($this->iniReader->v('accessTokenExpiry'));
                // we always grant the scope the user authorized, no further restrictions here...
                // FIXME: the merging of authorized scopes in the authorize function is a bit of a mess!
                // we should deal with that there and come up with a good solution...
                $token['scope'] = $result['scope'];
                $token['refresh_token'] = $approval['refresh_token'];
                $token['token_type'] = "bearer";
                $this->storage->storeAccessToken(
                    $token['access_token'],
                    time(),
                    $client->getId(),
                    $result['resource_owner_id'],
                    $token['scope'],
                    $token['expires_in']
                );
                break;
            case "refresh_token":
                if (null === $refreshToken) {
                    throw new TokenException("invalid_request", "the refresh_token parameter is missing");
                }
                $result = $this->storage->getApprovalByRefreshToken($client->getId(), $refreshToken);
                if (false === $result) {
                    throw new TokenException("invalid_grant", "the refresh_token was not found");
                }

                $token = array();
                $token['access_token'] = Utils::randomHex(16);
                $token['expires_in'] = intval($this->iniReader->v('accessTokenExpiry'));
                if (null !== $scope) {
                    // the client wants to obtain a specific scope
                    $requestedScope = Scope::fromString($scope);
                    $authorizedScope = Scope::fromString($result['scope']);
                    if ($requestedScope->isSubsetOf($authorizedScope)) {
                        // if it is a subset of the authorized scope we honor that
                        $token['scope'] = $requestedScope->toString();
                    } else {
                        // if not the client gets the authorized scope
                        $token['scope'] = $result['scope'];
                    }
                } else {
                    $token['scope'] = $result['scope'];
                }

                $token['token_type'] = "bearer";
                $this->storage->storeAccessToken(
                    $token['access_token'],
                    time(),
                    $client->getId(),
                    $result['resource_owner_id'],
                    $token['scope'],
                    $token['expires_in']
                );
                break;
            default:
                throw new TokenException("unsupported_grant_type", "the requested grant type is not supported");
        }

        return $token;
    }
}
