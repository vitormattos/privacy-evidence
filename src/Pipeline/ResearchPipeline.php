<?php

declare(strict_types=1);

namespace PrivacyEvidence\Pipeline;

use PrivacyEvidence\Acquisition\AcquisitionException;
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
use PrivacyEvidence\Source\ResourceClassifier;
use PrivacyEvidence\Source\ResourceType;
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
        private ResourceClassifier $resourceClassifier = new ResourceClassifier(),
        private ?BrowserProvider $browser = null,
        private PipelineConfig $config = new PipelineConfig(),
    ) {
    }

    public function start(ResearchRun $run, SourceAdapter $source): void
    {
        $this->runs->create($run);

        $resources = iterator_to_array($source->resources(), false);

        /** @var array<string,list<string>> $eligibleIdsByUrl */
        $eligibleIdsByUrl = [];
        foreach ($resources as $resource) {
            if (
                $resource->type->isWebsiteMeasurementEligible()
                && $resource->normalizedUrl !== null
            ) {
                $eligibleIdsByUrl[$resource->normalizedUrl][] = $resource->id;
            }
        }

        /** @var array<string,string> $canonicalIdByUrl */
        $canonicalIdByUrl = [];
        foreach ($eligibleIdsByUrl as $url => $resourceIds) {
            sort($resourceIds, SORT_STRING);
            $canonicalIdByUrl[$url] = $resourceIds[0];
        }

        foreach ($resources as $resource) {
            $this->observations->recordResource($run->id, $resource);
            $this->runs->increment($run->id, 'resources_imported');

            if (!$resource->type->isWebsiteMeasurementEligible()) {
                $this->runs->increment($run->id, 'resources_not_eligible');
                $this->runs->increment($run->id, 'resources_not_eligible.' . $resource->type->value);
                $this->runs->increment($run->id, 'jobs_skipped');
                $this->runs->recordEvent(
                    $run->id,
                    'resource_terminal',
                    $resource->id,
                    [
                        'status' => 'not_eligible',
                        'category' => $resource->type->value,
                        'classification_rule' => $resource->classificationRule,
                    ],
                );
                continue;
            }

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

            $canonicalId = $canonicalIdByUrl[$resource->normalizedUrl] ?? $resource->id;
            if ($canonicalId !== $resource->id) {
                $this->runs->increment($run->id, 'resources_duplicate_reference');
                $this->runs->increment($run->id, 'jobs_skipped');
                $this->runs->recordEvent(
                    $run->id,
                    'resource_terminal',
                    $resource->id,
                    [
                        'status' => 'duplicate_reference',
                        'category' => 'duplicate_source_url',
                        'canonical_resource_id' => $canonicalId,
                        'url' => $resource->normalizedUrl,
                    ],
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
                        // The per-resource crawl clock starts when the root job
                        // is actually processed, not while it waits behind the
                        // rest of the population in the queue.
                        'crawl_started_at' => 0,
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
        /** @var array<array-key,mixed> $usageBefore */
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
                if ($e instanceof AcquisitionException) {
                    $category = $e->category;
                    $retryable = $e->retryable;
                    $terminalStatus = JobStatus::Failed;
                } else {
                    $category = 'unexpected_exception';
                    $retryable = false;
                    $terminalStatus = JobStatus::Dead;
                }
                $status = $this->jobs->fail(
                    $job->id,
                    $e->getMessage(),
                    maxAttempts: $retryable ? 3 : 1,
                    terminalStatus: $terminalStatus,
                    retryDelayMs: $e instanceof AcquisitionException ? $e->retryDelayMs : null,
                );

                $this->runs->increment($runId, 'job_attempt_failures');
                $this->runs->increment($runId, 'job_attempt_failures.' . $stage);
                $this->runs->increment($runId, 'job_attempt_failures.' . $stage . '.' . $category);

                if ($status === JobStatus::Pending) {
                    $this->runs->increment($runId, 'jobs_retried');
                } elseif ($status === JobStatus::Failed) {
                    $this->runs->increment($runId, 'resource_acquisition_failures');
                    $this->runs->increment($runId, 'resource_acquisition_failures.' . $category);
                    $resourceId = $this->payloadString($job, 'resource_id');
                    if ($resourceId !== null) {
                        $this->runs->recordEvent(
                            $runId,
                            'resource_terminal',
                            $resourceId,
                            [
                                'status' => 'unreachable',
                                'category' => $category,
                                'url' => $this->payloadString($job, 'url'),
                            ],
                        );
                    }
                } else {
                    $this->runs->increment($runId, 'jobs_dead');
                    $this->runs->increment($runId, 'terminal_failures');
                    $this->runs->increment($runId, 'terminal_failures.' . $stage);
                    $this->runs->increment($runId, 'terminal_failures.' . $stage . '.' . $category);
                }

                $this->runs->recordEvent(
                    $runId,
                    'job_failure',
                    $this->payloadString($job, 'resource_id'),
                    [
                        'stage' => $stage,
                        'status' => $status->value,
                        'attempt' => $job->attempts,
                        'url' => $this->payloadString($job, 'url'),
                        'category' => $category,
                        'retryable' => $retryable,
                        'retry_delay_ms' => $e instanceof AcquisitionException ? $e->retryDelayMs : null,
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
        $crawlStartedAt = $this->payloadInt($job, 'crawl_started_at', 0);
        if ($crawlStartedAt <= 0) {
            $crawlStartedAt = time();
        }

        $document = $this->fetcher->fetch(
            resourceId: $resourceId,
            url: $url,
            maxBytes: $this->config->maxBodyBytes,
        );
        $this->persistAndAnalyze($runId, $document);

        if (
            in_array($document->statusCode, [429, 502, 503, 504], true)
            && $job->attempts < 3
        ) {
            $retryAfterValue = $document->metadata['retryAfterMs'] ?? null;
            $retryAfterMs = is_int($retryAfterValue)
                ? max(1_000, $retryAfterValue)
                : min(30_000, 2_000 * (1 << max(0, $job->attempts - 1)));

            if ($job->host !== null) {
                $this->jobs->deferHost($runId, 'fetch', $job->host, $retryAfterMs);
            }

            $this->runs->increment($runId, 'http_retries');
            $this->runs->increment($runId, 'http_retries.' . $document->statusCode);

            throw new AcquisitionException(
                url: $url,
                category: 'http_' . $document->statusCode,
                retryable: true,
                message: sprintf(
                    'Transient HTTP %d response from %s.',
                    $document->statusCode,
                    $document->finalUrl,
                ),
                retryDelayMs: $retryAfterMs,
            );
        }

        if ($document->statusCode < 200 || $document->statusCode >= 300) {
            $this->runs->increment($runId, 'http_status.' . $document->statusCode);

            if ($document->statusCode === 403) {
                $challenge = $this->browserPolicy->decide($document);
                if (
                    $challenge->required
                    && $challenge->reason === 'anti_bot_challenge_candidate'
                    && $this->config->enableBrowserEscalation
                    && $this->browser !== null
                ) {
                    $this->scheduleBrowser(
                        $runId,
                        $resourceId,
                        $url,
                        $challenge->policyVersion,
                        $challenge->reason,
                    );
                    $this->runs->increment($runId, 'http_403_browser_recovery_candidates');
                    return;
                }
            }

            if ($depth === 0) {
                $this->runs->recordEvent(
                    $runId,
                    'resource_terminal',
                    $resourceId,
                    [
                        'status' => 'http_error',
                        'http_status' => $document->statusCode,
                    ],
                );
            }

            return;
        }

        if ($depth === 0) {
            $finalClassification = $this->resourceClassifier->classifyDetailed(
                $document->finalUrl,
                $document->finalUrl,
            );
            if ($document->finalUrl !== $url) {
                $this->runs->recordEvent(
                    $runId,
                    'root_redirect',
                    $resourceId,
                    [
                        'requested_url' => $url,
                        'final_url' => $document->finalUrl,
                        'final_type' => $finalClassification->type->value,
                        'classification_rule' => $finalClassification->rule,
                    ],
                );
            }

            if (
                $document->finalUrl !== $url
                && !$finalClassification->type->isWebsiteMeasurementEligible()
            ) {
                $this->recordMeasurementLimit(
                    $runId,
                    $resourceId,
                    'redirected_to_' . $finalClassification->type->value,
                    $document->finalUrl,
                );
            }

            if (!str_contains(strtolower($document->mediaType), 'html')) {
                $this->recordMeasurementLimit(
                    $runId,
                    $resourceId,
                    'root_non_html',
                    $document->finalUrl,
                );
            } elseif (trim(strip_tags($document->body)) === '') {
                $this->recordMeasurementLimit(
                    $runId,
                    $resourceId,
                    'empty_html_content',
                    $document->finalUrl,
                );
            }

            if ($document->truncated) {
                $this->recordMeasurementLimit(
                    $runId,
                    $resourceId,
                    'response_truncated',
                    $document->finalUrl,
                );
            }
        }

        $this->scheduleLinks(
            runId: $runId,
            resourceId: $resourceId,
            document: $document,
            depth: $depth,
            crawlStartedAt: $crawlStartedAt,
        );

        $decision = $this->browserPolicy->decide($document);
        if (!$decision->required) {
            return;
        }

        $this->runs->increment($runId, 'browser_escalations');
        $this->runs->increment(
            $runId,
            'browser_escalation.' . $decision->reason,
        );

        if (!$this->config->enableBrowserEscalation || $this->browser === null) {
            $limitReason = match ($decision->reason) {
                'anti_bot_challenge_candidate' => 'anti_bot_challenge_browser_unavailable',
                'javascript_application_shell',
                'javascript_challenge_candidate' => 'dynamic_content_browser_unavailable',
                'consent_behavior_candidate' => 'behavioral_evidence_browser_unavailable',
                default => 'browser_required_unavailable',
            };
            $this->recordMeasurementLimit(
                $runId,
                $resourceId,
                $limitReason,
                $document->finalUrl,
            );
            $this->runs->increment($runId, 'browser_escalations_unavailable');
            $this->runs->increment($runId, 'jobs_skipped');

            return;
        }

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

        $this->scheduleBrowser(
            $runId,
            $resourceId,
            $url,
            $decision->policyVersion,
            $decision->reason,
        );
    }

    private function scheduleBrowser(
        string $runId,
        string $resourceId,
        string $url,
        string $policyVersion,
        string $reason,
    ): void {
        $this->jobs->enqueue(
            new Job(
                id: Uuid::v7()->toRfc4122(),
                runId: $runId,
                stage: 'browser',
                deduplicationKey: $resourceId . '|' . $url,
                payload: [
                    'resource_id' => $resourceId,
                    'url' => $url,
                    'policy_version' => $policyVersion,
                    'escalation_reason' => $reason,
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
        try {
            $observation = $this->browser->observe($url);
        } catch (\Throwable $exception) {
            $reason = $this->payloadString($job, 'escalation_reason');
            $category = $reason === 'anti_bot_challenge_candidate'
                ? 'anti_bot_challenge'
                : 'browser_failure';

            throw new AcquisitionException(
                url: $url,
                category: $category,
                retryable: false,
                message: sprintf(
                    'Browser acquisition failed for %s: %s',
                    $url,
                    mb_substr($exception->getMessage(), 0, 300),
                ),
            );
        }

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

        $postBrowserDecision = $this->browserPolicy->decide($rendered);
        if (
            $postBrowserDecision->required
            && $postBrowserDecision->reason === 'anti_bot_challenge_candidate'
        ) {
            // Preserve the rendered challenge as an auditable artifact, but do
            // not mistake an HTTP 200 CAPTCHA/challenge page for successful
            // measurement of the intended website content.
            $this->persistAndAnalyze($runId, $rendered);

            throw new AcquisitionException(
                url: $url,
                category: 'anti_bot_challenge',
                retryable: false,
                message: sprintf(
                    'Browser reached an unresolved anti-bot challenge at %s.',
                    $observation->url,
                ),
            );
        }

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

        $candidates = $this->linkDiscoverer->discover($document);
        $relevantCandidates = 0;
        $scheduledPages = $this->jobs->scheduledCount($runId, 'fetch', $resourceId . '|');
        foreach ($candidates as $candidate) {
            if ($candidate->priority < $budget->minLinkPriority) {
                $this->runs->increment($runId, 'crawl_candidates_skipped_irrelevant');
                continue;
            }

            $relevantCandidates++;

            if ($scheduledPages >= $budget->maxPages) {
                $this->recordBudgetStop(
                    $runId,
                    $resourceId,
                    'pages',
                    $candidate->url,
                );
                break;
            }

            $inserted = $this->jobs->enqueue(
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
            if ($inserted) {
                $scheduledPages++;
            }
        }

        $this->runs->recordEvent(
            $runId,
            'crawl_discovery',
            $resourceId,
            [
                'url' => $document->finalUrl,
                'depth' => $depth,
                'candidates' => count($candidates),
                'relevant_candidates' => $relevantCandidates,
            ],
        );

        if ($depth === 0 && $relevantCandidates === 0) {
            $this->runs->increment($runId, 'resources_no_relevant_links');
        }
    }

    private function persistAndAnalyze(string $runId, FetchedDocument $document): void
    {
        $this->documents->put($document);
        $this->observations->recordDocument($runId, $document);
        $this->runs->increment($runId, 'documents_acquired');
        $this->runs->increment($runId, 'bytes_acquired', strlen($document->body));

        if ($document->statusCode < 200 || $document->statusCode >= 300) {
            return;
        }

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
            $status = ($counts['dead'] ?? 0) > 0
                ? RunStatus::Failed
                : RunStatus::Completed;

            $this->runs->setStatus($runId, $status);
            $this->recordResourceTerminals($runId);
            $this->runs->recordEvent(
                $runId,
                'run_terminal',
                null,
                ['status' => $status->value],
            );
            return;
        }

        $this->runs->setStatus($runId, RunStatus::Interrupted);
        $this->runs->recordEvent($runId, 'run_terminal', null, ['status' => 'interrupted']);
    }

    private function recordResourceTerminals(string $runId): void
    {
        $failed = [];
        $terminal = [];

        foreach ($this->runs->events($runId) as $event) {
            $subject = $event['subjectId'];
            if ($event['type'] === 'resource_terminal' && $subject !== null) {
                $terminal[$subject] = true;
            }

            if (
                $event['type'] === 'job_failure'
                && $subject !== null
                && ($event['detail']['status'] ?? null) === JobStatus::Dead->value
            ) {
                $failed[$subject] = true;
            }
        }

        foreach ($this->observations->resourceRecords($runId) as $resource) {
            $id = $resource['id'] ?? null;
            if (!is_string($id) || isset($terminal[$id])) {
                continue;
            }

            $hasNormalizedUrl = isset($resource['normalizedUrl'])
                && is_string($resource['normalizedUrl'])
                && $resource['normalizedUrl'] !== '';
            $status = !$hasNormalizedUrl
                ? 'invalid_url'
                : (isset($failed[$id]) ? 'failed' : 'completed');

            $this->runs->recordEvent(
                $runId,
                'resource_terminal',
                $id,
                ['status' => $status],
            );
        }
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

    private function recordMeasurementLimit(
        string $runId,
        string $resourceId,
        string $reason,
        string $url,
    ): void {
        $this->runs->increment($runId, 'measurement_limits');
        $this->runs->increment($runId, 'measurement_limit.' . $reason);
        $this->runs->recordEvent(
            $runId,
            'measurement_limit',
            $resourceId,
            [
                'reason' => $reason,
                'url' => $url,
            ],
        );
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
     * @param array<array-key,mixed> $usageBefore
     */
    private function recordStagePerformance(
        string $runId,
        string $stage,
        int $processed,
        float $startedAt,
        array $usageBefore,
    ): void {
        $elapsedMs = max(0.0, (microtime(true) - $startedAt) * 1000.0);
        /** @var array<array-key,mixed> $usageAfter */
        $usageAfter = getrusage();

        $userBefore = $this->rusageInt($usageBefore, 'ru_utime.tv_sec') * 1_000_000
            + $this->rusageInt($usageBefore, 'ru_utime.tv_usec');
        $userAfter = $this->rusageInt($usageAfter, 'ru_utime.tv_sec') * 1_000_000
            + $this->rusageInt($usageAfter, 'ru_utime.tv_usec');
        $systemBefore = $this->rusageInt($usageBefore, 'ru_stime.tv_sec') * 1_000_000
            + $this->rusageInt($usageBefore, 'ru_stime.tv_usec');
        $systemAfter = $this->rusageInt($usageAfter, 'ru_stime.tv_sec') * 1_000_000
            + $this->rusageInt($usageAfter, 'ru_stime.tv_usec');

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

    /**
     * @param array<array-key,mixed> $usage
     */
    private function rusageInt(array $usage, string $key): int
    {
        /** @psalm-suppress MixedAssignment getrusage() exposes platform-dependent mixed values. */
        $value = $usage[$key] ?? 0;

        return is_int($value) ? $value : 0;
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
