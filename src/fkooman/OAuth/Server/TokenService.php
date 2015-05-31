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

use fkooman\Rest\Service;
use fkooman\Rest\Plugin\UserInfo;
use fkooman\Http\Request;
use fkooman\Http\Exception\BadRequestException;
use fkooman\Http\JsonResponse;
use RuntimeException;

class TokenService extends Service
{
    /** @var fkooman\OAuth\Server\PdoStorage */
    private $db;

    /** @var fkooman\OAuth\Server\IO */
    private $io;

    /** @var int */
    private $accessTokenExpiry;

    public function __construct(PdoStorage $db, IO $io = null, $accessTokenExpiry = 3600)
    {
        parent::__construct();
        $this->setPathInfoRedirect(false);

        $this->db = $db;

        if (null === $io) {
            $io = new IO();
        }
        $this->io = $io;

        $this->accessTokenExpiry = (int) $accessTokenExpiry;

        $compatThis = &$this;

        $this->post(
            '*',
            function (Request $request, UserInfo $userInfo) use ($compatThis) {
                return $compatThis->postToken($request, $userInfo);
            }
        );
    }

    public function postToken(Request $request, UserInfo $userInfo)
    {
        $tokenRequest = new TokenRequest($request);

        $grantType = $tokenRequest->getGrantType();
        $clientId = $tokenRequest->getClientId();

        // the userId from Basic Autentication is the same as the client_id
        $userId = $userInfo->getUserId();

        $clientData = $this->db->getClient($userId);
        if (false === $clientData) {
            throw new RuntimeException('authenticated, but client no longer exists');
        }

        if (null !== $clientId) {
            if ($clientId !== $userId) {
                throw new BadRequestException(
                    'invalid_grant',
                    'authenicated user must match client_id in request body'
                );
            }
        }

        if ('code' !== $clientData->getType()) {
            throw new BadRequestException(
                'invalid_client',
                'this client type is not allowed to use the token endpoint'
            );
        }

        switch ($grantType) {
            case 'authorization_code':
                $accessToken = $this->handleCode($tokenRequest, $clientData);
                break;
            case 'refresh_token':
                $accessToken = $this->handleRefreshToken($tokenRequest, $clientData);
                break;
            default:
                throw new BadRequestException('invalid_request', 'unsupported grant_type');
        }

        $response = new JsonResponse();
        $response->setHeaders(array('Cache-Control' => 'no-store', 'Pragma' => 'no-cache'));
        $response->setContent($accessToken);

        return $response;
    }

    public function handleCode(TokenRequest $tokenRequest, ClientData $clientData)
    {
        $code = $tokenRequest->getCode();
        $redirectUri = $tokenRequest->getRedirectUri();

        // If the redirect_uri was present in the authorize request, it MUST also be there
        // in the token request. If it was not there in authorize request, it MUST NOT be
        // there in the token request (this is not explicit in the spec!)
        $result = $this->db->getAuthorizationCode($clientData->getId(), $code, $redirectUri);
        if (false === $result) {
            throw new BadRequestException('invalid_grant', 'the authorization code was not found');
        }

        if ($this->io->getTime() > $result['issue_time'] + 600) {
            throw new BadRequestException('invalid_grant', 'the authorization code expired');
        }

        // we MUST be able to delete the authorization code, otherwise it was used before
        if (false === $this->db->deleteAuthorizationCode($clientData->getId(), $code, $redirectUri)) {
            // check to prevent deletion race condition
            throw new BadRequestException('invalid_grant', 'this authorization code grant was already used');
        }

        $approval = $this->db->getApprovalByResourceOwnerId($clientData->getId(), $result['resource_owner_id']);

        $token = array();
        $token['access_token'] = $this->io->getRandomHex();
        $token['expires_in'] = $this->accessTokenExpiry;
        // we always grant the scope the user authorized, no further restrictions here...
        // FIXME: the merging of authorized scopes in the authorize function is a bit of a mess!
        // we should deal with that there and come up with a good solution...
        $token['scope'] = $result['scope'];
        $token['refresh_token'] = $approval['refresh_token'];
        $token['token_type'] = 'bearer';

        $this->db->storeAccessToken(
            $token['access_token'],
            $this->io->getTime(),
            $clientData->getId(),
            $result['resource_owner_id'],
            $token['scope'],
            $token['expires_in']
        );

        return $token;
    }

    public function handleRefreshToken(TokenRequest $tokenRequest, ClientData $clientData)
    {
        $refreshToken = $tokenRequest->getRefreshToken();
        $scope = $tokenRequest->getScope();

        $result = $this->db->getApprovalByRefreshToken($clientData->getId(), $refreshToken);
        if (false === $result) {
            throw new BadRequestException('invalid_grant', 'the refresh_token was not found');
        }

        $token = array();
        $token['access_token'] = $this->io->getRandomHex();
        $token['expires_in'] = $this->accessTokenExpiry;
        if (null !== $scope) {
            // the client wants to obtain a specific scope
            $requestedScope = new Scope($scope);
            $authorizedScope = new Scope($result['scope']);
            if ($requestedScope->hasOnlyScope($authorizedScope)) {
                // if it is a subset of the authorized scope we honor that
                $token['scope'] = $requestedScope->toString();
            } else {
                // if not the client gets the authorized scope
                $token['scope'] = $result['scope'];
            }
        } else {
            $token['scope'] = $result['scope'];
        }

        $token['token_type'] = 'bearer';

        $this->db->storeAccessToken(
            $token['access_token'],
            $this->io->getTime(),
            $clientData->getId(),
            $result['resource_owner_id'],
            $token['scope'],
            $token['expires_in']
        );

        return $token;
    }
}
