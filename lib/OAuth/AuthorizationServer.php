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
use \RestService\Utils\Json as Json;

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
            $clientId     = Utils::getParameter($get, 'client_id');
            $responseType = Utils::getParameter($get, 'response_type');
            $redirectUri  = Utils::getParameter($get, 'redirect_uri');
            // FIXME: scope can never be empty, if the client requests no scope we should have a default scope!
            $scope        = new Scope(Utils::getParameter($get, 'scope'));
            $state        = Utils::getParameter($get, 'state');

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
                if ($client['redirect_uri'] !== $redirectUri) {
                    throw new ResourceOwnerException('specified redirect_uri not the same as registered redirect_uri');
                }
            }

            // we need to make sure the client can only request the grant types belonging to its profile
            $allowedClientProfiles = array ( "web_application" => array ("code"),
                                             "native_application" => array ("token", "code"),
                                             "user_agent_based_application" => array ("token"));

            if (!in_array($responseType, $allowedClientProfiles[$client['type']])) {
                throw new ClientException("unsupported_response_type", "response_type not supported by client profile", $client, $state);
            }

            if (!$scope->isSubsetOf(new Scope($client['allowed_scope']))) {
                throw new ClientException("invalid_scope", "not authorized to request this scope", $client, $state);
            }

            $this->_storage->updateResourceOwner($resourceOwner->getResourceOwnerId(), Json::enc($resourceOwner->getAttributes()));

            $approvedScope = $this->_storage->getApprovalByResourceOwnerId($clientId, $resourceOwner->getResourceOwnerId());
            if (FALSE === $approvedScope || FALSE === $scope->isSubsetOf(new Scope($approvedScope['scope']))) {
                $ar = new AuthorizeResult(AuthorizeResult::ASK_APPROVAL);
                $ar->setClient(ClientRegistration::fromArray($client));
                $ar->setScope($scope);

                return $ar;
            } else {
                if ("token" === $responseType) {
                    // implicit grant
                    // FIXME: return existing access token if it exists for this exact client, resource owner and scope?
                    $accessToken = Utils::randomHex(16);
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
                    $ar->setRedirectUri(new Uri($client['redirect_uri'] . "#" . http_build_query($token)));

                    return $ar;
                } else {
                    // authorization code grant
                    $authorizationCode = Utils::randomHex(16);
                    $this->_storage->storeAuthorizationCode($authorizationCode, $resourceOwner->getResourceOwnerId(), time(), $clientId, $redirectUri, $scope->getScope());
                    $token = array("code" => $authorizationCode);
                    if (NULL !== $state) {
                        $token += array ("state" => $state);
                    }
                    $ar = new AuthorizeResult(AuthorizeResult::REDIRECT);
                    $separator = (FALSE === strpos($client['redirect_uri'], "?")) ? "?" : "&";
                    $ar->setRedirectUri(new Uri($client['redirect_uri'] . $separator . http_build_query($token)));

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
            $clientId     = Utils::getParameter($get, 'client_id');
            $responseType = Utils::getParameter($get, 'response_type');
            $redirectUri  = Utils::getParameter($get, 'redirect_uri');
            $scope        = new Scope(Utils::getParameter($get, 'scope'));
            $state        = Utils::getParameter($get, 'state');

            $result = $this->authorize($resourceOwner, $get);
            if (AuthorizeResult::ASK_APPROVAL !== $result->getAction()) {
                return $result;
            }

            $postScope = new Scope(Utils::getParameter($post, 'scope'));
            $approval = Utils::getParameter($post, 'approval');

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
                    $refreshToken = ("code" === $responseType) ? Utils::randomHex(16) : NULL;
                    $this->_storage->addApproval($clientId, $resourceOwner->getResourceOwnerId(), $postScope->getScope(), $refreshToken);
                } elseif (!$postScope->isSubsetOf(new Scope($approvedScope['scope']))) {
                    // not a subset, merge and store the new one
                    $mergedScopes = clone $postScope;
                    $mergedScopes->mergeWith(new Scope($approvedScope['scope']));
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

}
