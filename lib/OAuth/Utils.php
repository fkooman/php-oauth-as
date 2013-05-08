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

class Utils
{
    public static function randomHex($len = 16)
    {
        $randomString = bin2hex(openssl_random_pseudo_bytes($len, $strong));
        // @codeCoverageIgnoreStart
        if (FALSE === $strong) {
            throw new Exception("unable to securely generate random string");
        }
        // @codeCoverageIgnoreEnd
        return $randomString;
    }

    public static function getParameter(array $parameters, $key)
    {
        return (array_key_exists($key, $parameters) && !empty($parameters[$key])) ? $parameters[$key] : NULL;
    }

}
