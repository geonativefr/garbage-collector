<?php

declare(strict_types=1);

namespace GeoNative\GarbageCollector\Tests\App;

use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\AbstractException as DriverException;
use Doctrine\DBAL\Exception\ConnectionLost;
use Doctrine\DBAL\Result;

/**
 * A connection whose next query fails the way a MySQL server dropping an idle connection does.
 *
 * Reports itself as connected so that the ping is actually performed: the real drop cannot be
 * reproduced on the SQLite database the test suite runs on.
 */
final class FlakyConnection extends Connection
{
    /**
     * @var list<string>
     */
    public array $events = [];

    public bool $failNextQuery = true;

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

    public function close(): void
    {
        $this->events[] = 'close';

        parent::close();
    }
}
