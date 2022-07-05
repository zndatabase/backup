<?php

namespace ZnDatabase\Backup\Domain\Interfaces\Storages;

use ZnCore\Domain\Collection\Libs\Collection;
use ZnCore\Domain\Collection\Interfaces\Enumerable;

interface StorageInterface
{

    public function getNextCollection(string $table): Enumerable;

    public function insertBatch(string $table, array $data): void;

    public function close(string $table): void;

    public function truncate(string $table): void;

    public function tableList(): Enumerable;
}
