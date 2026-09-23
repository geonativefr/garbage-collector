<?php

declare(strict_types=1);

namespace GeoNative\GarbageCollector\Tests\Unit;

use Doctrine\ORM\EntityManagerInterface;
use GeoNative\GarbageCollector\Services\ConnectionPinger;
use GeoNative\GarbageCollector\Tests\App\Entity\PruneMe;

require_once __DIR__ . '/functions.php';

it('closes and queries again a connection the server has dropped', function () {
    // Given
    $connection = flaky_sqlite_connection();
    $managerRegistry = manager_registry_for($connection, isOpen: true);
    $managerRegistry->expects($this->never())->method('resetManager');

    // When
    (new ConnectionPinger($managerRegistry))->pingConnectionFor(PruneMe::class);

    // Then
    // The first dummy select failed, the connection was closed, the dummy select was issued again
    $dummySelect = 'query:' . $connection->getDatabasePlatform()->getDummySelectSQL();
    expect($connection->events)->toBe([
        $dummySelect,
        'close',
        $dummySelect,
    ]);
    expect($connection->failNextQuery)->toBeFalse();
});

it('resets the manager whose name can be resolved when it is no longer open', function () {
    // Given
    $connection = warmed_sqlite_connection();
    $managerRegistry = manager_registry_for($connection, isOpen: false, resolvedManagerName: 'default');
    $managerRegistry->expects($this->once())->method('resetManager')->with('default');

    // When
    (new ConnectionPinger($managerRegistry))->pingConnectionFor(PruneMe::class);
});

it('does not reset a manager whose name cannot be resolved', function () {
    // Given
    $connection = warmed_sqlite_connection();
    $otherEntityManager = $this->createMock(EntityManagerInterface::class);
    $managerRegistry = manager_registry_for(
        $connection,
        isOpen: false,
        resolvedManagerName: 'default',
        resolvesTo: $otherEntityManager,
    );
    $managerRegistry->expects($this->never())->method('resetManager');

    // When
    (new ConnectionPinger($managerRegistry))->pingConnectionFor(PruneMe::class);
});

it('does not query a connection that never opened', function () {
    // Given
    $connection = disconnected_connection();
    $managerRegistry = manager_registry_for($connection, isOpen: false);
    $managerRegistry->expects($this->never())->method('resetManager');

    // When
    (new ConnectionPinger($managerRegistry))->pingConnectionFor(PruneMe::class);
});

it('resets the manager when both the connection and the manager are already closed', function () {
    // Given
    $connection = disconnected_connection();
    $managerRegistry = manager_registry_for($connection, isOpen: false, resolvedManagerName: 'default');
    $managerRegistry->expects($this->once())->method('resetManager')->with('default');

    // When
    (new ConnectionPinger($managerRegistry))->pingConnectionFor(PruneMe::class);
});

it('pings the primary of a primary-replica connection', function () {
    // Given
    $connection = spy_primary_read_replica_connection();
    $managerRegistry = manager_registry_for($connection, isOpen: true);

    expect($connection->isConnectedToPrimary())->toBeFalse();

    // When
    (new ConnectionPinger($managerRegistry))->pingConnectionFor(PruneMe::class);

    // Then
    expect($connection->isConnectedToPrimary())->toBeTrue();
    expect($connection->executedSql)->toBe([$connection->getDatabasePlatform()->getDummySelectSQL()]);
});

it('retries on the primary after a primary-replica connection is dropped', function () {
    // Given
    $connection = flaky_primary_read_replica_connection();
    $managerRegistry = manager_registry_for($connection, isOpen: true);

    // When
    (new ConnectionPinger($managerRegistry))->pingConnectionFor(PruneMe::class);

    // Then
    // The first dummy select failed on the primary, the connection was closed, the retry
    // reconnected to the primary rather than the replica
    $dummySelect = 'query:' . $connection->getDatabasePlatform()->getDummySelectSQL();
    expect($connection->events)->toBe([
        $dummySelect,
        'close',
        $dummySelect,
    ]);
    expect($connection->isConnectedToPrimary())->toBeTrue();
});
