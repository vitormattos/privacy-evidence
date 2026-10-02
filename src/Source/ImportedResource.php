<?php

declare(strict_types=1);

namespace PrivacyEvidence\Source;

final readonly class ImportedResource
{
    /**
     * @param array<string, scalar|null> $metadata
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $sourceValue,
        public ?string $normalizedUrl,
        public ResourceType $type = ResourceType::Unknown,
        public array $metadata = [],
    ) {
    }
}
