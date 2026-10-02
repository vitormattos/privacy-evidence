<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Unit\Analysis;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Analysis\InterRaterAgreement;
use PrivacyEvidence\Core\ObservationState;
use PrivacyEvidence\Evidence\EvidenceType;
use PrivacyEvidence\Review\ReviewDecision;
use PrivacyEvidence\Review\ReviewerType;

final class InterRaterAgreementTest extends TestCase
{
    public function testCalculatesPerSignalCohenKappaAndDisagreements(): void
    {
        $decisions = [
            $this->decision('e1', 'a', ObservationState::Present),
            $this->decision('e2', 'a', ObservationState::Present),
            $this->decision('e3', 'a', ObservationState::Absent),
            $this->decision('e4', 'a', ObservationState::Absent),
            $this->decision('e1', 'b', ObservationState::Present),
            $this->decision('e2', 'b', ObservationState::Absent),
            $this->decision('e3', 'b', ObservationState::Absent),
            $this->decision('e4', 'b', ObservationState::Absent),
        ];

        $result = (new InterRaterAgreement())->compare($decisions, 'a', 'b');
        $privacy = $result['privacy_notice'];

        self::assertSame(4, $privacy['sampleSize']);
        self::assertSame(0.75, $privacy['observedAgreement']);
        self::assertEqualsWithDelta(0.5, $privacy['expectedAgreement'], 0.00001);
        self::assertEqualsWithDelta(0.5, $privacy['kappa'], 0.00001);
        self::assertCount(1, $privacy['disagreements']);
        self::assertSame('e2', $privacy['disagreements'][0]['evidenceId']);
    }

    private function decision(
        string $evidenceId,
        string $reviewerId,
        ObservationState $state,
    ): ReviewDecision {
        return new ReviewDecision(
            runId: 'run',
            evidenceId: $evidenceId,
            type: EvidenceType::PrivacyNotice,
            state: $state,
            reviewerType: ReviewerType::Human,
            reviewerId: $reviewerId,
            reviewedAt: '2026-10-02T00:00:00Z',
            rationale: 'test',
        );
    }
}
