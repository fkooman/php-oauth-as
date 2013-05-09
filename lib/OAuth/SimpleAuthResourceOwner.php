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

namespace OAuth;

use \RestService\Utils\Config as Config;
use \SimpleAuth as SimpleAuth;
use \RestService\Utils\Json as Json;

class SimpleAuthResourceOwner implements IResourceOwner
{
    private $_config;
    private $_simpleAuth;
    private $_resourceOwnerIdHint;

    public function __construct(Config $c)
    {
        $this->_c = $c;

        $bPath = $this->_c->getSectionValue('SimpleAuthResourceOwner', 'simpleAuthPath') . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'SimpleAuth.php';
        if (!file_exists($bPath) || !is_file($bPath) || !is_readable($bPath)) {
            throw new SimpleAuthResourceOwnerException("invalid path to php-simple-auth");
        }
        require_once $bPath;

        $this->_simpleAuth = new SimpleAuth();
    }

    public function getId()
    {
        return $this->_simpleAuth->authenticate($this->_resourceOwnerIdHint);
    }

    public function getDisplayName()
    {
        // we just return the user names
        return $this->getId();
    }

    public function getEntitlements()
    {
        $attributesFile = $this->_c->getSectionValue('SimpleAuthResourceOwner', 'entitlementsFile');
        $fileContents = @file_get_contents($attributesFile);
        if (FALSE === $fileContents) {
            // no entitlements file, so no entitlements
            return array();
        }
        $entitlements = Json::dec($fileContents);
        if (is_array($entitlements) && isset($entitlements[$this->getId()]) && is_array($entitlements[$this->getId()])) {
            return $entitlements[$this->getId()];
        }

        return array();
    }

    public function getAttributes()
    {
        // unsupported
        return array();
    }

}
