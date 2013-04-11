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

use \OAuth\ResourceServerException as ResourceServerException;
use \OAuth\AuthorizeResult as AuthorizeResult;
use \OAuth\AuthorizationServer as AuthorizationServer;
use \OAuth\ResourceServer as ResourceServer;

class ImplicitGrantTest extends OAuthHelper
{
    private $_as;
    private $_ro;
    private $_rs;

    public function setUp()
    {
        parent::setUp();

        $oauthStorageBackend = 'OAuth\\' . $this->_config->getValue('storageBackend');
        $storage = new $oauthStorageBackend($this->_config);

        $authMech = 'OAuth\\' . $this->_config->getValue('authenticationMechanism');
        $this->_ro = new $authMech($this->_config);

        $this->_rs = new ResourceServer($storage);

        $this->_as = new AuthorizationServer($storage, $this->_config);
    }

#    public function testImplicitGrant() {
#        // now we ask the authorize endpoint
#        $get = array("client_id" => "testclient",
#                     "response_type" => "token",
#                     "scope" => "read");
#        $response = $this->_as->authorize($this->_ro, $get);
#        $this->assertEquals(AuthorizeResult::ASK_APPROVAL, $response->getAction());
#        $this->assertEquals("testclient", $response->getClient()->getId());

#        // now we approve
#        $post = array("approval" => "Approve", "scope" => array("read"));
#        $response = $this->_as->approve($this->_ro, $get, $post);
#        $this->assertEquals(AuthorizeResult::REDIRECT, $response->getAction());
#        // regexp match to deal with random access token
#        $this->assertRegExp('|^http://localhost/php-oauth/unit/test.html#access_token=[a-zA-Z0-9]+&expires_in=5&token_type=bearer&scope=read$|', $response->getRedirectUri()->getUri());
#    }

#    public function testImplicitGrantWithoutScope() {
#        // now we ask the authorize endpoint
#        $get = array("client_id" => "testclient",
#                     "response_type" => "token");
#        $response = $this->_as->authorize($this->_ro, $get);
#        $this->assertEquals(AuthorizeResult::ASK_APPROVAL, $response->getAction());
#        $this->assertEquals("testclient", $response->getClient()->getId());

#        // now we approve
#        $post = array("approval" => "Approve", "scope" => array());
#        $response = $this->_as->approve($this->_ro, $get, $post);
#        $this->assertEquals(AuthorizeResult::REDIRECT, $response->getAction());
#        // regexp match to deal with random access token
#        $this->assertRegExp('|^http://localhost/php-oauth/unit/test.html#access_token=[a-zA-Z0-9]+&expires_in=5&token_type=bearer$|', $response->getRedirectUri()->getUri());
#    }

    public function testAuthorizationCode()
    {
        $get = array("client_id" => "testcodeclient",
                     "response_type" => "code",
                     "scope" => "read");
        $response = $this->_as->authorize($this->_ro, $get);
        $this->assertEquals(AuthorizeResult::ASK_APPROVAL, $response->getAction());
        $this->assertEquals("testcodeclient", $response->getClient()->getId());

        // now we approve
        $post = array("approval" => "Approve", "scope" => array("read"));
        $response = $this->_as->approve($this->_ro, $get, $post);
        $this->assertEquals(AuthorizeResult::REDIRECT, $response->getAction());
        // regexp match to deal with random authorization code
        $this->assertRegExp('|^http://localhost/php-oauth/unit/test.html\?code=[a-zA-Z0-9]+$|', $response->getRedirectUri()->getUri());

        preg_match('|^http://localhost/php-oauth/unit/test.html\?code=([a-zA-Z0-9]+)$|', $response->getRedirectUri()->getUri(), $matches);

        // exchange code for token
        $post = array ("grant_type" => "authorization_code",
                       "code" => $matches[1]);
        $response = $this->_as->token($post, "testcodeclient", "abcdef");

        $this->assertRegExp('|^[a-zA-Z0-9]+$|', $response['access_token']);
        $this->assertEquals(5, $response['expires_in']);
        $this->assertRegExp('|^[a-zA-Z0-9]+$|', $response['refresh_token']);
        $this->assertEquals("read", $response['scope']);
        // .. redirect_uri

        // verify token
        $this->_rs->verifyAuthorizationHeader("Bearer " . $response['access_token']);
        $this->assertEquals("1234-5678-9999", $this->_rs->getResourceOwnerId());
        $this->assertTrue($this->_rs->hasEntitlement("urn:x-oauth:entitlement:applications"));
        $this->assertFalse($this->_rs->hasEntitlement("foobar"));
        $this->assertTrue($this->_rs->hasScope("read"));
        $this->assertFalse($this->_rs->hasScope("foo"));

        try {
            $this->_rs->requireScope("foo");
            $this->assertTrue(FALSE);
        } catch (ResourceServerException $e) {
            $this->assertEquals("insufficient_scope", $e->getMessage());
            $this->assertEquals("no permission for this call with granted scope", $e->getDescription());
        }

        try {
            $this->_rs->requireEntitlement("foobar");
            $this->assertTrue(FALSE);
        } catch (ResourceServerException $e) {
            $this->assertEquals("insufficient_entitlement", $e->getMessage());
            $this->assertEquals("no permission for this call with granted entitlement", $e->getDescription());
        }

        // wait for 6 seconds so the token should be expired...
        sleep(6);

        try {
            $this->_rs->verifyAuthorizationHeader("Bearer " . $response['access_token']);
            $this->assertTrue(FALSE);
        } catch (ResourceServerException $e) {
            $this->assertEquals("invalid_token", $e->getMessage());
            $this->assertEquals("the access token expired", $e->getDescription());
        }

        // use the refresh token to get a new access token
        $post = array ("grant_type" => "refresh_token",
                       "refresh_token" => $response['refresh_token']);
        $response = $this->_as->token($post, "testcodeclient", "abcdef");

        $this->assertRegExp('|^[a-zA-Z0-9]+$|', $response['access_token']);
        $this->_rs->verifyAuthorizationHeader("Bearer " . $response['access_token']);
        $this->assertEquals("1234-5678-9999", $this->_rs->getResourceOwnerId());
    }

}
