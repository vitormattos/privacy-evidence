<?php

declare(strict_types=1);

namespace PrivacyEvidence\Queue;

use PDO;

final class SqliteJobQueue implements JobQueue
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly int $maxPending = 10000,
    ) {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->migrate();
    }

    public function enqueue(Job $job): void
    {
        $count = (int) $this->pdo->query(
            'SELECT COUNT(*) FROM jobs WHERE status IN ("pending", "running")',
        )->fetchColumn();

        if ($count >= $this->maxPending) {
            throw new \RuntimeException('Job queue capacity reached; backpressure applied.');
        }

        $stmt = $this->pdo->prepare(
            'INSERT OR IGNORE INTO jobs
             (id, run_id, stage, deduplication_key, payload_json, status, attempts)
             VALUES (:id, :run_id, :stage, :deduplication_key, :payload, :status, :attempts)',
        );
        $stmt->execute([
            'id' => $job->id,
            'run_id' => $job->runId,
            'stage' => $job->stage,
            'deduplication_key' => $job->deduplicationKey,
            'payload' => json_encode($job->payload, JSON_THROW_ON_ERROR),
            'status' => $job->status->value,
            'attempts' => $job->attempts,
        ]);
    }

    public function reserve(string $runId, string $stage): ?Job
    {
        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare(
                'SELECT * FROM jobs
                 WHERE run_id = :run_id AND stage = :stage AND status = "pending"
                 ORDER BY id
                 LIMIT 1',
            );
            $stmt->execute([
                'run_id' => $runId,
                'stage' => $stage,
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!is_array($row)) {
                $this->pdo->commit();

                return null;
            }

            $update = $this->pdo->prepare(
                'UPDATE jobs SET status = "running", attempts = attempts + 1 WHERE id = :id',
            );
            $update->execute(['id' => $row['id']]);
            $this->pdo->commit();

            $decoded = json_decode(
                (string) $row['payload_json'],
                true,
                flags: JSON_THROW_ON_ERROR,
            );
            if (!is_array($decoded)) {
                throw new \RuntimeException('Stored job payload is invalid.');
            }

            $payload = [];
            foreach ($decoded as $key => $value) {
                if (is_string($key) && (is_scalar($value) || $value === null)) {
                    $payload[$key] = $value;
                }
            }

            return new Job(
                id: (string) $row['id'],
                runId: (string) $row['run_id'],
                stage: (string) $row['stage'],
                deduplicationKey: (string) $row['deduplication_key'],
                payload: $payload,
                status: JobStatus::Running,
                attempts: (int) $row['attempts'] + 1,
            );
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    public function complete(string $jobId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE jobs SET status = "completed", last_error = NULL WHERE id = :id',
        );
        $stmt->execute(['id' => $jobId]);
    }

    public function fail(string $jobId, string $error, int $maxAttempts = 3): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE jobs
             SET status = CASE WHEN attempts >= :max_attempts THEN "dead" ELSE "pending" END,
                 last_error = :error
             WHERE id = :id',
        );
        $stmt->execute([
            'max_attempts' => $maxAttempts,
            'error' => $error,
            'id' => $jobId,
        ]);
    }

    public function counts(string $runId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT status, COUNT(*) AS count
             FROM jobs
             WHERE run_id = :run_id
             GROUP BY status',
        );
        $stmt->execute(['run_id' => $runId]);

        $result = [];
        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            $result[(string) $row['status']] = (int) $row['count'];
        }

        return $result;
    }

    private function migrate(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS jobs (
                id TEXT PRIMARY KEY,
                run_id TEXT NOT NULL,
                stage TEXT NOT NULL,
                deduplication_key TEXT NOT NULL,
                payload_json TEXT NOT NULL,
                status TEXT NOT NULL,
                attempts INTEGER NOT NULL DEFAULT 0,
                last_error TEXT,
                UNIQUE(run_id, stage, deduplication_key)
            )',
        );
        $this->pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_jobs_run_stage_status
             ON jobs(run_id, stage, status)',
        );
    }
}
