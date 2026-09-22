<?php

declare(strict_types=1);

namespace GeoNative\GarbageCollector\Tests\Unit;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use GeoNative\GarbageCollector\Services\ConnectionPinger;
use GeoNative\GarbageCollector\Tests\App\Entity\PruneMe;
use GeoNative\GarbageCollector\Tests\App\FlakyConnection;

it('closes and queries again a connection the server has dropped', function () {
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
    expect($connection->events)->toBe([
        'query:SELECT 1',
        'close',
        'query:SELECT 1',
    ]);
    expect($connection->failNextQuery)->toBeFalse();
});
