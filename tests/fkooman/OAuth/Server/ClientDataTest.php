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

use PHPUnit_Framework_TestCase;
use InvalidArgumentException;

class ClientDataTest extends PHPUnit_Framework_TestCase
{
    public static function validProviderFromArray()
    {
        return array(
            array('foo', "bar", "code", "http://xyz", "Foo", "foo", "http://x/a.png", "Description", "f@example.org"),
        );
    }

    public static function invalidProviderFromArray()
    {
        return array(
            array('foo', "bar", "code", "http://xyz", "Foo", "√", null, null, null, "scope token contains invalid characters"),
            array('foo', "bar", "code", "http://xyz", "Foo", "foo", "x", null, null, "icon must be valid URL with path"),
            array('foo', "bar", "code", "http://xyz", "Foo", "foo", "http://x/a.png", "Description", "nomail", "contact_email should be valid email address"),
        );
    }

   /**
     * @dataProvider validProviderFromArray
     */
    public function testValidFromArray($id, $secret, $type, $redirectUri, $name, $allowedScope, $icon, $description, $contactEmail)
    {
        $c = new ClientData(array("id" => $id, "secret" => $secret, "redirect_uri" => $redirectUri, "name" => $name, 'type' => $type, "allowed_scope" => $allowedScope, "icon" => $icon, "description" => $description, "contact_email" => $contactEmail));
        $this->assertEquals($id, $c->getId());
        $this->assertEquals($secret, $c->getSecret());
        $this->assertEquals($redirectUri, $c->getRedirectUri());
        $this->assertEquals($name, $c->getName());
        $this->assertEquals($allowedScope, $c->getAllowedScope());
        $this->assertEquals($icon, $c->getIcon());
        $this->assertEquals($description, $c->getDescription());
        $this->assertFalse($c->getDisableUserConsent());
        $this->assertEquals($contactEmail, $c->getContactEmail());
    }

   /**
     * @dataProvider invalidProviderFromArray
     */
    public function testInvalidFromArray($id, $secret, $type, $redirectUri, $name, $allowedScope, $icon, $description, $contactEmail, $exceptionMessage)
    {
        try {
            $c = new ClientData(array("id" => $id, "secret" => $secret, "type" => $type, "redirect_uri" => $redirectUri, "name" => $name, "allowed_scope" => $allowedScope, "icon" => $icon, "description" => $description, "contact_email" => $contactEmail));
            $this->assertTrue(false);
        } catch (InvalidArgumentException $e) {
            $this->assertEquals($exceptionMessage, $e->getMessage());
        }
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage "id" must be a non-empty string with maximum length 255
     */
    public function testBrokenFromArray()
    {
        new ClientData(array("foo" => "bar"));
    }

    public function testVerifyRedirectUriNoRegExp()
    {
        $clientData = new ClientData(
            array(
                'id' => 'foo',
                'redirect_uri' => 'https://www.example.org/callback',
                'name' => 'Foo',
                'type' => 'code'
            )
        );

        $this->assertTrue($clientData->verifyRedirectUri('https://www.example.org/callback'));
        $this->assertFalse($clientData->verifyRedirectUri('https://www.example.org/callback0'));
    }

    public function testVerifyRedirectUriRegExp()
    {
        $clientData = new ClientData(
            array(
                'id' => 'foo',
                'redirect_uri' => 'https://www.example.org/callback/[0-9]+',
                'name' => 'Foo',
                'type' => 'code'
            )
        );

        $this->assertTrue(
            $clientData->verifyRedirectUri(
                'https://www.example.org/callback/[0-9]+'
            )
        );
        $this->assertTrue(
            $clientData->verifyRedirectUri(
                'https://www.example.org/callback/55',
                 true
            )
        );
        $this->assertFalse(
            $clientData->verifyRedirectUri(
                'https://www.example.org/callback/a5',
                 true
            )
        );
    }
}
