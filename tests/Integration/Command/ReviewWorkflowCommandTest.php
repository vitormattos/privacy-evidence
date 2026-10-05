<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Integration\Command;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Acquisition\FetchedDocument;
use PrivacyEvidence\Acquisition\FilesystemDocumentStore;
use PrivacyEvidence\Command\ReviewAgreementCommand;
use PrivacyEvidence\Command\ReviewEvaluateCommand;
use PrivacyEvidence\Command\ReviewImportCommand;
use PrivacyEvidence\Command\ReviewSampleCommand;
use PrivacyEvidence\Core\ObservationState;
use PrivacyEvidence\Evidence\EvidenceType;
use PrivacyEvidence\Evidence\PrivacyEvidence;
use PrivacyEvidence\Run\ResearchRun;
use PrivacyEvidence\Runtime\RuntimeFactory;
use PrivacyEvidence\Source\ImportedResource;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ReviewWorkflowCommandTest extends TestCase
{
    private string $projectRoot = '';

    protected function setUp(): void
    {
        $this->projectRoot = sys_get_temp_dir() . '/privacy-evidence-review-' . bin2hex(random_bytes(6));
        mkdir($this->projectRoot . '/data/derived', 0700, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->projectRoot);
    }

    public function testSampleImportAndAgreementWorkflow(): void
    {
        $runtime = RuntimeFactory::create($this->projectRoot);
        $run = new ResearchRun(
            id: 'review-run',
            startedAt: '2026-10-02T00:00:00Z',
            gitCommit: 'fixture',
            datasetHash: str_repeat('a', 64),
            protocolVersion: '1.0.0',
            versions: ['schema' => '1.0.0'],
            configuration: [],
        );
        $runtime->runs->create($run);

        foreach (
            [
                ['resource-a', ObservationState::Present],
                ['resource-b', ObservationState::Absent],
            ] as [$resourceId, $state]
        ) {
            $runtime->observations->recordEvidence($run->id, new PrivacyEvidence(
                EvidenceType::PrivacyNotice,
                $state,
                $resourceId,
                hash('sha256', $resourceId),
                'https://' . $resourceId . '.test/privacy',
                'fixture',
                '1.0.0',
                'test',
                excerpt: 'Privacy fixture',
                confidence: 0.8,
                needsReview: true,
            ));
        }

        $missing = new PrivacyEvidence(
            EvidenceType::CookieAcceptControl,
            ObservationState::Unknown,
            'resource-missing',
            str_repeat('b', 64),
            'https://missing.test/',
            'fixture',
            '1.0.0',
            'test',
        );
        $runtime->observations->recordEvidence($run->id, $missing);

        $package = $this->projectRoot . '/annotation.json';
        $sample = new CommandTester(new ReviewSampleCommand($this->projectRoot));
        self::assertSame(Command::SUCCESS, $sample->execute([
            'run-id' => $run->id,
            'output' => $package,
            '--per-stratum' => '2',
            '--seed' => 'test-seed',
        ]));

        /** @var mixed $decoded */
        $decoded = json_decode((string) file_get_contents($package), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        $cases = $decoded['cases'] ?? null;
        self::assertIsArray($cases);
        /** @var list<array<string,mixed>> $cases */
        self::assertCount(3, $cases);

        foreach ($cases as &$case) {
            if (($case['excerpt'] ?? null) === null) {
                continue;
            }
            $automatedState = $case['automatedState'] ?? null;
            self::assertIsString($automatedState);
            $case['humanState'] = $automatedState;
            $case['rationale'] = 'Independent review A.';
            $case['reviewedAt'] = '2026-10-02T01:00:00Z';
        }
        unset($case);
        $decoded['cases'] = $cases;
        file_put_contents($package, json_encode($decoded, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        $importA = new CommandTester(new ReviewImportCommand($this->projectRoot));
        self::assertSame(Command::SUCCESS, $importA->execute([
            'package' => $package,
            'reviewer-id' => 'reviewer-a',
        ]));

        /** @var mixed $importResult */
        $importResult = json_decode($importA->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($importResult);
        self::assertSame(2, $importResult['imported'] ?? null);
        self::assertSame(1, $importResult['deferred'] ?? null);
        self::assertSame([$missing->id()], $importResult['deferredEvidenceIds'] ?? null);

        $firstAnnotated = true;
        foreach ($cases as &$case) {
            if (($case['excerpt'] ?? null) === null) {
                continue;
            }
            if ($firstAnnotated) {
                $firstAnnotated = false;
                $case['humanState'] = $case['humanState'] === 'present' ? 'absent' : 'present';
            }
            $case['rationale'] = 'Independent review B.';
            $case['reviewedAt'] = '2026-10-02T02:00:00Z';
        }
        unset($case);
        $decoded['cases'] = $cases;
        file_put_contents($package, json_encode($decoded, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        $importB = new CommandTester(new ReviewImportCommand($this->projectRoot));
        self::assertSame(Command::SUCCESS, $importB->execute([
            'package' => $package,
            'reviewer-id' => 'reviewer-b',
        ]));

        $agreement = new CommandTester(new ReviewAgreementCommand($this->projectRoot));
        self::assertSame(Command::SUCCESS, $agreement->execute([
            'run-id' => $run->id,
            'reviewer-a' => 'reviewer-a',
            'reviewer-b' => 'reviewer-b',
        ]));

        /** @var mixed $metrics */
        $metrics = json_decode($agreement->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($metrics);
        $privacyNotice = $metrics['privacy_notice'] ?? null;
        self::assertIsArray($privacyNotice);
        self::assertSame(2, $privacyNotice['paired'] ?? null);
        self::assertArrayHasKey('kappa', $privacyNotice);

        $evaluate = new CommandTester(new ReviewEvaluateCommand($this->projectRoot));
        self::assertSame(Command::SUCCESS, $evaluate->execute([
            'run-id' => $run->id,
            'reviewer-id' => 'reviewer-a',
            'gold-version' => 'gold-test-v1',
        ]));

        /** @var mixed $evaluation */
        $evaluation = json_decode($evaluate->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($evaluation);
        self::assertSame('gold-test-v1', $evaluation['goldDatasetVersion'] ?? null);
        $signals = $evaluation['signals'] ?? null;
        self::assertIsArray($signals);
        self::assertArrayHasKey('privacy_notice', $signals);
    }

    public function testNullDetectorExcerptCanBeReviewedButForgedMaterialCannotDeferIt(): void
    {
        $runtime = RuntimeFactory::create($this->projectRoot);
        $run = new ResearchRun(
            id: 'context-run',
            startedAt: '2026-10-05T00:00:00Z',
            gitCommit: 'fixture',
            datasetHash: str_repeat('a', 64),
            protocolVersion: '1.0.0',
            versions: [],
            configuration: [],
        );
        $runtime->runs->create($run);
        $runtime->observations->recordResource($run->id, new ImportedResource(
            'sample-1',
            'Synthetic Example',
            'https://example.test/',
            'https://example.test/',
        ));
        $document = new FetchedDocument(
            'sample-1',
            'https://example.test/policy',
            'https://example.test/policy',
            200,
            'text/html',
            '<p>The responsible organization is Synthetic Example Ltd.</p>',
            '2026-10-05T00:00:00Z',
        );
        (new FilesystemDocumentStore($runtime->artifactDirectory))->put($document);
        $runtime->observations->recordDocument($run->id, $document);
        $evidence = new PrivacyEvidence(
            EvidenceType::ControllerIdentity,
            ObservationState::Absent,
            'sample-1',
            $document->sha256,
            $document->finalUrl,
            'fixture',
            '1.0.0',
            'test',
        );
        $runtime->observations->recordEvidence($run->id, $evidence);
        $path = $this->projectRoot . '/context.json';
        $sampler = new CommandTester(new ReviewSampleCommand($this->projectRoot));
        self::assertSame(Command::SUCCESS, $sampler->execute(['run-id' => $run->id, 'output' => $path]));
        /** @var array{cases: list<array<string, mixed>>} $package */
        $package = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        self::assertNull($package['cases'][0]['excerpt']);
        $forged = $package;
        $forged['cases'][0]['artifactHash'] = str_repeat('b', 64);
        $forged['cases'][0]['reviewContext'] = ['reason' => 'missing_artifact'];
        file_put_contents($path, json_encode($forged, JSON_THROW_ON_ERROR));
        $importer = new CommandTester(new ReviewImportCommand($this->projectRoot));
        self::assertSame(Command::INVALID, $importer->execute(['package' => $path, 'reviewer-id' => 'human-a']));
        self::assertCount(0, $runtime->reviews->decisions($run->id));
        $package['cases'][0]['humanState'] = 'present';
        $package['cases'][0]['rationale'] = 'The responsible organization is explicitly named in the archived page.';
        $package['cases'][0]['reviewedAt'] = '2026-10-05T01:00:00Z';
        file_put_contents($path, json_encode($package, JSON_THROW_ON_ERROR));
        self::assertSame(Command::SUCCESS, $importer->execute(['package' => $path, 'reviewer-id' => 'human-a']));
        self::assertCount(1, $runtime->reviews->decisions($run->id));
    }

    public function testRejectsTestPackageBeforeOpeningResearchStorage(): void
    {
        $path = $this->projectRoot . '/form-test.json';
        file_put_contents($path, '{"testMode":true,"runId":"test","cases":[]}');
        $tester = new CommandTester(new ReviewImportCommand($this->projectRoot));

        self::assertSame(Command::INVALID, $tester->execute([
            'package' => $path,
            'reviewer-id' => 'human-reviewer',
        ]));
        self::assertStringContainsString('test packages cannot be imported', $tester->getDisplay());
        self::assertFileDoesNotExist($this->projectRoot . '/data/derived/privacy-evidence.sqlite');
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
