<?php

declare(strict_types=1);

namespace PrivacyEvidence\Queue;

interface JobQueue
{
    public function enqueue(Job $job): void;

    public function reserve(
        string $runId,
        string $stage,
        int $perHostConcurrency = 2,
        int $minHostDelayMs = 0,
    ): ?Job;

    public function complete(string $jobId): void;

    public function fail(string $jobId, string $error, int $maxAttempts = 3): JobStatus;

    public function requeueRunning(string $runId): int;

    /**
     * @return array<string,int>
     */
    public function counts(string $runId): array;

    /**
     * @return array<string,int>
     */
    public function stageCounts(string $runId, string $stage): array;

    public function scheduledCount(
        string $runId,
        string $stage,
        string $deduplicationPrefix,
    ): int;
}
