<?php

declare(strict_types=1);

namespace PrivacyEvidence\Review;

final class AgreementCalculator
{
    /**
     * @param list<ReviewDecision> $decisions
     * @return array<string,array{
     *   paired:int,
     *   observedAgreement:float|null,
     *   expectedAgreement:float|null,
     *   kappa:float|null,
     *   categories:list<string>
     * }>
     */
    public function cohenKappa(array $decisions, string $reviewerA, string $reviewerB): array
    {
        if ($reviewerA === '' || $reviewerB === '' || $reviewerA === $reviewerB) {
            throw new \InvalidArgumentException('Two distinct reviewer ids are required.');
        }

        /** @var array<string,array<string,array<string,string>>> $labels */
        $labels = [];

        foreach ($decisions as $decision) {
            if ($decision->reviewerType !== ReviewerType::Human) {
                continue;
            }

            if ($decision->reviewerId !== $reviewerA && $decision->reviewerId !== $reviewerB) {
                continue;
            }

            $labels[$decision->type->value][$decision->evidenceId][$decision->reviewerId]
                = $decision->state->value;
        }

        ksort($labels);
        $result = [];

        foreach ($labels as $type => $items) {
            $pairs = [];
            $categories = [];

            foreach ($items as $byReviewer) {
                if (!isset($byReviewer[$reviewerA], $byReviewer[$reviewerB])) {
                    continue;
                }

                $a = $byReviewer[$reviewerA];
                $b = $byReviewer[$reviewerB];
                $pairs[] = [$a, $b];
                $categories[$a] = true;
                $categories[$b] = true;
            }

            $categoryList = array_keys($categories);
            sort($categoryList);
            $count = count($pairs);

            if ($count === 0) {
                $result[$type] = [
                    'paired' => 0,
                    'observedAgreement' => null,
                    'expectedAgreement' => null,
                    'kappa' => null,
                    'categories' => $categoryList,
                ];
                continue;
            }

            $matches = 0;
            /** @var array<string,int> $countsA */
            $countsA = [];
            /** @var array<string,int> $countsB */
            $countsB = [];

            foreach ($pairs as [$a, $b]) {
                if ($a === $b) {
                    $matches++;
                }
                $countsA[$a] = ($countsA[$a] ?? 0) + 1;
                $countsB[$b] = ($countsB[$b] ?? 0) + 1;
            }

            $observed = $matches / $count;
            $expected = 0.0;
            foreach ($categoryList as $category) {
                $expected += (($countsA[$category] ?? 0) / $count)
                    * (($countsB[$category] ?? 0) / $count);
            }

            $kappa = (1.0 - $expected) <= 0.0
                ? null
                : ($observed - $expected) / (1.0 - $expected);

            $result[$type] = [
                'paired' => $count,
                'observedAgreement' => $observed,
                'expectedAgreement' => $expected,
                'kappa' => $kappa,
                'categories' => $categoryList,
            ];
        }

        return $result;
    }
}
