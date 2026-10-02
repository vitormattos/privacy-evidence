<?php

declare(strict_types=1);

namespace PrivacyEvidence\Regulatory;

use PrivacyEvidence\Core\ObservationState;
use PrivacyEvidence\Evidence\PrivacyEvidence;

final class ProfileEvaluator
{
    /**
     * @param list<PrivacyEvidence> $evidence
     * @return list<array{id:string,state:string,present:list<string>,missing:list<string>,interpretation:string}>
     */
    public function evaluate(RegulatoryProfile $profile, array $evidence): array
    {
        $states = [];
        foreach ($evidence as $item) {
            $states[$item->type->value][] = $item->state;
        }

        $results = [];
        foreach ($profile->requirements() as $requirement) {
            $present = [];
            $missing = [];
            foreach ($requirement->evidenceTypes as $type) {
                $typeStates = $states[$type->value] ?? [];
                $hasPresent = in_array(ObservationState::Present, $typeStates, true);
                if ($hasPresent) {
                    $present[] = $type->value;
                } else {
                    $missing[] = $type->value;
                }
            }

            $state = $present === []
                ? 'no_observed_support'
                : ($missing === [] ? 'observed_support' : 'partial_observed_support');

            $results[] = [
                'id' => $requirement->id,
                'state' => $state,
                'present' => $present,
                'missing' => $missing,
                'interpretation' => $requirement->interpretation,
            ];
        }

        return $results;
    }
}
