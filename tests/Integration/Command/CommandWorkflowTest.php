<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Integration\Command;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Command\ResumeCommand;
use PrivacyEvidence\Command\RunCommand;
use PrivacyEvidence\Command\SourceImportCommand;
use PrivacyEvidence\Command\StatusCommand;
use PrivacyEvidence\Queue\Job;
use PrivacyEvidence\Run\ResearchRun;
use PrivacyEvidence\Run\RunStatus;
use PrivacyEvidence\Runtime\RuntimeFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class CommandWorkflowTest extends TestCase
{
    private string $projectRoot = '';

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
        $fixture = dirname(__DIR__, 2) . '/Fixtures/sources/sites.csv';
        $tester = new CommandTester(new SourceImportCommand());

        $exit = $tester->execute(['dataset' => $fixture]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('"sourceId"', $tester->getDisplay());
        self::assertStringContainsString('"snapshot"', $tester->getDisplay());
    }

    public function testRunRejectsNegativeMaxJobs(): void
    {
        $fixture = dirname(__DIR__, 2) . '/Fixtures/sources/sites.csv';
        $tester = new CommandTester(new RunCommand($this->projectRoot));

        $exit = $tester->execute([
            'dataset' => $fixture,
            '--max-jobs' => '-1',
        ]);

        self::assertSame(Command::INVALID, $exit);
        self::assertStringContainsString('max-jobs', $tester->getDisplay());
    }

    public function testRunCanEnqueueOnlyForConcurrentWorkerExecution(): void
    {
        $fixture = dirname(__DIR__, 2) . '/Fixtures/sources/sites.csv';
        $tester = new CommandTester(new RunCommand($this->projectRoot));

        $exit = $tester->execute([
            'dataset' => $fixture,
            '--enqueue-only' => true,
        ]);

        self::assertSame(Command::SUCCESS, $exit);
        $runId = trim($tester->getDisplay());
        self::assertNotSame('', $runId);

        $runtime = RuntimeFactory::create($this->projectRoot);
        self::assertSame(RunStatus::Running, $runtime->runs->status($runId));

        $counts = $runtime->jobs->counts($runId);
        self::assertGreaterThan(0, $counts['pending'] ?? 0);
        self::assertSame(0, $runtime->observations->counts($runId)['documents'] ?? 0);
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
        $runtime->jobs->enqueue(new Job(
            id: 'failed-job',
            runId: $run->id,
            stage: 'fetch',
            deduplicationKey: 'resource-1|https://failure.example/',
            payload: [
                'resource_id' => 'resource-1',
                'url' => 'https://failure.example/',
            ],
        ));
        $runtime->jobs->reserve($run->id, 'fetch', minHostDelayMs: 0);
        $runtime->jobs->fail('failed-job', 'dns failure', maxAttempts: 1);

        $tester = new CommandTester(new StatusCommand($this->projectRoot));
        $exit = $tester->execute(['run-id' => $run->id]);

        self::assertSame(Command::SUCCESS, $exit);

        /** @var mixed $decoded */
        $decoded = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        self::assertSame('status-run', $decoded['runId'] ?? null);
        self::assertSame('interrupted', $decoded['status'] ?? null);

        $telemetry = $decoded['telemetry'] ?? null;
        self::assertIsArray($telemetry);
        self::assertSame(3, $telemetry['jobs_completed'] ?? null);
        self::assertSame(0, $decoded['events'] ?? null);

        $failures = $decoded['failures'] ?? null;
        self::assertIsArray($failures);
        self::assertCount(1, $failures);
        $firstFailure = $failures[0] ?? null;
        self::assertIsArray($firstFailure);
        self::assertSame('https://failure.example/', $firstFailure['url'] ?? null);
        self::assertSame('dns failure', $firstFailure['error'] ?? null);
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
