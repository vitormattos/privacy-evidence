<?php

declare(strict_types=1);

namespace PrivacyEvidence\Analysis;

use PrivacyEvidence\Core\Value;
use PrivacyEvidence\Evidence\EvidenceType;

final readonly class MissingnessSensitivityAnalyzer
{
    public const SCHEMA_VERSION = '1.0.0';

    /** @var list<string> */
    private const PRIMARY_OUTCOMES = [
        'privacy_notice',
        'privacy_law_reference',
        'privacy_contact',
        'rights_disclosure',
        'cookie_notice',
    ];

    public function export(string $directory): void
    {
        $attrition = $this->decodeList($directory . '/attrition-results.json');
        $evidence = $this->decodeList($directory . '/evidence.json');

        $analysis = $this->analyze($attrition, $evidence);
        $this->json($directory . '/missingness-sensitivity.json', $analysis);
        $this->csv($directory . '/missingness-sensitivity.csv', $analysis['outcomes']);
    }

    /**
     * @param list<array<array-key,mixed>> $attrition
     * @param list<array<array-key,mixed>> $evidence
     * @return array{
     *   schemaVersion:string,
     *   primaryOutcomes:list<string>,
     *   canonicalWebsiteUnits:int,
     *   outcomes:list<array{
     *     evidenceType:string,
     *     primaryOutcome:bool,
     *     canonicalUnits:int,
     *     excludedOrNotApplicable:int,
     *     applicableUnits:int,
     *     present:int,
     *     absent:int,
     *     unresolved:int,
     *     completeCase:array{numerator:int,denominator:int,prevalence:float|null,coverage:float|null},
     *     naiveNegative:array{numerator:int,denominator:int,prevalence:float|null,coverage:float},
     *     provenanceAware:array{
     *       numerator:int,
     *       resolvedDenominator:int,
     *       applicableDenominator:int,
     *       observedPrevalence:float|null,
     *       coverage:float|null,
     *       lowerBound:float|null,
     *       upperBound:float|null
     *     },
     *     absoluteNaiveVsCompleteCase:float|null,
     *     relativeNaiveVsCompleteCase:float|null
     *   }>
     * }
     */
    public function analyze(array $attrition, array $evidence): array
    {
        /** @var array<string,true> $canonical */
        $canonical = [];
        foreach ($attrition as $row) {
            if (empty($row['canonicalWebsiteUnit'])) {
                continue;
            }

            $resourceId = Value::string($row['resourceId'] ?? null, 'missingness.resourceId');
            $canonical[$resourceId] = true;
        }

        /** @var array<string,array<string,list<string>>> $statesByTypeAndResource */
        $statesByTypeAndResource = [];
        foreach ($evidence as $row) {
            $resourceId = Value::string($row['resourceId'] ?? null, 'missingness.evidence.resourceId');
            if (!isset($canonical[$resourceId])) {
                continue;
            }

            $type = Value::string($row['type'] ?? null, 'missingness.evidence.type');
            $state = Value::string($row['state'] ?? null, 'missingness.evidence.state');
            $statesByTypeAndResource[$type][$resourceId][] = $state;
        }

        $outcomes = [];
        foreach (EvidenceType::cases() as $evidenceType) {
            $type = $evidenceType->value;
            $present = 0;
            $absent = 0;
            $unresolved = 0;
            $excluded = 0;

            foreach (array_keys($canonical) as $resourceId) {
                $states = $statesByTypeAndResource[$type][$resourceId] ?? [];
                $state = $this->canonicalResourceState($states);

                if ($state === 'present') {
                    $present++;
                } elseif ($state === 'absent') {
                    $absent++;
                } elseif ($state === 'excluded_or_not_applicable') {
                    $excluded++;
                } else {
                    $unresolved++;
                }
            }

            $canonicalUnits = count($canonical);
            $applicable = $canonicalUnits - $excluded;
            $resolved = $present + $absent;

            $completeCasePrevalence = $this->rate($present, $resolved);
            $coverage = $this->rate($resolved, $applicable);
            $naivePrevalence = $this->rate($present, $applicable);
            $lowerBound = $naivePrevalence;
            $upperBound = $this->rate($present + $unresolved, $applicable);

            $absolute = $completeCasePrevalence === null || $naivePrevalence === null
                ? null
                : $naivePrevalence - $completeCasePrevalence;
            $relative = $completeCasePrevalence === null || $completeCasePrevalence === 0.0 || $absolute === null
                ? null
                : $absolute / $completeCasePrevalence;

            $outcomes[] = [
                'evidenceType' => $type,
                'primaryOutcome' => in_array($type, self::PRIMARY_OUTCOMES, true),
                'canonicalUnits' => $canonicalUnits,
                'excludedOrNotApplicable' => $excluded,
                'applicableUnits' => $applicable,
                'present' => $present,
                'absent' => $absent,
                'unresolved' => $unresolved,
                'completeCase' => [
                    'numerator' => $present,
                    'denominator' => $resolved,
                    'prevalence' => $completeCasePrevalence,
                    'coverage' => $coverage,
                ],
                'naiveNegative' => [
                    'numerator' => $present,
                    'denominator' => $applicable,
                    'prevalence' => $naivePrevalence,
                    'coverage' => $applicable === 0 ? 0.0 : 1.0,
                ],
                'provenanceAware' => [
                    'numerator' => $present,
                    'resolvedDenominator' => $resolved,
                    'applicableDenominator' => $applicable,
                    'observedPrevalence' => $completeCasePrevalence,
                    'coverage' => $coverage,
                    'lowerBound' => $lowerBound,
                    'upperBound' => $upperBound,
                ],
                'absoluteNaiveVsCompleteCase' => $absolute,
                'relativeNaiveVsCompleteCase' => $relative,
            ];
        }

        return [
            'schemaVersion' => self::SCHEMA_VERSION,
            'primaryOutcomes' => self::PRIMARY_OUTCOMES,
            'canonicalWebsiteUnits' => count($canonical),
            'outcomes' => $outcomes,
        ];
    }

    /**
     * @param list<string> $states
     */
    private function canonicalResourceState(array $states): string
    {
        if (in_array('present', $states, true)) {
            return 'present';
        }

        foreach (['unknown', 'unavailable', 'invalid'] as $unresolved) {
            if (in_array($unresolved, $states, true)) {
                return 'unresolved';
            }
        }

        if (in_array('absent', $states, true)) {
            return 'absent';
        }

        if ($states !== []) {
            foreach ($states as $state) {
                if (!in_array($state, ['excluded', 'not_applicable'], true)) {
                    return 'unresolved';
                }
            }

            return 'excluded_or_not_applicable';
        }

        return 'unresolved';
    }

    private function rate(int $numerator, int $denominator): ?float
    {
        return $denominator === 0 ? null : $numerator / $denominator;
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
     *   evidenceType:string,
     *   primaryOutcome:bool,
     *   canonicalUnits:int,
     *   excludedOrNotApplicable:int,
     *   applicableUnits:int,
     *   present:int,
     *   absent:int,
     *   unresolved:int,
     *   completeCase:array{numerator:int,denominator:int,prevalence:float|null,coverage:float|null},
     *   naiveNegative:array{numerator:int,denominator:int,prevalence:float|null,coverage:float},
     *   provenanceAware:array{
     *     numerator:int,
     *     resolvedDenominator:int,
     *     applicableDenominator:int,
     *     observedPrevalence:float|null,
     *     coverage:float|null,
     *     lowerBound:float|null,
     *     upperBound:float|null
     *   },
     *   absoluteNaiveVsCompleteCase:float|null,
     *   relativeNaiveVsCompleteCase:float|null
     * }> $outcomes
     */
    private function csv(string $path, array $outcomes): void
    {
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new \RuntimeException(sprintf('Unable to write %s.', $path));
        }

        try {
            fputcsv($handle, [
                'evidenceType',
                'primaryOutcome',
                'canonicalUnits',
                'excludedOrNotApplicable',
                'applicableUnits',
                'present',
                'absent',
                'unresolved',
                'completeCaseDenominator',
                'completeCasePrevalence',
                'coverage',
                'naiveNegativeDenominator',
                'naiveNegativePrevalence',
                'provenanceLowerBound',
                'provenanceUpperBound',
                'absoluteNaiveVsCompleteCase',
                'relativeNaiveVsCompleteCase',
            ], ',', '"', '');

            foreach ($outcomes as $row) {
                $completeCase = $row['completeCase'];
                $naive = $row['naiveNegative'];
                $provenance = $row['provenanceAware'];

                fputcsv($handle, [
                    Value::string($row['evidenceType'], 'missingness.csv.evidenceType'),
                    !empty($row['primaryOutcome']) ? '1' : '0',
                    Value::int($row['canonicalUnits'], 'missingness.csv.canonicalUnits'),
                    Value::int($row['excludedOrNotApplicable'], 'missingness.csv.excluded'),
                    Value::int($row['applicableUnits'], 'missingness.csv.applicable'),
                    Value::int($row['present'], 'missingness.csv.present'),
                    Value::int($row['absent'], 'missingness.csv.absent'),
                    Value::int($row['unresolved'], 'missingness.csv.unresolved'),
                    Value::int($completeCase['denominator'], 'missingness.csv.completeCaseDenominator'),
                    $this->scalar($completeCase['prevalence'] ?? null),
                    $this->scalar($completeCase['coverage'] ?? null),
                    Value::int($naive['denominator'], 'missingness.csv.naiveNegativeDenominator'),
                    $this->scalar($naive['prevalence'] ?? null),
                    $this->scalar($provenance['lowerBound'] ?? null),
                    $this->scalar($provenance['upperBound'] ?? null),
                    $this->scalar($row['absoluteNaiveVsCompleteCase'] ?? null),
                    $this->scalar($row['relativeNaiveVsCompleteCase'] ?? null),
                ], ',', '"', '');
            }
        } finally {
            fclose($handle);
        }
    }

    private function scalar(mixed $value): string
    {
        return is_int($value) || is_float($value) || is_string($value) ? (string) $value : '';
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
