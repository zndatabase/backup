<?php

namespace ZnDatabase\Backup\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use ZnCore\Base\Legacy\Yii\Helpers\ArrayHelper;
use ZnCore\Base\Legacy\Yii\Helpers\FileHelper;
use ZnCore\Base\Libs\App\Helpers\ContainerHelper;
use ZnCore\Base\Libs\DotEnv\DotEnv;
use ZnCore\Base\Libs\Store\Store;
use ZnCore\Domain\Helpers\EntityHelper;
use ZnDatabase\Base\Console\Traits\OverwriteDatabaseTrait;
use ZnDatabase\Base\Domain\Libs\Dependency;
use ZnDatabase\Base\Domain\Repositories\Eloquent\SchemaRepository;
use ZnDatabase\Eloquent\Domain\Factories\ManagerFactory;
use ZnDatabase\Fixture\Domain\Repositories\DbRepository;
use ZnLib\Console\Symfony4\Question\ChoiceQuestion;
use ZnSandbox\Sandbox\Office\Domain\Libs\Zip;

class DumpRestoreCommand extends Command
{
    
    use OverwriteDatabaseTrait;
    
    protected static $defaultName = 'db:database:dump-restore';
    private $capsule;
    private $schemaRepository;
    private $dbRepository;
    private $currentDumpPath;
    private $dumpPath;

    public function __construct(
        ?string $name = null,
        SchemaRepository $schemaRepository,
        DbRepository $dbRepository
    )
    {
        $this->capsule = ManagerFactory::createManagerFromEnv();
        $this->schemaRepository = $schemaRepository;
        $this->dbRepository = $dbRepository;
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

    private function getHistory(): array
    {
        $options = [];
//        $options['only'][] = '*.zip';
        $tree = FileHelper::findFiles($this->dumpPath, $options);
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

    private function getTables(string $version)
    {
        $versionPath = $this->dumpPath . '/' . $version;
        $files = FileHelper::scanDir($versionPath);
        $tables = [];
        foreach ($files as $file) {
            $tables[] = str_replace('.zip', '', $file);
        }
        return $tables;
    }

    private function getZipPath(string $version, string $table): string
    {
        $versionPath = $this->dumpPath . '/' . $version;
        $zipPath = $versionPath . '/' . $table . '.zip';
        return $zipPath;
    }

    private function one(string $version, string $table): int
    {
        $zipPath = $this->getZipPath($version, $table);
        $zip = new Zip($zipPath);
        $result = 0;
        $queryBuilder = $this->dbRepository->getQueryBuilderByTableName($table);
        foreach ($zip->files() as $file) {
            $jsonData = $zip->readFile($file);
            $ext = FileHelper::fileExt($file);
            $store = new Store($ext);
            $data = $store->decode($jsonData);
//            $data = json_decode($jsonData, JSON_OBJECT_AS_ARRAY);
            $queryBuilder->insert($data);
            $result = $result + count($data);
        }
        $this->dbRepository->resetAutoIncrement($table);
        return $result;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(['<fg=white># Dump restore</>']);

        if (!$this->isContinue($input, $output)) {
            return 0;
        }

        $this->dumpPath = DotEnv::get('ROOT_DIRECTORY') . '/' . DotEnv::get('DUMP_DIRECTORY');
        $this->currentDumpPath = $this->dumpPath . '/' . date('Y-m/d/H-i-s');

        $versions = $this->getHistory();

        $output->writeln('');
        $question = new ChoiceQuestion(
            'Select tables for import',
            $versions,
            'a'
        );
        $question->setMultiselect(false);
        $selectedVesrion = $this->getHelper('question')->ask($input, $output, $question);

//        $tree = FileHelper::scanDirTree($this->dumpPath, $options);

        $output->writeln(['', '<fg=white>## Prepare</>', '']);

        $output->write('calulate table dependencies ... ');

        $tables = $this->getTables($selectedVesrion);

        $ignoreTables = [
            'eq_migration',
        ];

        $tableList = $this->schemaRepository->allTables();
        $tt = EntityHelper::getColumn($tableList, 'name');
        foreach ($ignoreTables as $ignoreTable) {
            ArrayHelper::removeByValue($ignoreTable, $tt);
        }

        $dependency = ContainerHelper::getContainer()->get(Dependency::class);
        $tableQueue = $dependency->run($tt);

        $output->writeln('<fg=green>OK</>');

//        dd($tableQueue);

        $output->write('Truncate tables ... ');
        foreach ($tableQueue as $tableName) {
            $this->dbRepository->truncateData($tableName);
        }
        $output->writeln('<fg=green>OK</>');

        $output->writeln(['', '<fg=white>## Restore</>', '']);

        $total = [];
        foreach ($tableQueue as $tableName) {
            $output->write($tableName . ' ... ');
            $count = $this->one($selectedVesrion, /*'public.' . */ $tableName);
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