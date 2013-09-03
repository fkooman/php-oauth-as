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
use \PersonaVerifier as PersonaVerifier;
use \RestService\Utils\Json as Json;

class PersonaResourceOwner implements IResourceOwner
{
    private $_c;
    private $_verifier;

    public function __construct(Config $c)
    {
        $this->_c = $c;

        $bPath = $this->_c->getSectionValue('PersonaResourceOwner', 'personaPath') . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'PersonaVerifier.php';
        if (!file_exists($bPath) || !is_file($bPath) || !is_readable($bPath)) {
            throw new PersonaResourceOwnerException("invalid path to php-browserid");
        }
        require_once $bPath;

        $this->_verifier = new PersonaVerifier($this->_c->getSectionValue('PersonaResourceOwner', 'verifierAddress'));
    }

    public function setResourceOwnerHint($resourceOwnerHint)
    {
        // nop
    }

    public function getId()
    {
        return $this->_verifier->authenticate();
    }

    public function getEntitlement()
    {
        $entitlementFile = $this->_c->getSectionValue('PersonaResourceOwner', 'entitlementFile');
        $fileContents = @file_get_contents($entitlementFile);
        if (FALSE === $fileContents) {
            // no entitlement file, so no entitlement
            return array();
        }
        $entitlement = Json::dec($fileContents);
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
