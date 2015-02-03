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

use InvalidArgumentException;

class ClientData
{
    // VSCHAR     = %x20-7E
    const REGEXP_VSCHAR = '/^(?:[\x20-\x7E])*$/';
    const REGEXP_SCOPE_TOKEN = '/^(?:\x21|[\x23-\x5B]|[\x5D-\x7E])+$/';
    const MAX_LEN = 255;

    /** @var string */
    private $id;

    /** @var string */
    private $redirectUri;

    /** @var string */
    private $name;

    /** @var string */
    private $allowedScope;

    /** @var string */
    private $secret;

    /** @var string */
    private $type;

    /** @var string */
    private $description;

    /** @var bool */
    private $disableUserConsent;

    /** @var string */
    private $icon;

    /** @var string */
    private $contactEmail;

    public function __construct(array $clientData)
    {
        $supportedFields = array(
            'id',
            'redirect_uri',
            'name',
            'allowed_scope',
            'secret',
            'type',
            'description',
            'disable_user_consent',
            'icon',
            'contact_email'
        );

        foreach ($supportedFields as $supportedField) {
            if (!array_key_exists($supportedField, $clientData)) {
                $clientData[$supportedField] = null;
            }
        }

        $this->setId($clientData['id']);
        $this->setRedirectUri($clientData['redirect_uri']);
        $this->setName($clientData['name']);
        $this->setAllowedScope($clientData['allowed_scope']);
        $this->setSecret($clientData['secret']);
        $this->setType($clientData['type']);
        $this->setDescription($clientData['description']);
        $this->setDisableUserConsent($clientData['disable_user_consent']);
        $this->setIcon($clientData['icon']);
        $this->setContactEmail($clientData['contact_email']);
    }
    
    private function checkString($str, $name)
    {
        if (null === $str || !is_string($str) || 0 >= strlen($str) || 255 < strlen($str)) {
            throw new InvalidArgumentException(
                sprintf(
                    '"%s" must be a non-empty string with maximum length %s',
                    $name,
                    self::MAX_LEN
                )
            );
        }
    }
    
    public function setId($id)
    {
        $this->checkString($id, 'id');

        if (1 !== preg_match(self::REGEXP_VSCHAR, $id)) {
            throw new InvalidArgumentException('id contains invalid characters');
        }
        $this->id = $id;
    }

    public function setRedirectUri($redirectUri)
    {
        $this->checkString($redirectUri, 'redirect_uri');

        if (false === filter_var($redirectUri, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('redirect_uri MUST be a valid URL');
        }
        // not allowed to have a fragment (#) in it
        if (false !== strpos($redirectUri, '#')) {
            throw new InvalidArgumentException('redirect_uri MUST NOT contain a fragment');
        }
        $this->redirectUri = $redirectUri;
    }

    public function verifyRedirectUri($redirectUri, $allowRegExpMatch = false)
    {
        if ($this->redirectUri === $redirectUri) {
            return true;
        }
        if ($allowRegExpMatch) {
            if (1 === @preg_match(sprintf('|^%s$|', $this->redirectUri), $redirectUri)) {
                return true;
            }
        }

        return false;
    }

    public function setName($name)
    {
        $this->checkString($name, 'name');
        $this->name = $name;
    }

    public function setAllowedScope($allowedScope)
    {
        if (empty($allowedScope)) {
            return;
        }
        $this->checkString($allowedScope, 'allowed_scope');

        $scopeTokens = explode(' ', $allowedScope);
        foreach ($scopeTokens as $scopeToken) {
            if (0 >= strlen($scopeToken)) {
                throw new InvalidArgumentException('scope token must be a non-empty string');
            }
            if (1 !== preg_match(self::REGEXP_SCOPE_TOKEN, $scopeToken)) {
                throw new InvalidArgumentException('scope token contains invalid characters');
            }
        }
        $this->allowedScope = $allowedScope;
    }

    public function setSecret($secret)
    {
        if (empty($secret)) {
            return;
        }
        $this->checkString($secret, 'secret');
        if (1 !== preg_match(self::REGEXP_VSCHAR, $secret)) {
            throw new InvalidArgumentException('secret contains invalid characters');
        }
        $this->secret = $secret;
    }

    public function setType($type)
    {
        $this->checkString($type, 'type');
        $validTypes = array('web_application', 'user_agent_based_application', 'native_application');
        if (!in_array($type, $validTypes)) {
            throw new InvalidArgumentException('unsupported type');
        }
        $this->type = $type;
    }

    public function setDescription($description)
    {
        if (empty($description)) {
            return;
        }
        $this->checkString($description, 'description');
        $this->description = $description;
    }

    public function setDisableUserConsent($disableUserConsent)
    {
        // null is casted to false, which is good in this case
        $this->disableUserConsent = (bool) $disableUserConsent;
    }

    public function setIcon($icon)
    {
        if (empty($icon)) {
            return;
        }
        $this->checkString($icon, 'icon');
        if (false === filter_var($icon, FILTER_VALIDATE_URL, FILTER_FLAG_PATH_REQUIRED)) {
            throw new InvalidArgumentException('icon must be valid URL with path');
        }
        $this->icon = $icon;
    }

    public function setContactEmail($contactEmail)
    {
        if (empty($contactEmail)) {
            return;
        }
        $this->checkString($contactEmail, 'contact_email');
        if (false === filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('contact_email should be valid email address');
        }
        $this->contactEmail = $contactEmail;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getRedirectUri()
    {
        return $this->redirectUri;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getAllowedScope()
    {
        return $this->allowedScope;
    }

    public function getSecret()
    {
        return $this->secret;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function getDisableUserConsent()
    {
        return $this->disableUserConsent;
    }

    public function getIcon()
    {
        return $this->icon;
    }

    public function getContactEmail()
    {
        return $this->contactEmail;
    }

    public function toArray()
    {
        return array(
            'id' => $this->getId(),
            'redirect_uri' => $this->getRedirectUri(),
            'name' => $this->getName(),
            'allowed_scope' => $this->getAllowedScope(),
            'secret' => $this->getSecret(),
            'type' => $this->getType(),
            'description' => $this->getDescription(),
            'disable_user_consent' => $this->getDisableUserConsent(),
            'icon' => $this->getIcon(),
            'contact_email' => $this->getContactEmail()
        );
    }
}
