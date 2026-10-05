<?php

declare(strict_types=1);

namespace PrivacyEvidence\Command;

use PrivacyEvidence\Analysis\RegulatoryAnalysisService;
use PrivacyEvidence\Browser\PlaywrightBrowserProvider;
use PrivacyEvidence\Pipeline\DefaultProfileRegistry;
use PrivacyEvidence\Pipeline\PipelineConfig;
use PrivacyEvidence\Run\RunManifestWriter;
use PrivacyEvidence\Run\RunStatus;
use PrivacyEvidence\Runtime\RuntimeFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ResumeCommand extends Command
{
    public function __construct(private readonly string $projectRoot)
    {
        parent::__construct('run:resume');
    }

    protected function configure(): void
    {
        $this->setDescription('Resume a persisted interrupted run.')
            ->addArgument('run-id', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $value = $input->getArgument('run-id');
        if (!is_string($value) || $value === '') {
            return Command::INVALID;
        }

        $runtime = RuntimeFactory::create($this->projectRoot);
        $run = $runtime->runs->get($value);
        if ($run === null) {
            $output->writeln('<error>Unknown run.</error>');

            return Command::FAILURE;
        }

        $worker = $this->projectRoot . '/browser/src/worker.mjs';
        $browser = is_file($worker) && is_dir($this->projectRoot . '/browser/node_modules/playwright')
            ? new PlaywrightBrowserProvider($worker)
            : null;

        RuntimeFactory::pipeline(
            $runtime,
            new PipelineConfig(enableBrowserEscalation: $browser !== null),
            $browser,
        )->resume($value);

        $status = $runtime->runs->status($value) ?? throw new \RuntimeException(
            'Run status disappeared after resume.',
        );
        if ($status === RunStatus::Completed) {
            (new RegulatoryAnalysisService(
                $runtime->observations,
                DefaultProfileRegistry::create(),
                runs: $runtime->runs,
            ))->analyze($value);
        }

        (new RunManifestWriter())->write(
            $run,
            $this->projectRoot . '/data/derived/runs/' . $value . '/manifest.json',
            $status,
        );

        return $status === RunStatus::Failed
            ? Command::FAILURE
            : Command::SUCCESS;
    }
}
