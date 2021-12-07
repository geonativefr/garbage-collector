<?php

declare(strict_types=1);

namespace GeoNative\GarbageCollector\Services;

use Generator;
use GeoNative\GarbageCollector\Entity\GarbageCollectorLog;
use GeoNative\GarbageCollector\PrunableRepositoryInterface;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectRepository;

use function microtime;
use function round;

final class GarbageCollector
{
    public const PRUNABLE_REPOSITORY = 'garbage_collector.prunable_repository';

    /**
     * @var string[]
     */
    private array $classes = [];

    /**
     * @param iterable<PrunableRepositoryInterface&ObjectRepository> $repositories
     */
    public function __construct(
        private ManagerRegistry $managerRegistry,
        iterable $repositories
    ) {
        foreach ($repositories as $repository) {
            $this->classes[] = $repository->getClassName();
        }
    }

    public function prune(): Generator
    {
        foreach ($this->classes as $class) {
            yield $class => $this->pruneEntitiesFromClass($class);
        }
    }

    private function pruneEntitiesFromClass(string $class): int
    {
        /** @var PrunableRepositoryInterface $repository */
        $repository = $this->managerRegistry->getRepository($class); // @phpstan-ignore-line

        $lastLog = $this->getLastLog($class);
        if (!$this->shouldPerformCheck($class, $lastLog)) {
            return 0;
        }

        $log = $this->createLog($class);

        $start = microtime(true);
        $log->removed = $repository->pruneStaleEntities();
        $end = microtime(true);

        $log->lastPrunedAt = $log->removed > 0 ? new DateTimeImmutable() : $lastLog?->lastPrunedAt;
        $log->duration = (int) round(($end - $start) * 1000);

        /** @var EntityManagerInterface $em */
        $em = $this->managerRegistry->getManagerForClass(GarbageCollectorLog::class);
        $em->persist($log);
        $em->flush();

        return $log->removed;
    }

    private function shouldPerformCheck(string $class, ?GarbageCollectorLog $lastLog): bool
    {
        if (null === $lastLog) {
            return true;
        }

        /** @var PrunableRepositoryInterface $repository */
        $repository = $this->managerRegistry->getRepository($class); // @phpstan-ignore-line
        $interval = $repository->getGarbageCollectorCheckInterval();

        return new DateTimeImmutable() > $lastLog->lastCheckedAt->add($interval);
    }

    private function getLogRepository(): ObjectRepository
    {
        return $this->managerRegistry->getRepository(GarbageCollectorLog::class);
    }

    private function getLastLog(string $class): ?GarbageCollectorLog
    {
        return $this->getLogRepository()->findOneBy(['class' => $class], ['id' => 'DESC']); // @phpstan-ignore-line
    }

    private function createLog(string $class): GarbageCollectorLog
    {
        $log = new GarbageCollectorLog();
        $log->class = $class;
        $log->lastCheckedAt = new DateTimeImmutable();

        return $log;
    }
}
