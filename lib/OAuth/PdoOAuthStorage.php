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
use \RestService\Utils\Json as Json;

use \PDO as PDO;

/**
 * Class to implement storage for the OAuth Authorization Server using PDO.
 */
class PdoOAuthStorage implements IOAuthStorage
{
    private $_c;
    private $_pdo;

    public function __construct(Config $c)
    {
        $this->_c = $c;

        $driverOptions = array();
        if ($this->_c->getSectionValue('PdoOAuthStorage', 'persistentConnection')) {
            $driverOptions[PDO::ATTR_PERSISTENT] = TRUE;
        }

        $this->_pdo = new PDO($this->_c->getSectionValue('PdoOAuthStorage', 'dsn'), $this->_c->getSectionValue('PdoOAuthStorage', 'username', FALSE), $this->_c->getSectionValue('PdoOAuthStorage', 'password', FALSE), $driverOptions);
        $this->_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if (0 === strpos($this->_c->getSectionValue('PdoOAuthStorage', 'dsn'), "sqlite:")) {
            // only for SQlite
            $this->_pdo->exec("PRAGMA foreign_keys = ON");
        }
    }

    public function getClients()
    {
        $stmt = $this->_pdo->prepare("SELECT id, name, description, redirect_uri, type, icon, allowed_scope FROM Client");
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getClient($clientId)
    {
        $stmt = $this->_pdo->prepare("SELECT * FROM Client WHERE id = :client_id");
        $stmt->bindValue(":client_id", $clientId, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateClient($clientId, $data)
    {
        $stmt = $this->_pdo->prepare("UPDATE Client SET name = :name, description = :description, secret = :secret, redirect_uri = :redirect_uri, type = :type, icon = :icon, allowed_scope = :allowed_scope, contact_email = :contact_email WHERE id = :client_id");
        $stmt->bindValue(":name", $data['name'], PDO::PARAM_STR);
        $stmt->bindValue(":description", $data['description'], PDO::PARAM_STR);
        $stmt->bindValue(":secret", $data['secret'], PDO::PARAM_STR);
        $stmt->bindValue(":redirect_uri", $data['redirect_uri'], PDO::PARAM_STR);
        $stmt->bindValue(":type", $data['type'], PDO::PARAM_STR);
        $stmt->bindValue(":icon", $data['icon'], PDO::PARAM_STR);
        $stmt->bindValue(":allowed_scope", $data['allowed_scope'], PDO::PARAM_STR);
        $stmt->bindValue(":contact_email", $data['contact_email'], PDO::PARAM_STR);
        $stmt->bindValue(":client_id", $clientId, PDO::PARAM_STR);
        $stmt->execute();

        return 1 === $stmt->rowCount();
    }

    public function addClient($data)
    {
        $stmt = $this->_pdo->prepare("INSERT INTO Client (id, name, description, secret, redirect_uri, type, icon, allowed_scope, contact_email) VALUES(:client_id, :name, :description, :secret, :redirect_uri, :type, :icon, :allowed_scope, :contact_email)");
        $stmt->bindValue(":client_id", $data['id'], PDO::PARAM_STR);
        $stmt->bindValue(":name", $data['name'], PDO::PARAM_STR);
        $stmt->bindValue(":description", $data['description'], PDO::PARAM_STR);
        $stmt->bindValue(":secret", $data['secret'], PDO::PARAM_STR);
        $stmt->bindValue(":redirect_uri", $data['redirect_uri'], PDO::PARAM_STR);
        $stmt->bindValue(":type", $data['type'], PDO::PARAM_STR);
        $stmt->bindValue(":icon", $data['icon'], PDO::PARAM_STR);
        $stmt->bindValue(":allowed_scope", $data['allowed_scope'], PDO::PARAM_STR);
        $stmt->bindValue(":contact_email", $data['contact_email'], PDO::PARAM_STR);
        $stmt->execute();

        return 1 === $stmt->rowCount();
    }

    public function deleteClient($clientId)
    {
        // cascading in foreign keys takes care of deleting all tokens
        $stmt = $this->_pdo->prepare("DELETE FROM Client WHERE id = :client_id");
        $stmt->bindValue(":client_id", $clientId, PDO::PARAM_STR);
        $stmt->execute();

        return 1 === $stmt->rowCount();
    }

    public function addApproval($clientId, $resourceOwnerId, $scope, $refreshToken)
    {
        $stmt = $this->_pdo->prepare("INSERT INTO Approval (client_id, resource_owner_id, scope, refresh_token) VALUES(:client_id, :resource_owner_id, :scope, :refresh_token)");
        $stmt->bindValue(":client_id", $clientId, PDO::PARAM_STR);
        $stmt->bindValue(":resource_owner_id", $resourceOwnerId, PDO::PARAM_STR);
        $stmt->bindValue(":scope", $scope, PDO::PARAM_STR);
        $stmt->bindValue(":refresh_token", $refreshToken, PDO::PARAM_STR);
        $stmt->execute();

        return 1 === $stmt->rowCount();
    }

    public function updateApproval($clientId, $resourceOwnerId, $scope)
    {
        // FIXME: should we regenerate the refresh_token?
        $stmt = $this->_pdo->prepare("UPDATE Approval SET scope = :scope WHERE client_id = :client_id AND resource_owner_id = :resource_owner_id");
        $stmt->bindValue(":client_id", $clientId, PDO::PARAM_STR);
        $stmt->bindValue(":resource_owner_id", $resourceOwnerId, PDO::PARAM_STR);
        $stmt->bindValue(":scope", $scope, PDO::PARAM_STR);
        $stmt->execute();

        return 1 === $stmt->rowCount();
    }

    public function getApprovalByResourceOwnerId($clientId, $resourceOwnerId)
    {
        $stmt = $this->_pdo->prepare("SELECT * FROM Approval WHERE client_id = :client_id AND resource_owner_id = :resource_owner_id");
        $stmt->bindValue(":client_id", $clientId, PDO::PARAM_STR);
        $stmt->bindValue(":resource_owner_id", $resourceOwnerId, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getApprovalByRefreshToken($clientId, $refreshToken)
    {
        $stmt = $this->_pdo->prepare("SELECT * FROM Approval WHERE client_id = :client_id AND refresh_token = :refresh_token");
        $stmt->bindValue(":client_id", $clientId, PDO::PARAM_STR);
        $stmt->bindValue(":refresh_token", $refreshToken, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function storeAccessToken($accessToken, $issueTime, $clientId, $resourceOwnerId, $scope, $expiry)
    {
        $stmt = $this->_pdo->prepare("INSERT INTO AccessToken (client_id, resource_owner_id, issue_time, expires_in, scope, access_token) VALUES(:client_id, :resource_owner_id, :issue_time, :expires_in, :scope, :access_token)");
        $stmt->bindValue(":client_id", $clientId, PDO::PARAM_STR);
        $stmt->bindValue(":resource_owner_id", $resourceOwnerId, PDO::PARAM_STR);
        $stmt->bindValue(":issue_time", time(), PDO::PARAM_INT);
        $stmt->bindValue(":expires_in", $expiry, PDO::PARAM_INT);
        $stmt->bindValue(":scope", $scope, PDO::PARAM_STR);
        $stmt->bindValue(":access_token", $accessToken, PDO::PARAM_STR);
        $stmt->execute();

        return 1 === $stmt->rowCount();
    }

    public function deleteExpiredAccessTokens()
    {
        // delete access tokens that expired 8 hours or longer ago
        $stmt = $this->_pdo->prepare("DELETE FROM AccessToken WHERE issue_time + expires_in < :time");
        $stmt->bindValue(":time", time() - 28800, PDO::PARAM_INT);
        $stmt->execute();

        return TRUE;
    }

    public function deleteExpiredAuthorizationCodes()
    {
        // delete authorization codes that expired 8 hours or longer ago
        $stmt = $this->_pdo->prepare("DELETE FROM AuthorizationCode WHERE issue_time + 600 < :time");
        $stmt->bindValue(":time", time() - 28800, PDO::PARAM_INT);
        $stmt->execute();

        return TRUE;
    }

    public function storeAuthorizationCode($authorizationCode, $resourceOwnerId, $issueTime, $clientId, $redirectUri, $scope)
    {
        $stmt = $this->_pdo->prepare("INSERT INTO AuthorizationCode (client_id, resource_owner_id, authorization_code, redirect_uri, issue_time, scope) VALUES(:client_id, :resource_owner_id, :authorization_code, :redirect_uri, :issue_time, :scope)");
        $stmt->bindValue(":client_id", $clientId, PDO::PARAM_STR);
        $stmt->bindValue(":resource_owner_id", $resourceOwnerId, PDO::PARAM_STR);
        $stmt->bindValue(":authorization_code", $authorizationCode, PDO::PARAM_STR);
        $stmt->bindValue(":redirect_uri", $redirectUri, PDO::PARAM_STR);
        $stmt->bindValue(":issue_time", $issueTime, PDO::PARAM_INT);
        $stmt->bindValue(":scope", $scope, PDO::PARAM_STR);
        $stmt->execute();

        return 1 === $stmt->rowCount();
    }

    public function getAuthorizationCode($clientId, $authorizationCode, $redirectUri)
    {
        if (NULL !== $redirectUri) {
            $stmt = $this->_pdo->prepare("SELECT * FROM AuthorizationCode WHERE client_id = :client_id AND authorization_code = :authorization_code AND redirect_uri = :redirect_uri");
            $stmt->bindValue(":redirect_uri", $redirectUri, PDO::PARAM_STR);
        } else {
            $stmt = $this->_pdo->prepare("SELECT * FROM AuthorizationCode WHERE client_id = :client_id AND authorization_code = :authorization_code AND redirect_uri IS NULL");
        }
        $stmt->bindValue(":client_id", $clientId, PDO::PARAM_STR);
        $stmt->bindValue(":authorization_code", $authorizationCode, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function deleteAuthorizationCode($clientId, $authorizationCode, $redirectUri)
    {
        if (NULL !== $redirectUri) {
            $stmt = $this->_pdo->prepare("DELETE FROM AuthorizationCode WHERE client_id = :client_id AND authorization_code = :authorization_code AND redirect_uri = :redirect_uri");
            $stmt->bindValue(":redirect_uri", $redirectUri, PDO::PARAM_STR);
        } else {
            $stmt = $this->_pdo->prepare("DELETE FROM AuthorizationCode WHERE client_id = :client_id AND authorization_code = :authorization_code AND redirect_uri IS NULL");
        }
        $stmt->bindValue(":client_id", $clientId, PDO::PARAM_STR);
        $stmt->bindValue(":authorization_code", $authorizationCode, PDO::PARAM_STR);
        $stmt->execute();

        return 1 === $stmt->rowCount();
    }

    public function getAccessToken($accessToken)
    {
        $stmt = $this->_pdo->prepare("SELECT * FROM AccessToken WHERE access_token = :access_token");
        $stmt->bindValue(":access_token", $accessToken, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getApprovals($resourceOwnerId)
    {
        $stmt = $this->_pdo->prepare("SELECT a.scope, c.id, c.name, c.description, c.redirect_uri, c.type, c.icon, c.allowed_scope FROM Approval a, Client c WHERE resource_owner_id = :resource_owner_id AND a.client_id = c.id");
        $stmt->bindValue(":resource_owner_id", $resourceOwnerId, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deleteApproval($clientId, $resourceOwnerId)
    {
        // remove access token
        $stmt = $this->_pdo->prepare("DELETE FROM AccessToken WHERE client_id = :client_id AND resource_owner_id = :resource_owner_id");
        $stmt->bindValue(":client_id", $clientId, PDO::PARAM_STR);
        $stmt->bindValue(":resource_owner_id", $resourceOwnerId, PDO::PARAM_STR);
        $stmt->execute();

        // remove approval
        $stmt = $this->_pdo->prepare("DELETE FROM Approval WHERE client_id = :client_id AND resource_owner_id = :resource_owner_id");
        $stmt->bindValue(":client_id", $clientId, PDO::PARAM_STR);
        $stmt->bindValue(":resource_owner_id", $resourceOwnerId, PDO::PARAM_STR);
        $stmt->execute();

        return 1 === $stmt->rowCount();
    }

    public function updateResourceOwner(IResourceOwner $resourceOwner)
    {
        $result = $this->getResourceOwner($resourceOwner->getId());
        if (FALSE === $result) {
            $stmt = $this->_pdo->prepare("INSERT INTO resource_owner (id, entitlement, ext) VALUES(:id, :entitlement, :ext)");
            $stmt->bindValue(":id", $resourceOwner->getId(), PDO::PARAM_STR);
            $stmt->bindValue(":entitlement", Json::enc($resourceOwner->getEntitlement()), PDO::PARAM_STR);
            $stmt->bindValue(":ext", Json::enc($resourceOwner->getExt()), PDO::PARAM_STR);
            $stmt->execute();

           return 1 === $stmt->rowCount();
        } else {
            $stmt = $this->_pdo->prepare("UPDATE resource_owner SET entitlement = :entitlement, ext = :ext WHERE id = :id");
            $stmt->bindValue(":id", $resourceOwner->getId(), PDO::PARAM_STR);
            $stmt->bindValue(":entitlement", Json::enc($resourceOwner->getEntitlement()), PDO::PARAM_STR);
            $stmt->bindValue(":ext", Json::enc($resourceOwner->getExt()), PDO::PARAM_STR);
            $stmt->execute();

            return 1 === $stmt->rowCount();
        }
    }

    public function getResourceOwner($resourceOwnerId)
    {
        $stmt = $this->_pdo->prepare("SELECT id, entitlement, ext FROM resource_owner WHERE id = :id");
        $stmt->bindValue(":id", $resourceOwnerId, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getStats()
    {
        $data = array();

        // determine number of valid access tokens per client/user
        $stmt = $this->_pdo->prepare("SELECT client_id, COUNT(resource_owner_id) AS active_tokens FROM AccessToken GROUP BY client_id");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($result as $r) {
            $data[$r['client_id']]['active_access_tokens'] = $r['active_tokens'];
        }

        // determine number of consents per client/user
        $stmt = $this->_pdo->prepare("SELECT client_id, COUNT(resource_owner_id) AS consent_given FROM Approval GROUP BY client_id");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($result as $r) {
            $data[$r['client_id']]['consent_given'] = $r['consent_given'];
        }

        return $data;
    }

    public function getChangeInfo()
    {
        $stmt = $this->_pdo->prepare("SELECT MAX(patch_number) AS patch_number, description FROM db_changelog WHERE patch_number IS NOT NULL");
        $stmt->execute();
        // ugly hack because query will always return a result, even if there is none...
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return NULL === $result['patch_number'] ? FALSE : $result;
    }

    public function addChangeInfo($patchNumber, $description)
    {
        $stmt = $this->_pdo->prepare("INSERT INTO db_changelog (patch_number, description) VALUES(:patch_number, :description)");
        $stmt->bindValue(":patch_number", $patchNumber, PDO::PARAM_INT);
        $stmt->bindValue(":description", $description, PDO::PARAM_STR);
        $stmt->execute();

        return 1 === $stmt->rowCount();
    }

    public function dbQuery($query)
    {
        $this->_pdo->exec($query);
    }

}
