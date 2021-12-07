<?php

declare(strict_types=1);

namespace GeoNative\GarbageCollector\Tests\App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use GeoNative\GarbageCollector\Tests\App\Repository\PruneMeRepository;
use Symfony\Bridge\Doctrine\IdGenerator\UlidGenerator;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: PruneMeRepository::class)]
class PruneMe
{
    #[
        ORM\Id,
        ORM\Column(type: 'ulid', unique: true),
        ORM\GeneratedValue(strategy: 'CUSTOM'),
        ORM\CustomIdGenerator(class: UlidGenerator::class)
    ]
    public Ulid $id;

    #[ORM\Column]
    public DateTimeImmutable $createdAt;

    public function __construct(DateTimeImmutable $createdAt)
    {
        $this->createdAt = $createdAt;
    }
}
