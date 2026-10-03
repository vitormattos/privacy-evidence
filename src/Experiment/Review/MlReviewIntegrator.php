<?php

declare(strict_types=1);

namespace PrivacyEvidence\Experiment\Review;

use PrivacyEvidence\Evidence\PrivacyEvidence;
use PrivacyEvidence\Experiment\Rubix\RubixSignalPrediction;
use PrivacyEvidence\Review\ReviewDecision;
use PrivacyEvidence\Review\ReviewerType;
use PrivacyEvidence\Review\ReviewQueue;

final class MlReviewIntegrator
{
    public function __construct(
        private readonly ReviewQueue $reviews,
        private readonly MlReviewPolicy $policy = new MlReviewPolicy(),
    ) {
    }

    public function record(
        string $runId,
        PrivacyEvidence $ruleEvidence,
        RubixSignalPrediction $prediction,
        string $reviewedAt,
    ): MlReviewAssessment {
        $assessment = $this->policy->assess($ruleEvidence, $prediction);

        if ($assessment->needsReview) {
            $payload = $ruleEvidence->toArray();
            $payload['mlReviewPriority'] = $assessment->priority;
            $this->reviews->enqueue(
                $runId,
                $ruleEvidence->id(),
                json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            );
        }

        $rationale = [
            'kind' => 'ml_second_opinion',
            'ruleState' => $ruleEvidence->state->value,
            'ruleDetector' => $ruleEvidence->detector,
            'ruleDetectorVersion' => $ruleEvidence->detectorVersion,
            'mlState' => $assessment->mlState->value,
            'probability' => $prediction->probability,
            'threshold' => $prediction->threshold,
            'lowConfidence' => $assessment->lowConfidence,
            'disagreesWithRule' => $assessment->disagreesWithRule,
            'reviewPriority' => $assessment->priority,
            'artifactSha256' => $prediction->artifactSha256,
            'model' => $prediction->model->toArray(),
        ];

        $this->reviews->decide(new ReviewDecision(
            runId: $runId,
            evidenceId: $ruleEvidence->id(),
            type: $ruleEvidence->type,
            state: $assessment->mlState,
            reviewerType: ReviewerType::AiSuggestion,
            reviewerId: 'ai:php-native-ml',
            reviewedAt: $reviewedAt,
            rationale: json_encode($rationale, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ));

        return $assessment;
    }
}
