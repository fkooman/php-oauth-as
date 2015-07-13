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

use fkooman\Http\Exception\BadRequestException;
use fkooman\Http\RedirectResponse;
use fkooman\Http\Request;
use fkooman\Rest\Plugin\Authentication\UserInfoInterface;
use fkooman\Rest\Service;
use fkooman\Http\Response;

class AuthorizeService extends Service
{
    /** @var fkooman\OAuth\Server\PdoStorage */
    private $storage;

    /** @var fkooman\OAuth\Server\IO */
    private $io;

    /** @var fkooman\OAuth\Server\TemplateManager */
    private $templateManager;

    /** @var int */
    private $accessTokenExpiry;

    /** @var bool */
    private $allowRegExpRedirectUriMatch;

    public function __construct(PdoStorage $storage, IO $io = null, $accessTokenExpiry = 3600, $allowRegExpRedirectUriMatch = false)
    {
        parent::__construct();
        $this->storage = $storage;

        if (null === $io) {
            $io = new IO();
        }
        $this->io = $io;

        $this->templateManager = new TemplateManager();

        $this->accessTokenExpiry = $accessTokenExpiry;
        $this->allowRegExpRedirectUriMatch = (bool) $allowRegExpRedirectUriMatch;

        $compatThis = &$this;

        $this->get(
            '*',
            function (Request $request, UserInfoInterface $userInfo) use ($compatThis) {
                return $compatThis->getAuthorization($request, $userInfo);
            }
        );

        $this->post(
            '*',
            function (Request $request, UserInfoInterface $userInfo) use ($compatThis) {
                return $compatThis->postAuthorization($request, $userInfo);
            }
        );
    }

    public function getAuthorization(Request $request, UserInfoInterface $userInfo)
    {
        $authorizeRequest = new AuthorizeRequest($request);

        $clientId = $authorizeRequest->getClientId();
        $responseType = $authorizeRequest->getResponseType();
        $redirectUri = $authorizeRequest->getRedirectUri();
        $scope = $authorizeRequest->getScope();
        $state = $authorizeRequest->getState();

        $clientData = $this->storage->getClient($clientId);
        if (false === $clientData) {
            throw new BadRequestException('client not registered');
        }
        if (null === $redirectUri) {
            $redirectUri = $clientData->getRedirectUri();
        } else {
            if (!$clientData->verifyRedirectUri($redirectUri, $this->allowRegExpRedirectUriMatch)) {
                throw new BadRequestException(
                    'specified redirect_uri not the same as registered redirect_uri'
                );
            }
            // we now use the provided redirect_uri...
        }

        if ($responseType !== $clientData->getType()) {
            return new ClientResponse(
                $clientData,
                $request,
                $redirectUri,
                array(
                    'error' => 'unsupported_response_type',
                    'error_description' => 'response_type not supported by client profile',
                )
            );
        }

        $scopeObj = new Scope($scope);
        $allowedScopeObj = new Scope($clientData->getAllowedScope());

        if (!$scopeObj->hasOnlyScope($allowedScopeObj)) {
            return new ClientResponse(
                $clientData,
                $request,
                $redirectUri,
                array(
                    'error' => 'invalid_scope',
                    'error_description' => 'not authorized to request this scope',
                )
            );
        }

        if ($clientData->getDisableUserConsent()) {
            // we do not require approval by the user, add implicit approval
            $this->addApproval($clientData, $userInfo->getUserId(), $scope);
        }

        $approval = $this->storage->getApprovalByResourceOwnerId($clientId, $userInfo->getUserId());
        $approvedScopeObj = new Scope($approval['scope']);

        if (false === $approval || false === $scopeObj->hasOnlyScope($approvedScopeObj)) {
            // we do not yet have an approval at all, or client wants more
            // permissions, so we ask the user for approval
            $response = new Response();
            $response->setBody(
                $this->templateManager->render(
                    'askAuthorization',
                    array(
                        'resourceOwnerId' => $userInfo->getUserId(),
                        'sslEnabled' => 'https' === $request->getUrl()->getScheme(),
                        'contactEmail' => $clientData->getContactEmail(),
                        'scopes' => $scopeObj->toArray(),
                        'clientName' => $clientData->getName(),
                        'clientId' => $clientData->getId(),
                        'clientDescription' => $clientData->getDescription(),
                    )
                )
            );

            return $response;
        } else {
            // we already have approval
            if ('token' === $responseType) {
                // implicit grant
                // FIXME: return existing access token if it exists for this exact client, resource owner and scope?
                $accessToken = $this->io->getRandomHex();
                $this->storage->storeAccessToken(
                    $accessToken,
                    $this->io->getTime(),
                    $clientId,
                    $userInfo->getUserId(),
                    $scope,
                    $this->accessTokenExpiry
                );

                return new ClientResponse(
                    $clientData,
                    $request,
                    $redirectUri,
                    array(
                        'access_token' => $accessToken,
                        'expires_in' => $this->accessTokenExpiry,
                        'token_type' => 'bearer',
                        'scope' => $scope,
                    )
                );
            } else {
                // authorization code grant
                $authorizationCode = $this->io->getRandomHex();
                $this->storage->storeAuthorizationCode(
                    $authorizationCode,
                    $userInfo->getUserId(),
                    $this->io->getTime(),
                    $clientId,
                    // we need to store the actual redirect_uri provided, or null if none
                    // was provided...
                    $authorizeRequest->getRedirectUri(),
                    $scope
                );

                return new ClientResponse(
                    $clientData,
                    $request,
                    $redirectUri,
                    array(
                        'code' => $authorizationCode,
                    )
                );
            }
        }
    }

    public function postAuthorization(Request $request, UserInfoInterface $userInfo)
    {
        $authorizeRequest = new AuthorizeRequest($request);

        $clientId = $authorizeRequest->getClientId();
        $responseType = $authorizeRequest->getResponseType();
        $redirectUri = $authorizeRequest->getRedirectUri();
        $scope = $authorizeRequest->getScope();
        $state = $authorizeRequest->getState();

        $clientData = $this->storage->getClient($clientId);
        if (false === $clientData) {
            throw new BadRequestException('client not registered');
        }

        // if no redirect_uri is part of the query parameter, use the one from
        // the client registration
        if (null === $redirectUri) {
            $redirectUri = $clientData->getRedirectUri();
        }

        if ('approve' !== $request->getPostParameter('approval')) {
            return new ClientResponse(
                $clientData,
                $request,
                $redirectUri,
                array(
                    'error' => 'access_denied',
                    'error_description' => 'not authorized by resource owner',
                )
            );
        }

        $this->addApproval($clientData, $userInfo->getUserId(), $scope);

        // redirect to self
        return new RedirectResponse($request->getUrl()->toString(), 302);
    }

    private function addApproval(ClientData $clientData, $userId, $scope)
    {
        $approval = $this->storage->getApprovalByResourceOwnerId($clientData->getId(), $userId);
        if (false === $approval) {
            // no approval exists, generate a refresh_token and add it
            $refreshToken = ('code' === $clientData->getType()) ? $this->io->getRandomHex() : null;
            $this->storage->addApproval($clientData->getId(), $userId, $scope, $refreshToken);
        } else {
            // an approval exists, we don't care about the scope, we just
            // update it if needed keeping the same refresh_token
            $this->storage->updateApproval($clientData->getId(), $userId, $scope);
        }
    }
}
