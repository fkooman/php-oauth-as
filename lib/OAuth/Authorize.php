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

use \RestService\Utils\Config as Config;
use \RestService\Http\HttpRequest as HttpRequest;
use \RestService\Http\HttpResponse as HttpResponse;
use \RestService\Utils\Logger as Logger;

class Authorize
{
    private $_config;
    private $_logger;

    private $_resourceOwner;
    private $_as;

    public function __construct(Config $c, Logger $l = NULL)
    {
        $this->_config = $c;
        $this->_logger = $l;

        $authMech = 'OAuth\\' . $this->_config->getValue('authenticationMechanism');
        $this->_resourceOwner = new $authMech($this->_config);

        $oauthStorageBackend = 'OAuth\\' . $this->_config->getValue('storageBackend');
        $storage = new $oauthStorageBackend($this->_config);

        $this->_as = new AuthorizationServer($storage, $this->_config);
    }

    public function handleRequest(HttpRequest $request)
    {
        $response = new HttpResponse();
        try {
            switch ($request->getRequestMethod()) {
                case "GET":
                        $result = $this->_as->authorize($this->_resourceOwner, $request->getQueryParameters());
                        if (AuthorizeResult::ASK_APPROVAL === $result->getAction()) {
                            // FIXME: should this be true also for the POST?
                            $response->setHeader("X-Frame-Options", "deny");
                            $resourceOwnerCnArray = $this->_resourceOwner->getAttribute("cn");
                            $tplData = array(
                                "resourceOwnerId" => $this->_resourceOwner->getResourceOwnerId(),
                                "resourceOwnerCn" => $resourceOwnerCnArray[0],
                                "config" => $this->_config,
                                "client" => $result->getClient(),
                                "scope" => $result->getScope(),
                                "sslEnabled" => "https" === $request->getRequestUri()->getScheme(),
                            );
                            extract($tplData);
                            $_e = function($m) {
                                return htmlentities($m, ENT_QUOTES, "UTF-8");
                            };
                            ob_start();
                            require dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . "templates" . DIRECTORY_SEPARATOR . "askAuthorization.php";
                            $response->setContent(ob_get_clean());
                        } elseif (AuthorizeResult::REDIRECT === $result->getAction()) {
                            $response->setStatusCode(302);
                            $response->setHeader("Location", $result->getRedirectUri()->getUri());
                        } else {
                            // should never happen...
                            throw new \Exception("invalid authorize result");
                        }
                    break;

                case "POST";
                    // CSRF protection, check the referrer, it should be equal to the
                    // request URI
                    $fullRequestUri = $request->getRequestUri()->getUri();
                    $referrerUri = $request->getHeader("HTTP_REFERER");

                    if ($fullRequestUri !== $referrerUri) {
                        throw new ResourceOwnerException("csrf protection triggered, referrer does not match request uri");
                    }
                    $result = $this->_as->approve($this->_resourceOwner, $request->getQueryParameters(), $request->getPostParameters());
                    if (AuthorizeResult::REDIRECT !== $result->getAction()) {
                        // FIXME: this is dead code?
                        throw new ResourceOwnerException("approval not found");
                    }
                    $response->setStatusCode(302);
                    $response->setHeader("Location", $result->getRedirectUri()->getUri());
                    break;

                default:
                    // method not allowed
                    $response->setStatusCode(405);
                    $response->setHeader("Allow", "GET, POST");
                    break;
            }
        } catch (ClientException $e) {
            // tell the client about the error
            $client = $e->getClient();

            if ($client->type === "user_agent_based_application") {
                $separator = "#";
            } else {
                $separator = (FALSE === strpos($client->redirect_uri, "?")) ? "?" : "&";
            }
            $parameters = array("error" => $e->getMessage(), "error_description" => $e->getDescription());
            if (NULL !== $e->getState()) {
                $parameters['state'] = $e->getState();
            }
            $response->setStatusCode(302);
            $response->setHeader("Location", $client->redirect_uri . $separator . http_build_query($parameters));
            if (NULL !== $this->_logger) {
                $this->_logger->logFatal($e->getLogMessage(TRUE) . PHP_EOL . $request . PHP_EOL . $response);
            }
        } catch (ResourceOwnerException $e) {
            // tell resource owner about the error (through browser)
            $response->setStatusCode(400);
            $_e = function($m) {
                return htmlentities($m, ENT_QUOTES, "UTF-8");
            };
            ob_start();
            require dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . "templates" . DIRECTORY_SEPARATOR . "errorPage.php";
            $response->setContent(ob_get_clean());
            if (NULL !== $this->_logger) {
                $this->_logger->logFatal($e->getMessage() . PHP_EOL . $request . PHP_EOL . $response);
            }
        }

        return $response;
    }

}
