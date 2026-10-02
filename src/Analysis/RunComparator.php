<?php

declare(strict_types=1);

namespace PrivacyEvidence\Analysis;

use PrivacyEvidence\Evidence\PrivacyEvidence;
use PrivacyEvidence\Run\RunStore;
use PrivacyEvidence\Storage\ObservationStore;

final readonly class RunComparator
{
    public function __construct(
        private RunStore $runs,
        private ObservationStore $observations,
    ) {
    }

    /**
     * @return array{
     *   baseRun:string,
     *   targetRun:string,
     *   comparable:bool,
     *   warnings:list<string>,
     *   addedResources:list<string>,
     *   removedResources:list<string>,
     *   resourceChanges:list<array<string,mixed>>,
     *   evidenceTransitions:list<array<string,mixed>>
     * }
     */
    public function compare(string $baseRunId, string $targetRunId): array
    {
        $baseRun = $this->runs->get($baseRunId)
            ?? throw new \InvalidArgumentException(sprintf('Unknown base run %s.', $baseRunId));
        $targetRun = $this->runs->get($targetRunId)
            ?? throw new \InvalidArgumentException(sprintf('Unknown target run %s.', $targetRunId));

        $warnings = [];
        if ($baseRun->protocolVersion !== $targetRun->protocolVersion) {
            $warnings[] = sprintf(
                'Protocol version differs: %s -> %s.',
                $baseRun->protocolVersion,
                $targetRun->protocolVersion,
            );
        }

        $baseSchema = $baseRun->versions['schema'] ?? null;
        $targetSchema = $targetRun->versions['schema'] ?? null;
        if ($baseSchema !== $targetSchema) {
            $warnings[] = sprintf(
                'Schema version differs: %s -> %s.',
                $baseSchema ?? 'unspecified',
                $targetSchema ?? 'unspecified',
            );
        }

        $baseResources = $this->indexResources($this->observations->resourceRecords($baseRunId));
        $targetResources = $this->indexResources($this->observations->resourceRecords($targetRunId));

        $baseIds = array_keys($baseResources);
        $targetIds = array_keys($targetResources);

        $added = array_values(array_diff($targetIds, $baseIds));
        $removed = array_values(array_diff($baseIds, $targetIds));
        sort($added);
        sort($removed);

        $changes = [];
        foreach (array_intersect($baseIds, $targetIds) as $resourceId) {
            if (!isset($baseResources[$resourceId], $targetResources[$resourceId])) {
                continue;
            }

            $before = $baseResources[$resourceId];
            $after = $targetResources[$resourceId];
            $fields = [];

            foreach (['name', 'sourceValue', 'normalizedUrl', 'type'] as $field) {
                if (($before[$field] ?? null) !== ($after[$field] ?? null)) {
                    $fields[$field] = [
                        'from' => $before[$field] ?? null,
                        'to' => $after[$field] ?? null,
                    ];
                }
            }

            if ($fields !== []) {
                $changes[] = [
                    'resourceId' => $resourceId,
                    'fields' => $fields,
                ];
            }
        }

        $baseEvidence = $this->evidenceStateSets($this->observations->evidence($baseRunId));
        $targetEvidence = $this->evidenceStateSets($this->observations->evidence($targetRunId));

        $keys = array_values(array_unique(array_merge(
            array_keys($baseEvidence),
            array_keys($targetEvidence),
        )));
        sort($keys);

        $transitions = [];
        foreach ($keys as $key) {
            $from = $baseEvidence[$key] ?? [];
            $to = $targetEvidence[$key] ?? [];

            if ($from === $to) {
                continue;
            }

            [$resourceId, $type] = explode('|', $key, 2);
            $transitions[] = [
                'resourceId' => $resourceId,
                'evidenceType' => $type,
                'fromStates' => $from,
                'toStates' => $to,
            ];
        }

        return [
            'baseRun' => $baseRunId,
            'targetRun' => $targetRunId,
            'comparable' => $warnings === [],
            'warnings' => $warnings,
            'addedResources' => $added,
            'removedResources' => $removed,
            'resourceChanges' => $changes,
            'evidenceTransitions' => $transitions,
        ];
    }

    /**
     * Stable resource ID is the longitudinal identity key.
     *
     * @param list<array<string,mixed>> $records
     * @return array<string,array<string,mixed>>
     */
    private function indexResources(array $records): array
    {
        $indexed = [];
        foreach ($records as $record) {
            $id = $record['id'] ?? null;
            if (!is_string($id) || $id === '') {
                throw new \RuntimeException('Resource record has no stable id.');
            }
            $indexed[$id] = $record;
        }

        ksort($indexed);

        return $indexed;
    }

    /**
     * Preserve all observed semantic states instead of collapsing unknown/unavailable into absent.
     *
     * @param list<PrivacyEvidence> $evidence
     * @return array<string,list<string>>
     */
    private function evidenceStateSets(array $evidence): array
    {
        $grouped = [];
        foreach ($evidence as $item) {
            $key = $item->resourceId . '|' . $item->type->value;
            $grouped[$key][$item->state->value] = true;
        }

        $result = [];
        foreach ($grouped as $key => $states) {
            $values = array_keys($states);
            sort($values);
            $result[$key] = $values;
        }
        ksort($result);

        return $result;
    }
}
