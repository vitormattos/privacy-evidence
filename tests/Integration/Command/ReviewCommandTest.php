<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Integration\Command;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Command\ReviewExportCommand;
use PrivacyEvidence\Command\ReviewImportCommand;
use PrivacyEvidence\Run\ResearchRun;
use PrivacyEvidence\Runtime\RuntimeFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ReviewCommandTest extends TestCase
{
    private string $projectRoot = '';

    protected function setUp(): void
    {
        $this->projectRoot = sys_get_temp_dir() . '/privacy-evidence-review-cli-' . bin2hex(random_bytes(6));
        mkdir($this->projectRoot . '/data/derived', 0700, true);

        $runtime = RuntimeFactory::create($this->projectRoot);
        $runtime->runs->create(new ResearchRun(
            id: 'review-run',
            startedAt: '2026-10-02T00:00:00Z',
            gitCommit: 'abc123',
            datasetHash: str_repeat('a', 64),
            protocolVersion: '1.0.0',
            versions: ['schema' => '1.0.0'],
            configuration: [],
        ));
        $runtime->reviews->enqueue(
            'review-run',
            'evidence-1',
            json_encode([
                'id' => 'evidence-1',
                'type' => 'privacy_notice',
                'state' => 'unknown',
                'sourceUrl' => 'https://example.test/privacy',
            ], JSON_THROW_ON_ERROR),
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->projectRoot);
    }

    public function testExportAndImportHumanReviewDecision(): void
    {
        $packet = $this->projectRoot . '/review/reviewer-a.jsonl';

        $export = new CommandTester(new ReviewExportCommand($this->projectRoot));
        self::assertSame(
            Command::SUCCESS,
            $export->execute(['run-id' => 'review-run', 'output' => $packet]),
        );
        self::assertSame('1', trim($export->getDisplay()));

        $line = file_get_contents($packet);
        self::assertIsString($line);

        /** @var mixed $record */
        $record = json_decode(trim($line), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($record);

        /** @var array<string,mixed> $decision */
        $decision = $record['decision'];
        $decision['state'] = 'present';
        $decision['reviewerType'] = 'human';
        $decision['reviewerId'] = 'human:reviewer-a';
        $decision['reviewedAt'] = '2026-10-02T12:00:00Z';
        $decision['rationale'] = 'The page is explicitly dedicated to privacy processing.';
        $record['decision'] = $decision;

        file_put_contents(
            $packet,
            json_encode($record, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL,
        );

        $import = new CommandTester(new ReviewImportCommand($this->projectRoot));
        self::assertSame(Command::SUCCESS, $import->execute(['input' => $packet]));
        self::assertSame('1', trim($import->getDisplay()));

        $decisions = RuntimeFactory::create($this->projectRoot)->reviews->decisions('review-run');
        self::assertCount(1, $decisions);
        self::assertSame('human', $decisions[0]['reviewerType']);
        self::assertSame('human:reviewer-a', $decisions[0]['reviewerId']);
        self::assertSame('present', $decisions[0]['state']);
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
