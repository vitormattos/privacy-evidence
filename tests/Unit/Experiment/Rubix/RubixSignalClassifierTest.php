<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Unit\Experiment\Rubix;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Evidence\EvidenceType;
use PrivacyEvidence\Experiment\Dataset\ClaudinhaLabelMapping;
use PrivacyEvidence\Experiment\Rubix\RubixSignalClassifier;
use PrivacyEvidence\Experiment\Rubix\RubixSignalTrainingProvenance;

final class RubixSignalClassifierTest extends TestCase
{
    public function testRequiresBothBinaryClasses(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        RubixSignalClassifier::train(
            EvidenceType::ControllerIdentity,
            [
                ['text' => 'controller company identity', 'present' => true],
                ['text' => 'data controller name', 'present' => true],
            ],
            $this->provenance(),
            32,
        );
    }

    public function testPredictionCarriesProbabilityAndModelProvenance(): void
    {
        $model = $this->model();

        $prediction = $model->predict('controller company identity');

        self::assertSame(EvidenceType::ControllerIdentity->value, $prediction->signal);
        self::assertGreaterThanOrEqual(0.0, $prediction->probability);
        self::assertLessThanOrEqual(1.0, $prediction->probability);
        self::assertSame('fixture-v1', $prediction->model->datasetVersion);
        self::assertSame(ClaudinhaLabelMapping::VERSION, $prediction->model->mappingVersion);
        self::assertNull($prediction->artifactSha256);
    }

    public function testRejectsInvalidThreshold(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->model()->predict('controller identity', 1.1);
    }

    private function model(): RubixSignalClassifier
    {
        return RubixSignalClassifier::train(
            EvidenceType::ControllerIdentity,
            [
                ['text' => 'controller company identity privacy policy', 'present' => true],
                ['text' => 'data controller legal name and address', 'present' => true],
                ['text' => 'responsável pelo tratamento controlador empresa', 'present' => true],
                ['text' => 'identificação do controlador de dados', 'present' => true],
                ['text' => 'community news events products', 'present' => false],
                ['text' => 'welcome home contact services', 'present' => false],
                ['text' => 'notícias comunidade agenda eventos', 'present' => false],
                ['text' => 'produtos serviços novidades institucional', 'present' => false],
            ],
            $this->provenance(),
            64,
        );
    }

    private function provenance(): RubixSignalTrainingProvenance
    {
        return new RubixSignalTrainingProvenance(
            'fixture',
            'fixture-v1',
            str_repeat('a', 64),
            str_repeat('b', 64),
            ClaudinhaLabelMapping::VERSION,
            '2026-10-03T12:00:00Z',
        );
    }
}
