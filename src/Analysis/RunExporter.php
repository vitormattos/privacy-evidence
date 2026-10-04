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
        $profileSummary = $this->profileSummary($resources, $profiles);
        $jobFailures = $this->runtime->jobs->failures($runId);
        $telemetry = $this->runtime->runs->telemetry($runId);
        $events = $this->runtime->runs->events($runId);
        $counts = $this->runtime->observations->counts($runId);

        $analysis = $this->analysis(
            runId: $runId,
            generatedAt: $run->startedAt,
            resources: $resources,
            evidence: $evidenceObjects,
            profiles: $profiles,
            profileSummary: $profileSummary,
            jobFailures: $jobFailures,
            telemetry: $telemetry,
            counts: $counts,
        );

        $this->json($directory . '/manifest.json', $run->toArray($status));
        $this->json($directory . '/resources.json', $resources);
        $this->json($directory . '/documents.json', $documents);
        $this->json($directory . '/evidence.json', $evidence);
        $this->json($directory . '/reviews.json', $reviews);
        $this->json($directory . '/profiles.json', $profiles);
        $this->json($directory . '/profile-summary.json', $profileSummary);
        $this->json($directory . '/failures.json', $jobFailures);
        $this->json($directory . '/telemetry.json', $telemetry);
        $this->json($directory . '/events.json', $events);
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
        $this->csv(
            $directory . '/profiles.csv',
            [
                'resourceId',
                'profile',
                'profileVersion',
                'requirementId',
                'requirementTitle',
                'state',
                'present',
                'absent',
                'unknown',
                'unavailable',
                'notApplicable',
            ],
            $this->profileRows($profiles),
        );
        $this->csv(
            $directory . '/profile-summary.csv',
            [
                'resourceId',
                'name',
                'normalizedUrl',
                'profile',
                'profileVersion',
                'publicEvidenceState',
                'observedSupport',
                'partialObservedSupport',
                'noObservedSupport',
                'indeterminate',
                'unavailable',
                'notApplicable',
                'applicabilityUnknown',
                'totalRequirements',
            ],
            $this->profileSummaryRows($profileSummary),
        );

        $this->report(
            $directory . '/report.md',
            $run->id,
            $run->protocolVersion,
            $run->gitCommit,
            $run->datasetHash,
            $analysis,
            $profileSummary,
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
     * @param list<array<string,mixed>> $records
     * @return list<list<scalar|null>>
     */
    private function profileRows(array $records): array
    {
        $rows = [];
        foreach ($records as $record) {
            $result = $record['result'] ?? null;
            if (!is_array($result)) {
                continue;
            }

            $rows[] = [
                Value::string($record['resourceId'] ?? null, 'profile.resourceId'),
                Value::string($record['profile'] ?? null, 'profile.profile'),
                Value::string($record['profileVersion'] ?? null, 'profile.profileVersion'),
                Value::string($result['id'] ?? null, 'profile.requirementId'),
                $this->stringValue($result['title'] ?? '', ''),
                Value::string($result['state'] ?? null, 'profile.state'),
                $this->stringList($result['present'] ?? []),
                $this->stringList($result['absent'] ?? []),
                $this->stringList($result['unknown'] ?? []),
                $this->stringList($result['unavailable'] ?? []),
                $this->stringList($result['notApplicable'] ?? []),
            ];
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $summary
     * @return list<list<scalar|null>>
     */
    private function profileSummaryRows(array $summary): array
    {
        $rows = [];
        foreach ($summary as $row) {
            /** @psalm-suppress MixedAssignment */
            $countsValue = $row['requirements'] ?? [];
            $counts = is_array($countsValue) ? $countsValue : [];

            $rows[] = [
                Value::string($row['resourceId'] ?? null, 'profileSummary.resourceId'),
                Value::string($row['name'] ?? null, 'profileSummary.name'),
                Value::nullableString($row['normalizedUrl'] ?? null, 'profileSummary.normalizedUrl'),
                Value::string($row['profile'] ?? null, 'profileSummary.profile'),
                Value::string($row['profileVersion'] ?? null, 'profileSummary.profileVersion'),
                Value::string($row['publicEvidenceState'] ?? null, 'profileSummary.publicEvidenceState'),
                $this->intValue($counts['observed_support'] ?? 0),
                $this->intValue($counts['partial_observed_support'] ?? 0),
                $this->intValue($counts['no_observed_support'] ?? 0),
                $this->intValue($counts['indeterminate'] ?? 0),
                $this->intValue($counts['unavailable'] ?? 0),
                $this->intValue($counts['not_applicable'] ?? 0),
                $this->intValue($counts['applicability_unknown'] ?? 0),
                $this->intValue($row['totalRequirements'] ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $resources
     * @param list<array<string,mixed>> $profiles
     * @return list<array<string,mixed>>
     */
    private function profileSummary(array $resources, array $profiles): array
    {
        $resourceById = [];
        foreach ($resources as $resource) {
            /** @psalm-suppress MixedAssignment */
            $id = $resource['id'] ?? null;
            if (is_string($id)) {
                $resourceById[$id] = $resource;
            }
        }

        /** @var array<string,array<string,mixed>> $groups */
        $groups = [];
        foreach ($profiles as $profileResult) {
            $resourceId = $profileResult['resourceId'] ?? null;
            $profile = $profileResult['profile'] ?? null;
            $profileVersion = $profileResult['profileVersion'] ?? null;
            $result = $profileResult['result'] ?? null;

            if (
                !is_string($resourceId)
                || !is_string($profile)
                || !is_string($profileVersion)
                || !is_array($result)
            ) {
                continue;
            }

            $key = $resourceId . '|' . $profile . '|' . $profileVersion;
            if (!isset($groups[$key])) {
                $resource = $resourceById[$resourceId] ?? [];
                $groups[$key] = [
                    'resourceId' => $resourceId,
                    'name' => is_string($resource['name'] ?? null) ? $resource['name'] : $resourceId,
                    'normalizedUrl' => is_string($resource['normalizedUrl'] ?? null)
                        ? $resource['normalizedUrl']
                        : null,
                    'profile' => $profile,
                    'profileVersion' => $profileVersion,
                    'requirements' => [],
                    'totalRequirements' => 0,
                ];
            }

            $state = $result['state'] ?? null;
            if (!is_string($state)) {
                continue;
            }

            /** @psalm-suppress MixedAssignment */
            $countsValue = $groups[$key]['requirements'];
            $counts = is_array($countsValue) ? $countsValue : [];
            $counts[$state] = $this->intValue($counts[$state] ?? 0) + 1;
            $groups[$key]['requirements'] = $counts;
            $groups[$key]['totalRequirements'] = $this->intValue(
                $groups[$key]['totalRequirements'] ?? 0,
            ) + 1;
        }

        $summary = [];
        foreach ($groups as $group) {
            /** @psalm-suppress MixedAssignment */
            $countsValue = $group['requirements'] ?? [];
            $counts = is_array($countsValue) ? $countsValue : [];
            $total = $this->intValue($group['totalRequirements'] ?? 0);

            $observed = $this->intValue($counts['observed_support'] ?? 0);
            $partial = $this->intValue($counts['partial_observed_support'] ?? 0);
            $noSupport = $this->intValue($counts['no_observed_support'] ?? 0);
            $unresolved = $this->intValue($counts['indeterminate'] ?? 0)
                + $this->intValue($counts['unavailable'] ?? 0)
                + $this->intValue($counts['not_applicable'] ?? 0)
                + $this->intValue($counts['applicability_unknown'] ?? 0);

            if ($total > 0 && $observed === $total) {
                $state = 'complete_observed_support';
            } elseif ($total > 0 && $unresolved === $total) {
                $state = 'unavailable_or_indeterminate';
            } elseif ($total > 0 && $noSupport === $total) {
                $state = 'no_observed_support';
            } elseif ($observed > 0 || $partial > 0 || $noSupport > 0) {
                $state = 'mixed_observed_support';
            } else {
                $state = 'unavailable_or_indeterminate';
            }

            $group['publicEvidenceState'] = $state;
            $summary[] = $group;
        }

        usort(
            $summary,
            static fn (array $a, array $b): int =>
                strcmp(
                    Value::string($a['resourceId'] ?? null, 'profileSummary.resourceId'),
                    Value::string($b['resourceId'] ?? null, 'profileSummary.resourceId'),
                )
                ?: strcmp(
                    Value::string($a['profile'] ?? null, 'profileSummary.profile'),
                    Value::string($b['profile'] ?? null, 'profileSummary.profile'),
                ),
        );

        return $summary;
    }

    private function intValue(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value)) {
            return (int) $value;
        }
        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        return 0;
    }

    private function stringValue(mixed $value, string $default): string
    {
        return is_string($value) ? $value : $default;
    }

    private function stringList(mixed $value): string
    {
        if (!is_array($value)) {
            return '';
        }

        $strings = [];
        /** @psalm-suppress MixedAssignment */
        foreach ($value as $item) {
            if (is_string($item)) {
                $strings[] = $item;
            }
        }

        return implode(';', $strings);
    }

    /**
     * @param list<array<string,mixed>> $resources
     * @param list<PrivacyEvidence> $evidence
     * @param list<array<string,mixed>> $profiles
     * @param list<array<string,mixed>> $profileSummary
     * @param list<array{id:string,stage:string,status:string,attempts:int,url:string|null,resourceId:string|null,error:string}> $jobFailures
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
     *     }>,
     *     regulatoryResults:int,
     *     profileSummary:list<array<string,mixed>>
     *   },
     *   failures:array<string,mixed>,
     *   performance:array<string,int|float|string>
     * }
     */
    private function analysis(
        string $runId,
        string $generatedAt,
        array $resources,
        array $evidence,
        array $profiles,
        array $profileSummary,
        array $jobFailures,
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

        $failures = [
            'resourceAcquisitionFailures' => $this->intValue(
                $telemetry['resource_acquisition_failures'] ?? 0,
            ),
            'terminalPipelineFailures' => $this->intValue(
                $telemetry['terminal_failures'] ?? 0,
            ),
            'jobs' => $jobFailures,
        ];

        return [
            'schemaVersion' => self::SCHEMA_VERSION,
            'runId' => $runId,
            'generatedAt' => $generatedAt,
            'metrics' => [
                'counts' => $counts,
                'evidenceByType' => $byType,
                'regulatoryResults' => count($profiles),
                'profileSummary' => $profileSummary,
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
     *     }>,
     *     regulatoryResults:int,
     *     profileSummary:list<array<string,mixed>>
     *   },
     *   failures:array<string,mixed>,
     *   performance:array<string,int|float|string>
     * } $analysis
     * @param list<array<string,mixed>> $profileSummary
     */
    private function report(
        string $path,
        string $runId,
        string $protocolVersion,
        string $gitCommit,
        string $datasetHash,
        array $analysis,
        array $profileSummary,
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

        $profileStates = [];
        foreach ($profileSummary as $item) {
            /** @psalm-suppress MixedAssignment */
            $profile = $item['profile'] ?? null;
            /** @psalm-suppress MixedAssignment */
            $state = $item['publicEvidenceState'] ?? null;
            if (!is_string($profile) || !is_string($state)) {
                continue;
            }
            $profileStates[$profile][$state] = ($profileStates[$profile][$state] ?? 0) + 1;
        }

        $lines[] = '';
        $lines[] = '## Regulatory public-evidence summary';
        $lines[] = '';
        $lines[] = '| Profile | Public-evidence state | Resources |';
        $lines[] = '| --- | --- | ---: |';
        foreach ($profileStates as $profile => $states) {
            ksort($states);
            foreach ($states as $state => $count) {
                $lines[] = sprintf('| %s | %s | %d |', $profile, $state, $count);
            }
        }
        $lines[] = '';
        $lines[] = 'Per-resource results are exported in `profile-summary.csv`; requirement-level results are in `profiles.csv`.';
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
