<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Integration\Command;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Command\ReviewHtmlCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ReviewHtmlCommandTest extends TestCase
{
    private string $projectRoot = '';

    protected function setUp(): void
    {
        $this->projectRoot = sys_get_temp_dir() . '/privacy-evidence-review-html-' . bin2hex(random_bytes(6));
        mkdir($this->projectRoot . '/resources/review', 0700, true);
        copy(
            dirname(__DIR__, 3) . '/resources/review/reviewer.html',
            $this->projectRoot . '/resources/review/reviewer.html',
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->projectRoot);
    }

    public function testGeneratesOfflineReviewerWithoutExposingAutomatedStateInNormalView(): void
    {
        $packagePath = $this->projectRoot . '/reviewer.json';
        $outputPath = $this->projectRoot . '/reviewer.html';
        file_put_contents($packagePath, json_encode([
            'packageVersion' => '1.0.0',
            'annotationHandbookVersion' => '1.0.0',
            'runId' => 'run-1',
            'seed' => 'seed-1',
            'perStratum' => 2,
            'cases' => [[
                'evidenceId' => 'evidence-1',
                'resourceId' => 'resource-1',
                'evidenceType' => 'privacy_notice',
                'automatedState' => 'present',
                'sourceUrl' => 'https://example.test/privacy',
                'artifactHash' => str_repeat('a', 64),
                'excerpt' => '</script><strong>Privacy notice</strong>',
                'detector' => 'fixture',
                'detectorVersion' => '1.0.0',
                'confidence' => 0.9,
                'needsReview' => false,
                'humanState' => null,
                'rationale' => null,
                'reviewedAt' => null,
            ]],
        ], JSON_THROW_ON_ERROR));

        $tester = new CommandTester(new ReviewHtmlCommand($this->projectRoot));
        self::assertSame(Command::SUCCESS, $tester->execute([
            'package' => $packagePath,
            'output' => $outputPath,
        ]));

        $html = (string) file_get_contents($outputPath);
        self::assertStringContainsString('Independent human review', $html);
        self::assertStringContainsString('automated detector state', strtolower($html));
        self::assertStringContainsString('\\u003C/script\\u003E', $html);
        self::assertStringNotContainsString('</script><strong>Privacy notice</strong>', $html);
        self::assertStringContainsString('Export completed JSON', $html);
        self::assertStringContainsString('Exportar JSON concluído', $html);
        self::assertStringContainsString("'not_applicable'", $html);
        self::assertStringNotContainsString("'not-applicable'", $html);
        self::assertStringContainsString('minimumForEstimate = 5', $html);
        self::assertStringContainsString('function median(values)', $html);
        self::assertStringContainsString('controller_identity:', $html);
        self::assertStringContainsString('missingEvidence', $html);
        self::assertStringContainsString('localStorage', $html);
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
