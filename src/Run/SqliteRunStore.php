<?php

declare(strict_types=1);

namespace PrivacyEvidence\Run;

use PDO;
use PDOStatement;
use PrivacyEvidence\Core\Value;

final class SqliteRunStore implements RunStore
{
    public function __construct(private readonly PDO $pdo)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->migrate();
    }

    public function create(ResearchRun $run): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT OR REPLACE INTO research_runs
             (id, started_at, git_commit, dataset_hash, protocol_version, versions_json, configuration_json, status)
             VALUES (:id, :started_at, :git_commit, :dataset_hash, :protocol_version, :versions, :configuration, :status)',
        );

        $stmt->execute([
            'id' => $run->id,
            'started_at' => $run->startedAt,
            'git_commit' => $run->gitCommit,
            'dataset_hash' => $run->datasetHash,
            'protocol_version' => $run->protocolVersion,
            'versions' => json_encode($run->versions, JSON_THROW_ON_ERROR),
            'configuration' => json_encode($run->configuration, JSON_THROW_ON_ERROR),
            'status' => RunStatus::Created->value,
        ]);
    }

    public function setStatus(string $runId, RunStatus $status): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE research_runs SET status = :status WHERE id = :id',
        );
        $stmt->execute([
            'status' => $status->value,
            'id' => $runId,
        ]);
    }

    public function get(string $runId): ?ResearchRun
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM research_runs WHERE id = :id',
        );
        $stmt->execute(['id' => $runId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return null;
        }

        /** @psalm-suppress MixedAssignment */
        $versionsDecoded = json_decode(
            Value::string($row['versions_json'] ?? null, 'versions_json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        /** @psalm-suppress MixedAssignment */
        $configurationDecoded = json_decode(
            Value::string($row['configuration_json'] ?? null, 'configuration_json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        return new ResearchRun(
            id: Value::string($row['id'] ?? null, 'id'),
            startedAt: Value::string($row['started_at'] ?? null, 'started_at'),
            gitCommit: Value::string($row['git_commit'] ?? null, 'git_commit'),
            datasetHash: Value::string($row['dataset_hash'] ?? null, 'dataset_hash'),
            protocolVersion: Value::string($row['protocol_version'] ?? null, 'protocol_version'),
            versions: Value::stringMap($versionsDecoded, 'versions_json'),
            configuration: Value::configurationMap($configurationDecoded, 'configuration_json'),
        );
    }


    public function status(string $runId): ?RunStatus
    {
        $stmt = $this->pdo->prepare(
            'SELECT status FROM research_runs WHERE id = :id',
        );
        $stmt->execute(['id' => $runId]);
        $value = $stmt->fetchColumn();

        return is_string($value) ? RunStatus::tryFrom($value) : null;
    }

    public function telemetry(string $runId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT metric, value FROM run_telemetry WHERE run_id = :run_id ORDER BY metric',
        );
        $stmt->execute(['run_id' => $runId]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $result = [];

        foreach ($rows as $row) {
            $metric = Value::string($row['metric'] ?? null, 'metric');
            /** @psalm-suppress MixedAssignment */
            $rawValue = $row['value'];
            $result[$metric] = is_numeric($rawValue)
                ? Value::float($rawValue, 'value')
                : Value::string($rawValue, 'value');
        }

        return $result;
    }

    public function increment(string $runId, string $metric, int|float $amount = 1): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO run_telemetry (run_id, metric, value)
             VALUES (:run_id, :metric, :value)
             ON CONFLICT(run_id, metric)
             DO UPDATE SET value = value + excluded.value',
        );
        $stmt->execute([
            'run_id' => $runId,
            'metric' => $metric,
            'value' => $amount,
        ]);
    }

    private function migrate(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS research_runs (
                id TEXT PRIMARY KEY,
                started_at TEXT NOT NULL,
                git_commit TEXT NOT NULL,
                dataset_hash TEXT NOT NULL,
                protocol_version TEXT NOT NULL,
                versions_json TEXT NOT NULL,
                configuration_json TEXT NOT NULL,
                status TEXT NOT NULL
            )',
        );
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS run_telemetry (
                run_id TEXT NOT NULL,
                metric TEXT NOT NULL,
                value REAL NOT NULL DEFAULT 0,
                PRIMARY KEY (run_id, metric)
            )',
        );
    }
}
