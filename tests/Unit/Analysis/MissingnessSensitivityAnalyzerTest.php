<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Unit\Analysis;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Analysis\MissingnessSensitivityAnalyzer;

final class MissingnessSensitivityAnalyzerTest extends TestCase
{
    public function testComparesCompleteCaseNaiveNegativeAndProvenanceAwareSemantics(): void
    {
        $attrition = [
            ['resourceId' => 'a', 'canonicalWebsiteUnit' => true],
            ['resourceId' => 'b', 'canonicalWebsiteUnit' => true],
            ['resourceId' => 'c', 'canonicalWebsiteUnit' => true],
            ['resourceId' => 'd', 'canonicalWebsiteUnit' => true],
            ['resourceId' => 'duplicate', 'canonicalWebsiteUnit' => false],
        ];

        $evidence = [
            ['resourceId' => 'a', 'type' => 'privacy_notice', 'state' => 'present'],
            ['resourceId' => 'b', 'type' => 'privacy_notice', 'state' => 'absent'],
            ['resourceId' => 'c', 'type' => 'privacy_notice', 'state' => 'unknown'],
            ['resourceId' => 'duplicate', 'type' => 'privacy_notice', 'state' => 'present'],
        ];

        $result = (new MissingnessSensitivityAnalyzer())->analyze($attrition, $evidence);
        $privacyNotice = null;
        foreach ($result['outcomes'] as $outcome) {
            if ($outcome['evidenceType'] === 'privacy_notice') {
                $privacyNotice = $outcome;
                break;
            }
        }

        self::assertIsArray($privacyNotice);
        self::assertSame(4, $privacyNotice['canonicalUnits']);
        self::assertSame(1, $privacyNotice['present']);
        self::assertSame(1, $privacyNotice['absent']);
        self::assertSame(2, $privacyNotice['unresolved']);

        self::assertSame(2, $privacyNotice['completeCase']['denominator']);
        self::assertEqualsWithDelta(0.5, $privacyNotice['completeCase']['prevalence'], 0.000001);
        self::assertEqualsWithDelta(0.5, $privacyNotice['completeCase']['coverage'], 0.000001);

        self::assertSame(4, $privacyNotice['naiveNegative']['denominator']);
        self::assertEqualsWithDelta(0.25, $privacyNotice['naiveNegative']['prevalence'], 0.000001);

        self::assertEqualsWithDelta(0.25, $privacyNotice['provenanceAware']['lowerBound'], 0.000001);
        self::assertEqualsWithDelta(0.75, $privacyNotice['provenanceAware']['upperBound'], 0.000001);
        self::assertEqualsWithDelta(-0.25, $privacyNotice['absoluteNaiveVsCompleteCase'], 0.000001);
        self::assertEqualsWithDelta(-0.5, $privacyNotice['relativeNaiveVsCompleteCase'], 0.000001);
    }

    public function testPresentWinsAcrossArtifactsButUnresolvedWinsOverAbsent(): void
    {
        $analysis = new MissingnessSensitivityAnalyzer();
        $attrition = [
            ['resourceId' => 'a', 'canonicalWebsiteUnit' => true],
            ['resourceId' => 'b', 'canonicalWebsiteUnit' => true],
        ];
        $evidence = [
            ['resourceId' => 'a', 'type' => 'privacy_notice', 'state' => 'absent'],
            ['resourceId' => 'a', 'type' => 'privacy_notice', 'state' => 'present'],
            ['resourceId' => 'b', 'type' => 'privacy_notice', 'state' => 'absent'],
            ['resourceId' => 'b', 'type' => 'privacy_notice', 'state' => 'unavailable'],
        ];

        $result = $analysis->analyze($attrition, $evidence);
        $privacyNotice = null;
        foreach ($result['outcomes'] as $outcome) {
            if ($outcome['evidenceType'] === 'privacy_notice') {
                $privacyNotice = $outcome;
                break;
            }
        }

        self::assertIsArray($privacyNotice);
        self::assertSame(1, $privacyNotice['present']);
        self::assertSame(0, $privacyNotice['absent']);
        self::assertSame(1, $privacyNotice['unresolved']);
    }

    public function testExcludedAndNotApplicableDoNotEnterApplicableDenominator(): void
    {
        $attrition = [
            ['resourceId' => 'a', 'canonicalWebsiteUnit' => true],
            ['resourceId' => 'b', 'canonicalWebsiteUnit' => true],
        ];
        $evidence = [
            ['resourceId' => 'a', 'type' => 'cookie_notice', 'state' => 'not_applicable'],
            ['resourceId' => 'b', 'type' => 'cookie_notice', 'state' => 'excluded'],
        ];

        $result = (new MissingnessSensitivityAnalyzer())->analyze($attrition, $evidence);
        $cookieNotice = null;
        foreach ($result['outcomes'] as $outcome) {
            if ($outcome['evidenceType'] === 'cookie_notice') {
                $cookieNotice = $outcome;
                break;
            }
        }

        self::assertIsArray($cookieNotice);
        self::assertSame(2, $cookieNotice['excludedOrNotApplicable']);
        self::assertSame(0, $cookieNotice['applicableUnits']);
        self::assertNull($cookieNotice['completeCase']['prevalence']);
        self::assertNull($cookieNotice['provenanceAware']['lowerBound']);
        self::assertNull($cookieNotice['provenanceAware']['upperBound']);
    }
}
