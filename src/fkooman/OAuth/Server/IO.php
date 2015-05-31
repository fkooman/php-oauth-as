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

use InvalidArgumentException;

class IO
{
    /** @var int */
    private $randomLength;

    public function __construct($randomLength = 16)
    {
        $l = intval($randomLength);
        if (8 > $l) {
            throw new InvalidArgumentException('random length MUST be at least 8');
        }
        $this->randomLength = $l;
    }

    public function getRandomHex()
    {
        return bin2hex(
            openssl_random_pseudo_bytes(
                $this->randomLength
            )
        );
    }

    public function getTime()
    {
        return time();
    }
}
