<?php

declare(strict_types=1);

namespace PrivacyEvidence\Source\Json;

use PrivacyEvidence\Source\ImportedResource;
use PrivacyEvidence\Source\ResourceClassifier;
use PrivacyEvidence\Source\SourceAdapter;
use PrivacyEvidence\Source\SourceSnapshot;
use PrivacyEvidence\Source\UrlNormalizer;

final class JsonSource implements SourceAdapter
{
    private string $contents;

    public function __construct(
        private readonly string $path,
        private readonly UrlNormalizer $normalizer = new UrlNormalizer(),
        private readonly ResourceClassifier $classifier = new ResourceClassifier(),
    ) {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new \RuntimeException(sprintf('Unable to read JSON source: %s', $path));
        }
        $this->contents = $contents;
    }

    public function sourceId(): string
    {
        return 'json:' . basename($this->path);
    }

    public function resources(): iterable
    {
        $records = json_decode($this->contents, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($records) || !array_is_list($records)) {
            throw new \InvalidArgumentException('JSON source must be an array of objects.');
        }

        foreach ($records as $record) {
            if (!is_array($record) || !isset($record['id'], $record['name'], $record['url'])) {
                throw new \InvalidArgumentException('Each JSON record requires id, name and url.');
            }

            $sourceValue = (string) $record['url'];
            $normalized = $this->normalizer->normalize($sourceValue);
            $metadata = $record;
            unset($metadata['id'], $metadata['name'], $metadata['url']);

            /** @var array<string, scalar|null> $metadata */
            yield new ImportedResource(
                id: (string) $record['id'],
                name: (string) $record['name'],
                sourceValue: $sourceValue,
                normalizedUrl: $normalized,
                type: $this->classifier->classify($sourceValue, $normalized),
                metadata: $metadata,
            );
        }
    }

    public function snapshot(): SourceSnapshot
    {
        return new SourceSnapshot(
            sourceId: $this->sourceId(),
            capturedAt: gmdate(DATE_ATOM, (int) filemtime($this->path)),
            sha256: hash('sha256', $this->contents),
            mediaType: 'application/json',
            location: $this->path,
        );
    }
}
