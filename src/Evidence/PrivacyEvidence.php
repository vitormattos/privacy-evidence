<?php

declare(strict_types=1);

namespace PrivacyEvidence\Evidence;

use PrivacyEvidence\Core\ObservationState;

final readonly class PrivacyEvidence
{
    /**
     * @param array<string, scalar|null> $attributes
     */
    public function __construct(
        public EvidenceType $type,
        public ObservationState $state,
        public string $resourceId,
        public string $artifactHash,
        public string $sourceUrl,
        public string $detector,
        public string $detectorVersion,
        public string $method,
        public ?string $excerpt = null,
        public float $confidence = 1.0,
        public bool $needsReview = false,
        public array $attributes = [],
    ) {
        if ($confidence < 0.0 || $confidence > 1.0) {
            throw new \InvalidArgumentException('Confidence must be between 0 and 1.');
        }
    }
}
