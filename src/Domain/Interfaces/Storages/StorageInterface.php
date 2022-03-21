<?php

namespace ZnDatabase\Backup\Domain\Interfaces\Storages;

use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;

interface StorageInterface
{

    public function getNextCollection(string $table): Collection;

    public function insertBatch(string $table, array $data): void;

    public function close(string $table): void;

    public function truncate(string $table): void;

    public function tableList(): Enumerable;
}
