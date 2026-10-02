<?php

declare(strict_types=1);

namespace PrivacyEvidence\Source\Csv;

use PrivacyEvidence\Source\ImportedResource;
use PrivacyEvidence\Source\ResourceClassifier;
use PrivacyEvidence\Source\SourceAdapter;
use PrivacyEvidence\Source\SourceSnapshot;
use PrivacyEvidence\Source\UrlNormalizer;

final class CsvSource implements SourceAdapter
{
    private string $contents;

    public function __construct(
        private readonly string $path,
        private readonly UrlNormalizer $normalizer = new UrlNormalizer(),
        private readonly ResourceClassifier $classifier = new ResourceClassifier(),
    ) {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new \RuntimeException(sprintf('Unable to read CSV source: %s', $path));
        }
        $this->contents = $contents;
    }

    public function sourceId(): string
    {
        return 'csv:' . basename($this->path);
    }

    public function resources(): iterable
    {
        $handle = fopen('php://memory', 'r+');
        if ($handle === false) {
            throw new \RuntimeException('Unable to create CSV memory stream.');
        }

        fwrite($handle, $this->contents);
        rewind($handle);

        $header = fgetcsv($handle, escape: '');
        if ($header === false) {
            throw new \InvalidArgumentException('CSV source is empty.');
        }

        $required = ['id', 'name', 'url'];
        foreach ($required as $column) {
            if (!in_array($column, $header, true)) {
                throw new \InvalidArgumentException(sprintf('CSV is missing required column "%s".', $column));
            }
        }

        while (($row = fgetcsv($handle, escape: '')) !== false) {
            if ($row === [null] || $row === []) {
                continue;
            }

            if (count($row) !== count($header)) {
                throw new \InvalidArgumentException('CSV row has a different number of columns than the header.');
            }

            /** @var array<string, string|null> $record */
            $record = array_combine($header, $row);
            $sourceValue = (string) ($record['url'] ?? '');
            $normalized = $this->normalizer->normalize($sourceValue);

            $metadata = $record;
            unset($metadata['id'], $metadata['name'], $metadata['url']);

            yield new ImportedResource(
                id: (string) $record['id'],
                name: (string) $record['name'],
                sourceValue: $sourceValue,
                normalizedUrl: $normalized,
                type: $this->classifier->classify($sourceValue, $normalized),
                metadata: $metadata,
            );
        }

        fclose($handle);
    }

    public function snapshot(): SourceSnapshot
    {
        return new SourceSnapshot(
            sourceId: $this->sourceId(),
            capturedAt: gmdate(DATE_ATOM, (int) filemtime($this->path)),
            sha256: hash('sha256', $this->contents),
            mediaType: 'text/csv',
            location: $this->path,
        );
    }
}
