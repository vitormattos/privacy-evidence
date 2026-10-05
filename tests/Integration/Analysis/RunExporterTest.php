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
    public function testPopulationResultsKeepNonEligiblePopulationMembers(): void
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
            id: 'population-export',
            startedAt: '2026-10-05T00:00:00Z',
            gitCommit: 'fixture',
            datasetHash: str_repeat('b', 64),
            protocolVersion: '1.0.0',
            versions: ['schema' => '1.0.0'],
            configuration: [],
        );
        $runs->create($run);
        $runs->setStatus($run->id, RunStatus::Completed);

        $site = new ImportedResource(
            'site-1',
            'Website',
            'https://example.test/',
            'https://example.test/',
            ResourceType::InstitutionalWebsite,
        );
        $social = new ImportedResource(
            'social-1',
            'Social',
            'https://instagram.com/example',
            'https://instagram.com/example',
            ResourceType::SocialNetwork,
            classificationRule: 'known_social_host',
            classificationConfidence: 1.0,
        );
        $observations->recordResource($run->id, $site);
        $observations->recordResource($run->id, $social);
        $runs->recordEvent(
            $run->id,
            'resource_terminal',
            $social->id,
            [
                'status' => 'not_eligible',
                'category' => 'social_network',
                'classification_rule' => 'known_social_host',
            ],
        );

        (new RegulatoryAnalysisService(
            $observations,
            DefaultProfileRegistry::create(),
        ))->analyze($run->id);

        $directory = sys_get_temp_dir() . '/privacy-evidence-population-export-' . bin2hex(random_bytes(4));
        $runtime = new RuntimeContext(
            runs: $runs,
            jobs: $jobs,
            observations: $observations,
            reviews: $reviews,
            artifactDirectory: $directory . '/artifacts',
        );

        try {
            (new RunExporter($runtime))->export($run->id, $directory);

            /** @var mixed $population */
            $population = json_decode(
                (string) file_get_contents($directory . '/population-results.json'),
                true,
                flags: JSON_THROW_ON_ERROR,
            );
            self::assertIsArray($population);
            self::assertCount(2, $population);

            $byId = [];
            /** @psalm-suppress MixedAssignment */
            foreach ($population as $row) {
                if (!is_array($row)) {
                    continue;
                }

                /** @psalm-suppress MixedAssignment */
                $resourceIdValue = $row['resourceId'] ?? null;
                if (is_string($resourceIdValue)) {
                    $byId[$resourceIdValue] = $row;
                }
            }

            self::assertSame('not_eligible', $byId['social-1']['measurementStatus'] ?? null);
            self::assertSame('known_social_host', $byId['social-1']['primaryReason'] ?? null);
            self::assertNull($byId['social-1']['lgpdPublicEvidenceState'] ?? null);
            self::assertFalse($byId['social-1']['duplicateNormalizedUrl'] ?? true);
            self::assertArrayHasKey('site-1', $byId);
            self::assertArrayHasKey('lgpdPublicEvidenceState', $byId['site-1']);
        } finally {
            $this->removeDirectory($directory);
        }
    }

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
        self::assertFileExists($directory . '/resource-outcomes.json');
        self::assertFileExists($directory . '/resource-outcomes.csv');
        self::assertFileExists($directory . '/population-results.json');
        self::assertFileExists($directory . '/population-results.csv');
        self::assertFileExists($directory . '/population-summary.json');
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

        /** @var mixed $outcomes */
        $outcomes = json_decode(
            (string) file_get_contents($directory . '/resource-outcomes.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        self::assertIsArray($outcomes);
        self::assertCount(1, $outcomes);
        $firstOutcome = $outcomes[0] ?? null;
        self::assertIsArray($firstOutcome);
        self::assertSame('site-1', $firstOutcome['resourceId'] ?? null);
        self::assertSame('measured', $firstOutcome['measurementStatus'] ?? null);

        /** @var mixed $population */
        $population = json_decode(
            (string) file_get_contents($directory . '/population-results.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        self::assertIsArray($population);
        self::assertCount(1, $population);
        $populationRow = $population[0] ?? null;
        self::assertIsArray($populationRow);
        self::assertSame('site-1', $populationRow['resourceId'] ?? null);
        self::assertSame('institutional_website', $populationRow['classificationType'] ?? null);
        self::assertTrue($populationRow['eligibleForWebsiteMeasurement'] ?? false);
        self::assertSame('measured', $populationRow['measurementStatus'] ?? null);
        self::assertArrayHasKey('lgpdPublicEvidenceState', $populationRow);

        /** @var mixed $populationSummary */
        $populationSummary = json_decode(
            (string) file_get_contents($directory . '/population-summary.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        self::assertIsArray($populationSummary);
        self::assertSame(1, $populationSummary['population'] ?? null);
        self::assertSame(1, $populationSummary['accountedResources'] ?? null);
        self::assertTrue($populationSummary['completePopulationAccounting'] ?? false);
        self::assertSame(1, $populationSummary['eligibleForWebsiteMeasurement'] ?? null);

        /** @var mixed $summaryDecoded */
        $summaryDecoded = json_decode(
            (string) file_get_contents($directory . '/profile-summary.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        self::assertIsArray($summaryDecoded);
        $lgpdSummary = null;
        /** @psalm-suppress MixedAssignment */
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
