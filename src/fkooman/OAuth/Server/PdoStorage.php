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
use PDO;

/**
 * Class to implement storage for the OAuth Authorization Server using PDO.
 */
class PdoStorage
{
    /** @var PDO */
    private $db;

    public function __construct(PDO $db, $prefix = '')
    {
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db = $db;
        $this->prefix = $prefix;
    }

    public function getClients()
    {
        $stmt = $this->db->prepare('SELECT id, name, description, redirect_uri, disable_user_consent, type, icon, allowed_scope FROM clients');
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getClient($clientId)
    {
        $stmt = $this->db->prepare('SELECT * FROM clients WHERE id = :client_id');
        $stmt->bindValue(':client_id', $clientId, PDO::PARAM_STR);
        $stmt->execute();

        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        // convert disable_user_consent to boolean
        if (false !== $data && array_key_exists('disable_user_consent', $data)) {
            $data['disable_user_consent'] = (bool) $data['disable_user_consent'];
        }
        return false !== $data ? new ClientData($data) : false;
    }

    public function updateClient($clientId, ClientData $clientData)
    {
        $stmt = $this->db->prepare('UPDATE clients SET name = :name, description = :description, secret = :secret, disable_user_consent = :disable_user_consent, redirect_uri = :redirect_uri, type = :type, icon = :icon, allowed_scope = :allowed_scope, contact_email = :contact_email WHERE id = :client_id');
        $stmt->bindValue(':name', $clientData->getName(), PDO::PARAM_STR);
        $stmt->bindValue(':description', $clientData->getDescription(), PDO::PARAM_STR);
        $stmt->bindValue(':secret', $clientData->getSecret(), PDO::PARAM_STR);
        $stmt->bindValue(':redirect_uri', $clientData->getRedirectUri(), PDO::PARAM_STR);
        $stmt->bindValue(':disable_user_consent', $clientData->getDisableUserConsent(), PDO::PARAM_BOOL);
        $stmt->bindValue(':type', $clientData->getType(), PDO::PARAM_STR);
        $stmt->bindValue(':icon', $clientData->getIcon(), PDO::PARAM_STR);
        $stmt->bindValue(':allowed_scope', $clientData->getAllowedScope(), PDO::PARAM_STR);
        $stmt->bindValue(':contact_email', $clientData->getContactEmail(), PDO::PARAM_STR);
        $stmt->bindValue(':client_id', $clientId, PDO::PARAM_STR);
        $stmt->execute();

        return 1 === $stmt->rowCount();
    }

    public function addClient(ClientData $clientData)
    {
        $stmt = $this->db->prepare('INSERT INTO clients (id, name, description, secret, disable_user_consent, redirect_uri, type, icon, allowed_scope, contact_email) VALUES(:client_id, :name, :description, :secret, :disable_user_consent, :redirect_uri, :type, :icon, :allowed_scope, :contact_email)');
        $stmt->bindValue(':client_id', $clientData->getId(), PDO::PARAM_STR);
        $stmt->bindValue(':name', $clientData->getName(), PDO::PARAM_STR);
        $stmt->bindValue(':description', $clientData->getDescription(), PDO::PARAM_STR);
        $stmt->bindValue(':secret', $clientData->getSecret(), PDO::PARAM_STR);
        $stmt->bindValue(':redirect_uri', $clientData->getRedirectUri(), PDO::PARAM_STR);
        $stmt->bindValue(':disable_user_consent', $clientData->getDisableUserConsent(), PDO::PARAM_BOOL);
        $stmt->bindValue(':type', $clientData->getType(), PDO::PARAM_STR);
        $stmt->bindValue(':icon', $clientData->getIcon(), PDO::PARAM_STR);
        $stmt->bindValue(':allowed_scope', $clientData->getAllowedScope(), PDO::PARAM_STR);
        $stmt->bindValue(':contact_email', $clientData->getContactEmail(), PDO::PARAM_STR);
        $stmt->execute();

        return 1 === $stmt->rowCount();
    }

    public function deleteClient($clientId)
    {
        // cascading in foreign keys takes care of deleting all tokens
        $stmt = $this->db->prepare('DELETE FROM clients WHERE id = :client_id');
        $stmt->bindValue(':client_id', $clientId, PDO::PARAM_STR);
        $stmt->execute();

        return 1 === $stmt->rowCount();
    }

    public function addApproval($clientId, $resourceOwnerId, $scope, $refreshToken)
    {
        $stmt = $this->db->prepare('INSERT INTO approvals (client_id, resource_owner_id, scope, refresh_token) VALUES(:client_id, :resource_owner_id, :scope, :refresh_token)');
        $stmt->bindValue(':client_id', $clientId, PDO::PARAM_STR);
        $stmt->bindValue(':resource_owner_id', $resourceOwnerId, PDO::PARAM_STR);
        $stmt->bindValue(':scope', $scope, PDO::PARAM_STR);
        $stmt->bindValue(':refresh_token', $refreshToken, PDO::PARAM_STR);
        $stmt->execute();

        return 1 === $stmt->rowCount();
    }

    public function updateApproval($clientId, $resourceOwnerId, $scope)
    {
        // FIXME: should we regenerate the refresh_token?
        $stmt = $this->db->prepare('UPDATE approvals SET scope = :scope WHERE client_id = :client_id AND resource_owner_id = :resource_owner_id');
        $stmt->bindValue(':client_id', $clientId, PDO::PARAM_STR);
        $stmt->bindValue(':resource_owner_id', $resourceOwnerId, PDO::PARAM_STR);
        $stmt->bindValue(':scope', $scope, PDO::PARAM_STR);
        $stmt->execute();

        return 1 === $stmt->rowCount();
    }

    public function getApprovalByResourceOwnerId($clientId, $resourceOwnerId)
    {
        $stmt = $this->db->prepare('SELECT * FROM approvals WHERE client_id = :client_id AND resource_owner_id = :resource_owner_id');
        $stmt->bindValue(':client_id', $clientId, PDO::PARAM_STR);
        $stmt->bindValue(':resource_owner_id', $resourceOwnerId, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getApprovalByRefreshToken($clientId, $refreshToken)
    {
        $stmt = $this->db->prepare('SELECT * FROM approvals WHERE client_id = :client_id AND refresh_token = :refresh_token');
        $stmt->bindValue(':client_id', $clientId, PDO::PARAM_STR);
        $stmt->bindValue(':refresh_token', $refreshToken, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function storeAccessToken($accessToken, $issueTime, $clientId, $resourceOwnerId, $scope, $expiry)
    {
        $stmt = $this->db->prepare('INSERT INTO access_tokens (client_id, resource_owner_id, issue_time, expires_in, scope, access_token) VALUES(:client_id, :resource_owner_id, :issue_time, :expires_in, :scope, :access_token)');
        $stmt->bindValue(':client_id', $clientId, PDO::PARAM_STR);
        $stmt->bindValue(':resource_owner_id', $resourceOwnerId, PDO::PARAM_STR);
        $stmt->bindValue(':issue_time', time(), PDO::PARAM_INT);
        $stmt->bindValue(':expires_in', $expiry, PDO::PARAM_INT);
        $stmt->bindValue(':scope', $scope, PDO::PARAM_STR);
        $stmt->bindValue(':access_token', $accessToken, PDO::PARAM_STR);
        $stmt->execute();

        return 1 === $stmt->rowCount();
    }

    public function deleteExpiredAccessTokens()
    {
        // delete access tokens that expired 8 hours or longer ago
        $stmt = $this->db->prepare('DELETE FROM access_tokens WHERE issue_time + expires_in < :time');
        $stmt->bindValue(':time', time() - 28800, PDO::PARAM_INT);
        $stmt->execute();

        return true;
    }

    public function deleteExpiredAuthorizationCodes()
    {
        // delete authorization codes that expired 8 hours or longer ago
        $stmt = $this->db->prepare('DELETE FROM authorization_codes WHERE issue_time + 600 < :time');
        $stmt->bindValue(':time', time() - 28800, PDO::PARAM_INT);
        $stmt->execute();

        return true;
    }

    public function storeAuthorizationCode($authorizationCode, $resourceOwnerId, $issueTime, $clientId, $redirectUri, $scope)
    {
        $stmt = $this->db->prepare('INSERT INTO authorization_codes (client_id, resource_owner_id, authorization_code, redirect_uri, issue_time, scope) VALUES(:client_id, :resource_owner_id, :authorization_code, :redirect_uri, :issue_time, :scope)');
        $stmt->bindValue(':client_id', $clientId, PDO::PARAM_STR);
        $stmt->bindValue(':resource_owner_id', $resourceOwnerId, PDO::PARAM_STR);
        $stmt->bindValue(':authorization_code', $authorizationCode, PDO::PARAM_STR);
        $stmt->bindValue(':redirect_uri', $redirectUri, PDO::PARAM_STR);
        $stmt->bindValue(':issue_time', $issueTime, PDO::PARAM_INT);
        $stmt->bindValue(':scope', $scope, PDO::PARAM_STR);
        $stmt->execute();

        return 1 === $stmt->rowCount();
    }

    public function getAuthorizationCode($clientId, $authorizationCode, $redirectUri)
    {
        if (null !== $redirectUri) {
            $stmt = $this->db->prepare('SELECT * FROM authorization_codes WHERE client_id = :client_id AND authorization_code = :authorization_code AND redirect_uri = :redirect_uri');
            $stmt->bindValue(':redirect_uri', $redirectUri, PDO::PARAM_STR);
        } else {
            $stmt = $this->db->prepare('SELECT * FROM authorization_codes WHERE client_id = :client_id AND authorization_code = :authorization_code AND redirect_uri IS NULL');
        }
        $stmt->bindValue(':client_id', $clientId, PDO::PARAM_STR);
        $stmt->bindValue(':authorization_code', $authorizationCode, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function deleteAuthorizationCode($clientId, $authorizationCode, $redirectUri)
    {
        if (null !== $redirectUri) {
            $stmt = $this->db->prepare('DELETE FROM authorization_codes WHERE client_id = :client_id AND authorization_code = :authorization_code AND redirect_uri = :redirect_uri');
            $stmt->bindValue(':redirect_uri', $redirectUri, PDO::PARAM_STR);
        } else {
            $stmt = $this->db->prepare('DELETE FROM authorization_codes WHERE client_id = :client_id AND authorization_code = :authorization_code AND redirect_uri IS NULL');
        }
        $stmt->bindValue(':client_id', $clientId, PDO::PARAM_STR);
        $stmt->bindValue(':authorization_code', $authorizationCode, PDO::PARAM_STR);
        $stmt->execute();

        return 1 === $stmt->rowCount();
    }

    public function getAccessToken($accessToken)
    {
        $stmt = $this->db->prepare('SELECT * FROM access_tokens WHERE access_token = :access_token');
        $stmt->bindValue(':access_token', $accessToken, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getApprovals($resourceOwnerId)
    {
        $stmt = $this->db->prepare('SELECT a.scope, c.id, c.name, c.description, c.redirect_uri, c.type, c.icon, c.allowed_scope FROM approvals a, clients c WHERE resource_owner_id = :resource_owner_id AND a.client_id = c.id');
        $stmt->bindValue(':resource_owner_id', $resourceOwnerId, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deleteApproval($clientId, $resourceOwnerId)
    {
        // remove access token
        $stmt = $this->db->prepare('DELETE FROM access_tokens WHERE client_id = :client_id AND resource_owner_id = :resource_owner_id');
        $stmt->bindValue(':client_id', $clientId, PDO::PARAM_STR);
        $stmt->bindValue(':resource_owner_id', $resourceOwnerId, PDO::PARAM_STR);
        $stmt->execute();

        // remove approval
        $stmt = $this->db->prepare('DELETE FROM approvals WHERE client_id = :client_id AND resource_owner_id = :resource_owner_id');
        $stmt->bindValue(':client_id', $clientId, PDO::PARAM_STR);
        $stmt->bindValue(':resource_owner_id', $resourceOwnerId, PDO::PARAM_STR);
        $stmt->execute();

        return 1 === $stmt->rowCount();
    }

    public function getStats()
    {
        $data = array();

        // determine number of valid access tokens per client/user
        $stmt = $this->db->prepare('SELECT client_id, COUNT(resource_owner_id) AS active_tokens FROM access_tokens GROUP BY client_id');
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($result as $r) {
            $data[$r['client_id']]['active_access_tokens'] = $r['active_tokens'];
        }

        // determine number of consents per client/user
        $stmt = $this->db->prepare('SELECT client_id, COUNT(resource_owner_id) AS consent_given FROM approvals GROUP BY client_id');
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($result as $r) {
            $data[$r['client_id']]['consent_given'] = $r['consent_given'];
        }

        return $data;
    }

    public function initDatabase()
    {
        $queries = array(
            'CREATE TABLE IF NOT EXISTS clients (
                id VARCHAR(255)  NOT NULL,
                name VARCHAR(255) NOT NULL,
                description VARCHAR(255) DEFAULT NULL,
                secret VARCHAR(255) DEFAULT NULL,
                redirect_uri VARCHAR(255) NOT NULL,
                disable_user_consent BOOLEAN DEFAULT 0,
                type VARCHAR(255) NOT NULL,
                icon VARCHAR(255) DEFAULT NULL,
                allowed_scope VARCHAR(255) DEFAULT NULL,
                contact_email VARCHAR(255) DEFAULT NULL,
                PRIMARY KEY (id)
            )',

            'CREATE TABLE IF NOT EXISTS access_tokens (
                access_token VARCHAR(255)  NOT NULL,
                client_id VARCHAR(255)  NOT NULL,
                resource_owner_id VARCHAR(255)  NOT NULL,
                issue_time INTEGER DEFAULT NULL,
                expires_in INTEGER DEFAULT NULL,
                scope VARCHAR(255) NOT NULL,
                PRIMARY KEY (access_token),
                FOREIGN KEY (client_id)
                    REFERENCES clients (id)
                    ON UPDATE CASCADE ON DELETE CASCADE
            )',

            'CREATE TABLE IF NOT EXISTS approvals (
                client_id VARCHAR(255)  NOT NULL,
                resource_owner_id VARCHAR(255)  NOT NULL,
                scope VARCHAR(255) DEFAULT NULL,
                refresh_token VARCHAR(255) DEFAULT NULL,
                FOREIGN KEY (client_id)
                    REFERENCES clients (id)
                    ON UPDATE CASCADE ON DELETE CASCADE,
                UNIQUE (client_id , resource_owner_id)
            )',

            'CREATE TABLE IF NOT EXISTS authorization_codes (
                authorization_code VARCHAR(255)  NOT NULL,
                client_id VARCHAR(255)  NOT NULL,
                resource_owner_id VARCHAR(255)  NOT NULL,
                redirect_uri VARCHAR(255) DEFAULT NULL,
                issue_time INTEGER NOT NULL,
                scope VARCHAR(255) DEFAULT NULL,
                PRIMARY KEY (authorization_code),
                FOREIGN KEY (client_id)
                    REFERENCES clients (id)
                    ON UPDATE CASCADE ON DELETE CASCADE
            )',
        );

        foreach ($queries as $query) {
            $this->db->query($query);
        }

        // make sure the tables are empty
        $this->db->query('DELETE FROM clients');
        $this->db->query('DELETE FROM access_tokens');
        $this->db->query('DELETE FROM approvals');
        $this->db->query('DELETE FROM authorization_codes');
    }
}
