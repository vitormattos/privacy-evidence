<?php

declare(strict_types=1);

use PrivacyEvidence\Acquisition\FetchedDocument;
use PrivacyEvidence\Pipeline\DefaultDetectorRegistry;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Process\Process;

require dirname(__DIR__) . '/vendor/autoload.php';

$globalConcurrency = 4;
$perHostConcurrency = 2;
$requests = 200;
$router = dirname(__DIR__) . '/tests/Fixtures/http/router.php';

/**
 * @return array{process: Process, port: int}
 */
$startServer = static function (int $port) use ($router): array {
    $process = new Process([PHP_BINARY, '-S', '127.0.0.1:' . $port, $router]);
    $process->setTimeout(null);
    $process->start();

    $deadline = microtime(true) + 5.0;
    while (microtime(true) < $deadline) {
        $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
        if (is_resource($socket)) {
            fclose($socket);

            return ['process' => $process, 'port' => $port];
        }
        usleep(25_000);
    }

    $process->stop();
    throw new RuntimeException('Mixed benchmark fixture server did not become ready.');
};

$first = $startServer(20_000 + random_int(0, 1_000));
$second = $startServer(22_000 + random_int(0, 1_000));

$client = HttpClient::create(['timeout' => 10.0]);
$detectors = DefaultDetectorRegistry::create()->detectors;

$urls = [];
for ($i = 0; $i < $requests; $i++) {
    $port = $i % 2 === 0 ? $first['port'] : $second['port'];
    $urls[] = sprintf('http://127.0.0.1:%d/final?request=%d', $port, $i);
}

$started = hrtime(true);
$errors = 0;
$bytes = 0;
$evidenceItems = 0;
$maxObservedPerHost = 0;

try {
    for ($offset = 0; $offset < $requests; $offset += $globalConcurrency) {
        $batch = array_slice($urls, $offset, $globalConcurrency);
        $hostCounts = [];
        $responses = [];

        foreach ($batch as $url) {
            $parts = parse_url($url);
            if (!is_array($parts) || !isset($parts['host'], $parts['port'])) {
                throw new RuntimeException('Invalid benchmark URL authority.');
            }
            $host = $parts['host'] . ':' . $parts['port'];
            $hostCounts[$host] = ($hostCounts[$host] ?? 0) + 1;
            if ($hostCounts[$host] > $perHostConcurrency) {
                throw new RuntimeException('Per-host benchmark concurrency invariant violated.');
            }
            $maxObservedPerHost = max($maxObservedPerHost, $hostCounts[$host]);
            $responses[$url] = $client->request('GET', $url);
        }

        foreach ($responses as $url => $response) {
            try {
                $body = $response->getContent();
                $bytes += strlen($body);
                $document = new FetchedDocument(
                    resourceId: hash('sha256', $url),
                    requestedUrl: $url,
                    finalUrl: $url,
                    statusCode: $response->getStatusCode(),
                    mediaType: 'text/html',
                    fetchedAt: '2026-01-01T00:00:00+00:00',
                    body: $body,
                    acquisitionMode: 'http',
                    truncated: false,
                );
                foreach ($detectors as $detector) {
                    $evidenceItems += count($detector->detect($document));
                }
            } catch (Throwable) {
                $errors++;
            }
        }
    }
} finally {
    $first['process']->stop();
    $second['process']->stop();
}

$finished = hrtime(true);
$seconds = max(($finished - $started) / 1_000_000_000, 0.000001);

echo json_encode([
    'schemaVersion' => '1.0.0',
    'phpVersion' => PHP_VERSION,
    'requests' => $requests,
    'successfulRequests' => $requests - $errors,
    'errors' => $errors,
    'bytes' => $bytes,
    'evidenceItems' => $evidenceItems,
    'globalConcurrency' => $globalConcurrency,
    'perHostConcurrency' => $perHostConcurrency,
    'maxObservedPerHostConcurrency' => $maxObservedPerHost,
    'elapsedMs' => round($seconds * 1000, 2),
    'requestsPerSecond' => round($requests / $seconds, 2),
    'peakMemoryMiB' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
