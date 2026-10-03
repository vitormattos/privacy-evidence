<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Unit\Experiment\Review;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Core\ObservationState;
use PrivacyEvidence\Evidence\EvidenceType;
use PrivacyEvidence\Evidence\PrivacyEvidence;
use PrivacyEvidence\Experiment\Review\MlReviewPolicy;
use PrivacyEvidence\Experiment\Rubix\RubixSignalModelMetadata;
use PrivacyEvidence\Experiment\Rubix\RubixSignalPrediction;

final class MlReviewPolicyTest extends TestCase
{
    /**
     * @return iterable<string,array{ObservationState,bool,float,bool,bool,int}>
     */
    public static function scenarios(): iterable
    {
        yield 'agreement high confidence' => [ObservationState::Present, false, 0.90, false, false, 0];
        yield 'disagreement' => [ObservationState::Absent, false, 0.90, true, true, 100];
        yield 'low confidence agreement' => [ObservationState::Present, false, 0.55, false, true, 50];
        yield 'rule already requests review' => [ObservationState::Present, true, 0.90, false, true, 25];
        yield 'unknown rule state' => [ObservationState::Unknown, false, 0.90, false, true, 25];
    }

    #[DataProvider('scenarios')]
    public function testDeterministicReviewPolicy(
        ObservationState $ruleState,
        bool $ruleNeedsReview,
        float $probability,
        bool $disagrees,
        bool $needsReview,
        int $priority,
    ): void {
        $evidence = $this->evidence($ruleState, $ruleNeedsReview);
        $prediction = $this->prediction($probability);

        $assessment = (new MlReviewPolicy())->assess($evidence, $prediction);

        self::assertSame($disagrees, $assessment->disagreesWithRule);
        self::assertSame($needsReview, $assessment->needsReview);
        self::assertSame($priority, $assessment->priority);
    }

    private function evidence(ObservationState $state, bool $needsReview): PrivacyEvidence
    {
        return new PrivacyEvidence(
            EvidenceType::ControllerIdentity,
            $state,
            'resource-1',
            str_repeat('a', 64),
            'https://example.test/privacy',
            'privacy_contact_dpo',
            '1.0.0',
            'rule_based_text',
            needsReview: $needsReview,
        );
    }

    private function prediction(float $probability): RubixSignalPrediction
    {
        return new RubixSignalPrediction(
            EvidenceType::ControllerIdentity->value,
            $probability,
            $probability >= 0.5,
            0.5,
            new RubixSignalModelMetadata(
                EvidenceType::ControllerIdentity->value,
                'fixture',
                'v1',
                str_repeat('b', 64),
                str_repeat('c', 64),
                '1.0.0',
                '1.0.0',
                'Rubix\\ML\\Classifiers\\GaussianNB',
                '3.0.0-rc4',
                '2026-10-03T12:00:00Z',
            ),
            str_repeat('d', 64),
        );
    }
}
