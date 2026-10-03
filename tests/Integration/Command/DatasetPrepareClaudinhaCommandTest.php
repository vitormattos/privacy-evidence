<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Integration\Command;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Command\DatasetPrepareClaudinhaCommand;
use PrivacyEvidence\Experiment\Dataset\ClaudinhaCorpusLoader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class DatasetPrepareClaudinhaCommandTest extends TestCase
{
    private string $root = '';

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/privacy-evidence-claudinha-cli-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0700, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
    }

    public function testPreparesDatasetAndEmitsManifestJson(): void
    {
        $source = $this->root . '/source.csv';
        $output = $this->root . '/prepared';
        $csv = "id,created_at,updated_at,uid,id_clause,id_company,data_gathering,clause,annotator,legal_area,compliance_category,compliance_degree,non_compliant_clause\n"
            . "1,2022-01-01,2022-01-01,p1,10,example,2022-01-01,Texto sobre cookies,1,privacy,cookie,3.0,TRUE\n";
        file_put_contents($source, $csv);

        $tester = new CommandTester(new DatasetPrepareClaudinhaCommand(
            new ClaudinhaCorpusLoader(md5($csv)),
        ));

        self::assertSame(Command::SUCCESS, $tester->execute([
            'source' => $source,
            'output-directory' => $output,
        ]));

        /** @var mixed $manifest */
        $manifest = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($manifest);
        self::assertSame('claudinha-lgpd-corpus', $manifest['datasetId'] ?? null);
        self::assertArrayHasKey('sha256', $manifest['sourceChecksums'] ?? []);
        self::assertArrayHasKey('canonicalSha256', $manifest);
        self::assertFileExists($output . '/claudinha-v1.jsonl');
        self::assertFileExists($output . '/claudinha-v1.manifest.json');
    }

    public function testChecksumFailureReturnsNonZeroWithoutManifestOutput(): void
    {
        $source = $this->root . '/source.csv';
        $output = $this->root . '/prepared';
        file_put_contents($source, "changed\n");

        $tester = new CommandTester(new DatasetPrepareClaudinhaCommand(
            new ClaudinhaCorpusLoader(str_repeat('0', 32)),
        ));

        self::assertSame(Command::FAILURE, $tester->execute([
            'source' => $source,
            'output-directory' => $output,
        ]));

        self::assertStringContainsString('checksum mismatch', $tester->getDisplay());
        self::assertFileDoesNotExist($output . '/claudinha-v1.manifest.json');
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
