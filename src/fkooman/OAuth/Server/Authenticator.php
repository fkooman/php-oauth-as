<?php

namespace fkooman\OAuth\Server;

use fkooman\Ini\IniReader;
use fkooman\Rest\Plugin\Basic\BasicAuthentication;
use fkooman\Rest\Plugin\Mellon\MellonAuthentication;

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
}
