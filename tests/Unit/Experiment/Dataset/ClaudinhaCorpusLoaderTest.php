<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Unit\Experiment\Dataset;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Experiment\Dataset\ClaudinhaCorpusLoader;

final class ClaudinhaCorpusLoaderTest extends TestCase
{
    public function testPreparesCanonicalDatasetWithoutComplianceVerdicts(): void
    {
        $root = sys_get_temp_dir() . '/privacy-evidence-claudinha-' . bin2hex(random_bytes(4));
        mkdir($root, 0700, true);
        $source = $root . '/source.csv';
        $output = $root . '/out';

        $csv = "id,created_at,updated_at,uid,id_clause,id_company,data_gathering,clause,annotator,legal_area,compliance_category,compliance_degree,non_compliant_clause\n"
            . "1,2022-01-01,2022-01-01,p1,10,example,2022-01-01,Texto sobre cookies,1,privacy,cookie,3.0,TRUE\n"
            . "2,2022-01-01,2022-01-01,p1,10,example,2022-01-01,Texto sobre cookies,1,privacy,consent,1.0,FALSE\n";
        file_put_contents($source, $csv);

        try {
            $manifest = (new ClaudinhaCorpusLoader(md5($csv)))->prepare($source, $output);

            self::assertSame(2, $manifest['records']);
            self::assertSame(1, $manifest['distinctParagraphs']);
            self::assertSame('CC-BY-4.0', $manifest['license']);
            self::assertArrayHasKey('sha256', $manifest['sourceChecksums']);

            $lines = file($output . '/claudinha-v1.jsonl', FILE_IGNORE_NEW_LINES);
            self::assertIsArray($lines);
            self::assertCount(2, $lines);

            $first = $this->decodeRow($lines[0]);
            self::assertSame('cookie', $first['externalLabel']);
            self::assertArrayNotHasKey('compliance_degree', $first);
            self::assertArrayNotHasKey('non_compliant_clause', $first);
        } finally {
            $this->removeDirectory($root);
        }
    }

    public function testRejectsSourceChecksumMismatch(): void
    {
        $root = sys_get_temp_dir() . '/privacy-evidence-claudinha-' . bin2hex(random_bytes(4));
        mkdir($root, 0700, true);
        $source = $root . '/source.csv';
        file_put_contents($source, "changed\n");

        try {
            $this->expectException(\RuntimeException::class);
            (new ClaudinhaCorpusLoader(str_repeat('0', 32)))->prepare($source, $root . '/out');
        } finally {
            $this->removeDirectory($root);
        }
    }

    /** @return array<string,mixed> */
    private function decodeRow(string $line): array
    {
        $value = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($value)) {
            throw new \RuntimeException('Canonical fixture row must be an array.');
        }

        $row = [];
        foreach ($value as $key => $item) {
            if (!is_string($key)) {
                throw new \RuntimeException('Canonical fixture keys must be strings.');
            }
            $row[$key] = $item;
        }

        return $row;
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
