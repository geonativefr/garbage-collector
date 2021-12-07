<?php

declare(strict_types=1);

namespace GeoNative\GarbageCollector\Command;

use GeoNative\GarbageCollector\Services\GarbageCollector;
use React\EventLoop\Loop;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function class_exists;
use function is_numeric;

#[AsCommand(
    name: 'gc:entities:prune',
)]
final class GarbageCollectorCommand extends Command
{
    public function __construct(
        private GarbageCollector $garbageCollector
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            name: 'loop',
            mode: InputOption::VALUE_REQUIRED,
            description: 'Runs garbage collector at the given interval, in seconds.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $interval = $input->getOption('loop');
        if (is_numeric($interval)) {
            if (!class_exists(Loop::class)) {
                $io->error('Please install react/event-loop first.');

                return self::FAILURE;
            }
            Loop::futureTick(fn () => $this->runGarbageCollector($io));
            Loop::futureTick(fn () => $io->comment("Waiting {$interval}s..."));
            Loop::addPeriodicTimer((float) $interval, fn () => $this->runGarbageCollector($io));
            Loop::addPeriodicTimer((float) $interval, fn () => $io->comment("Waiting {$interval}s..."));
        } else {
            $this->runGarbageCollector($io);
        }

        return self::SUCCESS;
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
