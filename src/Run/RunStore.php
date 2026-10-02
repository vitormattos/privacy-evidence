<?php

declare(strict_types=1);

namespace PrivacyEvidence\Run;

interface RunStore
{
    public function create(ResearchRun $run): void;

    public function setStatus(string $runId, RunStatus $status): void;

    public function get(string $runId): ?ResearchRun;

    public function status(string $runId): ?RunStatus;

    /**
     * @return array<string, int|float|string>
     */
    public function telemetry(string $runId): array;

    public function increment(string $runId, string $metric, int|float $amount = 1): void;

    public function setMetric(string $runId, string $metric, int|float $value): void;

    /**
     * @param array<string, scalar|null> $detail
     */
    public function recordEvent(
        string $runId,
        string $type,
        ?string $subjectId = null,
        array $detail = [],
    ): void;

    /**
     * @return list<array{
     *   type:string,
     *   subjectId:string|null,
     *   occurredAt:string,
     *   detail:array<string,scalar|null>
     * }>
     */
    public function events(string $runId): array;
}
