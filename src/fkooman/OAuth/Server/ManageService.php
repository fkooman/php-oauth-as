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
use fkooman\Rest\Plugin\UserInfo;
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
            function (Request $request, UserInfo $userInfo) use ($compatThis) {
                return $compatThis->getClients($request, $userInfo);
            }
        );

        $this->delete(
            '*',
            function (Request $request, UserInfo $userInfo) use ($compatThis) {
                return $compatThis->deleteClient($request, $userInfo);
            }
        );
    }

    public function getClients(Request $request, UserInfo $userInfo)
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

    public function deleteClient(Request $request, UserInfo $userInfo)
    {
        $id = $request->getUrl()->getQueryParameter('id');
        $this->db->deleteClient(
            $id
        );

        return new RedirectResponse($request->getUrl()->getRootUrl(), 302);
    }
}
