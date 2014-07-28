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

require_once 'OAuthHelper.php';

use fkooman\Http\Request;
use fkooman\Json\Json;

class TokenIntrospectionTest extends OAuthHelper
{
    public function setUp()
    {
        parent::setUp();

        $oauthStorageBackend = 'fkooman\\OAuth\\Server\\' . $this->config->getValue('storageBackend');
        $storage = new $oauthStorageBackend($this->config);

        $resourceOwnerOne = array(
            "id" => "fkooman",
            "entitlement" => array ("urn:x-foo:service:access", "urn:x-bar:privilege:admin"),
            "ext" => array()
        );
        $resourceOwnerTwo = array(
            "id" => "frko",
            "entitlement" => array (),
            "ext" => array()
        );

        $storage->updateResourceOwner(new MockResourceOwner($resourceOwnerOne));
        $storage->updateResourceOwner(new MockResourceOwner($resourceOwnerTwo));

        $storage->storeAccessToken("foo", time(), "testclient", "fkooman", "foo bar", 1234);
        $storage->storeAccessToken("bar", time(), "testclient", "frko", "a b c", 1234);

    }

    public function testGetTokenIntrospection()
    {
        $h = new Request("https://auth.example.org/introspect?token=foo", "GET");
        $t = new TokenIntrospection($this->config, NULL);
        $response = $t->handleRequest($h);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertRegexp('|{"active":true,"exp":[0-9]+,"iat":[0-9]+,"scope":"foo bar","client_id":"testclient","sub":"fkooman","token_type":"bearer","x-entitlement":\["urn:x-foo:service:access","urn:x-bar:privilege:admin"\]}|', Json::encode($response->getContent()));
    }

    public function testPostTokenIntrospection()
    {
        $h = new Request("https://auth.example.org/introspect", "POST");
        $h->setPostParameters(array("token" => "foo"));
        $t = new TokenIntrospection($this->config, NULL);
        $response = $t->handleRequest($h);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertRegexp('{"active":true,"exp":[0-9]+,"iat":[0-9]+,"scope":"foo bar","client_id":"testclient","sub":"fkooman","token_type":"bearer","x-entitlement":\["urn:x-foo:service:access","urn:x-bar:privilege:admin"\]}', Json::encode($response->getContent()));
    }

    public function testPostTokenIntrospectionNoEntitlement()
    {
        $h = new Request("https://auth.example.org/introspect", "POST");
        $h->setPostParameters(array("token" => "bar"));
        $t = new TokenIntrospection($this->config, NULL);
        $response = $t->handleRequest($h);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertRegexp('|{"active":true,"exp":[0-9]+,"iat":[0-9]+,"scope":"a b c","client_id":"testclient","sub":"frko","token_type":"bearer"}|', Json::encode($response->getContent()));
    }

    public function testMissingGetTokenIntrospection()
    {
        $h = new Request("https://auth.example.org/introspect?token=foobar", "GET");
        $t = new TokenIntrospection($this->config, NULL);
        $response = $t->handleRequest($h);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('{"active":false}', Json::encode($response->getContent()));
    }

    public function testUnsupportedMethod()
    {
        $h = new Request("https://auth.example.org/introspect?token=foobar", "DELETE");
        $t = new TokenIntrospection($this->config, NULL);
        $response = $t->handleRequest($h);
        $this->assertEquals(405, $response->getStatusCode());
        $this->assertEquals('{"error":"method_not_allowed","error_description":"invalid request method"}', Json::encode($response->getContent()));
    }
}
