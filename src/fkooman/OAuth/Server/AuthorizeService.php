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

use Twig_Loader_Filesystem;
use Twig_Environment;
use fkooman\Rest\Service;
use fkooman\Http\Request;
use fkooman\Http\Exception\BadRequestException;
use fkooman\Http\RedirectResponse;
use fkooman\Rest\Plugin\UserInfo;

class AuthorizeService extends Service
{
    /** @var fkooman\OAuth\Server\PdoStorage */
    private $storage;

    /** @var int */
    private $accessTokenExpiry;

    /** @var bool */
    private $allowRegExpRedirectUriMatch;

    public function __construct(PdoStorage $storage, $accessTokenExpiry = 3600, $allowRegExpRedirectUriMatch = false)
    {
        parent::__construct();

        $this->storage = $storage;
        $this->accessTokenExpiry = $accessTokenExpiry;
        $this->allowRegExpRedirectUriMatch = (bool) $allowRegExpRedirectUriMatch;

        $compatThis = &$this;

        $this->get(
            '/',
            function (Request $request, UserInfo $userInfo) use ($compatThis) {
                return $compatThis->getAuthorization($request, $userInfo);
            }
        );

        $this->post(
            '/',
            function (Request $request, UserInfo $userInfo) use ($compatThis) {
                return $compatThis->postAuthorization($request, $userInfo);
            }
        );
    }

    public function getAuthorization(Request $request, UserInfo $userInfo)
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
                    'error_description' => 'response_type not supported by client profile'
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
                    'error_description' => 'not authorized to request this scope'
                )
            );
        }
        
        if ($clientData->getDisableUserConsent()) {
            // we do not require approval by the user
            $approvedScope = array('scope' => $scope);
        } else {
            $approvedScope = $this->storage->getApprovalByResourceOwnerId($clientId, $userInfo->getUserId());
        }
    
        $approvedScopeObj = new Scope($approvedScope['scope']);

        // FIXME: why the || ???
        if (false === $approvedScope || false === $scopeObj->hasOnlyScope($approvedScopeObj)) {
            // we need to ask for approval
            $twig = $this->getTwig();
            return $twig->render(
                'askAuthorization.twig',
                array(
                    'resourceOwnerId' => $userInfo->getUserId(),
                    'sslEnabled' => 'https' === $request->getRequestUri()->getScheme(),
                    'contactEmail' => $clientData->getContactEmail(),
                    'scopes' => $scopeObj->toArray(),
                    'clientName' => $clientData->getName(),
                    'clientId' => $clientData->getId(),
                    'clientDescription' => $clientData->getDescription()
                )
            );
        } else {
            // we already have approval
            if ('token' === $responseType) {
                // implicit grant
                // FIXME: return existing access token if it exists for this exact client, resource owner and scope?
                $accessToken = bin2hex(openssl_random_pseudo_bytes(16));
                $this->storage->storeAccessToken(
                    $accessToken,
                    time(),
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
                        'scope' => $scope
                    )
                );
            } else {
                // authorization code grant
                $authorizationCode = bin2hex(openssl_random_pseudo_bytes(16));
                $this->storage->storeAuthorizationCode(
                    $authorizationCode,
                    $userInfo->getUserId(),
                    time(),
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
                        'code' => $authorizationCode
                    )
                );
            }
        }
    }

    public function postAuthorization(Request $request, UserInfo $userInfo)
    {
        $authorizeRequest = new AuthorizeRequest($request);

        $clientId = $authorizeRequest->getClientId();
        $responseType = $authorizeRequest->getResponseType();
        $redirectUri = $authorizeRequest->getRedirectUri();
        $scope = $authorizeRequest->getScope();
        $state = $authorizeRequest->getState();

        if ($request->getHeader('HTTP_REFERER') !== $request->getRequestUri()->getUri()) {
            throw new BadRequestException('CSRF protection triggered');
        }

        $clientData = $this->storage->getClient($clientId);
        if (false === $clientData) {
            throw new BadRequestException('client not registered');
        }

        if ('approve' !== $request->getPostParameter('approval')) {
            return new ClientResponse(
                $clientData,
                $request,
                $redirectUri,
                array(
                    'error' => 'access_denied',
                    'error_description' => 'not authorized by resource owner'
                )
            );
        }

        $approvedScope = $this->storage->getApprovalByResourceOwnerId($clientId, $userInfo->getUserId());
        // FIXME: why no if here?
        if (false === $approvedScope) {
            // no approved scope stored yet, new entry
            $refreshToken = ('code' === $responseType) ? bin2hex(openssl_random_pseudo_bytes(16)) : null;
            $this->storage->addApproval($clientId, $userInfo->getUserId(), $scope, $refreshToken);
        } else {
            // FIXME: update merges the scopes?
            $this->storage->updateApproval($clientId, $userInfo->getUserId(), $scope);
        }

        // redirect back to the authorize uri, this time there should be an
        // approval...
        // FIXME: maybe move the already having approval code from getAuthorize
        // in a separate function as to avoid this extra ugly 'redirect'
        return new RedirectResponse($request->getRequestUri()->getUri(), 302);
    }

    private function getTwig()
    {
        $configTemplateDir = dirname(dirname(dirname(dirname(__DIR__)))).'/config/views';
        $defaultTemplateDir = dirname(dirname(dirname(dirname(__DIR__)))).'/views';
        $templateDirs = array();
        if (false !== is_dir($configTemplateDir)) {
            $templateDirs[] = $configTemplateDir;
        }
        $templateDirs[] = $defaultTemplateDir;
        return new Twig_Environment(
            new Twig_Loader_Filesystem($templateDirs)
        );
    }
}
