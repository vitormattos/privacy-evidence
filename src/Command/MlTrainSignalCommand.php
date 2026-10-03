<?php

declare(strict_types=1);

namespace PrivacyEvidence\Command;

use PrivacyEvidence\Evidence\EvidenceType;
use PrivacyEvidence\Experiment\Rubix\ExternalSignalTrainingSet;
use PrivacyEvidence\Experiment\Rubix\RubixSignalClassifier;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class MlTrainSignalCommand extends Command
{
    public function __construct(
        private readonly ExternalSignalTrainingSet $trainingSet = new ExternalSignalTrainingSet(),
    ) {
        parent::__construct('experiment:ml:train-signal');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('EXPERIMENTAL: train one PHP-native privacy-evidence signal model.')
            ->addArgument('partition', InputArgument::REQUIRED, 'Prepared external training partition JSONL.')
            ->addArgument('manifest', InputArgument::REQUIRED, 'Prepared external dataset manifest JSON.')
            ->addArgument('signal', InputArgument::REQUIRED, 'Privacy Evidence signal identifier.')
            ->addArgument('artifact', InputArgument::REQUIRED, 'Output RBX model-artifact path.')
            ->addArgument('trained-at', InputArgument::REQUIRED, 'Explicit UTC timestamp, e.g. 2026-10-03T12:00:00Z.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $partition = $this->requiredString($input, 'partition');
            $manifest = $this->requiredString($input, 'manifest');
            $signalValue = $this->requiredString($input, 'signal');
            $artifact = $this->requiredString($input, 'artifact');
            $trainedAt = $this->requiredString($input, 'trained-at');

            $signal = EvidenceType::tryFrom($signalValue);
            if (!$signal instanceof EvidenceType) {
                $output->writeln('<error>Unsupported Privacy Evidence signal.</error>');

                return Command::INVALID;
            }

            $loaded = $this->trainingSet->load($partition, $manifest, $signal, $trainedAt);
            $model = RubixSignalClassifier::train(
                $signal,
                $loaded['examples'],
                $loaded['provenance'],
            );
            $artifactSha256 = $model->save($artifact);

            $output->writeln(json_encode(
                [
                    'status' => 'experimental',
                    'artifact' => $artifact,
                    'artifactSha256' => $artifactSha256,
                    'model' => $model->metadata()->toArray(),
                    'trainingSamples' => count($loaded['examples']),
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
