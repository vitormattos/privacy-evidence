<?php

declare(strict_types=1);

namespace PrivacyEvidence\Experiment\Review;

use PrivacyEvidence\Core\ObservationState;

final readonly class MlReviewAssessment
{
    public function __construct(
        public ObservationState $mlState,
        public bool $disagreesWithRule,
        public bool $lowConfidence,
        public bool $needsReview,
        public int $priority,
    ) {
    }
}
