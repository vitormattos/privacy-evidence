<?php

declare(strict_types=1);

namespace PrivacyEvidence\Queue;

use PDO;
use PDOException;
use PrivacyEvidence\Core\Value;
use PrivacyEvidence\Storage\SqliteRetry;

final class SqliteJobQueue implements JobQueue
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly int $maxPending = 10000,
        private readonly int $retryBaseDelayMs = 500,
    ) {
        if ($maxPending <= 0 || $retryBaseDelayMs < 0) {
            throw new \InvalidArgumentException('Invalid queue limits.');
        }
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->migrate();
    }

    public function enqueue(Job $job): bool
    {
        $existing = $this->pdo->prepare(
            'SELECT 1 FROM jobs
             WHERE run_id = :run_id
               AND stage = :stage
               AND deduplication_key = :deduplication_key
             LIMIT 1',
        );
        SqliteRetry::execute($existing, [
            'run_id' => $job->runId,
            'stage' => $job->stage,
            'deduplication_key' => $job->deduplicationKey,
        ]);
        if ($existing->fetchColumn() !== false) {
            return false;
        }

        $countStatement = $this->pdo->query(
            'SELECT COUNT(*) FROM jobs WHERE status IN ("pending", "running")',
        );
        if ($countStatement === false) {
            throw new \RuntimeException('Unable to count queued jobs.');
        }

        if (Value::int($countStatement->fetchColumn(), 'job_count') >= $this->maxPending) {
            throw new \RuntimeException('Job queue capacity reached; backpressure applied.');
        }

        $enqueuedAtMs = $job->enqueuedAtMs > 0 ? $job->enqueuedAtMs : self::nowMs();

        $stmt = $this->pdo->prepare(
            'INSERT OR IGNORE INTO jobs
             (id, run_id, stage, deduplication_key, payload_json, status, attempts,
              priority, host, enqueued_at_ms, available_at_ms, reserved_at_ms)
             VALUES (:id, :run_id, :stage, :deduplication_key, :payload, :status, :attempts,
                     :priority, :host, :enqueued_at_ms, :available_at_ms, NULL)',
        );
        SqliteRetry::execute($stmt, [
            'id' => $job->id,
            'run_id' => $job->runId,
            'stage' => $job->stage,
            'deduplication_key' => $job->deduplicationKey,
            'payload' => json_encode($job->payload, JSON_THROW_ON_ERROR),
            'status' => $job->status->value,
            'attempts' => $job->attempts,
            'priority' => $job->priority,
            'host' => $job->host,
            'enqueued_at_ms' => $enqueuedAtMs,
            'available_at_ms' => $enqueuedAtMs,
        ]);

        return $stmt->rowCount() === 1;
    }

    public function reserve(
        string $runId,
        string $stage,
        int $perHostConcurrency = 2,
        int $minHostDelayMs = 0,
    ): ?Job {
        if ($perHostConcurrency <= 0 || $minHostDelayMs < 0) {
            throw new \InvalidArgumentException('Invalid host scheduling limits.');
        }

        $now = self::nowMs();
        $stmt = $this->pdo->prepare(
            'SELECT * FROM jobs
             WHERE run_id = :run_id
               AND stage = :stage
               AND status = "pending"
               AND available_at_ms <= :now
             ORDER BY priority DESC, enqueued_at_ms ASC, id ASC
             LIMIT 100',
        );
        SqliteRetry::execute($stmt, [
            'run_id' => $runId,
            'stage' => $stage,
            'now' => $now,
        ]);

        /** @var list<array<string,mixed>> $candidates */
        $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($candidates as $row) {
            $host = ($row['host'] ?? null) === null
                ? null
                : Value::string($row['host'], 'host');
            $id = Value::string($row['id'] ?? null, 'id');

            $this->beginImmediateWithRetry();

            try {
                if (
                    $host !== null
                    && !$this->hostEligible(
                        $runId,
                        $stage,
                        $host,
                        $perHostConcurrency,
                        $minHostDelayMs,
                        $now,
                    )
                ) {
                    $this->pdo->commit();
                    continue;
                }

                $update = $this->pdo->prepare(
                    'UPDATE jobs
                     SET status = "running",
                         attempts = attempts + 1,
                         reserved_at_ms = :reserved_at
                     WHERE id = :id
                       AND status = "pending"
                       AND available_at_ms <= :now',
                );
                SqliteRetry::execute($update, [
                    'reserved_at' => $now,
                    'id' => $id,
                    'now' => $now,
                ]);

                if ($update->rowCount() !== 1) {
                    $this->pdo->commit();
                    continue;
                }

                if ($host !== null) {
                    $limit = $this->pdo->prepare(
                        'INSERT INTO host_limiter
                         (run_id, stage, host, last_started_at_ms)
                         VALUES (:run_id, :stage, :host, :started)
                         ON CONFLICT(run_id, stage, host)
                         DO UPDATE SET last_started_at_ms = excluded.last_started_at_ms',
                    );
                    SqliteRetry::execute($limit, [
                        'run_id' => $runId,
                        'stage' => $stage,
                        'host' => $host,
                        'started' => $now,
                    ]);
                }

                $attemptsStatement = $this->pdo->prepare(
                    'SELECT attempts FROM jobs WHERE id = :id',
                );
                SqliteRetry::execute($attemptsStatement, ['id' => $id]);
                $attempts = Value::int($attemptsStatement->fetchColumn(), 'attempts');

                $this->pdo->commit();

                /** @psalm-suppress MixedAssignment */
                $decoded = json_decode(
                    Value::string($row['payload_json'] ?? null, 'payload_json'),
                    true,
                    flags: JSON_THROW_ON_ERROR,
                );

                return new Job(
                    id: $id,
                    runId: Value::string($row['run_id'] ?? null, 'run_id'),
                    stage: Value::string($row['stage'] ?? null, 'stage'),
                    deduplicationKey: Value::string(
                        $row['deduplication_key'] ?? null,
                        'deduplication_key',
                    ),
                    payload: Value::scalarMap($decoded, 'payload_json'),
                    status: JobStatus::Running,
                    attempts: $attempts,
                    priority: Value::int($row['priority'] ?? 0, 'priority'),
                    host: $host,
                    enqueuedAtMs: Value::int($row['enqueued_at_ms'] ?? 0, 'enqueued_at_ms'),
                    reservedAtMs: $now,
                );
            } catch (\Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }

                throw $e;
            }
        }

        return null;
    }

    public function complete(string $jobId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE jobs SET status = "completed", last_error = NULL WHERE id = :id',
        );
        SqliteRetry::execute($stmt, ['id' => $jobId]);
    }

    public function fail(
        string $jobId,
        string $error,
        int $maxAttempts = 3,
        JobStatus $terminalStatus = JobStatus::Dead,
        ?int $retryDelayMs = null,
    ): JobStatus {
        if ($maxAttempts <= 0) {
            throw new \InvalidArgumentException('maxAttempts must be positive.');
        }
        if (!in_array($terminalStatus, [JobStatus::Failed, JobStatus::Dead], true)) {
            throw new \InvalidArgumentException('Terminal failure status must be failed or dead.');
        }
        if ($retryDelayMs !== null && $retryDelayMs < 0) {
            throw new \InvalidArgumentException('Retry delay cannot be negative.');
        }

        $stmt = $this->pdo->prepare(
            'SELECT attempts FROM jobs WHERE id = :id',
        );
        SqliteRetry::execute($stmt, ['id' => $jobId]);
        $attempts = Value::int($stmt->fetchColumn(), 'attempts');
        $status = $attempts >= $maxAttempts ? $terminalStatus : JobStatus::Pending;
        $exponent = min(max($attempts - 1, 0), 16);
        $retryMultiplier = 1 << $exponent;
        $delayMs = $status === JobStatus::Pending
            ? ($retryDelayMs ?? min(30_000, $this->retryBaseDelayMs * $retryMultiplier))
            : 0;

        $update = $this->pdo->prepare(
            'UPDATE jobs
             SET status = :status,
                 last_error = :error,
                 available_at_ms = :available_at,
                 reserved_at_ms = NULL
             WHERE id = :id',
        );
        SqliteRetry::execute($update, [
            'status' => $status->value,
            'error' => $error,
            'available_at' => self::nowMs() + $delayMs,
            'id' => $jobId,
        ]);

        return $status;
    }

    public function deferHost(string $runId, string $stage, string $host, int $delayMs): void
    {
        if ($delayMs < 0) {
            throw new \InvalidArgumentException('Host defer delay cannot be negative.');
        }

        $until = self::nowMs() + $delayMs;
        $stmt = $this->pdo->prepare(
            'UPDATE jobs
             SET available_at_ms = MAX(available_at_ms, :until)
             WHERE run_id = :run_id
               AND stage = :stage
               AND host = :host
               AND status = "pending"',
        );
        SqliteRetry::execute($stmt, [
            'until' => $until,
            'run_id' => $runId,
            'stage' => $stage,
            'host' => $host,
        ]);
    }

    public function requeueRunning(string $runId): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE jobs
             SET status = "pending", reserved_at_ms = NULL, available_at_ms = :now
             WHERE run_id = :run_id AND status = "running"',
        );
        SqliteRetry::execute($stmt, [
            'now' => self::nowMs(),
            'run_id' => $runId,
        ]);

        return $stmt->rowCount();
    }

    public function counts(string $runId): array
    {
        return $this->countQuery(
            'SELECT status, COUNT(*) AS count
             FROM jobs
             WHERE run_id = :run_id
             GROUP BY status',
            ['run_id' => $runId],
        );
    }

    public function stageCounts(string $runId, string $stage): array
    {
        return $this->countQuery(
            'SELECT status, COUNT(*) AS count
             FROM jobs
             WHERE run_id = :run_id AND stage = :stage
             GROUP BY status',
            ['run_id' => $runId, 'stage' => $stage],
        );
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
        SqliteRetry::execute($stmt, [
            'run_id' => $runId,
            'stage' => $stage,
            'prefix' => $deduplicationPrefix . '%',
        ]);

        return Value::int($stmt->fetchColumn(), 'scheduled_count');
    }

    public function failures(string $runId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, stage, status, attempts, payload_json, last_error
             FROM jobs
             WHERE run_id = :run_id
               AND last_error IS NOT NULL
             ORDER BY stage, id',
        );
        SqliteRetry::execute($stmt, ['run_id' => $runId]);

        $failures = [];
        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            /** @psalm-suppress MixedAssignment */
            $payload = json_decode(
                Value::string($row['payload_json'] ?? null, 'payload_json'),
                true,
                flags: JSON_THROW_ON_ERROR,
            );
            /** @psalm-suppress MixedAssignment */
            $urlValue = is_array($payload) ? ($payload['url'] ?? null) : null;
            $url = is_string($urlValue) ? $urlValue : null;
            /** @psalm-suppress MixedAssignment */
            $resourceIdValue = is_array($payload) ? ($payload['resource_id'] ?? null) : null;
            $resourceId = is_string($resourceIdValue) ? $resourceIdValue : null;

            $failures[] = [
                'id' => Value::string($row['id'] ?? null, 'id'),
                'stage' => Value::string($row['stage'] ?? null, 'stage'),
                'status' => Value::string($row['status'] ?? null, 'status'),
                'attempts' => Value::int($row['attempts'] ?? null, 'attempts'),
                'url' => $url,
                'resourceId' => $resourceId,
                'error' => Value::string($row['last_error'] ?? null, 'last_error'),
            ];
        }

        return $failures;
    }

    /**
     * @param array<string,scalar> $parameters
     * @return array<string,int>
     */
    private function countQuery(string $sql, array $parameters): array
    {
        $stmt = $this->pdo->prepare($sql);
        SqliteRetry::execute($stmt, $parameters);

        $result = [];
        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            $result[Value::string($row['status'] ?? null, 'status')] = Value::int(
                $row['count'] ?? null,
                'count',
            );
        }

        return $result;
    }

    private function hostEligible(
        string $runId,
        string $stage,
        string $host,
        int $perHostConcurrency,
        int $minHostDelayMs,
        int $now,
    ): bool {
        $running = $this->pdo->prepare(
            'SELECT COUNT(*) FROM jobs
             WHERE run_id = :run_id
               AND stage = :stage
               AND host = :host
               AND status = "running"',
        );
        SqliteRetry::execute($running, [
            'run_id' => $runId,
            'stage' => $stage,
            'host' => $host,
        ]);
        if (Value::int($running->fetchColumn(), 'running_host_jobs') >= $perHostConcurrency) {
            return false;
        }

        if ($minHostDelayMs === 0) {
            return true;
        }

        $last = $this->pdo->prepare(
            'SELECT last_started_at_ms FROM host_limiter
             WHERE run_id = :run_id AND stage = :stage AND host = :host',
        );
        SqliteRetry::execute($last, [
            'run_id' => $runId,
            'stage' => $stage,
            'host' => $host,
        ]);
        /** @psalm-suppress MixedAssignment */
        $value = $last->fetchColumn();

        return $value === false
            || ($now - Value::int($value, 'last_started_at_ms')) >= $minHostDelayMs;
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
                priority INTEGER NOT NULL DEFAULT 0,
                host TEXT,
                enqueued_at_ms INTEGER NOT NULL DEFAULT 0,
                available_at_ms INTEGER NOT NULL DEFAULT 0,
                reserved_at_ms INTEGER,
                UNIQUE(run_id, stage, deduplication_key)
            )',
        );

        foreach (
            [
                'ALTER TABLE jobs ADD COLUMN priority INTEGER NOT NULL DEFAULT 0',
                'ALTER TABLE jobs ADD COLUMN host TEXT',
                'ALTER TABLE jobs ADD COLUMN enqueued_at_ms INTEGER NOT NULL DEFAULT 0',
                'ALTER TABLE jobs ADD COLUMN available_at_ms INTEGER NOT NULL DEFAULT 0',
                'ALTER TABLE jobs ADD COLUMN reserved_at_ms INTEGER',
            ] as $alter
        ) {
            try {
                $this->pdo->exec($alter);
            } catch (PDOException $e) {
                if (!str_contains(strtolower($e->getMessage()), 'duplicate column')) {
                    throw $e;
                }
            }
        }

        $this->pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_jobs_run_stage_status
             ON jobs(run_id, stage, status, priority DESC, enqueued_at_ms)',
        );
        $this->pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_jobs_host_status
             ON jobs(run_id, stage, host, status)',
        );
        $this->pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_jobs_available
             ON jobs(run_id, stage, status, available_at_ms, priority DESC, enqueued_at_ms)',
        );
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS host_limiter (
                run_id TEXT NOT NULL,
                stage TEXT NOT NULL,
                host TEXT NOT NULL,
                last_started_at_ms INTEGER NOT NULL,
                PRIMARY KEY (run_id, stage, host)
            )',
        );
    }

    private function beginImmediateWithRetry(): void
    {
        $attempt = 0;
        $deadline = microtime(true) + 30.0;

        while (true) {
            try {
                $this->pdo->exec('BEGIN IMMEDIATE');
                return;
            } catch (PDOException $exception) {
                if (!SqliteRetry::isBusy($exception) || microtime(true) >= $deadline) {
                    throw $exception;
                }

                $delayUs = min(250_000, 10_000 * (1 << min($attempt, 5)));
                usleep($delayUs);
                $attempt++;
            }
        }
    }

    private static function nowMs(): int
    {
        return (int) floor(microtime(true) * 1000.0);
    }
}
