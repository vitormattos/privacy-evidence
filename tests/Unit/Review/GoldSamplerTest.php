<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Unit\Review;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Core\ObservationState;
use PrivacyEvidence\Evidence\EvidenceType;
use PrivacyEvidence\Evidence\PrivacyEvidence;
use PrivacyEvidence\Review\GoldSampler;

final class GoldSamplerTest extends TestCase
{
    public function testSamplingIsDeterministicAndStratified(): void
    {
        $evidence = [
            $this->evidence('a', ObservationState::Present, false),
            $this->evidence('b', ObservationState::Present, false),
            $this->evidence('c', ObservationState::Absent, false),
            $this->evidence('d', ObservationState::Absent, false),
            $this->evidence('e', ObservationState::Unknown, true),
            $this->evidence('f', ObservationState::Unknown, true),
        ];

        $sampler = new GoldSampler();
        $first = $sampler->sample($evidence, 1, 'seed-1');
        $second = $sampler->sample(array_reverse($evidence), 1, 'seed-1');

        self::assertSame(
            array_map(static fn (PrivacyEvidence $item): string => $item->id(), $first),
            array_map(static fn (PrivacyEvidence $item): string => $item->id(), $second),
        );
        self::assertCount(3, $first);
        $states = array_values(array_unique(array_map(
            static fn (PrivacyEvidence $item): string => $item->state->value,
            $first,
        )));
        sort($states);

        self::assertSame(['absent', 'present', 'unknown'], $states);
    }

    private function evidence(
        string $resource,
        ObservationState $state,
        bool $needsReview,
    ): PrivacyEvidence {
        return new PrivacyEvidence(
            EvidenceType::PrivacyNotice,
            $state,
            $resource,
            hash('sha256', 'artifact-' . $resource),
            'https://' . $resource . '.test/privacy',
            'fixture',
            '1.0.0',
            'test',
            needsReview: $needsReview,
        );
    }
}
