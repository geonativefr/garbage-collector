<?php

declare(strict_types=1);

namespace GeoNative\GarbageCollector\Tests\App;

use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;

/**
 * Records every statement sent to the connection, in order, so tests can assert on their sequence.
 */
final class SpyConnection extends Connection
{
    /**
     * @var string[]
     */
    public array $executedSql = [];

    public function executeQuery(
        string $sql,
        array $params = [],
        array $types = [],
        ?QueryCacheProfile $qcp = null,
    ): Result {
        $this->executedSql[] = $sql;

        return parent::executeQuery($sql, $params, $types, $qcp);
    }

    public function executeStatement(string $sql, array $params = [], array $types = []): int|string
    {
        $this->executedSql[] = $sql;

        return parent::executeStatement($sql, $params, $types);
    }
}
