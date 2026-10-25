<?php

declare(strict_types=1);

namespace GeoNative\GarbageCollector\Command;

use GeoNative\GarbageCollector\Services\GarbageCollector;
use React\EventLoop\Loop;
use React\EventLoop\TimerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function class_exists;
use function function_exists;
use function is_numeric;

#[AsCommand(
    name: 'gc:entities:prune',
)]
final class GarbageCollectorCommand extends Command
{
    use LockableTrait;

    public function __construct(
        private GarbageCollector $garbageCollector,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            name: 'loop',
            mode: InputOption::VALUE_REQUIRED,
            description: 'Runs garbage collector at the given interval, in seconds.',
        );
        $this->addOption(
            name: 'lock',
            mode: InputOption::VALUE_NONE,
            description: 'Prevent multiple garbage collector instances from running simultaneously.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (true === $input->getOption('lock') && !$this->lock()) {
            $output->writeln('The command is already running in another process.');

            return Command::SUCCESS;
        }

        $interval = $input->getOption('loop');
        if (is_numeric($interval)) {
            if (!class_exists(Loop::class)) {
                $io->error('Please install react/event-loop first.');

                return self::FAILURE;
            }
            Loop::futureTick(fn () => $this->runGarbageCollector($io));
            Loop::futureTick(fn () => $io->comment("Waiting {$interval}s..."));
            $timers = [
                Loop::addPeriodicTimer((float) $interval, fn () => $this->runGarbageCollector($io)),
                Loop::addPeriodicTimer((float) $interval, fn () => $io->comment("Waiting {$interval}s...")),
            ];
            $this->stopLoopOnTermination($io, $timers);
            Loop::run();
        } else {
            $this->runGarbageCollector($io);
        }

        return self::SUCCESS;
    }

    /**
     * @param TimerInterface[] $timers
     */
    private function stopLoopOnTermination(SymfonyStyle $io, array $timers): void
    {
        if (!function_exists('pcntl_signal')) {
            $io->comment('ext-pcntl is not available: SIGTERM and SIGINT will not stop the loop gracefully.');

            return;
        }

        $signals = [SIGTERM, SIGINT];
        $stop = null;
        $stop = static function () use (&$stop, $signals, $timers): void {
            foreach ($timers as $timer) {
                Loop::cancelTimer($timer);
            }
            foreach ($signals as $signal) {
                Loop::removeSignal($signal, $stop);
            }
            Loop::stop();
        };
        foreach ($signals as $signal) {
            Loop::addSignal($signal, $stop);
        }
    }

    private function runGarbageCollector(SymfonyStyle $io): void
    {
        $io->comment('Cleaning up... 🧹');
        $pruned = $this->garbageCollector->prune();
        foreach ($pruned as $class => $removed) {
            if ($removed > 0) {
                $io->comment("Removed {$removed} entities from {$class}.");
            }
        }
        $io->success("Done. 💅");
    }
}
