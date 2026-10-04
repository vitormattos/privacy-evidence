<?php

declare(strict_types=1);

namespace PrivacyEvidence\Regulatory;

use PrivacyEvidence\Core\ObservationState;
use PrivacyEvidence\Evidence\EvidenceType;
use PrivacyEvidence\Evidence\PrivacyEvidence;

final class ProfileEvaluator
{
    /**
     * @param list<PrivacyEvidence> $evidence
     * @return list<array{
     *   id:string,
     *   title:string,
     *   source:string,
     *   state:string,
     *   present:list<string>,
     *   absent:list<string>,
     *   unknown:list<string>,
     *   unavailable:list<string>,
     *   notApplicable:list<string>,
     *   missing:list<string>,
     *   interpretation:string
     * }>
     */
    public function evaluate(RegulatoryProfile $profile, array $evidence): array
    {
        /** @var array<string,list<ObservationState>> $states */
        $states = [];
        foreach ($evidence as $item) {
            $states[$item->type->value][] = $item->state;
        }

        $results = [];
        foreach ($profile->requirements() as $requirement) {
            $present = [];
            $absent = [];
            $unknown = [];
            $unavailable = [];
            $notApplicable = [];

            foreach ($requirement->evidenceTypes as $type) {
                $state = $this->aggregateTypeState($type, $states);

                match ($state) {
                    ObservationState::Present => $present[] = $type->value,
                    ObservationState::Absent => $absent[] = $type->value,
                    ObservationState::NotApplicable => $notApplicable[] = $type->value,
                    ObservationState::Unknown => $unknown[] = $type->value,
                    default => $unavailable[] = $type->value,
                };
            }

            $missing = [
                ...$absent,
                ...$unknown,
                ...$unavailable,
                ...$notApplicable,
            ];

            $state = $this->requirementState(
                count($requirement->evidenceTypes),
                $present,
                $absent,
                $unknown,
                $unavailable,
                $notApplicable,
            );

            $results[] = [
                'id' => $requirement->id,
                'title' => $requirement->title,
                'source' => $requirement->source,
                'state' => $state,
                'present' => $present,
                'absent' => $absent,
                'unknown' => $unknown,
                'unavailable' => $unavailable,
                'notApplicable' => $notApplicable,
                'missing' => $missing,
                'interpretation' => $requirement->interpretation,
            ];
        }

        return $results;
    }

    /**
     * @param array<string,list<ObservationState>> $states
     */
    private function aggregateTypeState(EvidenceType $type, array $states): ObservationState
    {
        $observed = $states[$type->value] ?? [];
        if ($observed === []) {
            return ObservationState::Unavailable;
        }

        if (in_array(ObservationState::Present, $observed, true)) {
            return ObservationState::Present;
        }
        if (in_array(ObservationState::Absent, $observed, true)) {
            return ObservationState::Absent;
        }
        if (in_array(ObservationState::Unknown, $observed, true)) {
            return ObservationState::Unknown;
        }
        if (in_array(ObservationState::Unavailable, $observed, true)) {
            return ObservationState::Unavailable;
        }
        if (in_array(ObservationState::Invalid, $observed, true)) {
            return ObservationState::Invalid;
        }
        if (in_array(ObservationState::Excluded, $observed, true)) {
            return ObservationState::Excluded;
        }

        return ObservationState::NotApplicable;
    }

    /**
     * @param list<string> $present
     * @param list<string> $absent
     * @param list<string> $unknown
     * @param list<string> $unavailable
     * @param list<string> $notApplicable
     */
    private function requirementState(
        int $requiredTypes,
        array $present,
        array $absent,
        array $unknown,
        array $unavailable,
        array $notApplicable,
    ): string {
        if ($present !== [] && count($present) === $requiredTypes) {
            return 'observed_support';
        }

        if ($present !== []) {
            return 'partial_observed_support';
        }

        if ($absent !== []) {
            return 'no_observed_support';
        }

        if (count($notApplicable) === $requiredTypes) {
            return 'not_applicable';
        }

        if ($unknown !== []) {
            return 'indeterminate';
        }

        if ($unavailable !== []) {
            return 'unavailable';
        }

        return 'indeterminate';
    }
}
