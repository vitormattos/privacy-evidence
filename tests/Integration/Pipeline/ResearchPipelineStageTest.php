<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Integration\Pipeline;

use PDO;
use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Acquisition\FilesystemDocumentStore;
use PrivacyEvidence\Acquisition\HttpFetcher;
use PrivacyEvidence\Acquisition\HttpProbe;
use PrivacyEvidence\Browser\BrowserObservation;
use PrivacyEvidence\Browser\BrowserProvider;
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

final class ResearchPipelineStageTest extends TestCase
{
    private string $directory = '';

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/privacy-evidence-stage-' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    public function testFetchStageOnlyEnqueuesBrowserAndBrowserStageExecutesIt(): void
    {
        $responses = [
            new MockResponse('', [
                'http_code' => 200,
                'response_headers' => ['content-type: text/html'],
            ]),
            new MockResponse(
                '<html><body><div id="app"></div><script src="/app.js"></script></body></html>',
                [
                    'http_code' => 200,
                    'response_headers' => ['content-type: text/html'],
                ],
            ),
        ];
        $client = new MockHttpClient($responses);
        $fetcher = new HttpFetcher($client, new HttpProbe($client));

        $pdo = new PDO('sqlite::memory:');
        $runs = new SqliteRunStore($pdo);
        $jobs = new SqliteJobQueue($pdo, retryBaseDelayMs: 0);
        $observations = new SqliteObservationStore($pdo);
        $reviews = new SqliteReviewQueue($pdo);

        $browser = new class implements BrowserProvider {
            public int $calls;

            public function __construct()
            {
                $this->calls = 0;
            }

            public function observe(string $url, array $actions = []): BrowserObservation
            {
                $this->calls++;

                return new BrowserObservation(
                    url: $url,
                    html: '<html><body><main>Rendered privacy content</main></body></html>',
                    capturedAt: '2026-10-02T00:00:01Z',
                    browserVersion: 'fixture-browser',
                    metadata: [],
                );
            }
        };

        $csv = $this->directory . '/sites.csv';
        file_put_contents(
            $csv,
            "id,name,url\nsite-1,Example,https://example.test/\n",
        );

        $pipeline = new ResearchPipeline(
            runs: $runs,
            jobs: $jobs,
            observations: $observations,
            documents: new FilesystemDocumentStore($this->directory . '/artifacts'),
            reviews: $reviews,
            fetcher: $fetcher,
            detectors: DefaultDetectorRegistry::create(),
            browser: $browser,
            config: new PipelineConfig(
                maxJobsPerInvocation: 1,
                minHostDelayMs: 0,
            ),
        );

        $datasetHash = hash_file('sha256', $csv);

        $run = new ResearchRun(
            id: 'run-stage-test',
            startedAt: '2026-10-02T00:00:00Z',
            gitCommit: 'fixture',
            datasetHash: $datasetHash === false ? str_repeat('0', 64) : $datasetHash,
            protocolVersion: 'test',
            versions: ['schema' => 'test'],
            configuration: [],
        );

        $pipeline->start($run, new CsvSource($csv));
        self::assertSame(['pending' => 1], $jobs->stageCounts($run->id, 'fetch'));

        self::assertSame(1, $pipeline->executeStage($run->id, 'fetch', 1));
        self::assertSame(0, $browser->calls);
        self::assertSame(['pending' => 1], $jobs->stageCounts($run->id, 'browser'));

        self::assertSame(1, $pipeline->executeStage($run->id, 'browser', 1));
        self::assertSame(1, $browser->calls);
        self::assertSame(2, $observations->counts($run->id)['documents']);
        self::assertSame(['completed' => 1], $jobs->stageCounts($run->id, 'browser'));
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
