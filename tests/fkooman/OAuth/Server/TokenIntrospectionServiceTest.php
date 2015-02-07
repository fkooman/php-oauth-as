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

use PDO;
use PHPUnit_Framework_TestCase;
use fkooman\Http\Request;
use fkooman\Json\Json;

class TokenIntrospectionServiceTest extends PHPUnit_Framework_TestCase
{
    /** @var fkooman\OAuth\Server\PdoStorage */
    private $storage;

    /** @var fkooman\OAuth\Server\Entitlements */
    private $entitlements;

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
        $this->storage->addClient(
            new ClientData(
                array(
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
                )
            )
        );
        $this->storage->storeAccessToken("foo", time(), "testclient", "fkooman", "foo bar", 1234);
        $this->storage->storeAccessToken("bar", time(), "testclient", "frko", "a b c", 1234);

        $this->entitlements = new Entitlements(
            dirname(dirname(dirname(__DIR__))) . '/data/entitlements.json'
        );
    }

    public function testGetTokenIntrospection()
    {
        $h = new Request("https://auth.example.org/introspect?token=foo", "GET");
        $t = new TokenIntrospectionService($this->storage, $this->entitlements);
        $response = $t->run($h);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertRegexp('|{"active":true,"exp":[0-9]+,"iat":[0-9]+,"scope":"foo bar","client_id":"testclient","sub":"fkooman","token_type":"bearer","x-entitlement":"urn:x-foo:service:access urn:x-bar:privilege:admin"}|', Json::encode($response->getContent()));
    }

    public function testPostTokenIntrospection()
    {
        $h = new Request("https://auth.example.org/introspect", "POST");
        $h->setPostParameters(array("token" => "foo"));
        $t = new TokenIntrospectionService($this->storage, $this->entitlements);
        $response = $t->run($h);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertRegexp('{"active":true,"exp":[0-9]+,"iat":[0-9]+,"scope":"foo bar","client_id":"testclient","sub":"fkooman","token_type":"bearer","x-entitlement":"urn:x-foo:service:access urn:x-bar:privilege:admin"}', Json::encode($response->getContent()));
    }

    public function testPostTokenIntrospectionNoEntitlement()
    {
        $h = new Request("https://auth.example.org/introspect", "POST");
        $h->setPostParameters(array("token" => "bar"));
        $t = new TokenIntrospectionService($this->storage, $this->entitlements);
        ;
        $response = $t->run($h);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertRegexp('|{"active":true,"exp":[0-9]+,"iat":[0-9]+,"scope":"a b c","client_id":"testclient","sub":"frko","token_type":"bearer"}|', Json::encode($response->getContent()));
    }

    public function testMissingGetTokenIntrospection()
    {
        $h = new Request("https://auth.example.org/introspect?token=foobar", "GET");
        $t = new TokenIntrospectionService($this->storage, $this->entitlements);
        ;
        $response = $t->run($h);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('{"active":false}', Json::encode($response->getContent()));
    }

    /**
     * @expectedException fkooman\Http\Exception\MethodNotAllowedException
     * @expectedExceptionMessage unsupported method
     */
    public function testUnsupportedMethod()
    {
        $h = new Request("https://auth.example.org/introspect?token=foobar", "DELETE");
        $t = new TokenIntrospectionService($this->storage, $this->entitlements);
        ;
        $t->run($h);
    }
}
