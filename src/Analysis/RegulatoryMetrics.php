<?php

declare(strict_types=1);

namespace PrivacyEvidence\Analysis;

final class RegulatoryMetrics
{
    /**
     * @param list<array<string,mixed>> $profileResults
     * @return list<array{
     *   profile:string,
     *   profileVersion:string,
     *   requirementId:string,
     *   requirementTitle:string,
     *   totalResources:int,
     *   measurableResources:int,
     *   observedSupport:int,
     *   partialObservedSupport:int,
     *   noObservedSupport:int,
     *   indeterminate:int,
     *   unavailable:int,
     *   notApplicable:int,
     *   applicabilityUnknown:int,
     *   fullObservedSupportRate:float|null
     * }>
     */
    public function summarize(array $profileResults): array
    {
        /** @var array<string,array{
         *   profile:string,
         *   profileVersion:string,
         *   requirementId:string,
         *   requirementTitle:string,
         *   totalResources:int,
         *   measurableResources:int,
         *   observedSupport:int,
         *   partialObservedSupport:int,
         *   noObservedSupport:int,
         *   indeterminate:int,
         *   unavailable:int,
         *   notApplicable:int,
         *   applicabilityUnknown:int,
         *   fullObservedSupportRate:float|null
         * }> $groups
         */
        $groups = [];

        foreach ($profileResults as $record) {
            $profile = $record['profile'] ?? null;
            $profileVersion = $record['profileVersion'] ?? null;
            $result = $record['result'] ?? null;
            if (!is_string($profile) || !is_string($profileVersion) || !is_array($result)) {
                continue;
            }

            $requirementId = $result['id'] ?? null;
            $title = $result['title'] ?? null;
            $state = $result['state'] ?? null;
            if (!is_string($requirementId) || !is_string($title) || !is_string($state)) {
                continue;
            }

            $key = $profile . '|' . $profileVersion . '|' . $requirementId;
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'profile' => $profile,
                    'profileVersion' => $profileVersion,
                    'requirementId' => $requirementId,
                    'requirementTitle' => $title,
                    'totalResources' => 0,
                    'measurableResources' => 0,
                    'observedSupport' => 0,
                    'partialObservedSupport' => 0,
                    'noObservedSupport' => 0,
                    'indeterminate' => 0,
                    'unavailable' => 0,
                    'notApplicable' => 0,
                    'applicabilityUnknown' => 0,
                    'fullObservedSupportRate' => null,
                ];
            }

            $groups[$key]['totalResources']++;
            match ($state) {
                'observed_support' => $groups[$key]['observedSupport']++,
                'partial_observed_support' => $groups[$key]['partialObservedSupport']++,
                'no_observed_support' => $groups[$key]['noObservedSupport']++,
                'indeterminate' => $groups[$key]['indeterminate']++,
                'unavailable' => $groups[$key]['unavailable']++,
                'not_applicable' => $groups[$key]['notApplicable']++,
                'applicability_unknown' => $groups[$key]['applicabilityUnknown']++,
                default => null,
            };
        }

        foreach ($groups as &$group) {
            $group['measurableResources'] = $group['observedSupport']
                + $group['partialObservedSupport']
                + $group['noObservedSupport'];

            $group['fullObservedSupportRate'] = $group['measurableResources'] > 0
                ? $group['observedSupport'] / $group['measurableResources']
                : null;
        }
        unset($group);

        ksort($groups);

        return array_values($groups);
    }
}
