<?php

declare(strict_types=1);

use PrivacyEvidence\Acquisition\FetchedDocument;
use PrivacyEvidence\Pipeline\DefaultDetectorRegistry;
use PrivacyEvidence\Queue\Job;
use PrivacyEvidence\Queue\SqliteJobQueue;

require dirname(__DIR__) . '/vendor/autoload.php';

$iterations = (int) ($_SERVER['BENCH_ITERATIONS'] ?? 1000);
if ($iterations < 1 || $iterations > 100_000) {
    throw new RuntimeException('BENCH_ITERATIONS must be between 1 and 100000.');
}

$started = hrtime(true);
$usageBefore = getrusage();
$fdBefore = is_dir('/proc/self/fd') ? count(scandir('/proc/self/fd') ?: []) : null;
$pdo = new PDO('sqlite::memory:');
$queue = new SqliteJobQueue($pdo, maxPending: $iterations + 10, retryBaseDelayMs: 0);

$queueStart = hrtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $queue->enqueue(new Job(
        id: 'job-' . $i,
        runId: 'benchmark',
        stage: 'fetch',
        deduplicationKey: 'resource-' . $i,
        payload: ['resource_id' => 'resource-' . $i, 'url' => 'https://example.test/' . $i],
    ));
}
$enqueueEnd = hrtime(true);

$processed = 0;
while (($job = $queue->reserve('benchmark', 'fetch')) !== null) {
    $queue->complete($job->id);
    $processed++;
}
$queueEnd = hrtime(true);

$retryJob = new Job(
    id: 'retry-job',
    runId: 'benchmark-retry',
    stage: 'fetch',
    deduplicationKey: 'retry-resource',
    payload: ['resource_id' => 'retry-resource', 'url' => 'https://example.test/retry'],
);
$queue->enqueue($retryJob);
$retryReserved = $queue->reserve('benchmark-retry', 'fetch');
if ($retryReserved === null) {
    throw new RuntimeException('Unable to reserve synthetic retry job.');
}
$queue->fail($retryReserved->id, 'synthetic transient failure', 3);
$retryReservedAgain = $queue->reserve('benchmark-retry', 'fetch');
if ($retryReservedAgain === null) {
    throw new RuntimeException('Synthetic retry job was not requeued.');
}
$queue->complete($retryReservedAgain->id);
$retryEnd = hrtime(true);

$document = new FetchedDocument(
    resourceId: 'fixture',
    requestedUrl: 'https://example.test/',
    finalUrl: 'https://example.test/',
    statusCode: 200,
    mediaType: 'text/html',
    body: '<html><body><h1>Privacy Policy</h1><p>LGPD GDPR privacy contact dpo@example.test rights access correction deletion.</p><button>Accept all cookies</button><button>Reject all</button></body></html>',
    fetchedAt: '2026-10-02T00:00:00Z',
);
$detectors = DefaultDetectorRegistry::create();

$detectorStart = hrtime(true);
$evidenceCount = 0;
for ($i = 0; $i < $iterations; $i++) {
    foreach ($detectors->detectors as $detector) {
        $evidenceCount += count($detector->detect($document));
    }
}
$detectorEnd = hrtime(true);
$finished = hrtime(true);
$usageAfter = getrusage();
$fdAfter = is_dir('/proc/self/fd') ? count(scandir('/proc/self/fd') ?: []) : null;

$durationMs = static fn (int $from, int $to): float => round(($to - $from) / 1_000_000, 2);
$perSecond = static fn (int $count, int $from, int $to): float => round(
    $count / max(($to - $from) / 1_000_000_000, 0.000001),
    2,
);

$result = [
    'schemaVersion' => '1.0.0',
    'phpVersion' => PHP_VERSION,
    'iterations' => $iterations,
    'queue' => [
        'enqueueMs' => $durationMs($queueStart, $enqueueEnd),
        'drainMs' => $durationMs($enqueueEnd, $queueEnd),
        'enqueuePerSecond' => $perSecond($iterations, $queueStart, $enqueueEnd),
        'completePerSecond' => $perSecond($processed, $enqueueEnd, $queueEnd),
        'processed' => $processed,
        'averageEnqueueMicros' => round((($enqueueEnd - $queueStart) / 1000) / $iterations, 2),
        'averageReserveCompleteMicros' => round((($queueEnd - $enqueueEnd) / 1000) / max($processed, 1), 2),
        'syntheticRetryCycleMs' => $durationMs($queueEnd, $retryEnd),
        'syntheticRetrySucceeded' => true,
    ],
    'detectors' => [
        'durationMs' => $durationMs($detectorStart, $detectorEnd),
        'documentsPerSecond' => $perSecond($iterations, $detectorStart, $detectorEnd),
        'evidenceItems' => $evidenceCount,
    ],
    'memory' => [
        'peakBytes' => memory_get_peak_usage(true),
        'peakMiB' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
    ],
    'process' => [
        'userCpuMicros' => (($usageAfter['ru_utime.tv_sec'] ?? 0) - ($usageBefore['ru_utime.tv_sec'] ?? 0)) * 1_000_000
            + (($usageAfter['ru_utime.tv_usec'] ?? 0) - ($usageBefore['ru_utime.tv_usec'] ?? 0)),
        'systemCpuMicros' => (($usageAfter['ru_stime.tv_sec'] ?? 0) - ($usageBefore['ru_stime.tv_sec'] ?? 0)) * 1_000_000
            + (($usageAfter['ru_stime.tv_usec'] ?? 0) - ($usageBefore['ru_stime.tv_usec'] ?? 0)),
        'fileDescriptorsBefore' => $fdBefore,
        'fileDescriptorsAfter' => $fdAfter,
    ],
    'totalMs' => $durationMs($started, $finished),
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
