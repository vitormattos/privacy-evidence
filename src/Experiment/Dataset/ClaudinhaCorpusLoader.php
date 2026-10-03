<?php

declare(strict_types=1);

namespace PrivacyEvidence\Experiment\Dataset;

final readonly class ClaudinhaCorpusLoader
{
    public const DATASET_ID = 'claudinha-lgpd-corpus';
    public const DATASET_VERSION = 'v1';
    public const DOI = '10.5281/zenodo.13371639';
    public const LICENSE = 'CC-BY-4.0';
    public const SOURCE_FILE = 'corpus_data_privacy.csv';
    public const SOURCE_MD5 = 'cbc6bc61e1fbe9396b98157355935e41';
    public const DOWNLOAD_URL = 'https://zenodo.org/records/13371639/files/corpus_data_privacy.csv?download=1';

    public function __construct(
        private string $expectedMd5 = self::SOURCE_MD5,
    ) {
    }

    public function prepare(string $sourcePath, string $outputDirectory): array
    {
        if (!is_file($sourcePath)) {
            throw new \InvalidArgumentException('Claudinha source CSV does not exist.');
        }

        $actualMd5 = md5_file($sourcePath);
        if (!is_string($actualMd5) || !hash_equals($this->expectedMd5, $actualMd5)) {
            throw new \RuntimeException('Claudinha source checksum mismatch.');
        }

        if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0700, true) && !is_dir($outputDirectory)) {
            throw new \RuntimeException('Unable to create Claudinha output directory.');
        }

        $input = fopen($sourcePath, 'rb');
        if ($input === false) {
            throw new \RuntimeException('Unable to open Claudinha source CSV.');
        }

        $outputPath = $outputDirectory . '/claudinha-v1.jsonl';
        $output = fopen($outputPath, 'wb');
        if ($output === false) {
            fclose($input);
            throw new \RuntimeException('Unable to create canonical Claudinha dataset.');
        }

        $rows = 0;
        $paragraphs = [];

        try {
            $header = fgetcsv($input, escape: '');
            if (!is_array($header)) {
                throw new \RuntimeException('Claudinha source CSV has no header.');
            }

            $columns = array_flip($header);
            foreach (['id', 'uid', 'id_clause', 'id_company', 'clause', 'compliance_category'] as $required) {
                if (!array_key_exists($required, $columns)) {
                    throw new \RuntimeException('Claudinha source CSV is missing required columns.');
                }
            }

            while (($record = fgetcsv($input, escape: '')) !== false) {
                if ($record === [null] || $record === []) {
                    continue;
                }

                $id = $this->field($record, $columns, 'id');
                $uid = $this->field($record, $columns, 'uid');
                $clauseId = $this->field($record, $columns, 'id_clause');
                $company = $this->field($record, $columns, 'id_company');
                $text = $this->field($record, $columns, 'clause');
                $label = $this->field($record, $columns, 'compliance_category');

                $canonical = [
                    'sourceRecordId' => $id,
                    'paragraphId' => $uid,
                    'clauseId' => $clauseId,
                    'companyId' => $company,
                    'text' => $text,
                    'externalLabel' => $label,
                    'language' => 'pt-BR',
                ];

                $json = json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                if (fwrite($output, $json . PHP_EOL) === false) {
                    throw new \RuntimeException('Unable to write canonical Claudinha dataset.');
                }

                $paragraphs[$uid] = true;
                $rows++;
            }
        } finally {
            fclose($input);
            fclose($output);
        }

        $sourceSha256 = hash_file('sha256', $sourcePath);
        $canonicalSha256 = hash_file('sha256', $outputPath);
        if (!is_string($sourceSha256) || !is_string($canonicalSha256)) {
            throw new \RuntimeException('Unable to hash Claudinha dataset artifacts.');
        }

        $manifest = [
            'datasetId' => self::DATASET_ID,
            'datasetVersion' => self::DATASET_VERSION,
            'doi' => self::DOI,
            'license' => self::LICENSE,
            'downloadUrl' => self::DOWNLOAD_URL,
            'sourceFile' => self::SOURCE_FILE,
            'sourceChecksums' => [
                'md5' => $actualMd5,
                'sha256' => $sourceSha256,
            ],
            'canonicalFile' => basename($outputPath),
            'canonicalSha256' => $canonicalSha256,
            'language' => 'pt-BR',
            'records' => $rows,
            'distinctParagraphs' => count($paragraphs),
            'excludedColumns' => [
                'compliance_degree',
                'non_compliant_clause',
            ],
            'purpose' => 'external-development-data',
        ];

        $manifestPath = $outputDirectory . '/claudinha-v1.manifest.json';
        $encoded = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
        if (file_put_contents($manifestPath, $encoded, LOCK_EX) === false) {
            throw new \RuntimeException('Unable to write Claudinha dataset manifest.');
        }

        return $manifest;
    }

    private function field(array $record, array $columns, string $name): string
    {
        $offset = $columns[$name] ?? null;
        if (!is_int($offset)) {
            throw new \LogicException('Missing required Claudinha column.');
        }

        $value = $record[$offset] ?? null;
        if (!is_string($value)) {
            throw new \RuntimeException('Malformed Claudinha CSV record.');
        }

        return $value;
    }
}
