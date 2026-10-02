<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Integration\Command;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Command\ResumeCommand;
use PrivacyEvidence\Command\SourceImportCommand;
use PrivacyEvidence\Command\StatusCommand;
use PrivacyEvidence\Run\ResearchRun;
use PrivacyEvidence\Run\RunStatus;
use PrivacyEvidence\Runtime\RuntimeFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class CommandWorkflowTest extends TestCase
{
    private string $projectRoot;

    protected function setUp(): void
    {
        $this->projectRoot = sys_get_temp_dir() . '/privacy-evidence-cli-' . bin2hex(random_bytes(6));
        mkdir($this->projectRoot . '/data/derived', 0700, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->projectRoot);
    }

    public function testSourceImportProducesCanonicalJsonLines(): void
    {
        $fixture = dirname(__DIR__, 2) . '/Fixtures/sources/valid.csv';
        $tester = new CommandTester(new SourceImportCommand());

        $exit = $tester->execute(['dataset' => $fixture]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('"sourceId"', $tester->getDisplay());
        self::assertStringContainsString('"snapshot"', $tester->getDisplay());
    }

    public function testStatusReportsDurableRunStateAndTelemetry(): void
    {
        $runtime = RuntimeFactory::create($this->projectRoot);
        $run = new ResearchRun(
            id: 'status-run',
            startedAt: '2026-10-02T00:00:00Z',
            gitCommit: 'abc123',
            datasetHash: str_repeat('a', 64),
            protocolVersion: '1.0.0',
            versions: ['schema' => '1.0.0'],
            configuration: [],
        );
        $runtime->runs->create($run);
        $runtime->runs->setStatus($run->id, RunStatus::Interrupted);
        $runtime->runs->increment($run->id, 'jobs_completed', 3);

        $tester = new CommandTester(new StatusCommand($this->projectRoot));
        $exit = $tester->execute(['run-id' => $run->id]);

        self::assertSame(Command::SUCCESS, $exit);

        /** @var mixed $decoded */
        $decoded = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        self::assertSame('status-run', $decoded['runId']);
        self::assertSame('interrupted', $decoded['status']);
        self::assertSame(3.0, $decoded['telemetry']['jobs_completed']);
    }

    public function testResumeCompletesInterruptedRunWithNoPendingJobs(): void
    {
        $runtime = RuntimeFactory::create($this->projectRoot);
        $run = new ResearchRun(
            id: 'resume-run',
            startedAt: '2026-10-02T00:00:00Z',
            gitCommit: 'abc123',
            datasetHash: str_repeat('b', 64),
            protocolVersion: '1.0.0',
            versions: ['schema' => '1.0.0'],
            configuration: [],
        );
        $runtime->runs->create($run);
        $runtime->runs->setStatus($run->id, RunStatus::Interrupted);

        $tester = new CommandTester(new ResumeCommand($this->projectRoot));
        $exit = $tester->execute(['run-id' => $run->id]);

        self::assertSame(Command::SUCCESS, $exit);

        $after = RuntimeFactory::create($this->projectRoot);
        self::assertSame(RunStatus::Completed, $after->runs->status($run->id));
        self::assertSame([], $after->jobs->counts($run->id));
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
