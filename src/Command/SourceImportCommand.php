<?php

declare(strict_types=1);

namespace PrivacyEvidence\Command;

use PrivacyEvidence\Source\DatasetSourceFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class SourceImportCommand extends Command
{
    public function __construct()
    {
        parent::__construct('source:import');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Normalize and inspect a source dataset without running acquisition.')
            ->addArgument('dataset', InputArgument::REQUIRED, 'Canonical CSV or JSON dataset.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $argument = $input->getArgument('dataset');
        if (!is_string($argument) || $argument === '') {
            $output->writeln('<error>Dataset path must be a non-empty string.</error>');

            return Command::INVALID;
        }

        $source = DatasetSourceFactory::fromPath($argument);

        $output->writeln(json_encode([
            'sourceId' => $source->sourceId(),
            'snapshot' => [
                'sha256' => $source->snapshot()->sha256,
                'capturedAt' => $source->snapshot()->capturedAt,
                'location' => $source->snapshot()->location,
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        foreach ($source->resources() as $resource) {
            $output->writeln(
                json_encode(
                    $resource->toArray(),
                    JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
                ),
            );
        }

        return Command::SUCCESS;
    }
}
