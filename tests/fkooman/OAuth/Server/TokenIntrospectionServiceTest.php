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

class TokenIntrospectionServiceTest extends PHPUnit_Framework_TestCase
{
    /** @var fkooman\OAuth\Server\TokenIntrospectionService */
    private $service;

    public function setUp()
    {
        $storage = new PdoStorage(
            new PDO(
                $GLOBALS['DB_DSN'],
                $GLOBALS['DB_USER'],
                $GLOBALS['DB_PASSWD']
            )
        );
        $storage->initDatabase();
        $storage->addClient(
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
        $storage->storeAccessToken('foo', 1111111111, 'testclient', 'fkooman', 'foo bar', 1234);
        $storage->storeAccessToken('bar', 1111111111, 'testclient', 'frko', 'a b c', 1234);

        $ioStub = $this->getMockBuilder('fkooman\OAuth\Server\IO')->getMock();
        $ioStub->method('getRandomHex')->will(
            $this->onConsecutiveCalls(
                '11111111'
            )
        );
        $ioStub->method('getTime')->willReturn(1111111111);
        $this->service = new TokenIntrospectionService($storage, $ioStub);
    }

    public function testGetTokenIntrospection()
    {
        $h = new Request('https://auth.example.org/introspect?token=foo', 'GET');
        $response = $this->service->run($h);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(
            array(
                'active' => true,
                'exp' => 1111112345,
                'iat' => 1111111111,
                'scope' => 'foo bar',
                'client_id' => 'testclient',
                'sub' => 'fkooman',
                'user_id' => 'fkooman',
                'iss' => 'https://auth.example.org',
                'token_type' => 'bearer'
            ),
            $response->getContent()
        );
    }

    public function testPostTokenIntrospection()
    {
        $h = new Request('https://auth.example.org/introspect', 'POST');
        $h->setPostParameters(array('token' => 'foo'));
        $response = $this->service->run($h);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(
            array(
                'active' => true,
                'exp' => 1111112345,
                'iat' => 1111111111,
                'scope' => 'foo bar',
                'client_id' => 'testclient',
                'sub' => 'fkooman',
                'user_id' => 'fkooman',
                'iss' => 'https://auth.example.org',
                'token_type' => 'bearer'
            ),
            $response->getContent()
        );
    }

    public function testPostTokenIntrospectionNoEntitlement()
    {
        $h = new Request('https://auth.example.org/introspect', 'POST');
        $h->setPostParameters(array('token' => 'bar'));
        $response = $this->service->run($h);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(
            array(
                'active' => true,
                'exp' => 1111112345,
                'iat' => 1111111111,
                'scope' => 'a b c',
                'client_id' => 'testclient',
                'sub' => 'frko',
                'user_id' => 'frko',
                'iss' => 'https://auth.example.org',
                'token_type' => 'bearer'
            ),
            $response->getContent()
        );
    }

    public function testMissingGetTokenIntrospection()
    {
        $h = new Request('https://auth.example.org/introspect?token=foobar', 'GET');
        $response = $this->service->run($h);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(
            array(
                'active' => false
            ),
            $response->getContent()
        );
    }

    /**
     * @expectedException fkooman\Http\Exception\MethodNotAllowedException
     * @expectedExceptionMessage unsupported method
     */
    public function testUnsupportedMethod()
    {
        $h = new Request('https://auth.example.org/introspect?token=foobar', 'DELETE');
        $this->service->run($h);
    }
}
