<?php

declare(strict_types=1);

namespace GeoNative\GarbageCollector\Tests\Unit;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use GeoNative\GarbageCollector\Services\ConnectionPinger;
use GeoNative\GarbageCollector\Tests\App\Entity\PruneMe;
use GeoNative\GarbageCollector\Tests\App\FlakyConnection;
use GeoNative\GarbageCollector\Tests\App\FlakyPrimaryReadReplicaConnection;
use GeoNative\GarbageCollector\Tests\App\SpyPrimaryReadReplicaConnection;

it('closes and queries again a connection the server has dropped', function () {
    // Given
    /** @var FlakyConnection $connection */
    $connection = DriverManager::getConnection([
        'driver' => 'pdo_sqlite',
        'memory' => true,
        'wrapperClass' => FlakyConnection::class,
    ]);

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->method('getConnection')->willReturn($connection);
    $entityManager->method('isOpen')->willReturn(true);

    $managerRegistry = $this->createMock(ManagerRegistry::class);
    $managerRegistry->method('getManagerForClass')->with(PruneMe::class)->willReturn($entityManager);
    $managerRegistry->expects($this->never())->method('resetManager');

    // When
    (new ConnectionPinger($managerRegistry))->pingConnectionFor(PruneMe::class);

    // Then: the first dummy select failed, the connection was closed, the dummy select was issued again
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
    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $connection->executeQuery('SELECT 1');

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->method('getConnection')->willReturn($connection);
    $entityManager->method('isOpen')->willReturn(false);

    $managerRegistry = $this->createMock(ManagerRegistry::class);
    $managerRegistry->method('getManagerForClass')->with(PruneMe::class)->willReturn($entityManager);
    $managerRegistry->method('getManagerNames')->willReturn(['default' => 'doctrine.orm.default_entity_manager']);
    $managerRegistry->method('getManager')->with('default')->willReturn($entityManager);
    $managerRegistry->expects($this->once())->method('resetManager')->with('default');

    // When
    (new ConnectionPinger($managerRegistry))->pingConnectionFor(PruneMe::class);
});

it('does not reset a manager whose name cannot be resolved', function () {
    // Given
    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $connection->executeQuery('SELECT 1');

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->method('getConnection')->willReturn($connection);
    $entityManager->method('isOpen')->willReturn(false);

    $otherEntityManager = $this->createMock(EntityManagerInterface::class);

    $managerRegistry = $this->createMock(ManagerRegistry::class);
    $managerRegistry->method('getManagerForClass')->with(PruneMe::class)->willReturn($entityManager);
    $managerRegistry->method('getManagerNames')->willReturn(['default' => 'doctrine.orm.default_entity_manager']);
    $managerRegistry->method('getManager')->with('default')->willReturn($otherEntityManager);
    $managerRegistry->expects($this->never())->method('resetManager');

    // When
    (new ConnectionPinger($managerRegistry))->pingConnectionFor(PruneMe::class);
});

it('does not query a connection that never opened', function () {
    // Given
    $connection = $this->createMock(Connection::class);
    $connection->method('isConnected')->willReturn(false);
    $connection->expects($this->never())->method('executeQuery');

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->method('getConnection')->willReturn($connection);

    $managerRegistry = $this->createMock(ManagerRegistry::class);
    $managerRegistry->method('getManagerForClass')->with(PruneMe::class)->willReturn($entityManager);
    $managerRegistry->expects($this->never())->method('resetManager');

    // When
    (new ConnectionPinger($managerRegistry))->pingConnectionFor(PruneMe::class);
});

it('resets the manager when both the connection and the manager are already closed', function () {
    // Given
    $connection = $this->createMock(Connection::class);
    $connection->method('isConnected')->willReturn(false);
    $connection->expects($this->never())->method('executeQuery');

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->method('getConnection')->willReturn($connection);
    $entityManager->method('isOpen')->willReturn(false);

    $managerRegistry = $this->createMock(ManagerRegistry::class);
    $managerRegistry->method('getManagerForClass')->with(PruneMe::class)->willReturn($entityManager);
    $managerRegistry->method('getManagerNames')->willReturn(['default' => 'doctrine.orm.default_entity_manager']);
    $managerRegistry->method('getManager')->with('default')->willReturn($entityManager);
    $managerRegistry->expects($this->once())->method('resetManager')->with('default');

    // When
    (new ConnectionPinger($managerRegistry))->pingConnectionFor(PruneMe::class);
});

it('pings the primary of a primary-replica connection', function () {
    // Given
    /** @var SpyPrimaryReadReplicaConnection $connection */
    $connection = DriverManager::getConnection([
        'wrapperClass' => SpyPrimaryReadReplicaConnection::class,
        'driver' => 'pdo_sqlite',
        'primary' => ['memory' => true],
        'replica' => [['memory' => true]],
    ]);

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->method('getConnection')->willReturn($connection);
    $entityManager->method('isOpen')->willReturn(true);

    $managerRegistry = $this->createMock(ManagerRegistry::class);
    $managerRegistry->method('getManagerForClass')->with(PruneMe::class)->willReturn($entityManager);

    expect($connection->isConnectedToPrimary())->toBeFalse();

    // When
    (new ConnectionPinger($managerRegistry))->pingConnectionFor(PruneMe::class);

    // Then
    expect($connection->isConnectedToPrimary())->toBeTrue();
    expect($connection->executedSql)->toBe([$connection->getDatabasePlatform()->getDummySelectSQL()]);
});

it('retries on the primary after a primary-replica connection is dropped', function () {
    // Given
    /** @var FlakyPrimaryReadReplicaConnection $connection */
    $connection = DriverManager::getConnection([
        'wrapperClass' => FlakyPrimaryReadReplicaConnection::class,
        'driver' => 'pdo_sqlite',
        'primary' => ['memory' => true],
        'replica' => [['memory' => true]],
    ]);

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->method('getConnection')->willReturn($connection);
    $entityManager->method('isOpen')->willReturn(true);

    $managerRegistry = $this->createMock(ManagerRegistry::class);
    $managerRegistry->method('getManagerForClass')->with(PruneMe::class)->willReturn($entityManager);

    // When
    (new ConnectionPinger($managerRegistry))->pingConnectionFor(PruneMe::class);

    // Then: the first dummy select failed on the primary, the connection was closed, the retry
    // reconnected to the primary rather than the replica
    $dummySelect = 'query:' . $connection->getDatabasePlatform()->getDummySelectSQL();
    expect($connection->events)->toBe([
        $dummySelect,
        'close',
        $dummySelect,
    ]);
    expect($connection->isConnectedToPrimary())->toBeTrue();
});
