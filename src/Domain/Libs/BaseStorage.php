<?php

namespace ZnDatabase\Backup\Domain\Libs;

abstract class BaseStorage //implements StorageInterface
{

    private $counter;

    protected function defaultCounterValue(): int
    {
        return 0;
    }

    protected function incrementCounter(): void
    {
        $this->forgeCounter();
        $this->counter++;
    }

    protected function resetCounter(): void
    {
        $this->counter = 0;
    }

    protected function getCounter(): int
    {
        $this->forgeCounter();
        return $this->counter;
    }
    
    protected function forgeCounter(): void
    {
        if ($this->counter === null) {
            $this->resetCounter();
        }
    }
}
