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

class EntitlementsTest extends PHPUnit_Framework_TestCase
{

    public function testGoodCase()
    {
        $e = new Entitlements(dirname(dirname(dirname(__DIR__))).'/data/entitlements.json');
        $this->assertEquals(
            array(
                'urn:x-foo:service:access',
                'urn:x-bar:privilege:admin',
                'http://php-oauth.net/entitlement/manage'
            ),
            $e->getEntitlement('fkooman')
        );
    }
    
    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage unable to read file
     */
    public function testMissingFile()
    {
        $e = new Entitlements('foo');
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage unable to parse the file
     */
    public function testBrokenFile()
    {
        $e = new Entitlements(__FILE__);
    }
}
