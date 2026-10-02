<?php

declare(strict_types=1);

namespace PrivacyEvidence\Review;

use PDO;
use PDOStatement;

final class SqliteReviewQueue implements ReviewQueue
{
    public function __construct(private readonly PDO $pdo)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->migrate();
    }

    public function enqueue(string $evidenceId, string $payload): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT OR IGNORE INTO review_queue (evidence_id, payload, status) VALUES (:id, :payload, "pending")',
        );
        $stmt->execute([
            'id' => $evidenceId,
            'payload' => $payload,
        ]);
    }

    public function pending(): array
    {
        $stmt = $this->pdo->query(
            'SELECT evidence_id, payload, status
             FROM review_queue
             WHERE status = "pending"
             ORDER BY evidence_id',
        );

        if (!$stmt instanceof PDOStatement) {
            throw new \RuntimeException('Unable to query review queue.');
        }

        /** @var list<array{evidence_id:string,payload:string,status:string}> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $rows;
    }

    public function decide(ReviewDecision $decision): void
    {
        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO review_decisions
                 (evidence_id, type, state, reviewer_type, reviewer_id, reviewed_at, rationale)
                 VALUES (:evidence_id, :type, :state, :reviewer_type, :reviewer_id, :reviewed_at, :rationale)',
            );
            $stmt->execute([
                'evidence_id' => $decision->evidenceId,
                'type' => $decision->type->value,
                'state' => $decision->state->value,
                'reviewer_type' => $decision->reviewerType->value,
                'reviewer_id' => $decision->reviewerId,
                'reviewed_at' => $decision->reviewedAt,
                'rationale' => $decision->rationale,
            ]);

            $update = $this->pdo->prepare(
                'UPDATE review_queue SET status = "reviewed" WHERE evidence_id = :id',
            );
            $update->execute(['id' => $decision->evidenceId]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();

            throw $e;
        }
    }

    private function migrate(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS review_queue (
                evidence_id TEXT PRIMARY KEY,
                payload TEXT NOT NULL,
                status TEXT NOT NULL
            )',
        );

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS review_decisions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                evidence_id TEXT NOT NULL,
                type TEXT NOT NULL,
                state TEXT NOT NULL,
                reviewer_type TEXT NOT NULL,
                reviewer_id TEXT NOT NULL,
                reviewed_at TEXT NOT NULL,
                rationale TEXT NOT NULL
            )',
        );
    }
}
