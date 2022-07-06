<?php

namespace ZnDatabase\Backup\Domain;

use ZnCore\Domain\Interfaces\DomainInterface;

class Domain implements DomainInterface
{

    public function getName()
    {
        return 'backup';
    }

}