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
use fkooman\Rest\Plugin\Bearer\TokenIntrospection;
use fkooman\Rest\Plugin\Bearer\Scope;
use fkooman\Rest\Plugin\Bearer\Entitlement;
use InvalidArgumentException;

class ApiService extends Service
{
    /** @var fkooman\OAuth\Server\PdoSTorage */
    private $storage;

    public function __construct(PdoStorage $storage)
    {
        parent::__construct();
        $this->storage = $storage;

        // compatibility for PHP 5.3
        $compatThis = &$this;

        $this->options(
            '*',
            function () {
                return new Response();
            },
            array('fkooman\Rest\Plugin\Bearer\BearerAuthentication')
        );

        $this->post(
            '/authorizations/',
            function (Request $request, TokenIntrospection $tokenIntrospection) use ($compatThis) {
                return $compatThis->postAuthorization($request, $tokenIntrospection);
            }
        );

        $this->get(
            '/authorizations/:id',
            function (Request $request, TokenIntrospection $tokenIntrospection, $id) use ($compatThis) {
                return $compatThis->getAuthorization($request, $tokenIntrospection, $id);
            }
        );

        $this->delete(
            '/authorizations/:id',
            function (Request $request, TokenIntrospection $tokenIntrospection, $id) use ($compatThis) {
                return $compatThis->deleteAuthorization($request, $tokenIntrospection, $id);
            }
        );

        $this->get(
            '/authorizations/',
            function (Request $request, TokenIntrospection $tokenIntrospection) use ($compatThis) {
                return $compatThis->getAuthorizations($request, $tokenIntrospection);
            }
        );

        $this->get(
            '/applications/',
            function (Request $request, TokenIntrospection $tokenIntrospection) use ($compatThis) {
                return $compatThis->getApplications($request, $tokenIntrospection);
            }
        );

        $this->delete(
            '/applications/:id',
            function (Request $request, TokenIntrospection $tokenIntrospection, $id) use ($compatThis) {
                return $compatThis->deleteApplication($request, $tokenIntrospection, $id);
            }
        );

        $this->get(
            '/applications/:id',
            function (Request $request, TokenIntrospection $tokenIntrospection, $id) use ($compatThis) {
                return $compatThis->getApplication($request, $tokenIntrospection, $id);
            }
        );

        $this->post(
            '/applications/',
            function (Request $request, TokenIntrospection $tokenIntrospection) use ($compatThis) {
                return $compatThis->postApplication($request, $tokenIntrospection);
            }
        );

        $this->put(
            '/applications/:id',
            function (Request $request, TokenIntrospection $tokenIntrospection, $id) use ($compatThis) {
                return $compatThis->putApplication($request, $tokenIntrospection, $id);
            }
        );

        $this->get(
            '/stats/',
            function (Request $request, TokenIntrospection $tokenIntrospection) use ($compatThis) {
                return $compatThis->getStats($request, $tokenIntrospection);
            }
        );
    }

    public function postAuthorization(Request $request, TokenIntrospection $tokenIntrospection)
    {
        $this->requireScope($tokenIntrospection->getScope(), 'http://php-oauth.net/scope/authorize');
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

        $refreshToken = (array_key_exists('refresh_token', $data) && $data['refresh_token']) ? Utils::randomHex(16) : null;

        // check to see if an authorization for this client/resource_owner already exists
        if (false === $this->storage->getApprovalByResourceOwnerId($clientId, $tokenIntrospection->getSub())) {
            if (false === $this->storage->addApproval($clientId, $tokenIntrospection->getSub(), $data['scope'], $refreshToken)) {
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

    public function getAuthorization(Request $request, TokenIntrospection $tokenIntrospection, $id)
    {
        $this->requireScope($tokenIntrospection->getScope(), 'http://php-oauth.net/scope/authorize');
        $data = $this->storage->getApprovalByResourceOwnerId($id, $tokenIntrospection->getSub());
        if (false === $data) {
            throw new NotFoundException('authorization not found');
        }
        $response = new JsonResponse(200);
        $response->setContent($data);

        return $response;
    }

    public function deleteAuthorization(Request $request, TokenIntrospection $tokenIntrospection, $id)
    {
        $this->requireScope($tokenIntrospection->getScope(), 'http://php-oauth.net/scope/authorize');
        if (false === $this->storage->deleteApproval($id, $tokenIntrospection->getSub())) {
            throw new NotFoundException('authorization not found');
        }
        $response = new JsonResponse(200);
        $response->setContent(array('status' => 'ok'));

        return $response;
    }

    public function getAuthorizations(Request $request, TokenIntrospection $tokenIntrospection)
    {
        $this->requireScope($tokenIntrospection->getScope(), 'http://php-oauth.net/scope/authorize');
        $data = $this->storage->getApprovals($tokenIntrospection->getSub());

        $response = new JsonResponse(200);
        $response->setContent($data);

        return $response;
    }

    public function getApplications(Request $request, TokenIntrospection $tokenIntrospection)
    {
        $this->requireScope($tokenIntrospection->getScope(), 'http://php-oauth.net/scope/manage');
        $this->requireEntitlement($tokenIntrospection->getEntitlement(), 'http://php-oauth.net/entitlement/manage');
        // do not require entitlement to list clients...
        $data = $this->storage->getClients();
        $response = new JsonResponse(200);
        $response->setContent($data);

        return $response;
    }

    public function deleteApplication(Request $request, TokenIntrospection $tokenIntrospection, $id)
    {
        $this->requireScope($tokenIntrospection->getScope(), 'http://php-oauth.net/scope/manage');
        $this->requireEntitlement($tokenIntrospection->getEntitlement(), 'http://php-oauth.net/entitlement/manage');
        if (false === $this->storage->deleteClient($id)) {
            throw new NotFoundException('application not found');
        }
        $response = new JsonResponse(200);
        $response->setContent(array('status' => 'ok'));

        return $response;
    }

    public function getApplication(Request $request, TokenIntrospection $tokenIntrospection, $id)
    {
        $this->requireScope($tokenIntrospection->getScope(), 'http://php-oauth.net/scope/manage');
        $this->requireEntitlement($tokenIntrospection->getEntitlement(), 'http://php-oauth.net/entitlement/manage');
        $data = $this->storage->getClient($id);
        if (false === $data) {
            throw new NotFoundException('application not found');
        }
        $response = new JsonResponse(200);
        $response->setContent($data);

        return $response;
    }

    public function postApplication(Request $request, TokenIntrospection $tokenIntrospection)
    {
        $this->requireScope($tokenIntrospection->getScope(), 'http://php-oauth.net/scope/manage');
        $this->requireEntitlement($tokenIntrospection->getEntitlement(), 'http://php-oauth.net/entitlement/manage');

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

    public function putApplication(Request $request, TokenIntrospection $tokenIntrospection, $id)
    {
        $this->requireScope($tokenIntrospection->getScope(), 'http://php-oauth.net/scope/manage');
        $this->requireEntitlement($tokenIntrospection->getEntitlement(), 'http://php-oauth.net/entitlement/manage');

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

    public function getStats(Request $request, TokenIntrospection $tokenIntrospection)
    {
        $this->requireScope($tokenIntrospection->getScope(), 'http://php-oauth.net/scope/manage');
        $this->requireEntitlement($tokenIntrospection->getEntitlement(), 'http://php-oauth.net/entitlement/manage');
        $data = $this->storage->getStats();

        $response = new JsonResponse(200);
        $response->setContent($data);

        return $response;
    }

    private function requireScope(Scope $scope, $scopeValue)
    {
        if (!$scope->hasScope($scopeValue)) {
            throw new ForbiddenException('insufficient_scope');
        }
    }

    private function requireEntitlement(Entitlement $entitlement, $entitlementValue)
    {
        if (!$entitlement->hasEntitlement($entitlementValue)) {
            throw new ForbiddenException('insufficient_entitlement');
        }
    }
}
