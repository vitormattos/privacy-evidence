<?php

declare(strict_types=1);

namespace PrivacyEvidence\Command;

use PrivacyEvidence\Experiment\Rubix\RubixSignalClassifier;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class MlInferSignalCommand extends Command
{
    public function __construct()
    {
        parent::__construct('experiment:ml:infer-signal');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('EXPERIMENTAL: infer one candidate privacy-evidence signal with a saved PHP-native model.')
            ->addArgument('artifact', InputArgument::REQUIRED, 'RBX model-artifact path.')
            ->addArgument('text', InputArgument::REQUIRED, 'Text segment to classify.')
            ->addOption('threshold', null, InputOption::VALUE_REQUIRED, 'Positive candidate threshold.', '0.5');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $artifact = $this->requiredString($input, 'artifact');
            $text = $this->requiredString($input, 'text');
            $threshold = $input->getOption('threshold');

            if (!is_string($threshold) || !is_numeric($threshold)) {
                throw new \InvalidArgumentException('Threshold must be numeric.');
            }

            $prediction = RubixSignalClassifier::load($artifact)->predict($text, (float) $threshold);

            $output->writeln(json_encode(
                [
                    'status' => 'experimental',
                    'prediction' => $prediction->toArray(),
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ));

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
