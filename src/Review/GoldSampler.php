<?php

declare(strict_types=1);

namespace PrivacyEvidence\Review;

use PrivacyEvidence\Evidence\PrivacyEvidence;

final class GoldSampler
{
    /**
     * @param list<PrivacyEvidence> $evidence
     * @return list<PrivacyEvidence>
     */
    public function sample(array $evidence, int $perStratum, string $seed): array
    {
        if ($perStratum <= 0) {
            throw new \InvalidArgumentException('perStratum must be positive.');
        }

        /** @var array<string,list<PrivacyEvidence>> $strata */
        $strata = [];

        foreach ($evidence as $item) {
            $key = implode('|', [
                $item->type->value,
                $item->state->value,
                $item->needsReview ? 'review' : 'automatic',
            ]);
            $strata[$key] ??= [];
            $strata[$key][] = $item;
        }

        ksort($strata);
        $selected = [];

        foreach ($strata as $items) {
            usort(
                $items,
                static fn (PrivacyEvidence $a, PrivacyEvidence $b): int =>
                    strcmp(
                        hash('sha256', $seed . '|' . $a->id()),
                        hash('sha256', $seed . '|' . $b->id()),
                    ),
            );

            foreach (array_slice($items, 0, $perStratum) as $item) {
                $selected[$item->id()] = $item;
            }
        }

        ksort($selected);

        return array_values($selected);
    }
}
