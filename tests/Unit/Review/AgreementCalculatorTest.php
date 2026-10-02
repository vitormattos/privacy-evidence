<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Unit\Review;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Core\ObservationState;
use PrivacyEvidence\Evidence\EvidenceType;
use PrivacyEvidence\Review\AgreementCalculator;
use PrivacyEvidence\Review\ReviewDecision;
use PrivacyEvidence\Review\ReviewerType;

final class AgreementCalculatorTest extends TestCase
{
    public function testCohenKappaIsCalculatedPerEvidenceType(): void
    {
        $decisions = [
            $this->decision('1', 'a', ObservationState::Present),
            $this->decision('1', 'b', ObservationState::Present),
            $this->decision('2', 'a', ObservationState::Present),
            $this->decision('2', 'b', ObservationState::Absent),
            $this->decision('3', 'a', ObservationState::Absent),
            $this->decision('3', 'b', ObservationState::Absent),
            $this->decision('4', 'a', ObservationState::Absent),
            $this->decision('4', 'b', ObservationState::Absent),
        ];

        $result = (new AgreementCalculator())->cohenKappa($decisions, 'a', 'b');
        $metric = $result[EvidenceType::PrivacyNotice->value];

        self::assertSame(4, $metric['paired']);
        self::assertSame(0.75, $metric['observedAgreement']);
        self::assertSame(0.5, $metric['expectedAgreement']);
        self::assertSame(0.5, $metric['kappa']);
        self::assertSame(['absent', 'present'], $metric['categories']);
    }

    private function decision(
        string $evidenceId,
        string $reviewerId,
        ObservationState $state,
    ): ReviewDecision {
        return new ReviewDecision(
            'run',
            $evidenceId,
            EvidenceType::PrivacyNotice,
            $state,
            ReviewerType::Human,
            $reviewerId,
            '2026-10-02T00:00:00Z',
            'fixture rationale',
        );
    }
}
