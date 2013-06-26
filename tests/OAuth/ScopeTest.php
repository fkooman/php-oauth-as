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

use \OAuth\Scope as Scope;
use \OAuth\ScopeException as ScopeException;

class ScopeTest extends PHPUnit_Framework_TestCase
{
    public function testBasicScope()
    {
        $s = new Scope("read write delete");
        $this->assertTrue($s->isSubsetOf(new Scope("read write delete update")));
        $this->assertTrue($s->hasScope(new Scope("read")));
        $this->assertTrue($s->hasScope(new Scope("write")));
        $this->assertEquals("delete read write", $s->getScope());
        $this->assertEquals(array("delete", "read", "write"), $s->getScopeAsArray());
    }

    public function testBasicScopeArray()
    {
        $s = new Scope(array("read", "write", "delete"));
        $this->assertEquals("delete read write", $s->getScope());
        $this->assertEquals(array("delete", "read", "write"), $s->getScopeAsArray());
    }

    public function testEmptyScope()
    {
        $s = new Scope(NULL);
        $this->assertEquals("", $s->getScope());
        $this->assertEquals(array(), $s->getScopeAsArray());
        $t = new Scope("");
        $this->assertEquals("", $t->getScope());
        $this->assertEquals(array(), $t->getScopeAsArray());
        $u = new Scope(array());
        $this->assertEquals("", $u->getScope());
        $this->assertEquals(array(), $u->getScopeAsArray());
    }

    public function testFailingScope()
    {
        $s = new Scope("read write delete");
        $this->assertFalse($s->isSubsetOf(new Scope("read write update")));
        $this->assertFalse($s->hasScope(new Scope("foo")));
    }

    /**
     * @expectedException \OAuth\ScopeException
     */
    public function testMalformedScope()
    {
        $s = new Scope(" ");
    }

    public function testMerge()
    {
        $s = new Scope("read write delete merge");
        $t = new Scope("write delete");
        $s->mergeWith($t);
        $this->assertEquals("delete merge read write", $s->getScope());
        $this->assertEquals(array("delete", "merge", "read", "write"), $s->getScopeAsArray());
    }

    public function testClone()
    {
        $s = new Scope("read write delete merge");
        $t = new Scope("write delete");
        $c = clone $s;
        $c->mergeWith($t);
        $this->assertEquals("delete merge read write", $c->getScope());
        $this->assertEquals(array("delete", "merge", "read", "write"), $c->getScopeAsArray());
    }

}
