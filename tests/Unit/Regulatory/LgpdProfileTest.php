<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Unit\Regulatory;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Regulatory\LgpdProfile;

final class LgpdProfileTest extends TestCase
{
    public function testProfileExposesGranularObservableDimensionsAndConditionalApplicability(): void
    {
        $profile = new LgpdProfile();
        $requirements = $profile->requirements();
        $byId = [];
        foreach ($requirements as $requirement) {
            $byId[$requirement->id] = $requirement;
        }

        self::assertSame('1.1.0', $profile->version());
        self::assertCount(8, $requirements);
        self::assertArrayHasKey('lgpd-art9-purpose', $byId);
        self::assertArrayHasKey('lgpd-art9-controller', $byId);
        self::assertArrayHasKey('lgpd-art9-sharing', $byId);
        self::assertArrayHasKey('lgpd-art9-rights-information', $byId);
        self::assertTrue($byId['lgpd-encarregado']->conditionalApplicability);
        self::assertTrue($byId['lgpd-transfer-transparency']->conditionalApplicability);
        self::assertFalse($byId['lgpd-art9-purpose']->conditionalApplicability);
    }
}
