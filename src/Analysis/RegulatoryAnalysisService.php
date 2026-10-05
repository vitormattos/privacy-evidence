<?php

declare(strict_types=1);

namespace PrivacyEvidence\Analysis;

use PrivacyEvidence\Pipeline\ProfileRegistry;
use PrivacyEvidence\Regulatory\ProfileEvaluator;
use PrivacyEvidence\Storage\ObservationStore;

final readonly class RegulatoryAnalysisService
{
    public function __construct(
        private ObservationStore $store,
        private ProfileRegistry $profiles,
        private ProfileEvaluator $evaluator = new ProfileEvaluator(),
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
                && $typeValue === 'institutional_website'
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

                foreach ($this->evaluator->evaluate($profile, $evidence) as $result) {
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
}
