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

use fkooman\Ini\IniReader;
use fkooman\Rest\Plugin\Basic\BasicAuthentication;
use fkooman\Rest\Plugin\Mellon\MellonAuthentication;
use fkooman\Rest\Plugin\Mellon\SimpleSamlAuthentication;

class Authenticator
{

    /** @var fkooman\Ini\IniReader */
    private $iniReader;

    public function __construct(IniReader $iniReader)
    {
        $this->iniReader = $iniReader;
    }

    public function getAuthenticationPlugin()
    {
        $requestedPlugin = $this->iniReader->v('authenticationPlugin');
        switch ($requestedPlugin) {
            case 'BasicAuthentication':
                return $this->getBasicAuthenticationPlugin();
            case 'MellonAuthentication':
                return $this->getMellonAuthenticationPlugin();
            case 'SimpleSamlAuthentication':
                return $this->getSimpleSamlAuthenticationPlugin();
            default:
                throw new RuntimeException('unsupported authentication plugin');
        }
    }

    private function getBasicAuthenticationPlugin()
    {
        $userList = $this->iniReader->v('BasicAuthentication');
        return new BasicAuthentication(
            function ($userId) use ($userList) {
                if (!array_key_exists($userId, $userList)) {
                    return false;
                }
                return password_hash($userList[$userId], PASSWORD_DEFAULT);
            },
            'OAuth Server'
        );
    }

    private function getMellonAuthenticationPlugin()
    {
        return new MellonAuthentication(
            $this->iniReader->v('MellonAuthentication', 'mellonAttribute')
        );
    }

    public function getSimpleSamlAuthenticationPlugin()
    {
        return new SimpleSamlAuthentication(
            $this->iniReader->v('SimpleSamlAuthentication', 'simpleSamlPath'),
            $this->iniReader->v('SimpleSamlAuthentication', 'authSource'),
            $this->iniReader->v('SimpleSamlAuthentication', 'userAttribute', false, null)
        );
    }
}
