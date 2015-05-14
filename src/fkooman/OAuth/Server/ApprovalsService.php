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

class ApprovalsService extends Service
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

        $this->setDefaultRoute('/');

        $this->get(
            '/',
            function (Request $request, UserInfo $userInfo) use ($compatThis) {
                return $compatThis->getApprovals($request, $userInfo);
            }
        );

        $this->delete(
            '/:id',
            function (Request $request, UserInfo $userInfo, $id) use ($compatThis) {
                return $compatThis->deleteApproval($request, $userInfo, $id);
            }
        );
    }

    public function getApprovals(Request $request, UserInfo $userInfo)
    {
        $approvals = $this->db->getApprovals($userInfo->getUserId());
        return $this->templateManager->render(
            'approvals',
            array(
                'approvals' => $approvals
            )
        );
    }

    public function deleteApproval(Request $request, UserInfo $userInfo, $id)
    {
        $this->db->deleteApproval(
            $id,
            $userInfo->getUserId()
        );

        return new RedirectResponse($request->getAbsRoot(), 302);
    }
}
