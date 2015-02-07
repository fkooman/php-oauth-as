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

use fkooman\Json\Json;
use fkooman\Json\Exception\JsonException;
use RuntimeException;

class DummyResourceOwner implements IResourceOwner
{
    /** @var string */
    private $userId;

    /** @var array */
    private $entitlements;

    public function __construct($userId, array $entitlements)
    {
        $this->userId = $userId;
        $this->entitlements = $entitlements;
    }

    public function getId()
    {
        return $this->userId;
    }

    public function getEntitlement()
    {
        return $this->entitlements;
    }
}
