<?php

declare(strict_types=1);

namespace PrivacyEvidence\Regulatory;

interface RegulatoryProfile
{
    public function id(): string;

    public function version(): string;

    /**
     * @return list<RequirementMapping>
     */
    public function requirements(): array;
}
