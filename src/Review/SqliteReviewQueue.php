<?php

declare(strict_types=1);

namespace PrivacyEvidence\Review;

use PDO;
use PDOStatement;
use PrivacyEvidence\Core\Value;

final class SqliteReviewQueue implements ReviewQueue
{
    public function __construct(private readonly PDO $pdo)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->migrate();
    }

    public function enqueue(string $runId, string $evidenceId, string $payload): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT OR IGNORE INTO review_queue (run_id, evidence_id, payload, status)
             VALUES (:run_id, :id, :payload, "pending")',
        );
        $stmt->execute([
            'run_id' => $runId,
            'id' => $evidenceId,
            'payload' => $payload,
        ]);
    }

    public function pending(?string $runId = null): array
    {
        if ($runId === null) {
            $stmt = $this->pdo->query(
                'SELECT run_id, evidence_id, payload, status
                 FROM review_queue
                 WHERE status = "pending"
                 ORDER BY run_id, evidence_id',
            );
        } else {
            $stmt = $this->pdo->prepare(
                'SELECT run_id, evidence_id, payload, status
                 FROM review_queue
                 WHERE run_id = :run_id AND status = "pending"
                 ORDER BY evidence_id',
            );
            $stmt->execute(['run_id' => $runId]);
        }

        if (!$stmt instanceof PDOStatement) {
            throw new \RuntimeException('Unable to query review queue.');
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $result = [];

        foreach ($rows as $row) {
            $result[] = [
                'run_id' => Value::string($row['run_id'] ?? null, 'run_id'),
                'evidence_id' => Value::string($row['evidence_id'] ?? null, 'evidence_id'),
                'payload' => Value::string($row['payload'] ?? null, 'payload'),
                'status' => Value::string($row['status'] ?? null, 'status'),
            ];
        }

        return $result;
    }

    public function decide(ReviewDecision $decision): void
    {
        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO review_decisions
                 (run_id, evidence_id, type, state, reviewer_type, reviewer_id, reviewed_at, rationale)
                 VALUES (:run_id, :evidence_id, :type, :state, :reviewer_type,
                         :reviewer_id, :reviewed_at, :rationale)',
            );
            $stmt->execute([
                'run_id' => $decision->runId,
                'evidence_id' => $decision->evidenceId,
                'type' => $decision->type->value,
                'state' => $decision->state->value,
                'reviewer_type' => $decision->reviewerType->value,
                'reviewer_id' => $decision->reviewerId,
                'reviewed_at' => $decision->reviewedAt,
                'rationale' => $decision->rationale,
            ]);

            $update = $this->pdo->prepare(
                'UPDATE review_queue
                 SET status = "reviewed"
                 WHERE run_id = :run_id AND evidence_id = :id',
            );
            $update->execute([
                'run_id' => $decision->runId,
                'id' => $decision->evidenceId,
            ]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();

            throw $e;
        }
    }


    public function decisions(string $runId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT evidence_id, type, state, reviewer_type, reviewer_id, reviewed_at, rationale
             FROM review_decisions
             WHERE run_id = :run_id
             ORDER BY evidence_id, reviewed_at, id',
        );
        $stmt->execute(['run_id' => $runId]);

        $records = [];
        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            $records[] = [
                'evidenceId' => Value::string($row['evidence_id'] ?? null, 'evidence_id'),
                'type' => Value::string($row['type'] ?? null, 'type'),
                'state' => Value::string($row['state'] ?? null, 'state'),
                'reviewerType' => Value::string($row['reviewer_type'] ?? null, 'reviewer_type'),
                'reviewerId' => Value::string($row['reviewer_id'] ?? null, 'reviewer_id'),
                'reviewedAt' => Value::string($row['reviewed_at'] ?? null, 'reviewed_at'),
                'rationale' => Value::string($row['rationale'] ?? null, 'rationale'),
            ];
        }

        return $records;
    }

    private function migrate(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS review_queue (
                run_id TEXT NOT NULL,
                evidence_id TEXT NOT NULL,
                payload TEXT NOT NULL,
                status TEXT NOT NULL,
                PRIMARY KEY (run_id, evidence_id)
            )',
        );

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS review_decisions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                run_id TEXT NOT NULL,
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
