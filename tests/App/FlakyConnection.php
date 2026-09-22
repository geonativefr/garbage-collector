<?php

declare(strict_types=1);

namespace GeoNative\GarbageCollector\Tests\App;

use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\AbstractException as DriverException;
use Doctrine\DBAL\Exception\ConnectionLost;
use Doctrine\DBAL\Result;

/**
 * A connection that also records every statement sent to it, in order (like SpyConnection), and
 * whose next query fails the way a MySQL server dropping an idle connection does once a test
 * arms it.
 *
 * Reports itself as connected so that the ping is actually performed: the real drop cannot be
 * reproduced on the SQLite database the test suite runs on.
 */
final class FlakyConnection extends Connection
{
    /**
     * @var string[]
     */
    public array $events = [];

    /**
     * @var string[]
     */
    public array $executedSql = [];

    public bool $failNextQuery = false;

    public function isConnected(): bool
    {
        return true;
    }

    public function executeQuery(
        string $sql,
        array $params = [],
        array $types = [],
        ?QueryCacheProfile $qcp = null,
    ): Result {
        $this->executedSql[] = $sql;
        $this->events[] = "query:{$sql}";

        if ($this->failNextQuery) {
            $this->failNextQuery = false;

            throw new ConnectionLost(
                new class ('MySQL server has gone away', 'HY000', 2006) extends DriverException {},
                null,
            );
        }

        return parent::executeQuery($sql, $params, $types, $qcp);
    }

    public function executeStatement(string $sql, array $params = [], array $types = []): int|string
    {
        $this->executedSql[] = $sql;

        return parent::executeStatement($sql, $params, $types);
    }

    public function close(): void
    {
        $this->events[] = 'close';

        parent::close();
    }
}
