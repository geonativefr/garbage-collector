<?php

declare(strict_types=1);

use GeoNative\GarbageCollector\Command\GarbageCollectorCommand;
use GeoNative\GarbageCollector\Entity\GarbageCollectorLog;
use GeoNative\GarbageCollector\Services\GarbageCollector;
use GeoNative\GarbageCollector\Tests\App\Entity\PruneMe;
use GeoNative\GarbageCollector\Tests\App\FlakyConnection;
use GeoNative\GarbageCollector\Tests\App\SpyConnection;
use React\EventLoop\Loop;
use React\EventLoop\TimerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcher;

function make_next_check_due(GarbageCollectorLog $log): void
{
    $log->lastCheckedAt = $log->lastCheckedAt->modify('-2 hours');
    save($log);
    entityManager()->clear();
}

function save_stale_prune_me(): void
{
    save(new PruneMe(new DateTimeImmutable('-1 year')));
}

/**
 * @param DateTimeImmutable[] $createdAt
 *
 * @return PruneMe[]
 */
function save_prune_me(DateTimeImmutable ...$createdAt): array
{
    $entities = array_map(static fn (DateTimeImmutable $date) => new PruneMe($date), $createdAt);
    save(...$entities);

    return $entities;
}

function prune_first_class(GarbageCollector $garbageCollector): ?int
{
    foreach ($garbageCollector->prune() as $removed) {
        return $removed;
    }

    return null;
}

function entities_connection(): FlakyConnection
{
    /** @var FlakyConnection $connection */
    $connection = entityManager(PruneMe::class)->getConnection();
    expect($connection)->toBeInstanceOf(FlakyConnection::class);

    return $connection;
}

function log_connection(): SpyConnection
{
    /** @var SpyConnection $connection */
    $connection = entityManager()->getConnection();
    expect($connection)->toBeInstanceOf(SpyConnection::class);

    return $connection;
}

function dummy_select_index(FlakyConnection|SpyConnection $connection): ?int
{
    $index = array_search($connection->getDatabasePlatform()->getDummySelectSQL(), $connection->executedSql, true);

    return false === $index ? null : $index;
}

/**
 * @param string[] $executedSql
 */
function count_statements(array $executedSql, string $startingWith): int
{
    return count(array_filter($executedSql, static fn (string $sql) => str_starts_with($sql, $startingWith)));
}

/**
 * @param string[] $executedSql
 */
function first_statement_index(array $executedSql, ?string $startingWith = null, ?string $containing = null): ?int
{
    foreach ($executedSql as $index => $sql) {
        if (null !== $startingWith && !str_starts_with($sql, $startingWith)) {
            continue;
        }
        if (null !== $containing && !str_contains($sql, $containing)) {
            continue;
        }

        return $index;
    }

    return null;
}

function garbage_collector_command(): GarbageCollectorCommand
{
    /** @var GarbageCollector $garbageCollector */
    $garbageCollector = container()->get(GarbageCollector::class);

    return new GarbageCollectorCommand($garbageCollector);
}

/**
 * A console application with an event dispatcher: it registers its own signal handlers around the
 * command, and restores the previous ones when the command returns.
 */
function garbage_collector_application(): ApplicationTester
{
    $application = new Application();
    $application->setAutoExit(false);
    $application->setDispatcher(new EventDispatcher());
    $application->addCommand(garbage_collector_command());

    return new ApplicationTester($application);
}

function make_latest_check_due(): void
{
    /** @var GarbageCollectorLog[] $logs */
    $logs = repository(GarbageCollectorLog::class)->findBy([], ['id' => 'DESC'], 1);
    if ([] !== $logs) {
        make_next_check_due($logs[0]);
    }
}

/**
 * @return PruneMe[]
 */
function stale_prune_me(): array
{
    return repository(PruneMe::class)->createQueryBuilder('o')
        ->where('o.createdAt < :pruneDate')
        ->setParameter('pruneDate', new DateTimeImmutable('-6 months'))
        ->getQuery()
        ->getResult();
}

/**
 * Runs the looping command until it stops, the signal being sent from inside the first pass, right
 * before the stale rows are deleted. A safety timer ends a loop that ignores the signal.
 *
 * @return bool whether the safety timer had to end the loop
 */
function run_loop_signalled_in_pass(CommandTester $commandTester, int $signal, string $interval): bool
{
    return run_signalled_in_pass($signal, static fn () => $commandTester->execute(['--loop' => $interval]));
}

/**
 * Same as run_loop_signalled_in_pass(), through the console application. Once the application has
 * returned, the loop is run again as react/event-loop runs it on shutdown: a loop left for then runs
 * with the signal handlers the application restored, here a witness handler so that a signal still
 * pending fails the test instead of taking its default action. The safety timer stays armed through
 * that second run unless the pass already ran and the loop stopped on the signal, as it would then
 * be waited for.
 *
 * @return bool whether the safety timer had to end the loop
 */
function run_application_loop_signalled_in_pass(ApplicationTester $applicationTester, int $signal, string $interval): bool
{
    pcntl_signal($signal, static function (): void {
    });

    try {
        return run_signalled_in_pass(
            $signal,
            static function (TimerInterface $safetyTimer) use ($applicationTester, $interval): void {
                $applicationTester->run(['command' => 'gc:entities:prune', '--loop' => $interval]);
                if (null === entities_connection()->beforeNextStatement) {
                    Loop::cancelTimer($safetyTimer);
                }
                Loop::run();
            },
        );
    } finally {
        pcntl_signal($signal, SIG_DFL);
    }
}

/**
 * @return bool whether the safety timer had to end the loop
 */
function run_signalled_in_pass(int $signal, Closure $runCommand): bool
{
    entities_connection()->beforeNextStatement = static fn () => posix_kill(posix_getpid(), $signal);

    $timedOut = false;
    $safetyTimer = Loop::addTimer(2, static function () use (&$timedOut): void {
        $timedOut = true;
        Loop::stop();
    });
    $runCommand($safetyTimer);
    Loop::cancelTimer($safetyTimer);

    return $timedOut;
}
