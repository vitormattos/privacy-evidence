<?php

declare(strict_types=1);

namespace PrivacyEvidence\Command;

use PrivacyEvidence\Analysis\RegulatoryAnalysisService;
use PrivacyEvidence\Pipeline\DefaultProfileRegistry;
use PrivacyEvidence\Runtime\RuntimeFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class AnalyzeCommand extends Command
{
    public function __construct(private readonly string $projectRoot)
    {
        parent::__construct('analyze');
    }

    protected function configure(): void
    {
        $this->setDescription('Apply versioned regulatory profiles to persisted generic evidence.')
            ->addArgument('run-id', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $value = $input->getArgument('run-id');
        if (!is_string($value) || $value === '') {
            return Command::INVALID;
        }

        $runtime = RuntimeFactory::create($this->projectRoot);
        if ($runtime->runs->get($value) === null) {
            $output->writeln('<error>Unknown run.</error>');

            return Command::FAILURE;
        }

        (new RegulatoryAnalysisService(
            $runtime->observations,
            DefaultProfileRegistry::create(),
            runs: $runtime->runs,
        ))->analyze($value);

        return Command::SUCCESS;
    }
}
