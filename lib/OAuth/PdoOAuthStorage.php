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
use \PDO as PDO;

/**
 * Class to implement storage for the OAuth Authorization Server using PDO.
 *
 * FIXME: look into throwing exceptions on error instead of returning FALSE?
 * FIXME: switch to ASSOC instead of OBJ return types
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
            $driverOptions = array(PDO::ATTR_PERSISTENT => TRUE);
        }

        $this->_pdo = new PDO($this->_c->getSectionValue('PdoOAuthStorage', 'dsn'), $this->_c->getSectionValue('PdoOAuthStorage', 'username', FALSE), $this->_c->getSectionValue('PdoOAuthStorage', 'password', FALSE), $driverOptions);

            $this->_pdo->exec("PRAGMA foreign_keys = ON");
    }

    public function getClients()
    {
        $stmt = $this->_pdo->prepare("SELECT id, name, description, redirect_uri, type, icon, allowed_scope FROM Client");
        $result = $stmt->execute();
        if (FALSE === $result) {
            throw new StorageException("unable to retrieve clients");
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getClient($clientId)
    {
        $stmt = $this->_pdo->prepare("SELECT * FROM Client WHERE id = :client_id");
        $stmt->bindValue(":client_id", $clientId, PDO::PARAM_STR);
        $result = $stmt->execute();
        if (FALSE === $result) {
            throw new StorageException("unable to retrieve client");
        }

        return $stmt->fetch(PDO::FETCH_OBJ);
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
        if (FALSE === $stmt->execute()) {
            throw new StorageException("unable to update client");
        }

        return 1 === $stmt->rowCount();
    }

    public function addClient($data)
    {
        $stmt = $this->_pdo->prepare("INSERT INTO Client (id, name, description, secret, redirect_uri, type, icon, allowed_scope, contact_email) VALUES(:client_id, :name, :description, :secret, :redirect_uri, :type, :icon, :allowed_scope, :contact_email)");
        $stmt->bindValue(":client_id", $data['id'], PDO::PARAM_STR);
        $stmt->bindValue(":name", $data['name'], PDO::PARAM_STR);
        $stmt->bindValue(":description", $data['description'], PDO::PARAM_STR);
        $stmt->bindValue(":secret", $data['secret'], PDO::PARAM_STR | PDO::PARAM_NULL);
        $stmt->bindValue(":redirect_uri", $data['redirect_uri'], PDO::PARAM_STR);
        $stmt->bindValue(":type", $data['type'], PDO::PARAM_STR);
        $stmt->bindValue(":icon", $data['icon'], PDO::PARAM_STR);
        $stmt->bindValue(":allowed_scope", $data['allowed_scope'], PDO::PARAM_STR);
        $stmt->bindValue(":contact_email", $data['contact_email'], PDO::PARAM_STR);
        if (FALSE === $stmt->execute()) {
            throw new StorageException("unable to add client");
        }

        return 1 === $stmt->rowCount();
    }

    public function deleteClient($clientId)
    {
        // cascading in foreign keys takes care of deleting all tokens
        $stmt = $this->_pdo->prepare("DELETE FROM Client WHERE id = :client_id");
        $stmt->bindValue(":client_id", $clientId, PDO::PARAM_STR);
        if (FALSE === $stmt->execute()) {
            throw new StorageException("unable to delete client");
        }

        return 1 === $stmt->rowCount();
    }

    public function addApproval($clientId, $resourceOwnerId, $scope, $refreshToken)
    {
        $stmt = $this->_pdo->prepare("INSERT INTO Approval (client_id, resource_owner_id, scope, refresh_token) VALUES(:client_id, :resource_owner_id, :scope, :refresh_token)");
        $stmt->bindValue(":client_id", $clientId, PDO::PARAM_STR);
        $stmt->bindValue(":resource_owner_id", $resourceOwnerId, PDO::PARAM_STR);
        $stmt->bindValue(":scope", $scope, PDO::PARAM_STR);
        $stmt->bindValue(":refresh_token", $refreshToken, PDO::PARAM_STR);
        if (FALSE === $stmt->execute()) {
            throw new StorageException("unable to store approval");
        }

        return 1 === $stmt->rowCount();
    }

    public function updateApproval($clientId, $resourceOwnerId, $scope)
    {
        // FIXME: should we regenerate the refresh_token?
        $stmt = $this->_pdo->prepare("UPDATE Approval SET scope = :scope WHERE client_id = :client_id AND resource_owner_id = :resource_owner_id");
        $stmt->bindValue(":client_id", $clientId, PDO::PARAM_STR);
        $stmt->bindValue(":resource_owner_id", $resourceOwnerId, PDO::PARAM_STR);
        $stmt->bindValue(":scope", $scope, PDO::PARAM_STR);
        if (FALSE === $stmt->execute()) {
            throw new StorageException("unable to update approval");
        }

        return 1 === $stmt->rowCount();
    }

    public function getApprovalByResourceOwnerId($clientId, $resourceOwnerId)
    {
        $stmt = $this->_pdo->prepare("SELECT * FROM Approval WHERE client_id = :client_id AND resource_owner_id = :resource_owner_id");
        $stmt->bindValue(":client_id", $clientId, PDO::PARAM_STR);
        $stmt->bindValue(":resource_owner_id", $resourceOwnerId, PDO::PARAM_STR);
        $result = $stmt->execute();
        if (FALSE === $result) {
            throw new StorageException("unable to get approval");
        }

        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    public function getApprovalByRefreshToken($clientId, $refreshToken)
    {
        $stmt = $this->_pdo->prepare("SELECT * FROM Approval WHERE client_id = :client_id AND refresh_token = :refresh_token");
        $stmt->bindValue(":client_id", $clientId, PDO::PARAM_STR);
        $stmt->bindValue(":refresh_token", $refreshToken, PDO::PARAM_STR);
        $result = $stmt->execute();
        if (FALSE === $result) {
            throw new StorageException("unable to get approval");
        }

        return $stmt->fetch(PDO::FETCH_OBJ);
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
        if (FALSE === $stmt->execute()) {
            throw new StorageException("unable to store access token");
        }

        return 1 === $stmt->rowCount();
    }

    public function deleteExpiredAccessTokens()
    {
        // delete access tokens that expired 8 hours or longer ago
        $stmt = $this->_pdo->prepare("DELETE FROM AccessToken WHERE issue_time + expires_in < :time");
        $stmt->bindValue(":time", time() - 28800, PDO::PARAM_INT);
        if (FALSE === $stmt->execute()) {
            throw new StorageException("unable to delete access tokens");
        }
    }

    public function deleteExpiredAuthorizationCodes()
    {
        // delete authorization codes that expired 8 hours or longer ago
        $stmt = $this->_pdo->prepare("DELETE FROM AuthorizationCode WHERE issue_time + 600 < :time");
        $stmt->bindValue(":time", time() - 28800, PDO::PARAM_INT);
        if (FALSE === $stmt->execute()) {
            throw new StorageException("unable to delete authorization code");
        }
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
        if (FALSE === $stmt->execute()) {
            throw new StorageException("unable to store authorization code");
        }

        return 1 === $stmt->rowCount();
    }

    public function getAuthorizationCode($clientId, $authorizationCode, $redirectUri)
    {
    	// using the COALESCE operator here ensure compatibility between database engines when comparing [potential non-]null strings to NULL.
		$stmt = $this->_pdo->prepare("SELECT * FROM AuthorizationCode WHERE client_id = :client_id AND authorization_code = :authorization_code AND (COALESCE(redirect_uri,'NULL') = COALESCE(:redirect_uri, 'NULL'))");
        $stmt->bindValue(":client_id", $clientId, PDO::PARAM_STR);
        $stmt->bindValue(":authorization_code", $authorizationCode, PDO::PARAM_STR);
        $stmt->bindValue(":redirect_uri", $redirectUri, PDO::PARAM_STR | PDO::PARAM_NULL);
        $result = $stmt->execute();
        if (FALSE === $result) {
            throw new StorageException("unable to get authorization code");
        }

        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    public function deleteAuthorizationCode($clientId, $authorizationCode, $redirectUri)
    {
    	// using the COALESCE operator here ensure compatibility between database engines when comparing [potential non-]null strings to NULL.
        $stmt = $this->_pdo->prepare("DELETE FROM AuthorizationCode WHERE client_id = :client_id AND authorization_code = :authorization_code AND (COALESCE(redirect_uri,'NULL') = COALESCE(:redirect_uri, 'NULL'))");
        $stmt->bindValue(":client_id", $clientId, PDO::PARAM_STR);
        $stmt->bindValue(":authorization_code", $authorizationCode, PDO::PARAM_STR);
        $stmt->bindValue(":redirect_uri", $redirectUri, PDO::PARAM_STR | PDO::PARAM_NULL);
        $result = $stmt->execute();
        if (FALSE === $result) {
            throw new StorageException("unable to delete authorization code");
        }

        return 1 === $stmt->rowCount();
    }

    public function getAccessToken($accessToken)
    {
        $stmt = $this->_pdo->prepare("SELECT * FROM AccessToken WHERE access_token = :access_token");
        $stmt->bindValue(":access_token", $accessToken, PDO::PARAM_STR);
        $result = $stmt->execute();
        if (FALSE === $result) {
            throw new StorageException("unable to get access token");
        }

        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    public function getApprovals($resourceOwnerId)
    {
        $stmt = $this->_pdo->prepare("SELECT a.scope, c.id, c.name, c.description, c.redirect_uri, c.type, c.icon, c.allowed_scope FROM Approval a, Client c WHERE resource_owner_id = :resource_owner_id AND a.client_id = c.id");
        $stmt->bindValue(":resource_owner_id", $resourceOwnerId, PDO::PARAM_STR);
        $result = $stmt->execute();
        if (FALSE === $result) {
            throw new StorageException("unable to get approvals");
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deleteApproval($clientId, $resourceOwnerId)
    {
        // remove access token
        $stmt = $this->_pdo->prepare("DELETE FROM AccessToken WHERE client_id = :client_id AND resource_owner_id = :resource_owner_id");
        $stmt->bindValue(":client_id", $clientId, PDO::PARAM_STR);
        $stmt->bindValue(":resource_owner_id", $resourceOwnerId, PDO::PARAM_STR);
        if (FALSE === $stmt->execute()) {
            throw new StorageException("unable to delete access token");
        }
        // remove approval
        $stmt = $this->_pdo->prepare("DELETE FROM Approval WHERE client_id = :client_id AND resource_owner_id = :resource_owner_id");
        $stmt->bindValue(":client_id", $clientId, PDO::PARAM_STR);
        $stmt->bindValue(":resource_owner_id", $resourceOwnerId, PDO::PARAM_STR);
        if (FALSE === $stmt->execute()) {
            throw new StorageException("unable to delete approval");
        }

        return 1 === $stmt->rowCount();
    }

    public function updateResourceOwner($resourceOwnerId, $resourceOwnerAttributes)
    {
        $result = $this->getResourceOwner($resourceOwnerId);
        if (FALSE === $result) {
            $stmt = $this->_pdo->prepare("INSERT INTO ResourceOwner (id, time, attributes) VALUES(:resource_owner_id, :time, :attributes)");
            $stmt->bindValue(":resource_owner_id", $resourceOwnerId, PDO::PARAM_STR);
            $stmt->bindValue(":time", time(), PDO::PARAM_INT);
            $stmt->bindValue(":attributes", $resourceOwnerAttributes, PDO::PARAM_STR);
            if (FALSE === $stmt->execute()) {
                throw new StorageException("unable to add resource owner");
            }

           return 1 === $stmt->rowCount();
        } else {
            $stmt = $this->_pdo->prepare("UPDATE ResourceOwner SET time = :time, attributes = :attributes WHERE id = :resource_owner_id");
            $stmt->bindValue(":resource_owner_id", $resourceOwnerId, PDO::PARAM_STR);
            $stmt->bindValue(":time", time(), PDO::PARAM_INT);
            $stmt->bindValue(":attributes", $resourceOwnerAttributes, PDO::PARAM_STR);
            if (FALSE === $stmt->execute()) {
                throw new StorageException("unable to update resource owner");
            }

            return 1 === $stmt->rowCount();
        }
    }

    public function getResourceOwner($resourceOwnerId)
    {
        $stmt = $this->_pdo->prepare("SELECT * FROM ResourceOwner WHERE id = :resource_owner_id");
        $stmt->bindValue(":resource_owner_id", $resourceOwnerId, PDO::PARAM_STR);
        $result = $stmt->execute();
        if (FALSE === $result) {
            throw new StorageException("unable to get resource owner");
        }

        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    public function initDatabase()
    {
        $this->_pdo->exec("
            CREATE TABLE IF NOT EXISTS `ResourceOwner` (
            `id` VARCHAR(64) NOT NULL,
            `time` INT(11) NOT NULL,
            `attributes` TEXT DEFAULT NULL,
            PRIMARY KEY (`id`))
        ");

        $this->_pdo->exec("
            CREATE TABLE IF NOT EXISTS `Client` (
            `id` varchar(64) NOT NULL,
            `name` text NOT NULL,
            `description` text DEFAULT NULL,
            `secret` text DEFAULT NULL,
            `redirect_uri` text NOT NULL,
            `type` text NOT NULL,
            `icon` text DEFAULT NULL,
            `allowed_scope` text DEFAULT NULL,
            `contact_email` text DEFAULT NULL,
            PRIMARY KEY (`id`))
        ");

        $this->_pdo->exec("
            CREATE TABLE IF NOT EXISTS `AccessToken` (
            `access_token` varchar(64) NOT NULL,
            `client_id` varchar(64) NOT NULL,
            `resource_owner_id` varchar(64) NOT NULL,
            `issue_time` int(11) DEFAULT NULL,
            `expires_in` int(11) DEFAULT NULL,
            `scope` text NOT NULL,
            PRIMARY KEY (`access_token`),
            FOREIGN KEY (`client_id`) REFERENCES `Client` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
            FOREIGN KEY (`resource_owner_id`) REFERENCES `ResourceOwner` (`id`) ON UPDATE CASCADE ON DELETE CASCADE)
        ");

        $this->_pdo->exec("
            CREATE TABLE IF NOT EXISTS `Approval` (
            `client_id` varchar(64) NOT NULL,
            `resource_owner_id` varchar(64) NOT NULL,
            `scope` text DEFAULT NULL,
            `refresh_token` text DEFAULT NULL,
            FOREIGN KEY (`client_id`) REFERENCES `Client` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
            UNIQUE(`client_id`, `resource_owner_id`),
            FOREIGN KEY (`resource_owner_id`) REFERENCES `ResourceOwner` (`id`) ON UPDATE CASCADE ON DELETE CASCADE)
        ");

        $this->_pdo->exec("
            CREATE TABLE IF NOT EXISTS `AuthorizationCode` (
            `authorization_code` varchar(64) NOT NULL,
            `client_id` varchar(64) NOT NULL,
            `resource_owner_id` varchar(64) NOT NULL,
            `redirect_uri` text DEFAULT NULL,
            `issue_time` int(11) NOT NULL,
            `scope` text DEFAULT NULL,
            PRIMARY KEY (`authorization_code`),
            FOREIGN KEY (`client_id`) REFERENCES `Client` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
            FOREIGN KEY (`resource_owner_id`) REFERENCES `ResourceOwner` (`id`) ON UPDATE CASCADE ON DELETE CASCADE)
        ");
    }

}
