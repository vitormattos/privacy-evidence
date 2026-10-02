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
    'max_host_connections' => $concurrency,
    'timeout' => 10.0,
]);

$started = hrtime(true);
$responses = [];
for ($i = 0; $i < $requests; $i++) {
    $responses[] = $client->request('GET', sprintf(
        'http://127.0.0.1:%d/final?request=%d',
        $port,
        $i,
    ));
}

$errors = 0;
$bytes = 0;
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

$finished = hrtime(true);
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
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
