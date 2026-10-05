<?php

declare(strict_types=1);

namespace PrivacyEvidence\Command;

use PrivacyEvidence\Source\DatasetSourceFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class SourceStatsCommand extends Command
{
    public function __construct()
    {
        parent::__construct('source:stats');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Summarize deterministic classification and URL quality for a canonical dataset.')
            ->addArgument('dataset', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dataset = $input->getArgument('dataset');
        if (!is_string($dataset) || $dataset === '') {
            return Command::INVALID;
        }

        try {
            $source = DatasetSourceFactory::fromPath($dataset);
            $total = 0;
            $normalized = 0;
            $byType = [];
            $byRule = [];
            /** @var array<string,list<string>> $resourcesByUrl */
            $resourcesByUrl = [];

            foreach ($source->resources() as $resource) {
                $total++;
                $type = $resource->type->value;
                $byType[$type] = ($byType[$type] ?? 0) + 1;
                $byRule[$resource->classificationRule] = ($byRule[$resource->classificationRule] ?? 0) + 1;

                if ($resource->normalizedUrl !== null) {
                    $normalized++;
                    $resourcesByUrl[$resource->normalizedUrl][] = $resource->id;
                }
            }

            ksort($byType);
            ksort($byRule);
            ksort($resourcesByUrl);

            $duplicateGroups = [];
            $resourcesInDuplicateGroups = 0;
            $duplicateExcessResources = 0;
            foreach ($resourcesByUrl as $url => $resourceIds) {
                $count = count($resourceIds);
                if ($count < 2) {
                    continue;
                }

                sort($resourceIds, SORT_STRING);
                $duplicateGroups[] = [
                    'url' => $url,
                    'count' => $count,
                    'resourceIds' => $resourceIds,
                ];
                $resourcesInDuplicateGroups += $count;
                $duplicateExcessResources += $count - 1;
            }

            $summary = [
                'schemaVersion' => '1.0.0',
                'sourceId' => $source->sourceId(),
                'snapshotSha256' => $source->snapshot()->sha256,
                'resources' => [
                    'total' => $total,
                    'withNormalizedUrl' => $normalized,
                    'withoutNormalizedUrl' => $total - $normalized,
                ],
                'classification' => [
                    'byType' => $byType,
                    'byRule' => $byRule,
                ],
                'normalizedUrls' => [
                    'unique' => count($resourcesByUrl),
                    'duplicateGroups' => count($duplicateGroups),
                    'resourcesInDuplicateGroups' => $resourcesInDuplicateGroups,
                    'duplicateExcessResources' => $duplicateExcessResources,
                ],
                'duplicates' => $duplicateGroups,
            ];

            $output->writeln(json_encode(
                $summary,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ));

            return Command::SUCCESS;
        } catch (\InvalidArgumentException | \RuntimeException | \JsonException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');

            return Command::FAILURE;
        }
    }
}
