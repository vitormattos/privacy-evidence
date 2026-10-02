<?php

declare(strict_types=1);

namespace PrivacyEvidence\Command;

use PrivacyEvidence\Browser\PlaywrightBrowserProvider;
use PrivacyEvidence\Pipeline\PipelineConfig;
use PrivacyEvidence\Runtime\RuntimeFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class WorkerCommand extends Command
{
    public function __construct(private readonly string $projectRoot)
    {
        parent::__construct('worker');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Process a bounded pipeline stage for a persisted research run.')
            ->addArgument('run-id', InputArgument::REQUIRED)
            ->addArgument('stage', InputArgument::REQUIRED, 'fetch or browser')
            ->addOption('max-jobs', null, InputOption::VALUE_REQUIRED, 'Maximum jobs before recycling', '100')
            ->addOption(
                'per-host-concurrency',
                null,
                InputOption::VALUE_REQUIRED,
                'Maximum simultaneously reserved jobs per host',
                '2',
            )
            ->addOption(
                'min-host-delay-ms',
                null,
                InputOption::VALUE_REQUIRED,
                'Minimum delay between reservations for the same host',
                '250',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $runId = $input->getArgument('run-id');
        $stage = $input->getArgument('stage');
        if (!is_string($runId) || $runId === '' || !is_string($stage)) {
            return Command::INVALID;
        }

        if (!in_array($stage, ['fetch', 'browser'], true)) {
            $output->writeln('<error>Stage must be fetch or browser.</error>');
            return Command::INVALID;
        }

        $maxJobs = $this->positiveInt($input->getOption('max-jobs'), 'max-jobs');
        $perHost = $this->positiveInt(
            $input->getOption('per-host-concurrency'),
            'per-host-concurrency',
        );
        $minDelay = $this->nonNegativeInt(
            $input->getOption('min-host-delay-ms'),
            'min-host-delay-ms',
        );

        $runtime = RuntimeFactory::create($this->projectRoot);
        if ($runtime->runs->get($runId) === null) {
            $output->writeln('<error>Unknown run.</error>');
            return Command::FAILURE;
        }

        $worker = $this->projectRoot . '/browser/src/worker.mjs';
        $browser = is_file($worker) && is_dir($this->projectRoot . '/browser/node_modules/playwright')
            ? new PlaywrightBrowserProvider($worker)
            : null;

        if ($stage === 'browser' && $browser === null) {
            $output->writeln('<error>Playwright browser dependencies are unavailable.</error>');
            return Command::FAILURE;
        }

        $pipeline = RuntimeFactory::pipeline(
            $runtime,
            new PipelineConfig(
                maxJobsPerInvocation: $maxJobs,
                enableBrowserEscalation: $browser !== null,
                perHostConcurrency: $perHost,
                minHostDelayMs: $minDelay,
            ),
            $browser,
        );

        $processed = $pipeline->executeStage($runId, $stage, $maxJobs);
        $output->writeln((string) $processed);

        return Command::SUCCESS;
    }

    private function positiveInt(mixed $value, string $name): int
    {
        $parsed = filter_var($value, FILTER_VALIDATE_INT);
        if (!is_int($parsed) || $parsed <= 0) {
            throw new \InvalidArgumentException(sprintf('%s must be a positive integer.', $name));
        }

        return $parsed;
    }

    private function nonNegativeInt(mixed $value, string $name): int
    {
        $parsed = filter_var($value, FILTER_VALIDATE_INT);
        if (!is_int($parsed) || $parsed < 0) {
            throw new \InvalidArgumentException(sprintf('%s must be a non-negative integer.', $name));
        }

        return $parsed;
    }
}
