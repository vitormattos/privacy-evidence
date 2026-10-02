<?php

declare(strict_types=1);

namespace PrivacyEvidence\Review;

use PrivacyEvidence\Core\ObservationState;
use PrivacyEvidence\Evidence\EvidenceType;

final readonly class ReviewDecision
{
    public function __construct(
        public string $runId,
        public string $evidenceId,
        public EvidenceType $type,
        public ObservationState $state,
        public ReviewerType $reviewerType,
        public string $reviewerId,
        public string $reviewedAt,
        public string $rationale,
    ) {
        if ($reviewerType === ReviewerType::Human && str_starts_with($reviewerId, 'ai:')) {
            throw new \InvalidArgumentException('AI identities cannot be recorded as human reviewers.');
        }
    }
}
