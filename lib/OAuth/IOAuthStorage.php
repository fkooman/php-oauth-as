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

interface IOAuthStorage
{
    public function storeAccessToken         ($accessToken, $issueTime, $clientId, $resourceOwnerId, $scope, $expiry);
    public function getAccessToken           ($accessToken);
    public function storeAuthorizationCode   ($authorizationCode, $resourceOwnerId, $issueTime, $clientId, $redirectUri, $scope);
    public function getAuthorizationCode     ($clientId, $authorizationCode, $redirectUri);
    public function deleteAuthorizationCode  ($clientId, $authorizationCode, $redirectUri);
    public function deleteExpiredAccessTokens();
    public function deleteExpiredAuthorizationCodes();

    public function getApprovalByRefreshToken ($clientId, $refreshToken);
    public function getApprovalByResourceOwnerId ($clientId, $resourceOwnerId);

    public function getClients               ();
    public function getClient                ($clientId);

    public function addClient                ($data);
    public function updateClient             ($clientId, $data);
    public function deleteClient             ($clientId);

    public function getApprovals             ($resourceOwnerId);
    public function addApproval              ($clientId, $resourceOwnerId, $scope, $refreshToken);

    // FIXME: should we also update the refresh_token on token update?
    public function updateApproval           ($clientId, $resourceOwnerId, $scope);
    public function deleteApproval           ($clientId, $resourceOwnerId);

    public function updateResourceOwner      (IResourceOwner $resourceOwner);
    public function getResourceOwner         ($resourceOwnerId);
}
