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
use fkooman\OAuth\Common\TokenIntrospection;
use fkooman\Http\Exception\BadRequestException;
use fkooman\Http\Exception\InternalServerErrorException;
use fkooman\Http\Exception\NotFoundException;
use fkooman\Http\Exception\ForbiddenException;
use fkooman\Rest\Plugin\Bearer\BearerAuthentication;
use fkooman\OAuth\Common\Scope;

class ApiService extends Service
{
    public function __construct(PdoStorage $storage)
    {
        parent::__construct();

        $this->options(
            '*',
            function () {
                return new Response();
            },
            array('fkooman\Rest\Plugin\Bearer\BearerAuthentication')
        );

        $this->post(
            '/authorizations/',
            function (TokenIntrospection $rs, Request $request) use ($storage) {
                $this->requireScope($rs->getScope(), 'http://php-oauth.net/scope/authorize');
                $data = Json::decode($request->getContent());
                if (null === $data || !is_array($data) || !array_key_exists('client_id', $data) || !array_key_exists('scope', $data)) {
                    throw new BadRequestException('missing client_id or scope');
                }

                // client needs to exist
                $clientId = $data['client_id'];
                $client = $storage->getClient($clientId);
                if (false === $client) {
                    throw new NotFoundException('client is not registered');
                }

                // scope should be part of 'allowed_scope' of client registration
                $clientAllowedScope = Scope::fromString($client['allowed_scope']);
                $requestedScope = Scope::fromString($data['scope']);
                if (!$requestedScope->isSubSetOf($clientAllowedScope)) {
                    throw new BadRequestException('invalid scope for this client');
                }
                $refreshToken = (array_key_exists('refresh_token', $data) && $data['refresh_token']) ? Utils::randomHex(16) : null;

                // check to see if an authorization for this client/resource_owner already exists
                if (false === $storage->getApprovalByResourceOwnerId($clientId, $rs->getSub())) {
                    if (false === $storage->addApproval($clientId, $rs->getSub(), $data['scope'], $refreshToken)) {
                        throw new InternalServerErrorException('unable to add authorization');
                    }
                } else {
                    throw new BadRequestException(
                        'authorization already exists for this client and resource owner'
                    );
                }

                $response = new JsonResponse(201);

                return $response;
            }
        );

        $this->get(
            '/authorizations/:id',
            function (TokenIntrospection $rs, Request $request, $id) use ($storage) {
                $this->requireScope($rs->getScope(), 'http://php-oauth.net/scope/authorize');
                $data = $storage->getApprovalByResourceOwnerId($id, $rs->getSub());
                if (false === $data) {
                    throw new NotFoundException('authorization not found');
                }
                $response = new JsonResponse(200);
                $response->setContent($data);

                return $response;
            }
        );

        $this->delete(
            '/authorizations/:id',
            function (TokenIntrospection $rs, Request $request, $id) use ($storage) {
               $this->requireScope($rs->getScope(), 'http://php-oauth.net/scope/authorize');
                if (false === $storage->deleteApproval($id, $rs->getSub())) {
                    throw new NotFoundException('authorization not found');
                }
                $response = new JsonResponse(200);

                return $response;
            }
        );

        $this->get(
            '/authorizations/',
            function (TokenIntrospection $rs, Request $request) use ($storage) {
                $this->requireScope($rs->getScope(), 'http://php-oauth.net/scope/authorize');
                $data = $storage->getApprovals($rs->getSub());

                $response = new JsonResponse(200);
                $response->setContent($data);

                return $response;
            }
        );

        $this->get(
            '/applications/',
            function (TokenIntrospection $rs, Request $request) use ($storage) {
                $this->requireScope($rs->getScope(), 'http://php-oauth.net/scope/manage');
                $this->requireEntitlement($rs->getEntitlement(), 'http://php-oauth.net/entitlement/manage');
                // do not require entitlement to list clients...
                $data = $storage->getClients();
                $response = new JsonResponse(200);
                $response->setContent($data);

                return $response;
            }
        );

        $this->delete(
            '/applications/:id',
            function (TokenIntrospection $rs, Request $request, $id) use ($storage) {
                $this->requireScope($rs->getScope(), 'http://php-oauth.net/scope/manage');
                $this->requireEntitlement($rs->getEntitlement(), 'http://php-oauth.net/entitlement/manage');
                if (false === $storage->deleteClient($id)) {
                    throw new NotFoundException('application not found');
                }
                $response = new JsonResponse(200);

                return $response;
            }
        );

        $this->get(
            '/applications/:id',
            function (TokenIntrospection $rs, Request $request, $id) use ($storage) {
                $this->requireScope($rs->getScope(), 'http://php-oauth.net/scope/manage');
                $this->requireEntitlement($rs->getEntitlement(), 'http://php-oauth.net/entitlement/manage');
                $data = $storage->getClient($id);
                if (false === $data) {
                    throw new NotFoundException('application not found');
                }
                $response = new JsonResponse(200);
                $response->setContent($data);

                return $response;
            }
        );

        $this->post(
            '/applications/',
            function (TokenIntrospection $rs, Request $request) use ($storage) {
                $this->requireScope($rs->getScope(), 'http://php-oauth.net/scope/manage');
                $this->requireEntitlement($rs->getEntitlement(), 'http://php-oauth.net/entitlement/manage');
                try {
                    $client = ClientRegistration::fromArray(Json::decode($request->getContent()));
                    $data = $client->getClientAsArray();
                    // check to see if an application with this id already exists
                    if (false === $storage->getClient($data['id'])) {
                        if (false === $storage->addClient($data)) {
                            throw new InternalServerErrorException('unable to add application');
                        }
                    } else {
                        throw new BadRequestException('application already exists');
                    }
                    $response = new JsonResponse(201);

                    return $response;
                } catch (ClientRegistrationException $e) {
                    throw new BadRequestException('invalid client data', $e->getMessage());
                }
            }
        );

        $this->put(
            '/applications/:id',
            function (TokenIntrospection $rs, Request $request, $id) use ($storage) {
                $this->requireScope($rs->getScope(), 'http://php-oauth.net/scope/manage');
                $this->requireEntitlement($rs->getEntitlement(), 'http://php-oauth.net/entitlement/manage');
                try {
                    $client = ClientRegistration::fromArray(Json::decode($request->getContent()));
                    $data = $client->getClientAsArray();
                    if ($data['id'] !== $id) {
                        throw new BadRequestException('resource does not match client id value');
                    }
                    if (false === $storage->updateClient($id, $data)) {
                        throw new InternalServerErrorException('unable to update application');
                    }
                } catch (ClientRegistrationException $e) {
                    throw new BadRequestException('invalid client data', $e->getMessage());
                }
                $response = new JsonResponse(200);

                return $response;
            }
        );

        $this->get(
            '/stats/',
            function (TokenIntrospection $rs, Request $request) use ($storage) {
                $this->requireScope($rs->getScope(), 'http://php-oauth.net/scope/manage');
                $this->requireEntitlement($rs->getEntitlement(), 'http://php-oauth.net/entitlement/manage');
                $data = $storage->getStats();

                $response = new JsonResponse(200);
                $response->setContent($data);

                return $response;
            }
        );
    }

    private function requireScope(Scope $scope, $scopeValue)
    {
        if (!$scope->hasScope(Scope::fromString($scopeValue))) {
            throw new ForbiddenException('insufficient_scope');
        }
    }

    private function requireEntitlement(Entitlement $entitlement, $entitlementValue)
    {
        if (!$entitlement->hasEntitlement(Entitlement::fromString($entitlementValue))) {
            throw new ForbiddenException('insufficient_entitlement');
        }
    }
}
