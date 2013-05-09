<?php

namespace OAuth;

class MockResourceOwner implements IResourceOwner
{
    private $_data;

    public function __construct(array $resourceOwner)
    {
        $this->_data = array();
        $this->_data['id'] = $resourceOwner['id'];
        $this->_data['display_name'] = $resourceOwner['display_name'];
        $this->_data['entitlements'] = $resourceOwner['entitlements'];
        $this->_data['attributes'] = $resourceOwner['attributes'];
    }

    public function getId()
    {
        return $this->_data['id'];
    }

    public function getDisplayName()
    {
        return $this->_data['display_name'];

    }

    public function getEntitlements()
    {
        return $this->_data['entitlements'];

    }

    public function getAttributes()
    {
        return $this->_data['attributes'];

    }

}
