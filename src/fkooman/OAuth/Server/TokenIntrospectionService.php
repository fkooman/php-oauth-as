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

use fkooman\Rest\Service;
use fkooman\Http\JsonResponse;
use fkooman\Http\Request;
use fkooman\Http\Exception\BadRequestException;

/**
 * Implementation of https://tools.ietf.org/html/draft-richer-oauth-introspection.
 */
class TokenIntrospectionService extends Service
{
    /** @var fkooman\OAuth\Server\PdoStorage */
    private $db;

    /** @var fkooman\OAuth\Server\IO */
    private $io;

    public function __construct(PdoStorage $db, IO $io = null)
    {
        parent::__construct();
        $this->db = $db;

        if (null === $io) {
            $io = new IO();
        }
        $this->io = $io;

        $compatThis = &$this;

        $this->get(
            '*',
            function (Request $request) use ($compatThis) {
                return $compatThis->getTokenIntrospection($request, $request->getUrl()->getQueryParameter('token'));
            }
        );

        $this->post(
            '*',
            function (Request $request) use ($compatThis) {
                return $compatThis->getTokenIntrospection($request, $request->getPostParameter('token'));
            }
        );
    }

    public function getTokenIntrospection(Request $request, $tokenValue)
    {
        if (null === $tokenValue) {
            throw new BadRequestException('invalid_token', 'the token parameter is missing');
        }
        // FIXME: validate token format

        $accessToken = $this->db->getAccessToken($tokenValue);

        if (false === $accessToken) {
            // token does not exist
            $tokenInfo = array(
                'active' => false,
            );
        } elseif ($this->io->getTime() > $accessToken['issue_time'] + $accessToken['expires_in']) {
            // token expired
            $tokenInfo = array(
                'active' => false,
            );
        } else {
            // token exists and did not expire
            $tokenInfo = array(
                'active' => true,
                'exp' => intval($accessToken['issue_time'] + $accessToken['expires_in']),
                'iat' => intval($accessToken['issue_time']),
                'scope' => $accessToken['scope'],
                'iss' => $request->getUrl()->getHost(),
                'client_id' => $accessToken['client_id'],
                'sub' => $accessToken['resource_owner_id'],
                'user_id' => $accessToken['resource_owner_id'],
                'token_type' => 'bearer',
            );

            // as long as we have no RS registration we cannot set the audience...
            // $tokenInfo['aud'] => 'foo';
        }

        $response = new JsonResponse();
        $response->setHeaders(array('Cache-Control' => 'no-store', 'Pragma' => 'no-cache'));
        $response->setBody($tokenInfo);

        return $response;
    }
}
