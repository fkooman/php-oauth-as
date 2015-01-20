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

class OAuthHelper extends \PHPUnit_Framework_TestCase
{
    protected $_tmpDb;

    /** @var fkooman\Ini\IniReader */
    protected $iniReader;

    public function setUp()
    {
        $this->_tmpDb = tempnam(sys_get_temp_dir(), "oauth_");
        if (false === $this->_tmpDb) {
            throw new Exception("unable to generate temporary file for database");
        }
        $dsn = "sqlite:".$this->_tmpDb;

        $configArray = array(
            'authenticationMechanism' => 'DummyResourceOwner',
            'DummyResourceOwner' => array(
                'uid' => "fkooman",
                'entitlement' => array(
                    "http://php-oauth.net/entitlement/manage",
                ),
            ),
            'accessTokenExpiry' => 5,
            'PdoStorage' => array(
                'dsn' => $dsn,
            ),
        );
        $this->iniReader = new IniReader($configArray);

        // intialize storage
        $storage = new PdoStorage($this->iniReader);
        $storage->initDatabase();

        // add some clients
        $uaba = array("id" => "testclient",
                  "name" => "Simple Test Client",
                  "description" => "Client for unit testing",
                  "secret" => null,
                  "icon" => null,
                  "allowed_scope" => "read",
                  "contact_email" => "foo@example.org",
                  "redirect_uri" => "http://localhost/php-oauth/unit/test.html",
                  "type" => "user_agent_based_application", );

        $wa = array("id" => "testcodeclient",
                  "name" => "Simple Test Client for Authorization Code Profile",
                  "description" => "Client for unit testing",
                  "secret" => "abcdef",
                  "icon" => null,
                  "allowed_scope" => "read write foo bar foobar",
                  "contact_email" => null,
                  "redirect_uri" => "http://localhost/php-oauth/unit/test.html",
                  "type" => "web_application", );
        $na = array("id" => "testnativeclient",
                  "name" => "Simple Test Client for Authorization Code Native Profile",
                  "description" => "Client for unit testing",
                  "secret" => null,
                  "icon" => null,
                  "allowed_scope" => "read",
                  "contact_email" => null,
                  "redirect_uri" => "oauth://callback",
                  "type" => "native_application", );

        $storage->addClient($uaba);
        $storage->addClient($wa);
        $storage->addClient($na);
    }

    public function tearDown()
    {
        unlink($this->_tmpDb);
    }

    public function testNop()
    {
        $this->assertTrue(true);
    }
}
