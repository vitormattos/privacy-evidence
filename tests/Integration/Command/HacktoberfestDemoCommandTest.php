<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Integration\Command;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Command\HacktoberfestDemoBuildCommand;
use PrivacyEvidence\Command\HacktoberfestDemoCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class HacktoberfestDemoCommandTest extends TestCase
{
    private string $root = '';

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/privacy-evidence-hacktoberfest-demo-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0700, true);
    }

    protected function tearDown(): void
    {
        $paths = glob($this->root . '/*');
        if ($paths !== false) {
            foreach ($paths as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
        if (is_dir($this->root)) {
            rmdir($this->root);
        }
    }

    public function testBuildAndDemoExerciseRealModelAndAuditableDisagreement(): void
    {
        $artifact = $this->root . '/demo.rbx';

        $build = new CommandTester(new HacktoberfestDemoBuildCommand());
        self::assertSame(Command::SUCCESS, $build->execute(['artifact' => $artifact]));
        self::assertFileExists($artifact);
        self::assertFileExists($artifact . '.metadata.json');

        $demo = new CommandTester(new HacktoberfestDemoCommand());
        self::assertSame(Command::SUCCESS, $demo->execute(['artifact' => $artifact]));

        /** @var mixed $decoded */
        $decoded = json_decode($demo->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        self::assertTrue($decoded['aiAtCore'] ?? false);
        self::assertTrue($decoded['modelRequired'] ?? false);
        self::assertFalse($decoded['legalConclusion'] ?? true);

        $rule = $decoded['deterministicRule'] ?? null;
        self::assertIsArray($rule);
        self::assertSame('absent', $rule['state'] ?? null);

        $ml = $decoded['mlPrediction'] ?? null;
        self::assertIsArray($ml);
        self::assertSame('controller_identity', $ml['signal'] ?? null);
        self::assertTrue($ml['candidatePresent'] ?? false);

        $review = $decoded['review'] ?? null;
        self::assertIsArray($review);
        self::assertTrue($review['disagreesWithRule'] ?? false);
        self::assertTrue($review['needsReview'] ?? false);
        self::assertSame(1, $review['pendingCases'] ?? null);

        $suggestions = $review['suggestions'] ?? null;
        self::assertIsArray($suggestions);
        self::assertCount(1, $suggestions);
        self::assertSame('ai_suggestion', $suggestions[0]['reviewerType'] ?? null);
    }

    public function testDemoFailsWhenModelArtifactIsAbsent(): void
    {
        $tester = new CommandTester(new HacktoberfestDemoCommand());

        self::assertSame(Command::FAILURE, $tester->execute([
            'artifact' => $this->root . '/missing.rbx',
        ]));
        self::assertStringContainsString('missing or unreadable', $tester->getDisplay());
    }
}
