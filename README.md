# Doctrine Garbage Collector

This package is a Symfony Bundle providing a "garbage collector" command
to prune Doctrine entities that you consider stale.

## Installation

PHP 8.0 or above is required.

```bash
composer require geonative/garbage-collector
```

## Configuration

1. Add the bundle into your `config/bundles.php` (likely to be done automatically with Symfony Flex).

2. Update your configuration:

```yaml
# config/packages/doctrine.yaml
doctrine:
  orm:
    mappings:
      GarbageCollectorBundle: ~
```

```yaml
# config/services.yaml
services:
    _instanceof:
        GeoNative\GarbageCollector\PrunableRepositoryInterface:
            tags: [!php/const GeoNative\GarbageCollector\Services\GarbageCollector::PRUNABLE_REPOSITORY]
```

3. Update your database schema:

```bash
php bin/console doctrine:schema:update --dump-sql --force
```

4. Implement `GeoNative\GarbageCollector\PrunableRepositoryInterface` on your repositories:
    1. `getGarbageCollectorCheckInterval()` should return the minimum interval between checks, to avoid ruining your DB performances
    2. `pruneStaleEntities()` should actually perform removals and return the number of entities which have been removed.

If your entities should be pruned against a DateTime column, you can use `GeoNative\GarbageCollector\PruneStaleEntitiesTrait` to get started faster.

## Usage

### Oneshot

```bash
php bin/console gc:entities:prune
```

You can store this in a crontab to periodically cleanup your entities.

### Daemonize

This command can also run in a loop and be daemonized with supervisord or systemctl.

The `react/event-loop` package is required.

```bash
php bin/console gc:entities:prune --loop=5
```

The Garbage Collector will pass every 5 seconds.

### Lock

If your application runs on multiple hosts, you may want to [prevent several instances](https://symfony.com/doc/current/console/lockable_trait.html)
of the Garbage Collector from running simultaneously. To do so, just add a `--lock` option:

```bash
php bin/console gc:entities:prune --lock
```

## Tests

```bash
vendor/bin/pest
```

## License

MIT.
