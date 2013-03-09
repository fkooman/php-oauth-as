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
use \SimpleSAML_Auth_Simple as SimpleSAML_Auth_Simple;

class SspResourceOwner implements IResourceOwner
{
    private $_c;
    private $_ssp;

    public function __construct(Config $c)
    {
        $this->_c = $c;
        $sspPath = $this->_c->getSectionValue('SspResourceOwner', 'sspPath') . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . '_autoload.php';
        if (!file_exists($sspPath) || !is_file($sspPath) || !is_readable($sspPath)) {
            throw new SspResourceOwnerException("invalid path to simpleSAMLphp");
        }
        require_once $sspPath;

        $this->_ssp = new SimpleSAML_Auth_Simple($this->_c->getSectionValue('SspResourceOwner', 'authSource'));
    }

    public function setHint($resourceOwnerIdHint = NULL)
    {
        // this resource owner class does not support hinting
    }

    private function _authenticateUser()
    {
        $this->_ssp->requireAuth(array("saml:NameIDPolicy" => "urn:oasis:names:tc:SAML:2.0:nameid-format:persistent"));
    }

    public function getAttributes()
    {
        $this->_authenticateUser();

        return $this->_ssp->getAttributes();
    }

    public function getAttribute($key)
    {
        $attributes = $this->getAttributes();

        return array_key_exists($key, $attributes) ? $attributes[$key] : NULL;
    }

    public function getResourceOwnerId()
    {
        $this->_authenticateUser();
        $nameId = $this->_ssp->getAuthData("saml:sp:NameID");
        if ("urn:oasis:names:tc:SAML:2.0:nameid-format:persistent" !== $nameId['Format']) {
            throw new SspResourceOwnerException("NameID format MUST be 'urn:oasis:names:tc:SAML:2.0:nameid-format:persistent', but is '" . $nameId['Format'] . "'");
        }

        return $nameId['Value'];
    }

    /* FIXME: DEPRECATED */
    public function getEntitlement()
    {
        return $this->getAttribute("eduPersonEntitlement");
    }

}
