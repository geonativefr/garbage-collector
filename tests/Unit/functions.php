<?php

declare(strict_types=1);

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use GeoNative\GarbageCollector\Tests\App\Entity\PruneMe;
use GeoNative\GarbageCollector\Tests\App\FlakyConnection;
use GeoNative\GarbageCollector\Tests\App\FlakyPrimaryReadReplicaConnection;
use GeoNative\GarbageCollector\Tests\App\SpyPrimaryReadReplicaConnection;

function disconnected_connection(): Connection
{
    $connection = test()->createMock(Connection::class);
    $connection->method('isConnected')->willReturn(false);
    $connection->expects(test()->never())->method('executeQuery');

    return $connection;
}

function warmed_sqlite_connection(): Connection
{
    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $connection->executeQuery('SELECT 1');

    return $connection;
}

function flaky_sqlite_connection(): FlakyConnection
{
    /** @var FlakyConnection $connection */
    $connection = DriverManager::getConnection([
        'driver' => 'pdo_sqlite',
        'memory' => true,
        'wrapperClass' => FlakyConnection::class,
    ]);
    $connection->failNextQuery = true;

    return $connection;
}

function spy_primary_read_replica_connection(): SpyPrimaryReadReplicaConnection
{
    /** @var SpyPrimaryReadReplicaConnection $connection */
    $connection = DriverManager::getConnection([
        'wrapperClass' => SpyPrimaryReadReplicaConnection::class,
        'driver' => 'pdo_sqlite',
        'primary' => ['memory' => true],
        'replica' => [['memory' => true]],
    ]);

    return $connection;
}

function flaky_primary_read_replica_connection(): FlakyPrimaryReadReplicaConnection
{
    /** @var FlakyPrimaryReadReplicaConnection $connection */
    $connection = DriverManager::getConnection([
        'wrapperClass' => FlakyPrimaryReadReplicaConnection::class,
        'driver' => 'pdo_sqlite',
        'primary' => ['memory' => true],
        'replica' => [['memory' => true]],
    ]);

    return $connection;
}

function manager_registry_for(
    Connection $connection,
    bool $isOpen,
    ?string $resolvedManagerName = null,
    ?EntityManagerInterface $resolvesTo = null,
): ManagerRegistry {
    $entityManager = test()->createMock(EntityManagerInterface::class);
    $entityManager->method('getConnection')->willReturn($connection);
    $entityManager->method('isOpen')->willReturn($isOpen);

    /** @var ManagerRegistry $managerRegistry */
    $managerRegistry = test()->createMock(ManagerRegistry::class);
    $managerRegistry->method('getManagerForClass')->with(PruneMe::class)->willReturn($entityManager);

    if (null !== $resolvedManagerName) {
        $managerRegistry->method('getManagerNames')
            ->willReturn([$resolvedManagerName => "doctrine.orm.{$resolvedManagerName}_entity_manager"]);
        $managerRegistry->method('getManager')
            ->with($resolvedManagerName)
            ->willReturn($resolvesTo ?? $entityManager);
    }

    return $managerRegistry;
}
