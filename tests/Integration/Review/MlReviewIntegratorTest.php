<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Integration\Review;

use PDO;
use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Core\ObservationState;
use PrivacyEvidence\Evidence\EvidenceType;
use PrivacyEvidence\Evidence\PrivacyEvidence;
use PrivacyEvidence\Experiment\Review\MlReviewIntegrator;
use PrivacyEvidence\Experiment\Rubix\RubixSignalModelMetadata;
use PrivacyEvidence\Experiment\Rubix\RubixSignalPrediction;
use PrivacyEvidence\Review\ReviewerType;
use PrivacyEvidence\Review\SqliteReviewQueue;

final class MlReviewIntegratorTest extends TestCase
{
    public function testDisagreementKeepsHumanReviewPendingAndStoresAuditableAiSuggestion(): void
    {
        $queue = new SqliteReviewQueue(new PDO('sqlite::memory:'));
        $evidence = new PrivacyEvidence(
            EvidenceType::ControllerIdentity,
            ObservationState::Absent,
            'resource-1',
            str_repeat('a', 64),
            'https://example.test/privacy',
            'privacy_contact_dpo',
            '1.0.0',
            'rule_based_text',
        );
        $prediction = new RubixSignalPrediction(
            EvidenceType::ControllerIdentity->value,
            0.92,
            true,
            0.5,
            new RubixSignalModelMetadata(
                EvidenceType::ControllerIdentity->value,
                'fixture',
                'v1',
                str_repeat('b', 64),
                str_repeat('c', 64),
                '1.0.0',
                '1.0.0',
                'Rubix\\ML\\Classifiers\\GaussianNB',
                '3.0.0-rc4',
                '2026-10-03T12:00:00Z',
            ),
            str_repeat('d', 64),
        );

        $assessment = (new MlReviewIntegrator($queue))->record(
            'run-1',
            $evidence,
            $prediction,
            '2026-10-03T12:05:00Z',
        );

        self::assertTrue($assessment->disagreesWithRule);
        self::assertTrue($assessment->needsReview);
        self::assertCount(1, $queue->pending('run-1'));

        $decisions = $queue->decisions('run-1');
        self::assertCount(1, $decisions);
        self::assertSame(ReviewerType::AiSuggestion->value, $decisions[0]['reviewerType'] ?? null);
        self::assertSame(ObservationState::Present->value, $decisions[0]['state'] ?? null);

        $rationale = json_decode((string) ($decisions[0]['rationale'] ?? ''), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($rationale);
        self::assertTrue($rationale['disagreesWithRule'] ?? false);
        self::assertSame(str_repeat('d', 64), $rationale['artifactSha256'] ?? null);
        self::assertSame(100, $rationale['reviewPriority'] ?? null);
    }

    public function testAiSuggestionDoesNotOverwriteExistingHumanDecision(): void
    {
        $queue = new SqliteReviewQueue(new PDO('sqlite::memory:'));
        $evidence = new PrivacyEvidence(
            EvidenceType::ControllerIdentity,
            ObservationState::Present,
            'resource-1',
            str_repeat('a', 64),
            'https://example.test/privacy',
            'privacy_contact_dpo',
            '1.0.0',
            'rule_based_text',
        );
        $queue->enqueue('run-1', $evidence->id(), json_encode($evidence->toArray(), JSON_THROW_ON_ERROR));

        $queue->decide(new \PrivacyEvidence\Review\ReviewDecision(
            'run-1',
            $evidence->id(),
            EvidenceType::ControllerIdentity,
            ObservationState::Present,
            ReviewerType::Human,
            'reviewer-a',
            '2026-10-03T12:00:00Z',
            'Human review.',
        ));

        $prediction = new RubixSignalPrediction(
            EvidenceType::ControllerIdentity->value,
            0.1,
            false,
            0.5,
            new RubixSignalModelMetadata(
                EvidenceType::ControllerIdentity->value,
                'fixture',
                'v1',
                str_repeat('b', 64),
                str_repeat('c', 64),
                '1.0.0',
                '1.0.0',
                'Rubix\\ML\\Classifiers\\GaussianNB',
                '3.0.0-rc4',
                '2026-10-03T12:00:00Z',
            ),
            str_repeat('d', 64),
        );

        (new MlReviewIntegrator($queue))->record(
            'run-1',
            $evidence,
            $prediction,
            '2026-10-03T12:05:00Z',
        );

        $decisions = $queue->decisions('run-1');
        self::assertCount(2, $decisions);
        self::assertSame(ReviewerType::Human->value, $decisions[0]['reviewerType'] ?? null);
        self::assertSame(ReviewerType::AiSuggestion->value, $decisions[1]['reviewerType'] ?? null);
    }
}
