<?php

declare(strict_types=1);

namespace GeoNative\GarbageCollector\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UlidGenerator;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
class GarbageCollectorLog
{
    #[
        ORM\Id,
        ORM\Column(type: 'ulid', unique: true),
        ORM\GeneratedValue(strategy: 'CUSTOM'),
        ORM\CustomIdGenerator(class: UlidGenerator::class)
    ]
    public Ulid $id;

    #[ORM\Column]
    public string $class;

    #[ORM\Column(type: 'datetimetz_immutable')]
    public DateTimeImmutable $lastCheckedAt;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    public ?DateTimeImmutable $lastPrunedAt;

    #[ORM\Column]
    public int $duration;

    #[ORM\Column]
    public int $removed;
}
