<?php

declare(strict_types=1);

namespace PrivacyEvidence\Queue;

interface JobQueue
{
    public function enqueue(Job $job): void;

    public function reserve(string $runId, string $stage): ?Job;

    public function complete(string $jobId): void;

    public function fail(string $jobId, string $error, int $maxAttempts = 3): void;

    /**
     * @return array<string,int>
     */
    public function counts(string $runId): array;

    public function scheduledCount(
        string $runId,
        string $stage,
        string $deduplicationPrefix,
    ): int;
}
