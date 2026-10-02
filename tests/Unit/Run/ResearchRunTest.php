<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Unit\Run;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Run\ResearchRun;

final class ResearchRunTest extends TestCase
{
    public function testManifestContainsIndependentVersions(): void
    {
        $run = new ResearchRun(
            id: 'run-1',
            startedAt: '2026-10-02T00:00:00Z',
            gitCommit: 'abc123',
            datasetHash: str_repeat('0', 64),
            protocolVersion: '1.0.0',
            versions: ['schema' => '1.0.0', 'lgpd-profile' => '1.0.0'],
            configuration: ['http_concurrency' => 8],
        );

        self::assertSame('1.0.0', $run->toArray()['protocolVersion']);
        self::assertSame('1.0.0', $run->toArray()['versions']['schema']);
    }
}
