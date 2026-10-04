<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Unit\Analysis;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Analysis\RegulatoryMetrics;

final class RegulatoryMetricsTest extends TestCase
{
    public function testDenominatorExcludesUnavailableAndApplicabilityUnknown(): void
    {
        $rows = [];
        foreach ([
            'observed_support',
            'partial_observed_support',
            'no_observed_support',
            'unavailable',
            'applicability_unknown',
        ] as $index => $state) {
            $rows[] = [
                'resourceId' => 'r' . $index,
                'profile' => 'lgpd',
                'profileVersion' => '1.1.0',
                'result' => [
                    'id' => 'lgpd-art9-purpose',
                    'title' => 'Purpose',
                    'state' => $state,
                ],
            ];
        }

        $metrics = (new RegulatoryMetrics())->summarize($rows);

        self::assertCount(1, $metrics);
        self::assertSame(5, $metrics[0]['totalResources']);
        self::assertSame(3, $metrics[0]['measurableResources']);
        self::assertSame(1, $metrics[0]['observedSupport']);
        self::assertSame(1, $metrics[0]['partialObservedSupport']);
        self::assertSame(1, $metrics[0]['noObservedSupport']);
        self::assertSame(1, $metrics[0]['unavailable']);
        self::assertSame(1, $metrics[0]['applicabilityUnknown']);
        self::assertEqualsWithDelta(1 / 3, $metrics[0]['fullObservedSupportRate'], 0.000001);
    }

    public function testRateIsNullWhenNothingWasMeasurable(): void
    {
        $metrics = (new RegulatoryMetrics())->summarize([
            [
                'resourceId' => 'r1',
                'profile' => 'lgpd',
                'profileVersion' => '1.1.0',
                'result' => [
                    'id' => 'lgpd-transfer-transparency',
                    'title' => 'Transfers',
                    'state' => 'applicability_unknown',
                ],
            ],
        ]);

        self::assertNull($metrics[0]['fullObservedSupportRate']);
        self::assertSame(0, $metrics[0]['measurableResources']);
    }
}
