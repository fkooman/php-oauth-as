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
use InvalidArgumentException;
use fkooman\Http\Request;
use fkooman\Http\Response;
use fkooman\Http\Uri;
use fkooman\Http\UriException;
use fkooman\OAuth\Server\Exception\ClientException;
use fkooman\OAuth\Server\Exception\ResourceOwnerException;
use Exception;
use Twig_Loader_Filesystem;
use Twig_Environment;

class Authorize
{
    /** @var fkooman\Ini\IniReader */
    private $iniReader;

    /** @var fkooman\OAuth\Server\PdoStorage */
    private $storage;

    /** @var fkooman\OAuth\Server\IResourceOwner */
    private $resourceOwner;

    public function __construct(IniReader $c)
    {
        $this->iniReader = $c;

        $authMech = 'fkooman\\OAuth\\Server\\'.$this->iniReader->v('authenticationMechanism');
        $this->resourceOwner = new $authMech($this->iniReader);
        $this->storage = new PdoStorage($this->iniReader);
    }

    public function handleRequest(Request $request)
    {
        $response = new Response(200);
        try {
            // hint the authentication layer about the user that wants to authenticate
            // if this information is available as a parameter to the authorize endpoint
            $resourceOwnerHint = $request->getQueryParameter("x_resource_owner_hint");
            if (null !== $resourceOwnerHint) {
                $this->resourceOwner->setResourceOwnerHint($resourceOwnerHint);
            }

            switch ($request->getRequestMethod()) {
                case "GET":
                    $result = $this->handleAuthorize($this->resourceOwner, $request->getQueryParameters());
                    if (AuthorizeResult::ASK_APPROVAL === $result->getAction()) {
                        $redirectUri = $result->getRedirectUri();
                        $twig = $this->getTwig();
                        $output = $twig->render(
                            "askAuthorization.twig",
                            array(
                                'resourceOwnerId' => $this->resourceOwner->getId(),
                                'sslEnabled' => "https" === $request->getRequestUri()->getScheme(),
                                'contactEmail' => $result->getClient()->getContactEmail(),
                                'scopes' => $result->getScope()->toArray(),
                                'clientDomain' => $redirectUri->getHost(),
                                'clientName' => $result->getClient()->getName(),
                                'clientId' => $result->getClient()->getId(),
                                'clientDescription' => $result->getClient()->getDescription(),
                                'clientIcon' => $result->getClient()->getIcon(),
                                'redirectUri' => $redirectUri->getUri(),
                            )
                        );
                        $response->setContent($output);
                    } elseif (AuthorizeResult::REDIRECT === $result->getAction()) {
                        $response->setStatusCode(302);
                        $response->setHeader("Location", $result->getRedirectUri()->getUri());
                    } else {
                        // should never happen...
                        throw new Exception("invalid authorize result");
                    }
                    break;
                case "POST":
                    // CSRF protection, check the referrer, it should be equal to the
                    // request URI
                    $fullRequestUri = $request->getRequestUri()->getUri();
                    $referrerUri = $request->getHeader("HTTP_REFERER");

                    if ($fullRequestUri !== $referrerUri) {
                        throw new ResourceOwnerException(
                            "csrf protection triggered, referrer does not match request uri"
                        );
                    }
                    $result = $this->handleApprove(
                        $this->resourceOwner,
                        $request->getQueryParameters(),
                        $request->getPostParameters()
                    );
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

            if ("user_agent_based_application" === $client->getType()) {
                $separator = "#";
            } else {
                $separator = (false === strpos($client->getRedirectUri(), "?")) ? "?" : "&";
            }
            $parameters = array("error" => $e->getMessage(), "error_description" => $e->getDescription());
            if (null !== $e->getState()) {
                $parameters['state'] = $e->getState();
            }
            $response->setStatusCode(302);
            $response->setHeader("Location", $client->getRedirectUri().$separator.http_build_query($parameters));
        } catch (ResourceOwnerException $e) {
            // tell resource owner about the error (through browser)
            $response->setStatusCode(400);
            $loader = new Twig_Loader_Filesystem(
                dirname(dirname(dirname(dirname(__DIR__))))."/views"
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
        }

        return $response;
    }

    private function handleAuthorize(IResourceOwner $resourceOwner, array $get)
    {
        try {
            $clientId     = Utils::getParameter($get, 'client_id');
            $responseType = Utils::getParameter($get, 'response_type');
            $redirectUri  = Utils::getParameter($get, 'redirect_uri');
            // FIXME: scope can never be empty, if the client requests no scope we should have a default scope!
            $scope        = Scope::fromString(Utils::getParameter($get, 'scope'));
            $state        = Utils::getParameter($get, 'state');

            if (null === $clientId) {
                throw new ResourceOwnerException('client_id missing');
            }

            if (null === $responseType) {
                throw new ResourceOwnerException('response_type missing');
            }

            $client = $this->storage->getClient($clientId);
            if (false === $client) {
                if ($this->iniReader->v('allowRemoteStorageClients', false, false)) {
                    // first we need to figure out of the authorize request is
                    // coming from remoteStorage client, this is hard... if the
                    // client_id and the redirect_uri are both set and both a
                    // URI and have the same domain, we assume it is a
                    // new remoteStorage client
                    try {
                        $clientIdAsUri = new Uri($clientId);
                        $redirectUriAsUri = new Uri($redirectUri);
                        if ($clientIdAsUri->getHost() !== $redirectUriAsUri->getHost()) {
                            // client_id host and redirect_uri do not have the same host, we are done
                            throw new ResourceOwnerException('client not registered');
                        }
                    } catch (UriException $e) {
                        // client_id or redirect_uri is not a URI, so we are done again
                        throw new ResourceOwnerException('client not registered');
                    }

                    if ("token" !== $responseType) {
                        // if it is not a token response_type, we are done once more
                        throw new ResourceOwnerException('client not registered');
                    }

                    $clientData = new ClientData(
                        array(
                            'id' => $clientId,
                            'secret' => null,
                            'type' => 'user_agent_based_application',
                            'redirect_uri' => $redirectUri,
                            'name' => $clientId,
                            'allowed_scope' => $scope->toString(),
                        )
                    );
                    $this->storage->addClient($clientData);
                    $client = $this->storage->getClient($clientId);
                } else {
                    throw new ResourceOwnerException('client not registered');
                }
            }

            if (null === $redirectUri) {
                $redirectUri = $client->getRedirectUri();
            } else {
                $allowRegExpRedirectUriMatch = $this->iniReader->v('allowRegExpRedirectUriMatch', false, false);
                if (!$client->verifyRedirectUri($redirectUri, $allowRegExpRedirectUriMatch)) {
                    throw new ResourceOwnerException(
                        'specified redirect_uri not the same as registered redirect_uri'
                    );
                }
            }

            // we need to make sure the client can only request the grant types belonging to its profile
            $allowedClientProfiles = array(
                "web_application" => array(
                    "code",
                ),
                "native_application" => array(
                    "token",
                    "code",
                ),
                "user_agent_based_application" => array(
                    "token",
                ),
            );

            if (!in_array($responseType, $allowedClientProfiles[$client->getType()])) {
                throw new ClientException(
                    "unsupported_response_type",
                    "response_type not supported by client profile",
                    $client,
                    $state
                );
            }

            if (!$scope->isSubsetOf(Scope::fromString($client->getAllowedScope()))) {
                throw new ClientException(
                    "invalid_scope",
                    "not authorized to request this scope",
                    $client,
                    $state
                );
            }

            $this->storage->updateResourceOwner($resourceOwner);
        
            if ($client->getDisableUserConsent()) {
                // we do not require approval by the user
                $approvedScope = array('scope' => $scope->toString());
            } else {
                $approvedScope = $this->storage->getApprovalByResourceOwnerId($clientId, $resourceOwner->getId());
            }
            if (false === $approvedScope || false === $scope->isSubsetOf(Scope::fromString($approvedScope['scope']))) {
                $ar = new AuthorizeResult(AuthorizeResult::ASK_APPROVAL);
                $ar->setClient($client);
                $ar->setRedirectUri(new Uri($redirectUri));
                $ar->setScope($scope);

                return $ar;
            } else {
                if ("token" === $responseType) {
                    // implicit grant
                    // FIXME: return existing access token if it exists for this exact client, resource owner and scope?
                    $accessToken = Utils::randomHex(16);
                    $this->storage->storeAccessToken(
                        $accessToken,
                        time(),
                        $clientId,
                        $resourceOwner->getId(),
                        $scope->toString(),
                        $this->iniReader->v('accessTokenExpiry')
                    );
                    $token = array(
                        "access_token" => $accessToken,
                        "expires_in" => $this->iniReader->v('accessTokenExpiry'),
                        "token_type" => "bearer",
                    );
                    $s = $scope->toString();
                    if (!empty($s)) {
                        $token += array("scope" => $s);
                    }
                    if (null !== $state) {
                        $token += array("state" => $state);
                    }
                    $ar = new AuthorizeResult(AuthorizeResult::REDIRECT);
                    $ar->setRedirectUri(new Uri($redirectUri."#".http_build_query($token)));

                    return $ar;
                } else {
                    // authorization code grant
                    $authorizationCode = Utils::randomHex(16);
                    $this->storage->storeAuthorizationCode(
                        $authorizationCode,
                        $resourceOwner->getId(),
                        time(),
                        $clientId,
                        $redirectUri,
                        $scope->getScope()
                    );
                    $token = array("code" => $authorizationCode);
                    if (null !== $state) {
                        $token += array("state" => $state);
                    }
                    $ar = new AuthorizeResult(AuthorizeResult::REDIRECT);
                    $separator = (false === strpos($redirectUri, "?")) ? "?" : "&";
                    $ar->setRedirectUri(new Uri($redirectUri.$separator.http_build_query($token)));

                    return $ar;
                }
            }
        } catch (InvalidArgumentException $e) {
            // FIXME: really weird place to handle scope exceptions?
            throw new ClientException("invalid_scope", "malformed scope", $client, $state);
        }
    }

    private function handleApprove(IResourceOwner $resourceOwner, array $get, array $post)
    {
        try {
            $clientId     = Utils::getParameter($get, 'client_id');
            $responseType = Utils::getParameter($get, 'response_type');
            $redirectUri  = Utils::getParameter($get, 'redirect_uri');
            $scope        = Scope::fromString(Utils::getParameter($get, 'scope'));
            $state        = Utils::getParameter($get, 'state');

            $result = $this->handleAuthorize($resourceOwner, $get);
            if (AuthorizeResult::ASK_APPROVAL !== $result->getAction()) {
                return $result;
            }
            $approval = Utils::getParameter($post, 'approval');

            // FIXME: are we sure this client is always valid?
            $client = $this->storage->getClient($clientId);

            if ("approve" === $approval) {
                $approvedScope = $this->storage->getApprovalByResourceOwnerId($clientId, $resourceOwner->getId());
                if (false === $approvedScope) {
                    // no approved scope stored yet, new entry
                    $refreshToken = ("code" === $responseType) ? Utils::randomHex(16) : null;
                    $this->storage->addApproval($clientId, $resourceOwner->getId(), $scope->toString(), $refreshToken);
                } else {
                    $this->storage->updateApproval($clientId, $resourceOwner->getId(), $scope->getScope());
                }

                return $this->handleAuthorize($resourceOwner, $get);
            } else {
                throw new ClientException("access_denied", "not authorized by resource owner", $client, $state);
            }
        } catch (InvalidArgumentException $e) {
            // FIXME: really weird place to handle scope exceptions?
            throw new ClientException("invalid_scope", "malformed scope", $client, $state);
        }
    }

    private function getTwig()
    {
        $configTemplateDir = dirname(dirname(dirname(dirname(__DIR__)))).'/config/views';
        $defaultTemplateDir = dirname(dirname(dirname(dirname(__DIR__)))).'/views';

        $templateDirs = array();

        // the template directory actually needs to exist, otherwise the
        // Twig_Loader_Filesystem class will throw an exception when loading
        // templates, the actual template does not need to exist though...
        if (false !== is_dir($configTemplateDir)) {
            $templateDirs[] = $configTemplateDir;
        }
        $templateDirs[] = $defaultTemplateDir;

        $loader = new Twig_Loader_Filesystem($templateDirs);

        return new Twig_Environment($loader);
    }
}
