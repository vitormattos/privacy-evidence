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

        $seenIds = [];

        foreach ($records as $record) {
            if (!is_array($record)) {
                throw new \InvalidArgumentException('Each JSON record must be an object.');
            }

            foreach (['id', 'name', 'url'] as $field) {
                if (!array_key_exists($field, $record) || !is_scalar($record[$field])) {
                    throw new \InvalidArgumentException(
                        sprintf('Each JSON record requires scalar %s.', $field),
                    );
                }
            }

            $id = (string) $record['id'];
            if ($id === '') {
                throw new \InvalidArgumentException('JSON record id must not be empty.');
            }

            if (isset($seenIds[$id])) {
                throw new \InvalidArgumentException(
                    sprintf('JSON contains duplicate id "%s".', $id),
                );
            }
            $seenIds[$id] = true;

            $name = (string) $record['name'];
            $sourceValue = (string) $record['url'];
            $normalized = $this->normalizer->normalize($sourceValue);

            $metadata = [];
            foreach ($record as $key => $value) {
                if (!is_string($key)) {
                    continue;
                }

                if (in_array($key, ['id', 'name', 'url'], true)) {
                    continue;
                }

                if (is_scalar($value) || $value === null) {
                    $metadata[$key] = $value;
                }
            }

            yield new ImportedResource(
                id: $id,
                name: $name,
                sourceValue: $sourceValue,
                normalizedUrl: $normalized,
                type: $this->classifier->classify($sourceValue, $normalized),
                metadata: $metadata,
            );
        }
    }

    public function snapshot(): SourceSnapshot
    {
        $mtime = filemtime($this->path);

        return new SourceSnapshot(
            sourceId: $this->sourceId(),
            capturedAt: gmdate(DATE_ATOM, $mtime === false ? 0 : $mtime),
            sha256: hash('sha256', $this->contents),
            mediaType: 'application/json',
            location: $this->path,
        );
    }
}
