<?php

declare(strict_types=1);

use Doctrine\Persistence\Proxy;
use GeoNative\GarbageCollector\Tests\App\Kernel;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\KernelInterface;

function clear_type_registry(): void
{
    $registry = Type::getTypeRegistry();
    $refl = new ReflectionClass($registry);
    $instancesProp = $refl->getProperty('instances');
    $instancesProp->setValue($registry, []);
}

function app(): Kernel
{
    static $kernel;

    $kernel ??= (function () {
        $testCase = new class ('AppTests') extends KernelTestCase {
            public function getKernel(): KernelInterface
            {
                self::bootKernel();

                return self::$kernel;
            }
        };

        return $testCase->getKernel();
    })();

    return $kernel;
}

/**
 * Shortcut to the test container (all services are public).
 */
function container(): ContainerInterface
{
    return app()->getContainer()->get('test.service_container');
}

function create_schema(): void
{
    /** @var Registry $doctrine */
    $doctrine = container()->get('doctrine');
    /** @var EntityManagerInterface $entityManager */
    $entityManager = container()->get(EntityManagerInterface::class);
    $schemaTool = new SchemaTool($entityManager);
    $classes = $entityManager->getMetadataFactory()->getAllMetadata();
    $schemaTool->createSchema($classes);
}

function save(object ...$entities): void
{
    foreach ($entities as $entity) {
        $realClass = $entity instanceof Proxy
            ? get_parent_class($entity)
            : $entity::class;

        $entityManager = entityManager($realClass); // @phpstan-ignore-line
        $entityManager->persist($entity);
        $entityManager->flush();
    }
}

function entityManager(?string $className = null): EntityManagerInterface
{
    /** @var ManagerRegistry $doctrine */
    $doctrine = container()->get(ManagerRegistry::class);

    // @phpstan-ignore-next-line
    return $className ? $doctrine->getManagerForClass($className) : $doctrine->getManager();
}

/**
 * @psalm-param class-string<T> $className
 * @psalm-return EntityRepository<T>
 * @template T
 */
function repository(string $className): EntityRepository
{
    return entityManager($className)->getRepository($className);
}
