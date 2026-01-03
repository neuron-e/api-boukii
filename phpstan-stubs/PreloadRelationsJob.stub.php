<?php

namespace App\Jobs;

class PreloadRelationsJob
{
    /**
     * @param array<int, int|string> $ids
     * @param array<int, string> $relations
     */
    public static function dispatch(string $modelClass, array $ids, array $relations): self
    {
        return new self();
    }

    public function onQueue(string $queue): self
    {
        return $this;
    }
}
