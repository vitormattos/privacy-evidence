<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Integration\Pipeline;

use PDO;
use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Acquisition\FilesystemDocumentStore;
use PrivacyEvidence\Acquisition\HttpFetcher;
use PrivacyEvidence\Acquisition\HttpProbe;
use PrivacyEvidence\Analysis\RunExporter;
use PrivacyEvidence\Pipeline\DefaultDetectorRegistry;
use PrivacyEvidence\Pipeline\PipelineConfig;
use PrivacyEvidence\Pipeline\ResearchPipeline;
use PrivacyEvidence\Queue\SqliteJobQueue;
use PrivacyEvidence\Review\SqliteReviewQueue;
use PrivacyEvidence\Run\ResearchRun;
use PrivacyEvidence\Run\SqliteRunStore;
use PrivacyEvidence\Runtime\RuntimeContext;
use PrivacyEvidence\Source\Csv\CsvSource;
use PrivacyEvidence\Storage\SqliteObservationStore;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class BrowserUnavailableMeasurementTest extends TestCase
{
    public function testJavascriptShellWithoutBrowserIsNotReportedAsMeasured(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite not available');
        }

        $directory = sys_get_temp_dir() . '/privacy-evidence-browser-unavailable-' . bin2hex(random_bytes(6));
        mkdir($directory, 0700, true);

        try {
            $dataset = $directory . '/sites.csv';
            file_put_contents(
                $dataset,
                "id,name,url\nsite,Website,https://example.test/\n",
            );

            $client = new MockHttpClient([
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
            ]);

            $pdo = new PDO('sqlite::memory:');
            $runs = new SqliteRunStore($pdo);
            $jobs = new SqliteJobQueue($pdo, retryBaseDelayMs: 0);
            $observations = new SqliteObservationStore($pdo);
            $reviews = new SqliteReviewQueue($pdo);

            $pipeline = new ResearchPipeline(
                runs: $runs,
                jobs: $jobs,
                observations: $observations,
                documents: new FilesystemDocumentStore($directory . '/artifacts'),
                reviews: $reviews,
                fetcher: new HttpFetcher($client, new HttpProbe($client)),
                detectors: DefaultDetectorRegistry::create(),
                browser: null,
                config: new PipelineConfig(
                    enableBrowserEscalation: false,
                    minHostDelayMs: 0,
                ),
            );

            $hash = hash_file('sha256', $dataset);
            $run = new ResearchRun(
                id: 'browser-unavailable',
                startedAt: '2026-10-05T00:00:00Z',
                gitCommit: 'fixture',
                datasetHash: is_string($hash) ? $hash : str_repeat('0', 64),
                protocolVersion: 'test',
                versions: ['schema' => 'test'],
                configuration: [],
            );

            $pipeline->start($run, new CsvSource($dataset));
            $pipeline->execute($run->id);

            $export = $directory . '/export';
            (new RunExporter(new RuntimeContext(
                runs: $runs,
                jobs: $jobs,
                observations: $observations,
                reviews: $reviews,
                artifactDirectory: $directory . '/artifacts',
            )))->export($run->id, $export);

            /** @var mixed $outcomes */
            $outcomes = json_decode(
                (string) file_get_contents($export . '/resource-outcomes.json'),
                true,
                flags: JSON_THROW_ON_ERROR,
            );
            self::assertIsArray($outcomes);
            $outcome = $outcomes[0] ?? null;
            self::assertIsArray($outcome);
            self::assertSame('not_measurable', $outcome['measurementStatus'] ?? null);
            self::assertSame(
                'dynamic_content_browser_unavailable',
                $outcome['primaryReason'] ?? null,
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
