<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Unit\Experiment\Rubix;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Evidence\EvidenceType;
use PrivacyEvidence\Experiment\Dataset\ClaudinhaLabelMapping;
use PrivacyEvidence\Experiment\Rubix\RubixSignalClassifier;
use PrivacyEvidence\Experiment\Rubix\RubixSignalTrainingProvenance;
use PrivacyEvidence\Experiment\Rubix\RubixTextFeaturePipeline;

final class MlResourceBoundsTest extends TestCase
{
    public function testRejectsOversizedVocabularyConfiguration(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new RubixTextFeaturePipeline(RubixTextFeaturePipeline::MAX_VOCABULARY_SIZE + 1);
    }

    public function testRejectsOversizedTrainingText(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new RubixTextFeaturePipeline())->fitTransform([
            str_repeat('x', RubixTextFeaturePipeline::MAX_TEXT_BYTES + 1),
            'valid second class text',
        ]);
    }

    public function testRejectsOversizedInferenceText(): void
    {
        $pipeline = new RubixTextFeaturePipeline();
        $pipeline->fitTransform(['privacy controller', 'community events']);

        $this->expectException(\InvalidArgumentException::class);

        $pipeline->transform([str_repeat('x', RubixTextFeaturePipeline::MAX_TEXT_BYTES + 1)]);
    }

    public function testRejectsTooManyTrainingExamplesBeforeFitting(): void
    {
        $examples = array_fill(
            0,
            RubixSignalClassifier::MAX_TRAINING_EXAMPLES + 1,
            ['text' => 'privacy controller', 'present' => true],
        );

        $this->expectException(\InvalidArgumentException::class);

        RubixSignalClassifier::train(
            EvidenceType::ControllerIdentity,
            $examples,
            new RubixSignalTrainingProvenance(
                'fixture',
                'v1',
                str_repeat('a', 64),
                str_repeat('b', 64),
                ClaudinhaLabelMapping::VERSION,
                '2026-10-03T12:00:00Z',
            ),
        );
    }
}
