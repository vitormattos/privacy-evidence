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

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sourceValue' => $this->sourceValue,
            'normalizedUrl' => $this->normalizedUrl,
            'type' => $this->type->value,
            'metadata' => $this->metadata,
        ];
    }
}
