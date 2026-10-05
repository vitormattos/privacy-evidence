<?php

declare(strict_types=1);

namespace PrivacyEvidence\Command;

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

        $html = str_replace(
            ['__PACKAGE_JSON__', '__REVIEW_CONFIG_JSON__'],
            [$packageJson, $configJson],
            (string) file_get_contents($templatePath),
        );

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
}

