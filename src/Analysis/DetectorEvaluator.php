<?php

declare(strict_types=1);

namespace PrivacyEvidence\Analysis;

use PrivacyEvidence\Core\ObservationState;
use PrivacyEvidence\Evidence\PrivacyEvidence;
use PrivacyEvidence\Review\ReviewDecision;

final class DetectorEvaluator
{
    /**
     * @param list<PrivacyEvidence> $automated
     * @param list<ReviewDecision> $groundTruth
     * @return array{
     *   protocolVersion:string,
     *   goldDatasetVersion:string,
     *   signals:array<string,array<string,mixed>>
     * }
     */
    public function evaluate(
        array $automated,
        array $groundTruth,
        string $protocolVersion,
        string $goldDatasetVersion,
    ): array {
        /** @var array<string, ReviewDecision> $truthByEvidence */
        $truthByEvidence = [];
        foreach ($groundTruth as $decision) {
            $truthByEvidence[$decision->evidenceId] = $decision;
        }

        /** @var array<string,array{
         *   tp:int,fp:int,tn:int,fn:int,abstained:int,truthUnknown:int,
         *   evaluated:int,total:int,detectors:array<string,bool>,
         *   falsePositives:list<array<string,string>>,
         *   falseNegatives:list<array<string,string>>
         * }> $signals */
        $signals = [];

        foreach ($automated as $evidence) {
            $type = $evidence->type->value;
            $signals[$type] ??= [
                'tp' => 0,
                'fp' => 0,
                'tn' => 0,
                'fn' => 0,
                'abstained' => 0,
                'truthUnknown' => 0,
                'evaluated' => 0,
                'total' => 0,
                'detectors' => [],
                'falsePositives' => [],
                'falseNegatives' => [],
            ];

            $signal = &$signals[$type];
            $signal['total']++;
            $signal['detectors'][$evidence->detector . '@' . $evidence->detectorVersion] = true;

            $truth = $truthByEvidence[$evidence->id()] ?? null;
            if ($truth === null || $truth->type !== $evidence->type) {
                $signal['truthUnknown']++;
                unset($signal);
                continue;
            }

            if (!$this->binary($truth->state)) {
                $signal['truthUnknown']++;
                unset($signal);
                continue;
            }

            if (!$this->binary($evidence->state)) {
                $signal['abstained']++;
                unset($signal);
                continue;
            }

            $signal['evaluated']++;
            $predicted = $evidence->state === ObservationState::Present;
            $actual = $truth->state === ObservationState::Present;

            if ($predicted && $actual) {
                $signal['tp']++;
            } elseif ($predicted) {
                $signal['fp']++;
                $signal['falsePositives'][] = $this->caseRecord($evidence, $truth);
            } elseif ($actual) {
                $signal['fn']++;
                $signal['falseNegatives'][] = $this->caseRecord($evidence, $truth);
            } else {
                $signal['tn']++;
            }

            unset($signal);
        }

        ksort($signals);
        /** @var array<string,array{
         *   confusionMatrix:array{truePositive:int,falsePositive:int,trueNegative:int,falseNegative:int},
         *   precision:float|null,
         *   recall:float|null,
         *   f1:float|null,
         *   support:int,
         *   evaluated:int,
         *   abstained:int,
         *   truthUnknown:int,
         *   coverage:float|null,
         *   detectors:list<string>,
         *   falsePositives:list<array<string,string>>,
         *   falseNegatives:list<array<string,string>>
         * }> $result */
        $result = [];

        foreach ($signals as $type => $signal) {
            $matrix = new ConfusionMatrix(
                $signal['tp'],
                $signal['fp'],
                $signal['tn'],
                $signal['fn'],
            );
            $eligible = $signal['evaluated'] + $signal['abstained'];
            $coverage = $eligible === 0 ? null : $signal['evaluated'] / $eligible;

            $detectors = array_keys($signal['detectors']);
            sort($detectors);

            $result[$type] = [
                'confusionMatrix' => [
                    'truePositive' => $matrix->truePositive,
                    'falsePositive' => $matrix->falsePositive,
                    'trueNegative' => $matrix->trueNegative,
                    'falseNegative' => $matrix->falseNegative,
                ],
                'precision' => $matrix->precision(),
                'recall' => $matrix->recall(),
                'f1' => $matrix->f1(),
                'support' => $matrix->support(),
                'evaluated' => $signal['evaluated'],
                'abstained' => $signal['abstained'],
                'truthUnknown' => $signal['truthUnknown'],
                'coverage' => $coverage,
                'detectors' => $detectors,
                'falsePositives' => $signal['falsePositives'],
                'falseNegatives' => $signal['falseNegatives'],
            ];
        }

        return [
            'protocolVersion' => $protocolVersion,
            'goldDatasetVersion' => $goldDatasetVersion,
            'signals' => $result,
        ];
    }

    private function binary(ObservationState $state): bool
    {
        return $state === ObservationState::Present || $state === ObservationState::Absent;
    }

    /**
     * @return array<string,string>
     */
    private function caseRecord(PrivacyEvidence $evidence, ReviewDecision $truth): array
    {
        return [
            'evidenceId' => $evidence->id(),
            'resourceId' => $evidence->resourceId,
            'artifactHash' => $evidence->artifactHash,
            'sourceUrl' => $evidence->sourceUrl,
            'detector' => $evidence->detector,
            'detectorVersion' => $evidence->detectorVersion,
            'predicted' => $evidence->state->value,
            'actual' => $truth->state->value,
        ];
    }
}
