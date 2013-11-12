<?php

namespace fkooman\OAuth\Server;

class MockResourceOwner implements IResourceOwner
{
    private $data;

    public function __construct(array $resourceOwner)
    {
        $this->data = array();
        $this->data['id'] = $resourceOwner['id'];
        $this->data['entitlement'] = $resourceOwner['entitlement'];
        $this->data['ext'] = $resourceOwner['ext'];
    }

    public function setResourceOwnerHint($resourceOwnerHint)
    {
        // nop
    }

    public function getId()
    {
        return $this->data['id'];
    }

    public function getEntitlement()
    {
        return $this->data['entitlement'];

    }

    public function getExt()
    {
        return $this->data['ext'];

    }
}
