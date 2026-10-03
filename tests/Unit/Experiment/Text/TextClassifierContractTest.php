<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Unit\Experiment\Text;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Experiment\Text\MultinomialNaiveBayes;
use PrivacyEvidence\Experiment\Text\TextClassifier;

final class TextClassifierContractTest extends TestCase
{
    public function testBaselineImplementsProjectContract(): void
    {
        $classifier = new MultinomialNaiveBayes();

        self::assertInstanceOf(TextClassifier::class, $classifier);

        $classifier->train([
            ['label' => 'privacy', 'text' => 'privacy personal data controller rights'],
            ['label' => 'other', 'text' => 'news products events community'],
        ]);

        $result = $classifier->classify('privacy data rights');

        self::assertSame('privacy', $result->label);
        self::assertArrayHasKey('privacy', $result->scores);
        self::assertSame(MultinomialNaiveBayes::CLASSIFIER_ID, $result->classifier);
        self::assertSame(MultinomialNaiveBayes::CLASSIFIER_VERSION, $result->classifierVersion);
    }
}
