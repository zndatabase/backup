<?php

namespace ZnDatabase\Backup\Domain\Services;

use ZnDatabase\Backup\Domain\Interfaces\Services\DumpServiceInterface;
use ZnDatabase\Backup\Domain\Interfaces\Repositories\DumpRepositoryInterface;
use ZnCore\Service\Base\BaseCrudService;
use ZnCore\EntityManager\Interfaces\EntityManagerInterface;
use ZnDatabase\Backup\Domain\Entities\DumpEntity;

/**
 * @method DumpRepositoryInterface getRepository()
 */
class DumpService extends BaseCrudService implements DumpServiceInterface
{

    public function __construct(EntityManagerInterface $em)
    {
        $this->setEntityManager($em);
    }

    public function getEntityClass() : string
    {
        return DumpEntity::class;
    }

    
}
