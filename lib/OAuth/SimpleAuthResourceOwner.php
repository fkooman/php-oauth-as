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

use fkooman\Config\Config;
use fkooman\Json\Json;

use fkooman\SimpleAuth\SimpleAuth;

class SimpleAuthResourceOwner implements IResourceOwner
{
    /** @var fkooman\Config\Config */
    private $config;

    private $simpleAuth;
    private $resourceOwnerHint;

    public function __construct(Config $config)
    {
        $this->config = $config;

        $bPath = $this->config->s('SimpleAuthResourceOwner')->l('simpleAuthPath') . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
        if (!file_exists($bPath) || !is_file($bPath) || !is_readable($bPath)) {
            throw new SimpleAuthResourceOwnerException("invalid path to php-simple-auth");
        }
        require_once $bPath;

        $this->simpleAuth = new \fkooman\SimpleAuth\SimpleAuth();
    }

    public function setResourceOwnerHint($resourceOwnerHint)
    {
        $this->resourceOwnerHint = $resourceOwnerHint;
    }

    public function getId()
    {
        return $this->simpleAuth->authenticate($this->resourceOwnerHint);
    }

    public function getEntitlement()
    {
        $entitlementFile = $this->config->s('SimpleAuthResourceOwner')->l('entitlementFile');
        $fileContents = @file_get_contents($entitlementFile);
        if (FALSE === $fileContents) {
            // no entitlement file, so no entitlement
            return array();
        }
        $entitlement = Json::decode($fileContents);
        if (is_array($entitlement) && isset($entitlement[$this->getId()]) && is_array($entitlement[$this->getId()])) {
            return $entitlement[$this->getId()];
        }

        return array();
    }

    public function getExt()
    {
        // unsupported
        return array();
    }

}
