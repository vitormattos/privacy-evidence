<?php

declare(strict_types=1);

namespace PrivacyEvidence\Review;

interface ReviewQueue
{
    public function enqueue(string $runId, string $evidenceId, string $payload): void;

    /**
     * @return list<array{run_id:string,evidence_id:string,payload:string,status:string}>
     */
    public function pending(?string $runId = null): array;

    public function decide(ReviewDecision $decision): void;

    /**
     * @return list<array<string,mixed>>
     */
    public function decisions(string $runId): array;
}
