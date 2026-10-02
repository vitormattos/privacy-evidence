<?php

declare(strict_types=1);

namespace PrivacyEvidence\Storage;

use PDO;
use PrivacyEvidence\Acquisition\FetchedDocument;
use PrivacyEvidence\Core\ObservationState;
use PrivacyEvidence\Core\Value;
use PrivacyEvidence\Evidence\EvidenceType;
use PrivacyEvidence\Evidence\PrivacyEvidence;
use PrivacyEvidence\Source\ImportedResource;

final class SqliteObservationStore implements ObservationStore
{
    public function __construct(private readonly PDO $pdo)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->migrate();
    }

    public function recordResource(string $runId, ImportedResource $resource): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT OR REPLACE INTO resources
             (run_id, resource_id, name, source_value, normalized_url, resource_type, metadata_json)
             VALUES (:run_id, :resource_id, :name, :source_value, :normalized_url, :resource_type, :metadata_json)',
        );
        $stmt->execute([
            'run_id' => $runId,
            'resource_id' => $resource->id,
            'name' => $resource->name,
            'source_value' => $resource->sourceValue,
            'normalized_url' => $resource->normalizedUrl,
            'resource_type' => $resource->type->value,
            'metadata_json' => json_encode($resource->metadata, JSON_THROW_ON_ERROR),
        ]);
    }

    public function recordDocument(string $runId, FetchedDocument $document): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT OR IGNORE INTO documents
             (run_id, artifact_hash, resource_id, requested_url, final_url, status_code,
              media_type, fetched_at, acquisition_mode, truncated, body_size)
             VALUES (:run_id, :artifact_hash, :resource_id, :requested_url, :final_url, :status_code,
                     :media_type, :fetched_at, :acquisition_mode, :truncated, :body_size)',
        );
        $stmt->execute([
            'run_id' => $runId,
            'artifact_hash' => $document->sha256,
            'resource_id' => $document->resourceId,
            'requested_url' => $document->requestedUrl,
            'final_url' => $document->finalUrl,
            'status_code' => $document->statusCode,
            'media_type' => $document->mediaType,
            'fetched_at' => $document->fetchedAt,
            'acquisition_mode' => $document->acquisitionMode,
            'truncated' => $document->truncated ? 1 : 0,
            'body_size' => strlen($document->body),
        ]);
    }

    public function recordEvidence(string $runId, PrivacyEvidence $evidence): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT OR REPLACE INTO evidence
             (run_id, evidence_id, resource_id, artifact_hash, type, state, source_url,
              detector, detector_version, method, excerpt, confidence, needs_review, attributes_json)
             VALUES (:run_id, :evidence_id, :resource_id, :artifact_hash, :type, :state, :source_url,
                     :detector, :detector_version, :method, :excerpt, :confidence, :needs_review, :attributes_json)',
        );
        $stmt->execute([
            'run_id' => $runId,
            'evidence_id' => $evidence->id(),
            'resource_id' => $evidence->resourceId,
            'artifact_hash' => $evidence->artifactHash,
            'type' => $evidence->type->value,
            'state' => $evidence->state->value,
            'source_url' => $evidence->sourceUrl,
            'detector' => $evidence->detector,
            'detector_version' => $evidence->detectorVersion,
            'method' => $evidence->method,
            'excerpt' => $evidence->excerpt,
            'confidence' => $evidence->confidence,
            'needs_review' => $evidence->needsReview ? 1 : 0,
            'attributes_json' => json_encode($evidence->attributes, JSON_THROW_ON_ERROR),
        ]);
    }



    public function resourceRecords(string $runId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT resource_id, name, source_value, normalized_url, resource_type, metadata_json
             FROM resources WHERE run_id = :run_id ORDER BY resource_id',
        );
        $stmt->execute(['run_id' => $runId]);

        $records = [];
        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            $metadata = Value::scalarMap(
                json_decode(
                    Value::string($row['metadata_json'] ?? null, 'metadata_json'),
                    true,
                    flags: JSON_THROW_ON_ERROR,
                ),
                'metadata_json',
            );
            $records[] = [
                'id' => Value::string($row['resource_id'] ?? null, 'resource_id'),
                'name' => Value::string($row['name'] ?? null, 'name'),
                'sourceValue' => Value::string($row['source_value'] ?? null, 'source_value'),
                'normalizedUrl' => $row['normalized_url'] === null
                    ? null
                    : Value::string($row['normalized_url'], 'normalized_url'),
                'type' => Value::string($row['resource_type'] ?? null, 'resource_type'),
                'metadata' => $metadata,
            ];
        }

        return $records;
    }

    public function documentRecords(string $runId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT artifact_hash, resource_id, requested_url, final_url, status_code,
                    media_type, fetched_at, acquisition_mode, truncated, body_size
             FROM documents WHERE run_id = :run_id ORDER BY resource_id, fetched_at, artifact_hash',
        );
        $stmt->execute(['run_id' => $runId]);

        $records = [];
        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            $records[] = [
                'artifactHash' => Value::string($row['artifact_hash'] ?? null, 'artifact_hash'),
                'resourceId' => Value::string($row['resource_id'] ?? null, 'resource_id'),
                'requestedUrl' => Value::string($row['requested_url'] ?? null, 'requested_url'),
                'finalUrl' => Value::string($row['final_url'] ?? null, 'final_url'),
                'statusCode' => Value::int($row['status_code'] ?? null, 'status_code'),
                'mediaType' => Value::string($row['media_type'] ?? null, 'media_type'),
                'fetchedAt' => Value::string($row['fetched_at'] ?? null, 'fetched_at'),
                'acquisitionMode' => Value::string($row['acquisition_mode'] ?? null, 'acquisition_mode'),
                'truncated' => Value::int($row['truncated'] ?? null, 'truncated') === 1,
                'bodySize' => Value::int($row['body_size'] ?? null, 'body_size'),
            ];
        }

        return $records;
    }

    public function resourceIds(string $runId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT resource_id FROM resources WHERE run_id = :run_id ORDER BY resource_id',
        );
        $stmt->execute(['run_id' => $runId]);

        $ids = [];
        while (($value = $stmt->fetchColumn()) !== false) {
            $ids[] = Value::string($value, 'resource_id');
        }

        return $ids;
    }

    public function evidence(string $runId, ?string $resourceId = null): array
    {
        if ($resourceId === null) {
            $stmt = $this->pdo->prepare(
                'SELECT * FROM evidence WHERE run_id = :run_id ORDER BY resource_id, type, evidence_id',
            );
            $stmt->execute(['run_id' => $runId]);
        } else {
            $stmt = $this->pdo->prepare(
                'SELECT * FROM evidence
                 WHERE run_id = :run_id AND resource_id = :resource_id
                 ORDER BY type, evidence_id',
            );
            $stmt->execute([
                'run_id' => $runId,
                'resource_id' => $resourceId,
            ]);
        }

        $result = [];
        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            $attributes = Value::scalarMap(
                json_decode(
                    Value::string($row['attributes_json'] ?? null, 'attributes_json'),
                    true,
                    flags: JSON_THROW_ON_ERROR,
                ),
                'attributes_json',
            );

            $result[] = new PrivacyEvidence(
                type: EvidenceType::from(Value::string($row['type'] ?? null, 'type')),
                state: ObservationState::from(Value::string($row['state'] ?? null, 'state')),
                resourceId: Value::string($row['resource_id'] ?? null, 'resource_id'),
                artifactHash: Value::string($row['artifact_hash'] ?? null, 'artifact_hash'),
                sourceUrl: Value::string($row['source_url'] ?? null, 'source_url'),
                detector: Value::string($row['detector'] ?? null, 'detector'),
                detectorVersion: Value::string($row['detector_version'] ?? null, 'detector_version'),
                method: Value::string($row['method'] ?? null, 'method'),
                excerpt: $row['excerpt'] === null ? null : Value::string($row['excerpt'], 'excerpt'),
                confidence: Value::float($row['confidence'] ?? null, 'confidence'),
                needsReview: Value::int($row['needs_review'] ?? null, 'needs_review') === 1,
                attributes: $attributes,
            );
        }

        return $result;
    }

    public function recordProfileResult(
        string $runId,
        string $resourceId,
        string $profile,
        string $profileVersion,
        array $result,
    ): void {
        $id = hash(
            'sha256',
            $runId . '|' . $resourceId . '|' . $profile . '|' . $profileVersion . '|'
            . json_encode($result, JSON_THROW_ON_ERROR),
        );

        $stmt = $this->pdo->prepare(
            'INSERT OR REPLACE INTO profile_results
             (result_id, run_id, resource_id, profile, profile_version, result_json)
             VALUES (:result_id, :run_id, :resource_id, :profile, :profile_version, :result_json)',
        );
        $stmt->execute([
            'result_id' => $id,
            'run_id' => $runId,
            'resource_id' => $resourceId,
            'profile' => $profile,
            'profile_version' => $profileVersion,
            'result_json' => json_encode($result, JSON_THROW_ON_ERROR),
        ]);
    }



    public function profileResults(string $runId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT resource_id, profile, profile_version, result_json
             FROM profile_results
             WHERE run_id = :run_id
             ORDER BY resource_id, profile, result_id',
        );
        $stmt->execute(['run_id' => $runId]);

        $results = [];
        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            $decoded = json_decode(
                Value::string($row['result_json'] ?? null, 'result_json'),
                true,
                flags: JSON_THROW_ON_ERROR,
            );
            $results[] = [
                'resourceId' => Value::string($row['resource_id'] ?? null, 'resource_id'),
                'profile' => Value::string($row['profile'] ?? null, 'profile'),
                'profileVersion' => Value::string($row['profile_version'] ?? null, 'profile_version'),
                'result' => is_array($decoded) ? $decoded : [],
            ];
        }

        return $results;
    }

    public function resourceUsage(string $runId, string $resourceId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) AS pages,
                    COALESCE(SUM(body_size), 0) AS bytes,
                    COALESCE(SUM(CASE WHEN acquisition_mode = "browser" THEN 1 ELSE 0 END), 0)
                        AS browser_pages
             FROM documents
             WHERE run_id = :run_id AND resource_id = :resource_id',
        );
        $stmt->execute([
            'run_id' => $runId,
            'resource_id' => $resourceId,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return ['pages' => 0, 'bytes' => 0, 'browserPages' => 0];
        }

        return [
            'pages' => Value::int($row['pages'] ?? null, 'pages'),
            'bytes' => Value::int($row['bytes'] ?? null, 'bytes'),
            'browserPages' => Value::int($row['browser_pages'] ?? null, 'browser_pages'),
        ];
    }

    public function counts(string $runId): array
    {
        $result = [];

        foreach (['resources', 'documents', 'evidence', 'profile_results'] as $table) {
            $stmt = $this->pdo->prepare(
                sprintf('SELECT COUNT(*) FROM %s WHERE run_id = :run_id', $table),
            );
            $stmt->execute(['run_id' => $runId]);
            $result[$table] = Value::int($stmt->fetchColumn(), 'count');
        }

        return $result;
    }

    private function migrate(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS resources (
                run_id TEXT NOT NULL,
                resource_id TEXT NOT NULL,
                name TEXT NOT NULL,
                source_value TEXT NOT NULL,
                normalized_url TEXT,
                resource_type TEXT NOT NULL,
                metadata_json TEXT NOT NULL,
                PRIMARY KEY (run_id, resource_id)
            )',
        );

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS documents (
                run_id TEXT NOT NULL,
                artifact_hash TEXT NOT NULL,
                resource_id TEXT NOT NULL,
                requested_url TEXT NOT NULL,
                final_url TEXT NOT NULL,
                status_code INTEGER NOT NULL,
                media_type TEXT NOT NULL,
                fetched_at TEXT NOT NULL,
                acquisition_mode TEXT NOT NULL,
                truncated INTEGER NOT NULL,
                body_size INTEGER NOT NULL,
                PRIMARY KEY (run_id, artifact_hash)
            )',
        );

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS evidence (
                run_id TEXT NOT NULL,
                evidence_id TEXT NOT NULL,
                resource_id TEXT NOT NULL,
                artifact_hash TEXT NOT NULL,
                type TEXT NOT NULL,
                state TEXT NOT NULL,
                source_url TEXT NOT NULL,
                detector TEXT NOT NULL,
                detector_version TEXT NOT NULL,
                method TEXT NOT NULL,
                excerpt TEXT,
                confidence REAL NOT NULL,
                needs_review INTEGER NOT NULL,
                attributes_json TEXT NOT NULL,
                PRIMARY KEY (run_id, evidence_id)
            )',
        );

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS profile_results (
                result_id TEXT PRIMARY KEY,
                run_id TEXT NOT NULL,
                resource_id TEXT NOT NULL,
                profile TEXT NOT NULL,
                profile_version TEXT NOT NULL,
                result_json TEXT NOT NULL
            )',
        );
    }
}
