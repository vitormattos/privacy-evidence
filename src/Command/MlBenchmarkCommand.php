<?php

declare(strict_types=1);

namespace PrivacyEvidence\Command;

use PrivacyEvidence\Experiment\Benchmark\MlBenchmarkRunner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class MlBenchmarkCommand extends Command
{
    public function __construct(private readonly MlBenchmarkRunner $runner = new MlBenchmarkRunner())
    {
        parent::__construct('experiment:ml:benchmark');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('EXPERIMENTAL: compare rules, internal Naive Bayes and Rubix on one held-out partition.')
            ->addArgument('training', InputArgument::REQUIRED, 'Prepared training JSONL partition.')
            ->addArgument('evaluation', InputArgument::REQUIRED, 'Prepared held-out JSONL partition.')
            ->addArgument('manifest', InputArgument::REQUIRED, 'Prepared dataset manifest.')
            ->addArgument('trained-at', InputArgument::REQUIRED, 'Explicit UTC model-training timestamp.')
            ->addArgument('output', InputArgument::REQUIRED, 'Output JSON benchmark report.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $training = $this->requiredString($input, 'training');
            $evaluation = $this->requiredString($input, 'evaluation');
            $manifest = $this->requiredString($input, 'manifest');
            $trainedAt = $this->requiredString($input, 'trained-at');
            $path = $this->requiredString($input, 'output');

            $report = $this->runner->run($training, $evaluation, $manifest, $trainedAt);
            $directory = dirname($path);
            if ($directory !== '.' && !is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
                throw new \RuntimeException('Unable to create benchmark output directory.');
            }

            $encoded = json_encode(
                $report,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            ) . PHP_EOL;
            if (file_put_contents($path, $encoded, LOCK_EX) === false) {
                throw new \RuntimeException('Unable to write benchmark report.');
            }

            $output->writeln($encoded);

            return Command::SUCCESS;
        } catch (\InvalidArgumentException | \JsonException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');

            return Command::INVALID;
        } catch (\RuntimeException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');

            return Command::FAILURE;
        }
    }

    private function requiredString(InputInterface $input, string $name): string
    {
        $value = $input->getArgument($name);
        if (!is_string($value) || trim($value) === '') {
            throw new \InvalidArgumentException('Argument ' . $name . ' cannot be empty.');
        }

        return $value;
    }
}
