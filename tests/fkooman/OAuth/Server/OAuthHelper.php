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

class OAuthHelper extends \PHPUnit_Framework_TestCase
{
    protected $_tmpDb;

    /** @var fkooman\Config\Config */
    protected $config;

    public function setUp()
    {
        $this->_tmpDb = tempnam(sys_get_temp_dir(), "oauth_");
        if (FALSE === $this->_tmpDb) {
            throw new Exception("unable to generate temporary file for database");
        }
        $dsn = "sqlite:" . $this->_tmpDb;

        $config = Config::fromIniFile(dirname(dirname(dirname(dirname(__DIR__)))) . DIRECTORY_SEPARATOR . "config" . DIRECTORY_SEPARATOR . "oauth.ini.defaults");
        $configArray = $config->toArray();
        $configArray['accessTokenExpiry'] = 5;
        $configArray['storageBackend'] = 'PdoOAuthStorage';
        $configArray['PdoOAuthStorage']['dsn'] = $dsn;
        $configArray['Api']['enableApi'] = true;

        $this->config = new Config($configArray);

        // intialize storage
        $storage = new PdoOAuthStorage($this->config);
        $sql = file_get_contents('schema/db.sql');
        $storage->dbQuery($sql);
        // FIXME: apply updates

        // add some clients
        $uaba = array("id" => "testclient",
                  "name" => "Simple Test Client",
                  "description" => "Client for unit testing",
                  "secret" => NULL,
                  "icon" => NULL,
                  "allowed_scope" => "read",
                  "contact_email" => "foo@example.org",
                  "redirect_uri" => "http://localhost/php-oauth/unit/test.html",
                  "type" => "user_agent_based_application");

        $wa = array ("id" => "testcodeclient",
                  "name" => "Simple Test Client for Authorization Code Profile",
                  "description" => "Client for unit testing",
                  "secret" => "abcdef",
                  "icon" => NULL,
                  "allowed_scope" => "read write foo bar foobar",
                  "contact_email" => NULL,
                  "redirect_uri" => "http://localhost/php-oauth/unit/test.html",
                  "type" => "web_application");
        $na = array ("id" => "testnativeclient",
                  "name" => "Simple Test Client for Authorization Code Native Profile",
                  "description" => "Client for unit testing",
                  "secret" => NULL,
                  "icon" => NULL,
                  "allowed_scope" => "read",
                  "contact_email" => NULL,
                  "redirect_uri" => "oauth://callback",
                  "type" => "native_application");

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
        $this->assertTrue(TRUE);
    }

}
