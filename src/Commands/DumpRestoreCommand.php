<?php

namespace ZnDatabase\Backup\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use ZnCore\Base\Arr\Helpers\ArrayHelper;
use ZnCore\Container\Helpers\ContainerHelper;
use ZnCore\DotEnv\Domain\Libs\DotEnv;
use ZnCore\Entity\Helpers\CollectionHelper;
use ZnCore\Domain\Query\Entities\Query;
use ZnDatabase\Backup\Domain\Entities\DumpEntity;
use ZnDatabase\Backup\Domain\Interfaces\Services\DumpServiceInterface;
use ZnDatabase\Backup\Domain\Libs\DbStorage;
use ZnDatabase\Backup\Domain\Libs\ZipStorage;
use ZnDatabase\Base\Console\Traits\OverwriteDatabaseTrait;
use ZnDatabase\Base\Domain\Libs\Dependency;
use ZnDatabase\Base\Domain\Repositories\Eloquent\SchemaRepository;
use ZnDatabase\Fixture\Domain\Repositories\DbRepository;
use ZnLib\Console\Symfony4\Question\ChoiceQuestion;

class DumpRestoreCommand extends Command
{

    use OverwriteDatabaseTrait;

    protected static $defaultName = 'db:database:dump-restore';
//    private $capsule;
    private $schemaRepository;
    private $dbRepository;
    private $currentDumpPath;
    private $dumpPath;
    private $dumpService;

    public function __construct(
        ?string $name = null,
        SchemaRepository $schemaRepository,
        DbRepository $dbRepository,
        DumpServiceInterface $dumpService
    )
    {
//        $this->capsule = ManagerFactory::createManagerFromEnv();
        $this->schemaRepository = $schemaRepository;
        $this->dbRepository = $dbRepository;
        $this->dumpService = $dumpService;
        parent::__construct($name);
    }

    protected function configure()
    {
        parent::configure();
        $this
            ->addOption(
                'withConfirm',
                null,
                InputOption::VALUE_REQUIRED,
                'Your selection migrations',
                true
            );
    }

    /**
     * @return \ZnDatabase\Eloquent\Domain\Capsule\Manager
     */
    public function getCapsule(): \ZnDatabase\Eloquent\Domain\Capsule\Manager
    {
        return $this->capsule;
    }

    private function one(string $version, string $table): int
    {
        $result = 0;

        /** @var DbStorage $dbStorage */
        $dbStorage = ContainerHelper::getContainer()->get(DbStorage::class);
        $fileStorage = new ZipStorage($version);

        do {
            $collection = $fileStorage->getNextCollection($table);
            $dbStorage->insertBatch($table, $collection->toArray());
            $result = $result + $collection->count();
        } while (!$collection->isEmpty());

        $dbStorage->close($table);
        $fileStorage->close($table);

        return $result;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(['<fg=white># Dump restore</>']);

        if (!$this->isContinue($input, $output)) {
            return 0;
        }


        /** @var DbStorage $dbStorage */
        $dbStorage = ContainerHelper::getContainer()->get(DbStorage::class);
//        $fileStorage = new ZipStorage($version);


        $this->dumpPath = DotEnv::get('ROOT_DIRECTORY') . '/' . DotEnv::get('DUMP_DIRECTORY');
        $this->currentDumpPath = $this->dumpPath . '/' . date('Y-m/d/H-i-s');

        $collection = $this->dumpService->findAll();
        $versions = CollectionHelper::getColumn($collection, 'name');
//        dd($versions);
//        $versions = $this->getHistory();

        $output->writeln('');
        $question = new ChoiceQuestion(
            'Select version for restore',
            $versions,
            'a'
        );
        $question->setMultiselect(false);
        $selectedVesrion = $this->getHelper('question')->ask($input, $output, $question);

//        $tree = FileHelper::scanDirTree($this->dumpPath, $options);

        $output->writeln(['', '<fg=white>## Prepare</>', '']);

        $output->write('calulate table dependencies ... ');

        /** @var DumpEntity $dumpEntity */
        $query = new Query();
        $query->with(['tables']);
        $dumpEntity = $this->dumpService->findOneById($selectedVesrion, $query);
//        dd($dumpEntity);
        $tables = $dumpEntity->getTables();

//        $tables = $this->getTables($selectedVesrion);

        $ignoreTables = [
            'eq_migration',
        ];

        $tableList = $dbStorage->tableList();
        $tt = CollectionHelper::getColumn($tableList, 'name');
        foreach ($ignoreTables as $ignoreTable) {
            ArrayHelper::removeByValue($ignoreTable, $tt);
        }

        $dependency = ContainerHelper::getContainer()->get(Dependency::class);
        $tableQueue = $dependency->run($tt);

        $output->writeln('<fg=green>OK</>');

//        dd($tableQueue);

        $output->write('Truncate tables ... ');
        foreach ($tableQueue as $tableName) {
            $dbStorage->truncate($tableName);
        }
        $output->writeln('<fg=green>OK</>');

        $output->writeln(['', '<fg=white>## Restore</>', '']);

        $total = [];
        foreach ($tableQueue as $tableName) {
            $output->write($tableName . ' ... ');
            $count = $this->findOne($selectedVesrion, /*'public.' . */ $tableName);
            $output->writeln('(' . $count . ') <fg=green>OK</>');
            $total[$tableName] = $count;
        }

//        dd($tables);

        $output->writeln('');
        $output->writeln('<fg=green>Dump restore success!</>');
        $output->writeln('');
        $output->writeln('<fg=white>Total tables: ' . count($tables) . '</>');
        $output->writeln('<fg=white>Total rows: ' . array_sum($total) . '</>');

        return 0;
    }
}
