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
use \OAuth\Token as Token;
use \OAuth\MockResourceOwner as MockResourceOwner;

class TokenTest extends OAuthHelper
{
    public function setUp()
    {
        parent::setUp();

        $oauthStorageBackend = 'OAuth\\' . $this->_config->getValue('storageBackend');
        $storage = new $oauthStorageBackend($this->_config);

        $resourceOwner = array(
            "id" => "fkooman",
            "entitlement" => array(),
            "ext" => array()
        );
        $storage->updateResourceOwner(new MockResourceOwner($resourceOwner));

        $storage->addApproval('testcodeclient', 'fkooman', 'read write foo', 'r3fr3sh');
        $storage->addApproval('testnativeclient', 'fkooman', 'read', 'n4t1v3r3fr3sh');
        $storage->storeAuthorizationCode("4uth0r1z4t10n", "fkooman", time(), "testcodeclient", NULL, "read");
        $storage->storeAuthorizationCode("3xp1r3d4uth0r1z4t10n", "fkooman", time() - 1000, "testcodeclient", NULL, "read");
        $storage->storeAuthorizationCode("n4t1v34uth0r1z4t10n", "fkooman", time(), "testnativeclient", NULL, "read");
        $storage->storeAuthorizationCode("authorizeRequestWithRedirectUri", "fkooman", time(), "testcodeclient", "http://localhost/php-oauth/unit/test.html", "read");
    }

    public function testAuthorizationCode()
    {
        $h = new HttpRequest("https://auth.example.org/token", "POST");
        $h->setBasicAuthUser("testcodeclient");
        $h->setBasicAuthPass("abcdef");
        $h->setPostParameters(array("code" => "4uth0r1z4t10n", "grant_type" => "authorization_code"));
        $t = new Token($this->_config, NULL);
        $response = $t->handleRequest($h);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertRegexp('|^{"access_token":"[a-zA-Z0-9]+","expires_in":5,"scope":"read","refresh_token":"r3fr3sh","token_type":"bearer"}$|', $response->getContent());
    }

    public function testAuthorizationCodeWithoutRedirectUri()
    {
        $h = new HttpRequest("https://auth.example.org/token", "POST");
        $h->setBasicAuthUser("testcodeclient");
        $h->setBasicAuthPass("abcdef");
        // fail because redrect_uri was part of the authorize request, so must also be
        // there at token request
        $h->setPostParameters(array("code" => "authorizeRequestWithRedirectUri", "grant_type" => "authorization_code"));
        $t = new Token($this->_config, NULL);
        $response = $t->handleRequest($h);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals('{"error":"invalid_grant","error_description":"the authorization code was not found"}', $response->getContent());
    }

    public function testAuthorizationCodeWithInvalidRedirectUri()
    {
        $h = new HttpRequest("https://auth.example.org/token", "POST");
        $h->setBasicAuthUser("testcodeclient");
        $h->setBasicAuthPass("abcdef");
        $h->setPostParameters(array("redirect_uri" => "http://example.org/invalid", "code" => "authorizeRequestWithRedirectUri", "grant_type" => "authorization_code"));
        $t = new Token($this->_config, NULL);
        $response = $t->handleRequest($h);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals('{"error":"invalid_grant","error_description":"the authorization code was not found"}', $response->getContent());
    }

    public function testAuthorizationCodeWithRedirectUri()
    {
        $h = new HttpRequest("https://auth.example.org/token", "POST");
        $h->setBasicAuthUser("testcodeclient");
        $h->setBasicAuthPass("abcdef");
        $h->setPostParameters(array("redirect_uri" => "http://localhost/php-oauth/unit/test.html", "code" => "authorizeRequestWithRedirectUri", "grant_type" => "authorization_code"));
        $t = new Token($this->_config, NULL);
        $response = $t->handleRequest($h);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertRegexp('|^{"access_token":"[a-zA-Z0-9]+","expires_in":5,"scope":"read","refresh_token":"r3fr3sh","token_type":"bearer"}$|', $response->getContent());
    }

    public function testRefreshToken()
    {
        $h = new HttpRequest("https://auth.example.org/token", "POST");
        $h->setBasicAuthUser("testcodeclient");
        $h->setBasicAuthPass("abcdef");
        $h->setPostParameters(array("refresh_token" => "r3fr3sh", "grant_type" => "refresh_token"));
        $t = new Token($this->_config, NULL);
        $response = $t->handleRequest($h);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertRegexp('|^{"access_token":"[a-zA-Z0-9]+","expires_in":5,"scope":"read write foo","token_type":"bearer"}$|', $response->getContent());
    }

    public function testInvalidRequestMethod()
    {
        $h = new HttpRequest("https://auth.example.org?client_id=foo&response_type=token&scope=read&state=xyz", "GET");
        $o = new Token($this->_config);
        $response = $o->handleRequest($h);
        $this->assertEquals(405, $response->getStatusCode());
    }

    public function testWithoutGrantType()
    {
        $h = new HttpRequest("https://auth.example.org/token", "POST");
        $h->setBasicAuthUser("testcodeclient");
        $h->setBasicAuthPass("abcdef");
        $h->setPostParameters(array("code" => "4uth0r1z4t10n"));
        $t = new Token($this->_config, NULL);
        $response = $t->handleRequest($h);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals('{"error":"invalid_request","error_description":"the grant_type parameter is missing"}', $response->getContent());
    }

    public function testWithoutCredentials()
    {
        $h = new HttpRequest("https://auth.example.org/token", "POST");
        $h->setPostParameters(array("client_id" => "testcodeclient", "code" => "4uth0r1z4t10n", "grant_type" => "authorization_code"));
        $t = new Token($this->_config, NULL);
        $response = $t->handleRequest($h);
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('Basic realm="OAuth Server"', $response->getHeader("WWW-Authenticate"));
        $this->assertEquals('{"error":"invalid_client","error_description":"client authentication failed"}', $response->getContent());
    }

    public function testWithInvalidClient()
    {
        $h = new HttpRequest("https://auth.example.org/token", "POST");
        $h->setBasicAuthUser("NONEXISTINGCLIENT");
        $h->setBasicAuthPass("abcdef");
        $h->setPostParameters(array("code" => "4uth0r1z4t10n"));
        $t = new Token($this->_config, NULL);
        $response = $t->handleRequest($h);
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('Basic realm="OAuth Server"', $response->getHeader("WWW-Authenticate"));
        $this->assertEquals('{"error":"invalid_client","error_description":"client authentication failed"}', $response->getContent());
    }

    public function testWithInvalidPassword()
    {
        $h = new HttpRequest("https://auth.example.org/token", "POST");
        $h->setBasicAuthUser("testcodeclient");
        $h->setBasicAuthPass("WRONGPASSWORD");
        $h->setPostParameters(array("code" => "4uth0r1z4t10n"));
        $t = new Token($this->_config, NULL);
        $response = $t->handleRequest($h);
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('Basic realm="OAuth Server"', $response->getHeader("WWW-Authenticate"));
        $this->assertEquals('{"error":"invalid_client","error_description":"client authentication failed"}', $response->getContent());
    }

    public function testClientIdUserMismatch()
    {
        $h = new HttpRequest("https://auth.example.org/token", "POST");
        $h->setBasicAuthUser("testcodeclient");
        $h->setBasicAuthPass("abcdef");
        $h->setPostParameters(array("code" => "4uth0r1z4t10n", "grant_type" => "authorization_code", "client_id" => "MISMATCH_CLIENT_ID"));
        $t = new Token($this->_config, NULL);
        $response = $t->handleRequest($h);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals('{"error":"invalid_grant","error_description":"client_id inconsistency: authenticating user must match POST body client_id"}', $response->getContent());
    }

    public function testExpiredAuthorization()
    {
        $h = new HttpRequest("https://auth.example.org/token", "POST");
        $h->setBasicAuthUser("testcodeclient");
        $h->setBasicAuthPass("abcdef");
        $h->setPostParameters(array("code" => "3xp1r3d4uth0r1z4t10n", "grant_type" => "authorization_code"));
        $t = new Token($this->_config, NULL);
        $response = $t->handleRequest($h);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals('{"error":"invalid_grant","error_description":"the authorization code expired"}', $response->getContent());
    }

    public function testNativeClientRequest()
    {
        $h = new HttpRequest("https://auth.example.org/token", "POST");
        $h->setPostParameters(array("client_id" => "testnativeclient", "code" => "n4t1v34uth0r1z4t10n", "grant_type" => "authorization_code"));
        $t = new Token($this->_config, NULL);
        $response = $t->handleRequest($h);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertRegexp('|^{"access_token":"[a-zA-Z0-9]+","expires_in":5,"scope":"read","refresh_token":"n4t1v3r3fr3sh","token_type":"bearer"}$|', $response->getContent());
    }

    public function testInvalidCode()
    {
        $h = new HttpRequest("https://auth.example.org/token", "POST");
        $h->setBasicAuthUser("testcodeclient");
        $h->setBasicAuthPass("abcdef");
        $h->setPostParameters(array("code" => "1nv4l1d4uth0r1z4t10n", "grant_type" => "authorization_code"));
        $t = new Token($this->_config, NULL);
        $response = $t->handleRequest($h);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals('{"error":"invalid_grant","error_description":"the authorization code was not found"}', $response->getContent());
    }

    public function testCodeNotBoundToUsedClient()
    {
        $h = new HttpRequest("https://auth.example.org/token", "POST");
        $h->setBasicAuthUser("testcodeclient");
        $h->setBasicAuthPass("abcdef");
        $h->setPostParameters(array("code" => "n4t1v34uth0r1z4t10n", "grant_type" => "authorization_code"));
        $t = new Token($this->_config, NULL);
        $response = $t->handleRequest($h);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals('{"error":"invalid_grant","error_description":"the authorization code was not found"}', $response->getContent());
    }

    public function checkReuseAuthorizationCode()
    {
        $h = new HttpRequest("https://auth.example.org/token", "POST");
        $h->setBasicAuthUser("testcodeclient");
        $h->setBasicAuthPass("abcdef");
        $h->setPostParameters(array("code" => "4uth0r1z4t10n", "grant_type" => "authorization_code"));
        $t = new Token($this->_config, NULL);
        $response = $t->handleRequest($h);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertRegexp('|^{"access_token":"[a-zA-Z0-9]+","expires_in":5,"scope":"read","refresh_token":"r3fr3sh","token_type":"bearer"}$|', $response->getContent());
        $response = $t->handleRequest($h);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals('{"error":"invalid_grant","error_description":"the authorization code was not found"}', $response->getContent());
    }

    public function testRefreshTokenSubScope()
    {
        $h = new HttpRequest("https://auth.example.org/token", "POST");
        $h->setBasicAuthUser("testcodeclient");
        $h->setBasicAuthPass("abcdef");
        $h->setPostParameters(array("refresh_token" => "r3fr3sh", "scope" => "foo", "grant_type" => "refresh_token"));
        $t = new Token($this->_config, NULL);
        $response = $t->handleRequest($h);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertRegexp('|^{"access_token":"[a-zA-Z0-9]+","expires_in":5,"scope":"foo","token_type":"bearer"}$|', $response->getContent());
    }

    public function testRefreshTokenNoSubScope()
    {
        $h = new HttpRequest("https://auth.example.org/token", "POST");
        $h->setBasicAuthUser("testcodeclient");
        $h->setBasicAuthPass("abcdef");
        $h->setPostParameters(array("refresh_token" => "r3fr3sh", "scope" => "we want no sub scope", "grant_type" => "refresh_token"));
        $t = new Token($this->_config, NULL);
        $response = $t->handleRequest($h);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertRegexp('|^{"access_token":"[a-zA-Z0-9]+","expires_in":5,"scope":"read write foo","token_type":"bearer"}$|', $response->getContent());
    }

}
