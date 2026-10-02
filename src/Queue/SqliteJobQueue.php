<?php

declare(strict_types=1);

namespace PrivacyEvidence\Queue;

use PDO;
use PrivacyEvidence\Core\Value;

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
        $countStatement = $this->pdo->query(
            'SELECT COUNT(*) FROM jobs WHERE status IN ("pending", "running")',
        );
        if ($countStatement === false) {
            throw new \RuntimeException('Unable to count queued jobs.');
        }
        $count = Value::int($countStatement->fetchColumn(), 'job_count');

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
                Value::string($row['payload_json'] ?? null, 'payload_json'),
                true,
                flags: JSON_THROW_ON_ERROR,
            );

            return new Job(
                id: Value::string($row['id'] ?? null, 'id'),
                runId: Value::string($row['run_id'] ?? null, 'run_id'),
                stage: Value::string($row['stage'] ?? null, 'stage'),
                deduplicationKey: Value::string(
                    $row['deduplication_key'] ?? null,
                    'deduplication_key',
                ),
                payload: Value::scalarMap($decoded, 'payload_json'),
                status: JobStatus::Running,
                attempts: Value::int($row['attempts'] ?? null, 'attempts') + 1,
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
            $result[Value::string($row['status'] ?? null, 'status')] = Value::int(
                $row['count'] ?? null,
                'count',
            );
        }

        return $result;
    }


    public function scheduledCount(
        string $runId,
        string $stage,
        string $deduplicationPrefix,
    ): int {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM jobs
             WHERE run_id = :run_id
               AND stage = :stage
               AND deduplication_key LIKE :prefix',
        );
        $stmt->execute([
            'run_id' => $runId,
            'stage' => $stage,
            'prefix' => $deduplicationPrefix . '%',
        ]);

        return Value::int($stmt->fetchColumn(), 'scheduled_count');
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
