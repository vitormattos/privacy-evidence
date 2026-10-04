<?php

declare(strict_types=1);

namespace PrivacyEvidence\Source;

final readonly class DatasetProvenance
{
    /**
     * @param array<string,scalar|null> $summary
     */
    private function __construct(
        public string $path,
        public string $sha256,
        public array $summary,
    ) {
    }

    public static function discover(string $datasetPath): ?self
    {
        $path = $datasetPath . '.provenance.json';
        if (!is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);
        if (!is_string($contents)) {
            throw new \InvalidArgumentException('Unable to read dataset provenance sidecar.');
        }

        $decoded = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new \InvalidArgumentException('Dataset provenance sidecar must decode to an object.');
        }

        $dataset = $decoded['dataset'] ?? null;
        if (!is_array($dataset)) {
            throw new \InvalidArgumentException('Dataset provenance sidecar has no dataset object.');
        }

        $expectedSha256 = $dataset['sha256'] ?? null;
        if (!is_string($expectedSha256) || preg_match('/^[a-f0-9]{64}$/D', $expectedSha256) !== 1) {
            throw new \InvalidArgumentException('Dataset provenance sidecar has an invalid dataset SHA-256.');
        }

        $actualSha256 = hash_file('sha256', $datasetPath);
        if (!is_string($actualSha256) || !hash_equals($expectedSha256, $actualSha256)) {
            throw new \InvalidArgumentException('Dataset provenance SHA-256 does not match the canonical dataset.');
        }

        $producer = $decoded['producer'] ?? null;
        $producerVersion = $decoded['producerVersion'] ?? null;
        $schemaVersion = $decoded['schemaVersion'] ?? null;

        foreach ([
            'schemaVersion' => $schemaVersion,
            'producer' => $producer,
            'producerVersion' => $producerVersion,
        ] as $field => $value) {
            if (!is_string($value) || trim($value) === '') {
                throw new \InvalidArgumentException(
                    'Dataset provenance sidecar has no valid ' . $field . '.',
                );
            }
        }

        return new self(
            path: $path,
            sha256: hash('sha256', $contents),
            summary: [
                'schemaVersion' => $schemaVersion,
                'producer' => $producer,
                'producerVersion' => $producerVersion,
                'datasetSha256' => $expectedSha256,
            ],
        );
    }
}
