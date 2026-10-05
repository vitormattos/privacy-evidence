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
        $eligible = [];
        foreach ($this->store->resourceRecords($runId) as $resource) {
            $resourceId = $resource['id'] ?? null;
            $type = $resource['type'] ?? null;
            if (is_string($resourceId) && $type === 'institutional_website') {
                $eligible[$resourceId] = true;
            }
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
