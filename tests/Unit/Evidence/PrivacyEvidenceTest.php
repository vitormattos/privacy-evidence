<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Unit\Evidence;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Core\ObservationState;
use PrivacyEvidence\Evidence\EvidenceType;
use PrivacyEvidence\Evidence\PrivacyEvidence;

final class PrivacyEvidenceTest extends TestCase
{
    public function testRejectsInvalidConfidence(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new PrivacyEvidence(
            type: EvidenceType::PrivacyNotice,
            state: ObservationState::Present,
            resourceId: 'site-1',
            artifactHash: str_repeat('a', 64),
            sourceUrl: 'https://example.test/privacy',
            detector: 'test',
            detectorVersion: '1.0.0',
            method: 'fixture',
            confidence: 1.1,
        );
    }
}
