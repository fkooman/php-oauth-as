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

use fkooman\Config\Config;
use fkooman\Json\Json;
use fkooman\OAuth\Common\Scope;

use fkooman\Http\Request;
use fkooman\Rest\Service;
use fkooman\Http\JsonResponse;

class Api
{
    /** @var fkooman\Config\Config */
    private $config;

    private $_storage;
    private $_rs;

    public function __construct(Config $c)
    {
        $this->config = $c;

        $oauthStorageBackend = 'fkooman\\OAuth\\Server\\' . $this->config->getValue('storageBackend');
        $this->_storage = new $oauthStorageBackend($this->config);

        $this->_rs = new ResourceServer($this->_storage);
    }

    public function handleRequest(Request $request)
    {
        try {
            if (!$this->config->s('Api')->l('enableApi')) {
                throw new ApiException("forbidden","api disabled");
            }

            $this->_rs->verifyAuthorizationHeader($request->getHeader("Authorization"));

            $storage = $this->_storage; // FIXME: can this be avoided??
            $rs = $this->_rs; // FIXME: can this be avoided??

            $service = new Service($request);

            $service->match("POST", "/authorizations/", function () use ($request, $storage, $rs) {
                $rs->requireScope("authorizations");
                $data = Json::decode($request->getContent());
                if (NULL === $data || !is_array($data) || !array_key_exists("client_id", $data) || !array_key_exists("scope", $data)) {
                    throw new ApiException("invalid_request", "missing required parameters");
                }

                // client needs to exist
                $clientId = $data['client_id'];
                $client = $storage->getClient($clientId);
                if (FALSE === $client) {
                    throw new ApiException("invalid_request", "client is not registered");
                }

                // scope should be part of "allowed_scope" of client registration
                $clientAllowedScope = Scope::fromString($client['allowed_scope']);
                $requestedScope = Scope::fromString($data['scope']);
                if (!$requestedScope->isSubSetOf($clientAllowedScope)) {
                    throw new ApiException("invalid_request", "invalid scope for this client");
                }
                $refreshToken = (array_key_exists("refresh_token", $data) && $data['refresh_token']) ? Utils::randomHex(16) : NULL;

                // check to see if an authorization for this client/resource_owner already exists
                if (FALSE === $storage->getApprovalByResourceOwnerId($clientId, $rs->getResourceOwnerId())) {
                    if (FALSE === $storage->addApproval($clientId, $rs->getResourceOwnerId(), $data['scope'], $refreshToken)) {
                        throw new ApiException("invalid_request", "unable to add authorization");
                    }
                } else {
                    throw new ApiException("invalid_request", "authorization already exists for this client and resource owner");
                }

                $response = new JsonResponse(201);
                $response->setContent(array("ok" => true));

                return $response;
            });

            $service->match("GET", "/authorizations/:id", function ($id) use ($request, $storage, $rs) {
                $rs->requireScope("authorizations");
                $data = $storage->getApprovalByResourceOwnerId($id, $rs->getResourceOwnerId());
                if (FALSE === $data) {
                    throw new ApiException("not_found", "the resource you are trying to retrieve does not exist");
                }
                $response = new JsonResponse(200);
                $response->setContent($data);

                return $response;
            });

            $service->match("GET", "/authorizations/:id", function ($id) use ($request, $storage, $rs) {
                $rs->requireScope("authorizations");
                $data = $storage->getApprovalByResourceOwnerId($id, $rs->getResourceOwnerId());
                if (FALSE === $data) {
                    throw new ApiException("not_found", "the resource you are trying to retrieve does not exist");
                }
                $response = new JsonResponse(200);
                $response->setContent($data);

                return $response;
            });

            $service->match("DELETE", "/authorizations/:id", function ($id) use ($request, $storage, $rs) {
                $rs->requireScope("authorizations");
                if (FALSE === $storage->deleteApproval($id, $rs->getResourceOwnerId())) {
                    throw new ApiException("not_found", "the resource you are trying to delete does not exist");
                }
                $response = new JsonResponse(200);
                $response->setContent(array("ok" => true));

                return $response;
            });

            $service->match("GET", "/authorizations/", function () use ($request, $storage, $rs) {
                $rs->requireScope("authorizations");
                $data = $storage->getApprovals($rs->getResourceOwnerId());

                $response = new JsonResponse(200);
                $response->setContent($data);

                return $response;
            });

            $service->match("GET", "/applications/", function () use ($request, $storage, $rs) {
                $rs->requireScope("applications");
                // $rs->requireEntitlement("urn:x-oauth:entitlement:applications");    // do not require entitlement to list clients...
                $data = $storage->getClients();
                $response = new JsonResponse(200);
                $response->setContent($data);

                return $response;
            });

            $service->match("DELETE", "/applications/:id", function ($id) use ($request, $storage, $rs) {
                $rs->requireScope("applications");
                $rs->requireEntitlement("urn:x-oauth:entitlement:applications");
                if (FALSE === $storage->deleteClient($id)) {
                    throw new ApiException("not_found", "the resource you are trying to delete does not exist");
                }
                $response = new JsonResponse(200);
                $response->setContent(array("ok" => true));

                return $response;
            });

            $service->match("GET", "/applications/:id", function ($id) use ($request, $storage, $rs) {
                $rs->requireScope("applications");
                $rs->requireEntitlement("urn:x-oauth:entitlement:applications");
                // FIXME: for now require entitlement as long as password hashing is not
                // implemented...

                $data = $storage->getClient($id);
                if (FALSE === $data) {
                    throw new ApiException("not_found", "the resource you are trying to retrieve does not exist");
                }
                $response = new JsonResponse(200);
                $response->setContent($data);

                return $response;
            });

            $service->match("POST", "/applications/", function () use ($request, $storage, $rs) {
                $rs->requireScope("applications");
                $rs->requireEntitlement("urn:x-oauth:entitlement:applications");
                try {
                    $client = ClientRegistration::fromArray(Json::decode($request->getContent()));
                    $data = $client->getClientAsArray();
                    // check to see if an application with this id already exists
                    if (FALSE === $storage->getClient($data['id'])) {
                        if (FALSE === $storage->addClient($data)) {
                            throw new ApiException("invalid_request", "unable to add application");
                        }
                    } else {
                        throw new ApiException("invalid_request", "application already exists");
                    }
                    $response = new JsonResponse(201);
                    $response->setContent(array("ok" => true));

                    return $response;
                } catch (ClientRegistrationException $e) {
                    throw new ApiException("invalid_request", $e->getMessage());
                }
            });

            $service->match("GET", "/stats/", function () use ($request, $storage, $rs) {
                $rs->requireScope("applications");
                $rs->requireEntitlement("urn:x-oauth:entitlement:applications");
                $data = $storage->getStats();

                $response = new JsonResponse(200);
                $response->setContent($data);

                return $response;
            });

            $service->match("PUT", "/applications/:id", function ($id) use ($request, $storage, $rs) {
                $rs->requireScope("applications");
                $rs->requireEntitlement("urn:x-oauth:entitlement:applications");
                try {
                    $client = ClientRegistration::fromArray(Json::decode($request->getContent()));
                    $data = $client->getClientAsArray();
                    if ($data['id'] !== $id) {
                        throw new ApiException("invalid_request", "resource does not match client id value");
                    }
                    if (FALSE === $storage->updateClient($id, $data)) {
                        throw new ApiException("invalid_request", "unable to update application");
                    }
                } catch (ClientRegistrationException $e) {
                    throw new ApiException("invalid_request", $e->getMessage());
                }
                $response = new JsonResponse(200);
                $response->setContent(array("ok" => true));

                return $response;
            });

            return $service->run();
        } catch (ResourceServerException $e) {
            $response = new JsonResponse($e->getResponseCode());
            if ("no_token" === $e->getMessage()) {
                // no authorization header is a special case, the client did not know
                // authentication was required, so tell it now without giving error message
                $hdr = 'Bearer realm="Resource Server"';
           } else {
                $hdr = sprintf('Bearer realm="Resource Server",error="%s",error_description="%s"', $e->getMessage(), $e->getDescription());
            }
            $response->setHeader("WWW-Authenticate", $hdr);
            $response->setContent(array("error" => $e->getMessage(), "error_description" => $e->getDescription()));

            return $response;
        } catch (ApiException $e) {
            $response = new JsonResponse($e->getResponseCode());
            $response->setContent(array("error" => $e->getMessage(), "error_description" => $e->getDescription()));

            return $response;
        }
    }
}
