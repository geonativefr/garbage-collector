<?php

declare(strict_types=1);

namespace GeoNative\GarbageCollector\Tests\App\Repository;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use GeoNative\GarbageCollector\PruneStaleEntitiesTrait;
use GeoNative\GarbageCollector\PrunableRepositoryInterface;
use GeoNative\GarbageCollector\Tests\App\Entity\PruneMe;

/**
 * @method PruneMe|null find($id, $lockMode = null, $lockVersion = null)
 * @method PruneMe[] findAll()
 *
 * @template-extends ServiceEntityRepository<PruneMe>
 */
final class PruneMeRepository extends ServiceEntityRepository implements PrunableRepositoryInterface
{
    use PruneStaleEntitiesTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PruneMe::class);
    }

    public function getPruneDateProperty(): string
    {
        return 'createdAt';
    }

    public function getPruneDateBeforeValue(): DateTimeInterface
    {
        return new DateTimeImmutable('-6 months');
    }

    public function getGarbageCollectorCheckInterval(): DateInterval
    {
        return DateInterval::createFromDateString('1 hour');
    }
}

