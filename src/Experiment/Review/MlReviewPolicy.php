<?php

declare(strict_types=1);

namespace PrivacyEvidence\Experiment\Review;

use PrivacyEvidence\Core\ObservationState;
use PrivacyEvidence\Evidence\PrivacyEvidence;
use PrivacyEvidence\Experiment\Rubix\RubixSignalPrediction;

final class MlReviewPolicy
{
    public function __construct(private readonly float $uncertaintyMargin = 0.15)
    {
        if ($this->uncertaintyMargin < 0.0 || $this->uncertaintyMargin > 0.5) {
            throw new \InvalidArgumentException('Uncertainty margin must be between 0 and 0.5.');
        }
    }

    public function assess(
        PrivacyEvidence $ruleEvidence,
        RubixSignalPrediction $prediction,
    ): MlReviewAssessment {
        if ($ruleEvidence->type->value !== $prediction->signal) {
            throw new \InvalidArgumentException('Rule evidence and ML prediction must refer to the same signal.');
        }

        $mlState = $prediction->candidatePresent
            ? ObservationState::Present
            : ObservationState::Absent;

        $ruleHasBinaryState = in_array(
            $ruleEvidence->state,
            [ObservationState::Present, ObservationState::Absent],
            true,
        );
        $disagrees = $ruleHasBinaryState && $ruleEvidence->state !== $mlState;
        $lowConfidence = abs($prediction->probability - $prediction->threshold) <= $this->uncertaintyMargin;
        $needsReview = $disagrees
            || $lowConfidence
            || $ruleEvidence->needsReview
            || !$ruleHasBinaryState;

        $priority = 0;
        if ($disagrees) {
            $priority += 100;
        }
        if ($lowConfidence) {
            $priority += 50;
        }
        if ($ruleEvidence->needsReview || !$ruleHasBinaryState) {
            $priority += 25;
        }

        return new MlReviewAssessment(
            $mlState,
            $disagrees,
            $lowConfidence,
            $needsReview,
            $priority,
        );
    }
}
