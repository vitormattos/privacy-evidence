<?php

declare(strict_types=1);

namespace PrivacyEvidence\Storage;

use PrivacyEvidence\Acquisition\FetchedDocument;
use PrivacyEvidence\Evidence\PrivacyEvidence;
use PrivacyEvidence\Source\ImportedResource;

interface ObservationStore
{
    public function recordResource(string $runId, ImportedResource $resource): void;

    public function recordDocument(string $runId, FetchedDocument $document): void;

    public function recordEvidence(string $runId, PrivacyEvidence $evidence): void;

    /**
     * @return list<array<string,mixed>>
     */
    public function resourceRecords(string $runId): array;

    /**
     * @return list<array<string,mixed>>
     */
    public function documentRecords(string $runId): array;

    /**
     * @return list<string>
     */
    public function resourceIds(string $runId): array;

    /**
     * @return list<PrivacyEvidence>
     */
    public function evidence(string $runId, ?string $resourceId = null): array;

    /**
     * @param array<string,mixed> $result
     */
    public function recordProfileResult(
        string $runId,
        string $resourceId,
        string $profile,
        string $profileVersion,
        array $result,
    ): void;

    /**
     * @return list<array<string,mixed>>
     */
    public function profileResults(string $runId): array;

    /**
     * @return array{pages:int,bytes:int,browserPages:int}
     */
    public function resourceUsage(string $runId, string $resourceId): array;

    /**
     * @return array<string,int>
     */
    public function counts(string $runId): array;
}
