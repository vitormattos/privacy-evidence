<?php

declare(strict_types=1);

namespace PrivacyEvidence\Command;

use PrivacyEvidence\Analysis\MeasurementAttritionExporter;
use PrivacyEvidence\Analysis\RunExporter;
use PrivacyEvidence\Runtime\RuntimeFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ReportCommand extends Command
{
    public function __construct(private readonly string $projectRoot)
    {
        parent::__construct('report');
    }

    protected function configure(): void
    {
        $this->setDescription('Generate machine-readable exports and a human-readable run report.')
            ->addArgument('run-id', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $value = $input->getArgument('run-id');
        if (!is_string($value) || $value === '') {
            return Command::INVALID;
        }

        $directory = $this->projectRoot . '/data/exports/' . $value;
        (new RunExporter(RuntimeFactory::create($this->projectRoot)))->export($value, $directory);
        (new MeasurementAttritionExporter())->export($directory);
        $output->writeln($directory);

        return Command::SUCCESS;
    }
}
