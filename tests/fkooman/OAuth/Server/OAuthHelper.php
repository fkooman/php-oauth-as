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

use PHPUnit_Framework_TestCase;
use PDO;

class OAuthHelper extends PHPUnit_Framework_TestCase
{
    /** @var fkooman\OAuth\Server\PdoStorage */
    protected $storage;

    public function setUp()
    {
        $this->storage = new PdoStorage(
            new PDO(
                $GLOBALS['DB_DSN'],
                $GLOBALS['DB_USER'],
                $GLOBALS['DB_PASSWD']
            )
        );
        $this->storage->initDatabase();

        // add some clients
        $uaba = array(
            "id" => "testclient",
            "name" => "Simple Test Client",
            "description" => "Client for unit testing",
            "secret" => null,
            "icon" => null,
            "allowed_scope" => "read",
            "disable_user_consent" => false,
            "contact_email" => "foo@example.org",
            "redirect_uri" => "http://localhost/php-oauth/unit/test.html",
            "type" => "token"
        );

        $wa = array(
            "id" => "testcodeclient",
            "name" => "Simple Test Client for Authorization Code Profile",
            "description" => "Client for unit testing",
            "secret" => "abcdef",
            "icon" => null,
            "allowed_scope" => "read write foo bar foobar",
            "disable_user_consent" => false,
            "contact_email" => null,
            "redirect_uri" => "http://localhost/php-oauth/unit/test.html",
            "type" => "code"
        );

        $na = array(
            "id" => "testnativeclient",
            "name" => "Simple Test Client for Authorization Code Native Profile",
            "description" => "Client for unit testing",
            "secret" => "foo",
            "icon" => null,
            "allowed_scope" => "read",
            "contact_email" => null,
            "disable_user_consent" => false,
            "redirect_uri" => "oauth://callback",
            "type" => "code"
        );

        $this->storage->addClient(new ClientData($uaba));
        $this->storage->addClient(new ClientData($wa));
        $this->storage->addClient(new ClientData($na));
    }
}
