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

use fkooman\OAuth\Common\Scope;
use fkooman\Http\Uri;
use fkooman\OAuth\Server\Exception\AuthorizeResultException;

class AuthorizeResult
{
    const REDIRECT     = 100;
    const ASK_APPROVAL = 200;

    private $action;
    private $redirectUri;
    private $client;
    private $scope;

    public function __construct($action)
    {
        if (!in_array($action, array (self::REDIRECT, self::ASK_APPROVAL))) {
            throw new AuthorizeResultException("invalid action");
        }
        $this->action = $action;
        $this->redirectUri = null;
        $this->client = null;
        $this->scope = null;
    }

    public function getAction()
    {
        return $this->action;
    }

    public function setRedirectUri(Uri $u)
    {
        if (self::REDIRECT !== $this->action) {
            throw new AuthorizeResultException("cannot set url for this action");
        }
        $this->redirectUri = $u;
    }

    public function getRedirectUri()
    {
        if (self::REDIRECT !== $this->action) {
            throw new AuthorizeResultException("cannot get url for this action");
        }

        return $this->redirectUri;
    }

    public function setClient(ClientRegistration $c)
    {
        if (self::ASK_APPROVAL !== $this->action) {
            throw new AuthorizeResultException("cannot set client for this action");
        }
        $this->client = $c;
    }

    public function getClient()
    {
        if (self::ASK_APPROVAL !== $this->action) {
            throw new AuthorizeResultException("cannot get client for this action");
        }

        return $this->client;
    }

    public function setScope(Scope $s)
    {
        if (self::ASK_APPROVAL !== $this->action) {
            throw new AuthorizeResultException("cannot set scope for this action");
        }
        $this->scope = $s;
    }

    public function getScope()
    {
        if (self::ASK_APPROVAL !== $this->action) {
            throw new AuthorizeResultException("cannot get scope for this action");
        }

        return $this->scope;
    }
}
