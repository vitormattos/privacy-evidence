<?php

declare(strict_types=1);

namespace PrivacyEvidence\Review;

interface ReviewQueue
{
    public function enqueue(string $evidenceId, string $payload): void;

    /**
     * @return list<array{evidence_id:string,payload:string,status:string}>
     */
    public function pending(): array;

    public function decide(ReviewDecision $decision): void;
}
