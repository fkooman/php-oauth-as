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

require_once 'OAuthHelper.php';

use \RestService\Http\HttpRequest as HttpRequest;
use \RestService\Utils\Json as Json;
use \OAuth\TokenIntrospection as TokenIntrospection;

class TokenIntrospectionTest extends OAuthHelper
{
    public function setUp()
    {
        parent::setUp();

        $oauthStorageBackend = 'OAuth\\' . $this->_config->getValue('storageBackend');
        $storage = new $oauthStorageBackend($this->_config);

        $attributes = array (
            "eduPersonEntitlement" => array (
                "urn:x-foo:service:access",
                "urn:x-bar:privilege:admin"
            ),
        );
        $storage->updateResourceOwner('fkooman', Json::enc($attributes));
        $storage->updateResourceOwner('frko', NULL);

        $storage->storeAccessToken("foo", time(), "testclient", "fkooman", "foo bar", 1234);
        $storage->storeAccessToken("bar", time(), "testclient", "frko", "a b c", 1234);

    }

    public function testGetTokenIntrospection()
    {
        $h = new HttpRequest("https://auth.example.org/introspect?token=foo", "GET");
        $t = new TokenIntrospection($this->_config, NULL);
        $response = $t->handleRequest($h);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertRegexp('|{"active":true,"expires_at":[0-9]+,"issued_at":[0-9]+,"scope":"foo bar","client_id":"testclient","sub":"fkooman","x-entitlement":"urn:x-foo:service:access urn:x-bar:privilege:admin"}|', $response->getContent());
    }

    public function testPostTokenIntrospection()
    {
        $h = new HttpRequest("https://auth.example.org/introspect", "POST");
        $h->setPostParameters(array("token" => "foo"));
        $t = new TokenIntrospection($this->_config, NULL);
        $response = $t->handleRequest($h);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertRegexp('|{"active":true,"expires_at":[0-9]+,"issued_at":[0-9]+,"scope":"foo bar","client_id":"testclient","sub":"fkooman","x-entitlement":"urn:x-foo:service:access urn:x-bar:privilege:admin"}|', $response->getContent());
    }

    public function testPostTokenIntrospectionNoEntitlement()
    {
        $h = new HttpRequest("https://auth.example.org/introspect", "POST");
        $h->setPostParameters(array("token" => "bar"));
        $t = new TokenIntrospection($this->_config, NULL);
        $response = $t->handleRequest($h);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertRegexp('|{"active":true,"expires_at":[0-9]+,"issued_at":[0-9]+,"scope":"a b c","client_id":"testclient","sub":"frko"}|', $response->getContent());
    }

    public function testMissingGetTokenIntrospection()
    {
        $h = new HttpRequest("https://auth.example.org/introspect?token=foobar", "GET");
        $t = new TokenIntrospection($this->_config, NULL);
        $response = $t->handleRequest($h);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('{"active":false}', $response->getContent());
    }

    public function testUnsupportedMethod()
    {
        $h = new HttpRequest("https://auth.example.org/introspect?token=foobar", "DELETE");
        $t = new TokenIntrospection($this->_config, NULL);
        $response = $t->handleRequest($h);
        $this->assertEquals(405, $response->getStatusCode());
        $this->assertEquals('{"error":"method_not_allowed","error_description":"invalid request method"}', $response->getContent());
    }
}
