<?php

namespace ZnDatabase\Backup\Domain\Libs;

use Illuminate\Support\Collection;
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

    public function __construct(string $version)
    {
        $this->version = $version;
        $this->dumpPath = DotEnv::get('ROOT_DIRECTORY') . '/' . DotEnv::get('DUMP_DIRECTORY');
        $this->currentDumpPath = $this->dumpPath . '/' . date('Y-m/d/H-i-s');
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
