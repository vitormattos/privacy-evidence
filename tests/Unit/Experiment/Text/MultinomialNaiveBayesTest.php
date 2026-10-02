<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Unit\Experiment\Text;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Experiment\Text\MultinomialNaiveBayes;

final class MultinomialNaiveBayesTest extends TestCase
{
    public function testLearnsSimplePrivacyPolicyVocabularyDeterministically(): void
    {
        $classifier = new MultinomialNaiveBayes();
        $classifier->train([
            ['label' => 'privacy', 'text' => 'privacy personal data controller rights'],
            ['label' => 'privacy', 'text' => 'proteção dados pessoais privacidade direitos'],
            ['label' => 'other', 'text' => 'news products events community'],
            ['label' => 'other', 'text' => 'notícias eventos produtos comunidade'],
        ]);

        self::assertSame(
            'privacy',
            $classifier->predict('privacy data rights')['label'],
        );
        self::assertSame(
            'other',
            $classifier->predict('community events news')['label'],
        );
    }

    public function testRequiresAtLeastTwoLabels(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $classifier = new MultinomialNaiveBayes();
        $classifier->train([
            ['label' => 'privacy', 'text' => 'privacy policy'],
        ]);
    }
}
