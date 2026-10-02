<?php

declare(strict_types=1);

namespace PrivacyEvidence\Queue;

final readonly class Job
{
    /**
     * @param array<string, scalar|null> $payload
     */
    public function __construct(
        public string $id,
        public string $runId,
        public string $stage,
        public string $deduplicationKey,
        public array $payload,
        public JobStatus $status = JobStatus::Pending,
        public int $attempts = 0,
    ) {
    }
}
