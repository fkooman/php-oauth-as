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
use \RestService\Http\Uri as Uri;

class Authorize
{
    private $_config;
    private $_logger;
    private $_storage;

    private $_resourceOwner;

    public function __construct(Config $c, Logger $l = NULL)
    {
        $this->_config = $c;
        $this->_logger = $l;

        $authMech = 'OAuth\\' . $this->_config->getValue('authenticationMechanism');
        $this->_resourceOwner = new $authMech($this->_config);

        $oauthStorageBackend = 'OAuth\\' . $this->_config->getValue('storageBackend');
        $this->_storage = new $oauthStorageBackend($this->_config);
    }

    public function handleRequest(HttpRequest $request)
    {
        $response = new HttpResponse(200);
        try {
            // hint the authentication layer about the user that wants to authenticate
            // if this information is available as a parameter to the authorize endpoint
            $resourceOwnerHint = $request->getQueryParameter("x_resource_owner_hint");
            if (null !== $resourceOwnerHint) {
                $this->_resourceOwner->setResourceOwnerHint($resourceOwnerHint);
            }

            switch ($request->getRequestMethod()) {
                case "GET":
                        $result = $this->_handleAuthorize($this->_resourceOwner, $request->getQueryParameters());
                        if (AuthorizeResult::ASK_APPROVAL === $result->getAction()) {
                            $loader = new \Twig_Loader_Filesystem(dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . "views");
                            $twig = new \Twig_Environment($loader);

                            $redirectUri = new Uri($result->getClient()->getRedirectUri());

                            $output = $twig->render("askAuthorization.twig", array (
                                'serviceName' => $this->_config->getValue('serviceName'),
                                'serviceLogoUri' => $this->_config->getValue('serviceLogoUri', FALSE),
                                'serviceLogoWidth' => $this->_config->getValue('serviceLogoWidth', FALSE),
                                'serviceLogoHeight' => $this->_config->getValue('serviceLogoHeight', FALSE),
                                'resourceOwnerId' => $this->_resourceOwner->getId(),
                                'sslEnabled' => "https" === $request->getRequestUri()->getScheme(),
                                'contactEmail' => $result->getClient()->getContactEmail(),
                                'scopes' => $result->getScope()->getScopeAsArray(),
                                'clientDomain' => $redirectUri->getHost(),
                                'clientName' => $result->getClient()->getName(),
                                'clientId' => $result->getClient()->getId(),
                                'clientDescription' => $result->getClient()->getDescription(),
                                'clientIcon' => $result->getClient()->getIcon(),
                                'redirectUri' => $redirectUri->getUri()
                            ));
                            $response->setContent($output);
                        } elseif (AuthorizeResult::REDIRECT === $result->getAction()) {
                            $response->setStatusCode(302);
                            $response->setHeader("Location", $result->getRedirectUri()->getUri());
                        } else {
                            // should never happen...
                            throw new \Exception("invalid authorize result");
                        }
                    break;

                case "POST";
                    // CSRF protection, check the referrer, it should be equal to the
                    // request URI
                    $fullRequestUri = $request->getRequestUri()->getUri();
                    $referrerUri = $request->getHeader("HTTP_REFERER");

                    if ($fullRequestUri !== $referrerUri) {
                        throw new ResourceOwnerException("csrf protection triggered, referrer does not match request uri");
                    }
                    $result = $this->_handleApprove($this->_resourceOwner, $request->getQueryParameters(), $request->getPostParameters());
                    if (AuthorizeResult::REDIRECT !== $result->getAction()) {
                        // FIXME: this is dead code?
                        throw new ResourceOwnerException("approval not found");
                    }
                    $response->setStatusCode(302);
                    $response->setHeader("Location", $result->getRedirectUri()->getUri());
                    break;

                default:
                    // method not allowed
                    $response->setStatusCode(405);
                    $response->setHeader("Allow", "GET, POST");
                    break;
            }
        } catch (ClientException $e) {
            // tell the client about the error
            $client = $e->getClient();

            if ($client['type'] === "user_agent_based_application") {
                $separator = "#";
            } else {
                $separator = (FALSE === strpos($client['redirect_uri'], "?")) ? "?" : "&";
            }
            $parameters = array("error" => $e->getMessage(), "error_description" => $e->getDescription());
            if (NULL !== $e->getState()) {
                $parameters['state'] = $e->getState();
            }
            $response->setStatusCode(302);
            $response->setHeader("Location", $client['redirect_uri'] . $separator . http_build_query($parameters));
            if (NULL !== $this->_logger) {
                $this->_logger->logFatal($e->getLogMessage(TRUE) . PHP_EOL . $request . PHP_EOL . $response);
            }
        } catch (ResourceOwnerException $e) {
            // tell resource owner about the error (through browser)
            $response->setStatusCode(400);
            $loader = new \Twig_Loader_Filesystem(dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . "views");
            $twig = new \Twig_Environment($loader);
            $output = $twig->render("error.twig", array (
                "statusCode" => $response->getStatusCode(),
                "statusReason" => $response->getStatusReason(),
                "errorMessage" => $e->getMessage()
            ));
            $response->setContent($output);

            if (NULL !== $this->_logger) {
                $this->_logger->logFatal($e->getMessage() . PHP_EOL . $request . PHP_EOL . $response);
            }
        }

        return $response;
    }

    private function _handleAuthorize(IResourceOwner $resourceOwner, array $get)
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

            $this->_storage->updateResourceOwner($resourceOwner);

            $approvedScope = $this->_storage->getApprovalByResourceOwnerId($clientId, $resourceOwner->getId());
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
                    $this->_storage->storeAccessToken($accessToken, time(), $clientId, $resourceOwner->getId(), $scope->getScope(), $this->_config->getValue('accessTokenExpiry'));
                    $token = array("access_token" => $accessToken,
                                   "expires_in" => $this->_config->getValue('accessTokenExpiry'),
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
                    $this->_storage->storeAuthorizationCode($authorizationCode, $resourceOwner->getId(), time(), $clientId, $redirectUri, $scope->getScope());
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

    private function _handleApprove(IResourceOwner $resourceOwner, array $get, array $post)
    {
        try {
            $clientId     = Utils::getParameter($get, 'client_id');
            $responseType = Utils::getParameter($get, 'response_type');
            $redirectUri  = Utils::getParameter($get, 'redirect_uri');
            $scope        = new Scope(Utils::getParameter($get, 'scope'));
            $state        = Utils::getParameter($get, 'state');

            $result = $this->_handleAuthorize($resourceOwner, $get);
            if (AuthorizeResult::ASK_APPROVAL !== $result->getAction()) {
                return $result;
            }
            $approval = Utils::getParameter($post, 'approval');

            // FIXME: are we sure this client is always valid?
            $client = $this->_storage->getClient($clientId);

            if ("approve" === $approval) {
                $approvedScope = $this->_storage->getApprovalByResourceOwnerId($clientId, $resourceOwner->getId());
                if (FALSE === $approvedScope) {
                    // no approved scope stored yet, new entry
                    $refreshToken = ("code" === $responseType) ? Utils::randomHex(16) : NULL;
                    $this->_storage->addApproval($clientId, $resourceOwner->getId(), $scope->getScope(), $refreshToken);
                } else {
                    $this->_storage->updateApproval($clientId, $resourceOwner->getId(), $scope->getScope());
                }

                return $this->_handleAuthorize($resourceOwner, $get);
            } else {
                throw new ClientException("access_denied", "not authorized by resource owner", $client, $state);
            }
        } catch (ScopeException $e) {
            throw new ClientException("invalid_scope", "malformed scope", $client, $state);
        }
    }

}
