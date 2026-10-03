<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Integration\Experiment;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Experiment\Rubix\RubixBackendProbe;

final class RubixBackendProbeTest extends TestCase
{
    public function testTrainsAndPredictsWithoutExternalServices(): void
    {
        $status = (new RubixBackendProbe())->check();

        self::assertSame('near-zero', $status->prediction);
        self::assertSame('3.0.0-rc4', $status->engineVersion);
        self::assertSame('4.0.0-rc2', $status->tensorVersion);
    }
}
