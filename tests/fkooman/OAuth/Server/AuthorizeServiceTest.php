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
use fkooman\Rest\Plugin\Basic\BasicAuthentication;
use fkooman\Rest\PluginRegistry;
use fkooman\Rest\Plugin\ReferrerCheckPlugin;

class AuthorizeServiceTest extends PHPUnit_Framework_TestCase
{
    private $service;

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
                    'id' => 'token_client',
                    'name' => 'Token Client',
                    'allowed_scope' => 'read',
                    'redirect_uri' => 'https://example.org/callback.html',
                    'type' => 'token',
                )
            )
        );

        $this->storage->addClient(
            new ClientData(
                array(
                    'id' => 'code_client',
                    'name' => 'Code Client',
                    'secret' => 'foobar',
                    'allowed_scope' => 'read',
                    'redirect_uri' => 'https://example.org/callback',
                    'type' => 'code',
                )
            )
        );

        $ioStub = $this->getMockBuilder('fkooman\OAuth\Server\IO')->getMock();
        $ioStub->method('getRandomHex')->will(
            $this->onConsecutiveCalls(
                '11111111'
            )
        );
        $ioStub->method('getTime')->willReturn(1111111111);

        $compatStorage = &$this->storage;

        $basicAuthenticationPlugin = new BasicAuthentication(
            function ($userId) use ($compatStorage) {
                return 'admin' === $userId ? password_hash('adm1n', PASSWORD_DEFAULT, array('cost' => 4)) : false;
            },
            'OAuth Server Authentication'
        );

        $pluginRegistry = new PluginRegistry();
        $pluginRegistry->registerDefaultPlugin($basicAuthenticationPlugin);
        $pluginRegistry->registerDefaultPlugin(new ReferrerCheckPlugin());

        $this->service = new AuthorizeService($this->storage, $ioStub, 5, false);
        $this->service->setPluginRegistry($pluginRegistry);
    }

    public function testGetAuthorizeToken()
    {
        $h = new Request(
            array(
                'SERVER_NAME' => 'www.example.org',
                'SERVER_PORT' => 80,
                'QUERY_STRING' => 'client_id=token_client&response_type=token&scope=read&state=xyz',
                'REQUEST_URI' => '/authorize.php?client_id=token_client&response_type=token&scope=read&state=xyz',
                'SCRIPT_NAME' => '/authorize.php',
                'REQUEST_METHOD' => 'GET',
                'HTTP_AUTHORIZATION' => sprintf('Basic %s', base64_encode('admin:adm1n')),
            )
        );
#        $h = new Request('https://auth.example.org/?client_id=token_client&response_type=token&scope=read&state=xyz', 'GET');
#        $h->setBasicAuthUser('admin');
#        $h->setBasicAuthPass('adm1n');
        $response = $this->service->run($h);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html;charset=UTF-8', $response->getHeader('Content-Type'));
        // FIXME: use a file compare
        //$this->assertEquals('', $response->getContent());
    }

    public function testGetAuthorizeTokenWithApproval()
    {
        // we already store an approval so we should get a redirect
        $this->storage->addApproval('token_client', 'admin', 'read', null);

        $h = new Request(
            array(
                'SERVER_NAME' => 'www.example.org',
                'SERVER_PORT' => 80,
                'QUERY_STRING' => 'client_id=token_client&response_type=token&scope=read&state=xyz',
                'REQUEST_URI' => '/authorize.php?client_id=token_client&response_type=token&scope=read&state=xyz',
                'SCRIPT_NAME' => '/authorize.php',
                'REQUEST_METHOD' => 'GET',
                'HTTP_AUTHORIZATION' => sprintf('Basic %s', base64_encode('admin:adm1n')),
            )
        );

#        $h = new Request('https://auth.example.org/?client_id=token_client&response_type=token&scope=read&state=xyz', 'GET');
#        $h->setBasicAuthUser('admin');
#        $h->setBasicAuthPass('adm1n');
        $response = $this->service->run($h);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals(
            'https://example.org/callback.html#access_token=11111111&expires_in=5&token_type=bearer&scope=read&state=xyz',
            $response->getHeader('Location')
        );
    }

    public function testGetAuthorizeCodeWithApproval()
    {
        // we already store an approval so we should get a redirect
        $this->storage->addApproval('code_client', 'admin', 'read', '12345');

        $h = new Request(
            array(
                'SERVER_NAME' => 'www.example.org',
                'SERVER_PORT' => 80,
                'QUERY_STRING' => 'client_id=code_client&response_type=code&scope=read&state=xyz',
                'REQUEST_URI' => '/authorize.php?client_id=code_client&response_type=code&scope=read&state=xyz',
                'SCRIPT_NAME' => '/authorize.php',
                'REQUEST_METHOD' => 'GET',
                'HTTP_AUTHORIZATION' => sprintf('Basic %s', base64_encode('admin:adm1n')),
            )
        );
#        $h = new Request('https://auth.example.org/?client_id=code_client&response_type=code&scope=read&state=xyz', 'GET');
#        $h->setBasicAuthUser('admin');
#        $h->setBasicAuthPass('adm1n');
        $response = $this->service->run($h);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals(
            'https://example.org/callback?code=11111111&state=xyz',
            $response->getHeader('Location')
        );
    }

    public function testGetAuthorizeCode()
    {
        $h = new Request(
            array(
                'SERVER_NAME' => 'www.example.org',
                'SERVER_PORT' => 80,
                'QUERY_STRING' => 'client_id=code_client&response_type=code&scope=read&state=xyz',
                'REQUEST_URI' => '/authorize.php?client_id=code_client&response_type=code&scope=read&state=xyz',
                'SCRIPT_NAME' => '/authorize.php',
                'REQUEST_METHOD' => 'GET',
                'HTTP_AUTHORIZATION' => sprintf('Basic %s', base64_encode('admin:adm1n')),
            )
        );
#        $h = new Request('https://auth.example.org/?client_id=code_client&response_type=code&scope=read&state=xyz', 'GET');
#        $h->setBasicAuthUser('admin');
#        $h->setBasicAuthPass('adm1n');
        $response = $this->service->run($h);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testPostAuthorizeTokenClientApprove()
    {
        $h = new Request(
            array(
                'SERVER_NAME' => 'www.example.org',
                'SERVER_PORT' => 80,
                'QUERY_STRING' => 'client_id=token_client&response_type=token&scope=read&state=xyz',
                'REQUEST_URI' => '/authorize.php?client_id=token_client&response_type=token&scope=read&state=xyz',
                'SCRIPT_NAME' => '/authorize.php',
                'REQUEST_METHOD' => 'POST',
                'HTTP_AUTHORIZATION' => sprintf('Basic %s', base64_encode('admin:adm1n')),
                'HTTP_REFERER' => 'http://www.example.org/authorize.php?client_id=token_client&response_type=token&scope=read&state=xyz',
            ),
            array(
                'approval' => 'approve',
            )
        );
#        $h = new Request('https://auth.example.org/?client_id=token_client&response_type=token&scope=read&state=xyz', 'POST');
#        $h->setBasicAuthUser('admin');
#        $h->setBasicAuthPass('adm1n');
#        $h->setHeaders(array('HTTP_REFERER' => 'https://auth.example.org/?client_id=token_client&response_type=token&scope=read&state=xyz'));
#        $h->setPostParameters(array('approval' => 'approve'));
        $response = $this->service->run($h);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('http://www.example.org/authorize.php?client_id=token_client&response_type=token&scope=read&state=xyz', $response->getHeader('Location'));
    }

    public function testPostAuthorizeTokenClientReject()
    {
        $h = new Request(
            array(
                'SERVER_NAME' => 'www.example.org',
                'SERVER_PORT' => 80,
                'QUERY_STRING' => 'client_id=token_client&response_type=token&scope=read&state=xyz',
                'REQUEST_URI' => '/authorize.php?client_id=token_client&response_type=token&scope=read&state=xyz',
                'SCRIPT_NAME' => '/authorize.php',
                'REQUEST_METHOD' => 'POST',
                'HTTP_AUTHORIZATION' => sprintf('Basic %s', base64_encode('admin:adm1n')),
                'HTTP_REFERER' => 'http://www.example.org/authorize.php?client_id=token_client&response_type=token&scope=read&state=xyz',
            ),
            array(
                'approval' => 'reject',
            )
        );
#        $h = new Request('https://auth.example.org/?client_id=token_client&response_type=token&scope=read&state=xyz', 'POST');
#        $h->setBasicAuthUser('admin');
#        $h->setBasicAuthPass('adm1n');
#        $h->setHeaders(array('HTTP_REFERER' => 'https://auth.example.org/?client_id=token_client&response_type=token&scope=read&state=xyz'));
#        $h->setPostParameters(array('approval' => 'reject'));
        $response = $this->service->run($h);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals(
            'https://example.org/callback.html#error=access_denied&error_description=not+authorized+by+resource+owner&state=xyz',
            $response->getHeader('Location')
        );
    }

    public function testPostAuthorizeCodeClientApprove()
    {
        $h = new Request(
            array(
                'SERVER_NAME' => 'www.example.org',
                'SERVER_PORT' => 80,
                'QUERY_STRING' => 'client_id=code_client&response_type=code&scope=read&state=xyz',
                'REQUEST_URI' => '/authorize.php?client_id=code_client&response_type=code&scope=read&state=xyz',
                'SCRIPT_NAME' => '/authorize.php',
                'REQUEST_METHOD' => 'POST',
                'HTTP_AUTHORIZATION' => sprintf('Basic %s', base64_encode('admin:adm1n')),
                'HTTP_REFERER' => 'http://www.example.org/authorize.php?client_id=code_client&response_type=code&scope=read&state=xyz',
            ),
            array(
                'approval' => 'approve',
            )
        );
#        $h = new Request('https://auth.example.org/?client_id=code_client&response_type=code&scope=read&state=xyz', 'POST');
#        $h->setBasicAuthUser('admin');
#        $h->setBasicAuthPass('adm1n');
#        $h->setHeaders(array('HTTP_REFERER' => 'https://auth.example.org/?client_id=code_client&response_type=code&scope=read&state=xyz'));
#        $h->setPostParameters(array('approval' => 'approve'));
        $response = $this->service->run($h);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('http://www.example.org/authorize.php?client_id=code_client&response_type=code&scope=read&state=xyz', $response->getHeader('Location'));
    }

    public function testPostAuthorizeCodeClientReject()
    {
        $h = new Request(
            array(
                'SERVER_NAME' => 'www.example.org',
                'SERVER_PORT' => 80,
                'QUERY_STRING' => 'client_id=code_client&response_type=code&scope=read&state=xyz',
                'REQUEST_URI' => '/authorize.php?client_id=code_client&response_type=code&scope=read&state=xyz',
                'SCRIPT_NAME' => '/authorize.php',
                'REQUEST_METHOD' => 'POST',
                'HTTP_AUTHORIZATION' => sprintf('Basic %s', base64_encode('admin:adm1n')),
                'HTTP_REFERER' => 'http://www.example.org/authorize.php?client_id=code_client&response_type=code&scope=read&state=xyz',
            ),
            array(
                'approval' => 'reject',
            )
        );
#        $h = new Request('https://auth.example.org/?client_id=code_client&response_type=code&scope=read&state=xyz', 'POST');
#        $h->setBasicAuthUser('admin');
#        $h->setBasicAuthPass('adm1n');
#        $h->setHeaders(array('HTTP_REFERER' => 'https://auth.example.org/?client_id=code_client&response_type=code&scope=read&state=xyz'));
#        $h->setPostParameters(array('approval' => 'reject'));
        $response = $this->service->run($h);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals(
            'https://example.org/callback?error=access_denied&error_description=not+authorized+by+resource+owner&state=xyz',
            $response->getHeader('Location')
        );
    }

#    public function testPostAuthorize()
#    {
#        $h = new Request('https://auth.example.org/?client_id=token_client&response_type=token&scope=read&state=xyz', 'POST');
#        $h->setBasicAuthUser('admin');
#        $h->setBasicAuthPass('adm1n');
#        $h->setHeader('HTTP_REFERER', 'https://auth.example.org/?client_id=token_client&response_type=token&scope=read&state=xyz');
#        $h->setPostParameters(array('approval' => 'approve'));
#
#        $response = $this->service->run($h);
#        $this->assertEquals(302, $response->getStatusCode());
#        $this->assertRegexp('|^http://localhost/php-oauth/unit/test.html#access_token=[a-zA-Z0-9]+&expires_in=5&token_type=bearer&scope=read&state=xyz$|', $response->getHeader('Location'));

#        // now a get should immediately return the access token redirect...
#        $h = new Request('https://auth.example.org/?client_id=token_client&response_type=token&scope=read&state=abc', 'GET');
#        $h->setBasicAuthUser('admin');
#        $h->setBasicAuthPass('adm1n');

#        $response = $this->service->run($h);
#        $this->assertEquals(302, $response->getStatusCode());
#        $this->assertRegexp('|^http://localhost/php-oauth/unit/test.html#access_token=[a-zA-Z0-9]+&expires_in=5&token_type=bearer&scope=read&state=abc$|', $response->getHeader('Location'));
#    }

    public function testUnsupportedScope()
    {
        $h = new Request(
            array(
                'SERVER_NAME' => 'www.example.org',
                'SERVER_PORT' => 80,
                'QUERY_STRING' => 'client_id=token_client&response_type=token&scope=foo&state=xyz',
                'REQUEST_URI' => '/authorize.php?client_id=token_client&response_type=token&scope=foo&state=xyz',
                'SCRIPT_NAME' => '/authorize.php',
                'REQUEST_METHOD' => 'GET',
                'HTTP_AUTHORIZATION' => sprintf('Basic %s', base64_encode('admin:adm1n')),
            )
        );
#        $h = new Request('https://auth.example.org/?client_id=token_client&response_type=token&scope=foo&state=xyz', 'GET');
#        $h->setBasicAuthUser('admin');
#        $h->setBasicAuthPass('adm1n');
        $response = $this->service->run($h);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('https://example.org/callback.html#error=invalid_scope&error_description=not+authorized+to+request+this+scope&state=xyz', $response->getHeader('Location'));
    }

    /**
     * @expectedException fkooman\Http\Exception\BadRequestException
     * @expectedExceptionMessage client not registered
     */
    public function testUnregisteredClient()
    {
        $h = new Request(
            array(
                'SERVER_NAME' => 'www.example.org',
                'SERVER_PORT' => 80,
                'QUERY_STRING' => 'client_id=foo&response_type=token&scope=read&state=xyz',
                'REQUEST_URI' => '/authorize.php?client_id=foo&response_type=token&scope=read&state=xyz',
                'SCRIPT_NAME' => '/authorize.php',
                'REQUEST_METHOD' => 'GET',
                'HTTP_AUTHORIZATION' => sprintf('Basic %s', base64_encode('admin:adm1n')),
            )
        );
#        $h = new Request('https://auth.example.org/?client_id=foo&response_type=token&scope=read&state=xyz', 'GET');
#        $h->setBasicAuthUser('admin');
#        $h->setBasicAuthPass('adm1n');
        $this->service->run($h);
    }

    /**
     * @expectedException fkooman\Http\Exception\MethodNotAllowedException
     * @expectedExceptionMessage unsupported method
     */
    public function testInvalidRequestMethod()
    {
        $h = new Request(
            array(
                'SERVER_NAME' => 'www.example.org',
                'SERVER_PORT' => 80,
                'QUERY_STRING' => 'client_id=foo&response_type=token&scope=read&state=xyz',
                'REQUEST_URI' => '/authorize.php?client_id=foo&response_type=token&scope=read&state=xyz',
                'SCRIPT_NAME' => '/authorize.php',
                'REQUEST_METHOD' => 'DELETE',
                'HTTP_AUTHORIZATION' => sprintf('Basic %s', base64_encode('admin:adm1n')),
            )
        );
#        $h = new Request('https://auth.example.org/?client_id=foo&response_type=token&scope=read&state=xyz', 'DELETE');
#        $h->setBasicAuthUser('admin');
#        $h->setBasicAuthPass('adm1n');
        $this->service->run($h);
    }

    /**
     * @expectedException fkooman\Http\Exception\BadRequestException
     * @expectedExceptionMessage HTTP_REFERER has unexpected value
     */
    public function testCSRFAttack()
    {
        $h = new Request(
            array(
                'SERVER_NAME' => 'www.example.org',
                'SERVER_PORT' => 80,
                'QUERY_STRING' => 'client_id=token_client&response_type=token&scope=read&state=xyz',
                'REQUEST_URI' => '/authorize.php?client_id=token_client&response_type=token&scope=read&state=xyz',
                'SCRIPT_NAME' => '/authorize.php',
                'REQUEST_METHOD' => 'POST',
                'HTTP_AUTHORIZATION' => sprintf('Basic %s', base64_encode('admin:adm1n')),
                'HTTP_REFERER' => 'https://evil.site.org/xyz',
            ),
            array(
                'approval' => 'approve',
            )
        );
#        $h = new Request('https://auth.example.org/?client_id=token_client&response_type=token&scope=read&state=xyz', 'POST');
#        $h->setBasicAuthUser('admin');
#        $h->setBasicAuthPass('adm1n');
#        $h->setHeaders(array('HTTP_REFERER' => 'https://evil.site.org/xyz'));
#        $h->setPostParameters(array('approval' => 'approve'));
        $this->service->run($h);
    }

    /**
     * @expectedException fkooman\Http\Exception\BadRequestException
     * @expectedExceptionMessage "client_id" must be a non-empty string with maximum length 255
     */
    public function testMissingClientId()
    {
        $h = new Request(
            array(
                'SERVER_NAME' => 'www.example.org',
                'SERVER_PORT' => 80,
                'QUERY_STRING' => '',
                'REQUEST_URI' => '/authorize.php',
                'SCRIPT_NAME' => '/authorize.php',
                'REQUEST_METHOD' => 'GET',
                'HTTP_AUTHORIZATION' => sprintf('Basic %s', base64_encode('admin:adm1n')),
            )
        );
#        $h = new Request('https://auth.example.org', 'GET');
#        $h->setBasicAuthUser('admin');
#        $h->setBasicAuthPass('adm1n');
        $this->service->run($h);
    }

    /**
     * @expectedException fkooman\Http\Exception\BadRequestException
     * @expectedExceptionMessage "response_type" must be a non-empty string with maximum length 255
     */
    public function testMissingResponseType()
    {
        $h = new Request(
            array(
                'SERVER_NAME' => 'www.example.org',
                'SERVER_PORT' => 80,
                'QUERY_STRING' => 'client_id=token_client',
                'REQUEST_URI' => '/authorize.php?client_id=token_client',
                'SCRIPT_NAME' => '/authorize.php',
                'REQUEST_METHOD' => 'GET',
                'HTTP_AUTHORIZATION' => sprintf('Basic %s', base64_encode('admin:adm1n')),
            )
        );
#        $h = new Request('https://auth.example.org/?client_id=token_client', 'GET');
#        $h->setBasicAuthUser('admin');
#        $h->setBasicAuthPass('adm1n');
        $this->service->run($h);
    }

    /**
     * @expectedException fkooman\Http\Exception\BadRequestException
     * @expectedExceptionMessage specified redirect_uri not the same as registered redirect_uri
     */
    public function testWrongRedirectUri()
    {
        $h = new Request(
            array(
                'SERVER_NAME' => 'www.example.org',
                'SERVER_PORT' => 80,
                'QUERY_STRING' => sprintf('client_id=token_client&response_type=token&scope=read&state=abc&redirect_uri=%s', urlencode('http://wrong.example.org/foo')),
                'REQUEST_URI' => sprintf('/authorize.php?client_id=token_client&response_type=token&scope=read&state=abc&redirect_uri=%s', urlencode('http://wrong.example.org/foo')),
                'SCRIPT_NAME' => '/authorize.php',
                'REQUEST_METHOD' => 'GET',
                'HTTP_AUTHORIZATION' => sprintf('Basic %s', base64_encode('admin:adm1n')),
            )
        );
#        $h = new Request(
#            sprintf(
#                'https://auth.example.org/?client_id=token_client&response_type=token&scope=read&state=abc&redirect_uri=%s',
#                urlencode('http://wrong.example.org/foo')
#            ),
#            'GET'
#        );
#        $h->setBasicAuthUser('admin');
#        $h->setBasicAuthPass('adm1n');
        $this->service->run($h);
    }

    public function testWrongClientType()
    {
        $h = new Request(
            array(
                'SERVER_NAME' => 'www.example.org',
                'SERVER_PORT' => 80,
                'QUERY_STRING' => 'client_id=token_client&scope=read&response_type=code&state=foo',
                'REQUEST_URI' => '/authorize.php?client_id=token_client&scope=read&response_type=code&state=foo',
                'SCRIPT_NAME' => '/authorize.php',
                'REQUEST_METHOD' => 'GET',
                'HTTP_AUTHORIZATION' => sprintf('Basic %s', base64_encode('admin:adm1n')),
            )
        );
#        $h = new Request('https://auth.example.org/?client_id=token_client&scope=read&response_type=code&state=foo', 'GET');
#        $h->setBasicAuthUser('admin');
#        $h->setBasicAuthPass('adm1n');
        $response = $this->service->run($h);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('https://example.org/callback.html#error=unsupported_response_type&error_description=response_type+not+supported+by+client+profile&state=foo', $response->getHeader('Location'));
    }
}
