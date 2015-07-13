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

use fkooman\Http\Request;
use fkooman\Rest\Plugin\Authentication\UserInfoInterface;
use fkooman\Rest\Service;
use fkooman\Http\RedirectResponse;
use fkooman\Http\Response;

class ManageService extends Service
{
    /** @var fkooman\OAuth\Server\PdoStorage */
    private $db;

    /** @var fkooman\OAuth\Server\TemplateManager */
    private $templateManager;

    public function __construct(PdoStorage $db, TemplateManager $templateManager = null)
    {
        parent::__construct();
        $this->db = $db;

        if (null === $templateManager) {
            $templateManager = new TemplateManager();
        }
        $this->templateManager = $templateManager;

        $compatThis = &$this;

        $this->get(
            '*',
            function (Request $request, UserInfoInterface $userInfo) use ($compatThis) {
                $id = $request->getUrl()->getQueryParameter('id');
                if (null !== $id) {
                    // specific client requested
                    return $compatThis->getClient($id);
                }

                return $compatThis->getClients($request, $userInfo);
            }
        );

        $this->put(
            '*',
            function (Request $request, UserInfoInterface $userInfo) use ($compatThis) {
                $id = $request->getUrl()->getQueryParameter('id');
                $redirectTo = $request->getUrl()->getRootUrl();

                return $compatThis->updateClient($id, $request->getPostParameters(), $redirectTo);
            }
        );

        $this->post(
            '*',
            function (Request $request, UserInfoInterface $userInfo) use ($compatThis) {
                $redirectTo = $request->getUrl()->getRootUrl();

                return $compatThis->addClient($request->getPostParameters(), $redirectTo);
            }
        );

        $this->delete(
            '*',
            function (Request $request, UserInfoInterface $userInfo) use ($compatThis) {
                $id = $request->getUrl()->getQueryParameter('id');
                $redirectTo = $request->getUrl()->getRootUrl();

                return $compatThis->deleteClient($id, $redirectTo);
            }
        );
    }

    public function getClient($id)
    {
        $client = $this->db->getClient($id);

        $response = new Response();
        $response->setBody(
            $this->templateManager->render(
                'editClient',
                array(
                    'client' => $client,
                )
            )
        );

        return $response;
    }

    public function getClients()
    {
        $clients = $this->db->getClients();

        $response = new Response();
        $response->setBody(
            $this->templateManager->render(
                'clients',
                array(
                    'clients' => $clients,
                )
            )
        );

        return $response;
    }

    public function addClient(array $clientData, $redirectTo)
    {
        $this->db->addClient(new ClientData($clientData));

        return new RedirectResponse($redirectTo, 302);
    }

    public function updateClient($id, array $clientData, $redirectTo)
    {
        $this->db->updateClient($id, new ClientData($clientData));

        return new RedirectResponse($redirectTo, 302);
    }

    public function deleteClient($id, $redirectTo)
    {
        $this->db->deleteClient($id);

        return new RedirectResponse($redirectTo, 302);
    }
}
