<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Integration\Experiment;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Experiment\Benchmark\MlBenchmarkRunner;
use PrivacyEvidence\Experiment\Dataset\ClaudinhaLabelMapping;

final class MlBenchmarkRunnerTest extends TestCase
{
    private string $root = '';

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/privacy-evidence-benchmark-test-' . bin2hex(random_bytes(6));
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

    public function testProducesTraceableDeterministicReportShapeForAllSupportedSignals(): void
    {
        [$train, $eval, $manifest] = $this->fixtures();

        $report = (new MlBenchmarkRunner())->run(
            $train,
            $eval,
            $manifest,
            '2026-10-03T12:00:00Z',
        );

        self::assertSame('external-development-only', $report['scope'] ?? null);
        self::assertFalse($report['adoptionGateSatisfied'] ?? true);
        self::assertFalse($report['automaticPromotion'] ?? true);

        $signals = $report['signals'] ?? null;
        self::assertIsArray($signals);
        self::assertArrayHasKey('controller_identity', $signals);
        self::assertArrayHasKey('rights_disclosure', $signals);
        self::assertArrayHasKey('cookie_notice', $signals);

        foreach ($signals as $signal) {
            self::assertIsArray($signal);
            foreach (['rule', 'internalNaiveBayes', 'rubix'] as $candidate) {
                $metrics = $signal[$candidate] ?? null;
                self::assertIsArray($metrics);
                self::assertArrayHasKey('confusionMatrix', $metrics);
                self::assertArrayHasKey('precision', $metrics);
                self::assertArrayHasKey('recall', $metrics);
                self::assertArrayHasKey('f1', $metrics);
                self::assertArrayHasKey('support', $metrics);
                self::assertSame(1.0, $metrics['coverage'] ?? null);
                self::assertSame(0, $metrics['abstained'] ?? null);
                $modelSha256 = $metrics['modelSha256'] ?? null;
                self::assertIsString($modelSha256);
                self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $modelSha256);
            }
        }
    }

    /**
     * @return array{0:string,1:string,2:string}
     */
    private function fixtures(): array
    {
        $train = $this->root . '/train.jsonl';
        $eval = $this->root . '/development.jsonl';
        $manifest = $this->root . '/manifest.json';

        $labels = [
            'Controller identification',
            'Purpose of treatment',
            'Third party sharing',
            'Duration of treatment',
            'ID and contact DPO',
            'Access to data',
            'Cookies',
            'Generic expressions',
        ];

        $trainLines = [];
        $evalLines = [];
        $i = 0;
        foreach ($labels as $label) {
            for ($copy = 0; $copy < 2; $copy++) {
                ++$i;
                $trainLines[] = $this->row('t' . $i, $this->text($label, $copy), [$label]);
            }

            ++$i;
            $evalLines[] = $this->row('e' . $i, $this->text($label, 9), [$label]);
        }

        file_put_contents($train, implode(PHP_EOL, $trainLines) . PHP_EOL);
        file_put_contents($eval, implode(PHP_EOL, $evalLines) . PHP_EOL);
        file_put_contents($manifest, json_encode([
            'datasetId' => 'fixture',
            'datasetVersion' => 'fixture-v1',
            'canonicalSha256' => str_repeat('a', 64),
            'purpose' => 'external-development-data',
            'split' => [
                'splitSha256' => str_repeat('b', 64),
                'mappingVersion' => ClaudinhaLabelMapping::VERSION,
            ],
        ], JSON_THROW_ON_ERROR));

        return [$train, $eval, $manifest];
    }

    /**
     * @param list<string> $labels
     */
    private function row(string $id, string $text, array $labels): string
    {
        return json_encode([
            'paragraphId' => $id,
            'companyId' => 'company-' . $id,
            'text' => $text,
            'externalLabels' => $labels,
            'language' => 'pt-BR',
            'sourceRecordIds' => [$id],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private function text(string $label, int $copy): string
    {
        return match ($label) {
            'Controller identification' => 'controlador de dados nome empresa exemplo ' . $copy,
            'Purpose of treatment' => 'finalidade tratamento dados para serviço ' . $copy,
            'Third party sharing' => 'compartilhamos dados com terceiros destinatários ' . $copy,
            'Duration of treatment' => 'retenção dados armazenaremos período necessário ' . $copy,
            'ID and contact DPO' => 'encarregado tratamento dados nome dpo privacidade@example.test ' . $copy,
            'Access to data' => 'direitos do titular direito de acesso aos dados ' . $copy,
            'Cookies' => 'cookies preferências aceitar rejeitar ' . $copy,
            default => 'conteúdo institucional comunidade notícias eventos ' . $copy,
        };
    }
}
