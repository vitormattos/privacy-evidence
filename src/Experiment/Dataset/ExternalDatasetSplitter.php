<?php

declare(strict_types=1);

namespace PrivacyEvidence\Experiment\Dataset;

final class ExternalDatasetSplitter
{
    private const PARTITIONS = ['train', 'validation', 'development'];

    /**
     * @return array<string,mixed>
     */
    public function split(
        string $canonicalPath,
        string $manifestPath,
        string $outputDirectory,
        string $seed,
        float $trainRatio = 0.70,
        float $validationRatio = 0.15,
    ): array {
        if ($seed === '') {
            throw new \InvalidArgumentException('Split seed cannot be empty.');
        }

        if ($trainRatio <= 0.0 || $validationRatio <= 0.0 || ($trainRatio + $validationRatio) >= 1.0) {
            throw new \InvalidArgumentException('Split ratios must leave non-zero train, validation and development partitions.');
        }

        $manifest = $this->readManifest($manifestPath);
        $rows = $this->readCanonicalRows($canonicalPath);
        $samples = $this->reconstructSamples($rows);

        if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0700, true) && !is_dir($outputDirectory)) {
            throw new \RuntimeException('Unable to create split output directory.');
        }

        /** @var array<string,list<array<string,mixed>>> $partitions */
        $partitions = array_fill_keys(self::PARTITIONS, []);
        /** @var array<string,string> $groupPartition */
        $groupPartition = [];
        /** @var array<string,string> $normalizedTextPartition */
        $normalizedTextPartition = [];

        foreach ($samples as $sample) {
            $groupId = $sample['companyId'];
            if ($groupId === '') {
                $groupId = 'paragraph:' . $sample['paragraphId'];
            }

            $partition = $groupPartition[$groupId] ?? $this->partitionFor(
                $seed,
                $groupId,
                $trainRatio,
                $validationRatio,
            );
            $groupPartition[$groupId] = $partition;

            $normalizedText = $this->normalizeText($sample['text']);
            $existingPartition = $normalizedTextPartition[$normalizedText] ?? null;
            if (is_string($existingPartition) && $existingPartition !== $partition) {
                throw new \RuntimeException(
                    'Duplicate normalized text crosses partitions: ' . $sample['paragraphId'],
                );
            }
            $normalizedTextPartition[$normalizedText] = $partition;
            $partitions[$partition][] = $sample;
        }

        $partitionMetadata = [];
        foreach (self::PARTITIONS as $partition) {
            $path = $outputDirectory . '/' . $partition . '.jsonl';
            $partitionMetadata[$partition] = $this->writePartition($path, $partitions[$partition]);
        }

        $assignment = $groupPartition;
        ksort($assignment);
        $splitIdentity = [
            'strategy' => 'grouped-sha256-v1',
            'seed' => $seed,
            'groupingField' => 'companyId',
            'fallbackGroupingField' => 'paragraphId',
            'ratios' => [
                'train' => $trainRatio,
                'validation' => $validationRatio,
                'development' => 1.0 - $trainRatio - $validationRatio,
            ],
            'assignments' => $assignment,
        ];
        $encodedIdentity = json_encode(
            $splitIdentity,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );

        $manifest['split'] = [
            'version' => '1.0.0',
            'strategy' => $splitIdentity['strategy'],
            'seed' => $seed,
            'groupingField' => $splitIdentity['groupingField'],
            'fallbackGroupingField' => $splitIdentity['fallbackGroupingField'],
            'ratios' => $splitIdentity['ratios'],
            'splitSha256' => hash('sha256', $encodedIdentity),
            'mappingVersion' => ClaudinhaLabelMapping::VERSION,
            'partitions' => $partitionMetadata,
        ];

        $encodedManifest = json_encode(
            $manifest,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        ) . PHP_EOL;

        if (file_put_contents($manifestPath, $encodedManifest, LOCK_EX) === false) {
            throw new \RuntimeException('Unable to update dataset manifest with split metadata.');
        }

        return $manifest;
    }

    /**
     * @return array<string,mixed>
     */
    private function readManifest(string $manifestPath): array
    {
        $contents = @file_get_contents($manifestPath);
        if (!is_string($contents)) {
            throw new \InvalidArgumentException('Dataset manifest does not exist.');
        }

        $decoded = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Dataset manifest must decode to an object.');
        }

        $manifest = [];
        /** @psalm-suppress MixedAssignment */
        foreach ($decoded as $key => $value) {
            if (is_string($key)) {
                $manifest[$key] = $value;
            }
        }

        return $manifest;
    }

    /**
     * @return list<array{
     *   sourceRecordId:string,
     *   paragraphId:string,
     *   clauseId:string,
     *   companyId:string,
     *   text:string,
     *   externalLabel:string,
     *   language:string
     * }>
     */
    private function readCanonicalRows(string $canonicalPath): array
    {
        $handle = @fopen($canonicalPath, 'rb');
        if ($handle === false) {
            throw new \InvalidArgumentException('Canonical dataset does not exist.');
        }

        $rows = [];
        try {
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }

                $decoded = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                if (!is_array($decoded)) {
                    throw new \RuntimeException('Canonical dataset row must decode to an object.');
                }

                $rows[] = [
                    'sourceRecordId' => $this->stringField($decoded, 'sourceRecordId'),
                    'paragraphId' => $this->stringField($decoded, 'paragraphId'),
                    'clauseId' => $this->stringField($decoded, 'clauseId'),
                    'companyId' => $this->stringField($decoded, 'companyId'),
                    'text' => $this->stringField($decoded, 'text'),
                    'externalLabel' => $this->stringField($decoded, 'externalLabel'),
                    'language' => $this->stringField($decoded, 'language'),
                ];
            }
        } finally {
            fclose($handle);
        }

        return $rows;
    }

    /**
     * @param list<array{
     *   sourceRecordId:string,
     *   paragraphId:string,
     *   clauseId:string,
     *   companyId:string,
     *   text:string,
     *   externalLabel:string,
     *   language:string
     * }> $rows
     * @return list<array{
     *   paragraphId:string,
     *   clauseIds:list<string>,
     *   companyId:string,
     *   text:string,
     *   externalLabels:list<string>,
     *   language:string,
     *   sourceRecordIds:list<string>
     * }>
     */
    private function reconstructSamples(array $rows): array
    {
        /** @var array<string,array{
         *   paragraphId:string,
         *   clauseIds:array<string,bool>,
         *   companyId:string,
         *   text:string,
         *   externalLabels:array<string,bool>,
         *   language:string,
         *   sourceRecordIds:array<string,bool>
         * }> $samples
         */
        $samples = [];

        foreach ($rows as $row) {
            $key = $row['paragraphId'];
            if (!isset($samples[$key])) {
                $samples[$key] = [
                    'paragraphId' => $row['paragraphId'],
                    'clauseIds' => [],
                    'companyId' => $row['companyId'],
                    'text' => $row['text'],
                    'externalLabels' => [],
                    'language' => $row['language'],
                    'sourceRecordIds' => [],
                ];
            }

            if (
                $samples[$key]['companyId'] !== $row['companyId']
                || $samples[$key]['text'] !== $row['text']
                || $samples[$key]['language'] !== $row['language']
            ) {
                throw new \RuntimeException('Inconsistent duplicated paragraph: ' . $row['paragraphId']);
            }

            $samples[$key]['clauseIds'][$row['clauseId']] = true;
            $samples[$key]['externalLabels'][$row['externalLabel']] = true;
            $samples[$key]['sourceRecordIds'][$row['sourceRecordId']] = true;
        }

        ksort($samples);
        $result = [];
        foreach ($samples as $sample) {
            $clauseIds = array_keys($sample['clauseIds']);
            $externalLabels = array_keys($sample['externalLabels']);
            $sourceRecordIds = array_keys($sample['sourceRecordIds']);
            sort($clauseIds);
            sort($externalLabels);
            sort($sourceRecordIds);

            $result[] = [
                'paragraphId' => $sample['paragraphId'],
                'clauseIds' => $clauseIds,
                'companyId' => $sample['companyId'],
                'text' => $sample['text'],
                'externalLabels' => $externalLabels,
                'language' => $sample['language'],
                'sourceRecordIds' => $sourceRecordIds,
            ];
        }

        return $result;
    }

    private function partitionFor(
        string $seed,
        string $groupId,
        float $trainRatio,
        float $validationRatio,
    ): string {
        $digest = hash('sha256', $seed . "\0" . $groupId);
        $bucket = hexdec(substr($digest, 0, 8)) / 4294967296;

        if ($bucket < $trainRatio) {
            return 'train';
        }

        if ($bucket < ($trainRatio + $validationRatio)) {
            return 'validation';
        }

        return 'development';
    }

    private function normalizeText(string $text): string
    {
        $normalized = preg_replace('/\s+/u', ' ', mb_strtolower(trim($text)));
        if (!is_string($normalized) || $normalized === '') {
            throw new \RuntimeException('Canonical sample text cannot be empty.');
        }

        return $normalized;
    }

    /**
     * @param list<array<string,mixed>> $samples
     * @return array{
     *   file:string,
     *   sha256:string,
     *   records:int,
     *   labelSupport:array<string,int>
     * }
     */
    private function writePartition(string $path, array $samples): array
    {
        $support = [];
        $lines = [];

        foreach ($samples as $sample) {
            $labels = $sample['externalLabels'] ?? null;
            if (!is_array($labels)) {
                throw new \LogicException('Canonical reconstructed sample has invalid labels.');
            }

            /** @psalm-suppress MixedAssignment */
            foreach ($labels as $label) {
                if (!is_string($label)) {
                    throw new \LogicException('Canonical reconstructed sample has non-string label.');
                }
                $support[$label] = ($support[$label] ?? 0) + 1;
            }

            $lines[] = json_encode(
                $sample,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            );
        }

        sort($lines);
        ksort($support);
        $contents = $lines === [] ? '' : implode(PHP_EOL, $lines) . PHP_EOL;

        if (file_put_contents($path, $contents, LOCK_EX) === false) {
            throw new \RuntimeException('Unable to write split partition.');
        }

        return [
            'file' => basename($path),
            'sha256' => hash('sha256', $contents),
            'records' => count($samples),
            'labelSupport' => $support,
        ];
    }

    /**
     * @param array<mixed> $row
     */
    private function stringField(array $row, string $field): string
    {
        $value = $row[$field] ?? null;
        if (!is_string($value)) {
            throw new \RuntimeException('Canonical dataset row is missing string field: ' . $field);
        }

        return $value;
    }
}
