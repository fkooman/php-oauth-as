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
use InvalidArgumentException;
use RuntimeException;

class Entitlements
{
    /** @var array */
    private $entitlements;

    public function __construct($entitlementsFile)
    {
        try {
            $entitlements = Json::decodeFile($entitlementsFile);
        } catch (InvalidArgumentException $e) {
            throw new RuntimeException('unable to parse the file');
        }
        if (!is_array($entitlements)) {
            throw new RuntimeException('entitlements not stored as JSON array');
        }

        $this->entitlements = $entitlements;
    }

    public function getEntitlement($userId)
    {
        if (!array_key_exists($userId, $this->entitlements)) {
            // no entitlements found for this user
            return array();
        }
        if (!is_array($this->entitlements[$userId])) {
            throw new RuntimeException('entitlement for user not stored as JSON array');
        }

        return $this->entitlements[$userId];
    }
}
