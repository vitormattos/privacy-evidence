<?php

declare(strict_types=1);

namespace PrivacyEvidence\Analysis;

use PrivacyEvidence\Review\ReviewDecision;

final class InterRaterAgreement
{
    /**
     * @param list<ReviewDecision> $decisions
     * @return array<string,array{
     *   sampleSize:int,
     *   paired:int,
     *   observedAgreement:float|null,
     *   expectedAgreement:float|null,
     *   kappa:float|null,
     *   reviewerA:string,
     *   reviewerB:string,
     *   categories:array<string,array{reviewerA:int,reviewerB:int}>,
     *   disagreements:list<array{evidenceId:string,reviewerAState:string,reviewerBState:string}>
     * }>
     */
    public function compare(array $decisions, string $reviewerA, string $reviewerB): array
    {
        /** @var array<string,ReviewDecision> $a */
        $a = [];
        /** @var array<string,ReviewDecision> $b */
        $b = [];

        foreach ($decisions as $decision) {
            if ($decision->reviewerId === $reviewerA) {
                $a[$decision->evidenceId] = $decision;
            } elseif ($decision->reviewerId === $reviewerB) {
                $b[$decision->evidenceId] = $decision;
            }
        }

        /** @var array<string,array{
         *   matches:int,total:int,
         *   countsA:array<string,int>,countsB:array<string,int>,
         *   disagreements:list<array{evidenceId:string,reviewerAState:string,reviewerBState:string}>
         * }> $groups */
        $groups = [];

        foreach ($a as $evidenceId => $left) {
            $right = $b[$evidenceId] ?? null;
            if ($right === null || $right->type !== $left->type) {
                continue;
            }

            $type = $left->type->value;
            $groups[$type] ??= [
                'matches' => 0,
                'total' => 0,
                'countsA' => [],
                'countsB' => [],
                'disagreements' => [],
            ];

            $leftState = $left->state->value;
            $rightState = $right->state->value;
            $groups[$type]['total']++;
            $groups[$type]['countsA'][$leftState] = ($groups[$type]['countsA'][$leftState] ?? 0) + 1;
            $groups[$type]['countsB'][$rightState] = ($groups[$type]['countsB'][$rightState] ?? 0) + 1;

            if ($leftState === $rightState) {
                $groups[$type]['matches']++;
            } else {
                $groups[$type]['disagreements'][] = [
                    'evidenceId' => $evidenceId,
                    'reviewerAState' => $leftState,
                    'reviewerBState' => $rightState,
                ];
            }
        }

        ksort($groups);
        $result = [];

        foreach ($groups as $type => $group) {
            $total = $group['total'];
            if ($total === 0) {
                continue;
            }

            $categories = array_values(array_unique(array_merge(
                array_keys($group['countsA']),
                array_keys($group['countsB']),
            )));
            sort($categories);

            $expected = 0.0;
            $categoryResult = [];
            foreach ($categories as $category) {
                $countA = $group['countsA'][$category] ?? 0;
                $countB = $group['countsB'][$category] ?? 0;
                $expected += ((float) $countA / (float) $total) * ((float) $countB / (float) $total);
                $categoryResult[$category] = [
                    'reviewerA' => $countA,
                    'reviewerB' => $countB,
                ];
            }

            $observed = (float) $group['matches'] / (float) $total;
            $denominator = 1.0 - $expected;
            $kappa = abs($denominator) < 1.0e-12
                ? null
                : ($observed - $expected) / $denominator;

            $result[$type] = [
                'sampleSize' => $total,
                'paired' => $total,
                'observedAgreement' => $observed,
                'expectedAgreement' => $expected,
                'kappa' => $kappa,
                'reviewerA' => $reviewerA,
                'reviewerB' => $reviewerB,
                'categories' => $categoryResult,
                'disagreements' => $group['disagreements'],
            ];
        }

        return $result;
    }
}
