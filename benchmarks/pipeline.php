<?php

declare(strict_types=1);

use PDO;
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
$pdo = new PDO('sqlite::memory:');
$queue = new SqliteJobQueue($pdo, maxPendingJobs: $iterations + 10);

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
    'totalMs' => $durationMs($started, $finished),
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
