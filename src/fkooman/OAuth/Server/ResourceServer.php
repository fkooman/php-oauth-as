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

use fkooman\Json\Json;
use fkooman\OAuth\Common\Scope;
use fkooman\OAuth\Server\Exception\ResourceServerException;

class ResourceServer
{
    private $storage;
    private $entitlementEnforcement;
    private $resourceOwnerId;
    private $grantedScope;
    private $resourceOwnerEntitlement;
    private $resourceOwnerExt;

    public function __construct(PdoStorage $s)
    {
        $this->storage = $s;
        $this->entitlementEnforcement = true;
        $this->resourceOwnerId = null;
        $this->grantedScope = null;
        $this->resourceOwnerEntitlement = array();
        $this->resourceOwnerExt = array();
    }

    public function verifyAuthorizationHeader($authorizationHeader)
    {
        if (null === $authorizationHeader) {
            throw new ResourceServerException("no_token", "no authorization header in the request");
        }
        // b64token = 1*( ALPHA / DIGIT / "-" / "." / "_" / "~" / "+" / "/" ) *"="
        $b64TokenRegExp = '(?:[[:alpha:][:digit:]-._~+/]+=*)';
        $result = preg_match('|^Bearer (?P<value>'.$b64TokenRegExp.')$|', $authorizationHeader, $matches);
        if ($result === false || $result === 0) {
            throw new ResourceServerException("invalid_token", "the access token is malformed");
        }
        $accessToken = $matches['value'];
        $token = $this->storage->getAccessToken($accessToken);
        if (false === $token) {
            throw new ResourceServerException("invalid_token", "the access token is invalid");
        }
        if (time() > $token['issue_time'] + $token['expires_in']) {
            throw new ResourceServerException("invalid_token", "the access token expired");
        }
        $this->resourceOwnerId = $token['resource_owner_id'];
        $this->grantedScope = $token['scope'];
        $resourceOwner = $this->storage->getResourceOwner($token['resource_owner_id']);
        $j = new Json();
        $this->resourceOwnerEntitlement = $j->decode($resourceOwner['entitlement']);
        $this->resourceOwnerExt = $j->decode($resourceOwner['ext']);
    }

    public function setEntitlementEnforcement($enforce = true)
    {
        $this->entitlementEnforcement = $enforce;
    }

    public function getResourceOwnerId()
    {
        // FIXME: should we die when the resourceOwnerId is NULL?
        return $this->resourceOwnerId;
    }

    public function getEntitlement()
    {
        return $this->resourceOwnerEntitlement;
    }

    public function hasScope($scope)
    {
        $grantedScope = Scope::fromString($this->grantedScope);
        $requiredScope = Scope::fromString($scope);

        return $grantedScope->hasScope($requiredScope);
    }

    public function requireScope($scope)
    {
        if (false === $this->hasScope($scope)) {
            throw new ResourceServerException("insufficient_scope", "no permission for this call with granted scope");
        }
    }

    public function hasEntitlement($entitlement)
    {
        return in_array($entitlement, $this->resourceOwnerEntitlement);
    }

    public function requireEntitlement($entitlement)
    {
        if ($this->entitlementEnforcement) {
            if (false === $this->hasEntitlement($entitlement)) {
                throw new ResourceServerException(
                    "insufficient_entitlement",
                    "no permission for this call with granted entitlement"
                );
            }
        }
    }

    public function getExt()
    {
        return $this->resourceOwnerExt;
    }

    public function getExtKey($key)
    {
        $ext = $this->getExt();

        return array_key_exists($key, $ext) ? $ext[$key] : null;
    }
}
