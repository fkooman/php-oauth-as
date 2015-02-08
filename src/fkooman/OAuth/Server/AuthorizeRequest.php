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

class AuthorizeRequest
{
    // VSCHAR     = %x20-7E
    const REGEXP_VSCHAR = '/^(?:[\x20-\x7E])*$/';
    const REGEXP_SCOPE_TOKEN = '/^(?:\x21|[\x23-\x5B]|[\x5D-\x7E])+$/';
    const MAX_LEN = 255;

    /** @var string */
    private $clientId;

    /** @var string */
    private $responseType;

    /** @var string */
    private $redirectUri;

    /** @var string */
    private $scope;

    /** @var string */
    private $state;

    public function __construct(Request $request)
    {
        $this->setClientId($request->getQueryParameter('client_id'));
        $this->setResponseType($request->getQueryParameter('response_type'));
        $this->setRedirectUri($request->getQueryParameter('redirect_uri'));
        $this->setScope($request->getQueryParameter('scope'));
        $this->setState($request->getQueryParameter('state'));
    }

    private function checkString($str, $name)
    {
        if (null === $str || !is_string($str) || 0 >= strlen($str) || 255 < strlen($str)) {
            throw new BadRequestException(
                sprintf(
                    '"%s" must be a non-empty string with maximum length %s',
                    $name,
                    self::MAX_LEN
                )
            );
        }
    }

    public function setClientId($clientId)
    {
        $this->checkString($clientId, 'client_id');

        if (1 !== preg_match(self::REGEXP_VSCHAR, $clientId)) {
            throw new BadRequestException('client_id contains invalid characters');
        }
        $this->clientId = $clientId;
    }

    public function getClientId()
    {
        return $this->clientId;
    }
    
    public function setResponseType($responseType)
    {
        $this->checkString($responseType, 'response_type');
        
        if (!in_array($responseType, array('code', 'token'))) {
            throw new BadRequestException('response_type contains unsupported response_type');
        }
        $this->responseType = $responseType;
    }

    public function getResponseType()
    {
        return $this->responseType;
    }
        
    public function setRedirectUri($redirectUri)
    {
        if (empty($redirectUri)) {
            return;
        }
        $this->checkString($redirectUri, 'redirect_uri');

        if (false === filter_var($redirectUri, FILTER_VALIDATE_URL)) {
            throw new BadRequestException('redirect_uri MUST be a valid URL');
        }
        // not allowed to have a fragment (#) in it
        if (false !== strpos($redirectUri, '#')) {
            throw new BadRequestException('redirect_uri MUST NOT contain a fragment');
        }
        $this->redirectUri = $redirectUri;
    }

    public function getRedirectUri()
    {
        return $this->redirectUri;
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
                throw new BadRequestException('scope token must be a non-empty string');
            }
            if (1 !== preg_match(self::REGEXP_SCOPE_TOKEN, $scopeToken)) {
                throw new BadRequestException('scope token contains invalid characters');
            }
        }
        $this->scope = $scope;
    }

    public function getScope()
    {
        return $this->scope;
    }

    public function setState($state)
    {
        $this->checkString($state, 'state');

        if (1 !== preg_match(self::REGEXP_VSCHAR, $state)) {
            throw new BadRequestException('state contains invalid characters');
        }
        $this->state = $state;
    }

    public function getState()
    {
        return $this->state;
    }
}
