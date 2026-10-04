<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Integration\Command;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Command\SourceStatsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class SourceStatsCommandTest extends TestCase
{
    public function testSummarizesClassificationAndDuplicateUrlsDeterministically(): void
    {
        $directory = sys_get_temp_dir() . '/privacy-evidence-source-stats-' . bin2hex(random_bytes(6));
        mkdir($directory, 0700, true);
        $dataset = $directory . '/sites.csv';

        try {
            file_put_contents($dataset, implode(PHP_EOL, [
                'id,name,url',
                'a,Alpha,https://example.org',
                'b,Beta,https://example.org',
                'c,Social,https://instagram.com/example',
                'd,Malformed,mailto:test@example.org',
            ]) . PHP_EOL);

            $tester = new CommandTester(new SourceStatsCommand());
            self::assertSame(Command::SUCCESS, $tester->execute(['dataset' => $dataset]));

            /** @var mixed $decoded */
            $decoded = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
            self::assertIsArray($decoded);

            $resources = $decoded['resources'] ?? null;
            self::assertIsArray($resources);
            self::assertSame(4, $resources['total'] ?? null);
            self::assertSame(3, $resources['withNormalizedUrl'] ?? null);
            self::assertSame(1, $resources['withoutNormalizedUrl'] ?? null);

            $classification = $decoded['classification'] ?? null;
            self::assertIsArray($classification);
            $byType = $classification['byType'] ?? null;
            self::assertIsArray($byType);
            self::assertSame(2, $byType['institutional_website'] ?? null);
            self::assertSame(1, $byType['social_network'] ?? null);
            self::assertSame(1, $byType['malformed'] ?? null);

            $urlStats = $decoded['normalizedUrls'] ?? null;
            self::assertIsArray($urlStats);
            self::assertSame(2, $urlStats['unique'] ?? null);
            self::assertSame(1, $urlStats['duplicateGroups'] ?? null);
            self::assertSame(2, $urlStats['resourcesInDuplicateGroups'] ?? null);
            self::assertSame(1, $urlStats['duplicateExcessResources'] ?? null);

            $duplicates = $decoded['duplicates'] ?? null;
            self::assertIsArray($duplicates);
            self::assertCount(1, $duplicates);
            $first = $duplicates[0] ?? null;
            self::assertIsArray($first);
            self::assertSame('https://example.org/', $first['url'] ?? null);
            self::assertSame(['a', 'b'], $first['resourceIds'] ?? null);
        } finally {
            if (is_file($dataset)) {
                unlink($dataset);
            }
            if (is_dir($directory)) {
                rmdir($directory);
            }
        }
    }
}
