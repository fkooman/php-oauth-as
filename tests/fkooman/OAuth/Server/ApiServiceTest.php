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

use fkooman\Json\Json;
use fkooman\Http\Request;
use fkooman\Rest\Plugin\Bearer\TokenIntrospection;

class ApiServiceTest extends OAuthHelper
{
    private $storage;

    public function setUp()
    {
        parent::setUp();

        $storage = new PdoStorage($this->iniReader);
        $resourceOwner = array(
            'id' => 'fkooman',
            'entitlement' => array(),
            'ext' => array(),
        );
        $storage->updateResourceOwner(new MockResourceOwner($resourceOwner));

        $storage->addApproval('testclient', 'fkooman', 'read', null);
        $storage->storeAccessToken('12345abc', time(), 'testcodeclient', 'fkooman', 'http://php-oauth.net/scope/authorize', 3600);
        $this->storage = $storage;

        $stub = $this->getMockBuilder('fkooman\Rest\Plugin\Bearer\BearerAuthentication')
                     ->disableOriginalConstructor()
                     ->getMock();
        $stub->method('execute')->willReturn(
            new TokenIntrospection(
                array(
                    'active' => true,
                    'sub' => 'fkooman',
                    'scope' => 'http://php-oauth.net/scope/authorize',
                )
            )
        );
        $this->bearerAuthenticationStub = $stub;
    }

    public function testRetrieveAuthorizations()
    {
        $api = new ApiService($this->storage);
        $api->registerBeforeEachMatchPlugin($this->bearerAuthenticationStub);

        $h = new Request('http://www.example.org/api.php');
        $h->setPathInfo('/authorizations/');

        $response = $api->run($h);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeader('Content-Type'));
        $this->assertEquals(
            array(
                array(
                    'scope' => 'read',
                    'id' => 'testclient',
                    'name' => 'Simple Test Client',
                    'description' => 'Client for unit testing',
                    'redirect_uri' => 'http://localhost/php-oauth/unit/test.html',
                    'type' => 'user_agent_based_application',
                    'icon' => null,
                    'allowed_scope' => 'read',
                ),
            ),
            $response->getContent()
        );
    }

    public function testAddAuthorizations()
    {
        $api = new ApiService($this->storage);
        $api->registerBeforeEachMatchPlugin($this->bearerAuthenticationStub);

        $h = new Request('http://www.example.org/api.php');
        $h->setRequestMethod('POST');
        $h->setPathInfo('/authorizations/');
        $h->setContent(
            Json::encode(
                array(
                    'client_id' => 'testcodeclient',
                    'scope' => 'read',
                    'refresh_token' => null,
                )
            )
        );
        $response = $api->run($h);
        $this->assertEquals(201, $response->getStatusCode());
    }

    /**
     * @expectedException fkooman\Http\Exception\NotFoundException
     * @expectedExceptionMessage client is not registered
     */
    public function testAddAuthorizationsUnregisteredClient()
    {
        $api = new ApiService($this->storage);
        $api->registerBeforeEachMatchPlugin($this->bearerAuthenticationStub);

        $h = new Request('http://www.example.org/api.php');
        $h->setRequestMethod('POST');
        $h->setPathInfo('/authorizations/');
        $h->setContent(
            Json::encode(
                array(
                    'client_id' => 'nonexistingclient',
                    'scope' => 'read',
                )
            )
        );
        $api->run($h);
    }

    public function testGetAuthorization()
    {
        $api = new ApiService($this->storage);
        $api->registerBeforeEachMatchPlugin($this->bearerAuthenticationStub);

        $h = new Request('http://www.example.org/api.php');
        $h->setPathInfo('/authorizations/testclient');
        // FIXME: test with non existing client_id!

        $response = $api->run($h);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeader('Content-Type'));
        $this->assertEquals(
            array(
                'client_id' => 'testclient',
                'resource_owner_id' => 'fkooman',
                'scope' => 'read',
                'refresh_token' => null,
            ),
            $response->getContent()
        );
    }

    public function testDeleteAuthorization()
    {
        $api = new ApiService($this->storage);
        $api->registerBeforeEachMatchPlugin($this->bearerAuthenticationStub);

        $h = new Request('http://www.example.org/api.php');
        $h->setRequestMethod('DELETE');
        $h->setPathInfo('/authorizations/testclient');

        // FIXME: test with non existing client_id!
        $response = $api->run($h);
        $this->assertEquals(200, $response->getStatusCode());
    }
}
