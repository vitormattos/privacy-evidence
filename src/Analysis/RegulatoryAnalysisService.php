<?php

declare(strict_types=1);

namespace PrivacyEvidence\Analysis;

use PrivacyEvidence\Pipeline\ProfileRegistry;
use PrivacyEvidence\Regulatory\ProfileEvaluator;
use PrivacyEvidence\Run\RunStore;
use PrivacyEvidence\Source\ResourceType;
use PrivacyEvidence\Storage\ObservationStore;

final readonly class RegulatoryAnalysisService
{
    public function __construct(
        private ObservationStore $store,
        private ProfileRegistry $profiles,
        private ProfileEvaluator $evaluator = new ProfileEvaluator(),
        private ?RunStore $runs = null,
    ) {
    }

    public function analyze(string $runId): void
    {
        /** @var array<string,list<string>> $eligibleIdsByUrl */
        $eligibleIdsByUrl = [];
        foreach ($this->store->resourceRecords($runId) as $resource) {
            /** @psalm-suppress MixedAssignment */
            $resourceIdValue = $resource['id'] ?? null;
            /** @psalm-suppress MixedAssignment */
            $typeValue = $resource['type'] ?? null;
            /** @psalm-suppress MixedAssignment */
            $normalizedUrlValue = $resource['normalizedUrl'] ?? null;
            if (
                is_string($resourceIdValue)
                && is_string($typeValue)
                && ResourceType::tryFrom($typeValue)?->isWebsiteMeasurementEligible() === true
                && is_string($normalizedUrlValue)
            ) {
                $eligibleIdsByUrl[$normalizedUrlValue][] = $resourceIdValue;
            }
        }

        $eligible = [];
        foreach ($eligibleIdsByUrl as $resourceIds) {
            sort($resourceIds, SORT_STRING);
            $eligible[$resourceIds[0]] = true;
        }

        $incompleteResources = $this->incompleteResources($runId);

        foreach ($this->store->resourceIds($runId) as $resourceId) {
            if (!isset($eligible[$resourceId])) {
                continue;
            }

            $evidence = $this->store->evidence($runId, $resourceId);

            foreach ($this->profiles->profiles as $profile) {
                $this->store->clearProfileResults(
                    $runId,
                    $resourceId,
                    $profile->id(),
                    $profile->version(),
                );

                foreach (
                    $this->evaluator->evaluate(
                        $profile,
                        $evidence,
                        negativeEvidenceReliable: !isset($incompleteResources[$resourceId]),
                    ) as $result
                ) {
                    $this->store->recordProfileResult(
                        runId: $runId,
                        resourceId: $resourceId,
                        profile: $profile->id(),
                        profileVersion: $profile->version(),
                        result: $result,
                    );
                }
            }
        }
    }

    /**
     * @return array<string,true>
     */
    private function incompleteResources(string $runId): array
    {
        if ($this->runs === null) {
            return [];
        }

        $incomplete = [];
        foreach ($this->runs->events($runId) as $event) {
            $resourceId = $event['subjectId'];
            if ($resourceId === null) {
                continue;
            }

            if (in_array($event['type'], ['crawl_budget_stop', 'measurement_limit'], true)) {
                $incomplete[$resourceId] = true;
                continue;
            }

            if (
                $event['type'] === 'job_failure'
                && in_array($event['detail']['status'] ?? null, ['failed', 'dead'], true)
            ) {
                $incomplete[$resourceId] = true;
                continue;
            }

            if ($event['type'] === 'resource_terminal') {
                $status = $event['detail']['status'] ?? null;
                if (
                    is_string($status)
                    && in_array(
                        $status,
                        ['unreachable', 'http_error', 'failed', 'invalid_url'],
                        true,
                    )
                ) {
                    $incomplete[$resourceId] = true;
                }
            }
        }

        return $incomplete;
    }
}
