<?php

declare(strict_types=1);

namespace GeoNative\GarbageCollector;

use DateTimeInterface;

trait PruneStaleEntitiesTrait
{
    abstract public function getPruneDateProperty(): string;

    abstract public function getPruneDateBeforeValue(): DateTimeInterface;

    public function pruneStaleEntities(): int
    {
        $qb = $this->createQueryBuilder('o')
            ->delete()
            ->where("o.{$this->getPruneDateProperty()} < :pruneDate")
            ->setParameter('pruneDate', $this->getPruneDateBeforeValue());
        $query = $qb->getQuery();

        return $query->execute();
    }
}
