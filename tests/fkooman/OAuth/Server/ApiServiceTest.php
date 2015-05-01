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
use fkooman\Json\Json;
use fkooman\Http\Request;
use fkooman\Rest\Plugin\Bearer\TokenInfo;

class ApiServiceTest extends PHPUnit_Framework_TestCase
{
    private $storage;

    private $bearerAuthenticationStub;

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
                    'id' => 'testclient',
                    'name' => 'Simple Test Client',
                    'description' => 'Client for unit testing',
                    'secret' => null,
                    'icon' => null,
                    'allowed_scope' => 'read',
                    'disable_user_consent' => false,
                    'contact_email' => 'foo@example.org',
                    'redirect_uri' => 'http://localhost/php-oauth/unit/test.html',
                    'type' => 'token'
                )
            )
        );

        $this->storage->addClient(
            new ClientData(
                array(
                    'id' => 'testcodeclient',
                    'name' => 'Simple Test Client for Authorization Code Profile',
                    'description' => 'Client for unit testing',
                    'secret' => 'abcdef',
                    'icon' => null,
                    'allowed_scope' => 'read write foo bar foobar',
                    'disable_user_consent' => false,
                    'contact_email' => null,
                    'redirect_uri' => 'http://localhost/php-oauth/unit/test.html',
                    'type' => 'code'
                )
            )
        );

        $this->storage->addApproval('testclient', 'fkooman', 'read', null);

        $stub = $this->getMockBuilder('fkooman\Rest\Plugin\Bearer\BearerAuthentication')
                     ->disableOriginalConstructor()
                     ->getMock();
        $stub->method('execute')->willReturn(
            new TokenInfo(
                array(
                    'active' => true,
                    'sub' => 'fkooman',
                    'scope' => 'http://php-oauth.net/scope/authorize http://php-oauth.net/scope/manage'
                )
            )
        );
        $this->bearerAuthenticationStub = $stub;

        $this->entitlements = new Entitlements(dirname(dirname(dirname(__DIR__))).'/data/entitlements.json');
    }

    public function testRetrieveAuthorizations()
    {
        $api = new ApiService($this->storage, $this->entitlements);
        $api->registerOnMatchPlugin($this->bearerAuthenticationStub);

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
                    'type' => 'token',
                    'icon' => null,
                    'allowed_scope' => 'read',
                ),
            ),
            $response->getContent()
        );
    }

    public function testAddAuthorizations()
    {
        $api = new ApiService($this->storage, $this->entitlements);
        $api->registerOnMatchPlugin($this->bearerAuthenticationStub);

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
        $api = new ApiService($this->storage, $this->entitlements);
        $api->registerOnMatchPlugin($this->bearerAuthenticationStub);

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
        $api = new ApiService($this->storage, $this->entitlements);
        $api->registerOnMatchPlugin($this->bearerAuthenticationStub);

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
        $api = new ApiService($this->storage, $this->entitlements);
        $api->registerOnMatchPlugin($this->bearerAuthenticationStub);

        $h = new Request('http://www.example.org/api.php');
        $h->setRequestMethod('DELETE');
        $h->setPathInfo('/authorizations/testclient');

        // FIXME: test with non existing client_id!
        $response = $api->run($h);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testAddApplication()
    {
        $api = new ApiService($this->storage, $this->entitlements);
        $api->registerOnMatchPlugin($this->bearerAuthenticationStub);

        $h = new Request('http://www.example.org/api.php');
        $h->setRequestMethod('POST');
        $h->setPathInfo('/applications/');
        $h->setContent(
            Json::encode(
                array(
                    'id' => 'foo',
                    'scope' => 'read write',
                    'type' => 'token',
                    'secret' => null,
                    'redirect_uri' => 'http://www.example.org/redirect',
                    'name' => 'Foo',
                )
            )
        );
        $api->run($h);
    }


    /**
     * @expectedException fkooman\Http\Exception\BadRequestException
     * @expectedExceptionMessage invalid client data
     */
    public function testAddNullApplication()
    {
        $api = new ApiService($this->storage, $this->entitlements);
        $api->registerOnMatchPlugin($this->bearerAuthenticationStub);

        $h = new Request('http://www.example.org/api.php');
        $h->setRequestMethod('POST');
        $h->setPathInfo('/applications/');
        $h->setContent(null);
        $api->run($h);
    }
}
