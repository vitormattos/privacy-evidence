<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Unit\Regulatory;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Core\ObservationState;
use PrivacyEvidence\Evidence\EvidenceType;
use PrivacyEvidence\Evidence\PrivacyEvidence;
use PrivacyEvidence\Regulatory\GdprProfile;
use PrivacyEvidence\Regulatory\ProfileEvaluator;

final class ProfileEvaluatorTest extends TestCase
{
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
