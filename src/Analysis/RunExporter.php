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
        $profileMetrics = (new RegulatoryMetrics())->summarize($profiles);
        $jobFailures = $this->runtime->jobs->failures($runId);
        $telemetry = $this->runtime->runs->telemetry($runId);
        $events = $this->runtime->runs->events($runId);
        $counts = $this->runtime->observations->counts($runId);
        $resourceOutcomes = $this->resourceOutcomes($resources, $documents, $events);
        $populationResults = $this->populationResults($resources, $resourceOutcomes, $profileSummary);
        $populationSummary = $this->populationSummary($populationResults);

        $analysis = $this->analysis(
            runId: $runId,
            generatedAt: $run->startedAt,
            resources: $resources,
            evidence: $evidenceObjects,
            profiles: $profiles,
            profileSummary: $profileSummary,
            profileMetrics: $profileMetrics,
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
        $this->json($directory . '/profile-metrics.json', $profileMetrics);
        $this->json($directory . '/failures.json', $jobFailures);
        $this->json($directory . '/telemetry.json', $telemetry);
        $this->json($directory . '/events.json', $events);
        $this->json($directory . '/counts.json', $counts);
        $this->json($directory . '/analysis.json', $analysis);
        $this->json($directory . '/resource-outcomes.json', $resourceOutcomes);
        $this->json($directory . '/population-results.json', $populationResults);
        $this->json($directory . '/population-summary.json', $populationSummary);

        $this->csv(
            $directory . '/resources.csv',
            ['id', 'name', 'sourceValue', 'normalizedUrl', 'type'],
            $this->resourceRows($resources),
        );
        $this->csv(
            $directory . '/resource-outcomes.csv',
            [
                'resourceId',
                'name',
                'normalizedUrl',
                'measurementStatus',
                'primaryReason',
                'successfulDocuments',
                'httpErrors',
                'noRelevantLinks',
                'budgetLimited',
                'antiBotChallenge',
                'measurementLimitReasons',
            ],
            $this->resourceOutcomeRows($resourceOutcomes),
        );
        $this->csv(
            $directory . '/population-results.csv',
            [
                'resourceId',
                'name',
                'sourceValue',
                'normalizedUrl',
                'classificationType',
                'classificationRule',
                'classificationConfidence',
                'duplicateNormalizedUrl',
                'duplicateGroupSize',
                'duplicateCanonicalResourceId',
                'eligibleForWebsiteMeasurement',
                'measurementStatus',
                'primaryReason',
                'lgpdPublicEvidenceState',
                'lgpdCoverageRate',
                'lgpdFullObservedSupportRate',
                'lgpdAnyObservedSupportRate',
            ],
            $this->populationResultRows($populationResults),
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
            $directory . '/profile-metrics.csv',
            [
                'profile',
                'profileVersion',
                'requirementId',
                'requirementTitle',
                'totalResources',
                'measurableResources',
                'observedSupport',
                'partialObservedSupport',
                'noObservedSupport',
                'indeterminate',
                'unavailable',
                'notApplicable',
                'applicabilityUnknown',
                'fullObservedSupportRate',
            ],
            $this->profileMetricRows($profileMetrics),
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
                'measurableRequirements',
                'unresolvedRequirements',
                'coverageDenominator',
                'publicEvidenceCoverageRate',
                'fullObservedSupportRate',
                'anyObservedSupportRate',
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
            $profileMetrics,
            $populationSummary,
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
     * @param list<array{
     *   profile:string,
     *   profileVersion:string,
     *   requirementId:string,
     *   requirementTitle:string,
     *   totalResources:int,
     *   measurableResources:int,
     *   observedSupport:int,
     *   partialObservedSupport:int,
     *   noObservedSupport:int,
     *   indeterminate:int,
     *   unavailable:int,
     *   notApplicable:int,
     *   applicabilityUnknown:int,
     *   fullObservedSupportRate:float|null
     * }> $metrics
     * @return list<list<scalar|null>>
     */
    private function profileMetricRows(array $metrics): array
    {
        $rows = [];
        foreach ($metrics as $metric) {
            $rows[] = [
                $metric['profile'],
                $metric['profileVersion'],
                $metric['requirementId'],
                $metric['requirementTitle'],
                $metric['totalResources'],
                $metric['measurableResources'],
                $metric['observedSupport'],
                $metric['partialObservedSupport'],
                $metric['noObservedSupport'],
                $metric['indeterminate'],
                $metric['unavailable'],
                $metric['notApplicable'],
                $metric['applicabilityUnknown'],
                $metric['fullObservedSupportRate'],
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
                $this->intValue($row['measurableRequirements'] ?? 0),
                $this->intValue($row['unresolvedRequirements'] ?? 0),
                $this->intValue($row['coverageDenominator'] ?? 0),
                $this->floatValue($row['publicEvidenceCoverageRate'] ?? null),
                $this->floatValue($row['fullObservedSupportRate'] ?? null),
                $this->floatValue($row['anyObservedSupportRate'] ?? null),
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
            $indeterminate = $this->intValue($counts['indeterminate'] ?? 0);
            $unavailable = $this->intValue($counts['unavailable'] ?? 0);
            $notApplicable = $this->intValue($counts['not_applicable'] ?? 0);
            $applicabilityUnknown = $this->intValue($counts['applicability_unknown'] ?? 0);
            $measurable = $observed + $partial + $noSupport;
            $unresolved = $indeterminate + $unavailable + $applicabilityUnknown;
            $coverageDenominator = max(0, $total - $notApplicable);

            $group['measurableRequirements'] = $measurable;
            $group['unresolvedRequirements'] = $unresolved;
            $group['coverageDenominator'] = $coverageDenominator;
            $group['publicEvidenceCoverageRate'] = $coverageDenominator > 0
                ? $measurable / $coverageDenominator
                : null;
            $group['fullObservedSupportRate'] = $measurable > 0
                ? $observed / $measurable
                : null;
            $group['anyObservedSupportRate'] = $measurable > 0
                ? ($observed + $partial) / $measurable
                : null;

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

    /**
     * @param list<array<string,mixed>> $resources
     * @param list<array<string,mixed>> $documents
     * @param list<array{type:string,subjectId:string|null,occurredAt:string,detail:array<string,scalar|null>}> $events
     * @return list<array<string,mixed>>
     */
    private function resourceOutcomes(array $resources, array $documents, array $events): array
    {
        /** @var array<string,int> $successfulDocuments */
        $successfulDocuments = [];
        foreach ($documents as $document) {
            /** @psalm-suppress MixedAssignment */
            $resourceIdValue = $document['resourceId'] ?? null;
            /** @psalm-suppress MixedAssignment */
            $statusCodeValue = $document['statusCode'] ?? null;
            $resourceId = is_string($resourceIdValue) ? $resourceIdValue : null;
            $statusCode = is_int($statusCodeValue) ? $statusCodeValue : null;
            if ($resourceId !== null && $statusCode !== null && $statusCode >= 200 && $statusCode < 300) {
                $successfulDocuments[$resourceId] = ($successfulDocuments[$resourceId] ?? 0) + 1;
            }
        }

        $eventData = [];
        foreach ($events as $event) {
            $resourceId = $event['subjectId'];
            if ($resourceId === null) {
                continue;
            }

            $eventData[$resourceId] ??= [
                'terminal' => [],
                'httpErrors' => 0,
                'noRelevantLinks' => false,
                'budgetLimited' => false,
                'antiBotChallenge' => false,
                'measurementLimits' => [],
            ];

            if ($event['type'] === 'resource_terminal') {
                $eventData[$resourceId]['terminal'] = $event['detail'];
            } elseif ($event['type'] === 'crawl_discovery') {
                $depth = $event['detail']['depth'] ?? null;
                $relevant = $event['detail']['relevant_candidates'] ?? null;
                if ((int) $depth === 0 && (int) $relevant === 0) {
                    $eventData[$resourceId]['noRelevantLinks'] = true;
                }
            } elseif ($event['type'] === 'crawl_budget_stop') {
                $eventData[$resourceId]['budgetLimited'] = true;
            } elseif ($event['type'] === 'measurement_limit') {
                $reason = $event['detail']['reason'] ?? null;
                if (is_string($reason)) {
                    $eventData[$resourceId]['measurementLimits'][$reason] = true;
                }
            } elseif ($event['type'] === 'job_failure') {
                $category = $event['detail']['category'] ?? null;
                if ($category === 'anti_bot_challenge') {
                    $eventData[$resourceId]['antiBotChallenge'] = true;
                }
            }
        }

        $outcomes = [];
        foreach ($resources as $resource) {
            $id = $resource['id'] ?? null;
            if (!is_string($id)) {
                continue;
            }

            $data = $eventData[$id] ?? [];
            /** @var array<string,scalar|null> $terminal */
            $terminal = $data['terminal'] ?? [];
            $terminalStatusValue = $terminal['status'] ?? null;
            $categoryValue = $terminal['category'] ?? null;
            $classificationRuleValue = $terminal['classification_rule'] ?? null;
            $httpStatusValue = $terminal['http_status'] ?? null;
            $terminalStatus = is_string($terminalStatusValue) ? $terminalStatusValue : null;
            $category = is_string($categoryValue) ? $categoryValue : null;
            $classificationRule = is_string($classificationRuleValue)
                ? $classificationRuleValue
                : null;
            $httpStatus = is_int($httpStatusValue) ? $httpStatusValue : null;
            $successCount = $successfulDocuments[$id] ?? 0;
            $noRelevantLinks = $data['noRelevantLinks'] ?? false;
            $budgetLimited = $data['budgetLimited'] ?? false;
            $antiBotChallenge = $data['antiBotChallenge'] ?? false;
            /** @var array<string,bool> $measurementLimitMap */
            $measurementLimitMap = is_array($data['measurementLimits'] ?? null)
                ? $data['measurementLimits']
                : [];
            $measurementLimitReasons = array_keys($measurementLimitMap);
            sort($measurementLimitReasons, SORT_STRING);
            $hasHardMeasurementLimit = in_array(
                'root_non_html',
                $measurementLimitReasons,
                true,
            ) || in_array(
                'empty_html_content',
                $measurementLimitReasons,
                true,
            ) || in_array(
                'anti_bot_challenge_browser_unavailable',
                $measurementLimitReasons,
                true,
            ) || in_array(
                'dynamic_content_browser_unavailable',
                $measurementLimitReasons,
                true,
            );
            $hasPartialMeasurementLimit = in_array(
                'response_truncated',
                $measurementLimitReasons,
                true,
            ) || in_array(
                'behavioral_evidence_browser_unavailable',
                $measurementLimitReasons,
                true,
            ) || in_array(
                'browser_required_unavailable',
                $measurementLimitReasons,
                true,
            );

            if ($terminalStatus === 'not_eligible') {
                $measurementStatus = 'not_eligible';
                $primaryReason = $classificationRule ?? $category ?? 'not_eligible';
            } elseif ($terminalStatus === 'invalid_url') {
                $measurementStatus = 'not_measurable';
                $primaryReason = 'invalid_url';
            } elseif ($terminalStatus === 'unreachable') {
                $measurementStatus = 'not_measurable';
                $primaryReason = $category ?? 'unreachable';
            } elseif ($terminalStatus === 'http_error') {
                $measurementStatus = 'not_measurable';
                $primaryReason = $httpStatus === null ? 'http_error' : sprintf('http_%d', $httpStatus);
            } elseif ($antiBotChallenge) {
                $measurementStatus = 'not_measurable';
                $primaryReason = 'anti_bot_challenge';
            } elseif ($hasHardMeasurementLimit) {
                $measurementStatus = 'not_measurable';
                $primaryReason = $measurementLimitReasons[0];
            } elseif ($budgetLimited || $hasPartialMeasurementLimit) {
                $measurementStatus = 'partially_measured';
                $primaryReason = $budgetLimited
                    ? 'crawl_budget_exhausted'
                    : $measurementLimitReasons[0];
            } elseif ($successCount > 0 && $noRelevantLinks) {
                $measurementStatus = 'measured';
                $primaryReason = 'homepage_only_no_relevant_links';
            } elseif ($successCount > 0) {
                $measurementStatus = 'measured';
                $primaryReason = 'measured';
            } else {
                $measurementStatus = 'not_measurable';
                $primaryReason = 'no_successful_document';
            }

            $outcomes[] = [
                'resourceId' => $id,
                'name' => is_string($resource['name'] ?? null) ? $resource['name'] : $id,
                'normalizedUrl' => is_string($resource['normalizedUrl'] ?? null) ? $resource['normalizedUrl'] : null,
                'measurementStatus' => $measurementStatus,
                'primaryReason' => $primaryReason,
                'successfulDocuments' => $successCount,
                'httpErrors' => $terminalStatus === 'http_error' ? 1 : 0,
                'noRelevantLinks' => $noRelevantLinks,
                'budgetLimited' => $budgetLimited,
                'antiBotChallenge' => $antiBotChallenge,
                'measurementLimitReasons' => $measurementLimitReasons,
            ];
        }

        return $outcomes;
    }

    /**
     * @param list<array<string,mixed>> $outcomes
     * @return list<list<scalar|null>>
     */
    private function resourceOutcomeRows(array $outcomes): array
    {
        $rows = [];
        foreach ($outcomes as $outcome) {
            $rows[] = [
                Value::string($outcome['resourceId'] ?? null, 'outcome.resourceId'),
                Value::string($outcome['name'] ?? null, 'outcome.name'),
                Value::nullableString($outcome['normalizedUrl'] ?? null, 'outcome.normalizedUrl'),
                Value::string($outcome['measurementStatus'] ?? null, 'outcome.measurementStatus'),
                Value::string($outcome['primaryReason'] ?? null, 'outcome.primaryReason'),
                $this->intValue($outcome['successfulDocuments'] ?? 0),
                $this->intValue($outcome['httpErrors'] ?? 0),
                !empty($outcome['noRelevantLinks']) ? '1' : '0',
                !empty($outcome['budgetLimited']) ? '1' : '0',
                !empty($outcome['antiBotChallenge']) ? '1' : '0',
                $this->stringList($outcome['measurementLimitReasons'] ?? []),
            ];
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $resources
     * @param list<array<string,mixed>> $outcomes
     * @param list<array<string,mixed>> $profileSummary
     * @return list<array<string,mixed>>
     */
    private function populationResults(
        array $resources,
        array $outcomes,
        array $profileSummary,
    ): array {
        $outcomeById = [];
        foreach ($outcomes as $outcome) {
            /** @psalm-suppress MixedAssignment */
            $idValue = $outcome['resourceId'] ?? null;
            if (is_string($idValue)) {
                $outcomeById[$idValue] = $outcome;
            }
        }

        $lgpdById = [];
        foreach ($profileSummary as $summary) {
            /** @psalm-suppress MixedAssignment */
            $resourceIdValue = $summary['resourceId'] ?? null;
            /** @psalm-suppress MixedAssignment */
            $profileValue = $summary['profile'] ?? null;
            if (is_string($resourceIdValue) && $profileValue === 'lgpd') {
                $lgpdById[$resourceIdValue] = $summary;
            }
        }

        /** @var array<string,list<string>> $resourceIdsByNormalizedUrl */
        $resourceIdsByNormalizedUrl = [];
        foreach ($resources as $resource) {
            /** @psalm-suppress MixedAssignment */
            $resourceIdValue = $resource['id'] ?? null;
            /** @psalm-suppress MixedAssignment */
            $normalizedUrlValue = $resource['normalizedUrl'] ?? null;
            if (is_string($resourceIdValue) && is_string($normalizedUrlValue)) {
                $resourceIdsByNormalizedUrl[$normalizedUrlValue][] = $resourceIdValue;
            }
        }
        foreach ($resourceIdsByNormalizedUrl as &$resourceIds) {
            sort($resourceIds, SORT_STRING);
        }
        unset($resourceIds);

        $results = [];
        foreach ($resources as $resource) {
            /** @psalm-suppress MixedAssignment */
            $idValue = $resource['id'] ?? null;
            if (!is_string($idValue)) {
                continue;
            }

            $outcome = $outcomeById[$idValue] ?? [];
            $lgpd = $lgpdById[$idValue] ?? [];
            /** @psalm-suppress MixedAssignment */
            $classificationValue = $resource['classification'] ?? [];
            $classification = is_array($classificationValue) ? $classificationValue : [];
            /** @psalm-suppress MixedAssignment */
            $typeValue = $resource['type'] ?? null;
            $type = is_string($typeValue) ? $typeValue : 'unknown';
            /** @psalm-suppress MixedAssignment */
            $normalizedUrlValue = $resource['normalizedUrl'] ?? null;
            $normalizedUrl = is_string($normalizedUrlValue) ? $normalizedUrlValue : null;
            $duplicateIds = $normalizedUrl === null
                ? []
                : ($resourceIdsByNormalizedUrl[$normalizedUrl] ?? []);
            $duplicateGroupSize = count($duplicateIds);

            $results[] = [
                'resourceId' => $idValue,
                'name' => is_string($resource['name'] ?? null) ? $resource['name'] : $idValue,
                'sourceValue' => is_string($resource['sourceValue'] ?? null) ? $resource['sourceValue'] : '',
                'normalizedUrl' => $normalizedUrl,
                'classificationType' => $type,
                'classificationRule' => is_string($classification['rule'] ?? null)
                    ? $classification['rule']
                    : 'unknown',
                'classificationConfidence' => is_float($classification['confidence'] ?? null)
                    ? $classification['confidence']
                    : 0.0,
                'duplicateNormalizedUrl' => $duplicateGroupSize > 1,
                'duplicateGroupSize' => $duplicateGroupSize,
                'duplicateCanonicalResourceId' => $duplicateGroupSize > 1
                    ? $duplicateIds[0]
                    : null,
                'eligibleForWebsiteMeasurement' => $type === 'institutional_website',
                'measurementStatus' => is_string($outcome['measurementStatus'] ?? null)
                    ? $outcome['measurementStatus']
                    : 'missing_outcome',
                'primaryReason' => is_string($outcome['primaryReason'] ?? null)
                    ? $outcome['primaryReason']
                    : 'missing_outcome',
                'lgpdPublicEvidenceState' => is_string($lgpd['publicEvidenceState'] ?? null)
                    ? $lgpd['publicEvidenceState']
                    : null,
                'lgpdCoverageRate' => $this->floatValue($lgpd['publicEvidenceCoverageRate'] ?? null),
                'lgpdFullObservedSupportRate' => $this->floatValue($lgpd['fullObservedSupportRate'] ?? null),
                'lgpdAnyObservedSupportRate' => $this->floatValue($lgpd['anyObservedSupportRate'] ?? null),
            ];
        }

        return $results;
    }

    /**
     * @param list<array<string,mixed>> $results
     * @return array<string,mixed>
     */
    private function populationSummary(array $results): array
    {
        $classification = [];
        $measurement = [];
        $reasons = [];
        $lgpdStates = [];
        $eligible = 0;
        $accounted = 0;
        $duplicateResources = 0;
        $duplicateGroups = [];

        foreach ($results as $result) {
            /** @psalm-suppress MixedAssignment */
            $classificationValue = $result['classificationType'] ?? null;
            /** @psalm-suppress MixedAssignment */
            $measurementValue = $result['measurementStatus'] ?? null;
            /** @psalm-suppress MixedAssignment */
            $reasonValue = $result['primaryReason'] ?? null;
            /** @psalm-suppress MixedAssignment */
            $lgpdValue = $result['lgpdPublicEvidenceState'] ?? null;
            /** @psalm-suppress MixedAssignment */
            $canonicalValue = $result['duplicateCanonicalResourceId'] ?? null;

            $classificationType = is_string($classificationValue) ? $classificationValue : 'unknown';
            $measurementStatus = is_string($measurementValue) ? $measurementValue : 'missing_outcome';
            $reason = is_string($reasonValue) ? $reasonValue : 'missing_outcome';

            $classification[$classificationType] = ($classification[$classificationType] ?? 0) + 1;
            $measurement[$measurementStatus] = ($measurement[$measurementStatus] ?? 0) + 1;
            $reasons[$reason] = ($reasons[$reason] ?? 0) + 1;

            if (!empty($result['eligibleForWebsiteMeasurement'])) {
                $eligible++;
            }
            if ($measurementStatus !== 'missing_outcome') {
                $accounted++;
            }
            if (!empty($result['duplicateNormalizedUrl'])) {
                $duplicateResources++;
                if (is_string($canonicalValue)) {
                    $duplicateGroups[$canonicalValue] = true;
                }
            }
            if (is_string($lgpdValue)) {
                $lgpdStates[$lgpdValue] = ($lgpdStates[$lgpdValue] ?? 0) + 1;
            }
        }

        ksort($classification);
        ksort($measurement);
        ksort($reasons);
        ksort($lgpdStates);

        return [
            'schemaVersion' => '1.0.0',
            'population' => count($results),
            'accountedResources' => $accounted,
            'completePopulationAccounting' => $accounted === count($results),
            'eligibleForWebsiteMeasurement' => $eligible,
            'classificationByType' => $classification,
            'measurementByStatus' => $measurement,
            'primaryReasons' => $reasons,
            'duplicates' => [
                'groups' => count($duplicateGroups),
                'resources' => $duplicateResources,
            ],
            'lgpdPublicEvidenceStates' => $lgpdStates,
        ];
    }

    /**
     * @param list<array<string,mixed>> $results
     * @return list<list<scalar|null>>
     */
    private function populationResultRows(array $results): array
    {
        $rows = [];
        foreach ($results as $result) {
            $rows[] = [
                Value::string($result['resourceId'] ?? null, 'population.resourceId'),
                Value::string($result['name'] ?? null, 'population.name'),
                Value::string($result['sourceValue'] ?? null, 'population.sourceValue'),
                Value::nullableString($result['normalizedUrl'] ?? null, 'population.normalizedUrl'),
                Value::string($result['classificationType'] ?? null, 'population.classificationType'),
                Value::string($result['classificationRule'] ?? null, 'population.classificationRule'),
                $this->floatValue($result['classificationConfidence'] ?? null),
                !empty($result['duplicateNormalizedUrl']) ? '1' : '0',
                $this->intValue($result['duplicateGroupSize'] ?? 0),
                Value::nullableString(
                    $result['duplicateCanonicalResourceId'] ?? null,
                    'population.duplicateCanonicalResourceId',
                ),
                !empty($result['eligibleForWebsiteMeasurement']) ? '1' : '0',
                Value::string($result['measurementStatus'] ?? null, 'population.measurementStatus'),
                Value::string($result['primaryReason'] ?? null, 'population.primaryReason'),
                Value::nullableString($result['lgpdPublicEvidenceState'] ?? null, 'population.lgpdPublicEvidenceState'),
                $this->floatValue($result['lgpdCoverageRate'] ?? null),
                $this->floatValue($result['lgpdFullObservedSupportRate'] ?? null),
                $this->floatValue($result['lgpdAnyObservedSupportRate'] ?? null),
            ];
        }

        return $rows;
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

    private function floatValue(mixed $value): ?float
    {
        if (is_float($value)) {
            return $value;
        }
        if (is_int($value)) {
            return (float) $value;
        }

        return null;
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
     * @param list<array<string,mixed>> $profileMetrics
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
     *     profileSummary:list<array<string,mixed>>,
     *     profileMetrics:list<array<string,mixed>>
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
        array $profileMetrics,
        array $jobFailures,
        array $telemetry,
        array $counts,
    ): array {
        $eligibleResourceCount = 0;
        foreach ($resources as $resource) {
            /** @psalm-suppress MixedAssignment */
            $typeValue = $resource['type'] ?? null;
            if ($typeValue === 'institutional_website') {
                $eligibleResourceCount++;
            }
        }

        /** @var array<string,array{eligibleResources:int,observations:int,states:array<string,int>}> $byType */
        $byType = [];

        foreach ($evidence as $item) {
            $type = $item->type->value;
            $state = $item->state->value;

            if (!isset($byType[$type])) {
                $byType[$type] = [
                    'eligibleResources' => $eligibleResourceCount,
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
                'profileMetrics' => $profileMetrics,
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
     *     profileSummary:list<array<string,mixed>>,
     *     profileMetrics:list<array<string,mixed>>
     *   },
     *   failures:array<string,mixed>,
     *   performance:array<string,int|float|string>
     * } $analysis
     * @param list<array<string,mixed>> $profileSummary
     * @param list<array<string,mixed>> $profileMetrics
     * @param array<string,mixed> $populationSummary
     */
    private function report(
        string $path,
        string $runId,
        string $protocolVersion,
        string $gitCommit,
        string $datasetHash,
        array $analysis,
        array $profileSummary,
        array $profileMetrics,
        array $populationSummary,
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
            '## Population accounting',
            '',
            '- Population: ' . $this->intValue($populationSummary['population'] ?? 0),
            '- Accounted resources: ' . $this->intValue($populationSummary['accountedResources'] ?? 0),
            '- Website-measurement eligible: ' . $this->intValue(
                $populationSummary['eligibleForWebsiteMeasurement'] ?? 0,
            ),
            '- Complete accounting: ' . (!empty($populationSummary['completePopulationAccounting'])
                ? 'yes'
                : 'no'),
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
        $lines[] = '## Requirement-level observable support';
        $lines[] = '';
        $lines[] = '| Profile | Requirement | Measurable / total | Full observed support | Rate |';
        $lines[] = '| --- | --- | ---: | ---: | ---: |';
        foreach ($profileMetrics as $metric) {
            /** @psalm-suppress MixedAssignment */
            $profile = $metric['profile'] ?? null;
            /** @psalm-suppress MixedAssignment */
            $requirement = $metric['requirementId'] ?? null;
            /** @psalm-suppress MixedAssignment */
            $measurable = $metric['measurableResources'] ?? null;
            /** @psalm-suppress MixedAssignment */
            $total = $metric['totalResources'] ?? null;
            /** @psalm-suppress MixedAssignment */
            $support = $metric['observedSupport'] ?? null;
            /** @psalm-suppress MixedAssignment */
            $rate = $metric['fullObservedSupportRate'] ?? null;
            if (
                !is_string($profile)
                || !is_string($requirement)
                || !is_int($measurable)
                || !is_int($total)
                || !is_int($support)
                || ($rate !== null && !is_float($rate))
            ) {
                continue;
            }
            $lines[] = sprintf(
                '| %s | %s | %d / %d | %d | %s |',
                $profile,
                $requirement,
                $measurable,
                $total,
                $support,
                $rate === null ? 'n/a' : sprintf('%.1f%%', $rate * 100.0),
            );
        }
        $lines[] = '';
        $lines[] = 'Rates use only measurable resources as denominators. Unavailable, not-applicable and applicability-unknown cases remain separate.';
        $lines[] = '';
        $lines[] = 'Per-resource results are exported in `profile-summary.csv`; requirement-level results are in `profiles.csv` and aggregate denominators in `profile-metrics.csv`.';
        $lines[] = '';
        $lines[] = 'Per-resource summaries include an explicit public-evidence coverage denominator plus two support rates: full support (only fully observed requirements) and any support (full or partial). These are website-observability metrics, not legal-compliance scores.';
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
