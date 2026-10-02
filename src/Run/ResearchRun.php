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
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
