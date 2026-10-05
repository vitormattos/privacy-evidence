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
                'excerpt' => '</script><strong>Privacy notice</strong> __REVIEW_CONFIG_JSON__',
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
        self::assertStringContainsString('Privacy notice\\u003C/strong\\u003E __REVIEW_CONFIG_JSON__', $html);
    }

    public function testTestModeIsExplicitAndDoesNotMarkTheInputPackage(): void
    {
        $packagePath = $this->projectRoot . '/test.json';
        $outputPath = $this->projectRoot . '/test.html';
        $original = '{"runId":"test-run","cases":[]}';
        file_put_contents($packagePath, $original);
        $tester = new CommandTester(new ReviewHtmlCommand($this->projectRoot));

        self::assertSame(Command::SUCCESS, $tester->execute([
            'package' => $packagePath,
            'output' => $outputPath,
            '--test-mode' => true,
        ]));
        self::assertStringContainsString('"testMode":true', (string) file_get_contents($outputPath));
        self::assertStringNotContainsString('__REVIEW_CONFIG_JSON__', (string) file_get_contents($outputPath));
        self::assertSame($original, file_get_contents($packagePath));
    }

    public function testEnrichesExistingSelectionFromArchivedExportWithoutEditingTheInput(): void
    {
        $body = '<html><title>Archived policy</title><p>The controller is Synthetic Example Ltd.</p></html>';
        $hash = hash('sha256', $body);
        mkdir($this->projectRoot . '/archive', 0700);
        mkdir($this->projectRoot . '/artifacts', 0700);
        file_put_contents($this->projectRoot . '/artifacts/' . $hash . '.bin', $body);
        file_put_contents($this->projectRoot . '/archive/resources.json', json_encode([[
            'id' => 'sample-1', 'name' => 'Sampled website', 'normalizedUrl' => 'https://sample.test/',
        ]], JSON_THROW_ON_ERROR));
        file_put_contents($this->projectRoot . '/archive/documents.json', json_encode([[
            'resourceId' => 'sample-1', 'artifactHash' => $hash, 'finalUrl' => 'https://external.test/policy',
            'mediaType' => 'text/html', 'fetchedAt' => '2026-10-05T00:00:00Z', 'truncated' => false,
        ]], JSON_THROW_ON_ERROR));
        $original = json_encode(['runId' => 'fixture', 'cases' => [[
            'evidenceId' => 'selected-evidence', 'resourceId' => 'sample-1', 'artifactHash' => $hash,
            'sourceUrl' => 'https://external.test/policy', 'evidenceType' => 'controller_identity', 'excerpt' => null,
        ]]], JSON_THROW_ON_ERROR);
        file_put_contents($this->projectRoot . '/selection.json', $original);
        $tester = new CommandTester(new ReviewHtmlCommand($this->projectRoot));
        self::assertSame(Command::SUCCESS, $tester->execute([
            'package' => $this->projectRoot . '/selection.json', 'output' => $this->projectRoot . '/review.html',
            '--context-dir' => $this->projectRoot . '/archive', '--artifacts-dir' => $this->projectRoot . '/artifacts',
        ]));
        $html = (string) file_get_contents($this->projectRoot . '/review.html');
        self::assertStringContainsString('Synthetic Example Ltd.', $html);
        self::assertStringContainsString('Sampled website', $html);
        self::assertStringContainsString('2026-10-05T00:00:00Z', $html);
        self::assertStringContainsString('"reason":null', $html);
        self::assertSame($original, file_get_contents($this->projectRoot . '/selection.json'));
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
