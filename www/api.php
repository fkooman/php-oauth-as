<?php

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . "lib" . DIRECTORY_SEPARATOR . "SplClassLoader.php";
$c =  new SplClassLoader("Tuxed", dirname(__DIR__) . DIRECTORY_SEPARATOR . "lib");
$c->register();

use \Tuxed\Http\HttpResponse as HttpResponse;
use \Tuxed\Config as Config;
use \Tuxed\Http\IncomingHttpRequest as IncomingHttpRequest;
use \Tuxed\Http\HttpRequest as HttpRequest;
use \Tuxed\OAuth\ApiException as ApiException;
use \Tuxed\OAuth\ResourceServer as ResourceServer;
use \Tuxed\OAuth\ResourceServerException as ResourceServerException;
use \Tuxed\OAuth\ClientRegistration as ClientRegistration;
use \Tuxed\OAuth\ClientRegistrationException as ClientRegistrationException;
use \Tuxed\OAuth\AuthorizationServer as AuthorizationServer;
use \Tuxed\Logger as Logger;

$logger = NULL;
$request = NULL;
$response = NULL;

try {
    $request = HttpRequest::fromIncomingHttpRequest(new IncomingHttpRequest());
    $response = new HttpResponse();
    $response->setHeader("Content-Type", "application/json");

    $config = new Config(dirname(__DIR__) . DIRECTORY_SEPARATOR . "config" . DIRECTORY_SEPARATOR . "oauth.ini");
    $logger = new Logger($config->getSectionValue('Log', 'logLevel'), $config->getValue('serviceName'), $config->getSectionValue('Log', 'logFile'), $config->getSectionValue('Log', 'logMail', FALSE));

    $logger->logDebug($request);

    if(!$config->getSectionValue("Api", "enableApi")) {
        throw new ApiException("forbidden","api disabled");
    }

    $oauthStorageBackend = '\\Tuxed\\OAuth\\' . $config->getValue('storageBackend');
    $storage = new $oauthStorageBackend($config);

    $rs = new ResourceServer();
    
    $authorizationHeader = $request->getHeader("HTTP_AUTHORIZATION");
    if(NULL === $authorizationHeader) {
        throw new ResourceServerException("invalid_token", "no token provided");
    }
    $rs->verifyAuthorizationHeader($authorizationHeader);

    if($config->getSectionValue("Api", "disableEntitlementEnforcement")) {
        $rs->setEntitlementEnforcement(FALSE);
    }

    $request->matchRest("GET", "/resource_owner/id", function() use ($response, $rs) {
        $response->setContent(json_encode(array("id" => $rs->getResourceOwnerId())));
    });

    $request->matchRest("POST", "/authorizations/", function() use ($request, $response, $storage, $rs) {
        $rs->requireScope("authorizations");
        $data = json_decode($request->getContent(), TRUE);
        if(NULL === $data || !is_array($data) || !array_key_exists("client_id", $data) || !array_key_exists("scope", $data) || !array_key_exists("refresh_token", $data)) {
            throw new ApiException("invalid_request", "missing required parameters");
        }
        // check to see if an authorization for this client/resource_owner already exists
        if(FALSE === $storage->getApproval($data['client_id'], $rs->getResourceOwnerId())) {
            $refreshToken = $data['refresh_token'] ? AuthorizationServer::randomHex(16) : NULL;
            if(FALSE === $storage->addApproval($data['client_id'], $rs->getResourceOwnerId(), $data['scope'], $refreshToken)) {
                throw new ApiException("invalid_request", "unable to add authorization");
            }
        } else {
            throw new ApiException("invalid_request", "authorization already exists for this client and resource owner");
        }
        $response->setStatusCode(201);
    });

    $request->matchRest("GET", "/authorizations/:id", function($id) use ($request, $response, $storage, $rs) {
        $rs->requireScope("authorizations");
        $data = $storage->getApproval($id, $rs->getResourceOwnerId());
        if(FALSE === $data) {
            throw new ApiException("not_found", "the resource you are trying to retrieve does not exist");
        }
        $response->setContent(json_encode($data));      
    });


    $request->matchRest("GET", "/authorizations/:id", function($id) use ($request, $response, $storage, $rs) {
        $rs->requireScope("authorizations");
        $data = $storage->getApproval($id, $rs->getResourceOwnerId());
        if(FALSE === $data) {
            throw new ApiException("not_found", "the resource you are trying to retrieve does not exist");
        }
        $response->setContent(json_encode($data));      
    });

    $request->matchRest("DELETE", "/authorizations/:id", function($id) use ($request, $response, $storage, $rs) {
        $rs->requireScope("authorizations");
        if(FALSE === $storage->deleteApproval($id, $rs->getResourceOwnerId())) {
            throw new ApiException("not_found", "the resource you are trying to delete does not exist");
        }
    });

    $request->matchRest("GET", "/authorizations/", function() use ($request, $response, $storage, $rs) {
        $rs->requireScope("authorizations");
        $data = $storage->getApprovals($rs->getResourceOwnerId());
        $response->setContent(json_encode($data));
    });

    $request->matchRest("GET", "/applications/", function() use ($request, $response, $storage, $rs) {
        $rs->requireScope("applications");
        // $rs->requireEntitlement("applications");    // do not require entitlement to list clients...
        $data = $storage->getClients();
        $response->setContent(json_encode($data)); 
    });

    $request->matchRest("DELETE", "/applications/:id", function($id) use ($request, $response, $storage, $rs) {
        $rs->requireScope("applications");
        $rs->requireEntitlement("applications");
        if(FALSE === $storage->deleteClient($id)) {
            throw new ApiException("not_found", "the resource you are trying to delete does not exist");
        }
    });

    $request->matchRest("GET", "/applications/:id", function($id) use ($request, $response, $storage, $rs) {
        $rs->requireScope("applications");
        $rs->requireEntitlement("applications");    // FIXME: for now require entitlement as long as password hashing is not
                                                    // implemented...
        $data = $storage->getClient($id);
        if(FALSE === $data) {
            throw new ApiException("not_found", "the resource you are trying to retrieve does not exist");
        }
        $response->setContent(json_encode($data));
    });

    $request->matchRest("POST", "/applications/", function() use ($request, $response, $storage, $rs) {
        $rs->requireScope("applications");
        $rs->requireEntitlement("applications");
        try { 
            $client = ClientRegistration::fromArray(json_decode($request->getContent(), TRUE));
            $data = $client->getClientAsArray();
            // check to see if an application with this id already exists
            if(FALSE === $storage->getClient($data['id'])) {
                if(FALSE === $storage->addClient($data)) {
                    throw new ApiException("invalid_request", "unable to add application");
                }
            } else {
                throw new ApiException("invalid_request", "application already exists");
            }
            $response->setStatusCode(201);
        } catch(ClientRegistrationException $e) {
            throw new ApiException("invalid_request", $e->getMessage());
        }
    });

    $request->matchRest("PUT", "/applications/:id", function($id) use ($request, $response, $storage, $rs) {
        $rs->requireScope("applications");
        $rs->requireEntitlement("applications");
        try {
            $client = ClientRegistration::fromArray(json_decode($request->getContent(), TRUE));
            $data = $client->getClientAsArray();
            if($data['id'] !== $id) {
                throw new ApiException("invalid_request", "resource does not match client id value");
            }
            if(FALSE === $storage->updateClient($id, $data)) {
                throw new ApiException("invalid_request", "unable to update application");
            }
        } catch(ClientRegistrationException $e) {
            throw new ApiException("invalid_request", $e->getMessage());
        }        
    });

    $request->matchRestDefault(function($methodMatch, $patternMatch) use ($request, $response) {
        if(in_array($request->getRequestMethod(), $methodMatch)) {
            if(!$patternMatch) {
                throw new ApiException("not_found", "resource not found");
            }
        } else {
            throw new ApiException("method_not_allowed", "request method not allowed");
        }
    });

} catch (ResourceServerException $e) {
    $response->setStatusCode($e->getResponseCode());
    $response->setHeader("WWW-Authenticate", sprintf('Bearer realm="Resource Server",error="%s",error_description="%s"', $e->getMessage(), $e->getDescription()));
    $response->setContent(json_encode(array("error" => $e->getMessage(), "error_description" => $e->getDescription())));
    if(NULL !== $logger) {
        $logger->logFatal($e->getLogMessage(TRUE) . PHP_EOL . $request . PHP_EOL . $response);
    }
} catch (ApiException $e) {
    $response->setStatusCode($e->getResponseCode());
    $response->setContent(json_encode(array("error" => $e->getMessage(), "error_description" => $e->getDescription())));
    if(NULL !== $logger) {
        $logger->logFatal($e->getLogMessage(TRUE) . PHP_EOL . $request . PHP_EOL . $response);
    }
} catch (Exception $e) {
    // any other error thrown by any of the modules, assume internal server error
    $response->setStatusCode(500);
    $response->setContent(json_encode(array("error" => "internal_server_error", "error_description" => $e->getMessage())));
    if(NULL !== $logger) {
        $logger->logFatal($e->getMessage() . PHP_EOL . $request . PHP_EOL . $response);
    }
}

if(NULL !== $logger) {
    $logger->logDebug($response);
}
if(NULL !== $response) {
    $response->sendResponse();
}
