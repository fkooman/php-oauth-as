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
use fkooman\Rest\Plugin\Basic\BasicAuthentication;
use fkooman\Http\Request;
use fkooman\Json\Json;

class TokenServiceTest extends PHPUnit_Framework_TestCase
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

        $resourceOwner = array(
            "id" => "fkooman",
            "entitlement" => array(),
            "ext" => array(),
        );
        $this->storage->updateResourceOwner(new MockResourceOwner($resourceOwner));

        $clientData = new ClientData(
            array(
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
            )
        );
        $this->storage->addClient($clientData);

        $this->storage->addApproval('testcodeclient', 'fkooman', 'read write foo', 'r3fr3sh');
        $this->storage->storeAuthorizationCode("4uth0r1z4t10n", "fkooman", time(), "testcodeclient", null, "read");
        $this->storage->storeAuthorizationCode("3xp1r3d4uth0r1z4t10n", "fkooman", time() - 1000, "testcodeclient", null, "read");
        $this->storage->storeAuthorizationCode("authorizeRequestWithRedirectUri", "fkooman", time(), "testcodeclient", "http://localhost/php-oauth/unit/test.html", "read");

        $compatStorage = &$this->storage;

        $basicAuthenticationPlugin = new BasicAuthentication(
            function ($userId) use ($compatStorage) {
                $clientData = $compatStorage->getClient($userId);

                return false !== $clientData ? $clientData->getSecret() : false;
            },
            'OAuth Server'
        );

        $this->service = new TokenService($this->storage, 5);
        $this->service->registerBeforeEachMatchPlugin($basicAuthenticationPlugin);
    }

    public function testAuthorizationCode()
    {
        $h = new Request("https://auth.example.org/token", "POST");
        $h->setBasicAuthUser("testcodeclient");
        $h->setBasicAuthPass("abcdef");
        $h->setPostParameters(array("code" => "4uth0r1z4t10n", "grant_type" => "authorization_code"));
        
        $response = $this->service->run($h);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertRegexp('|^{"access_token":"[a-zA-Z0-9]+","expires_in":5,"scope":"read","refresh_token":"r3fr3sh","token_type":"bearer"}$|', Json::encode($response->getContent()));
    }

    /**
     * @expectedException \fkooman\Http\Exception\BadRequestException
     * @expectedExceptionMessage invalid_grant
     */
    public function testAuthorizationCodeWithoutRedirectUri()
    {
        $h = new Request("https://auth.example.org/token", "POST");
        $h->setBasicAuthUser("testcodeclient");
        $h->setBasicAuthPass("abcdef");
        // fail because redrect_uri was part of the authorize request, so must also be
        // there at token request
        $h->setPostParameters(array("code" => "authorizeRequestWithRedirectUri", "grant_type" => "authorization_code"));
        $this->service->run($h);
    }

    /**
     * @expectedException \fkooman\Http\Exception\BadRequestException
     * @expectedExceptionMessage invalid_grant
     */
    public function testAuthorizationCodeWithInvalidRedirectUri()
    {
        $h = new Request("https://auth.example.org/token", "POST");
        $h->setBasicAuthUser("testcodeclient");
        $h->setBasicAuthPass("abcdef");
        $h->setPostParameters(array("redirect_uri" => "http://example.org/invalid", "code" => "authorizeRequestWithRedirectUri", "grant_type" => "authorization_code"));
        
        $this->service->run($h);
    }

    public function testAuthorizationCodeWithRedirectUri()
    {
        $h = new Request("https://auth.example.org/token", "POST");
        $h->setBasicAuthUser("testcodeclient");
        $h->setBasicAuthPass("abcdef");
        $h->setPostParameters(array("redirect_uri" => "http://localhost/php-oauth/unit/test.html", "code" => "authorizeRequestWithRedirectUri", "grant_type" => "authorization_code"));
        
        $response = $this->service->run($h);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertRegexp('|^{"access_token":"[a-zA-Z0-9]+","expires_in":5,"scope":"read","refresh_token":"r3fr3sh","token_type":"bearer"}$|', Json::encode($response->getContent()));
    }

    public function testRefreshToken()
    {
        $h = new Request("https://auth.example.org/token", "POST");
        $h->setBasicAuthUser("testcodeclient");
        $h->setBasicAuthPass("abcdef");
        $h->setPostParameters(array("refresh_token" => "r3fr3sh", "grant_type" => "refresh_token"));
        
        $response = $this->service->run($h);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertRegexp('|^{"access_token":"[a-zA-Z0-9]+","expires_in":5,"scope":"read write foo","token_type":"bearer"}$|', Json::encode($response->getContent()));
    }

    /**
     * @expectedException \fkooman\Http\Exception\MethodNotAllowedException
     */
    public function testInvalidRequestMethod()
    {
        $h = new Request("https://auth.example.org?client_id=foo&response_type=token&scope=read&state=xyz", "GET");
        
        $this->service->run($h);
        $this->assertEquals(405, $response->getStatusCode());
    }

    /**
     * @expectedException \fkooman\Http\Exception\BadRequestException
     * @expectedExceptionMessage invalid_request
     */
    public function testWithoutGrantType()
    {
        $h = new Request("https://auth.example.org/token", "POST");
        $h->setBasicAuthUser("testcodeclient");
        $h->setBasicAuthPass("abcdef");
        $h->setPostParameters(array("code" => "4uth0r1z4t10n"));
        
        $this->service->run($h);
    }

    /**
     * @expectedException \fkooman\Http\Exception\UnauthorizedException
     * @expectedExceptionMessage invalid_credentials
     */
    public function testWithoutCredentials()
    {
        $h = new Request("https://auth.example.org/token", "POST");
        $h->setPostParameters(array("client_id" => "testcodeclient", "code" => "4uth0r1z4t10n", "grant_type" => "authorization_code"));
        
        $this->service->run($h);
    }

    /**
     * @expectedException \fkooman\Http\Exception\UnauthorizedException
     * @expectedExceptionMessage invalid_credentials
     */
    public function testWithInvalidClient()
    {
        $h = new Request("https://auth.example.org/token", "POST");
        $h->setBasicAuthUser("NONEXISTINGCLIENT");
        $h->setBasicAuthPass("abcdef");
        $h->setPostParameters(array("code" => "4uth0r1z4t10n"));
        
        $this->service->run($h);
    }

    /**
     * @expectedException \fkooman\Http\Exception\UnauthorizedException
     * @expectedExceptionMessage invalid_credentials
     */
    public function testWithInvalidPassword()
    {
        $h = new Request("https://auth.example.org/token", "POST");
        $h->setBasicAuthUser("testcodeclient");
        $h->setBasicAuthPass("WRONGPASSWORD");
        $h->setPostParameters(array("code" => "4uth0r1z4t10n"));
        
        $this->service->run($h);
    }

    /**
     * @expectedException \fkooman\Http\Exception\BadRequestException
     * @expectedExceptionMessage invalid_grant
     */
    public function testClientIdUserMismatch()
    {
        $h = new Request("https://auth.example.org/token", "POST");
        $h->setBasicAuthUser("testcodeclient");
        $h->setBasicAuthPass("abcdef");
        $h->setPostParameters(array("code" => "4uth0r1z4t10n", "grant_type" => "authorization_code", "client_id" => "MISMATCH_CLIENT_ID"));
        
        $this->service->run($h);
    }

    /**
     * @expectedException \fkooman\Http\Exception\BadRequestException
     * @expectedExceptionMessage invalid_grant
     */
    public function testExpiredAuthorization()
    {
        $h = new Request("https://auth.example.org/token", "POST");
        $h->setBasicAuthUser("testcodeclient");
        $h->setBasicAuthPass("abcdef");
        $h->setPostParameters(array("code" => "3xp1r3d4uth0r1z4t10n", "grant_type" => "authorization_code"));
        
        $this->service->run($h);
    }

    /**
     * @expectedException \fkooman\Http\Exception\BadRequestException
     * @expectedExceptionMessage invalid_grant
     */
    public function testInvalidCode()
    {
        $h = new Request("https://auth.example.org/token", "POST");
        $h->setBasicAuthUser("testcodeclient");
        $h->setBasicAuthPass("abcdef");
        $h->setPostParameters(array("code" => "1nv4l1d4uth0r1z4t10n", "grant_type" => "authorization_code"));
        
        $this->service->run($h);
    }

    /**
     * @expectedException \fkooman\Http\Exception\BadRequestException
     * @expectedExceptionMessage invalid_grant
     */
    public function testCodeNotBoundToUsedClient()
    {
        $h = new Request("https://auth.example.org/token", "POST");
        $h->setBasicAuthUser("testcodeclient");
        $h->setBasicAuthPass("abcdef");
        $h->setPostParameters(array("code" => "n4t1v34uth0r1z4t10n", "grant_type" => "authorization_code"));
        
        $this->service->run($h);
    }

    /**
     * @expectedException \fkooman\Http\Exception\BadRequestException
     * @expectedExceptionMessage invalid_grant
     */
    public function checkReuseAuthorizationCode()
    {
        $h = new Request("https://auth.example.org/token", "POST");
        $h->setBasicAuthUser("testcodeclient");
        $h->setBasicAuthPass("abcdef");
        $h->setPostParameters(array("code" => "4uth0r1z4t10n", "grant_type" => "authorization_code"));
        
        $response = $this->service->run($h);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertRegexp('|^{"access_token":"[a-zA-Z0-9]+","expires_in":5,"scope":"read","refresh_token":"r3fr3sh","token_type":"bearer"}$|', $response->getContent());
        $this->service->run($h);
    }

    public function testRefreshTokenSubScope()
    {
        $h = new Request("https://auth.example.org/token", "POST");
        $h->setBasicAuthUser("testcodeclient");
        $h->setBasicAuthPass("abcdef");
        $h->setPostParameters(array("refresh_token" => "r3fr3sh", "scope" => "foo", "grant_type" => "refresh_token"));
        
        $response = $this->service->run($h);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertRegexp('|^{"access_token":"[a-zA-Z0-9]+","expires_in":5,"scope":"foo","token_type":"bearer"}$|', Json::encode($response->getContent()));
    }

    public function testRefreshTokenNoSubScope()
    {
        $h = new Request("https://auth.example.org/token", "POST");
        $h->setBasicAuthUser("testcodeclient");
        $h->setBasicAuthPass("abcdef");
        $h->setPostParameters(array("refresh_token" => "r3fr3sh", "scope" => "we want no sub scope", "grant_type" => "refresh_token"));
        
        $response = $this->service->run($h);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertRegexp('|^{"access_token":"[a-zA-Z0-9]+","expires_in":5,"scope":"read write foo","token_type":"bearer"}$|', Json::encode($response->getContent()));
    }
}
