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

namespace OAuth;

use \RestService\Utils\Config as Config;

class DummyResourceOwner implements IResourceOwner
{
    private $_c;

    public function __construct(Config $c)
    {
        $this->_c = $c;
    }

    public function setResourceOwnerHint($resourceOwnerHint)
    {
        // nop
    }

    public function getId()
    {
        return $this->_c->getSectionValue('DummyResourceOwner', 'uid');
    }

    public function getEntitlement()
    {
        return $this->_c->getSectionValue('DummyResourceOwner', 'entitlement');
    }

    public function getExt()
    {
        // unsupported
        return array();
    }

}
