<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Integration\Pipeline;

use PDO;
use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Acquisition\FilesystemDocumentStore;
use PrivacyEvidence\Acquisition\HttpFetcher;
use PrivacyEvidence\Acquisition\HttpProbe;
use PrivacyEvidence\Crawl\CrawlBudget;
use PrivacyEvidence\Pipeline\DefaultDetectorRegistry;
use PrivacyEvidence\Pipeline\PipelineConfig;
use PrivacyEvidence\Pipeline\ResearchPipeline;
use PrivacyEvidence\Queue\SqliteJobQueue;
use PrivacyEvidence\Review\SqliteReviewQueue;
use PrivacyEvidence\Run\ResearchRun;
use PrivacyEvidence\Run\SqliteRunStore;
use PrivacyEvidence\Source\Csv\CsvSource;
use PrivacyEvidence\Storage\SqliteObservationStore;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ResearchPipelineFocusedCrawlTest extends TestCase
{
    public function testDefaultCrawlSchedulesEvidenceRelevantLinksButSkipsGenericNavigation(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite not available');
        }

        $directory = sys_get_temp_dir() . '/privacy-evidence-focused-' . bin2hex(random_bytes(6));
        mkdir($directory, 0700, true);

        try {
            $responses = [
                new MockResponse('', [
                    'http_code' => 200,
                    'response_headers' => ['content-type: text/html'],
                ]),
                new MockResponse(
                    '<a href="/privacy">Privacy</a>'
                    . '<a href="/contact">Contact</a>'
                    . '<a href="/news">News</a>',
                    [
                        'http_code' => 200,
                        'response_headers' => ['content-type: text/html'],
                    ],
                ),
            ];
            $client = new MockHttpClient($responses);

            $pdo = new PDO('sqlite::memory:');
            $runs = new SqliteRunStore($pdo);
            $jobs = new SqliteJobQueue($pdo, retryBaseDelayMs: 0);
            $observations = new SqliteObservationStore($pdo);

            $csv = $directory . '/sites.csv';
            file_put_contents($csv, "id,name,url\nsite-1,Example,https://example.test/\n");

            $pipeline = new ResearchPipeline(
                runs: $runs,
                jobs: $jobs,
                observations: $observations,
                documents: new FilesystemDocumentStore($directory . '/artifacts'),
                reviews: new SqliteReviewQueue($pdo),
                fetcher: new HttpFetcher($client, new HttpProbe($client)),
                detectors: DefaultDetectorRegistry::create(),
                config: new PipelineConfig(
                    maxJobsPerInvocation: 1,
                    enableBrowserEscalation: false,
                    minHostDelayMs: 0,
                    crawlBudget: new CrawlBudget(minLinkPriority: 50),
                ),
            );

            $hash = hash_file('sha256', $csv);
            $run = new ResearchRun(
                id: 'focused-crawl',
                startedAt: '2026-10-04T00:00:00Z',
                gitCommit: 'fixture',
                datasetHash: is_string($hash) ? $hash : str_repeat('0', 64),
                protocolVersion: 'test',
                versions: ['schema' => 'test'],
                configuration: [],
            );

            $pipeline->start($run, new CsvSource($csv));
            self::assertSame(1, $pipeline->executeStage($run->id, 'fetch', 1));

            self::assertSame(
                3,
                $jobs->scheduledCount($run->id, 'fetch', 'site-1|'),
                'The homepage plus privacy and contact links should be scheduled.',
            );
            self::assertSame(
                1,
                $runs->telemetry($run->id)['crawl_candidates_skipped_irrelevant'] ?? 0,
            );
        } finally {
            $this->removeDirectory($directory);
        }
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . '/' . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    }
}
