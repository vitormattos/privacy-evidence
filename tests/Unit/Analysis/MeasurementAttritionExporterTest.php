<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Unit\Analysis;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Analysis\MeasurementAttritionExporter;

final class MeasurementAttritionExporterTest extends TestCase
{
    public function testAccountsForPopulationTransformationsAndMeasurementLoss(): void
    {
        $population = [
            [
                'resourceId' => 'site-1',
                'sourceValue' => 'https://example.test/',
                'normalizedUrl' => 'https://example.test/',
                'classificationType' => 'institutional_website',
                'websiteMeasurementCanonicalResourceId' => 'site-1',
                'eligibleForWebsiteMeasurement' => true,
                'measurementStatus' => 'measured',
                'primaryReason' => 'measured',
            ],
            [
                'resourceId' => 'site-dup',
                'sourceValue' => 'https://example.test/',
                'normalizedUrl' => 'https://example.test/',
                'classificationType' => 'institutional_website',
                'websiteMeasurementCanonicalResourceId' => 'site-1',
                'eligibleForWebsiteMeasurement' => true,
                'measurementStatus' => 'duplicate_reference',
                'primaryReason' => 'duplicate_source_url',
            ],
            [
                'resourceId' => 'site-partial',
                'sourceValue' => 'https://partial.test/',
                'normalizedUrl' => 'https://partial.test/',
                'classificationType' => 'institutional_website',
                'websiteMeasurementCanonicalResourceId' => 'site-partial',
                'eligibleForWebsiteMeasurement' => true,
                'measurementStatus' => 'partially_measured',
                'primaryReason' => 'crawl_budget_exhausted',
            ],
            [
                'resourceId' => 'site-loss',
                'sourceValue' => 'https://blocked.test/',
                'normalizedUrl' => 'https://blocked.test/',
                'classificationType' => 'institutional_website',
                'websiteMeasurementCanonicalResourceId' => 'site-loss',
                'eligibleForWebsiteMeasurement' => true,
                'measurementStatus' => 'not_measurable',
                'primaryReason' => 'anti_bot_challenge',
            ],
            [
                'resourceId' => 'social-1',
                'sourceValue' => 'https://social.example/',
                'normalizedUrl' => 'https://social.example/',
                'classificationType' => 'social_network',
                'websiteMeasurementCanonicalResourceId' => null,
                'eligibleForWebsiteMeasurement' => false,
                'measurementStatus' => 'not_eligible',
                'primaryReason' => 'known_social_host',
            ],
        ];

        $analysis = new MeasurementAttritionExporter();
        $results = $analysis->results($population);
        $summary = $analysis->summary($results);

        self::assertSame(5, $summary['sourcePopulation']);
        self::assertSame(5, $summary['normalizedResources']);
        self::assertSame(4, $summary['websiteEligibleResources']);
        self::assertSame(3, $summary['canonicalWebsiteUnits']);
        self::assertSame(1, $summary['fullyMeasuredUnits']);
        self::assertSame(1, $summary['partiallyMeasuredUnits']);
        self::assertSame(2, $summary['observedUnits']);
        self::assertSame(1, $summary['notMeasurableUnits']);
        self::assertSame(1, $summary['measurementLossUnits']);
        self::assertIsArray($summary['terminalStages']);
        $terminalStages = $summary['terminalStages'];
        self::assertSame(1, $terminalStages['protocol_excluded']);
        self::assertSame(1, $terminalStages['duplicate_eligible_reference']);

        self::assertIsArray($summary['canonicalFailureReasons']);
        $canonicalFailureReasons = $summary['canonicalFailureReasons'];
        self::assertSame(1, $canonicalFailureReasons['anti_bot_challenge']);
        self::assertTrue($summary['completeCanonicalAccounting']);
        self::assertEqualsWithDelta(2 / 3, $summary['canonicalToObservedRate'], 0.000001);
    }

    public function testUnnormalizedResourceRemainsInSourceAccounting(): void
    {
        $analysis = new MeasurementAttritionExporter();
        $results = $analysis->results([[
            'resourceId' => 'invalid-1',
            'sourceValue' => 'not a url',
            'normalizedUrl' => null,
            'classificationType' => 'malformed',
            'websiteMeasurementCanonicalResourceId' => null,
            'eligibleForWebsiteMeasurement' => false,
            'measurementStatus' => 'not_eligible',
            'primaryReason' => 'invalid_url',
        ]]);

        self::assertSame('normalization_unavailable', $results[0]['terminalStage']);

        $summary = $analysis->summary($results);
        self::assertSame(1, $summary['sourcePopulation']);
        self::assertSame(0, $summary['normalizedResources']);
        self::assertIsArray($summary['terminalStages']);
        $terminalStages = $summary['terminalStages'];
        self::assertSame(1, $terminalStages['normalization_unavailable']);
    }

    public function testFlowIsDeterministicAndUsesSummaryOnly(): void
    {
        $analysis = new MeasurementAttritionExporter();
        $summary = [
            'sourcePopulation' => 10,
            'normalizedResources' => 9,
            'websiteEligibleResources' => 7,
            'canonicalWebsiteUnits' => 6,
            'observedUnits' => 4,
            'notMeasurableUnits' => 2,
            'missingOutcomeUnits' => 0,
            'fullyMeasuredUnits' => 3,
            'partiallyMeasuredUnits' => 1,
            'terminalStages' => [
                'normalization_unavailable' => 1,
                'protocol_excluded' => 2,
                'duplicate_eligible_reference' => 1,
            ],
        ];

        $first = $analysis->flowMarkdown($summary);
        $second = $analysis->flowMarkdown($summary);

        self::assertSame($first, $second);
        self::assertStringContainsString('Source population: 10', $first);
        self::assertStringContainsString('Canonical website units: 6', $first);
        self::assertStringContainsString('Not measurable: 2', $first);
    }
}
