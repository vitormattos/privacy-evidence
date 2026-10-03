<?php

declare(strict_types=1);

namespace PrivacyEvidence\Experiment\Benchmark;

use PrivacyEvidence\Evidence\EvidenceType;
use PrivacyEvidence\Experiment\Dataset\ClaudinhaLabelMapping;

final class ExternalSignalEvaluationSet
{
    /**
     * @return list<array{
     *   id:string,
     *   text:string,
     *   present:bool,
     *   language:string,
     *   sourceRecordIds:list<string>
     * }>
     */
    public function load(string $partitionPath, EvidenceType $signal): array
    {
        $handle = @fopen($partitionPath, 'rb');
        if ($handle === false) {
            throw new \InvalidArgumentException('Evaluation partition does not exist.');
        }

        $mapping = new ClaudinhaLabelMapping();
        $cases = [];

        try {
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }

                $decoded = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                if (!is_array($decoded)) {
                    throw new \InvalidArgumentException('Evaluation row must decode to an object.');
                }

                $id = $this->stringField($decoded, 'paragraphId');
                $text = $this->stringField($decoded, 'text');
                $language = $this->stringField($decoded, 'language');
                $externalLabels = $decoded['externalLabels'] ?? null;
                $sourceRecordIds = $decoded['sourceRecordIds'] ?? null;

                if (!is_array($externalLabels) || !is_array($sourceRecordIds)) {
                    throw new \InvalidArgumentException('Evaluation row has invalid labels or source identifiers.');
                }

                $present = false;
                /** @psalm-suppress MixedAssignment */
                foreach ($externalLabels as $label) {
                    if (!is_string($label)) {
                        throw new \InvalidArgumentException('External evaluation label must be a string.');
                    }

                    foreach ($mapping->map($label)['evidenceTypes'] as $candidate) {
                        if ($candidate === $signal) {
                            $present = true;
                            break 2;
                        }
                    }
                }

                $ids = [];
                /** @psalm-suppress MixedAssignment */
                foreach ($sourceRecordIds as $sourceRecordId) {
                    if (!is_string($sourceRecordId)) {
                        throw new \InvalidArgumentException('Source record id must be a string.');
                    }
                    $ids[] = $sourceRecordId;
                }
                sort($ids);

                $cases[] = [
                    'id' => $id,
                    'text' => $text,
                    'present' => $present,
                    'language' => $language,
                    'sourceRecordIds' => $ids,
                ];
            }
        } finally {
            fclose($handle);
        }

        return $cases;
    }

    /**
     * @param array<mixed> $row
     */
    private function stringField(array $row, string $field): string
    {
        $value = $row[$field] ?? null;
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException('Missing evaluation string field: ' . $field);
        }

        return $value;
    }
}
