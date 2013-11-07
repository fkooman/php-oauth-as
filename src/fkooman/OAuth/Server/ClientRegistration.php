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

// FIXME: enforce maximum length of fields, match with database!
class ClientRegistration
{
    // VSCHAR     = %x20-7E
    public $regExpVSCHAR = '/^(?:[\x20-\x7E])*$/';

    private $_client;

    public function __construct($id, $secret, $type, $redirect_uri, $name)
    {
        $this->_client = array();
        $this->setId($id);
        $this->setSecret($secret);
        $this->setType($type);
        $this->setRedirectUri($redirect_uri);
        $this->setName($name);
        $this->setAllowedScope(NULL);
        $this->setIcon(NULL);
        $this->setDescription(NULL);
        $this->setContactEmail(NULL);
    }

    public static function fromArray(array $a)
    {
        $requiredFields = array ("id", "secret", "type", "redirect_uri", "name");
        foreach ($requiredFields as $r) {
            if (!array_key_exists($r, $a)) {
                throw new ClientRegistrationException("not a valid client, '" . $r . "' not set");
            }
        }
        $c = new static($a['id'], $a['secret'], $a['type'], $a['redirect_uri'], $a['name']);

        if (array_key_exists("allowed_scope", $a)) {
            $c->setAllowedScope($a['allowed_scope']);
        }
        if (array_key_exists("icon", $a)) {
            $c->setIcon($a['icon']);
        }
        if (array_key_exists("description", $a)) {
            $c->setDescription($a['description']);
        }
        if (array_key_exists("contact_email", $a)) {
            $c->setContactEmail($a['contact_email']);
        }

        return $c;
    }

    public function setId($i)
    {
        if (empty($i)) {
            throw new ClientRegistrationException("id cannot be empty");
        }
        $result = preg_match($this->regExpVSCHAR, $i);
        if (1 !== $result) {
            throw new ClientRegistrationException("id contains invalid character");
        }
        $this->_client['id'] = $i;
    }

    public function getId()
    {
        return $this->_client['id'];
    }

    public function setName($n)
    {
        if (empty($n)) {
            throw new ClientRegistrationException("name cannot be empty");
        }
        $this->_client['name'] = $n;
    }

    public function getName()
    {
        return $this->_client['name'];
    }

    public function setSecret($s)
    {
        $result = preg_match($this->regExpVSCHAR, $s);
        if (1 !== $result) {
            throw new ClientRegistrationException("secret contains invalid character");
        }
        $this->_client['secret'] = empty($s) ? NULL : $s;
    }

    public function getSecret()
    {
        return $this->_client['secret'];
    }

    public function setRedirectUri($r)
    {
        if (FALSE === filter_var($r, FILTER_VALIDATE_URL)) {
            throw new ClientRegistrationException("redirect_uri should be valid URL");
        }
        // not allowed to have a fragment (#) in it
        if (NULL !== parse_url($r, PHP_URL_FRAGMENT)) {
            throw new ClientRegistrationException("redirect_uri cannot contain a fragment");
        }
        $this->_client['redirect_uri'] = $r;
    }

    public function getRedirectUri()
    {
        return $this->_client['redirect_uri'];
    }

    public function setType($t)
    {
        if (!in_array($t, array ("user_agent_based_application", "web_application", "native_application"))) {
            throw new ClientRegistrationException("type not supported");
        }
        if ("web_application" === $t) {
            // secret cannot be empty when type is "web_application"
            if (NULL === $this->_client['secret']) {
                throw new ClientRegistrationException("secret should be set for web application type");
            }
        }
        if (NULL !== $this->_client['secret']) {
            // if a secret is set id cannot contain a ":" as it would break Basic authentication
            if (FALSE !== strpos($this->_client['id'], ":")) {
                throw new ClientRegistrationException("client_id cannot contain a colon when using a secret");
            }
        }
        $this->_client['type'] = $t;
    }

    public function getType()
    {
        return $this->_client['type'];
    }

    public function setAllowedScope($a)
    {
        try {
            $s = new Scope($a);
        } catch (ScopeException $e) {
            throw new ClientRegistrationException("scope is invalid");
        }
        $this->_client['allowed_scope'] = empty($a) ? NULL : $a;
    }

    public function getAllowedScope()
    {
        return $this->_client['allowed_scope'];
    }

    public function setIcon($i)
    {
        // icon should be empty, or URL with path
        if (!empty($i)) {
            if (FALSE === filter_var($i, FILTER_VALIDATE_URL, FILTER_FLAG_PATH_REQUIRED)) {
                throw new ClientRegistrationException("icon should be either empty or valid URL with path");
            }
        }
        $this->_client['icon'] = empty($i) ? NULL : $i;
    }

    public function getIcon()
    {
        return $this->_client['icon'];
    }

    public function setDescription($d)
    {
        $this->_client['description'] = empty($d) ? NULL : $d;
    }

    public function getDescription()
    {
        return $this->_client['description'];
    }

    public function setContactEmail($c)
    {
        if (!empty($c)) {
            if (FALSE === filter_var($c, FILTER_VALIDATE_EMAIL)) {
                throw new ClientRegistrationException("contact email should be either empty or valid email address");
            }
        }
        $this->_client['contact_email'] = empty($c) ? NULL : $c;
    }

    public function getContactEmail()
    {
        return $this->_client['contact_email'];
    }

    public function getClientAsArray()
    {
        return $this->_client;
    }

}
