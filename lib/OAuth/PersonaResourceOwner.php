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

    public function getId()
    {
        return $this->_verifier->authenticate();
    }

    public function getDisplayName()
    {
        // we just return the email address
        return $this->getId();
    }

    public function getEntitlements()
    {
        $attributesFile = $this->_c->getSectionValue('PersonaResourceOwner', 'entitlementsFile');
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
