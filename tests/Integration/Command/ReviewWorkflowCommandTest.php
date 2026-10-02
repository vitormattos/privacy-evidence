<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Integration\Command;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Command\ReviewAgreementCommand;
use PrivacyEvidence\Command\ReviewImportCommand;
use PrivacyEvidence\Command\ReviewSampleCommand;
use PrivacyEvidence\Core\ObservationState;
use PrivacyEvidence\Evidence\EvidenceType;
use PrivacyEvidence\Evidence\PrivacyEvidence;
use PrivacyEvidence\Run\ResearchRun;
use PrivacyEvidence\Runtime\RuntimeFactory;
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

        foreach ([
            ['resource-a', ObservationState::Present],
            ['resource-b', ObservationState::Absent],
        ] as [$resourceId, $state]) {
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
        self::assertCount(2, $cases);

        foreach ($cases as &$case) {
            self::assertIsArray($case);
            $case['humanState'] = $case['automatedState'];
            $case['rationale'] = 'Independent review A.';
            $case['reviewedAt'] = '2026-10-02T01:00:00Z';
        }
        unset($case);
        file_put_contents($package, json_encode($decoded, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        $importA = new CommandTester(new ReviewImportCommand($this->projectRoot));
        self::assertSame(Command::SUCCESS, $importA->execute([
            'package' => $package,
            'reviewer-id' => 'reviewer-a',
        ]));

        foreach ($cases as $index => &$case) {
            self::assertIsArray($case);
            if ($index === 0) {
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
