<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Integration\Review;

use PDO;
use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Core\ObservationState;
use PrivacyEvidence\Evidence\EvidenceType;
use PrivacyEvidence\Review\ReviewDecision;
use PrivacyEvidence\Review\ReviewerType;
use PrivacyEvidence\Review\SqliteReviewQueue;

final class SqliteReviewQueueTest extends TestCase
{
    public function testQueueAndDecisionAreAuditablePerRun(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite not available');
        }

        $queue = new SqliteReviewQueue(new PDO('sqlite::memory:'));
        $queue->enqueue('run-1', 'evidence-1', '{"type":"privacy_notice"}');
        $queue->enqueue('run-2', 'evidence-1', '{"type":"privacy_notice"}');

        self::assertCount(1, $queue->pending('run-1'));
        self::assertCount(2, $queue->pending());

        $queue->decide(new ReviewDecision(
            'run-1',
            'evidence-1',
            EvidenceType::PrivacyNotice,
            ObservationState::Present,
            ReviewerType::Human,
            'reviewer-1',
            '2026-10-02T00:00:00Z',
            'Policy page explicitly observed.',
        ));

        self::assertSame([], $queue->pending('run-1'));
        self::assertCount(1, $queue->pending('run-2'));
    }

    public function testAiCannotMasqueradeAsHuman(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ReviewDecision(
            'run-1',
            'x',
            EvidenceType::PrivacyNotice,
            ObservationState::Present,
            ReviewerType::Human,
            'ai:model',
            '2026-10-02T00:00:00Z',
            'bad',
        );
    }
}
