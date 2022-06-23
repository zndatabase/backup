<?php

namespace ZnDatabase\Backup\Domain\Libs;

use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;

use ZnCore\Base\FileSystem\Helpers\FileHelper;
use ZnCore\Base\DotEnv\Domain\Libs\DotEnv;
use ZnCore\Base\FileSystem\Helpers\FilePathHelper;
use ZnCore\Base\Store\Store;
use ZnCore\Base\Text\Helpers\TextHelper;
use ZnDatabase\Backup\Domain\Interfaces\Storages\StorageInterface;
use ZnSandbox\Sandbox\Office\Domain\Libs\Zip;

class ZipStorage extends BaseStorage implements StorageInterface
{

    private $currentDumpPath;
    private $dumpPath;
    private $version;
    private $format = 'json';

    public function __construct(string $version)
    {
        $this->version = $version;
        $this->dumpPath = DotEnv::get('ROOT_DIRECTORY') . '/' . DotEnv::get('DUMP_DIRECTORY');
        $this->currentDumpPath = $this->dumpPath . '/' . $version;
        FileHelper::createDirectory($this->currentDumpPath);
    }

    public function tableList(): Enumerable
    {

    }
    
    public function getNextCollection(string $table): Collection
    {
        $counter = $this->getCounter();
        $files = $this->tableFiles($table);
        if (!isset($files[$counter])) {
            return new Collection();
        }
        $file = $files[$counter];
        $this->incrementCounter();
        $rows = $this->readFile($table, $file);
        return new Collection($rows);
    }

    public function insertBatch(string $table, array $data): void
    {
        $counter = $this->getCounter();
        $zip = $this->createZipInstance($table);
        $file = TextHelper::fill($counter, 11, '0', 'before') . '.' . $this->format;
        $ext = FilePathHelper::fileExt($file);
        $store = new Store($ext);
        $jsonData = $store->encode($data);
        $zip->writeFile($file, $jsonData);
        $this->incrementCounter();
        $zip->close();
    }

    public function close(string $table): void
    {
        $this->resetCounter();
    }

    public function truncate(string $table): void
    {
        $zipPath = $this->getZipPath($this->version, $table);
        unlink($zipPath);
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
        $ext = FilePathHelper::fileExt($file);
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
