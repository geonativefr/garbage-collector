<?php

declare(strict_types=1);

use GeoNative\GarbageCollector\Entity\GarbageCollectorLog;
use GeoNative\GarbageCollector\Tests\App\Entity\PruneMe;
use GeoNative\GarbageCollector\Tests\App\FlakyConnection;
use GeoNative\GarbageCollector\Tests\App\SpyConnection;

function make_next_check_due(GarbageCollectorLog $log): void
{
    $log->lastCheckedAt = $log->lastCheckedAt->modify('-2 hours');
    save($log);
    entityManager()->clear();
}

function save_stale_prune_me(): void
{
    save(new PruneMe(new DateTimeImmutable('-1 year')));
}

function entities_connection(): FlakyConnection
{
    /** @var FlakyConnection $connection */
    $connection = entityManager(PruneMe::class)->getConnection();
    expect($connection)->toBeInstanceOf(FlakyConnection::class);

    return $connection;
}

function log_connection(): SpyConnection
{
    /** @var SpyConnection $connection */
    $connection = entityManager()->getConnection();
    expect($connection)->toBeInstanceOf(SpyConnection::class);

    return $connection;
}

function dummy_select_index(FlakyConnection|SpyConnection $connection): ?int
{
    $index = array_search($connection->getDatabasePlatform()->getDummySelectSQL(), $connection->executedSql, true);

    return false === $index ? null : $index;
}

/**
 * @param string[] $executedSql
 */
function first_statement_index(array $executedSql, ?string $startingWith = null, ?string $containing = null): ?int
{
    foreach ($executedSql as $index => $sql) {
        if (null !== $startingWith && !str_starts_with($sql, $startingWith)) {
            continue;
        }
        if (null !== $containing && !str_contains($sql, $containing)) {
            continue;
        }

        return $index;
    }

    return null;
}
