<?php

declare(strict_types=1);

namespace PrivacyEvidence\Analysis;

use PrivacyEvidence\Core\Value;

final class MeasurementAttritionExporter
{
    public const SCHEMA_VERSION = '1.0.0';

    public function export(string $directory): void
    {
        $populationPath = $directory . '/population-results.json';
        if (!is_file($populationPath)) {
            throw new \InvalidArgumentException('population-results.json does not exist.');
        }

        $decoded = json_decode(
            (string) file_get_contents($populationPath),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        if (!is_array($decoded)) {
            throw new \RuntimeException('Population results must decode to an array.');
        }

        /** @var list<array<string,mixed>> $population */
        $population = array_values(array_filter($decoded, 'is_array'));
        $results = $this->results($population);
        $summary = $this->summary($results);

        $this->json($directory . '/attrition-results.json', $results);
        $this->json($directory . '/attrition-summary.json', $summary);
        $this->csv($directory . '/attrition-results.csv', $results);

        if (
            file_put_contents(
                $directory . '/attrition-flow.md',
                $this->flowMarkdown($summary),
                LOCK_EX,
            ) === false
        ) {
            throw new \RuntimeException('Unable to write attrition flow.');
        }
    }

    /**
     * @param list<array<string,mixed>> $population
     * @return list<array<string,mixed>>
     */
    public function results(array $population): array
    {
        $results = [];

        foreach ($population as $row) {
            $resourceId = Value::string($row['resourceId'] ?? null, 'attrition.resourceId');
            $normalizedUrl = Value::nullableString(
                $row['normalizedUrl'] ?? null,
                'attrition.normalizedUrl',
            );
            $measurementStatus = Value::string(
                $row['measurementStatus'] ?? null,
                'attrition.measurementStatus',
            );
            $primaryReason = Value::string(
                $row['primaryReason'] ?? null,
                'attrition.primaryReason',
            );
            $canonicalResourceId = Value::nullableString(
                $row['websiteMeasurementCanonicalResourceId'] ?? null,
                'attrition.websiteMeasurementCanonicalResourceId',
            );

            $normalized = $normalizedUrl !== null;
            $websiteEligible = !empty($row['eligibleForWebsiteMeasurement']);
            $canonicalWebsiteUnit = $websiteEligible && $canonicalResourceId === $resourceId;

            $terminalStage = match (true) {
                !$normalized => 'normalization_unavailable',
                !$websiteEligible => 'protocol_excluded',
                !$canonicalWebsiteUnit => 'duplicate_eligible_reference',
                $measurementStatus === 'measured' => 'fully_measured',
                $measurementStatus === 'partially_measured' => 'partially_measured',
                $measurementStatus === 'not_measurable' => 'not_measurable',
                $measurementStatus === 'missing_outcome' => 'missing_outcome',
                default => 'other_measurement_status',
            };

            $results[] = [
                'resourceId' => $resourceId,
                'sourceValue' => Value::string(
                    $row['sourceValue'] ?? null,
                    'attrition.sourceValue',
                ),
                'normalizedUrl' => $normalizedUrl,
                'classificationType' => Value::string(
                    $row['classificationType'] ?? null,
                    'attrition.classificationType',
                ),
                'normalized' => $normalized,
                'websiteEligible' => $websiteEligible,
                'canonicalWebsiteUnit' => $canonicalWebsiteUnit,
                'measurementStatus' => $measurementStatus,
                'primaryReason' => $primaryReason,
                'terminalStage' => $terminalStage,
                'analyticallyObserved' => $canonicalWebsiteUnit
                    && in_array($measurementStatus, ['measured', 'partially_measured'], true),
            ];
        }

        return $results;
    }

    /**
     * @param list<array<string,mixed>> $results
     * @return array<string,mixed>
     */
    public function summary(array $results): array
    {
        $counts = [
            'sourcePopulation' => count($results),
            'normalizedResources' => 0,
            'websiteEligibleResources' => 0,
            'canonicalWebsiteUnits' => 0,
            'fullyMeasuredUnits' => 0,
            'partiallyMeasuredUnits' => 0,
            'notMeasurableUnits' => 0,
            'missingOutcomeUnits' => 0,
            'otherCanonicalStatusUnits' => 0,
        ];
        $terminalStages = [];
        $canonicalFailureReasons = [];

        foreach ($results as $row) {
            if (!empty($row['normalized'])) {
                $counts['normalizedResources']++;
            }
            if (!empty($row['websiteEligible'])) {
                $counts['websiteEligibleResources']++;
            }

            $terminalStage = Value::string(
                $row['terminalStage'] ?? null,
                'attrition.terminalStage',
            );
            $terminalStages[$terminalStage] = ($terminalStages[$terminalStage] ?? 0) + 1;

            if (empty($row['canonicalWebsiteUnit'])) {
                continue;
            }

            $counts['canonicalWebsiteUnits']++;
            $status = Value::string(
                $row['measurementStatus'] ?? null,
                'attrition.measurementStatus',
            );

            if ($status === 'measured') {
                $counts['fullyMeasuredUnits']++;
            } elseif ($status === 'partially_measured') {
                $counts['partiallyMeasuredUnits']++;
            } elseif ($status === 'not_measurable') {
                $counts['notMeasurableUnits']++;
                $reason = Value::string(
                    $row['primaryReason'] ?? null,
                    'attrition.primaryReason',
                );
                $canonicalFailureReasons[$reason]
                    = ($canonicalFailureReasons[$reason] ?? 0) + 1;
            } elseif ($status === 'missing_outcome') {
                $counts['missingOutcomeUnits']++;
            } else {
                $counts['otherCanonicalStatusUnits']++;
            }
        }

        ksort($terminalStages);
        ksort($canonicalFailureReasons);

        $observedUnits = $counts['fullyMeasuredUnits'] + $counts['partiallyMeasuredUnits'];
        $measurementLossUnits = $counts['notMeasurableUnits'] + $counts['missingOutcomeUnits'];
        $canonical = $counts['canonicalWebsiteUnits'];

        return [
            'schemaVersion' => self::SCHEMA_VERSION,
            ...$counts,
            'observedUnits' => $observedUnits,
            'measurementLossUnits' => $measurementLossUnits,
            'canonicalToObservedRate' => $canonical === 0 ? null : $observedUnits / $canonical,
            'canonicalToFullyMeasuredRate' => $canonical === 0
                ? null
                : $counts['fullyMeasuredUnits'] / $canonical,
            'terminalStages' => $terminalStages,
            'canonicalFailureReasons' => $canonicalFailureReasons,
            'completeCanonicalAccounting' => $canonical
                === $observedUnits
                    + $measurementLossUnits
                    + $counts['otherCanonicalStatusUnits'],
            'semantics' => [
                'observedUnits' => 'canonical website units with measured or partially_measured status',
                'measurementLossUnits' => 'canonical website units with not_measurable or missing_outcome status',
                'protocolExcluded' => 'source resources intentionally ineligible for website measurement',
                'duplicateEligibleReference' => 'eligible source resources represented by another canonical website unit',
            ],
        ];
    }

    /**
     * @param array<string,mixed> $summary
     */
    public function flowMarkdown(array $summary): string
    {
        $count = static function (string $key) use ($summary): int {
            if (!isset($summary[$key]) || !is_int($summary[$key])) {
                return 0;
            }

            return $summary[$key];
        };

        /** @var mixed $stageValue */
        $stageValue = $summary['terminalStages'] ?? [];
        /** @var array<string,int> $stages */
        $stages = is_array($stageValue) ? $stageValue : [];

        $stage = static function (string $key) use ($stages): int {
            if (!isset($stages[$key]) || !is_int($stages[$key])) {
                return 0;
            }

            return $stages[$key];
        };

        return implode(PHP_EOL, [
            '# Measurement attrition flow',
            '',
            'Generated deterministically from attrition-summary.json.',
            '',
            '~~~mermaid',
            'flowchart TD',
            sprintf(
                '  S["Source population: %d"] --> N["Normalized resources: %d"]',
                $count('sourcePopulation'),
                $count('normalizedResources'),
            ),
            sprintf(
                '  S --> NU["Normalization unavailable: %d"]',
                $stage('normalization_unavailable'),
            ),
            sprintf(
                '  N --> E["Website-eligible resources: %d"]',
                $count('websiteEligibleResources'),
            ),
            sprintf('  N --> X["Protocol excluded: %d"]', $stage('protocol_excluded')),
            sprintf(
                '  E --> C["Canonical website units: %d"]',
                $count('canonicalWebsiteUnits'),
            ),
            sprintf(
                '  E --> D["Duplicate eligible references: %d"]',
                $stage('duplicate_eligible_reference'),
            ),
            sprintf('  C --> O["Observed units: %d"]', $count('observedUnits')),
            sprintf('  C --> L["Not measurable: %d"]', $count('notMeasurableUnits')),
            sprintf('  C --> M["Missing outcome: %d"]', $count('missingOutcomeUnits')),
            sprintf('  O --> F["Fully measured: %d"]', $count('fullyMeasuredUnits')),
            sprintf('  O --> P["Partially measured: %d"]', $count('partiallyMeasuredUnits')),
            '~~~',
            '',
            'Protocol exclusions and duplicate references are transformations, not measurement failures.',
            'Observed units are canonical website units with measured or partially_measured status.',
            '',
        ]);
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private function csv(string $path, array $rows): void
    {
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('Unable to write attrition CSV.');
        }

        fputcsv($handle, [
            'resourceId',
            'sourceValue',
            'normalizedUrl',
            'classificationType',
            'normalized',
            'websiteEligible',
            'canonicalWebsiteUnit',
            'measurementStatus',
            'primaryReason',
            'terminalStage',
            'analyticallyObserved',
        ]);

        foreach ($rows as $row) {
            fputcsv($handle, [
                Value::string($row['resourceId'] ?? null, 'attrition.resourceId'),
                Value::string($row['sourceValue'] ?? null, 'attrition.sourceValue'),
                Value::nullableString($row['normalizedUrl'] ?? null, 'attrition.normalizedUrl') ?? '',
                Value::string($row['classificationType'] ?? null, 'attrition.classificationType'),
                !empty($row['normalized']) ? '1' : '0',
                !empty($row['websiteEligible']) ? '1' : '0',
                !empty($row['canonicalWebsiteUnit']) ? '1' : '0',
                Value::string($row['measurementStatus'] ?? null, 'attrition.measurementStatus'),
                Value::string($row['primaryReason'] ?? null, 'attrition.primaryReason'),
                Value::string($row['terminalStage'] ?? null, 'attrition.terminalStage'),
                !empty($row['analyticallyObserved']) ? '1' : '0',
            ]);
        }

        fclose($handle);
    }

    private function json(string $path, mixed $value): void
    {
        $encoded = json_encode(
            $value,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        if (file_put_contents($path, $encoded . PHP_EOL, LOCK_EX) === false) {
            throw new \RuntimeException('Unable to write attrition JSON.');
        }
    }
}
