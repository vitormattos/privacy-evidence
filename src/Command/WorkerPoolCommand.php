<?php

declare(strict_types=1);

namespace PrivacyEvidence\Command;

use PrivacyEvidence\Runtime\RuntimeFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

final class WorkerPoolCommand extends Command
{
    public function __construct(private readonly string $projectRoot)
    {
        parent::__construct('workers');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Run a bounded recyclable worker pool for one pipeline stage.')
            ->addArgument('run-id', InputArgument::REQUIRED)
            ->addArgument('stage', InputArgument::REQUIRED, 'fetch or browser')
            ->addOption('workers', null, InputOption::VALUE_REQUIRED, 'Global process concurrency')
            ->addOption('max-jobs', null, InputOption::VALUE_REQUIRED, 'Jobs before worker recycle', '100')
            ->addOption('per-host-concurrency', null, InputOption::VALUE_REQUIRED, 'Per-host concurrency')
            ->addOption('min-host-delay-ms', null, InputOption::VALUE_REQUIRED, 'Per-host delay')
            ->addOption(
                'max-worker-failures',
                null,
                InputOption::VALUE_REQUIRED,
                'Maximum consecutive failed worker waves before aborting',
                '3',
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

        $runtime = RuntimeFactory::create($this->projectRoot);
        $run = $runtime->runs->get($runId);
        if ($run === null) {
            $output->writeln('<error>Unknown run.</error>');
            return Command::FAILURE;
        }

        $scheduler = $run->configuration['scheduler'] ?? [];
        $scheduler = is_array($scheduler) ? $scheduler : [];

        /** @psalm-suppress MixedAssignment Symfony InputInterface returns mixed by contract. */
        $workersOption = $input->getOption('workers');
        $defaultWorkersKey = $stage === 'fetch'
            ? 'recommendedHttpWorkers'
            : 'recommendedBrowserWorkers';
        $defaultWorkers = $stage === 'fetch' ? 8 : 2;
        $workers = $workersOption === null
            ? $this->schedulerInt($scheduler, $defaultWorkersKey, $defaultWorkers)
            : $this->positiveInt($workersOption, 'workers');

        $maxJobs = $this->positiveInt($input->getOption('max-jobs'), 'max-jobs');
        $perHost = $input->getOption('per-host-concurrency') === null
            ? $this->schedulerInt($scheduler, 'perHostConcurrency', 2)
            : $this->positiveInt($input->getOption('per-host-concurrency'), 'per-host-concurrency');
        $delay = $input->getOption('min-host-delay-ms') === null
            ? $this->schedulerInt($scheduler, 'minHostDelayMs', 250, allowZero: true)
            : $this->nonNegativeInt($input->getOption('min-host-delay-ms'), 'min-host-delay-ms');
        $maxWorkerFailures = $this->positiveInt(
            $input->getOption('max-worker-failures'),
            'max-worker-failures',
        );

        $runtime->runs->recordEvent(
            $runId,
            'worker_pool_start',
            null,
            [
                'stage' => $stage,
                'workers' => $workers,
                'max_jobs' => $maxJobs,
                'per_host_concurrency' => $perHost,
                'min_host_delay_ms' => $delay,
                'max_worker_failures' => $maxWorkerFailures,
            ],
        );

        $bin = $this->projectRoot . '/bin/privacy-evidence';
        $waves = 0;
        $consecutiveFailedWaves = 0;

        while (true) {
            $counts = $runtime->jobs->stageCounts($runId, $stage);
            if (($counts['pending'] ?? 0) === 0 && ($counts['running'] ?? 0) === 0) {
                break;
            }
            if (($counts['pending'] ?? 0) === 0 && ($counts['running'] ?? 0) > 0) {
                $output->writeln('<error>Running jobs remain without available work; use run:resume after checking workers.</error>');
                return Command::FAILURE;
            }

            $processes = [];
            for ($i = 0; $i < $workers; $i++) {
                $process = new Process([
                    PHP_BINARY,
                    $bin,
                    'worker',
                    $runId,
                    $stage,
                    '--max-jobs=' . $maxJobs,
                    '--per-host-concurrency=' . $perHost,
                    '--min-host-delay-ms=' . $delay,
                ]);
                $process->setTimeout(null);
                $process->start();
                $processes[] = $process;
            }

            $failedProcesses = 0;
            foreach ($processes as $process) {
                $process->wait();
                if (!$process->isSuccessful()) {
                    $failedProcesses++;
                    $message = trim($process->getErrorOutput());
                    if ($message === '') {
                        $message = trim($process->getOutput());
                    }
                    $output->writeln('<error>' . $message . '</error>');
                }
            }

            $waves++;
            if ($failedProcesses > 0) {
                $consecutiveFailedWaves++;
                $requeued = $runtime->jobs->requeueRunning($runId);
                $runtime->runs->increment($runId, 'worker_process_failures', $failedProcesses);
                $runtime->runs->increment(
                    $runId,
                    'worker_process_failures.' . $stage,
                    $failedProcesses,
                );
                if ($requeued > 0) {
                    $runtime->runs->increment($runId, 'jobs_recovered', $requeued);
                }
                $runtime->runs->recordEvent(
                    $runId,
                    'worker_pool_recovery',
                    null,
                    [
                        'stage' => $stage,
                        'failed_processes' => $failedProcesses,
                        'requeued_running_jobs' => $requeued,
                        'consecutive_failed_waves' => $consecutiveFailedWaves,
                    ],
                );

                if ($consecutiveFailedWaves >= $maxWorkerFailures) {
                    return Command::FAILURE;
                }

                usleep(max($delay, 100) * 1000);
                continue;
            }

            $consecutiveFailedWaves = 0;

            if ($waves > 10000) {
                throw new \RuntimeException('Worker pool exceeded safety wave limit.');
            }

            usleep(max($delay, 25) * 1000);
        }

        $output->writeln(sprintf('%s pool completed with %d worker waves.', $stage, $waves));

        return Command::SUCCESS;
    }

    /**
     * @param array<array-key,scalar> $scheduler
     */
    private function schedulerInt(
        array $scheduler,
        string $key,
        int $default,
        bool $allowZero = false,
    ): int {
        $value = $scheduler[$key] ?? $default;
        if (!is_int($value)) {
            return $default;
        }

        if (($allowZero && $value >= 0) || (!$allowZero && $value > 0)) {
            return $value;
        }

        return $default;
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
