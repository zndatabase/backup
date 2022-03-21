<?php

namespace ZnDatabase\Backup\Domain\Libs;

use ZnCore\Base\Legacy\Yii\Helpers\FileHelper;
use ZnCore\Base\Libs\DotEnv\DotEnv;
use ZnCore\Base\Libs\Store\Store;
use ZnCore\Domain\Interfaces\Libs\EntityManagerInterface;
use ZnDatabase\Backup\Domain\Interfaces\Storages\StorageInterface;
use ZnDatabase\Base\Domain\Repositories\Eloquent\SchemaRepository;
use ZnDatabase\Eloquent\Domain\Factories\ManagerFactory;
use ZnDatabase\Fixture\Domain\Repositories\DbRepository;
use ZnSandbox\Sandbox\Office\Domain\Libs\Zip;

class DbStorage implements StorageInterface
{

    private $capsule;
    private $schemaRepository;
    private $dbRepository;
    private $currentDumpPath;
    private $dumpPath;

    public function __construct(
        SchemaRepository $schemaRepository,
        DbRepository $dbRepository,
        EntityManagerInterface $em
    )
    {
        $this->capsule = ManagerFactory::createManagerFromEnv();
        $this->schemaRepository = $schemaRepository;
        $this->dbRepository = $dbRepository;
//        $this->setEntityManager($em);

        $this->dumpPath = DotEnv::get('ROOT_DIRECTORY') . '/' . DotEnv::get('DUMP_DIRECTORY');
        $this->currentDumpPath = $this->dumpPath . '/' . date('Y-m/d/H-i-s');
    }

    public function insertBatch(string $table, array $data) {
        $queryBuilder = $this->dbRepository->getQueryBuilderByTableName($table);
        $queryBuilder->insert($data);
    }
    
    public function truncate(string $table) {
        $this->dbRepository->truncateData($table);
    }

    public function close(string $table) {
        $this->dbRepository->resetAutoIncrement($table);
    }
}
