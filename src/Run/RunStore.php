<?php

declare(strict_types=1);

namespace PrivacyEvidence\Run;

interface RunStore
{
    public function create(ResearchRun $run): void;

    public function setStatus(string $runId, RunStatus $status): void;

    public function get(string $runId): ?ResearchRun;

    /**
     * @return array<string, int|float|string>
     */
    public function telemetry(string $runId): array;

    public function increment(string $runId, string $metric, int|float $amount = 1): void;
}
