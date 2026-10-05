<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Integration\Pipeline;

use PDO;
use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Acquisition\FilesystemDocumentStore;
use PrivacyEvidence\Acquisition\HttpFetcher;
use PrivacyEvidence\Acquisition\HttpProbe;
use PrivacyEvidence\Analysis\RegulatoryAnalysisService;
use PrivacyEvidence\Analysis\RunExporter;
use PrivacyEvidence\Pipeline\DefaultDetectorRegistry;
use PrivacyEvidence\Pipeline\DefaultProfileRegistry;
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

final class FullPopulationEndToEndTest extends TestCase
{
    public function testEveryPopulationMemberReachesAnAuditableFinalResult(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite not available');
        }

        $directory = sys_get_temp_dir() . '/privacy-evidence-full-population-' . bin2hex(random_bytes(6));
        mkdir($directory, 0700, true);

        try {
            $dataset = $directory . '/population.csv';
            file_put_contents($dataset, implode(PHP_EOL, [
                'id,name,url',
                'website,Website,https://example.test/',
                'social,Social,https://instagram.com/example',
                'hosted,Hosted,https://church.blogspot.com/',
                'malformed,Malformed,mailto:test@example.org',
                'empty,Empty,',
            ]) . PHP_EOL);

            // HttpProbe and HttpFetcher each consume one response for the only
            // eligible institutional website.
            $client = new MockHttpClient([
                new MockResponse('', [
                    'http_code' => 200,
                    'response_headers' => ['content-type: text/html'],
                ]),
                new MockResponse(
                    '<html><body><p>Política de privacidade</p></body></html>',
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
                config: new PipelineConfig(
                    enableBrowserEscalation: false,
                    minHostDelayMs: 0,
                ),
            );

            $hash = hash_file('sha256', $dataset);
            $run = new ResearchRun(
                id: 'full-population-e2e',
                startedAt: '2026-10-05T00:00:00Z',
                gitCommit: 'fixture',
                datasetHash: is_string($hash) ? $hash : str_repeat('0', 64),
                protocolVersion: 'test',
                versions: ['schema' => 'test'],
                configuration: [],
            );

            $pipeline->start($run, new CsvSource($dataset));
            $pipeline->execute($run->id);

            (new RegulatoryAnalysisService(
                $observations,
                DefaultProfileRegistry::create(),
            ))->analyze($run->id);

            $export = $directory . '/export';
            (new RunExporter(new RuntimeContext(
                runs: $runs,
                jobs: $jobs,
                observations: $observations,
                reviews: $reviews,
                artifactDirectory: $directory . '/artifacts',
            )))->export($run->id, $export);

            /** @var mixed $population */
            $population = json_decode(
                (string) file_get_contents($export . '/population-results.json'),
                true,
                flags: JSON_THROW_ON_ERROR,
            );
            self::assertIsArray($population);
            self::assertCount(5, $population);

            $byId = [];
            /** @psalm-suppress MixedAssignment */
            foreach ($population as $row) {
                if (is_array($row) && is_string($row['resourceId'] ?? null)) {
                    $byId[$row['resourceId']] = $row;
                }
            }

            self::assertCount(5, $byId);
            self::assertSame('measured', $byId['website']['measurementStatus'] ?? null);
            self::assertTrue($byId['website']['eligibleForWebsiteMeasurement'] ?? false);
            self::assertIsString($byId['website']['lgpdPublicEvidenceState'] ?? null);

            foreach (['social', 'hosted', 'malformed', 'empty'] as $id) {
                self::assertFalse($byId[$id]['eligibleForWebsiteMeasurement'] ?? true);
                self::assertSame('not_eligible', $byId[$id]['measurementStatus'] ?? null);
                self::assertNull($byId[$id]['lgpdPublicEvidenceState'] ?? null);
                self::assertNotSame('missing_outcome', $byId[$id]['primaryReason'] ?? null);
            }

            /** @var mixed $summary */
            $summary = json_decode(
                (string) file_get_contents($export . '/population-summary.json'),
                true,
                flags: JSON_THROW_ON_ERROR,
            );
            self::assertIsArray($summary);
            self::assertSame(5, $summary['population'] ?? null);
            self::assertSame(5, $summary['accountedResources'] ?? null);
            self::assertTrue($summary['completePopulationAccounting'] ?? false);
            self::assertSame(1, $summary['eligibleForWebsiteMeasurement'] ?? null);

            $profileResults = $observations->profileResults($run->id);
            self::assertNotEmpty($profileResults);
            foreach ($profileResults as $profileResult) {
                self::assertSame('website', $profileResult['resourceId'] ?? null);
            }
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
