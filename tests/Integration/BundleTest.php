<?php

declare(strict_types=1);

namespace GeoNative\GarbageCollector\Tests\Integration;

use DateTimeImmutable;
use Doctrine\Persistence\ObjectRepository;
use GeoNative\GarbageCollector\Entity\GarbageCollectorLog;
use GeoNative\GarbageCollector\Services\GarbageCollector;
use GeoNative\GarbageCollector\Tests\App\Entity\PruneMe;
use GeoNative\GarbageCollector\Tests\App\FlakyConnection;
use GeoNative\GarbageCollector\Tests\App\Repository\PruneMeRepository;
use GeoNative\GarbageCollector\Tests\App\SpyConnection;

use function array_search;
use function repository;
use function str_starts_with;

beforeAll(function () {
    create_schema();
});

it('prunes entities', function () {
    /** @var GarbageCollector $garbageCollector */
    $garbageCollector = container()->get(GarbageCollector::class);

    /** @var PruneMeRepository $pruneMeRepository */
    $pruneMeRepository = repository(PruneMe::class);

    /** @var ObjectRepository $logRepository */
    $logRepository = repository(GarbageCollectorLog::class);

    // Given
    $entities = [
        new PruneMe(new DateTimeImmutable('-1 year')),
        new PruneMe(new DateTimeImmutable('-8 months')),
        new PruneMe(new DateTimeImmutable()),
    ];
    save(...$entities);

    // When
    foreach ($garbageCollector->prune() as $class => $removed) {
        break;
    }

    // Then
    /** @var GarbageCollectorLog[] $logs */
    $logs = $logRepository->findBy([], ['id' => 'DESC']);
    $remainingEntities = $pruneMeRepository->findAll();
    expect($class ?? null)->toBe(PruneMe::class);
    expect($removed ?? null)->toBe(2);
    expect($remainingEntities)->toHaveCount(1);
    expect($remainingEntities[0]->id->compare($entities[2]->id))->toBe(0);
    expect($logs)->toHaveCount(1);
    expect($logs[0]->class)->toBe($class);
    expect($logs[0]->removed)->toBe(2);

    // Next scenario: 5 minutes later, new entities shouldn't be checked again
    $lastCheckedAt = $logs[0]->lastCheckedAt = $logs[0]->lastCheckedAt->modify('-5 minutes');
    $lastPrunedAt = $logs[0]->lastPrunedAt = $logs[0]->lastPrunedAt->modify('-5 minutes');
    save($logs[0]);
    entityManager()->clear();

    // Given
    save(
        new PruneMe(new DateTimeImmutable('-1 year')),
        new PruneMe(new DateTimeImmutable()),
    );

    // When
    foreach ($garbageCollector->prune() as $class => $removed) {
        break;
    }

    // Then
    /** @var GarbageCollectorLog[] $logs */
    $logs = $logRepository->findBy([], ['id' => 'DESC']);
    $remainingEntities = $pruneMeRepository->findAll();
    expect($class ?? null)->toBe(PruneMe::class);
    expect($removed ?? null)->toBe(0);
    expect($remainingEntities)->toHaveCount(3);
    expect($logs)->toHaveCount(1);
    expect($logs[0]->lastCheckedAt->format('YmdHis') <=> $lastCheckedAt->format('YmdHis'))->toBe(0);
    expect($logs[0]->lastPrunedAt->format('YmdHis') <=> $lastPrunedAt->format('YmdHis'))->toBe(0);


    // Next scenario: 2 hours later, new entities should be checked again and pruned
    $lastCheckedAt = $logs[0]->lastCheckedAt = $logs[0]->lastCheckedAt->modify('-2 hours');
    save($logs[0]);
    entityManager()->clear();

    // When
    foreach ($garbageCollector->prune() as $class => $removed) {
        break;
    }

    // Then
    /** @var GarbageCollectorLog[] $logs */
    $logs = $logRepository->findBy([], ['id' => 'DESC']);
    $remainingEntities = $pruneMeRepository->findAll();
    expect($class ?? null)->toBe(PruneMe::class);
    expect($removed ?? null)->toBe(1);
    expect($remainingEntities)->toHaveCount(2);
    expect($logs)->toHaveCount(2);
    expect($logs[0]->lastCheckedAt->format('YmdHis') <=> $lastCheckedAt->format('YmdHis'))->toBe(1);
    expect($logs[0]->lastPrunedAt->format('YmdHis') <=> $lastPrunedAt->format('YmdHis'))->toBe(1);
});

it('pings the connection of the repository own manager before pruning', function () {
    /** @var GarbageCollector $garbageCollector */
    $garbageCollector = container()->get(GarbageCollector::class);

    /** @var ObjectRepository $logRepository */
    $logRepository = repository(GarbageCollectorLog::class);

    // Given: a stale entity, and a log old enough for the next check to be performed
    /** @var GarbageCollectorLog[] $logs */
    $logs = $logRepository->findBy([], ['id' => 'DESC']);
    $logs[0]->lastCheckedAt = $logs[0]->lastCheckedAt->modify('-2 hours');
    save($logs[0]);
    entityManager()->clear();
    save(new PruneMe(new DateTimeImmutable('-1 year')));

    // PruneMe lives on its own manager and connection, distinct from the log's
    $entitiesConnection = entityManager(PruneMe::class)->getConnection();
    $logConnection = entityManager()->getConnection();
    expect($entitiesConnection)->toBeInstanceOf(FlakyConnection::class);
    expect($logConnection)->toBeInstanceOf(SpyConnection::class);
    expect($entitiesConnection)->not->toBe($logConnection);
    $entitiesConnection->executedSql = [];
    $logConnection->executedSql = [];

    // When
    foreach ($garbageCollector->prune() as $class => $removed) {
        break;
    }

    // Then
    expect($removed ?? null)->toBe(1);

    // The entities connection was pinged before the DELETE it will run
    $entitiesPing = array_search(
        $entitiesConnection->getDatabasePlatform()->getDummySelectSQL(),
        $entitiesConnection->executedSql,
        true,
    );
    $delete = null;
    foreach ($entitiesConnection->executedSql as $index => $sql) {
        if (str_starts_with($sql, 'DELETE FROM prune_me')) {
            $delete = $index;
            break;
        }
    }
    expect($entitiesPing)->not->toBeFalse('no dummy select was issued on the entities connection');
    expect($delete)->not->toBeNull('the prune did not delete anything');
    expect($entitiesPing)->toBeLessThan($delete);

    // The log connection was pinged before the first read of its own table
    $logPing = array_search(
        $logConnection->getDatabasePlatform()->getDummySelectSQL(),
        $logConnection->executedSql,
        true,
    );
    $logSelect = null;
    foreach ($logConnection->executedSql as $index => $sql) {
        if (str_starts_with($sql, 'SELECT') && str_contains($sql, 'garbage_collector_log')) {
            $logSelect = $index;
            break;
        }
    }
    expect($logPing)->not->toBeFalse('no dummy select was issued on the log connection');
    expect($logSelect)->not->toBeNull('the log table was never read');
    expect($logPing)->toBeLessThan($logSelect, 'the log manager was not pinged before the first read of its table');
});

it('recovers a dropped entities connection during a prune', function () {
    /** @var GarbageCollector $garbageCollector */
    $garbageCollector = container()->get(GarbageCollector::class);

    /** @var ObjectRepository $logRepository */
    $logRepository = repository(GarbageCollectorLog::class);

    // Given: a stale entity, and a log old enough for the next check to be performed
    /** @var GarbageCollectorLog[] $logs */
    $logs = $logRepository->findBy([], ['id' => 'DESC']);
    $logs[0]->lastCheckedAt = $logs[0]->lastCheckedAt->modify('-2 hours');
    save($logs[0]);
    entityManager()->clear();
    save(new PruneMe(new DateTimeImmutable('-1 year')));

    /** @var FlakyConnection $entitiesConnection */
    $entitiesConnection = entityManager(PruneMe::class)->getConnection();
    expect($entitiesConnection)->toBeInstanceOf(FlakyConnection::class);
    $entitiesConnection->executedSql = [];
    $entitiesConnection->failNextQuery = true;

    // When: the entities connection drops the way an idle MySQL connection does, right before the tick
    foreach ($garbageCollector->prune() as $class => $removed) {
        break;
    }

    // Then: the tick still deleted the stale row and logged it, despite the dropped connection
    expect($removed ?? null)->toBe(1);
    /** @var GarbageCollectorLog[] $newLogs */
    $newLogs = $logRepository->findBy([], ['id' => 'DESC']);
    expect($newLogs[0]->removed)->toBe(1);

    // The entities connection shows the failed dummy select, then the retry, then the DELETE
    $dummySelect = $entitiesConnection->getDatabasePlatform()->getDummySelectSQL();
    expect($entitiesConnection->executedSql[0] ?? null)
        ->toBe($dummySelect, 'the entities connection was not pinged before pruning');
    expect($entitiesConnection->executedSql[1] ?? null)
        ->toBe($dummySelect, 'the dropped connection was not retried after being closed');

    $delete = null;
    foreach ($entitiesConnection->executedSql as $index => $sql) {
        if (str_starts_with($sql, 'DELETE FROM prune_me')) {
            $delete = $index;
            break;
        }
    }
    expect($delete)->not->toBeNull('the prune did not delete anything');
    expect($delete)->toBeGreaterThan(1);
});
