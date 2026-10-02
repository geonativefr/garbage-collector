<?php

declare(strict_types=1);

namespace GeoNative\GarbageCollector;

use DateTimeInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Type;

/**
 * Deletes the stale entities in batches of identifiers read on the prune date index, so that each DELETE locks only
 * the rows it removes, whatever plan the optimizer would choose for a DELETE on a range of dates.
 */
trait PruneStaleEntitiesTrait
{
    private const int DEFAULT_PRUNE_BATCH_SIZE = 1000;

    abstract public function getPruneDateProperty(): string;

    abstract public function getPruneDateBeforeValue(): DateTimeInterface;

    public function getPruneBatchSize(): int
    {
        return self::DEFAULT_PRUNE_BATCH_SIZE;
    }

    public function pruneStaleEntities(): int
    {
        $pruneDate = $this->getPruneDateBeforeValue();
        $removed = 0;

        do {
            $removedInBatch = $this->deleteEntities($this->findStaleIdentifiers($pruneDate));
            $removed += $removedInBatch;
        } while ($removedInBatch > 0);

        return $removed;
    }

    /**
     * @return mixed[]
     */
    private function findStaleIdentifiers(DateTimeInterface $pruneDate): array
    {
        $pruneDateProperty = "o.{$this->getPruneDateProperty()}";

        return $this->createQueryBuilder('o')
            ->select("o.{$this->getClassMetadata()->getSingleIdentifierFieldName()}")
            ->where("{$pruneDateProperty} < :pruneDate")
            ->setParameter('pruneDate', $pruneDate)
            ->orderBy($pruneDateProperty)
            ->setMaxResults($this->getPruneBatchSize())
            ->getQuery()
            ->getSingleColumnResult();
    }

    /**
     * @param mixed[] $identifiers
     */
    private function deleteEntities(array $identifiers): int
    {
        if ([] === $identifiers) {
            return 0;
        }

        $query = $this->createQueryBuilder('o')
            ->delete()
            ->where("o.{$this->getClassMetadata()->getSingleIdentifierFieldName()} IN (:identifiers)")
            ->setParameter('identifiers', $identifiers, $this->getIdentifierArrayParameterType())
            ->getQuery();

        return $query->execute();
    }

    private function getIdentifierArrayParameterType(): ArrayParameterType
    {
        $classMetadata = $this->getClassMetadata();
        $identifierType = Type::getType((string) $classMetadata->getTypeOfField($classMetadata->getSingleIdentifierFieldName()));

        return match ($identifierType->getBindingType()) {
            ParameterType::INTEGER => ArrayParameterType::INTEGER,
            ParameterType::ASCII => ArrayParameterType::ASCII,
            ParameterType::BINARY => ArrayParameterType::BINARY,
            default => ArrayParameterType::STRING,
        };
    }
}
