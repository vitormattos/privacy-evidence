<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Integration\Analysis;

use PDO;
use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Acquisition\FetchedDocument;
use PrivacyEvidence\Analysis\RegulatoryAnalysisService;
use PrivacyEvidence\Analysis\RunExporter;
use PrivacyEvidence\Core\ObservationState;
use PrivacyEvidence\Evidence\EvidenceType;
use PrivacyEvidence\Evidence\PrivacyEvidence;
use PrivacyEvidence\Pipeline\DefaultProfileRegistry;
use PrivacyEvidence\Queue\SqliteJobQueue;
use PrivacyEvidence\Review\SqliteReviewQueue;
use PrivacyEvidence\Run\ResearchRun;
use PrivacyEvidence\Run\RunStatus;
use PrivacyEvidence\Run\SqliteRunStore;
use PrivacyEvidence\Runtime\RuntimeContext;
use PrivacyEvidence\Source\ImportedResource;
use PrivacyEvidence\Source\ResourceType;
use PrivacyEvidence\Storage\SqliteObservationStore;

final class RunExporterTest extends TestCase
{
    public function testExportsDeterministicJsonCsvAndReportFromPersistedState(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite not available');
        }

        $pdo = new PDO('sqlite::memory:');
        $runs = new SqliteRunStore($pdo);
        $observations = new SqliteObservationStore($pdo);
        $reviews = new SqliteReviewQueue($pdo);
        $jobs = new SqliteJobQueue($pdo);

        $run = new ResearchRun(
            id: 'run-export',
            startedAt: '2026-10-02T00:00:00Z',
            gitCommit: 'abc123',
            datasetHash: str_repeat('a', 64),
            protocolVersion: '1.0.0',
            versions: ['schema' => '1.0.0'],
            configuration: ['http_concurrency' => 4],
        );
        $runs->create($run);
        $runs->setStatus($run->id, RunStatus::Completed);
        $runs->increment($run->id, 'documents_per_second', 2);

        $resource = new ImportedResource(
            'site-1',
            'Example',
            'example.test',
            'https://example.test/',
            ResourceType::InstitutionalWebsite,
        );
        $observations->recordResource($run->id, $resource);

        $document = new FetchedDocument(
            resourceId: $resource->id,
            requestedUrl: 'https://example.test/',
            finalUrl: 'https://example.test/',
            statusCode: 200,
            mediaType: 'text/html',
            body: '<p>Privacy policy</p>',
            fetchedAt: '2026-10-02T00:00:01Z',
        );
        $observations->recordDocument($run->id, $document);

        $observations->recordEvidence($run->id, new PrivacyEvidence(
            type: EvidenceType::PrivacyNotice,
            state: ObservationState::Present,
            resourceId: $resource->id,
            artifactHash: $document->sha256,
            sourceUrl: $document->finalUrl,
            detector: 'fixture',
            detectorVersion: '1.0.0',
            method: 'fixture',
        ));

        (new RegulatoryAnalysisService(
            $observations,
            DefaultProfileRegistry::create(),
        ))->analyze($run->id);

        $directory = sys_get_temp_dir() . '/privacy-evidence-export-' . bin2hex(random_bytes(4));
        $runtime = new RuntimeContext(
            runs: $runs,
            jobs: $jobs,
            observations: $observations,
            reviews: $reviews,
            artifactDirectory: $directory . '/artifacts',
        );

        (new RunExporter($runtime))->export($run->id, $directory);

        self::assertFileExists($directory . '/analysis.json');
        self::assertFileExists($directory . '/resources.csv');
        self::assertFileExists($directory . '/documents.csv');
        self::assertFileExists($directory . '/evidence.csv');
        self::assertFileExists($directory . '/profiles.csv');
        self::assertFileExists($directory . '/profile-summary.csv');
        self::assertFileExists($directory . '/profile-summary.json');
        self::assertFileExists($directory . '/failures.json');
        self::assertFileExists($directory . '/report.md');

        /** @var mixed $decoded */
        $decoded = json_decode(
            (string) file_get_contents($directory . '/analysis.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        self::assertIsArray($decoded);
        self::assertSame('run-export', $decoded['runId'] ?? null);
        self::assertSame('2026-10-02T00:00:00Z', $decoded['generatedAt'] ?? null);

        $metrics = $decoded['metrics'] ?? null;
        self::assertIsArray($metrics);
        $counts = $metrics['counts'] ?? null;
        self::assertIsArray($counts);
        self::assertSame(1, $counts['resources'] ?? null);

        $byType = $metrics['evidenceByType'] ?? null;
        self::assertIsArray($byType);
        $privacyNotice = $byType['privacy_notice'] ?? null;
        self::assertIsArray($privacyNotice);
        self::assertSame(1, $privacyNotice['eligibleResources'] ?? null);

        $report = (string) file_get_contents($directory . '/report.md');
        self::assertStringContainsString('not a legal-compliance certification', $report);
        self::assertStringContainsString('Eligible resources', $report);
        self::assertStringContainsString('Regulatory public-evidence summary', $report);
        self::assertStringContainsString('lgpd', $report);

        $summaryCsv = (string) file_get_contents($directory . '/profile-summary.csv');
        self::assertStringContainsString('publicEvidenceState', $summaryCsv);
        self::assertStringContainsString('publicEvidenceCoverageRate', $summaryCsv);
        self::assertStringContainsString('fullObservedSupportRate', $summaryCsv);
        self::assertStringContainsString('anyObservedSupportRate', $summaryCsv);
        self::assertStringContainsString('site-1', $summaryCsv);
        self::assertStringContainsString('lgpd', $summaryCsv);

        /** @var mixed $summaryDecoded */
        $summaryDecoded = json_decode(
            (string) file_get_contents($directory . '/profile-summary.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        self::assertIsArray($summaryDecoded);
        $lgpdSummary = null;
        foreach ($summaryDecoded as $item) {
            if (
                is_array($item)
                && ($item['resourceId'] ?? null) === 'site-1'
                && ($item['profile'] ?? null) === 'lgpd'
            ) {
                $lgpdSummary = $item;
                break;
            }
        }
        self::assertIsArray($lgpdSummary);
        self::assertArrayHasKey('measurableRequirements', $lgpdSummary);
        self::assertArrayHasKey('coverageDenominator', $lgpdSummary);
        self::assertArrayHasKey('publicEvidenceCoverageRate', $lgpdSummary);
        self::assertArrayHasKey('fullObservedSupportRate', $lgpdSummary);
        self::assertArrayHasKey('anyObservedSupportRate', $lgpdSummary);
    }
}
