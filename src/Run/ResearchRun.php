<?php

declare(strict_types=1);

namespace PrivacyEvidence\Run;

final readonly class ResearchRun
{
    /**
     * @param array<string, string> $versions
     * @param array<string, scalar|array<array-key, scalar>|null> $configuration
     */
    public function __construct(
        public string $id,
        public string $startedAt,
        public string $gitCommit,
        public string $datasetHash,
        public string $protocolVersion,
        public array $versions,
        public array $configuration,
    ) {
    }

    /**
     * @return array{
     *   id:string,
     *   startedAt:string,
     *   gitCommit:string,
     *   datasetHash:string,
     *   protocolVersion:string,
     *   versions:array<string,string>,
     *   configuration:array<string, scalar|array<array-key, scalar>|null>,
     *   status:string
     * }
     */
    public function toArray(RunStatus $status = RunStatus::Created): array
    {
        return [
            'id' => $this->id,
            'startedAt' => $this->startedAt,
            'gitCommit' => $this->gitCommit,
            'datasetHash' => $this->datasetHash,
            'protocolVersion' => $this->protocolVersion,
            'versions' => $this->versions,
            'configuration' => $this->configuration,
            'status' => $status->value,
        ];
    }
}
