<?php

namespace ZnDatabase\Backup\Domain;

use ZnDomain\Domain\Interfaces\DomainInterface;

class Domain implements DomainInterface
{

    public function getName()
    {
        return 'backup';
    }

}