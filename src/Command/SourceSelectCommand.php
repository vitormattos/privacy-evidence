<?php

declare(strict_types=1);

namespace PrivacyEvidence\Command;

use PrivacyEvidence\Source\DatasetSourceFactory;
use PrivacyEvidence\Source\ResourceType;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class SourceSelectCommand extends Command
{
    public function __construct()
    {
        parent::__construct('source:select');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Select all resources of a classified type from a canonical dataset.')
            ->addArgument('dataset', InputArgument::REQUIRED)
            ->addArgument('output', InputArgument::REQUIRED)
            ->addOption(
                'type',
                null,
                InputOption::VALUE_REQUIRED,
                'Resource type to select.',
                ResourceType::InstitutionalWebsite->value,
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dataset = $input->getArgument('dataset');
        $target = $input->getArgument('output');
        /** @psalm-suppress MixedAssignment Symfony InputInterface returns mixed by contract. */
        $typeValue = $input->getOption('type');

        if (
            !is_string($dataset) || $dataset === ''
            || !is_string($target) || $target === ''
            || !is_string($typeValue) || $typeValue === ''
        ) {
            return Command::INVALID;
        }

        $type = ResourceType::tryFrom($typeValue);
        if ($type === null) {
            $output->writeln(sprintf('<error>Unknown resource type: %s</error>', $typeValue));

            return Command::INVALID;
        }

        try {
            $source = DatasetSourceFactory::fromPath($dataset);
            $selected = [];
            $metadataKeys = [];

            foreach ($source->resources() as $resource) {
                if ($resource->type !== $type) {
                    continue;
                }

                foreach (array_keys($resource->metadata) as $key) {
                    $metadataKeys[$key] = true;
                }
                $selected[] = $resource;
            }

            $metadataColumns = array_keys($metadataKeys);
            sort($metadataColumns, SORT_STRING);

            $directory = dirname($target);
            if ($directory !== '.' && !is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
                throw new \RuntimeException('Unable to create selection output directory.');
            }

            $handle = fopen($target, 'wb');
            if ($handle === false) {
                throw new \RuntimeException('Unable to create selected dataset CSV.');
            }

            try {
                fputcsv($handle, ['id', 'name', 'url', ...$metadataColumns], ',', '"', '');
                foreach ($selected as $resource) {
                    $row = [
                        $resource->id,
                        $resource->name,
                        $resource->normalizedUrl ?? $resource->sourceValue,
                    ];
                    foreach ($metadataColumns as $key) {
                        $row[] = $resource->metadata[$key] ?? '';
                    }
                    fputcsv($handle, $row, ',', '"', '');
                }
            } finally {
                fclose($handle);
            }

            $datasetSha256 = hash_file('sha256', $target);
            if (!is_string($datasetSha256)) {
                throw new \RuntimeException('Unable to hash selected dataset.');
            }

            $provenance = [
                'schemaVersion' => '1.0.0',
                'producer' => 'privacy-evidence-source-selector',
                'producerVersion' => '1.0.0',
                'inputDataset' => [
                    'sourceId' => $source->sourceId(),
                    'sha256' => $source->snapshot()->sha256,
                ],
                'selection' => [
                    'resourceType' => $type->value,
                    'selected' => count($selected),
                ],
                'dataset' => [
                    'path' => $target,
                    'format' => 'privacy-evidence-csv-v1',
                    'sha256' => $datasetSha256,
                ],
            ];

            file_put_contents(
                $target . '.provenance.json',
                json_encode($provenance, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL,
                LOCK_EX,
            );

            $output->writeln(json_encode(
                $provenance,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ));

            return Command::SUCCESS;
        } catch (\InvalidArgumentException | \RuntimeException | \JsonException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');

            return Command::FAILURE;
        }
    }
}
