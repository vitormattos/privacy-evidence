<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Unit\Experiment\Rubix;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Experiment\Rubix\RubixTextFeaturePipeline;

final class RubixTextFeaturePipelineTest extends TestCase
{
    public function testNormalizesCaseAndBoundsFeatureDimensions(): void
    {
        $pipeline = new RubixTextFeaturePipeline(3);

        $training = $pipeline->fitTransform([
            'Privacy personal DATA rights',
            'community events products',
            'dados pessoais privacidade',
        ]);

        self::assertCount(3, $training);
        self::assertLessThanOrEqual(3, $pipeline->metadata()->dimensions);
        self::assertSame(3, $pipeline->metadata()->maxVocabularySize);

        self::assertSame(
            $pipeline->transform(['PRIVACY Data']),
            $pipeline->transform(['privacy data']),
        );
    }

    public function testUnknownAndEmptyInferenceProduceBoundedZeroVectors(): void
    {
        $pipeline = new RubixTextFeaturePipeline(8);
        $pipeline->fitTransform([
            'privacy rights controller',
            'community events products',
        ]);

        $unknown = $pipeline->transform(['completely unseen vocabulary']);
        $empty = $pipeline->transform(['']);

        self::assertCount($pipeline->metadata()->dimensions, $unknown[0]);
        self::assertSame(array_fill(0, $pipeline->metadata()->dimensions, 0.0), $unknown[0]);
        self::assertSame(array_fill(0, $pipeline->metadata()->dimensions, 0.0), $empty[0]);
    }

    public function testRejectsBlankTrainingText(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new RubixTextFeaturePipeline())->fitTransform(['privacy data', '   ']);
    }

    public function testRequiresFitBeforeInference(): void
    {
        $this->expectException(\LogicException::class);

        (new RubixTextFeaturePipeline())->transform(['privacy data']);
    }
}
