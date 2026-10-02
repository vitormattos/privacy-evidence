<?php

declare(strict_types=1);

namespace PrivacyEvidence\Run;

use PDO;
use PDOStatement;

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

        $versionsDecoded = json_decode(
            (string) $row['versions_json'],
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $configurationDecoded = json_decode(
            (string) $row['configuration_json'],
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        if (!is_array($versionsDecoded) || !is_array($configurationDecoded)) {
            throw new \RuntimeException('Stored ResearchRun metadata is invalid.');
        }

        $versions = [];
        foreach ($versionsDecoded as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $versions[$key] = $value;
            }
        }

        $configuration = [];
        foreach ($configurationDecoded as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $configuration[$key] = $value;
            } elseif (is_array($value)) {
                $scalars = [];
                foreach ($value as $nestedKey => $nestedValue) {
                    if (is_scalar($nestedValue)) {
                        $scalars[$nestedKey] = $nestedValue;
                    }
                }
                $configuration[$key] = $scalars;
            }
        }

        return new ResearchRun(
            id: (string) $row['id'],
            startedAt: (string) $row['started_at'],
            gitCommit: (string) $row['git_commit'],
            datasetHash: (string) $row['dataset_hash'],
            protocolVersion: (string) $row['protocol_version'],
            versions: $versions,
            configuration: $configuration,
        );
    }

    public function telemetry(string $runId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT metric, value FROM run_telemetry WHERE run_id = :run_id ORDER BY metric',
        );
        $stmt->execute(['run_id' => $runId]);

        /** @var list<array{metric:string,value:string|int|float}> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $result = [];

        foreach ($rows as $row) {
            $result[$row['metric']] = is_numeric($row['value'])
                ? (float) $row['value']
                : $row['value'];
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
