<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Unit\Regulatory;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Core\ObservationState;
use PrivacyEvidence\Evidence\EvidenceType;
use PrivacyEvidence\Evidence\PrivacyEvidence;
use PrivacyEvidence\Regulatory\GdprProfile;
use PrivacyEvidence\Regulatory\LgpdProfile;
use PrivacyEvidence\Regulatory\ProfileEvaluator;

final class ProfileEvaluatorTest extends TestCase
{
    public function testUnavailableMeasurementIsNotReportedAsNoObservedSupport(): void
    {
        $results = (new ProfileEvaluator())->evaluate(new LgpdProfile(), []);

        self::assertNotEmpty($results);
        foreach ($results as $result) {
            self::assertSame('unavailable', $result['state']);
            self::assertSame([], $result['present']);
            self::assertNotEmpty($result['unavailable']);
        }
    }

    public function testPartialRequirementKeepsObservedAndMissingSignalsDistinct(): void
    {
        $evidence = [
            new PrivacyEvidence(EvidenceType::ControllerIdentity, ObservationState::Present, 'x', str_repeat('a', 64), 'https://e.test', 'd', '1', 'rule'),
            new PrivacyEvidence(EvidenceType::PrivacyContact, ObservationState::Absent, 'x', str_repeat('a', 64), 'https://e.test', 'd', '1', 'rule'),
        ];

        $results = (new ProfileEvaluator())->evaluate(new LgpdProfile(), $evidence);
        $byId = array_column($results, null, 'id');
        $controller = $byId['lgpd-art9-controller'] ?? null;

        self::assertIsArray($controller);
        self::assertSame('partial_observed_support', $controller['state']);
        self::assertSame(['controller_identity'], $controller['present']);
        self::assertSame(['privacy_contact'], $controller['absent']);
    }

    public function testIncompleteMeasurementDoesNotTurnAbsenceIntoNoSupport(): void
    {
        $evidence = [
            new PrivacyEvidence(
                EvidenceType::PurposeDisclosure,
                ObservationState::Absent,
                'x',
                str_repeat('a', 64),
                'https://e.test',
                'd',
                '1',
                'rule',
            ),
        ];

        $results = (new ProfileEvaluator())->evaluate(
            new LgpdProfile(),
            $evidence,
            negativeEvidenceReliable: false,
        );
        $byId = array_column($results, null, 'id');

        self::assertSame('indeterminate', $byId['lgpd-art9-purpose']['state'] ?? null);
    }

    public function testUnavailablePartOfCompoundRequirementPreventsNegativeConclusion(): void
    {
        $evidence = [
            new PrivacyEvidence(
                EvidenceType::ControllerIdentity,
                ObservationState::Absent,
                'x',
                str_repeat('a', 64),
                'https://e.test',
                'd',
                '1',
                'rule',
            ),
            new PrivacyEvidence(
                EvidenceType::PrivacyContact,
                ObservationState::Unavailable,
                'x',
                str_repeat('a', 64),
                'https://e.test',
                'd',
                '1',
                'rule',
            ),
        ];

        $results = (new ProfileEvaluator())->evaluate(new LgpdProfile(), $evidence);
        $byId = array_column($results, null, 'id');

        self::assertSame('unavailable', $byId['lgpd-art9-controller']['state'] ?? null);
    }

    public function testConditionalRequirementDoesNotTurnAbsenceIntoNegativeSupport(): void
    {
        $evidence = [
            new PrivacyEvidence(EvidenceType::DpoRole, ObservationState::Absent, 'x', str_repeat('a', 64), 'https://e.test', 'd', '1', 'rule'),
            new PrivacyEvidence(EvidenceType::DpoContact, ObservationState::Absent, 'x', str_repeat('a', 64), 'https://e.test', 'd', '1', 'rule'),
        ];

        $results = (new ProfileEvaluator())->evaluate(new LgpdProfile(), $evidence);
        $byId = array_column($results, null, 'id');

        self::assertSame('applicability_unknown', $byId['lgpd-encarregado']['state'] ?? null);
    }

    public function testMapsGenericEvidenceWithoutProducingComplianceVerdict(): void
    {
        $evidence = [
            new PrivacyEvidence(EvidenceType::ControllerIdentity, ObservationState::Present, 'x', str_repeat('a', 64), 'https://e.test', 'd', '1', 'rule'),
            new PrivacyEvidence(EvidenceType::PrivacyContact, ObservationState::Present, 'x', str_repeat('a', 64), 'https://e.test', 'd', '1', 'rule'),
        ];

        $results = (new ProfileEvaluator())->evaluate(new GdprProfile(), $evidence);

        self::assertSame('gdpr-art13-controller', $results[0]['id']);
        self::assertSame('observed_support', $results[0]['state']);
        self::assertStringNotContainsString('compliant', $results[0]['state']);
    }
}
