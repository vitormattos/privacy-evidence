<?php

declare(strict_types=1);

namespace PrivacyEvidence\Command;

use PrivacyEvidence\Experiment\Dataset\ClaudinhaCorpusLoader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class DatasetPrepareClaudinhaCommand extends Command
{
    public function __construct(
        private readonly ClaudinhaCorpusLoader $loader = new ClaudinhaCorpusLoader(),
    ) {
        parent::__construct('experiment:dataset:prepare-claudinha');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Prepare the external Claudinha LGPD corpus for experimental ML development.')
            ->addArgument('source', InputArgument::REQUIRED, 'Path to the local upstream CSV.')
            ->addArgument('output-directory', InputArgument::REQUIRED, 'Directory for canonical JSONL and manifest.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $source = $input->getArgument('source');
        $outputDirectory = $input->getArgument('output-directory');

        if (
            !is_string($source) || trim($source) === ''
            || !is_string($outputDirectory) || trim($outputDirectory) === ''
        ) {
            return Command::INVALID;
        }

        try {
            $manifest = $this->loader->prepare($source, $outputDirectory);
        } catch (\InvalidArgumentException|\RuntimeException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');

            return Command::FAILURE;
        }

        $output->writeln(json_encode(
            $manifest,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ));

        return Command::SUCCESS;
    }
}
