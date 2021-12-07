<?php

declare(strict_types=1);

namespace GeoNative\GarbageCollector\Tests\Integration;

use DateTimeImmutable;
use Doctrine\Persistence\ObjectRepository;
use GeoNative\GarbageCollector\Entity\GarbageCollectorLog;
use GeoNative\GarbageCollector\Services\GarbageCollector;
use GeoNative\GarbageCollector\Tests\App\Entity\PruneMe;
use GeoNative\GarbageCollector\Tests\App\Repository\PruneMeRepository;
use function repository;

beforeAll(function () {
    create_database();
    create_schema();
});

afterAll(function () {
    drop_database();
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
