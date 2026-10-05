<?php

declare(strict_types=1);

namespace PrivacyEvidence\Queue;

interface JobQueue
{
    public function enqueue(Job $job): bool;

    public function reserve(
        string $runId,
        string $stage,
        int $perHostConcurrency = 2,
        int $minHostDelayMs = 0,
    ): ?Job;

    public function complete(string $jobId): void;

    public function fail(
        string $jobId,
        string $error,
        int $maxAttempts = 3,
        JobStatus $terminalStatus = JobStatus::Dead,
        ?int $retryDelayMs = null,
    ): JobStatus;

    public function deferHost(string $runId, string $stage, string $host, int $delayMs): void;

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

    /**
     * @return list<array{
     *   id:string,stage:string,status:string,attempts:int,url:string|null,
     *   resourceId:string|null,error:string
     * }>
     */
    public function failures(string $runId): array;
}
