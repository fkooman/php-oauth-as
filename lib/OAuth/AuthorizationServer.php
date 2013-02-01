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
use \RestService\Http\Uri as Uri;

class AuthorizationServer
{
    private $_storage;
    private $_c;

    public function __construct(IOAuthStorage $storage, Config $c)
    {
        $this->_storage = $storage;
        $this->_c = $c;

        // occasionally delete expired access tokens and authorization codes
        if (3 === rand(0,5)) {
            $storage->deleteExpiredAccessTokens();
            $storage->deleteExpiredAuthorizationCodes();
        }
    }

    public function authorize(IResourceOwner $resourceOwner, array $get)
    {
        try {
            $clientId     = self::getParameter($get, 'client_id');
            $responseType = self::getParameter($get, 'response_type');
            $redirectUri  = self::getParameter($get, 'redirect_uri');
            // FIXME: scope can never be empty, if the client requests no scope we should have a default scope!
            $scope        = new Scope(self::getParameter($get, 'scope'));
            $state        = self::getParameter($get, 'state');

            if (NULL === $clientId) {
                throw new ResourceOwnerException('client_id missing');
            }

            if (NULL === $responseType) {
                throw new ResourceOwnerException('response_type missing');
            }

            $client = $this->_storage->getClient($clientId);
            if (FALSE === $client) {
                throw new ResourceOwnerException('client not registered');
            }

            if (NULL !== $redirectUri) {
                if ($client->redirect_uri !== $redirectUri) {
                    throw new ResourceOwnerException('specified redirect_uri not the same as registered redirect_uri');
                }
            }

            // we need to make sure the client can only request the grant types belonging to its profile
            $allowedClientProfiles = array ( "web_application" => array ("code"),
                                             "native_application" => array ("token", "code"),
                                             "user_agent_based_application" => array ("token"));

            if (!in_array($responseType, $allowedClientProfiles[$client->type])) {
                throw new ClientException("unsupported_response_type", "response_type not supported by client profile", $client, $state);
            }

            if (!$scope->isSubsetOf(new Scope($client->allowed_scope))) {
                throw new ClientException("invalid_scope", "not authorized to request this scope", $client, $state);
            }

            $this->_storage->updateResourceOwner($resourceOwner->getResourceOwnerId(), json_encode($resourceOwner->getAttributes()));

            $approvedScope = $this->_storage->getApprovalByResourceOwnerId($clientId, $resourceOwner->getResourceOwnerId());
            if (FALSE === $approvedScope || FALSE === $scope->isSubsetOf(new Scope($approvedScope->scope))) {
                $ar = new AuthorizeResult(AuthorizeResult::ASK_APPROVAL);
                $ar->setClient(ClientRegistration::fromArray((array) $client));
                $ar->setScope($scope);

                return $ar;
            } else {
                if ("token" === $responseType) {
                    // implicit grant
                    // FIXME: return existing access token if it exists for this exact client, resource owner and scope?
                    $accessToken = self::randomHex(16);
                    $this->_storage->storeAccessToken($accessToken, time(), $clientId, $resourceOwner->getResourceOwnerId(), $scope->getScope(), $this->_c->getValue('accessTokenExpiry'));
                    $token = array("access_token" => $accessToken,
                                   "expires_in" => $this->_c->getValue('accessTokenExpiry'),
                                   "token_type" => "bearer");
                    $s = $scope->getScope();
                    if (!empty($s)) {
                        $token += array ("scope" => $s);
                    }
                    if (NULL !== $state) {
                        $token += array ("state" => $state);
                    }
                    $ar = new AuthorizeResult(AuthorizeResult::REDIRECT);
                    $ar->setRedirectUri(new Uri($client->redirect_uri . "#" . http_build_query($token)));

                    return $ar;
                } else {
                    // authorization code grant
                    $authorizationCode = self::randomHex(16);
                    $this->_storage->storeAuthorizationCode($authorizationCode, $resourceOwner->getResourceOwnerId(), time(), $clientId, $redirectUri, $scope->getScope());
                    $token = array("code" => $authorizationCode);
                    if (NULL !== $state) {
                        $token += array ("state" => $state);
                    }
                    $ar = new AuthorizeResult(AuthorizeResult::REDIRECT);
                    $separator = (FALSE === strpos($client->redirect_uri, "?")) ? "?" : "&";
                    $ar->setRedirectUri(new Uri($client->redirect_uri . $separator . http_build_query($token)));

                    return $ar;
                }
            }
        } catch (ScopeException $e) {
            throw new ClientException("invalid_scope", "malformed scope", $client, $state);
        }
    }

    public function approve(IResourceOwner $resourceOwner, array $get, array $post)
    {
        try {
            $clientId     = self::getParameter($get, 'client_id');
            $responseType = self::getParameter($get, 'response_type');
            $redirectUri  = self::getParameter($get, 'redirect_uri');
            $scope        = new Scope(self::getParameter($get, 'scope'));
            $state        = self::getParameter($get, 'state');

            $result = $this->authorize($resourceOwner, $get);
            if (AuthorizeResult::ASK_APPROVAL !== $result->getAction()) {
                return $result;
            }

            $postScope = new Scope(self::getParameter($post, 'scope'));
            $approval = self::getParameter($post, 'approval');

            // FIXME: are we sure this client is always valid?
            $client = $this->_storage->getClient($clientId);

            if ("Approve" === $approval) {
                if (!$postScope->isSubsetOf($scope)) {
                    // FIXME: should this actually be an authorize exception? this is a user error!
                    throw new ClientException("invalid_scope", "approved scope is not a subset of requested scope", $client, $state);
                }

                $approvedScope = $this->_storage->getApprovalByResourceOwnerId($clientId, $resourceOwner->getResourceOwnerId());
                if (FALSE === $approvedScope) {
                    // no approved scope stored yet, new entry
                    $refreshToken = ("code" === $responseType) ? self::randomHex(16) : NULL;
                    $this->_storage->addApproval($clientId, $resourceOwner->getResourceOwnerId(), $postScope->getScope(), $refreshToken);
                } elseif (!$postScope->isSubsetOf(new Scope($approvedScope->scope))) {
                    // not a subset, merge and store the new one
                    $mergedScopes = clone $postScope;
                    $mergedScopes->mergeWith(new Scope($approvedScope->scope));
                    $this->_storage->updateApproval($clientId, $resourceOwner->getResourceOwnerId(), $mergedScopes->getScope());
                } else {
                    // subset, approval for superset of scope already exists, do nothing
                }
                $get['scope'] = $postScope->getScope();

                return $this->authorize($resourceOwner, $get);

            } else {
                throw new ClientException("access_denied", "not authorized by resource owner", $client, $state);
            }
        } catch (ScopeException $e) {
            throw new ClientException("invalid_scope", "malformed scope", $client, $state);
        }
    }

    public function tokenInfo(array $get)
    {
        $token = self::getParameter($get, 'access_token');
        if (NULL === $token) {
            throw new TokenInfoException("invalid_token", "the token parameter is missing");
        }
        $accessToken = $this->_storage->getAccessToken($token);
        if (FALSE === $accessToken) {
            throw new TokenInfoException("invalid_token", "the token was not found");
        }

        if (time() > $accessToken->issue_time + $accessToken->expires_in) {
            throw new TokenInfoException("invalid_token", "the token expired");
        }

        $resourceOwner = $this->_storage->getResourceOwner($accessToken->resource_owner_id);
        $accessToken->resource_owner_attributes = json_decode($resourceOwner->attributes, TRUE);

        return $accessToken;
    }

    public function token(array $post, $user = NULL, $pass = NULL)
    {
        // exchange authorization code for access token
        $grantType    = self::getParameter($post, 'grant_type');
        $code         = self::getParameter($post, 'code');
        $redirectUri  = self::getParameter($post, 'redirect_uri');
        $refreshToken = self::getParameter($post, 'refresh_token');
        $token        = self::getParameter($post, 'token');
        $clientId     = self::getParameter($post, 'client_id');
        $scope        = self::getParameter($post, 'scope');

        if (NULL !== $user && !empty($user) && NULL !== $pass && !empty($pass)) {
            // client provided authentication, it MUST be valid now...
            $client = $this->_storage->getClient($user);
            if (FALSE === $client) {
                throw new TokenException("invalid_client", "client authentication failed");
            }

            // check pass
            if ($pass !== $client->secret) {
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

        if ("user_agent_based_application" === $client->type) {
            throw new TokenException("unauthorized_client", "this client type is not allowed to use the token endpoint");
        }

        if ("web_application" === $client->type && !$hasAuthenticated) {
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
                // FIXME: if all of a sudden a redirect_uri is present, it should be allowed?
                // spec is vague about this... but then again, it doesn't make sense to not specify it
                // in the authorize request, and now all of a sudden it is specified... ignore it?
                $result = $this->_storage->getAuthorizationCode($client->id, $code, $redirectUri);
                if (FALSE === $result) {
                    throw new TokenException("invalid_grant", "the authorization code was not found");
                }
                if (time() > $result->issue_time + 600) {
                    throw new TokenException("invalid_grant", "the authorization code expired");
                }

                // we MUST be able to delete the authorization code, otherwise it was used before
                if (FALSE === $this->_storage->deleteAuthorizationCode($client->id, $code, $redirectUri)) {
                    // check to prevent deletion race condition
                    throw new TokenException("invalid_grant", "this authorization code grant was already used");
                }

                $approval = $this->_storage->getApprovalByResourceOwnerId($client->id, $result->resource_owner_id);

                $token = array();
                $token['access_token'] = self::randomHex(16);
                $token['expires_in'] = $this->_c->getValue('accessTokenExpiry');
                // we always grant the scope the user authorized, no further restrictions here...
                // FIXME: the merging of authorized scopes in the authorize function is a bit of a mess!
                // we should deal with that there and come up with a good solution...
                $token['scope'] = $result->scope;
                $token['refresh_token'] = $approval->refresh_token;
                $token['token_type'] = "bearer";
                $this->_storage->storeAccessToken($token['access_token'], time(), $client->id, $result->resource_owner_id, $token['scope'], $token['expires_in']);
                break;

            case "refresh_token":
                if (NULL === $refreshToken) {
                    throw new TokenException("invalid_request", "the refresh_token parameter is missing");
                }
                $result = $this->_storage->getApprovalByRefreshToken($client->id, $refreshToken);
                if (FALSE === $result) {
                    throw new TokenException("invalid_grant", "the refresh_token was not found");
                }

                $token = array();
                $token['access_token'] = self::randomHex(16);
                $token['expires_in'] = $this->_c->getValue('accessTokenExpiry');
                if (NULL !== $scope) {
                    // the client wants to obtain a specific scope
                    $requestedScope = new Scope($scope);
                    $authorizedScope = new Scope($result->scope);
                    if ($requestedScope->isSubsetOf($authorizedScope)) {
                        // if it is a subset of the authorized scope we honor that
                        $token['scope'] = $requestedScope->getScope();
                    } else {
                        // if not the client gets the authorized scope
                        $token['scope'] = $result->scope;
                    }
                } else {
                    $token['scope'] = $result->scope;
                }

                $token['token_type'] = "bearer";
                $this->_storage->storeAccessToken($token['access_token'], time(), $client->id, $result->resource_owner_id, $token['scope'], $token['expires_in']);
                break;

            default:
                throw new TokenException("unsupported_grant_type", "the requested grant type is not supported");
        }

        return (object) $token;
    }

    private static function getParameter(array $parameters, $key)
    {
        return (array_key_exists($key, $parameters) && !empty($parameters[$key])) ? $parameters[$key] : NULL;
    }

    public static function randomHex($len = 16)
    {
        $randomString = bin2hex(openssl_random_pseudo_bytes($len, $strong));
        // @codeCoverageIgnoreStart
        if (FALSE === $strong) {
            throw new Exception("unable to securely generate random string");
        }
        // @codeCoverageIgnoreEnd
        return $randomString;
    }

}
