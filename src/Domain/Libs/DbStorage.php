<?php

namespace ZnDatabase\Backup\Domain\Libs;

use Illuminate\Support\Collection;
use ZnCore\Base\Helpers\StringHelper;
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
    private $page = 1;

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

    public function tableList() {
        $tableList = $this->schemaRepository->allTables();
        return $tableList;
        
        $tables = [];
        $schemas = [];
        foreach ($tableList as $tableEntity) {
            $tableName = $tableEntity->getName();
            if ($tableEntity->getSchemaName()) {
                $tableName = $tableEntity->getSchemaName() . '.' . $tableName;
            }
            $tables[] = $tableName;
            if ($tableEntity->getSchemaName() && $tableEntity->getSchemaName() != 'public') {
                $schemas[] = $tableEntity->getSchemaName();
            }
        }
        return $tables;
    }
    
    public function getNextCollection(string $table): Collection
    {
//        $page = 1;
        $perPage = 500;
        $queryBuilder = $this->dbRepository->getQueryBuilderByTableName($table);

        // todo: если есть ID или уникальные поля, сортировать по ним
        
        $queryBuilder->forPage($this->page, $perPage);
        $data = $queryBuilder->get()->toArray();
        $this->page++;
        return new Collection($data);
    }

    public function insertBatch(string $table, array $data): void
    {
        $queryBuilder = $this->dbRepository->getQueryBuilderByTableName($table);
        $queryBuilder->insert($data);
    }

    public function truncate(string $table): void
    {
        $this->dbRepository->truncateData($table);
    }

    public function close(string $table): void
    {
        $this->dbRepository->resetAutoIncrement($table);
    }
}
