<?php

declare(strict_types=1);

namespace PrivacyEvidence\Experiment\Rubix;

use PrivacyEvidence\Evidence\EvidenceType;
use PrivacyEvidence\Experiment\Dataset\ClaudinhaLabelMapping;

final class ExternalSignalTrainingSet
{
    /**
     * @return array{
     *   examples:list<array{text:string,present:bool}>,
     *   provenance:RubixSignalTrainingProvenance
     * }
     */
    public function load(
        string $partitionPath,
        string $manifestPath,
        EvidenceType $signal,
        string $trainedAt,
    ): array {
        $manifest = $this->readObject($manifestPath, 'dataset manifest');

        if (($manifest['purpose'] ?? null) !== 'external-development-data') {
            throw new \InvalidArgumentException(
                'Training manifest must identify purpose=external-development-data.',
            );
        }

        $datasetId = $this->stringField($manifest, 'datasetId');
        $datasetVersion = $this->stringField($manifest, 'datasetVersion');
        $datasetSha256 = $this->stringField($manifest, 'canonicalSha256');

        $split = $manifest['split'] ?? null;
        if (!is_array($split)) {
            throw new \InvalidArgumentException('Training manifest has no split metadata.');
        }

        $splitSha256 = $this->stringField($split, 'splitSha256');
        $mappingVersion = $this->stringField($split, 'mappingVersion');
        if ($mappingVersion !== ClaudinhaLabelMapping::VERSION) {
            throw new \InvalidArgumentException('Training manifest uses an unsupported label-mapping version.');
        }

        $handle = @fopen($partitionPath, 'rb');
        if ($handle === false) {
            throw new \InvalidArgumentException('Training partition does not exist.');
        }

        $mapping = new ClaudinhaLabelMapping();
        $examples = [];

        try {
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }

                $decoded = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                if (!is_array($decoded)) {
                    throw new \InvalidArgumentException('Training partition row must decode to an object.');
                }

                $text = $this->stringField($decoded, 'text');
                $externalLabels = $decoded['externalLabels'] ?? null;
                if (!is_array($externalLabels)) {
                    throw new \InvalidArgumentException('Training partition row has no externalLabels list.');
                }

                $present = false;
                /** @psalm-suppress MixedAssignment */
                foreach ($externalLabels as $externalLabel) {
                    if (!is_string($externalLabel)) {
                        throw new \InvalidArgumentException('External training label must be a string.');
                    }

                    $mapped = $mapping->map($externalLabel);
                    foreach ($mapped['evidenceTypes'] as $candidate) {
                        if ($candidate === $signal) {
                            $present = true;
                            break 2;
                        }
                    }
                }

                $examples[] = [
                    'text' => $text,
                    'present' => $present,
                ];
            }
        } finally {
            fclose($handle);
        }

        if ($examples === []) {
            throw new \InvalidArgumentException('Training partition contains no samples.');
        }

        return [
            'examples' => $examples,
            'provenance' => new RubixSignalTrainingProvenance(
                $datasetId,
                $datasetVersion,
                $datasetSha256,
                $splitSha256,
                $mappingVersion,
                $trainedAt,
            ),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function readObject(string $path, string $name): array
    {
        $contents = @file_get_contents($path);
        if (!is_string($contents)) {
            throw new \InvalidArgumentException(ucfirst($name) . ' does not exist.');
        }

        $decoded = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new \InvalidArgumentException(ucfirst($name) . ' must decode to an object.');
        }

        $result = [];
        /** @psalm-suppress MixedAssignment */
        foreach ($decoded as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * @param array<mixed> $source
     */
    private function stringField(array $source, string $field): string
    {
        $value = $source[$field] ?? null;
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException('Missing string field: ' . $field);
        }

        return $value;
    }
}
