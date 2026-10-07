<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Unit\Analysis;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Analysis\SelectiveMeasurabilityAnalyzer;

final class SelectiveMeasurabilityAnalyzerTest extends TestCase
{
    public function testAnalyzesPredeclaredPredictorsAgainstCanonicalMeasurability(): void
    {
        $directory = sys_get_temp_dir() . '/privacy-evidence-selectivity-' . bin2hex(random_bytes(6));
        self::assertTrue(mkdir($directory, 0700, true));

        try {
            file_put_contents($directory . '/attrition-results.json', json_encode([
                [
                    'resourceId' => 'a',
                    'canonicalWebsiteUnit' => true,
                    'measurementStatus' => 'measured',
                    'normalizedUrl' => 'https://a.test',
                    'classificationType' => 'institutional_website',
                ],
                [
                    'resourceId' => 'b',
                    'canonicalWebsiteUnit' => true,
                    'measurementStatus' => 'not_measurable',
                    'normalizedUrl' => 'http://b.test',
                    'classificationType' => 'institutional_website',
                ],
                [
                    'resourceId' => 'c',
                    'canonicalWebsiteUnit' => true,
                    'measurementStatus' => 'partially_measured',
                    'normalizedUrl' => 'https://c.test',
                    'classificationType' => 'third_party_hosted_page',
                ],
                [
                    'resourceId' => 'alias',
                    'canonicalWebsiteUnit' => false,
                    'measurementStatus' => 'missing_outcome',
                    'normalizedUrl' => 'https://a.test',
                    'classificationType' => 'institutional_website',
                ],
            ], JSON_THROW_ON_ERROR));

            file_put_contents($directory . '/population-results.json', json_encode([
                ['resourceId' => 'a', 'classificationType' => 'institutional_website', 'duplicateGroupSize' => 2],
                ['resourceId' => 'b', 'classificationType' => 'institutional_website', 'duplicateGroupSize' => 1],
                ['resourceId' => 'c', 'classificationType' => 'third_party_hosted_page', 'duplicateGroupSize' => 1],
                ['resourceId' => 'alias', 'classificationType' => 'institutional_website', 'duplicateGroupSize' => 2],
            ], JSON_THROW_ON_ERROR));

            $result = (new SelectiveMeasurabilityAnalyzer())->analyze($directory);

            self::assertSame(3, $result['population']['canonicalUnits']);
            self::assertSame(2, $result['population']['measurable']);
            self::assertSame(1, $result['population']['nonMeasurable']);

            $https = $this->findRow($result['predictors'], 'scheme', 'https');
            self::assertSame(2, $https['support']);
            self::assertSame(2, $https['measurable']);
            self::assertSame(0, $https['nonMeasurable']);
            self::assertEqualsWithDelta(1.0, $https['measurabilityRate'], 0.000001);
            self::assertEqualsWithDelta(0.0, $https['comparatorRate'], 0.000001);
            self::assertNotNull($https['fisherPValue']);
            self::assertNotNull($https['holmAdjustedPValue']);

            $duplicate = $this->findRow($result['predictors'], 'duplicate_group', 'duplicate_group');
            self::assertSame(1, $duplicate['support']);
            self::assertSame(1, $duplicate['measurable']);
        } finally {
            $paths = glob($directory . '/*');
            if ($paths !== false) {
                foreach ($paths as $path) {
                    unlink($path);
                }
            }
            rmdir($directory);
        }
    }

    public function testHolmAdjustedPValuesAreNeverSmallerThanRawValues(): void
    {
        $directory = sys_get_temp_dir() . '/privacy-evidence-selectivity-' . bin2hex(random_bytes(6));
        self::assertTrue(mkdir($directory, 0700, true));

        try {
            $attrition = [];
            $population = [];
            for ($i = 0; $i < 8; $i++) {
                $id = 'r' . $i;
                $attrition[] = [
                    'resourceId' => $id,
                    'canonicalWebsiteUnit' => true,
                    'measurementStatus' => $i < 4 ? 'measured' : 'not_measurable',
                    'normalizedUrl' => ($i < 4 ? 'https://' : 'http://') . $id . '.test',
                    'classificationType' => 'institutional_website',
                ];
                $population[] = [
                    'resourceId' => $id,
                    'classificationType' => 'institutional_website',
                    'duplicateGroupSize' => 1,
                ];
            }

            file_put_contents($directory . '/attrition-results.json', json_encode($attrition, JSON_THROW_ON_ERROR));
            file_put_contents($directory . '/population-results.json', json_encode($population, JSON_THROW_ON_ERROR));

            $result = (new SelectiveMeasurabilityAnalyzer())->analyze($directory);
            foreach ($result['predictors'] as $row) {
                if ($row['fisherPValue'] === null || $row['holmAdjustedPValue'] === null) {
                    continue;
                }

                self::assertGreaterThanOrEqual($row['fisherPValue'], $row['holmAdjustedPValue']);
            }
        } finally {
            $paths = glob($directory . '/*');
            if ($paths !== false) {
                foreach ($paths as $path) {
                    unlink($path);
                }
            }
            rmdir($directory);
        }
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
     * @return array{
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
     * }
     */
    private function findRow(array $rows, string $predictor, string $category): array
    {
        foreach ($rows as $row) {
            if ($row['predictor'] === $predictor && $row['category'] === $category) {
                return $row;
            }
        }

        self::fail(sprintf('Missing row %s/%s.', $predictor, $category));
    }
}
