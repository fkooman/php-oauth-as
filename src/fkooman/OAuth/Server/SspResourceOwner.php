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
use fkooman\OAuth\Server\Exception\SspResourceOwnerException;
use SimpleSAML_Auth_Simple;

class SspResourceOwner implements IResourceOwner
{
    /** @var fkooman\Config\Config */
    private $config;

    /** @var SimpleSAML_Auth_Simple */
    private $ssp;

    public function __construct(Config $c)
    {
        $this->config = $c;
        $sspPath = $this->config->s('SspResourceOwner')->l('sspPath') . '/lib/_autoload.php';
        if (!file_exists($sspPath) || !is_file($sspPath) || !is_readable($sspPath)) {
            throw new SspResourceOwnerException("invalid path to simpleSAMLphp");
        }
        require_once $sspPath;

        $this->ssp = new SimpleSAML_Auth_Simple($this->config->s('SspResourceOwner')->l('authSource'));
    }

    public function setResourceOwnerHint($resourceOwnerHint)
    {
        // nop
    }

    public function getId()
    {
        $this->authenticateUser();

        $resourceOwnerIdAttribute = $this->config->s('SspResourceOwner')->l('resourceOwnerIdAttribute', false);
        if (null === $resourceOwnerIdAttribute) {
            $nameId = $this->ssp->getAuthData("saml:sp:NameID");
            if ("urn:oasis:names:tc:SAML:2.0:nameid-format:persistent" !== $nameId['Format']) {
                throw new SspResourceOwnerException(
                    sprintf(
                        "NameID format MUST be 'urn:oasis:names:tc:SAML:2.0:nameid-format:persistent', but is '%s'",
                        $nameId['Format']
                    )
                );
            }

            return $nameId['Value'];
        } else {
            // use the attribute from resourceOwnerIdAttribute
            $attr = $this->getExt();
            if (isset($attr[$resourceOwnerIdAttribute]) && is_array($attr[$resourceOwnerIdAttribute])) {
                return $attr[$resourceOwnerIdAttribute][0];
            }
            throw new SspResourceOwnerException(
                sprintf(
                    "attribute '%s' for resource owner identifier is not available",
                    $resourceOwnerIdAttribute
                )
            );
        }
    }

    public function getEntitlement()
    {
        $attr = $this->getExt();
        if (isset($attr['eduPersonEntitlement']) && is_array($attr['eduPersonEntitlement'])) {
            return $attr['eduPersonEntitlement'];
        }

        return array();
    }

    public function getExt()
    {
        $this->authenticateUser();

        return $this->ssp->getAttributes();
    }

    private function authenticateUser()
    {
        $resourceOwnerIdAttribute = $this->config->s('SspResourceOwner')->l('resourceOwnerIdAttribute', false);
        if (null === $resourceOwnerIdAttribute) {
            $this->ssp->requireAuth(
                array(
                    "saml:NameIDPolicy" => "urn:oasis:names:tc:SAML:2.0:nameid-format:persistent"
                )
            );
        } else {
            $this->ssp->requireAuth();
        }
    }
}
