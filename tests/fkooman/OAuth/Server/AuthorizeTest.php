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

class AuthorizeTest extends PHPUnit_Framework_TestCase
{
    private $storage;

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

        $this->resourceOwner = new DummyResourceOwner(
            'fkooman',
            array(
                'http://php-oauth.net/entitlement/manage'
            )
        );
    }

    public function testGetAuthorize()
    {
        $h = new Request("https://auth.example.org?client_id=testclient&response_type=token&scope=read&state=xyz", "GET");
        $o = new Authorize($this->storage, $this->resourceOwner, 5);
        $response = $o->handleRequest($h);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testPostAuthorize()
    {
        $h = new Request("https://auth.example.org?client_id=testclient&response_type=token&scope=read&state=xyz", "POST");
        $h->setHeader("HTTP_REFERER", "https://auth.example.org?client_id=testclient&response_type=token&scope=read&state=xyz");
        $h->setPostParameters(array("approval" => "approve", "scope" => array("read")));
        $o = new Authorize($this->storage, $this->resourceOwner, 5);
        $response = $o->handleRequest($h);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertRegexp("|^http://localhost/php-oauth/unit/test.html#access_token=[a-zA-Z0-9]+&expires_in=5&token_type=bearer&scope=read&state=xyz$|", $response->getHeader("Location"));

        // now a get should immediately return the access token redirect...
        $h = new Request("https://auth.example.org?client_id=testclient&response_type=token&scope=read&state=abc", "GET");
        $o = new Authorize($this->storage, $this->resourceOwner, 5);
        $response = $o->handleRequest($h);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertRegexp("|^http://localhost/php-oauth/unit/test.html#access_token=[a-zA-Z0-9]+&expires_in=5&token_type=bearer&scope=read&state=abc$|", $response->getHeader("Location"));
    }

    public function testUnsupportedScope()
    {
        $h = new Request("https://auth.example.org?client_id=testclient&response_type=token&scope=foo&state=xyz", "GET");
        $o = new Authorize($this->storage, $this->resourceOwner, 5);
        $response = $o->handleRequest($h);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals("http://localhost/php-oauth/unit/test.html#error=invalid_scope&error_description=not+authorized+to+request+this+scope&state=xyz", $response->getHeader("Location"));
    }

    public function testUnregisteredClient()
    {
        $h = new Request("https://auth.example.org?client_id=foo&response_type=token&scope=read&state=xyz", "GET");
        $o = new Authorize($this->storage, $this->resourceOwner, 5);
        $response = $o->handleRequest($h);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertRegexp("|.*client not registered.*|", $response->getContent());
    }

    public function testInvalidRequestMethod()
    {
        $h = new Request("https://auth.example.org?client_id=foo&response_type=token&scope=read&state=xyz", "DELETE");
        $o = new Authorize($this->storage, $this->resourceOwner, 5);
        $response = $o->handleRequest($h);
        $this->assertEquals(405, $response->getStatusCode());
    }

    public function testCSRFAttack()
    {
        $h = new Request("https://auth.example.org?client_id=testclient&response_type=token&scope=read&state=xyz", "POST");
        $h->setHeader("HTTP_REFERER", "https://evil.site.org/xyz");
        $h->setPostParameters(array("approval" => "approve", "scope" => array("read")));
        $o = new Authorize($this->storage, $this->resourceOwner, 5);
        $response = $o->handleRequest($h);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertRegexp("|.*csrf protection triggered, referrer does not match request uri.*|", $response->getContent());
    }

    public function testMissingClientId()
    {
        $h = new Request("https://auth.example.org", "GET");
        $o = new Authorize($this->storage, $this->resourceOwner, 5);
        $response = $o->handleRequest($h);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertRegexp("|.*client_id missing.*|", $response->getContent());
    }

    public function testMissingResponseType()
    {
        $h = new Request("https://auth.example.org?client_id=testclient", "GET");
        $o = new Authorize($this->storage, $this->resourceOwner, 5);
        $response = $o->handleRequest($h);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertRegexp("|.*response_type missing.*|", $response->getContent());
    }

    public function testWrongRedirectUri()
    {
        $u = urlencode("http://wrong.example.org/foo");
        $h = new Request("https://auth.example.org?client_id=testclient&response_type=token&scope=read&redirect_uri=$u", "GET");
        $o = new Authorize($this->storage, $this->resourceOwner, 5);
        $response = $o->handleRequest($h);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertRegexp("|.*specified redirect_uri not the same as registered redirect_uri.*|", $response->getContent());
    }

    public function testWrongClientType()
    {
        $h = new Request("https://auth.example.org?client_id=testclient&scope=read&response_type=code", "GET");
        $o = new Authorize($this->storage, $this->resourceOwner, 5);
        $response = $o->handleRequest($h);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals("http://localhost/php-oauth/unit/test.html#error=unsupported_response_type&error_description=response_type+not+supported+by+client+profile", $response->getHeader("Location"));
    }
}
