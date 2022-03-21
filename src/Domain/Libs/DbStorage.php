<?php

namespace ZnDatabase\Backup\Domain\Libs;

use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use ZnDatabase\Backup\Domain\Interfaces\Storages\StorageInterface;
use ZnDatabase\Base\Domain\Repositories\Eloquent\SchemaRepository;
use ZnDatabase\Fixture\Domain\Repositories\DbRepository;

class DbStorage implements StorageInterface
{

    private $schemaRepository;
    private $dbRepository;
    private $page = 1;

    public function __construct(
        SchemaRepository $schemaRepository,
        DbRepository $dbRepository
    )
    {
        $this->schemaRepository = $schemaRepository;
        $this->dbRepository = $dbRepository;
    }

    public function tableList(): Enumerable
    {
        $tableList = $this->schemaRepository->allTables();
        return $tableList;

        /*$tables = [];
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
        return $tables;*/
    }

    public function getNextCollection(string $table): Collection
    {
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
        $this->page = 1;
    }
}
