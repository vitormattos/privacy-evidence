<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Integration\Pipeline;

use PDO;
use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Acquisition\FilesystemDocumentStore;
use PrivacyEvidence\Acquisition\HttpFetcher;
use PrivacyEvidence\Acquisition\HttpProbe;
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

final class ResearchPipelinePopulationAccountingTest extends TestCase
{
    public function testCompletePopulationIsPersistedButOnlyInstitutionalWebsitesAreCrawled(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite not available');
        }

        $directory = sys_get_temp_dir() . '/privacy-evidence-population-' . bin2hex(random_bytes(6));
        mkdir($directory, 0700, true);

        try {
            $csv = $directory . '/population.csv';
            file_put_contents($csv, implode(PHP_EOL, [
                'id,name,url',
                'site,Website,https://example.test/',
                'social,Social,https://instagram.com/example',
                'bad,Bad,mailto:test@example.org',
            ]) . PHP_EOL);

            $pdo = new PDO('sqlite::memory:');
            $runs = new SqliteRunStore($pdo);
            $jobs = new SqliteJobQueue($pdo, retryBaseDelayMs: 0);
            $observations = new SqliteObservationStore($pdo);
            $client = new MockHttpClient();

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
                ),
            );

            $hash = hash_file('sha256', $csv);
            $run = new ResearchRun(
                id: 'population-accounting',
                startedAt: '2026-10-05T00:00:00Z',
                gitCommit: 'fixture',
                datasetHash: is_string($hash) ? $hash : str_repeat('0', 64),
                protocolVersion: 'test',
                versions: ['schema' => 'test'],
                configuration: [],
            );

            $pipeline->start($run, new CsvSource($csv));

            self::assertCount(3, $observations->resourceRecords($run->id));
            self::assertSame(['pending' => 1], $jobs->counts($run->id));

            $terminalByResource = [];
            foreach ($runs->events($run->id) as $event) {
                if ($event['type'] !== 'resource_terminal' || $event['subjectId'] === null) {
                    continue;
                }
                $terminalByResource[$event['subjectId']] = $event['detail'];
            }

            self::assertSame('not_eligible', $terminalByResource['social']['status'] ?? null);
            self::assertSame('social_network', $terminalByResource['social']['category'] ?? null);
            self::assertSame('not_eligible', $terminalByResource['bad']['status'] ?? null);
            self::assertSame('malformed', $terminalByResource['bad']['category'] ?? null);

            $telemetry = $runs->telemetry($run->id);
            self::assertSame(3.0, $telemetry['resources_imported'] ?? null);
            self::assertSame(2.0, $telemetry['resources_not_eligible'] ?? null);
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
