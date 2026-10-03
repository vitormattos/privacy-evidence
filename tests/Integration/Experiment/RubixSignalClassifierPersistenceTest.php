<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Integration\Experiment;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Evidence\EvidenceType;
use PrivacyEvidence\Experiment\Dataset\ClaudinhaLabelMapping;
use PrivacyEvidence\Experiment\Rubix\RubixSignalClassifier;
use PrivacyEvidence\Experiment\Rubix\RubixSignalTrainingProvenance;

final class RubixSignalClassifierPersistenceTest extends TestCase
{
    public function testTrainSaveLoadPredictRoundTrip(): void
    {
        $root = sys_get_temp_dir() . '/privacy-evidence-signal-model-' . bin2hex(random_bytes(6));
        mkdir($root, 0700, true);
        $artifact = $root . '/controller-identity.rbx';

        try {
            $model = $this->model();
            $before = $model->predict('controller company identity');
            $hash = $model->save($artifact);

            self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $hash);
            self::assertFileExists($artifact);
            self::assertFileExists($artifact . '.metadata.json');

            $loaded = RubixSignalClassifier::load($artifact);
            $after = $loaded->predict('controller company identity');

            self::assertSame($hash, $after->artifactSha256);
            self::assertSame($before->signal, $after->signal);
            self::assertEqualsWithDelta($before->probability, $after->probability, 1e-12);
            self::assertSame($before->model->toArray(), $after->model->toArray());
        } finally {
            foreach ([$artifact, $artifact . '.metadata.json'] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
            if (is_dir($root)) {
                rmdir($root);
            }
        }
    }

    public function testRejectsArtifactWhoseBytesDoNotMatchSidecarHash(): void
    {
        $root = sys_get_temp_dir() . '/privacy-evidence-signal-model-' . bin2hex(random_bytes(6));
        mkdir($root, 0700, true);
        $artifact = $root . '/controller-identity.rbx';

        try {
            $this->model()->save($artifact);
            file_put_contents($artifact, 'corruption', FILE_APPEND);

            try {
                RubixSignalClassifier::load($artifact);
                self::fail('Expected corrupt model artifact to be rejected.');
            } catch (\RuntimeException $exception) {
                self::assertStringContainsString('checksum mismatch', $exception->getMessage());
            }
        } finally {
            foreach ([$artifact, $artifact . '.metadata.json'] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
            if (is_dir($root)) {
                rmdir($root);
            }
        }
    }

    private function model(): RubixSignalClassifier
    {
        return RubixSignalClassifier::train(
            EvidenceType::ControllerIdentity,
            [
                ['text' => 'controller company identity privacy policy', 'present' => true],
                ['text' => 'data controller legal name and address', 'present' => true],
                ['text' => 'responsável tratamento controlador empresa', 'present' => true],
                ['text' => 'identificação controlador dados pessoais', 'present' => true],
                ['text' => 'community news events products', 'present' => false],
                ['text' => 'welcome home services', 'present' => false],
                ['text' => 'notícias comunidade agenda eventos', 'present' => false],
                ['text' => 'produtos serviços novidades institucional', 'present' => false],
            ],
            new RubixSignalTrainingProvenance(
                'fixture',
                'fixture-v1',
                str_repeat('a', 64),
                str_repeat('b', 64),
                ClaudinhaLabelMapping::VERSION,
                '2026-10-03T12:00:00Z',
            ),
            64,
        );
    }
}
