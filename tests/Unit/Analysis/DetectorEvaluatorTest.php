<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Unit\Analysis;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Analysis\DetectorEvaluator;
use PrivacyEvidence\Core\ObservationState;
use PrivacyEvidence\Evidence\EvidenceType;
use PrivacyEvidence\Evidence\PrivacyEvidence;
use PrivacyEvidence\Review\ReviewDecision;
use PrivacyEvidence\Review\ReviewerType;

final class DetectorEvaluatorTest extends TestCase
{
    public function testReportsPerSignalMetricsAbstentionAndTraceableErrors(): void
    {
        $tp = $this->evidence('r1', 'a1', ObservationState::Present);
        $fp = $this->evidence('r2', 'a2', ObservationState::Present);
        $fn = $this->evidence('r3', 'a3', ObservationState::Absent);
        $tn = $this->evidence('r4', 'a4', ObservationState::Absent);
        $abstained = $this->evidence('r5', 'a5', ObservationState::Unknown);

        $truth = [
            $this->truth($tp, ObservationState::Present),
            $this->truth($fp, ObservationState::Absent),
            $this->truth($fn, ObservationState::Present),
            $this->truth($tn, ObservationState::Absent),
            $this->truth($abstained, ObservationState::Present),
        ];

        $result = (new DetectorEvaluator())->evaluate(
            [$tp, $fp, $fn, $tn, $abstained],
            $truth,
            '1.2.0',
            'gold-2026-01',
        );

        self::assertSame('1.2.0', $result['protocolVersion']);
        self::assertSame('gold-2026-01', $result['goldDatasetVersion']);

        $metric = $result['signals']['privacy_notice'];
        self::assertSame(1, $metric['confusionMatrix']['truePositive']);
        self::assertSame(1, $metric['confusionMatrix']['falsePositive']);
        self::assertSame(1, $metric['confusionMatrix']['trueNegative']);
        self::assertSame(1, $metric['confusionMatrix']['falseNegative']);
        self::assertSame(0.5, $metric['precision']);
        self::assertSame(0.5, $metric['recall']);
        self::assertSame(0.5, $metric['f1']);
        self::assertSame(2, $metric['support']);
        self::assertSame(1, $metric['abstained']);
        self::assertSame(0.8, $metric['coverage']);
        self::assertSame($fp->id(), $metric['falsePositives'][0]['evidenceId']);
        self::assertSame('a2', $metric['falsePositives'][0]['artifactHash']);
        self::assertSame($fn->id(), $metric['falseNegatives'][0]['evidenceId']);
        self::assertSame(['fixture@1.0.0'], $metric['detectors']);
    }

    private function evidence(
        string $resource,
        string $artifact,
        ObservationState $state,
    ): PrivacyEvidence {
        return new PrivacyEvidence(
            type: EvidenceType::PrivacyNotice,
            state: $state,
            resourceId: $resource,
            artifactHash: $artifact,
            sourceUrl: 'https://example.test/' . $resource,
            detector: 'fixture',
            detectorVersion: '1.0.0',
            method: 'fixture',
        );
    }

    private function truth(
        PrivacyEvidence $evidence,
        ObservationState $state,
    ): ReviewDecision {
        return new ReviewDecision(
            runId: 'run-1',
            evidenceId: $evidence->id(),
            type: $evidence->type,
            state: $state,
            reviewerType: ReviewerType::Human,
            reviewerId: 'human:reviewer-1',
            reviewedAt: '2026-10-02T00:00:00Z',
            rationale: 'fixture',
        );
    }
}
