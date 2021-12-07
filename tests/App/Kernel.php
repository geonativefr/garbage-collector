<?php

declare(strict_types=1);

namespace GeoNative\GarbageCollector\Tests\App;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use GeoNative\GarbageCollector\GarbageCollectorBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\FrameworkBundle\Test\TestContainer;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\ServiceLocator;

use function dirname;
use function Symfony\Component\DependencyInjection\Loader\Configurator\abstract_arg;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function sys_get_temp_dir;

final class Kernel extends \Symfony\Component\HttpKernel\Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new DoctrineBundle(),
            new GarbageCollectorBundle(),
        ];
    }

    public function configureContainer(ContainerConfigurator $container): void
    {
        $container->services()

            ->set('test.service_container', TestContainer::class)
            ->args([
                service('kernel'),
                'test.private_services_locator',
            ])
            ->public()

            ->set('test.private_services_locator', ServiceLocator::class)
            ->args([abstract_arg('callable collection')])
            ->public()
        ;

        $container->import($this->getProjectDir() . '/config/{packages}/*.yaml');
        $container->import($this->getProjectDir() . '/config/services.yaml');
    }

    public function getProjectDir(): string
    {
        return dirname(__DIR__);
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/sf-cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/sf-log';
    }
}
