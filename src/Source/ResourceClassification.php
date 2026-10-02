<?php

declare(strict_types=1);

namespace PrivacyEvidence\Source;

final readonly class ResourceClassification
{
    public function __construct(
        public ResourceType $type,
        public string $rule,
        public string $version,
        public float $confidence,
    ) {
        if ($confidence < 0.0 || $confidence > 1.0) {
            throw new \InvalidArgumentException('Classification confidence must be between 0 and 1.');
        }
    }
}
