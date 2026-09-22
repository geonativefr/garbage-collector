<?php

declare(strict_types=1);

namespace GeoNative\GarbageCollector\Services;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Connections\PrimaryReadReplicaConnection;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Checks whether the connection of a given class is still open, and reconnects it otherwise.
 *
 * A repository pruned once a day leaves its connection idle in between, long enough for the
 * server to drop it: the prune would then fail on its very first statement.
 */
final class ConnectionPinger
{
    public function __construct(
        private ManagerRegistry $managerRegistry,
    ) {
    }

    public function pingConnectionFor(string $class): void
    {
        $entityManager = $this->managerRegistry->getManagerForClass($class);
        if (!$entityManager instanceof EntityManagerInterface) {
            return;
        }

        $connection = $entityManager->getConnection();

        $this->ensureConnectedToPrimary($connection);

        if (!$connection->isConnected()) {
            return;
        }

        try {
            $this->executeDummySql($connection);
        } catch (DBALException) {
            $connection->close();
            $this->ensureConnectedToPrimary($connection);
            $this->executeDummySql($connection);
        }

        if (!$entityManager->isOpen()) {
            $name = $this->getManagerName($entityManager);
            if (null !== $name) {
                $this->managerRegistry->resetManager($name);
            }
        }
    }

    private function ensureConnectedToPrimary(Connection $connection): void
    {
        if ($connection instanceof PrimaryReadReplicaConnection) {
            $connection->ensureConnectedToPrimary();
        }
    }

    private function executeDummySql(Connection $connection): void
    {
        $connection->executeQuery($connection->getDatabasePlatform()->getDummySelectSQL());
    }

    private function getManagerName(EntityManagerInterface $entityManager): ?string
    {
        foreach ($this->managerRegistry->getManagerNames() as $name => $serviceId) {
            if ($this->managerRegistry->getManager($name) === $entityManager) {
                return $name;
            }
        }

        return null;
    }
}
