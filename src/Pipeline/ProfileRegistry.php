<?php

declare(strict_types=1);

namespace PrivacyEvidence\Pipeline;

use PrivacyEvidence\Regulatory\RegulatoryProfile;

final readonly class ProfileRegistry
{
    /**
     * @param list<RegulatoryProfile> $profiles
     */
    public function __construct(public array $profiles)
    {
    }
}
