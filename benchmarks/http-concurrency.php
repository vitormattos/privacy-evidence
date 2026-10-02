<?php

declare(strict_types=1);

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Process\Process;

require dirname(__DIR__) . '/vendor/autoload.php';

$concurrency = (int) ($_SERVER['BENCH_CONCURRENCY'] ?? 8);
$requests = (int) ($_SERVER['BENCH_REQUESTS'] ?? 200);

if ($concurrency < 1 || $concurrency > 128) {
    throw new RuntimeException('BENCH_CONCURRENCY must be between 1 and 128.');
}
if ($requests < 1 || $requests > 100_000) {
    throw new RuntimeException('BENCH_REQUESTS must be between 1 and 100000.');
}

$port = 18_000 + random_int(0, 2_000);
$router = dirname(__DIR__) . '/tests/Fixtures/http/router.php';
$server = new Process([PHP_BINARY, '-S', '127.0.0.1:' . $port, $router]);
$server->setTimeout(null);
$server->start();

$deadline = microtime(true) + 5.0;
$ready = false;
while (microtime(true) < $deadline) {
    $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
    if (is_resource($socket)) {
        fclose($socket);
        $ready = true;
        break;
    }
    usleep(25_000);
}

if (!$ready) {
    $server->stop();
    throw new RuntimeException('Benchmark fixture server did not become ready.');
}

$client = HttpClient::create([
    'timeout' => 10.0,
]);

$fdCount = static function (): ?int {
    if (!is_dir('/proc/self/fd')) {
        return null;
    }
    $items = scandir('/proc/self/fd');
    return is_array($items) ? max(count($items) - 2, 0) : null;
};

$usageBefore = getrusage();
$fdBefore = $fdCount();
$started = hrtime(true);
$errors = 0;
$bytes = 0;
$peakObservedFd = $fdBefore ?? 0;

for ($offset = 0; $offset < $requests; $offset += $concurrency) {
    $batchSize = min($concurrency, $requests - $offset);
    $responses = [];

    for ($i = 0; $i < $batchSize; $i++) {
        $requestNumber = $offset + $i;
        $responses[] = $client->request('GET', sprintf(
            'http://127.0.0.1:%d/final?request=%d',
            $port,
            $requestNumber,
        ));
    }

    $fdAfterDispatch = $fdCount();
    if (is_int($fdAfterDispatch)) {
        $peakObservedFd = max($peakObservedFd, $fdAfterDispatch);
    }

    foreach ($client->stream($responses) as $response => $chunk) {
        if ($chunk->isTimeout()) {
            $errors++;
            continue;
        }
        if ($chunk->isLast()) {
            try {
                if ($response->getStatusCode() !== 200) {
                    $errors++;
                }
            } catch (Throwable) {
                $errors++;
            }
            continue;
        }
        try {
            $bytes += strlen($chunk->getContent());
        } catch (Throwable) {
            $errors++;
        }
    }
}

$finished = hrtime(true);
$usageAfter = getrusage();
$fdAfter = $fdCount();
$server->stop();

$seconds = max(($finished - $started) / 1_000_000_000, 0.000001);

echo json_encode([
    'schemaVersion' => '1.0.0',
    'phpVersion' => PHP_VERSION,
    'concurrency' => $concurrency,
    'requests' => $requests,
    'successfulRequests' => $requests - $errors,
    'errors' => $errors,
    'bytes' => $bytes,
    'elapsedMs' => round($seconds * 1000, 2),
    'requestsPerSecond' => round($requests / $seconds, 2),
    'peakMemoryBytes' => memory_get_peak_usage(true),
    'peakMemoryMiB' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
    'fileDescriptors' => [
        'before' => $fdBefore,
        'after' => $fdAfter,
        'peakObserved' => max($peakObservedFd, $fdAfter ?? 0),
    ],
    'cpu' => [
        'userMicros' => (($usageAfter['ru_utime.tv_sec'] ?? 0) - ($usageBefore['ru_utime.tv_sec'] ?? 0)) * 1_000_000
            + (($usageAfter['ru_utime.tv_usec'] ?? 0) - ($usageBefore['ru_utime.tv_usec'] ?? 0)),
        'systemMicros' => (($usageAfter['ru_stime.tv_sec'] ?? 0) - ($usageBefore['ru_stime.tv_sec'] ?? 0)) * 1_000_000
            + (($usageAfter['ru_stime.tv_usec'] ?? 0) - ($usageBefore['ru_stime.tv_usec'] ?? 0)),
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
