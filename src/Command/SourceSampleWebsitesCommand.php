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

final class SourceSampleWebsitesCommand extends Command
{
    public function __construct()
    {
        parent::__construct('source:sample-websites');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Create a deterministic sample of institutional websites from any supported source.')
            ->addArgument('dataset', InputArgument::REQUIRED)
            ->addArgument('output', InputArgument::REQUIRED)
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Number of websites to select.', '8')
            ->addOption('seed', null, InputOption::VALUE_REQUIRED, 'Deterministic sampling seed.', 'privacy-evidence-demo-v1');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dataset = $input->getArgument('dataset');
        $target = $input->getArgument('output');
        /** @psalm-suppress MixedAssignment Symfony InputInterface returns mixed by contract. */
        $limitValue = $input->getOption('limit');
        /** @psalm-suppress MixedAssignment Symfony InputInterface returns mixed by contract. */
        $seed = $input->getOption('seed');

        if (!is_string($dataset) || $dataset === '' || !is_string($target) || $target === '') {
            return Command::INVALID;
        }
        if (!is_string($seed) || $seed === '') {
            return Command::INVALID;
        }

        $limit = filter_var($limitValue, FILTER_VALIDATE_INT);
        if (!is_int($limit) || $limit < 1) {
            return Command::INVALID;
        }

        try {
            $source = DatasetSourceFactory::fromPath($dataset);
            /** @var list<array{score:string,id:string,name:string,url:string}> $candidates */
            $candidates = [];

            foreach ($source->resources() as $resource) {
                if (
                    $resource->type !== ResourceType::InstitutionalWebsite
                    || $resource->normalizedUrl === null
                ) {
                    continue;
                }

                $candidates[] = [
                    'score' => hash('sha256', $seed . "\0" . $resource->id),
                    'id' => $resource->id,
                    'name' => $resource->name,
                    'url' => $resource->normalizedUrl,
                ];
            }

            usort(
                $candidates,
                static fn (array $a, array $b): int => $a['score'] <=> $b['score'],
            );
            $selected = array_slice($candidates, 0, $limit);

            $directory = dirname($target);
            if ($directory !== '.' && !is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
                throw new \RuntimeException('Unable to create sample output directory.');
            }

            $handle = fopen($target, 'wb');
            if ($handle === false) {
                throw new \RuntimeException('Unable to create sample CSV.');
            }

            try {
                fputcsv($handle, ['id', 'name', 'url'], ',', '"', '');
                foreach ($selected as $row) {
                    fputcsv($handle, [$row['id'], $row['name'], $row['url']], ',', '"', '');
                }
            } finally {
                fclose($handle);
            }

            $sampleSha256 = hash_file('sha256', $target);
            if (!is_string($sampleSha256)) {
                throw new \RuntimeException('Unable to hash sampled dataset.');
            }

            $provenance = [
                'schemaVersion' => '1.0.0',
                'producer' => 'privacy-evidence-source-sampler',
                'producerVersion' => '1.0.0',
                'inputDataset' => [
                    'sourceId' => $source->sourceId(),
                    'sha256' => $source->snapshot()->sha256,
                ],
                'sampling' => [
                    'strategy' => 'sha256-seeded-order-v1',
                    'resourceClassifier' => 'institutional_website',
                    'seed' => $seed,
                    'limit' => $limit,
                    'eligible' => count($candidates),
                    'selected' => count($selected),
                ],
                'dataset' => [
                    'path' => $target,
                    'format' => 'privacy-evidence-csv-v1',
                    'sha256' => $sampleSha256,
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
