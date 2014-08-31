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

use fkooman\Config\Config;
use fkooman\Json\Json;
use fkooman\Json\JsonException;
use RuntimeException;

use fkooman\SimpleAuth\SimpleAuth;
use fkooman\OAuth\Server\Exception\SimpleAuthResourceOwnerException;

class SimpleAuthResourceOwner implements IResourceOwner
{
    /** @var fkooman\Config\Config */
    private $config;

    private $simpleAuth;
    private $resourceOwnerHint;

    public function __construct(Config $config)
    {
        $this->config = $config;

        $bPath = $this->config->s('SimpleAuthResourceOwner')->l('simpleAuthPath') . '/vendor/autoload.php';
        if (!file_exists($bPath) || !is_file($bPath) || !is_readable($bPath)) {
            throw new SimpleAuthResourceOwnerException("invalid path to php-simple-auth");
        }
        require_once $bPath;

        $this->simpleAuth = new SimpleAuth();
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
        $entitlement = array();
        try {
            $entitlement = Json::decodeFromFile($this->config->l('entitlementsFile'));
        } catch (RuntimeException $e) {
            // problem with reading the entitlement file
            return array();
        } catch (JsonException $e) {
            // problem with the JSON formatting of entitlement file
            return array();
        }
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
