<?php

declare(strict_types=1);

namespace GeoNative\GarbageCollector\DependencyInjection;

use GeoNative\GarbageCollector\Command\GarbageCollectorCommand;
use GeoNative\GarbageCollector\Services\GarbageCollector;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $container) {
    $services = $container->services();
    $services
        ->defaults()
        ->private()
        ->autoconfigure()
        ->autowire();

    $services
        ->set(GarbageCollector::class)
        ->arg('$repositories', tagged_iterator(GarbageCollector::PRUNABLE_REPOSITORY));

    $services
        ->set(GarbageCollectorCommand::class);
};
