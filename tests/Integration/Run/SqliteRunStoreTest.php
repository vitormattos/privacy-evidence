<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Integration\Run;

use PDO;
use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Run\ResearchRun;
use PrivacyEvidence\Run\RunStatus;
use PrivacyEvidence\Run\SqliteRunStore;

final class SqliteRunStoreTest extends TestCase
{
    public function testRunCanBePersistedAndTelemetryUpdated(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite not available');
        }

        $store = new SqliteRunStore(new PDO('sqlite::memory:'));
        $run = new ResearchRun(
            'run-1',
            '2026-10-02T00:00:00Z',
            'abc',
            str_repeat('0', 64),
            '0.1.0',
            ['schema' => '0.1.0'],
            ['http_concurrency' => 4],
        );

        $store->create($run);
        $store->setStatus('run-1', RunStatus::Running);
        $store->increment('run-1', 'processed', 2);

        self::assertSame('run-1', $store->get('run-1')?->id);
        self::assertSame(2.0, $store->telemetry('run-1')['processed']);
    }
}
