<?php

declare(strict_types=1);

namespace PrivacyEvidence\Pipeline;

use PrivacyEvidence\Regulatory\BrazilCookiesProfile;
use PrivacyEvidence\Regulatory\EuCookiesProfile;
use PrivacyEvidence\Regulatory\GdprProfile;
use PrivacyEvidence\Regulatory\LgpdProfile;

final class DefaultProfileRegistry
{
    public static function create(): ProfileRegistry
    {
        return new ProfileRegistry([
            new LgpdProfile(),
            new GdprProfile(),
            new BrazilCookiesProfile(),
            new EuCookiesProfile(),
        ]);
    }
}
