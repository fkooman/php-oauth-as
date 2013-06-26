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

require_once dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . "vendor" . DIRECTORY_SEPARATOR . "autoload.php";

use \OAuth\ClientRegistration as ClientRegistration;
use \OAuth\ClientRegistrationException as ClientRegistrationException;

class ClientRegistrationTest extends PHPUnit_Framework_TestCase
{
    public static function validProvider()
    {
        return array(
            array("foo", NULL, "user_agent_based_application", "http://www.example.org/cb", "Foo Client"),
            array("foo", "s3cr3t", "web_application", "http://www.example.org/cb", "Foo Client"),
            array("foo:bar", NULL, "user_agent_based_application", "http://www.example.org/cb", "Foo Client"),
            array("foo", "s3cr3t", "native_application", "twitter://app/callback", "Foo Client"),
        );
    }

    public static function validProviderFromArray()
    {
        return array(
            array('foo', "bar", "web_application", "http://xyz", "Foo", "foo", "http://x/a.png", "Description", "f@example.org"),
        );
    }

    public static function invalidProvider()
    {
        return array(
            array("foo", NULL, "web_application", "http://www.example.org/cb", "Foo Client", "secret should be set for web application type"),
            array("foo:bar", "s3cr3t", "native_application", "http://www.example.org/cb", "Foo Client", "client_id cannot contain a colon when using a secret"),
            array(NULL, NULL, NULL, NULL, NULL, "id cannot be empty"),
            array('√', NULL, NULL, NULL, NULL, "id contains invalid character"),
            array('foo', NULL, "xyz", NULL, NULL, "type not supported"),
            array('foo', "√", NULL, NULL, NULL, "secret contains invalid character"),
            array('foo', "bar", "web_application", "http://x/y", NULL, "name cannot be empty"),
            array('foo', "bar", "web_application", "http://", NULL, "redirect_uri should be valid URL"),
            array('foo', "bar", "web_application", "http://foo/bar#fragment", NULL, "redirect_uri cannot contain a fragment"),
        );
    }

    public static function invalidProviderFromArray()
    {
        return array(
            array('foo', "bar", "web_application", "http://xyz", "Foo", "√", NULL, NULL, NULL, "scope is invalid"),
            array('foo', "bar", "web_application", "http://xyz", "Foo", "foo", "x", NULL, NULL, "icon should be either empty or valid URL with path"),
            array('foo', "bar", "web_application", "http://xyz", "Foo", "foo", "http://x/a.png", "Description", "nomail", "contact email should be either empty or valid email address"),
        );
    }

   /**
     * @dataProvider validProvider
     */
    public function testValid($id, $secret, $type, $redirectUri, $name)
    {
        $c = new ClientRegistration($id, $secret, $type, $redirectUri, $name);
        $this->assertEquals($id, $c->getId());
        $this->assertEquals($secret, $c->getSecret());
        $this->assertEquals($type, $c->getType());
        $this->assertEquals($redirectUri, $c->getRedirectUri());
        $this->assertEquals($name, $c->getName());
        $this->assertNull($c->getDescription());
        $this->assertNull($c->getIcon());
        $this->assertNull($c->getAllowedScope());
        $this->assertNull($c->getContactEmail());
    }

   /**
     * @dataProvider invalidProvider
     */
    public function testInvalid($id, $secret, $type, $redirectUri, $name, $exceptionMessage)
    {
        try {
            $c = new ClientRegistration($id, $secret, $type, $redirectUri, $name);
            $this->assertTrue(FALSE);
        } catch (ClientRegistrationException $e) {
            $this->assertEquals($exceptionMessage, $e->getMessage());
        }
    }

   /**
     * @dataProvider validProviderFromArray
     */
    public function testValidFromArray($id, $secret, $type, $redirectUri, $name, $allowedScope, $icon, $description, $contactEmail)
    {
        $c = ClientRegistration::fromArray(array("id" => $id, "secret" => $secret, "type" => $type, "redirect_uri" => $redirectUri, "name" =>$name, "allowed_scope" => $allowedScope, "icon" => $icon, "description" => $description, "contact_email" => $contactEmail));
        $this->assertEquals($id, $c->getId());
        $this->assertEquals($secret, $c->getSecret());
        $this->assertEquals($type, $c->getType());
        $this->assertEquals($redirectUri, $c->getRedirectUri());
        $this->assertEquals($name, $c->getName());
        $this->assertEquals($allowedScope, $c->getAllowedScope());
        $this->assertEquals($icon, $c->getIcon());
        $this->assertEquals($description, $c->getDescription());
        $this->assertEquals($contactEmail, $c->getContactEmail());
        $this->assertEquals(array("id" => $id, "secret" => $secret, "type" => $type, "redirect_uri" => $redirectUri, "name" => $name, "allowed_scope" => $allowedScope, "icon" => $icon, "description" => $description, "contact_email" => $contactEmail), $c->getClientAsArray());
    }

   /**
     * @dataProvider invalidProviderFromArray
     */
    public function testInvalidFromArray($id, $secret, $type, $redirectUri, $name, $allowedScope, $icon, $description, $contactEmail, $exceptionMessage)
    {
        try {
            $c = ClientRegistration::fromArray(array("id" => $id, "secret" => $secret, "type" => $type, "redirect_uri" => $redirectUri, "name" =>$name, "allowed_scope" => $allowedScope, "icon" => $icon, "description" => $description, "contact_email" => $contactEmail));
            $this->assertTrue(FALSE);
        } catch (ClientRegistrationException $e) {
            $this->assertEquals($exceptionMessage, $e->getMessage());
        }
    }

    public function testBrokenFromArray()
    {
        try {
            $c = ClientRegistration::fromArray(array("foo" => "bar"));
            $this->assertTrue(FALSE);
        } catch (ClientRegistrationException $e) {
            $this->assertEquals("not a valid client, 'id' not set", $e->getMessage());
        }
    }

}
