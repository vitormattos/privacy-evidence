<?php

declare(strict_types=1);

namespace PrivacyEvidence\Pipeline;

use PrivacyEvidence\Acquisition\DocumentStore;
use PrivacyEvidence\Acquisition\FetchedDocument;
use PrivacyEvidence\Acquisition\HttpFetcher;
use PrivacyEvidence\Browser\BrowserEscalationPolicy;
use PrivacyEvidence\Browser\BrowserProvider;
use PrivacyEvidence\Crawl\LinkDiscoverer;
use PrivacyEvidence\Queue\Job;
use PrivacyEvidence\Queue\JobQueue;
use PrivacyEvidence\Queue\JobStatus;
use PrivacyEvidence\Review\ReviewQueue;
use PrivacyEvidence\Run\ResearchRun;
use PrivacyEvidence\Run\RunStatus;
use PrivacyEvidence\Run\RunStore;
use PrivacyEvidence\Source\SourceAdapter;
use PrivacyEvidence\Storage\ObservationStore;
use Symfony\Component\Uid\Uuid;

final readonly class ResearchPipeline
{
    public function __construct(
        private RunStore $runs,
        private JobQueue $jobs,
        private ObservationStore $observations,
        private DocumentStore $documents,
        private ReviewQueue $reviews,
        private HttpFetcher $fetcher,
        private DetectorRegistry $detectors,
        private BrowserEscalationPolicy $browserPolicy = new BrowserEscalationPolicy(),
        private LinkDiscoverer $linkDiscoverer = new LinkDiscoverer(),
        private ?BrowserProvider $browser = null,
        private PipelineConfig $config = new PipelineConfig(),
    ) {
    }

    public function start(ResearchRun $run, SourceAdapter $source): void
    {
        $this->runs->create($run);

        foreach ($source->resources() as $resource) {
            $this->observations->recordResource($run->id, $resource);
            $this->runs->increment($run->id, 'resources_imported');

            if ($resource->normalizedUrl === null) {
                $this->runs->increment($run->id, 'resources_invalid');
                $this->runs->increment($run->id, 'jobs_skipped');
                $this->runs->recordEvent(
                    $run->id,
                    'resource_terminal',
                    $resource->id,
                    ['status' => 'invalid_url'],
                );
                continue;
            }

            $this->jobs->enqueue(
                new Job(
                    id: Uuid::v7()->toRfc4122(),
                    runId: $run->id,
                    stage: 'fetch',
                    deduplicationKey: $resource->id . '|' . $resource->normalizedUrl,
                    payload: [
                        'resource_id' => $resource->id,
                        'url' => $resource->normalizedUrl,
                        'depth' => 0,
                        'crawl_started_at' => time(),
                    ],
                    priority: 1000,
                    host: $this->hostForUrl($resource->normalizedUrl),
                ),
            );
        }

        $this->runs->setStatus($run->id, RunStatus::Running);
    }

    public function execute(string $runId): void
    {
        $remaining = $this->config->maxJobsPerInvocation;

        $processed = $this->executeStage(
            $runId,
            'fetch',
            $remaining,
        );
        if ($remaining > 0) {
            $remaining = max(0, $remaining - $processed);
        }

        if ($remaining === 0 && $this->config->maxJobsPerInvocation > 0) {
            $this->updateRunStatus($runId);
            return;
        }

        $this->executeStage(
            $runId,
            'browser',
            $remaining,
        );

        $this->updateRunStatus($runId);
    }

    public function executeStage(
        string $runId,
        string $stage,
        int $maxJobs = 0,
    ): int {
        if (!in_array($stage, ['fetch', 'browser'], true)) {
            throw new \InvalidArgumentException('Unsupported pipeline stage.');
        }

        $processed = 0;
        $idleRounds = 0;
        $stageStartedAt = microtime(true);
        $usageBefore = getrusage();

        while ($maxJobs === 0 || $processed < $maxJobs) {
            $job = $this->jobs->reserve(
                $runId,
                $stage,
                $this->config->perHostConcurrency,
                $this->config->minHostDelayMs,
            );

            if ($job === null) {
                $counts = $this->jobs->stageCounts($runId, $stage);
                if (($counts['pending'] ?? 0) > 0 && $idleRounds < 120) {
                    $idleRounds++;
                    usleep(max($this->config->minHostDelayMs, 25) * 1000);
                    continue;
                }

                break;
            }

            $idleRounds = 0;
            $this->recordQueueWait($runId, $job);

            try {
                if ($stage === 'fetch') {
                    $this->processFetch($runId, $job);
                } else {
                    $this->processBrowser($runId, $job);
                }

                $this->jobs->complete($job->id);
                $this->runs->increment($runId, 'jobs_completed');
                $this->runs->increment($runId, 'jobs_completed.' . $stage);
            } catch (\Throwable $e) {
                $status = $this->jobs->fail($job->id, $e->getMessage());
                $this->runs->increment($runId, 'jobs_failed');
                $this->runs->increment($runId, 'jobs_failed.' . $stage);
                $this->runs->increment($runId, 'failure.' . $stage);

                if ($status === JobStatus::Pending) {
                    $this->runs->increment($runId, 'jobs_retried');
                } else {
                    $this->runs->increment($runId, 'jobs_dead');
                }

                $this->runs->recordEvent(
                    $runId,
                    'job_failure',
                    $this->payloadString($job, 'resource_id'),
                    [
                        'stage' => $stage,
                        'status' => $status->value,
                        'attempt' => $job->attempts,
                        'error' => mb_substr($e->getMessage(), 0, 500),
                    ],
                );
            }

            $processed++;
        }

        $this->recordStagePerformance(
            $runId,
            $stage,
            $processed,
            $stageStartedAt,
            $usageBefore,
        );

        return $processed;
    }

    public function resume(string $runId): void
    {
        $requeued = $this->jobs->requeueRunning($runId);
        if ($requeued > 0) {
            $this->runs->increment($runId, 'jobs_recovered', $requeued);
            $this->runs->recordEvent(
                $runId,
                'run_resume',
                null,
                ['requeued_running_jobs' => $requeued],
            );
        }

        $this->runs->setStatus($runId, RunStatus::Running);
        $this->execute($runId);
    }

    private function processFetch(string $runId, Job $job): void
    {
        $resourceId = $this->requiredPayloadString($job, 'resource_id');
        $url = $this->requiredPayloadString($job, 'url');
        $depth = $this->payloadInt($job, 'depth', 0);
        $crawlStartedAt = $this->payloadInt($job, 'crawl_started_at', time());

        $document = $this->fetcher->fetch(
            resourceId: $resourceId,
            url: $url,
            maxBytes: $this->config->maxBodyBytes,
        );
        $this->persistAndAnalyze($runId, $document);
        $this->scheduleLinks(
            runId: $runId,
            resourceId: $resourceId,
            document: $document,
            depth: $depth,
            crawlStartedAt: $crawlStartedAt,
        );

        if (!$this->config->enableBrowserEscalation || $this->browser === null) {
            return;
        }

        $decision = $this->browserPolicy->decide($document);
        if (!$decision->required) {
            return;
        }

        $this->runs->increment($runId, 'browser_escalations');
        $this->runs->increment(
            $runId,
            'browser_escalation.' . $decision->reason,
        );

        $scheduledBrowserPages = $this->jobs->scheduledCount(
            $runId,
            'browser',
            $resourceId . '|',
        );
        if ($scheduledBrowserPages >= $this->config->crawlBudget->maxBrowserPages) {
            $this->recordBudgetStop(
                $runId,
                $resourceId,
                'browser_pages',
                $url,
            );
            $this->runs->increment($runId, 'jobs_skipped');
            return;
        }

        $this->jobs->enqueue(
            new Job(
                id: Uuid::v7()->toRfc4122(),
                runId: $runId,
                stage: 'browser',
                deduplicationKey: $resourceId . '|' . $url,
                payload: [
                    'resource_id' => $resourceId,
                    'url' => $url,
                    'policy_version' => $decision->policyVersion,
                    'escalation_reason' => $decision->reason,
                ],
                priority: 1000,
                host: $this->hostForUrl($url),
            ),
        );
    }

    private function processBrowser(string $runId, Job $job): void
    {
        if ($this->browser === null) {
            throw new \RuntimeException('Browser stage requested but no browser provider is configured.');
        }

        $resourceId = $this->requiredPayloadString($job, 'resource_id');
        $url = $this->requiredPayloadString($job, 'url');
        $observation = $this->browser->observe($url);

        $rendered = new FetchedDocument(
            resourceId: $resourceId,
            requestedUrl: $url,
            finalUrl: $observation->url,
            statusCode: 200,
            mediaType: 'text/html',
            body: $observation->html,
            fetchedAt: $observation->capturedAt,
            acquisitionMode: 'browser',
            metadata: [
                'browserVersion' => $observation->browserVersion,
                'browserEscalation' => [
                    'policyVersion' => $this->payloadString($job, 'policy_version'),
                    'reason' => $this->payloadString($job, 'escalation_reason'),
                ],
                'browser' => $observation->metadata,
            ],
        );

        $this->persistAndAnalyze($runId, $rendered);
        $this->runs->increment($runId, 'browser_pages_acquired');
    }

    private function scheduleLinks(
        string $runId,
        string $resourceId,
        FetchedDocument $document,
        int $depth,
        int $crawlStartedAt,
    ): void {
        $budget = $this->config->crawlBudget;
        $usage = $this->observations->resourceUsage($runId, $resourceId);

        $stopReason = null;
        if ($depth >= $budget->maxDepth) {
            $stopReason = 'depth';
        } elseif ($usage['pages'] >= $budget->maxPages) {
            $stopReason = 'pages';
        } elseif ($usage['bytes'] >= $budget->maxBytes) {
            $stopReason = 'bytes';
        } elseif ((time() - $crawlStartedAt) >= $budget->maxDurationSeconds) {
            $stopReason = 'duration';
        }

        if ($stopReason !== null) {
            $this->recordBudgetStop(
                $runId,
                $resourceId,
                $stopReason,
                $document->finalUrl,
            );
            return;
        }

        foreach ($this->linkDiscoverer->discover($document) as $candidate) {
            if ($this->jobs->scheduledCount($runId, 'fetch', $resourceId . '|') >= $budget->maxPages) {
                $this->recordBudgetStop(
                    $runId,
                    $resourceId,
                    'pages',
                    $candidate->url,
                );
                break;
            }

            $this->jobs->enqueue(
                new Job(
                    id: Uuid::v7()->toRfc4122(),
                    runId: $runId,
                    stage: 'fetch',
                    deduplicationKey: $resourceId . '|' . $candidate->url,
                    payload: [
                        'resource_id' => $resourceId,
                        'url' => $candidate->url,
                        'depth' => $depth + 1,
                        'crawl_started_at' => $crawlStartedAt,
                    ],
                    priority: $candidate->priority,
                    host: $this->hostForUrl($candidate->url),
                ),
            );
        }
    }

    private function persistAndAnalyze(string $runId, FetchedDocument $document): void
    {
        $this->documents->put($document);
        $this->observations->recordDocument($runId, $document);
        $this->runs->increment($runId, 'documents_acquired');
        $this->runs->increment($runId, 'bytes_acquired', strlen($document->body));

        foreach ($this->detectors->detectors as $detector) {
            foreach ($detector->detect($document) as $evidence) {
                $this->observations->recordEvidence($runId, $evidence);
                $this->runs->increment($runId, 'evidence_items');

                if ($evidence->needsReview) {
                    $this->reviews->enqueue(
                        $runId,
                        $evidence->id(),
                        json_encode($evidence->toArray(), JSON_THROW_ON_ERROR),
                    );
                }
            }
        }
    }

    private function updateRunStatus(string $runId): void
    {
        $counts = $this->jobs->counts($runId);
        if (($counts['pending'] ?? 0) === 0 && ($counts['running'] ?? 0) === 0) {
            $this->runs->setStatus($runId, RunStatus::Completed);
            $this->runs->recordEvent($runId, 'run_terminal', null, ['status' => 'completed']);
            return;
        }

        $this->runs->setStatus($runId, RunStatus::Interrupted);
        $this->runs->recordEvent($runId, 'run_terminal', null, ['status' => 'interrupted']);
    }

    private function recordQueueWait(string $runId, Job $job): void
    {
        if ($job->reservedAtMs === null || $job->enqueuedAtMs <= 0) {
            return;
        }

        $wait = max(0, $job->reservedAtMs - $job->enqueuedAtMs);
        $this->runs->increment($runId, 'queue_wait_ms.' . $job->stage, $wait);
        $this->runs->increment($runId, 'queue_reservations.' . $job->stage);
    }

    private function recordBudgetStop(
        string $runId,
        string $resourceId,
        string $reason,
        string $url,
    ): void {
        $this->runs->increment($runId, 'crawl_budget_stops');
        $this->runs->increment($runId, 'crawl_budget_stop.' . $reason);
        $this->runs->recordEvent(
            $runId,
            'crawl_budget_stop',
            $resourceId,
            [
                'reason' => $reason,
                'url' => $url,
            ],
        );
    }

    /**
     * @param array<string,int> $usageBefore
     */
    private function recordStagePerformance(
        string $runId,
        string $stage,
        int $processed,
        float $startedAt,
        array $usageBefore,
    ): void {
        $elapsedMs = max(0.0, (microtime(true) - $startedAt) * 1000);
        $usageAfter = getrusage();

        $userBefore = ($usageBefore['ru_utime.tv_sec'] ?? 0) * 1_000_000
            + ($usageBefore['ru_utime.tv_usec'] ?? 0);
        $userAfter = ($usageAfter['ru_utime.tv_sec'] ?? 0) * 1_000_000
            + ($usageAfter['ru_utime.tv_usec'] ?? 0);
        $systemBefore = ($usageBefore['ru_stime.tv_sec'] ?? 0) * 1_000_000
            + ($usageBefore['ru_stime.tv_usec'] ?? 0);
        $systemAfter = ($usageAfter['ru_stime.tv_sec'] ?? 0) * 1_000_000
            + ($usageAfter['ru_stime.tv_usec'] ?? 0);

        $this->runs->increment($runId, 'stage_jobs.' . $stage, $processed);
        $this->runs->increment($runId, 'stage_elapsed_ms.' . $stage, $elapsedMs);
        $this->runs->increment(
            $runId,
            'cpu_user_us.' . $stage,
            max(0, $userAfter - $userBefore),
        );
        $this->runs->increment(
            $runId,
            'cpu_system_us.' . $stage,
            max(0, $systemAfter - $systemBefore),
        );
        $this->runs->setMetric(
            $runId,
            'peak_memory_bytes',
            memory_get_peak_usage(true),
        );

        $telemetry = $this->runs->telemetry($runId);
        $totalJobs = $telemetry['stage_jobs.' . $stage] ?? 0;
        $totalElapsedMs = $telemetry['stage_elapsed_ms.' . $stage] ?? 0;
        if (is_numeric($totalJobs) && is_numeric($totalElapsedMs) && (float) $totalElapsedMs > 0.0) {
            $this->runs->setMetric(
                $runId,
                'throughput_jobs_per_second.' . $stage,
                (float) $totalJobs / ((float) $totalElapsedMs / 1000.0),
            );
        }
    }

    private function payloadInt(Job $job, string $key, int $default): int
    {
        $value = $job->payload[$key] ?? $default;

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        return $default;
    }

    private function requiredPayloadString(Job $job, string $key): string
    {
        $value = $job->payload[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new \RuntimeException(sprintf('Job payload is missing %s.', $key));
        }

        return $value;
    }

    private function payloadString(Job $job, string $key): ?string
    {
        $value = $job->payload[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function hostForUrl(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? strtolower($host) : null;
    }
}
