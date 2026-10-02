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
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Uid\Uuid;

final class RunCommand extends Command
{
    public function __construct(private readonly string $projectRoot)
    {
        parent::__construct('run');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Start and execute a research run for a CSV, JSON, or IPB HTML source.')
            ->addArgument('dataset', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $argument = $input->getArgument('dataset');
        if (!is_string($argument) || $argument === '') {
            $output->writeln('<error>Dataset path must be a non-empty string.</error>');

            return Command::INVALID;
        }

        $source = DatasetSourceFactory::fromPath($argument);
        $detectors = DefaultDetectorRegistry::create();
        $profiles = DefaultProfileRegistry::create();

        $lockPath = $this->projectRoot . '/composer.lock';
        $lockHash = is_file($lockPath) ? hash_file('sha256', $lockPath) : false;
        $versions = [
            'protocol' => '0.1.0-draft',
            'schema' => '0.1.0-draft',
            'php' => PHP_VERSION,
            'composer-lock-sha256' => is_string($lockHash) ? $lockHash : 'missing',
            'browser-backend' => 'playwright',
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
            new PipelineConfig(enableBrowserEscalation: $browser !== null),
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
