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

use fkooman\Http\Request;
use fkooman\Http\Exception\BadRequestException;

class TokenRequest
{
    // VSCHAR     = %x20-7E
    const REGEXP_VSCHAR = '/^(?:[\x20-\x7E])*$/';
    const REGEXP_SCOPE_TOKEN = '/^(?:\x21|[\x23-\x5B]|[\x5D-\x7E])+$/';
    const MAX_LEN = 255;

    /** @var string */
    private $grantType;

    /** @var string */
    private $code;

    /** @var string */
    private $redirectUri;

    /** @var string */
    private $clientId;

    /** @var string */
    private $refreshToken;

    /** @var string */
    private $scope;

    public function __construct(Request $request)
    {
        $this->setGrantType($request->getPostParameter('grant_type'));
        $this->setCode($request->getPostParameter('code'));
        $this->setRedirectUri($request->getPostParameter('redirect_uri'));
        $this->setClientId($request->getPostParameter('client_id'));
        $this->setRefreshToken($request->getPostParameter('refresh_token'));
        $this->setScope($request->getPostParameter('scope'));

        // some additional validation
        if ('authorization_code' === $this->getGrantType() && null === $this->getCode()) {
            throw new BadRequestException('invalid_requst', 'for authorization_code grant type a code must be provided');
        }
        if ('refresh_token' === $this->getGrantType() && null === $this->getRefreshToken()) {
            throw new BadRequestException('invalid_request', 'for refresh_token grant type a refresh_token must be provided');
        }
    }

    private function checkString($str, $name)
    {
        if (null === $str || !is_string($str) || 0 >= strlen($str) || 255 < strlen($str)) {
            throw new BadRequestException(
                'invalid_request',
                sprintf(
                    '"%s" must be a non-empty string with maximum length %s',
                    $name,
                    self::MAX_LEN
                )
            );
        }
    }

    public function setGrantType($grantType)
    {
        $this->checkString($grantType, 'grant_type');

        if (!in_array($grantType, array('authorization_code', 'refresh_token'))) {
            throw new BadRequestException('invalid_request', 'grant_type contains unsupported grant_type');
        }
        $this->grantType = $grantType;
    }

    public function getGrantType()
    {
        return $this->grantType;
    }

    public function setCode($code)
    {
        if (empty($code)) {
            return;
        }
        $this->checkString($code, 'code');

        if (1 !== preg_match(self::REGEXP_VSCHAR, $code)) {
            throw new BadRequestException('invalid_request', 'code contains invalid characters');
        }

        $this->code = $code;
    }

    public function getCode()
    {
        return $this->code;
    }

    public function setRedirectUri($redirectUri)
    {
        if (empty($redirectUri)) {
            return;
        }
        $this->checkString($redirectUri, 'redirect_uri');

        if (false === filter_var($redirectUri, FILTER_VALIDATE_URL)) {
            throw new BadRequestException('invalid_request', 'redirect_uri MUST be a valid URL');
        }
        // not allowed to have a fragment (#) in it
        if (false !== strpos($redirectUri, '#')) {
            throw new BadRequestException('invalid_request', 'redirect_uri MUST NOT contain a fragment');
        }
        $this->redirectUri = $redirectUri;
    }

    public function getRedirectUri()
    {
        return $this->redirectUri;
    }

    public function setClientId($clientId)
    {
        if (empty($clientId)) {
            return;
        }
        $this->checkString($clientId, 'client_id');

        if (1 !== preg_match(self::REGEXP_VSCHAR, $clientId)) {
            throw new BadRequestException('invalid_request', 'client_id contains invalid characters');
        }
        $this->clientId = $clientId;
    }

    public function getClientId()
    {
        return $this->clientId;
    }

    public function setRefreshToken($refreshToken)
    {
        if (empty($refreshToken)) {
            return;
        }
        $this->checkString($refreshToken, 'refresh_token');

        if (1 !== preg_match(self::REGEXP_VSCHAR, $refreshToken)) {
            throw new BadRequestException('invalid_request', 'refresh_token contains invalid characters');
        }

        $this->refreshToken = $refreshToken;
    }

    public function getRefreshToken()
    {
        return $this->refreshToken;
    }

    public function setScope($scope)
    {
        if (empty($scope)) {
            return;
        }
        $this->checkString($scope, 'scope');

        $scopeTokens = explode(' ', $scope);
        foreach ($scopeTokens as $scopeToken) {
            if (0 >= strlen($scopeToken)) {
                throw new BadRequestException('invalid_request', 'scope token must be a non-empty string');
            }
            if (1 !== preg_match(self::REGEXP_SCOPE_TOKEN, $scopeToken)) {
                throw new BadRequestException('invalid_request', 'scope token contains invalid characters');
            }
        }
        $this->scope = $scope;
    }

    public function getScope()
    {
        return $this->scope;
    }
}
