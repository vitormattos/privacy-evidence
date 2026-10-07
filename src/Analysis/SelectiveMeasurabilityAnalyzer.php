<?php

declare(strict_types=1);

namespace PrivacyEvidence\Analysis;

use PrivacyEvidence\Core\Value;

final readonly class SelectiveMeasurabilityAnalyzer
{
    public const SCHEMA_VERSION = '1.0.0';

    /**
     * @return array{
     *   schemaVersion:string,
     *   population:array{canonicalUnits:int,measurable:int,nonMeasurable:int},
     *   predictors:list<array{
     *     predictor:string,
     *     category:string,
     *     support:int,
     *     measurable:int,
     *     nonMeasurable:int,
     *     comparatorSupport:int,
     *     comparatorMeasurable:int,
     *     comparatorNonMeasurable:int,
     *     measurabilityRate:float|null,
     *     comparatorRate:float|null,
     *     rateDifference:float|null,
     *     oddsRatio:float|null,
     *     fisherPValue:float|null,
     *     holmAdjustedPValue:float|null
     *   }>,
     *   notes:list<string>
     * }
     */
    public function analyze(string $directory): array
    {
        $attrition = $this->decodeList($directory . '/attrition-results.json');
        $population = $this->decodeList($directory . '/population-results.json');

        /** @var array<string,array<string,mixed>> $populationById */
        $populationById = [];
        foreach ($population as $row) {
            $resourceId = Value::string($row['resourceId'] ?? null, 'selectivity.population.resourceId');
            $populationById[$resourceId] = $row;
        }

        /** @var list<array{resourceId:string,measurable:bool,scheme:string,resourceType:string,duplicateGroup:string}> $units */
        $units = [];
        foreach ($attrition as $row) {
            if (empty($row['canonicalWebsiteUnit'])) {
                continue;
            }

            $resourceId = Value::string($row['resourceId'] ?? null, 'selectivity.attrition.resourceId');
            $status = Value::string($row['measurementStatus'] ?? null, 'selectivity.attrition.measurementStatus');
            $normalizedUrl = Value::nullableString($row['normalizedUrl'] ?? null, 'selectivity.attrition.normalizedUrl');
            $populationRow = $populationById[$resourceId] ?? [];

            $resourceType = Value::string(
                $populationRow['classificationType'] ?? $row['classificationType'] ?? null,
                'selectivity.resourceType',
            );
            $duplicateGroupSize = Value::int(
                $populationRow['duplicateGroupSize'] ?? 1,
                'selectivity.duplicateGroupSize',
            );

            $units[] = [
                'resourceId' => $resourceId,
                'measurable' => in_array($status, ['measured', 'partially_measured'], true),
                'scheme' => $this->scheme($normalizedUrl),
                'resourceType' => $resourceType,
                'duplicateGroup' => $duplicateGroupSize > 1 ? 'duplicate_group' : 'singleton',
            ];
        }

        $measurable = 0;
        foreach ($units as $unit) {
            if ($unit['measurable']) {
                $measurable++;
            }
        }

        $rows = [];
        foreach ([
            'scheme' => 'scheme',
            'resource_type' => 'resourceType',
            'duplicate_group' => 'duplicateGroup',
        ] as $predictor => $field) {
            /** @var array<string,array{measurable:int,nonMeasurable:int}> $categories */
            $categories = [];
            foreach ($units as $unit) {
                $category = $unit[$field];
                if (!isset($categories[$category])) {
                    $categories[$category] = ['measurable' => 0, 'nonMeasurable' => 0];
                }
                if ($unit['measurable']) {
                    $categories[$category]['measurable']++;
                } else {
                    $categories[$category]['nonMeasurable']++;
                }
            }

            ksort($categories);
            foreach ($categories as $category => $counts) {
                $support = $counts['measurable'] + $counts['nonMeasurable'];
                $comparatorMeasurable = $measurable - $counts['measurable'];
                $comparatorNonMeasurable = (count($units) - $measurable) - $counts['nonMeasurable'];
                $comparatorSupport = $comparatorMeasurable + $comparatorNonMeasurable;

                $rate = $this->rate($counts['measurable'], $support);
                $comparatorRate = $this->rate($comparatorMeasurable, $comparatorSupport);
                $rateDifference = $rate === null || $comparatorRate === null ? null : $rate - $comparatorRate;

                $rows[] = [
                    'predictor' => $predictor,
                    'category' => $category,
                    'support' => $support,
                    'measurable' => $counts['measurable'],
                    'nonMeasurable' => $counts['nonMeasurable'],
                    'comparatorSupport' => $comparatorSupport,
                    'comparatorMeasurable' => $comparatorMeasurable,
                    'comparatorNonMeasurable' => $comparatorNonMeasurable,
                    'measurabilityRate' => $rate,
                    'comparatorRate' => $comparatorRate,
                    'rateDifference' => $rateDifference,
                    'oddsRatio' => $this->oddsRatio(
                        $counts['measurable'],
                        $counts['nonMeasurable'],
                        $comparatorMeasurable,
                        $comparatorNonMeasurable,
                    ),
                    'fisherPValue' => $support === 0 || $comparatorSupport === 0
                        ? null
                        : $this->fisherTwoSided(
                            $counts['measurable'],
                            $counts['nonMeasurable'],
                            $comparatorMeasurable,
                            $comparatorNonMeasurable,
                        ),
                    'holmAdjustedPValue' => null,
                ];
            }
        }

        $rows = $this->applyHolmCorrection($rows);

        return [
            'schemaVersion' => self::SCHEMA_VERSION,
            'population' => [
                'canonicalUnits' => count($units),
                'measurable' => $measurable,
                'nonMeasurable' => count($units) - $measurable,
            ],
            'predictors' => $rows,
            'notes' => [
                'Outcome is analytical measurability: measured or partially_measured versus other canonical measurement outcomes.',
                'Predictors are predeclared and available before the final privacy-evidence result: normalized URL scheme, protocol resource type, and duplicate-group membership.',
                'Each categorical level is tested one-versus-rest with a two-sided Fisher exact test; Holm correction controls family-wise error across reported tests.',
                'Association is not interpreted as causation. Missing hosting/provider and redirect predictors are not synthesized when the exported data do not support them.',
            ],
        ];
    }

    public function export(string $directory): void
    {
        $analysis = $this->analyze($directory);
        $this->json($directory . '/selective-measurability.json', $analysis);
        $this->csv($directory . '/selective-measurability.csv', $analysis['predictors']);
    }

    private function scheme(?string $url): string
    {
        if ($url === null || $url === '') {
            return 'missing';
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        return is_string($scheme) && $scheme !== '' ? strtolower($scheme) : 'missing';
    }

    private function rate(int $numerator, int $denominator): ?float
    {
        return $denominator === 0 ? null : $numerator / $denominator;
    }

    private function oddsRatio(int $a, int $b, int $c, int $d): ?float
    {
        if (($a + $b) === 0 || ($c + $d) === 0) {
            return null;
        }

        if ($a === 0 || $b === 0 || $c === 0 || $d === 0) {
            return (($a + 0.5) * ($d + 0.5)) / (($b + 0.5) * ($c + 0.5));
        }

        return ($a * $d) / ($b * $c);
    }

    private function fisherTwoSided(int $a, int $b, int $c, int $d): float
    {
        $row1 = $a + $b;
        $row2 = $c + $d;
        $col1 = $a + $c;
        $total = $row1 + $row2;

        $logFactorials = [0.0];
        for ($i = 1; $i <= $total; $i++) {
            $logFactorials[$i] = $logFactorials[$i - 1] + log((float) $i);
        }

        $probability = static function (int $x) use ($row1, $row2, $col1, $total, $logFactorials): float {
            $y = $col1 - $x;
            if ($x < 0 || $x > $row1 || $y < 0 || $y > $row2) {
                return 0.0;
            }

            $logCombination = static function (int $n, int $k) use ($logFactorials): float {
                return $logFactorials[$n] - $logFactorials[$k] - $logFactorials[$n - $k];
            };

            return exp(
                $logCombination($row1, $x)
                + $logCombination($row2, $y)
                - $logCombination($total, $col1),
            );
        };

        $observed = $probability($a);
        $min = max(0, $col1 - $row2);
        $max = min($row1, $col1);
        $p = 0.0;

        for ($x = $min; $x <= $max; $x++) {
            $candidate = $probability($x);
            if ($candidate <= $observed + 1e-12) {
                $p += $candidate;
            }
        }

        return min(1.0, $p);
    }

    /**
     * @param list<array{
     *   predictor:string,
     *   category:string,
     *   support:int,
     *   measurable:int,
     *   nonMeasurable:int,
     *   comparatorSupport:int,
     *   comparatorMeasurable:int,
     *   comparatorNonMeasurable:int,
     *   measurabilityRate:float|null,
     *   comparatorRate:float|null,
     *   rateDifference:float|null,
     *   oddsRatio:float|null,
     *   fisherPValue:float|null,
     *   holmAdjustedPValue:float|null
     * }> $rows
     * @return list<array{
     *   predictor:string,
     *   category:string,
     *   support:int,
     *   measurable:int,
     *   nonMeasurable:int,
     *   comparatorSupport:int,
     *   comparatorMeasurable:int,
     *   comparatorNonMeasurable:int,
     *   measurabilityRate:float|null,
     *   comparatorRate:float|null,
     *   rateDifference:float|null,
     *   oddsRatio:float|null,
     *   fisherPValue:float|null,
     *   holmAdjustedPValue:float|null
     * }>
     */
    private function applyHolmCorrection(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $index => $row) {
            if ($row['fisherPValue'] !== null) {
                $indexed[] = ['index' => $index, 'p' => $row['fisherPValue']];
            }
        }

        usort($indexed, static fn (array $left, array $right): int => $left['p'] <=> $right['p']);

        $m = count($indexed);
        $previous = 0.0;
        foreach ($indexed as $rank => $item) {
            $adjusted = min(1.0, ($m - $rank) * $item['p']);
            $adjusted = max($previous, $adjusted);
            $rows[$item['index']]['holmAdjustedPValue'] = $adjusted;
            $previous = $adjusted;
        }

        return $rows;
    }

    /**
     * @return list<array<array-key,mixed>>
     */
    private function decodeList(string $path): array
    {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException(sprintf('Unable to read %s.', $path));
        }

        $decoded = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new \RuntimeException(sprintf('%s must contain a JSON array.', $path));
        }

        /** @var list<array<array-key,mixed>> $rows */
        $rows = [];
        foreach ($decoded as $row) {
            if (!is_array($row)) {
                throw new \RuntimeException(sprintf('%s contains a non-object row.', $path));
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param list<array{
     *   predictor:string,
     *   category:string,
     *   support:int,
     *   measurable:int,
     *   nonMeasurable:int,
     *   comparatorSupport:int,
     *   comparatorMeasurable:int,
     *   comparatorNonMeasurable:int,
     *   measurabilityRate:float|null,
     *   comparatorRate:float|null,
     *   rateDifference:float|null,
     *   oddsRatio:float|null,
     *   fisherPValue:float|null,
     *   holmAdjustedPValue:float|null
     * }> $rows
     */
    private function csv(string $path, array $rows): void
    {
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new \RuntimeException(sprintf('Unable to write %s.', $path));
        }

        try {
            fputcsv($handle, [
                'predictor',
                'category',
                'support',
                'measurable',
                'nonMeasurable',
                'comparatorSupport',
                'comparatorMeasurable',
                'comparatorNonMeasurable',
                'measurabilityRate',
                'comparatorRate',
                'rateDifference',
                'oddsRatio',
                'fisherPValue',
                'holmAdjustedPValue',
            ], ',', '"', '');

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['predictor'],
                    $row['category'],
                    $row['support'],
                    $row['measurable'],
                    $row['nonMeasurable'],
                    $row['comparatorSupport'],
                    $row['comparatorMeasurable'],
                    $row['comparatorNonMeasurable'],
                    $this->scalar($row['measurabilityRate']),
                    $this->scalar($row['comparatorRate']),
                    $this->scalar($row['rateDifference']),
                    $this->scalar($row['oddsRatio']),
                    $this->scalar($row['fisherPValue']),
                    $this->scalar($row['holmAdjustedPValue']),
                ], ',', '"', '');
            }
        } finally {
            fclose($handle);
        }
    }

    private function scalar(float|int|string|null $value): string
    {
        return $value === null ? '' : (string) $value;
    }

    private function json(string $path, mixed $value): void
    {
        $encoded = json_encode(
            $value,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        if (file_put_contents($path, $encoded . PHP_EOL, LOCK_EX) === false) {
            throw new \RuntimeException(sprintf('Unable to write %s.', $path));
        }
    }
}
