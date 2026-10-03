<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Unit\Experiment\Dataset;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Experiment\Dataset\ExternalDatasetSplitter;

final class ExternalDatasetSplitterTest extends TestCase
{
    private string $root = '';

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/privacy-evidence-split-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0700, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
    }

    public function testReconstructsMultilabelRowsAndProducesDeterministicGroupedSplit(): void
    {
        $rows = [
            $this->row('1', 'p1', 'c1', 'company-a', 'Texto A', 'Controller identification'),
            $this->row('2', 'p1', 'c1', 'company-a', 'Texto A', 'Purpose of treatment'),
            $this->row('3', 'p2', 'c2', 'company-a', 'Texto B', 'Cookies'),
            $this->row('4', 'p3', 'c3', 'company-b', 'Texto C', 'Third party sharing'),
            $this->row('5', 'p4', 'c4', 'company-c', 'Texto D', 'Duration of treatment'),
            $this->row('6', 'p5', 'c5', 'company-d', 'Texto E', 'Access to data'),
            $this->row('7', 'p6', 'c6', 'company-e', 'Texto F', 'Advertising'),
            $this->row('8', 'p7', 'c7', 'company-f', 'Texto G', 'Children data'),
        ];

        [$canonical, $manifest] = $this->writeDataset($rows);
        $splitter = new ExternalDatasetSplitter();

        $first = $splitter->split($canonical, $manifest, $this->root . '/first', 'seed-42');
        $firstSplit = $this->splitMetadata($first);
        $firstHash = $firstSplit['splitSha256'];

        [$canonical2, $manifest2] = $this->writeDataset($rows, 'second-source');
        $second = $splitter->split($canonical2, $manifest2, $this->root . '/second', 'seed-42');
        $secondSplit = $this->splitMetadata($second);

        self::assertSame($firstHash, $secondSplit['splitSha256']);

        $all = $this->readPartitions($this->root . '/first');
        self::assertCount(7, $all);

        $p1 = array_values(array_filter($all, static fn (array $sample): bool => $sample['paragraphId'] === 'p1'));
        self::assertCount(1, $p1);
        self::assertSame(
            ['Controller identification', 'Purpose of treatment'],
            $p1[0]['externalLabels'],
        );

        /** @var array<string,list<string>> $companyPartitions */
        $companyPartitions = [];
        foreach ($all as $sample) {
            $companyId = $sample['companyId'] ?? null;
            $partition = $sample['_partition'] ?? null;
            self::assertIsString($companyId);
            self::assertIsString($partition);
            $companyPartitions[$companyId][] = $partition;
        }

        foreach ($companyPartitions as $partitions) {
            self::assertCount(1, array_unique($partitions));
        }

        self::assertSame('1.0.0', $firstSplit['mappingVersion']);
        self::assertArrayHasKey('partitions', $firstSplit);
    }

    public function testDetectsDuplicateNormalizedTextAcrossSourceGroups(): void
    {
        $seed = 'leakage-seed';
        [$companyA, $companyB] = $this->findGroupsInDifferentPartitions($seed);

        $rows = [
            $this->row('1', 'p1', 'c1', $companyA, 'Mesmo   TEXTO', 'Cookies'),
            $this->row('2', 'p2', 'c2', $companyB, 'mesmo texto', 'Purpose of treatment'),
        ];

        [$canonical, $manifest] = $this->writeDataset($rows);

        try {
            (new ExternalDatasetSplitter())->split(
                $canonical,
                $manifest,
                $this->root . '/leak',
                $seed,
            );
            self::fail('Expected duplicate normalized text leakage to be rejected.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString(
                'Duplicate normalized text crosses partitions',
                $exception->getMessage(),
            );
        }
    }

    /**
     * @return array{
     *   sourceRecordId:string,
     *   paragraphId:string,
     *   clauseId:string,
     *   companyId:string,
     *   text:string,
     *   externalLabel:string,
     *   language:string
     * }
     */
    private function row(
        string $sourceRecordId,
        string $paragraphId,
        string $clauseId,
        string $companyId,
        string $text,
        string $label,
    ): array {
        return [
            'sourceRecordId' => $sourceRecordId,
            'paragraphId' => $paragraphId,
            'clauseId' => $clauseId,
            'companyId' => $companyId,
            'text' => $text,
            'externalLabel' => $label,
            'language' => 'pt-BR',
        ];
    }

    /**
     * @param list<array<string,string>> $rows
     * @return array{0:string,1:string}
     */
    private function writeDataset(array $rows, string $prefix = 'source'): array
    {
        $directory = $this->root . '/' . $prefix;
        mkdir($directory, 0700, true);
        $canonical = $directory . '/claudinha-v1.jsonl';
        $manifest = $directory . '/claudinha-v1.manifest.json';

        $lines = array_map(
            static fn (array $row): string => json_encode(
                $row,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            ),
            $rows,
        );
        file_put_contents($canonical, implode(PHP_EOL, $lines) . PHP_EOL);
        file_put_contents(
            $manifest,
            json_encode(
                ['datasetId' => 'fixture', 'datasetVersion' => 'v1'],
                JSON_THROW_ON_ERROR,
            ),
        );

        return [$canonical, $manifest];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function readPartitions(string $directory): array
    {
        $samples = [];
        foreach (['train', 'validation', 'development'] as $partition) {
            $path = $directory . '/' . $partition . '.jsonl';
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            self::assertIsArray($lines);

            foreach ($lines as $line) {
                $decoded = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                self::assertIsArray($decoded);
                $sample = [];
                /** @psalm-suppress MixedAssignment */
                foreach ($decoded as $key => $value) {
                    if (is_string($key)) {
                        $sample[$key] = $value;
                    }
                }
                $sample['_partition'] = $partition;
                $samples[] = $sample;
            }
        }

        return $samples;
    }

    /**
     * @param array<string,mixed> $manifest
     * @return array{
     *   splitSha256:string,
     *   mappingVersion:string,
     *   partitions:array<mixed>
     * }
     */
    private function splitMetadata(array $manifest): array
    {
        $split = $manifest['split'] ?? null;
        self::assertIsArray($split);

        $splitSha256 = $split['splitSha256'] ?? null;
        $mappingVersion = $split['mappingVersion'] ?? null;
        $partitions = $split['partitions'] ?? null;
        self::assertIsString($splitSha256);
        self::assertIsString($mappingVersion);
        self::assertIsArray($partitions);

        return [
            'splitSha256' => $splitSha256,
            'mappingVersion' => $mappingVersion,
            'partitions' => $partitions,
        ];
    }

    /**
     * @return array{0:string,1:string}
     */
    private function findGroupsInDifferentPartitions(string $seed): array
    {
        $firstByPartition = [];

        for ($i = 0; $i < 1000; $i++) {
            $group = 'company-' . $i;
            $digest = hash('sha256', $seed . "\0" . $group);
            $bucket = hexdec(substr($digest, 0, 8)) / 4294967296;
            $partition = $bucket < 0.70 ? 'train' : ($bucket < 0.85 ? 'validation' : 'development');

            if ($firstByPartition !== [] && !isset($firstByPartition[$partition])) {
                return [array_values($firstByPartition)[0], $group];
            }

            $firstByPartition[$partition] ??= $group;
        }

        self::fail('Unable to find deterministic fixture groups in different partitions.');
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
