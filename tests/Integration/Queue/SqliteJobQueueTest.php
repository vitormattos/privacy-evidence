<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Integration\Queue;

use PDO;
use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Queue\Job;
use PrivacyEvidence\Queue\SqliteJobQueue;

final class SqliteJobQueueTest extends TestCase
{
    public function testJobsAreIdempotentAndResumable(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite not available');
        }

        $queue = new SqliteJobQueue(new PDO('sqlite::memory:'));
        $job = new Job(
            'j1',
            'r1',
            'fetch',
            'site-1',
            ['url' => 'https://example.test'],
        );

        $queue->enqueue($job);
        $queue->enqueue(
            new Job(
                'j2',
                'r1',
                'fetch',
                'site-1',
                ['url' => 'https://example.test'],
            ),
        );

        self::assertSame(['pending' => 1], $queue->counts('r1'));

        $reserved = $queue->reserve('r1', 'fetch');
        self::assertSame('j1', $reserved?->id);

        $queue->fail('j1', 'temporary');
        self::assertSame(['pending' => 1], $queue->counts('r1'));

        $reserved = $queue->reserve('r1', 'fetch');
        self::assertSame(2, $reserved?->attempts);

        $queue->complete('j1');
        self::assertSame(['completed' => 1], $queue->counts('r1'));
    }
}
