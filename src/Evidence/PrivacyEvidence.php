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

    public function id(): string
    {
        return hash(
            'sha256',
            implode('|', [
                $this->resourceId,
                $this->artifactHash,
                $this->type->value,
                $this->detector,
                $this->detectorVersion,
                $this->method,
            ]),
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id(),
            'type' => $this->type->value,
            'state' => $this->state->value,
            'resourceId' => $this->resourceId,
            'artifactHash' => $this->artifactHash,
            'sourceUrl' => $this->sourceUrl,
            'detector' => $this->detector,
            'detectorVersion' => $this->detectorVersion,
            'method' => $this->method,
            'excerpt' => $this->excerpt,
            'confidence' => $this->confidence,
            'needsReview' => $this->needsReview,
            'attributes' => $this->attributes,
        ];
    }
}
