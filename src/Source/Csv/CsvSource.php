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

        $rawHeader = fgetcsv($handle, escape: '');
        if ($rawHeader === false) {
            throw new \InvalidArgumentException('CSV source is empty.');
        }

        $header = [];
        foreach ($rawHeader as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('CSV header contains an empty or invalid column.');
            }
            $header[] = $column;
        }

        foreach (['id', 'name', 'url'] as $column) {
            if (!in_array($column, $header, true)) {
                throw new \InvalidArgumentException(
                    sprintf('CSV is missing required column "%s".', $column),
                );
            }
        }

        $seenIds = [];

        while (($row = fgetcsv($handle, escape: '')) !== false) {
            if (count($row) === 1 && $row[0] === null) {
                continue;
            }

            if (count($row) !== count($header)) {
                throw new \InvalidArgumentException(
                    'CSV row has a different number of columns than the header.',
                );
            }

            $values = [];
            foreach ($row as $value) {
                $values[] = $value ?? '';
            }

            /** @var array<string, string> $record */
            $record = array_combine($header, $values);
            if ($record['id'] === '') {
                throw new \InvalidArgumentException('CSV record id must not be empty.');
            }

            if (isset($seenIds[$record['id']])) {
                throw new \InvalidArgumentException(
                    sprintf('CSV contains duplicate id "%s".', $record['id']),
                );
            }
            $seenIds[$record['id']] = true;

            $sourceValue = $record['url'];
            $normalized = $this->normalizer->normalize($sourceValue);

            /** @var array<string, scalar|null> $metadata */
            $metadata = $record;
            unset($metadata['id'], $metadata['name'], $metadata['url']);

            yield new ImportedResource(
                id: $record['id'],
                name: $record['name'],
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
        $mtime = filemtime($this->path);

        return new SourceSnapshot(
            sourceId: $this->sourceId(),
            capturedAt: gmdate(DATE_ATOM, $mtime === false ? 0 : $mtime),
            sha256: hash('sha256', $this->contents),
            mediaType: 'text/csv',
            location: $this->path,
        );
    }
}
