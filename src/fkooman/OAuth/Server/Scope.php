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

use InvalidArgumentException;

class Scope
{
    /** @var array */
    private $scope;

    public function __construct($scope = null)
    {
        if (null === $scope) {
            $this->scope = array();
        } else {
            if (!is_string($scope)) {
                throw new InvalidArgumentException('argument must be string');
            }
            if (0 === strlen($scope)) {
                $this->scope = array();
            } else {
                $scopeTokens = explode(' ', $scope);
                foreach ($scopeTokens as $token) {
                    $this->validateScopeToken($token);
                }
                sort($scopeTokens, SORT_STRING);
                $this->scope = array_values(array_unique($scopeTokens, SORT_STRING));
            }
        }
    }

    private function validateScopeToken($scopeToken)
    {
        if (!is_string($scopeToken) || 0 >= strlen($scopeToken)) {
            throw new InvalidArgumentException('scope token must be a non-empty string');
        }
        if (1 !== preg_match('/^(?:\x21|[\x23-\x5B]|[\x5D-\x7E])+$/', $scopeToken)) {
            throw new InvalidArgumentException('invalid characters in scope token');
        }
    }

    public function hasOnlyScope(Scope $that)
    {
        if (0 === count($this->toArray())) {
            return true;
        }

        foreach ($this->toArray() as $s) {
            if (!in_array($s, $that->toArray())) {
                return false;
            }
        }

        return true;
    }

    public function hasScope($scopeToken)
    {
        $this->validateScopeToken($scopeToken);

        return in_array($scopeToken, $this->scope);
    }

    public function toArray()
    {
        return $this->scope;
    }

    public function toString()
    {
        return implode(' ', $this->scope);
    }

    public function __toString()
    {
        return $this->toString();
    }
}
