<?php

declare(strict_types=1);

namespace PrivacyEvidence\Command;

use PrivacyEvidence\Review\ReviewMaterial;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class ReviewHtmlCommand extends Command
{
    public function __construct(private readonly string $projectRoot)
    {
        parent::__construct('review:html');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Generate a self-contained offline HTML reviewer from an annotation package.')
            ->addArgument('package', InputArgument::REQUIRED)
            ->addArgument('output', InputArgument::REQUIRED)
            ->addOption('context-dir', null, InputOption::VALUE_REQUIRED, 'Run export directory containing resources.json and documents.json.')
            ->addOption('artifacts-dir', null, InputOption::VALUE_REQUIRED, 'Directory of original SHA-256 .bin artifacts.')
            ->addOption('test-mode', null, InputOption::VALUE_NONE, 'Test all form cases without producing human annotations.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $packagePath = $input->getArgument('package');
        $outputPath = $input->getArgument('output');

        if (!is_string($packagePath) || $packagePath === '') {
            $output->writeln('<error>Annotation package path is required.</error>');

            return Command::INVALID;
        }
        if (!is_file($packagePath)) {
            $output->writeln(sprintf('<error>Annotation package not found: %s</error>', $packagePath));

            return Command::INVALID;
        }
        if (!is_string($outputPath) || $outputPath === '') {
            $output->writeln('<error>Output HTML path is required.</error>');

            return Command::INVALID;
        }

        /** @var mixed $decoded */
        $decoded = json_decode((string) file_get_contents($packagePath), true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($decoded) || !isset($decoded['cases']) || !is_array($decoded['cases'])) {
            return Command::INVALID;
        }

        $contextDirectory = $input->getOption('context-dir');
        $artifactDirectory = $input->getOption('artifacts-dir');
        if ($contextDirectory !== null && !is_string($contextDirectory)) {
            return Command::INVALID;
        }
        if ($artifactDirectory !== null && !is_string($artifactDirectory)) {
            return Command::INVALID;
        }
        $contextDirectory ??= dirname(dirname($packagePath));
        $artifactDirectory ??= $this->projectRoot . '/data/raw/artifacts';
        if (!isset($decoded['reviewDocuments']) || $input->getOption('context-dir') !== null) {
            /** @var array<string, mixed> $decoded */
            $decoded = (new ReviewMaterial(
                $this->records($contextDirectory . '/resources.json'),
                $this->records($contextDirectory . '/documents.json'),
                $artifactDirectory,
            ))->enrich($decoded);
        }

        $testMode = $input->getOption('test-mode') === true;

        $templatePath = $this->projectRoot . '/resources/review/reviewer.html';
        if (!is_file($templatePath)) {
            $output->writeln('<error>Reviewer HTML template not found.</error>');

            return Command::FAILURE;
        }

        $packageJson = json_encode(
            $decoded,
            JSON_UNESCAPED_SLASHES
            | JSON_HEX_TAG
            | JSON_HEX_AMP
            | JSON_HEX_APOS
            | JSON_HEX_QUOT
            | JSON_THROW_ON_ERROR,
        );

        $configJson = json_encode([
            'testMode' => $testMode,
            'packageHash' => hash('sha256', $packageJson),
        ], JSON_THROW_ON_ERROR);

        $html = strtr((string) file_get_contents($templatePath), [
            '__PACKAGE_JSON__' => $packageJson,
            '__REVIEW_CONFIG_JSON__' => $configJson,
        ]);

        $directory = dirname($outputPath);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create reviewer HTML directory.');
        }

        file_put_contents($outputPath, $html);

        $output->writeln(json_encode([
            'path' => $outputPath,
            'cases' => count($decoded['cases']),
            'offline' => true,
            'testMode' => $testMode,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return Command::SUCCESS;
    }
    /** @return list<array<string, mixed>> */
    private function records(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }
        /** @var mixed $decoded */
        $decoded = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($decoded) || !array_is_list($decoded)) {
            throw new \InvalidArgumentException('Run context must be a list of records.');
        }
        $records = [];
        foreach ($decoded as $record) {
            if (!is_array($record)) {
                throw new \InvalidArgumentException('Run context record must be an object.');
            }
            /** @var array<string, mixed> $record */
            $records[] = $record;
        }

        return $records;
    }

}
