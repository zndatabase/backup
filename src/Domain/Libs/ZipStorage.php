<?php

namespace ZnDatabase\Backup\Domain\Libs;

use Illuminate\Support\Collection;
use ZnCore\Base\Helpers\StringHelper;
use ZnCore\Base\Legacy\Yii\Helpers\FileHelper;
use ZnCore\Base\Libs\DotEnv\DotEnv;
use ZnCore\Base\Libs\Store\Store;
use ZnDatabase\Backup\Domain\Interfaces\Storages\StorageInterface;
use ZnSandbox\Sandbox\Office\Domain\Libs\Zip;

class ZipStorage implements StorageInterface
{

    private $capsule;
    private $schemaRepository;
    private $dbRepository;
    private $currentDumpPath;
    private $dumpPath;
    private $version;
    private $current = 0;
    private $format = 'json';

    public function __construct(string $version)
    {
        $this->version = $version;
        $this->dumpPath = DotEnv::get('ROOT_DIRECTORY') . '/' . DotEnv::get('DUMP_DIRECTORY');
        $this->currentDumpPath = $this->dumpPath . '/' . $version;
        FileHelper::createDirectory($this->currentDumpPath);
    }

    public function getNextCollection(string $table): Collection
    {
        $files = $this->tableFiles($table);
        if (!isset($files[$this->current])) {
            return new Collection();
        }
        $file = $files[$this->current];
        $this->current++;
        $rows = $this->readFile($table, $file);
        return new Collection($rows);
    }

    public function insertBatch(string $table, array $data): void
    {
        $tablePath = $this->currentDumpPath . '/' . $table;
//        dd($tablePath);
        $zip = new Zip($tablePath . '.zip');
        $file = StringHelper::fill($this->current, 11, '0', 'before') . '.' . $this->format;
//dd($file);
        $ext = FileHelper::fileExt($file);
        $store = new Store($ext);
        $jsonData = $store->encode($data);

//                $jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $zip->writeFile($file, $jsonData);
        $this->current++;
        $zip->close();
    }

    public function close(string $table): void
    {

    }

    public function truncate(string $table): void
    {
        $tablePath = $this->currentDumpPath . '/' . $table;
        unlink($tablePath . '.zip');
    }

    private function tableFiles(string $table)
    {
        $zip = $this->createZipInstance($table);
        return $zip->files();
    }

    private function readFile(string $table, string $file)
    {
        $zip = $this->createZipInstance($table);
        $jsonData = $zip->readFile($file);
        $ext = FileHelper::fileExt($file);
        $store = new Store($ext);
        $data = $store->decode($jsonData);
        return $data;
    }

    private function createZipInstance(string $table): Zip
    {
        $zipPath = $this->getZipPath($this->version, $table);
        $zip = new Zip($zipPath);
        return $zip;
    }

    private function getZipPath(string $version, string $table): string
    {
        $versionPath = $this->dumpPath . '/' . $version;
        $zipPath = $versionPath . '/' . $table . '.zip';
        return $zipPath;
    }
}
