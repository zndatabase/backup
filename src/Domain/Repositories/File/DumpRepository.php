<?php

namespace ZnDatabase\Backup\Domain\Repositories\File;

use ZnCore\DotEnv\Domain\Libs\DotEnv;
use ZnCore\FileSystem\Helpers\FileHelper;
use ZnCore\FileSystem\Helpers\FindFileHelper;
use ZnCore\Collection\Interfaces\Enumerable;
use ZnCore\Collection\Libs\Collection;
use ZnDomain\Entity\Interfaces\EntityIdInterface;
use ZnDomain\EntityManager\Interfaces\EntityManagerInterface;
use ZnDomain\EntityManager\Traits\EntityManagerAwareTrait;
use ZnDomain\Query\Entities\Query;
use ZnDomain\Repository\Interfaces\CrudRepositoryInterface;
use ZnDatabase\Backup\Domain\Entities\DumpEntity;
use ZnDatabase\Base\Domain\Repositories\Eloquent\SchemaRepository;
use ZnDatabase\Eloquent\Domain\Factories\ManagerFactory;
use ZnDatabase\Fixture\Domain\Repositories\DbRepository;

class DumpRepository implements CrudRepositoryInterface
{

    use EntityManagerAwareTrait;

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
        $this->setEntityManager($em);

        $this->dumpPath = DotEnv::get('DUMP_DIRECTORY');
        $this->currentDumpPath = $this->dumpPath . '/' . date('Y-m/d/H-i-s');
    }

    public function getEntityClass(): string
    {
        return DumpEntity::class;
    }

    public function create(EntityIdInterface $entity)
    {
        // TODO: Implement create() method.
    }

    public function update(EntityIdInterface $entity)
    {
        // TODO: Implement update() method.
    }

    public function deleteById($id)
    {
        // TODO: Implement deleteById() method.
    }

    public function deleteByCondition(array $condition)
    {
        // TODO: Implement deleteByCondition() method.
    }

    private function parse(string $item)
    {
        preg_match('/((\d{4}).+(\d{2}).+(\d{2}).+(\d{2}).+(\d{2}).+(\d{2}))(-?.*)/i', $item, $matches);
//        dd($matches);
        $data['name'] = $matches[0];
        $data['version'] = $matches[1];

        $data['year'] = $matches[2];
        $data['month'] = $matches[3];
        $data['day'] = $matches[4];
        $data['hour'] = $matches[5];
        $data['min'] = $matches[6];
        $data['sec'] = $matches[7];
        $data['comment'] = ltrim($matches[8], '-');

        $data['title'] =
            $data['year'] . '-' . $data['month'] . '-' . $data['day'] . ' ' .
            $data['hour'] . ':' . $data['min'] . ':' . $data['sec'];
        $data['createdAt'] = new \DateTime($data['title']);
        $data['id'] = $data['createdAt']->getTimestamp();
        return $data;
    }

    private function forgeEntityFromName(string $name, ?array $with = []): DumpEntity
    {
        $data = $this->parse($name);
        /** @var DumpEntity $dumpEntity */
        $dumpEntity = $this->getEntityManager()->createEntity(DumpEntity::class);
        $dumpEntity->setId($data['id']);
        $dumpEntity->setName($data['name']);
        $dumpEntity->setVersion($data['version']);
        $dumpEntity->setPath($this->dumpPath . '/' . $name);
        $dumpEntity->setCreatedAt($data['createdAt']);
        if (!empty($data['comment'])) {
            $dumpEntity->setComment($data['comment']);
        }
        if (in_array('tables', $with ?: [])) {
            $dumpEntity->setTables($this->getTables($name));
        }
        return $dumpEntity;
    }

    private function getTree(): array
    {
        $options = [];
//        $options['only'][] = '*.zip';
        $tree = FileHelper::findFiles($this->dumpPath, $options);
        $collection = new Collection();
        foreach ($tree as &$item) {
            $item = str_replace($this->dumpPath, '', $item);
            $item = dirname($item);
            $item = trim($item, '/');
        }
        $tree = array_unique($tree);
        sort($tree);
        $tree = array_values($tree);
        return $tree;
    }

    public function findAll(Query $query = null): Enumerable
    {
        $tree = $this->getTree();
        $collection = new Collection();
        foreach ($tree as $name) {
            $dumpEntity = $this->forgeEntityFromName($name, $query->getWith());
            $collection->add($dumpEntity);
        }
        return $collection;
    }

    public function count(Query $query = null): int
    {
        $tree = $this->getTree();
        return count($tree);
    }

    public function findOneById($id, Query $query = null): EntityIdInterface
    {
        $dumpEntity = $this->forgeEntityFromName($id, $query->getWith());
        return $dumpEntity;
    }

    private function getTables(string $version)
    {
        $versionPath = $this->dumpPath . '/' . $version;
        $files = FindFileHelper::scanDir($versionPath);
        $tables = [];
        foreach ($files as $file) {
            $tables[] = str_replace('.zip', '', $file);
        }
        return $tables;
    }
}

