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

        if ($connection instanceof PrimaryReadReplicaConnection) {
            // Check the connection the writes will actually run on.
            $connection->ensureConnectedToPrimary();
        }

        if (!$connection->isConnected()) {
            // A lazy connection opens itself on the first query, there is nothing to check.
            return;
        }

        try {
            $this->executeDummySql($connection);
        } catch (DBALException) {
            $connection->close();
            // Attempt to reestablish the lazy connection by sending another query.
            $this->executeDummySql($connection);
        }

        if (!$entityManager->isOpen()) {
            $this->managerRegistry->resetManager($this->getManagerName($entityManager));
        }
    }

    /**
     * @throws DBALException
     */
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
