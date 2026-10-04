<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Integration\Queue;

use PDO;
use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Queue\Job;
use PrivacyEvidence\Queue\JobStatus;
use PrivacyEvidence\Queue\SqliteJobQueue;

final class SqliteJobQueueTest extends TestCase
{
    public function testJobsAreIdempotentRetriedWithBackoffAndResumable(): void
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
            host: 'example.test',
        );

        $queue->enqueue($job);
        $queue->enqueue(
            new Job(
                'j2',
                'r1',
                'fetch',
                'site-1',
                ['url' => 'https://example.test'],
                host: 'example.test',
            ),
        );

        self::assertSame(['pending' => 1], $queue->counts('r1'));

        $reserved = $queue->reserve('r1', 'fetch');
        self::assertInstanceOf(Job::class, $reserved);
        self::assertSame('j1', $reserved->id);
        self::assertNotNull($reserved->reservedAtMs);

        self::assertSame(JobStatus::Pending, $queue->fail('j1', 'temporary'));
        self::assertNull($queue->reserve('r1', 'fetch'));

        usleep(550_000);
        $reserved = $queue->reserve('r1', 'fetch');
        self::assertInstanceOf(Job::class, $reserved);
        self::assertSame(2, $reserved->attempts);

        self::assertSame(1, $queue->requeueRunning('r1'));
        self::assertSame(['pending' => 1], $queue->counts('r1'));

        $reserved = $queue->reserve('r1', 'fetch');
        self::assertInstanceOf(Job::class, $reserved);
        self::assertSame(3, $reserved->attempts);
        $queue->complete('j1');

        self::assertSame(['completed' => 1], $queue->counts('r1'));
    }


    public function testDuplicateDeliveryRemainsIdempotentAtCapacity(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite not available');
        }

        $queue = new SqliteJobQueue(new PDO('sqlite::memory:'), maxPending: 1);
        $queue->enqueue(new Job('j1', 'r1', 'fetch', 'same', ['url' => 'https://a.test']));
        $queue->enqueue(new Job('j2', 'r1', 'fetch', 'same', ['url' => 'https://a.test']));

        self::assertSame(['pending' => 1], $queue->counts('r1'));

        try {
            $queue->enqueue(new Job('j3', 'r1', 'fetch', 'different', ['url' => 'https://b.test']));
            self::fail('Expected queue backpressure exception.');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('Job queue capacity reached', $e->getMessage());
        }
    }

    public function testPriorityAndPerHostConcurrencyAreAppliedAcrossCandidates(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite not available');
        }

        $queue = new SqliteJobQueue(new PDO('sqlite::memory:'));

        $queue->enqueue(new Job(
            'low',
            'r1',
            'fetch',
            'low',
            ['url' => 'https://a.test/low'],
            priority: 10,
            host: 'a.test',
        ));
        $queue->enqueue(new Job(
            'high',
            'r1',
            'fetch',
            'high',
            ['url' => 'https://a.test/high'],
            priority: 100,
            host: 'a.test',
        ));
        $queue->enqueue(new Job(
            'other',
            'r1',
            'fetch',
            'other',
            ['url' => 'https://b.test/'],
            priority: 50,
            host: 'b.test',
        ));

        $first = $queue->reserve('r1', 'fetch', perHostConcurrency: 1);
        self::assertSame('high', $first?->id);

        $second = $queue->reserve('r1', 'fetch', perHostConcurrency: 1);
        self::assertSame('other', $second?->id);

        self::assertSame(['pending' => 1, 'running' => 2], $queue->stageCounts('r1', 'fetch'));
    }

    public function testExpectedFailuresCanTerminateWithoutDeadLetteringTheRun(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite not available');
        }

        $queue = new SqliteJobQueue(new PDO('sqlite::memory:'));
        $queue->enqueue(new Job(
            'unreachable',
            'r1',
            'fetch',
            'unreachable',
            ['resource_id' => 'resource-1', 'url' => 'https://blocked.example'],
        ));

        $queue->reserve('r1', 'fetch');
        self::assertSame(
            JobStatus::Failed,
            $queue->fail(
                'unreachable',
                'private network destination',
                maxAttempts: 1,
                terminalStatus: JobStatus::Failed,
            ),
        );
        self::assertSame(['failed' => 1], $queue->counts('r1'));

        $failures = $queue->failures('r1');
        self::assertCount(1, $failures);
        self::assertSame('failed', $failures[0]['status']);
        self::assertSame('resource-1', $failures[0]['resourceId']);
    }

    public function testDeadLettersAfterMaximumAttempts(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite not available');
        }

        $queue = new SqliteJobQueue(new PDO('sqlite::memory:'));
        $queue->enqueue(new Job(
            'dead',
            'r1',
            'fetch',
            'dead',
            ['url' => 'https://example.test'],
        ));

        $queue->reserve('r1', 'fetch');
        self::assertSame(JobStatus::Dead, $queue->fail('dead', 'fatal', maxAttempts: 1));
        self::assertSame(['dead' => 1], $queue->counts('r1'));

        self::assertSame([
            [
                'id' => 'dead',
                'stage' => 'fetch',
                'status' => 'dead',
                'attempts' => 1,
                'url' => 'https://example.test',
                'resourceId' => null,
                'error' => 'fatal',
            ],
        ], $queue->failures('r1'));
    }
}
