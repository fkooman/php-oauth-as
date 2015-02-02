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

use fkooman\Ini\IniReader;
use fkooman\Json\Json;
use PDO;

/**
 * Class to implement storage for the OAuth Authorization Server using PDO.
 */
class PdoStorage
{
    /** @var fkooman\Ini\IniReader */
    private $iniReader;

    /** @var PDO */
    private $pdo;

    public function __construct(IniReader $c)
    {
        $this->iniReader = $c;

        $driverOptions = array();
        if ($this->iniReader->v('PdoStorage', 'persistentConnection', false, false)) {
            $driverOptions[PDO::ATTR_PERSISTENT] = true;
        }

        $this->pdo = new PDO($this->iniReader->v('PdoStorage', 'dsn'), $this->iniReader->v('PdoStorage', 'username', false), $this->iniReader->v('PdoStorage', 'password', false), $driverOptions);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if (0 === strpos($this->iniReader->v('PdoStorage', 'dsn'), "sqlite:")) {
            // only for SQlite
            $this->pdo->exec("PRAGMA foreign_keys = ON");
        }
    }

    public function getClients()
    {
        $stmt = $this->pdo->prepare("SELECT id, name, description, redirect_uri, user_consent, type, icon, allowed_scope FROM clients");
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getClient($clientId)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM clients WHERE id = :client_id");
        $stmt->bindValue(":client_id", $clientId, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateClient($clientId, $data)
    {
        $stmt = $this->pdo->prepare("UPDATE clients SET name = :name, description = :description, secret = :secret, user_consent = :user_consent, redirect_uri = :redirect_uri, type = :type, icon = :icon, allowed_scope = :allowed_scope, contact_email = :contact_email WHERE id = :client_id");
        $stmt->bindValue(":name", $data['name'], PDO::PARAM_STR);
        $stmt->bindValue(":description", $data['description'], PDO::PARAM_STR);
        $stmt->bindValue(":secret", $data['secret'], PDO::PARAM_STR);
        $stmt->bindValue(":redirect_uri", $data['redirect_uri'], PDO::PARAM_STR);
        $stmt->bindValue(":user_consent", $data['user_consent'], PDO::PARAM_BOOL);
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
        $stmt = $this->pdo->prepare("INSERT INTO clients (id, name, description, secret, user_consent, redirect_uri, type, icon, allowed_scope, contact_email) VALUES(:client_id, :name, :description, :secret, :user_consent, :redirect_uri, :type, :icon, :allowed_scope, :contact_email)");
        $stmt->bindValue(":client_id", $data['id'], PDO::PARAM_STR);
        $stmt->bindValue(":name", $data['name'], PDO::PARAM_STR);
        $stmt->bindValue(":description", $data['description'], PDO::PARAM_STR);
        $stmt->bindValue(":secret", $data['secret'], PDO::PARAM_STR);
        $stmt->bindValue(":user_consent", $data['user_consent'], PDO::PARAM_BOOL);
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
        $stmt = $this->pdo->prepare("DELETE FROM clients WHERE id = :client_id");
        $stmt->bindValue(":client_id", $clientId, PDO::PARAM_STR);
        $stmt->execute();

        return 1 === $stmt->rowCount();
    }

    public function addApproval($clientId, $resourceOwnerId, $scope, $refreshToken)
    {
        $stmt = $this->pdo->prepare("INSERT INTO approvals (client_id, resource_owner_id, scope, refresh_token) VALUES(:client_id, :resource_owner_id, :scope, :refresh_token)");
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
        $stmt = $this->pdo->prepare("UPDATE approvals SET scope = :scope WHERE client_id = :client_id AND resource_owner_id = :resource_owner_id");
        $stmt->bindValue(":client_id", $clientId, PDO::PARAM_STR);
        $stmt->bindValue(":resource_owner_id", $resourceOwnerId, PDO::PARAM_STR);
        $stmt->bindValue(":scope", $scope, PDO::PARAM_STR);
        $stmt->execute();

        return 1 === $stmt->rowCount();
    }

    public function getApprovalByResourceOwnerId($clientId, $resourceOwnerId)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM approvals WHERE client_id = :client_id AND resource_owner_id = :resource_owner_id");
        $stmt->bindValue(":client_id", $clientId, PDO::PARAM_STR);
        $stmt->bindValue(":resource_owner_id", $resourceOwnerId, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getApprovalByRefreshToken($clientId, $refreshToken)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM approvals WHERE client_id = :client_id AND refresh_token = :refresh_token");
        $stmt->bindValue(":client_id", $clientId, PDO::PARAM_STR);
        $stmt->bindValue(":refresh_token", $refreshToken, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function storeAccessToken($accessToken, $issueTime, $clientId, $resourceOwnerId, $scope, $expiry)
    {
        $stmt = $this->pdo->prepare("INSERT INTO access_tokens (client_id, resource_owner_id, issue_time, expires_in, scope, access_token) VALUES(:client_id, :resource_owner_id, :issue_time, :expires_in, :scope, :access_token)");
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
        $stmt = $this->pdo->prepare("DELETE FROM access_tokens WHERE issue_time + expires_in < :time");
        $stmt->bindValue(":time", time() - 28800, PDO::PARAM_INT);
        $stmt->execute();

        return true;
    }

    public function deleteExpiredAuthorizationCodes()
    {
        // delete authorization codes that expired 8 hours or longer ago
        $stmt = $this->pdo->prepare("DELETE FROM authorization_codes WHERE issue_time + 600 < :time");
        $stmt->bindValue(":time", time() - 28800, PDO::PARAM_INT);
        $stmt->execute();

        return true;
    }

    public function storeAuthorizationCode($authorizationCode, $resourceOwnerId, $issueTime, $clientId, $redirectUri, $scope)
    {
        $stmt = $this->pdo->prepare("INSERT INTO authorization_codes (client_id, resource_owner_id, authorization_code, redirect_uri, issue_time, scope) VALUES(:client_id, :resource_owner_id, :authorization_code, :redirect_uri, :issue_time, :scope)");
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
        if (null !== $redirectUri) {
            $stmt = $this->pdo->prepare("SELECT * FROM authorization_codes WHERE client_id = :client_id AND authorization_code = :authorization_code AND redirect_uri = :redirect_uri");
            $stmt->bindValue(":redirect_uri", $redirectUri, PDO::PARAM_STR);
        } else {
            $stmt = $this->pdo->prepare("SELECT * FROM authorization_codes WHERE client_id = :client_id AND authorization_code = :authorization_code AND redirect_uri IS NULL");
        }
        $stmt->bindValue(":client_id", $clientId, PDO::PARAM_STR);
        $stmt->bindValue(":authorization_code", $authorizationCode, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function deleteAuthorizationCode($clientId, $authorizationCode, $redirectUri)
    {
        if (null !== $redirectUri) {
            $stmt = $this->pdo->prepare("DELETE FROM authorization_codes WHERE client_id = :client_id AND authorization_code = :authorization_code AND redirect_uri = :redirect_uri");
            $stmt->bindValue(":redirect_uri", $redirectUri, PDO::PARAM_STR);
        } else {
            $stmt = $this->pdo->prepare("DELETE FROM authorization_codes WHERE client_id = :client_id AND authorization_code = :authorization_code AND redirect_uri IS NULL");
        }
        $stmt->bindValue(":client_id", $clientId, PDO::PARAM_STR);
        $stmt->bindValue(":authorization_code", $authorizationCode, PDO::PARAM_STR);
        $stmt->execute();

        return 1 === $stmt->rowCount();
    }

    public function getAccessToken($accessToken)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM access_tokens WHERE access_token = :access_token");
        $stmt->bindValue(":access_token", $accessToken, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getApprovals($resourceOwnerId)
    {
        $stmt = $this->pdo->prepare("SELECT a.scope, c.id, c.name, c.description, c.redirect_uri, c.type, c.icon, c.allowed_scope FROM approvals a, clients c WHERE resource_owner_id = :resource_owner_id AND a.client_id = c.id");
        $stmt->bindValue(":resource_owner_id", $resourceOwnerId, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deleteApproval($clientId, $resourceOwnerId)
    {
        // remove access token
        $stmt = $this->pdo->prepare("DELETE FROM access_tokens WHERE client_id = :client_id AND resource_owner_id = :resource_owner_id");
        $stmt->bindValue(":client_id", $clientId, PDO::PARAM_STR);
        $stmt->bindValue(":resource_owner_id", $resourceOwnerId, PDO::PARAM_STR);
        $stmt->execute();

        // remove approval
        $stmt = $this->pdo->prepare("DELETE FROM approvals WHERE client_id = :client_id AND resource_owner_id = :resource_owner_id");
        $stmt->bindValue(":client_id", $clientId, PDO::PARAM_STR);
        $stmt->bindValue(":resource_owner_id", $resourceOwnerId, PDO::PARAM_STR);
        $stmt->execute();

        return 1 === $stmt->rowCount();
    }

    public function updateResourceOwner(IResourceOwner $resourceOwner)
    {
        $result = $this->getResourceOwner($resourceOwner->getId());
        if (false === $result) {
            $j = new Json();
            $stmt = $this->pdo->prepare("INSERT INTO resource_owner (id, entitlement, ext) VALUES(:id, :entitlement, :ext)");
            $stmt->bindValue(":id", $resourceOwner->getId(), PDO::PARAM_STR);
            $stmt->bindValue(":entitlement", $j->encode($resourceOwner->getEntitlement()), PDO::PARAM_STR);
            $stmt->bindValue(":ext", $j->encode($resourceOwner->getExt()), PDO::PARAM_STR);
            $stmt->execute();

            return 1 === $stmt->rowCount();
        } else {
            $j = new Json();
            $stmt = $this->pdo->prepare("UPDATE resource_owner SET entitlement = :entitlement, ext = :ext WHERE id = :id");
            $stmt->bindValue(":id", $resourceOwner->getId(), PDO::PARAM_STR);
            $stmt->bindValue(":entitlement", $j->encode($resourceOwner->getEntitlement()), PDO::PARAM_STR);
            $stmt->bindValue(":ext", $j->encode($resourceOwner->getExt()), PDO::PARAM_STR);
            $stmt->execute();

            return 1 === $stmt->rowCount();
        }
    }

    public function getResourceOwner($resourceOwnerId)
    {
        $stmt = $this->pdo->prepare("SELECT id, entitlement, ext FROM resource_owner WHERE id = :id");
        $stmt->bindValue(":id", $resourceOwnerId, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getStats()
    {
        $data = array();

        // determine number of valid access tokens per client/user
        $stmt = $this->pdo->prepare("SELECT client_id, COUNT(resource_owner_id) AS active_tokens FROM access_tokens GROUP BY client_id");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($result as $r) {
            $data[$r['client_id']]['active_access_tokens'] = $r['active_tokens'];
        }

        // determine number of consents per client/user
        $stmt = $this->pdo->prepare("SELECT client_id, COUNT(resource_owner_id) AS consent_given FROM approvals GROUP BY client_id");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($result as $r) {
            $data[$r['client_id']]['consent_given'] = $r['consent_given'];
        }

        return $data;
    }

    public function getChangeInfo()
    {
        $stmt = $this->pdo->prepare("SELECT MAX(patch_number) AS patch_number, description FROM db_changelog WHERE patch_number IS NOT NULL");
        $stmt->execute();
        // ugly hack because query will always return a result, even if there is none...
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return null === $result['patch_number'] ? false : $result;
    }

    public function addChangeInfo($patchNumber, $description)
    {
        $stmt = $this->pdo->prepare("INSERT INTO db_changelog (patch_number, description) VALUES(:patch_number, :description)");
        $stmt->bindValue(":patch_number", $patchNumber, PDO::PARAM_INT);
        $stmt->bindValue(":description", $description, PDO::PARAM_STR);
        $stmt->execute();

        return 1 === $stmt->rowCount();
    }

    public function initDatabase()
    {
        $queries = array(
            "CREATE TABLE IF NOT EXISTS resource_owner (
                id VARCHAR(255) NOT NULL,
                entitlement TEXT DEFAULT NULL,
                ext TEXT DEFAULT NULL,
                PRIMARY KEY (id)
            )",

            "CREATE TABLE IF NOT EXISTS clients (
                id VARCHAR(64) NOT NULL,
                name TEXT NOT NULL,
                description TEXT DEFAULT NULL,
                secret TEXT DEFAULT NULL,
                redirect_uri TEXT NOT NULL,
                user_consent BOOLEAN DEFAULT 1,
                type TEXT NOT NULL,
                icon TEXT DEFAULT NULL,
                allowed_scope TEXT DEFAULT NULL,
                contact_email TEXT DEFAULT NULL,
                PRIMARY KEY (id)
            )",

            "CREATE TABLE IF NOT EXISTS access_tokens (
                access_token VARCHAR(64) NOT NULL,
                client_id VARCHAR(64) NOT NULL,
                resource_owner_id VARCHAR(64) NOT NULL,
                issue_time INTEGER DEFAULT NULL,
                expires_in INTEGER DEFAULT NULL,
                scope TEXT NOT NULL,
                PRIMARY KEY (access_token),
                FOREIGN KEY (client_id)
                    REFERENCES clients (id)
                    ON UPDATE CASCADE ON DELETE CASCADE,
                FOREIGN KEY (resource_owner_id)
                    REFERENCES resource_owner (id)
                    ON UPDATE CASCADE ON DELETE CASCADE
            )",

            "CREATE TABLE IF NOT EXISTS approvals (
                client_id VARCHAR(64) NOT NULL,
                resource_owner_id VARCHAR(64) NOT NULL,
                scope TEXT DEFAULT NULL,
                refresh_token TEXT DEFAULT NULL,
                FOREIGN KEY (client_id)
                    REFERENCES clients (id)
                    ON UPDATE CASCADE ON DELETE CASCADE,
                UNIQUE (client_id , resource_owner_id),
                FOREIGN KEY (resource_owner_id)
                    REFERENCES resource_owner (id)
                    ON UPDATE CASCADE ON DELETE CASCADE
            )",

            "CREATE TABLE IF NOT EXISTS authorization_codes (
                authorization_code VARCHAR(64) NOT NULL,
                client_id VARCHAR(64) NOT NULL,
                resource_owner_id VARCHAR(64) NOT NULL,
                redirect_uri TEXT DEFAULT NULL,
                issue_time INTEGER NOT NULL,
                scope TEXT DEFAULT NULL,
                PRIMARY KEY (authorization_code),
                FOREIGN KEY (client_id)
                    REFERENCES clients (id)
                    ON UPDATE CASCADE ON DELETE CASCADE,
                FOREIGN KEY (resource_owner_id)
                    REFERENCES resource_owner (id)
                    ON UPDATE CASCADE ON DELETE CASCADE
            )",

            "CREATE TABLE IF NOT EXISTS db_changelog (
                patch_number INTEGER NOT NULL,
                description TEXT NOT NULL,
                PRIMARY KEY (patch_number)
            )",
        );

        foreach ($queries as $query) {
            $this->pdo->query($query);
        }

        // make sure the tables are empty
        $this->pdo->query("DELETE FROM resource_owner");
        $this->pdo->query("DELETE FROM clients");
        $this->pdo->query("DELETE FROM access_tokens");
        $this->pdo->query("DELETE FROM approvals");
        $this->pdo->query("DELETE FROM authorization_codes");
        $this->pdo->query("DELETE FROM db_changelog");
    }
}
