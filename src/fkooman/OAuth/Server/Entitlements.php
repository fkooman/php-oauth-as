<?php

namespace fkooman\OAuth\Server;

use fkooman\Json\Json;

class Entitlements
{

    /** @var array */
    private $entitlements;

    public function __construct($entitlementsFile)
    {
        $entitlements = Json::decodeFile($entitlementsFile);
        if (!is_array($entitlements)) {
            throw new RuntimeException('entitlements not stored as JSON array');
        }

        $this->entitlements = $entitlements;
    }

    public function getEntitlement($userId)
    {
        if (!array_key_exists($userId, $this->entitlements)) {
            // no entitlements found for this user
            return array();
        }
        if (!is_array($this->entitlements[$userId])) {
            throw new RuntimeException('entitlement for user not stored as JSON array');
        }

        return $this->entitlements[$userId];
    }
}
