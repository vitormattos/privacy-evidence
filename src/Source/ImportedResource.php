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
        public string $classificationRule = 'unspecified',
        public string $classificationVersion = ResourceClassifier::VERSION,
        public float $classificationConfidence = 0.0,
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
            'classification' => [
                'rule' => $this->classificationRule,
                'version' => $this->classificationVersion,
                'confidence' => $this->classificationConfidence,
            ],
            'metadata' => $this->metadata,
        ];
    }
}
