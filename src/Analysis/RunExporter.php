<?php

declare(strict_types=1);

namespace PrivacyEvidence\Analysis;

use PrivacyEvidence\Core\Value;
use PrivacyEvidence\Evidence\PrivacyEvidence;
use PrivacyEvidence\Runtime\RuntimeContext;

final readonly class RunExporter
{
    public const SCHEMA_VERSION = '1.0.0';

    public function __construct(private RuntimeContext $runtime)
    {
    }

    public function export(string $runId, string $directory): void
    {
        $run = $this->runtime->runs->get($runId);
        if ($run === null) {
            throw new \InvalidArgumentException(sprintf('Unknown run %s.', $runId));
        }

        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create export directory.');
        }

        $status = $this->runtime->runs->status($runId) ?? throw new \RuntimeException(
            'Run has no persisted status.',
        );

        $resources = $this->runtime->observations->resourceRecords($runId);
        $documents = $this->runtime->observations->documentRecords($runId);
        $evidenceObjects = $this->runtime->observations->evidence($runId);
        $evidence = array_map(
            static fn (PrivacyEvidence $item): array => $item->toArray(),
            $evidenceObjects,
        );
        $reviews = $this->runtime->reviews->decisions($runId);
        $profiles = $this->runtime->observations->profileResults($runId);
        $telemetry = $this->runtime->runs->telemetry($runId);
        $counts = $this->runtime->observations->counts($runId);

        $analysis = $this->analysis(
            runId: $runId,
            generatedAt: $run->startedAt,
            resources: $resources,
            evidence: $evidenceObjects,
            telemetry: $telemetry,
            counts: $counts,
        );

        $this->json($directory . '/manifest.json', $run->toArray($status));
        $this->json($directory . '/resources.json', $resources);
        $this->json($directory . '/documents.json', $documents);
        $this->json($directory . '/evidence.json', $evidence);
        $this->json($directory . '/reviews.json', $reviews);
        $this->json($directory . '/profiles.json', $profiles);
        $this->json($directory . '/telemetry.json', $telemetry);
        $this->json($directory . '/counts.json', $counts);
        $this->json($directory . '/analysis.json', $analysis);

        $this->csv(
            $directory . '/resources.csv',
            ['id', 'name', 'sourceValue', 'normalizedUrl', 'type'],
            $this->resourceRows($resources),
        );
        $this->csv(
            $directory . '/documents.csv',
            [
                'artifactHash',
                'resourceId',
                'requestedUrl',
                'finalUrl',
                'statusCode',
                'mediaType',
                'fetchedAt',
                'acquisitionMode',
                'truncated',
                'bodySize',
            ],
            $this->documentRows($documents),
        );
        $this->csv(
            $directory . '/evidence.csv',
            [
                'id',
                'resourceId',
                'artifactHash',
                'type',
                'state',
                'sourceUrl',
                'detector',
                'detectorVersion',
                'method',
                'confidence',
                'needsReview',
            ],
            $this->evidenceRows($evidenceObjects),
        );

        $this->report(
            $directory . '/report.md',
            $run->id,
            $run->protocolVersion,
            $run->gitCommit,
            $run->datasetHash,
            $analysis,
        );
    }

    /**
     * @param list<array<string,mixed>> $records
     * @return list<list<scalar|null>>
     */
    private function resourceRows(array $records): array
    {
        $rows = [];
        foreach ($records as $record) {
            $rows[] = [
                Value::string($record['id'] ?? null, 'resource.id'),
                Value::string($record['name'] ?? null, 'resource.name'),
                Value::string($record['sourceValue'] ?? null, 'resource.sourceValue'),
                Value::nullableString($record['normalizedUrl'] ?? null, 'resource.normalizedUrl'),
                Value::string($record['type'] ?? null, 'resource.type'),
            ];
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $records
     * @return list<list<scalar|null>>
     */
    private function documentRows(array $records): array
    {
        $rows = [];
        foreach ($records as $record) {
            $rows[] = [
                Value::string($record['artifactHash'] ?? null, 'document.artifactHash'),
                Value::string($record['resourceId'] ?? null, 'document.resourceId'),
                Value::string($record['requestedUrl'] ?? null, 'document.requestedUrl'),
                Value::string($record['finalUrl'] ?? null, 'document.finalUrl'),
                Value::int($record['statusCode'] ?? null, 'document.statusCode'),
                Value::string($record['mediaType'] ?? null, 'document.mediaType'),
                Value::string($record['fetchedAt'] ?? null, 'document.fetchedAt'),
                Value::string($record['acquisitionMode'] ?? null, 'document.acquisitionMode'),
                Value::bool($record['truncated'] ?? null, 'document.truncated') ? '1' : '0',
                Value::int($record['bodySize'] ?? null, 'document.bodySize'),
            ];
        }

        return $rows;
    }

    /**
     * @param list<PrivacyEvidence> $evidence
     * @return list<list<scalar|null>>
     */
    private function evidenceRows(array $evidence): array
    {
        $rows = [];
        foreach ($evidence as $item) {
            $rows[] = [
                $item->id(),
                $item->resourceId,
                $item->artifactHash,
                $item->type->value,
                $item->state->value,
                $item->sourceUrl,
                $item->detector,
                $item->detectorVersion,
                $item->method,
                $item->confidence,
                $item->needsReview ? '1' : '0',
            ];
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $resources
     * @param list<PrivacyEvidence> $evidence
     * @param array<string,int|float|string> $telemetry
     * @param array<string,int> $counts
     * @return array{
     *   schemaVersion:string,
     *   runId:string,
     *   generatedAt:string,
     *   metrics:array{
     *     counts:array<string,int>,
     *     evidenceByType:array<string,array{
     *       eligibleResources:int,
     *       observations:int,
     *       states:array<string,int>
     *     }>
     *   },
     *   failures:array<string,int|float|string>,
     *   performance:array<string,int|float|string>
     * }
     */
    private function analysis(
        string $runId,
        string $generatedAt,
        array $resources,
        array $evidence,
        array $telemetry,
        array $counts,
    ): array {
        $resourceCount = count($resources);

        /** @var array<string,array{eligibleResources:int,observations:int,states:array<string,int>}> $byType */
        $byType = [];

        foreach ($evidence as $item) {
            $type = $item->type->value;
            $state = $item->state->value;

            if (!isset($byType[$type])) {
                $byType[$type] = [
                    'eligibleResources' => $resourceCount,
                    'observations' => 0,
                    'states' => [],
                ];
            }

            $byType[$type]['observations']++;
            $byType[$type]['states'][$state] = ($byType[$type]['states'][$state] ?? 0) + 1;
        }

        ksort($byType);
        foreach ($byType as &$metric) {
            ksort($metric['states']);
        }
        unset($metric);

        $failures = [];
        foreach ($telemetry as $name => $value) {
            if (str_starts_with($name, 'failure.') || str_starts_with($name, 'failed.')) {
                $failures[$name] = $value;
            }
        }
        ksort($failures);

        return [
            'schemaVersion' => self::SCHEMA_VERSION,
            'runId' => $runId,
            'generatedAt' => $generatedAt,
            'metrics' => [
                'counts' => $counts,
                'evidenceByType' => $byType,
            ],
            'failures' => $failures,
            'performance' => $telemetry,
        ];
    }

    /**
     * @param array{
     *   schemaVersion:string,
     *   runId:string,
     *   generatedAt:string,
     *   metrics:array{
     *     counts:array<string,int>,
     *     evidenceByType:array<string,array{
     *       eligibleResources:int,
     *       observations:int,
     *       states:array<string,int>
     *     }>
     *   },
     *   failures:array<string,int|float|string>,
     *   performance:array<string,int|float|string>
     * } $analysis
     */
    private function report(
        string $path,
        string $runId,
        string $protocolVersion,
        string $gitCommit,
        string $datasetHash,
        array $analysis,
    ): void {
        $counts = $analysis['metrics']['counts'];
        $byType = $analysis['metrics']['evidenceByType'];

        $lines = [
            '# Privacy Evidence run ' . $runId,
            '',
            '- Protocol: ' . $protocolVersion,
            '- Git commit: ' . $gitCommit,
            '- Dataset SHA-256: ' . $datasetHash,
            '- Resources: ' . ($counts['resources'] ?? 0),
            '- Documents: ' . ($counts['documents'] ?? 0),
            '- Evidence items: ' . ($counts['evidence'] ?? 0),
            '- Regulatory profile results: ' . ($counts['profile_results'] ?? 0),
            '',
            '## Evidence summary',
            '',
            '| Evidence type | Eligible resources | Observations | States |',
            '| --- | ---: | ---: | --- |',
        ];

        foreach ($byType as $type => $metric) {
            $stateSummary = [];
            foreach ($metric['states'] as $state => $count) {
                $stateSummary[] = $state . '=' . $count;
            }

            $lines[] = sprintf(
                '| %s | %d | %d | %s |',
                $type,
                $metric['eligibleResources'],
                $metric['observations'],
                implode(', ', $stateSummary),
            );
        }

        $lines[] = '';
        $lines[] = '## Interpretation boundary';
        $lines[] = '';
        $lines[] = 'This report summarizes publicly observable privacy evidence. '
            . 'It is not a legal-compliance certification. Missing, unknown, unavailable, '
            . 'excluded and not-applicable observations retain distinct semantics.';
        $lines[] = '';

        if (file_put_contents($path, implode(PHP_EOL, $lines), LOCK_EX) === false) {
            throw new \RuntimeException('Unable to write report.');
        }
    }

    private function json(string $path, mixed $value): void
    {
        $json = json_encode(
            $value,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ) . PHP_EOL;

        if (file_put_contents($path, $json, LOCK_EX) === false) {
            throw new \RuntimeException(sprintf('Unable to write %s.', $path));
        }
    }

    /**
     * @param list<string> $headers
     * @param list<list<scalar|null>> $rows
     */
    private function csv(string $path, array $headers, array $rows): void
    {
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new \RuntimeException(sprintf('Unable to write %s.', $path));
        }

        try {
            fputcsv($handle, $headers, ',', '"', '');
            foreach ($rows as $row) {
                fputcsv(
                    $handle,
                    array_map(
                        static fn (string|int|float|bool|null $value): string =>
                            $value === null ? '' : (string) $value,
                        $row,
                    ),
                    ',',
                    '"',
                    '',
                );
            }
        } finally {
            fclose($handle);
        }
    }
}
