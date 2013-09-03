<?php

namespace OAuth;

class MockResourceOwner implements IResourceOwner
{
    private $_data;

    public function __construct(array $resourceOwner)
    {
        $this->_data = array();
        $this->_data['id'] = $resourceOwner['id'];
        $this->_data['entitlement'] = $resourceOwner['entitlement'];
        $this->_data['ext'] = $resourceOwner['ext'];
    }

    public function setResourceOwnerHint($resourceOwnerHint)
    {
        // nop
    }

    public function getId()
    {
        return $this->_data['id'];
    }

    public function getEntitlement()
    {
        return $this->_data['entitlement'];

    }

    public function getExt()
    {
        return $this->_data['ext'];

    }

}
