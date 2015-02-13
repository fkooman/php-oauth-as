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
                    "id" => "token_client",
                    "name" => "Token Client",
                    "allowed_scope" => "read",
                    "redirect_uri" => "https://example.org/callback.html",
                    "type" => "token"
                )
            )
        );

        $this->storage->addClient(
            new ClientData(
                array(
                    "id" => "code_client",
                    "name" => "Code Client",
                    "secret" => "foobar",
                    "allowed_scope" => "read",
                    "redirect_uri" => "https://example.org/callback",
                    "type" => "code"
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

        $this->service = new AuthorizeService($this->storage, $ioStub, 5, false);
        $this->service->registerBeforeEachMatchPlugin($basicAuthenticationPlugin);
    }

    public function testGetAuthorizeToken()
    {
        $h = new Request("https://auth.example.org?client_id=token_client&response_type=token&scope=read&state=xyz", "GET");
        $h->setBasicAuthUser("admin");
        $h->setBasicAuthPass("adm1n");
        $response = $this->service->run($h);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getContentType());
        // FIXME: use a file compare
        //$this->assertEquals('', $response->getContent());
    }

    public function testGetAuthorizeTokenWithApproval()
    {
        // we already store an approval so we should get a redirect
        $this->storage->addApproval('token_client', 'admin', 'read', null);

        $h = new Request("https://auth.example.org?client_id=token_client&response_type=token&scope=read&state=xyz", "GET");
        $h->setBasicAuthUser("admin");
        $h->setBasicAuthPass("adm1n");
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

        $h = new Request("https://auth.example.org?client_id=code_client&response_type=code&scope=read&state=xyz", "GET");
        $h->setBasicAuthUser("admin");
        $h->setBasicAuthPass("adm1n");
        $response = $this->service->run($h);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals(
            'https://example.org/callback?code=11111111&state=xyz',
            $response->getHeader('Location')
        );
    }

    public function testGetAuthorizeCode()
    {
        $h = new Request("https://auth.example.org?client_id=code_client&response_type=code&scope=read&state=xyz", "GET");
        $h->setBasicAuthUser("admin");
        $h->setBasicAuthPass("adm1n");
        $response = $this->service->run($h);
        $this->assertEquals(200, $response->getStatusCode());
    }

#    public function testPostAuthorize()
#    {
#        $h = new Request("https://auth.example.org?client_id=token_client&response_type=token&scope=read&state=xyz", "POST");
#        $h->setBasicAuthUser("admin");
#        $h->setBasicAuthPass("adm1n");
#        $h->setHeader("HTTP_REFERER", "https://auth.example.org?client_id=token_client&response_type=token&scope=read&state=xyz");
#        $h->setPostParameters(array("approval" => "approve", "scope" => array("read")));
#
#        $response = $this->service->run($h);
#        $this->assertEquals(302, $response->getStatusCode());
#        $this->assertRegexp("|^http://localhost/php-oauth/unit/test.html#access_token=[a-zA-Z0-9]+&expires_in=5&token_type=bearer&scope=read&state=xyz$|", $response->getHeader("Location"));

#        // now a get should immediately return the access token redirect...
#        $h = new Request("https://auth.example.org?client_id=token_client&response_type=token&scope=read&state=abc", "GET");
#        $h->setBasicAuthUser("admin");
#        $h->setBasicAuthPass("adm1n");

#        $response = $this->service->run($h);
#        $this->assertEquals(302, $response->getStatusCode());
#        $this->assertRegexp("|^http://localhost/php-oauth/unit/test.html#access_token=[a-zA-Z0-9]+&expires_in=5&token_type=bearer&scope=read&state=abc$|", $response->getHeader("Location"));
#    }

    public function testUnsupportedScope()
    {
        $h = new Request("https://auth.example.org?client_id=token_client&response_type=token&scope=foo&state=xyz", "GET");
        $h->setBasicAuthUser("admin");
        $h->setBasicAuthPass("adm1n");
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
        $h = new Request("https://auth.example.org?client_id=foo&response_type=token&scope=read&state=xyz", "GET");
        $h->setBasicAuthUser("admin");
        $h->setBasicAuthPass("adm1n");
        $this->service->run($h);
    }

    /**
     * @expectedException fkooman\Http\Exception\MethodNotAllowedException
     * @expectedExceptionMessage unsupported method
     */
    public function testInvalidRequestMethod()
    {
        $h = new Request("https://auth.example.org?client_id=foo&response_type=token&scope=read&state=xyz", "DELETE");
        $h->setBasicAuthUser("admin");
        $h->setBasicAuthPass("adm1n");
        $this->service->run($h);
    }

    /**
     * @expectedException fkooman\Http\Exception\BadRequestException
     * @expectedExceptionMessage CSRF protection triggered
     */
    public function testCSRFAttack()
    {
        $h = new Request("https://auth.example.org?client_id=token_client&response_type=token&scope=read&state=xyz", "POST");
        $h->setBasicAuthUser("admin");
        $h->setBasicAuthPass("adm1n");
        $h->setHeader("HTTP_REFERER", "https://evil.site.org/xyz");
        $h->setPostParameters(array("approval" => "approve"));
        
        $this->service->run($h);
    }

    /**
     * @expectedException fkooman\Http\Exception\BadRequestException
     * @expectedExceptionMessage "client_id" must be a non-empty string with maximum length 255
     */
    public function testMissingClientId()
    {
        $h = new Request("https://auth.example.org", "GET");
        $h->setBasicAuthUser("admin");
        $h->setBasicAuthPass("adm1n");
        
        $this->service->run($h);
    }

    /**
     * @expectedException fkooman\Http\Exception\BadRequestException
     * @expectedExceptionMessage "response_type" must be a non-empty string with maximum length 255
     */
    public function testMissingResponseType()
    {
        $h = new Request("https://auth.example.org?client_id=token_client", "GET");
        $h->setBasicAuthUser("admin");
        $h->setBasicAuthPass("adm1n");

        $this->service->run($h);
    }

    /**
     * @expectedException fkooman\Http\Exception\BadRequestException
     * @expectedExceptionMessage specified redirect_uri not the same as registered redirect_uri
     */
    public function testWrongRedirectUri()
    {
        $u = urlencode("http://wrong.example.org/foo");
        $h = new Request("https://auth.example.org?client_id=token_client&response_type=token&scope=read&state=abc&redirect_uri=$u", "GET");
        $h->setBasicAuthUser("admin");
        $h->setBasicAuthPass("adm1n");
        
        $this->service->run($h);
    }

    public function testWrongClientType()
    {
        $h = new Request("https://auth.example.org?client_id=token_client&scope=read&response_type=code&state=foo", "GET");
        $h->setBasicAuthUser("admin");
        $h->setBasicAuthPass("adm1n");
        
        $response = $this->service->run($h);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('https://example.org/callback.html#error=unsupported_response_type&error_description=response_type+not+supported+by+client+profile&state=foo', $response->getHeader('Location'));
    }
}
