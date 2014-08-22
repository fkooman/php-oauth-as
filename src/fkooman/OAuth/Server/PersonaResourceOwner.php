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
use fkooman\OAuth\Server\Exception\PersonaResourceOwnerException;
use PersonaVerifier;

class PersonaResourceOwner implements IResourceOwner
{
    /** @var fkooman\Config\Config */
    private $config;

    private $verifier;

    public function __construct(Config $c)
    {
        $this->config = $c;

        $bPath = $this->config->s('PersonaResourceOwner')->l('personaPath') . '/lib/PersonaVerifier.php';
        if (!file_exists($bPath) || !is_file($bPath) || !is_readable($bPath)) {
            throw new PersonaResourceOwnerException("invalid path to php-browserid");
        }
        require_once $bPath;

        $this->verifier = new PersonaVerifier($this->config->s('PersonaResourceOwner')->l('verifierAddress'));
    }

    public function setResourceOwnerHint($resourceOwnerHint)
    {
        // nop
    }

    public function getId()
    {
        return $this->verifier->authenticate();
    }

    public function getEntitlement()
    {
        $entitlementsFile = $this->config->l('entitlementsFile');
        $fileContents = @file_get_contents($entitlementsFile);
        if (false === $fileContents) {
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
