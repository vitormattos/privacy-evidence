<?php

declare(strict_types=1);

namespace PrivacyEvidence\Regulatory;

use PrivacyEvidence\Evidence\EvidenceType;

final readonly class RequirementMapping
{
    /**
     * @param list<EvidenceType> $evidenceTypes
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $source,
        public array $evidenceTypes,
        public bool $publiclyObservable,
        public string $interpretation,
    ) {
    }
}
