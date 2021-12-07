<?php

declare(strict_types=1);

namespace GeoNative\GarbageCollector;

use DateInterval;

interface PrunableRepositoryInterface
{
    public function getGarbageCollectorCheckInterval(): DateInterval;

    /**
     * @return int - Number of entities removed
     */
    public function pruneStaleEntities(): int;
}
