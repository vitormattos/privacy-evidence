<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

$repo = dirname(__DIR__, 2);
$samplePath = "$repo/examples/poc/sites.csv";
$config = json_decode((string) file_get_contents("$repo/config/experiments/wec-baseline-v1.json"), true, 512, JSON_THROW_ON_ERROR);
$sample = array_map('str_getcsv', array_slice(file($samplePath, FILE_IGNORE_NEW_LINES) ?: [], 1));
if (count($sample) !== 12) {
    throw new RuntimeException('The synthetic test must use the frozen 12-row input list.');
}
$tmp = sys_get_temp_dir() . '/pe-wec-normalize-test-' . bin2hex(random_bytes(8));
if (!mkdir($tmp, 0700, true)) {
    throw new RuntimeException('Cannot create a temporary synthetic fixture.');
}

/** @param mixed $data */
function fixtureJson(string $file, $data): void
{
    if (!is_dir(dirname($file))) {
        mkdir(dirname($file), 0700, true);
    }
    file_put_contents($file, json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
}

function runCommand(string $command): int
{
    exec($command . ' 2>&1', $output, $status);
    return $status;
}

function removeTree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    foreach (new FilesystemIterator($path) as $item) {
        if ($item->isDir() && !$item->isLink()) {
            removeTree($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }
    rmdir($path);
}

try {
    $commit = str_repeat('a', 40);
    fixtureJson("$tmp/freeze-manifest.json", [
        'sample' => ['sha256' => $config['sample']['sha256']],
        'wec' => ['sha256' => $config['wec']['distributionSha256']],
        'privacyEvidence' => ['gitCommit' => $commit],
    ]);
    fixtureJson("$tmp/privacy-evidence/exports/manifest.json", [
        'id' => 'synthetic-research-run', 'gitCommit' => $commit,
        'protocolVersion' => '0.1.0-draft',
        'versions' => ['php' => '8.4.1'], 'configuration' => ['synthetic' => true],
    ]);
    file_put_contents("$tmp/privacy-evidence/run-id.txt", "synthetic-research-run\n");
    file_put_contents("$tmp/privacy-evidence/started-at.txt", "2026-11-01T00:00:00Z\n");
    file_put_contents("$tmp/privacy-evidence/finished-at.txt", "2026-11-01T00:01:00Z\n");
    $population = [];
    $outcomes = [];
    $documents = [];
    foreach ($sample as $index => $entry) {
        [$id, $name, $url] = $entry;
        $population[] = ['resourceId' => $id, 'sourceValue' => $url];
        $ok = $index > 0;
        $outcomes[] = [
            'resourceId' => $id, 'measurementStatus' => $ok ? 'partially_measured' : 'not_measurable',
            'primaryReason' => $ok ? 'budget_limited' : 'synthetic_timeout',
            'successfulDocuments' => $ok ? 1 : 0,
        ];
        if ($ok) {
            $documents[] = ['resourceId' => $id, 'artifactHash' => str_repeat('f', 64)];
        }
        $wecRoot = "$tmp/wec/$id";
        mkdir("$wecRoot/output", 0700, true);
        file_put_contents("$wecRoot/declared-url.txt", "$url\n");
        file_put_contents("$wecRoot/exit-code.txt", "0\n");
        file_put_contents("$wecRoot/started-at.txt", "2026-11-01T00:00:00Z\n");
        file_put_contents("$wecRoot/finished-at.txt", "2026-11-01T00:00:05Z\n");
        file_put_contents("$wecRoot/stderr.log", $index === 11
            ? "{\"level\":\"error\",\"message\":\"testHttps HTTP 403 (synthetic)\"}\n" : '');
        fixtureJson("$wecRoot/output/inspection.json", [
            'uri_ins' => $url, 'browser' => ['version' => 'synthetic-browser'],
            'script' => ['version' => ['npm' => '3.3.0'], 'node_version' => 'v20', 'config' => []],
            'cookies' => [], 'hosts' => ['requests' => ['thirdParty' => []]],
        ]);
        if (runCommand('bash ' . escapeshellarg("$repo/scripts/research/wec-baseline-artifacts.sh") . ' hash '
            . escapeshellarg($wecRoot)) !== 0) {
            throw new RuntimeException('Synthetic hash verification failed.');
        }
    }
    fixtureJson("$tmp/privacy-evidence/exports/population-results.json", $population);
    fixtureJson("$tmp/privacy-evidence/exports/resource-outcomes.json", $outcomes);
    fixtureJson("$tmp/privacy-evidence/exports/documents.json", $documents);
    file_put_contents("$tmp/wec/started-at.txt", "2026-11-01T00:00:00Z\n");
    file_put_contents("$tmp/wec/finished-at.txt", "2026-11-01T00:01:00Z\n");
    file_put_contents("$tmp/tool-block-order.txt", "privacy-evidence,wec\n");
    $call = 'php ' . escapeshellarg("$repo/scripts/research/normalize-wec-baseline.php") . ' '
        . escapeshellarg($tmp) . ' ' . escapeshellarg($samplePath) . ' 12 12345';
    if (runCommand($call) !== 0) {
        throw new RuntimeException('Synthetic normalizer was expected to pass.');
    }
    $summary = json_decode((string) file_get_contents("$tmp/normalized/summary.json"), true, 512, JSON_THROW_ON_ERROR);
    $rows = json_decode((string) file_get_contents("$tmp/normalized/comparison.json"), true, 512, JSON_THROW_ON_ERROR);
    if (count($rows) !== 24 || $summary['wec']['successfulProcessWithInspection'] !== 12
        || $summary['wec']['validNonSelfHashManifests'] !== 12
        || $summary['wec']['targetsWithNativeHttp403Error'] !== 1
        || $summary['privacyEvidence']['targetsWithSuccessfulDocuments'] !== 11
        || $rows[0]['cookies_observable'] !== null
        || $rows[0]['collection_succeeded'] !== false
        || $rows[1]['cookies_observable'] !== true || $rows[1]['wall_seconds'] !== 5) {
        throw new RuntimeException('Normalizer lost native status or null/non-equivalence distinctions.');
    }
    unlink("$tmp/wec/poc-12/exit-code.txt");
    // A missing exit becomes a retained failure, never an automatically successful target.
    if (runCommand($call) !== 0) {
        throw new RuntimeException('Missing per-target WEC exit should remain as a normalized failure.');
    }
    $rows = json_decode((string) file_get_contents("$tmp/normalized/comparison.json"), true, 512, JSON_THROW_ON_ERROR);
    if ($rows[23]['collection_succeeded'] !== false || $rows[23]['terminal_reason'] !== 'missing_or_invalid_exit_code') {
        throw new RuntimeException('Missing exit code was incorrectly normalized.');
    }
    unlink("$tmp/wec/poc-12/declared-url.txt");
    if (runCommand($call) === 0) {
        throw new RuntimeException('Missing declared target must be rejected.');
    }
    echo "WEC baseline synthetic normalization tests passed.\n";
} finally {
    removeTree($tmp);
}
