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
use fkooman\Http\Request;
use fkooman\Rest\Service;
use fkooman\Http\JsonResponse;
use fkooman\Http\Response;
use fkooman\Http\Exception\BadRequestException;
use fkooman\Http\Exception\InternalServerErrorException;
use fkooman\Http\Exception\NotFoundException;
use fkooman\Http\Exception\ForbiddenException;
use fkooman\Rest\Plugin\Bearer\BearerAuthentication;
use fkooman\Rest\Plugin\Bearer\TokenInfo;
use fkooman\Rest\Plugin\Bearer\Scope;
use InvalidArgumentException;

class ApiService extends Service
{
    /** @var fkooman\OAuth\Server\PdoSTorage */
    private $storage;

    /** @var fkooman\OAuth\Server\IO */
    private $io;

    public function __construct(PdoStorage $storage, Entitlements $entitlements, IO $io = null)
    {
        parent::__construct();
        $this->storage = $storage;
        $this->entitlements = $entitlements;

        if (null === $io) {
            $io = new IO();
        }
        $this->io = $io;

        // compatibility for PHP 5.3
        $compatThis = &$this;

        $this->options(
            '*',
            function () {
                return new Response();
            },
            array(
                'skipPlugins' => array(
                    'fkooman\Rest\Plugin\Bearer\BearerAuthentication'
                )
            )
        );

        $this->post(
            '/authorizations/',
            function (Request $request, TokenInfo $tokenInfo) use ($compatThis) {
                return $compatThis->postAuthorization($request, $tokenInfo);
            }
        );

        $this->get(
            '/authorizations/:id',
            function (Request $request, TokenInfo $tokenInfo, $id) use ($compatThis) {
                return $compatThis->getAuthorization($request, $tokenInfo, $id);
            }
        );

        $this->delete(
            '/authorizations/:id',
            function (Request $request, TokenInfo $tokenInfo, $id) use ($compatThis) {
                return $compatThis->deleteAuthorization($request, $tokenInfo, $id);
            }
        );

        $this->get(
            '/authorizations/',
            function (Request $request, TokenInfo $tokenInfo) use ($compatThis) {
                return $compatThis->getAuthorizations($request, $tokenInfo);
            }
        );

        $this->get(
            '/applications/',
            function (Request $request, TokenInfo $tokenInfo) use ($compatThis) {
                return $compatThis->getApplications($request, $tokenInfo);
            }
        );

        $this->delete(
            '/applications/:id',
            function (Request $request, TokenInfo $tokenInfo, $id) use ($compatThis) {
                return $compatThis->deleteApplication($request, $tokenInfo, $id);
            }
        );

        $this->get(
            '/applications/:id',
            function (Request $request, TokenInfo $tokenInfo, $id) use ($compatThis) {
                return $compatThis->getApplication($request, $tokenInfo, $id);
            }
        );

        $this->post(
            '/applications/',
            function (Request $request, TokenInfo $tokenInfo) use ($compatThis) {
                return $compatThis->postApplication($request, $tokenInfo);
            }
        );

        $this->put(
            '/applications/:id',
            function (Request $request, TokenInfo $tokenInfo, $id) use ($compatThis) {
                return $compatThis->putApplication($request, $tokenInfo, $id);
            }
        );

        $this->get(
            '/stats/',
            function (Request $request, TokenInfo $tokenInfo) use ($compatThis) {
                return $compatThis->getStats($request, $tokenInfo);
            }
        );
    }

    public function postAuthorization(Request $request, TokenInfo $tokenInfo)
    {
        $this->requireScope($tokenInfo->getScope(), 'http://php-oauth.net/scope/authorize');
        $data = Json::decode($request->getContent());
        if (null === $data || !is_array($data) || !array_key_exists('client_id', $data) || !array_key_exists('scope', $data)) {
            throw new BadRequestException('missing client_id or scope');
        }

        // client needs to exist
        $clientId = $data['client_id'];
        $client = $this->storage->getClient($clientId);
        if (false === $client) {
            throw new NotFoundException('client is not registered');
        }

        $refreshToken = (array_key_exists('refresh_token', $data) && $data['refresh_token']) ? $this->io->getRandomHex() : null;

        // check to see if an authorization for this client/resource_owner already exists
        if (false === $this->storage->getApprovalByResourceOwnerId($clientId, $tokenInfo->get('sub'))) {
            if (false === $this->storage->addApproval($clientId, $tokenInfo->get('sub'), $data['scope'], $refreshToken)) {
                throw new InternalServerErrorException('unable to add authorization');
            }
        } else {
            throw new BadRequestException(
                'authorization already exists for this client and resource owner'
            );
        }

        $response = new JsonResponse(201);
        $response->setContent(array('status' => 'ok'));

        return $response;
    }

    public function getAuthorization(Request $request, TokenInfo $tokenInfo, $id)
    {
        $this->requireScope($tokenInfo->getScope(), 'http://php-oauth.net/scope/authorize');
        $data = $this->storage->getApprovalByResourceOwnerId($id, $tokenInfo->get('sub'));
        if (false === $data) {
            throw new NotFoundException('authorization not found');
        }
        $response = new JsonResponse(200);
        $response->setContent($data);

        return $response;
    }

    public function deleteAuthorization(Request $request, TokenInfo $tokenInfo, $id)
    {
        $this->requireScope($tokenInfo->getScope(), 'http://php-oauth.net/scope/authorize');
        if (false === $this->storage->deleteApproval($id, $tokenInfo->get('sub'))) {
            throw new NotFoundException('authorization not found');
        }
        $response = new JsonResponse(200);
        $response->setContent(array('status' => 'ok'));

        return $response;
    }

    public function getAuthorizations(Request $request, TokenInfo $tokenInfo)
    {
        $this->requireScope($tokenInfo->getScope(), 'http://php-oauth.net/scope/authorize');
        $data = $this->storage->getApprovals($tokenInfo->get('sub'));

        $response = new JsonResponse(200);
        $response->setContent($data);

        return $response;
    }

    public function getApplications(Request $request, TokenInfo $tokenInfo)
    {
        $this->requireScope($tokenInfo->getScope(), 'http://php-oauth.net/scope/manage');
        $this->requireEntitlement($tokenInfo->get('sub'), 'http://php-oauth.net/entitlement/manage');

        $data = $this->storage->getClients();
        $response = new JsonResponse(200);
        $response->setContent($data);

        return $response;
    }

    public function deleteApplication(Request $request, TokenInfo $tokenInfo, $id)
    {
        $this->requireScope($tokenInfo->getScope(), 'http://php-oauth.net/scope/manage');
        $this->requireEntitlement($tokenInfo->get('sub'), 'http://php-oauth.net/entitlement/manage');

        if (false === $this->storage->deleteClient($id)) {
            throw new NotFoundException('application not found');
        }
        $response = new JsonResponse(200);
        $response->setContent(array('status' => 'ok'));

        return $response;
    }

    public function getApplication(Request $request, TokenInfo $tokenInfo, $id)
    {
        $this->requireScope($tokenInfo->getScope(), 'http://php-oauth.net/scope/manage');
        $this->requireEntitlement($tokenInfo->get('sub'), 'http://php-oauth.net/entitlement/manage');

        $data = $this->storage->getClient($id);
        if (false === $data) {
            throw new NotFoundException('application not found');
        }
        $response = new JsonResponse(200);
        $response->setContent($data->toArray());

        return $response;
    }

    public function postApplication(Request $request, TokenInfo $tokenInfo)
    {
        $this->requireScope($tokenInfo->getScope(), 'http://php-oauth.net/scope/manage');
        $this->requireEntitlement($tokenInfo->get('sub'), 'http://php-oauth.net/entitlement/manage');

        $clientData = null;
        try {
            $clientData = new ClientData(Json::decode($request->getContent()));
        } catch (InvalidArgumentException $e) {
            throw new BadRequestException('invalid client data', $e->getMessage());
        }

        // check to see if an application with this id already exists
        if (false === $this->storage->getClient($clientData->getId())) {
            if (false === $this->storage->addClient($clientData)) {
                throw new InternalServerErrorException('unable to add application');
            }
        } else {
            throw new BadRequestException('application already exists');
        }
        $response = new JsonResponse(201);
        $response->setContent(array('status' => 'ok'));

        return $response;
    }

    public function putApplication(Request $request, TokenInfo $tokenInfo, $id)
    {
        $this->requireScope($tokenInfo->getScope(), 'http://php-oauth.net/scope/manage');
        $this->requireEntitlement($tokenInfo->get('sub'), 'http://php-oauth.net/entitlement/manage');

        $clientData = null;
        try {
            $clientData = new ClientData(Json::decode($request->getContent()));
        } catch (InvalidArgumentException $e) {
            throw new BadRequestException('invalid client data', $e->getMessage());
        }
        if ($clientData->getId() !== $id) {
            throw new BadRequestException('resource does not match client id value');
        }
        if (false === $this->storage->updateClient($id, $clientData)) {
            throw new InternalServerErrorException('unable to update application');
        }

        $response = new JsonResponse(200);
        $response->setContent(array('status' => 'ok'));

        return $response;
    }

    public function getStats(Request $request, TokenInfo $tokenInfo)
    {
        $this->requireScope($tokenInfo->getScope(), 'http://php-oauth.net/scope/manage');
        $this->requireEntitlement($tokenInfo->get('sub'), 'http://php-oauth.net/entitlement/manage');

        $data = $this->storage->getStats();

        $response = new JsonResponse(200);
        $response->setContent($data);

        return $response;
    }

    private function requireScope(Scope $scope, $scopeValue)
    {
        if (!$scope->hasScope($scopeValue)) {
            throw new ForbiddenException('insufficient_scope', sprintf('need scope "%s"', $scopeValue));
        }
    }

    private function requireEntitlement($userId, $entitlementValue)
    {
        $entitlements = $this->entitlements->getEntitlement($userId);
        if (array_key_exists($entitlementValue, $entitlements)) {
            throw new ForbiddenException('insufficient_entitlement', sprintf('need entitlement "%s"', $entitlementValue));
        }
    }
}
