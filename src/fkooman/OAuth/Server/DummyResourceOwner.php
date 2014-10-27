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

use fkooman\Ini\IniReader;
use fkooman\Json\Json;
use fkooman\Json\Exception\JsonException;
use RuntimeException;

class DummyResourceOwner implements IResourceOwner
{
    /** @var fkooman\Ini\IniReader */
    private $iniReader;

    public function __construct(IniReader $c)
    {
        $this->iniReader = $c;
    }

    public function setResourceOwnerHint($resourceOwnerHint)
    {
        // nop
    }

    public function getId()
    {
        return $this->iniReader->v('DummyResourceOwner', 'uid');
    }

    public function getEntitlement()
    {
        $entitlement = array();
        try {
            $j = new Json();
            $entitlement = $j->decodeFile($this->iniReader->v('entitlementsFile'));
        } catch (RuntimeException $e) {
            // problem with reading the entitlement file
            return array();
        } catch (JsonException $e) {
            // problem with the JSON formatting of entitlement file
            return array();
        }
        if (is_array($entitlement) && isset($entitlement[$this->getId()]) && is_array($entitlement[$this->getId()])) {
            return $entitlement[$this->getId()];
        }

        return array();
    }

    public function getExt()
    {
        // unsupported
        return array();
    }
}
