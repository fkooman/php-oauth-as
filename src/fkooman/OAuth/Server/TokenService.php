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
use fkooman\Rest\Plugin\Basic\BasicUserInfo;
use fkooman\Http\Request;
use fkooman\Http\Exception\BadRequestException;
use fkooman\OAuth\Common\Scope;
use fkooman\Http\JsonResponse;

class TokenService extends Service
{
    /** @var fkooman\OAuth\Server\PdoStorage */
    private $db;

    /** @var int */
    private $accessTokenExpiry;

    public function __construct(PdoStorage $db, $accessTokenExpiry = 3600)
    {
        parent::__construct();

        $this->db = $db;
        $this->accessTokenExpiry = (int) $accessTokenExpiry;

        $compatThis = &$this;

        $this->post(
            '/',
            function (Request $request, BasicUserInfo $basicUserInfo) use ($compatThis) {
                return $compatThis->postToken($request, $basicUserInfo);
            }
        );
    }

    public function postToken(Request $request, BasicUserInfo $basicUserInfo)
    {
        $userId = $basicUserInfo->getUserId();

        $clientData = $this->db->getClient($userId);
        if (false === $clientData) {
            // weird, client does not exist, but was able to authenticate?
            // is part of handling public clients...
            throw new \Exception("invalid_client", "client does not exist");
        }

        // verify the information
        $grantType    = $request->getPostParameter('grant_type');
        // FIXME: validate grant_type
        $clientId     = $request->getPostParameter('client_id');
        // FIXME: validate client_id

        if (null !== $clientId) {
            if ($clientId !== $userId) {
                throw new BadRequestException(
                    "invalid_grant",
                    "authenicated user must match client_id in request body"
                );
            }
        }

        if ("code" !== $clientData->getType()) {
            throw new BadRequestException(
                "invalid_client",
                "this client type is not allowed to use the token endpoint"
            );
        }

        if (null === $grantType) {
            throw new BadRequestException("invalid_request", "the grant_type parameter is missing");
        }

        if ('authorization_code' === $grantType) {
            $accessToken = $this->handleCode($request, $clientData);
        } elseif ('refresh_token' === $grantType) {
            $accessToken = $this->handleRefreshToken($request, $clientData);
        } else {
            throw new BadRequestException('invalid_request', 'unsupported grant_type');
        }

        $response = new JsonResponse();
        $response->setHeader('Cache-Control', 'no-store');
        $response->setHeader('Pragma', 'no-cache');
        $response->setContent($accessToken);

        return $response;
    }

    public function handleCode(Request $request, ClientData $clientData)
    {
        $code = $request->getPostParameter('code');
        // FIXME: validate code
        $redirectUri = $request->getPostParameter('redirect_uri');
        // FIXME: validate redirect_uri

        if (null === $code) {
            throw new BadRequestException("invalid_request", "the code parameter is missing");
        }
        // If the redirect_uri was present in the authorize request, it MUST also be there
        // in the token request. If it was not there in authorize request, it MUST NOT be
        // there in the token request (this is not explicit in the spec!)
        $result = $this->db->getAuthorizationCode($clientData->getId(), $code, $redirectUri);
        if (false === $result) {
            throw new BadRequestException("invalid_grant", "the authorization code was not found");
        }
        if (time() > $result['issue_time'] + 600) {
            throw new BadRequestException("invalid_grant", "the authorization code expired");
        }

        // we MUST be able to delete the authorization code, otherwise it was used before
        if (false === $this->db->deleteAuthorizationCode($clientData->getId(), $code, $redirectUri)) {
            // check to prevent deletion race condition
            throw new BadRequestException("invalid_grant", "this authorization code grant was already used");
        }

        $approval = $this->db->getApprovalByResourceOwnerId($clientData->getId(), $result['resource_owner_id']);

        $token = array();
        $token['access_token'] = bin2hex(openssl_random_pseudo_bytes(16));
        $token['expires_in'] = $this->accessTokenExpiry;
        // we always grant the scope the user authorized, no further restrictions here...
        // FIXME: the merging of authorized scopes in the authorize function is a bit of a mess!
        // we should deal with that there and come up with a good solution...
        $token['scope'] = $result['scope'];
        $token['refresh_token'] = $approval['refresh_token'];
        $token['token_type'] = "bearer";
        $this->db->storeAccessToken(
            $token['access_token'],
            time(),
            $clientData->getId(),
            $result['resource_owner_id'],
            $token['scope'],
            $token['expires_in']
        );

        return $token;
    }

    public function handleRefreshToken(Request $request, ClientData $clientData)
    {
        $refreshToken = $request->getPostParameter('refresh_token');
        // FIXME: validate refresh_token

        $scope = $request->getPostParameter('scope');
        // FIXME: validate scope

        if (null === $refreshToken) {
            throw new BadRequestException("invalid_request", "the refresh_token parameter is missing");
        }
        $result = $this->db->getApprovalByRefreshToken($clientData->getId(), $refreshToken);
        if (false === $result) {
            throw new BadRequestException("invalid_grant", "the refresh_token was not found");
        }

        $token = array();
        $token['access_token'] = bin2hex(openssl_random_pseudo_bytes(16));
        $token['expires_in'] = $this->accessTokenExpiry;
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
        $this->db->storeAccessToken(
            $token['access_token'],
            time(),
            $clientData->getId(),
            $result['resource_owner_id'],
            $token['scope'],
            $token['expires_in']
        );

        return $token;
    }
}
