<?php

declare(strict_types=1);

namespace PrivacyEvidence\Command;

use PrivacyEvidence\Browser\PlaywrightBrowserProvider;
use PrivacyEvidence\Pipeline\DefaultDetectorRegistry;
use PrivacyEvidence\Pipeline\DefaultProfileRegistry;
use PrivacyEvidence\Pipeline\PipelineConfig;
use PrivacyEvidence\Run\ResearchRun;
use PrivacyEvidence\Run\RunManifestWriter;
use PrivacyEvidence\Runtime\GitRevision;
use PrivacyEvidence\Runtime\RuntimeFactory;
use PrivacyEvidence\Source\DatasetSourceFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Process\Process;

final class RunCommand extends Command
{
    public function __construct(private readonly string $projectRoot)
    {
        parent::__construct('run');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Start and execute a research run for a canonical CSV or JSON dataset.')
            ->addArgument('dataset', InputArgument::REQUIRED)
            ->addOption(
                'max-jobs',
                null,
                InputOption::VALUE_REQUIRED,
                'Maximum jobs to process before leaving the run interrupted; 0 means unlimited.',
                '0',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $argument = $input->getArgument('dataset');
        if (!is_string($argument) || $argument === '') {
            $output->writeln('<error>Dataset path must be a non-empty string.</error>');

            return Command::INVALID;
        }

        /** @psalm-suppress MixedAssignment Symfony InputInterface returns mixed by contract. */
        $maxJobsOption = $input->getOption('max-jobs');
        $maxJobs = filter_var($maxJobsOption, FILTER_VALIDATE_INT);
        if (!is_int($maxJobs) || $maxJobs < 0) {
            $output->writeln('<error>max-jobs must be a non-negative integer.</error>');

            return Command::INVALID;
        }

        $source = DatasetSourceFactory::fromPath($argument);
        $detectors = DefaultDetectorRegistry::create();
        $profiles = DefaultProfileRegistry::create();

        $lockPath = $this->projectRoot . '/composer.lock';
        $lockHash = is_file($lockPath) ? hash_file('sha256', $lockPath) : false;

        $browserLockPath = $this->projectRoot . '/browser/package-lock.json';
        $browserLockHash = is_file($browserLockPath) ? hash_file('sha256', $browserLockPath) : false;

        $nodeVersion = 'unavailable';
        $node = new Process(['node', '--version']);
        $node->setTimeout(5.0);
        $node->run();
        if ($node->isSuccessful()) {
            $nodeVersion = trim($node->getOutput());
        }

        $playwrightVersion = 'unknown';
        $browserPackagePath = $this->projectRoot . '/browser/package.json';
        if (is_file($browserPackagePath)) {
            /** @var mixed $browserPackage */
            $browserPackage = json_decode(
                (string) file_get_contents($browserPackagePath),
                true,
            );
            if (is_array($browserPackage)) {
                /** @var mixed $dependencies */
                $dependencies = $browserPackage['dependencies'] ?? [];
                /** @var mixed $devDependencies */
                $devDependencies = $browserPackage['devDependencies'] ?? [];
                if (is_array($dependencies)) {
                    /** @var mixed $candidate */
                    $candidate = $dependencies['playwright'] ?? null;
                    if (is_string($candidate)) {
                        $playwrightVersion = $candidate;
                    }
                }
                if ($playwrightVersion === 'unknown' && is_array($devDependencies)) {
                    /** @var mixed $candidate */
                    $candidate = $devDependencies['playwright'] ?? null;
                    if (is_string($candidate)) {
                        $playwrightVersion = $candidate;
                    }
                }
            }
        }

        /** @var array<string,string> $versions */
        $versions = [
            'protocol' => '0.1.0-draft',
            'schema' => '0.1.0-draft',
            'php' => PHP_VERSION,
            'composer-lock-sha256' => is_string($lockHash) ? $lockHash : 'missing',
            'browser-lock-sha256' => is_string($browserLockHash) ? $browserLockHash : 'missing',
            'node' => $nodeVersion,
            'os-family' => PHP_OS_FAMILY,
            'kernel' => php_uname('s') . ' ' . php_uname('r'),
            'browser-backend' => 'playwright',
            'playwright-package' => $playwrightVersion,
        ];
        foreach ($detectors->detectors as $detector) {
            $versions['detector:' . $detector->name()] = $detector->version();
        }
        foreach ($profiles->profiles as $profile) {
            $versions['profile:' . $profile->id()] = $profile->version();
        }

        $worker = $this->projectRoot . '/browser/src/worker.mjs';
        $browser = is_file($worker) && is_dir($this->projectRoot . '/browser/node_modules/playwright')
            ? new PlaywrightBrowserProvider($worker)
            : null;

        $run = new ResearchRun(
            id: Uuid::v7()->toRfc4122(),
            startedAt: gmdate(DATE_ATOM),
            gitCommit: GitRevision::detect($this->projectRoot),
            datasetHash: $source->snapshot()->sha256,
            protocolVersion: '0.1.0-draft',
            versions: $versions,
            configuration: [
                'sourceId' => $source->sourceId(),
                'browserEscalation' => $browser !== null,
                'maxJobsPerInvocation' => $maxJobs,
                'scheduler' => [
                    'recommendedHttpWorkers' => 8,
                    'recommendedBrowserWorkers' => 2,
                    'perHostConcurrency' => 2,
                    'minHostDelayMs' => 250,
                ],
                'crawlBudget' => [
                    'maxPages' => 20,
                    'maxDepth' => 3,
                    'maxBytes' => 5000000,
                    'maxDurationSeconds' => 60,
                    'maxBrowserPages' => 3,
                ],
            ],
        );

        $runtime = RuntimeFactory::create($this->projectRoot);
        $pipeline = RuntimeFactory::pipeline(
            $runtime,
            new PipelineConfig(
                maxJobsPerInvocation: $maxJobs,
                enableBrowserEscalation: $browser !== null,
            ),
            $browser,
        );
        $pipeline->start($run, $source);
        $pipeline->execute($run->id);

        $status = $runtime->runs->status($run->id) ?? throw new \RuntimeException(
            'Run status disappeared after execution.',
        );
        (new RunManifestWriter())->write(
            $run,
            $this->projectRoot . '/data/derived/runs/' . $run->id . '/manifest.json',
            $status,
        );

        $output->writeln($run->id);

        return Command::SUCCESS;
    }
}
