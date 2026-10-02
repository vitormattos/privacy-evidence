<?php

declare(strict_types=1);

namespace PrivacyEvidence\Pipeline;

use PrivacyEvidence\Acquisition\DocumentStore;
use PrivacyEvidence\Acquisition\FetchedDocument;
use PrivacyEvidence\Acquisition\HttpFetcher;
use PrivacyEvidence\Browser\BrowserEscalationPolicy;
use PrivacyEvidence\Browser\BrowserProvider;
use PrivacyEvidence\Queue\Job;
use PrivacyEvidence\Queue\JobQueue;
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
                ),
            );
        }

        $this->runs->setStatus($run->id, RunStatus::Running);
    }

    public function execute(string $runId): void
    {
        $processed = 0;
        $limit = $this->config->maxJobsPerInvocation;

        while ($limit === 0 || $processed < $limit) {
            $job = $this->jobs->reserve($runId, 'fetch');
            if ($job === null) {
                break;
            }

            try {
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

                if ($this->config->enableBrowserEscalation && $this->browser !== null) {
                    $decision = $this->browserPolicy->decide($document);
                    if ($decision->required) {
                        $this->runs->increment($runId, 'browser_escalations');
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
                        );
                        $this->persistAndAnalyze($runId, $rendered);
                    }
                }

                $this->jobs->complete($job->id);
                $this->runs->increment($runId, 'jobs_completed');
            } catch (\Throwable $e) {
                $this->jobs->fail($job->id, $e->getMessage());
                $this->runs->increment($runId, 'jobs_failed');
            }

            $processed++;
        }

        $counts = $this->jobs->counts($runId);
        if (($counts['pending'] ?? 0) === 0 && ($counts['running'] ?? 0) === 0) {
            $this->runs->setStatus($runId, RunStatus::Completed);
        } else {
            $this->runs->setStatus($runId, RunStatus::Interrupted);
        }
    }

    public function resume(string $runId): void
    {
        $this->runs->setStatus($runId, RunStatus::Running);
        $this->execute($runId);
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

        if ($depth >= $budget->maxDepth
            || $usage['pages'] >= $budget->maxPages
            || $usage['bytes'] >= $budget->maxBytes
            || (time() - $crawlStartedAt) >= $budget->maxDurationSeconds
        ) {
            $this->runs->increment($runId, 'crawl_budget_stops');
            return;
        }

        foreach ($this->linkDiscoverer->discover($document) as $candidate) {
            if ($this->jobs->scheduledCount($runId, 'fetch', $resourceId . '|') >= $budget->maxPages) {
                $this->runs->increment($runId, 'crawl_page_budget_stops');
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

        $allEvidence = [];
        foreach ($this->detectors->detectors as $detector) {
            foreach ($detector->detect($document) as $evidence) {
                $this->observations->recordEvidence($runId, $evidence);
                $allEvidence[] = $evidence;
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
}
