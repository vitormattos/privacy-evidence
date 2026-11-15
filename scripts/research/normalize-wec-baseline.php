<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

/**
 * Normalize one frozen WEC/Privacy Evidence execution into RESTRICTED outputs.
 * Usage: php normalize-wec-baseline.php ARTIFACT_ROOT SAMPLE_CSV EXPECTED_COUNT RUN_ID
 * No website I/O or raw evidence publication. Never coerce unknown to false.
 */
if ($argc !== 5) {
    fwrite(STDERR, "Usage: php normalize-wec-baseline.php ARTIFACT_ROOT SAMPLE_CSV EXPECTED_COUNT RUN_ID\n");
    exit(2);
}

[$unused, $root, $sampleFile, $expectedRaw, $workflowRunId] = $argv;
$root = rtrim($root, '/');
if (!is_dir($root) || !is_file($sampleFile) || !preg_match('/^[1-9][0-9]*$/D', $expectedRaw)
    || !preg_match('/^[0-9]+$/D', $workflowRunId)) {
    fwrite(STDERR, "Invalid normalization inputs.\n");
    exit(2);
}
$expected = (int) $expectedRaw;

/** @return array<string, mixed>|list<mixed> */
function readJson(string $path): array
{
    if (!is_file($path)) {
        throw new RuntimeException("Required JSON file missing: $path");
    }
    $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new RuntimeException("Expected JSON object or array: $path");
    }
    return $decoded;
}

/** @return array<string, string> */
function sampleRows(string $path): array
{
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        throw new RuntimeException('Cannot read frozen sample.');
    }
    try {
        if (fgetcsv($handle, 0, ',', '"', '') !== ['id', 'name', 'url']) {
            throw new RuntimeException('Unexpected frozen sample header.');
        }
        $rows = [];
        while (($parts = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            if (count($parts) !== 3 || !preg_match('/^poc-[0-9]{2}$/D', $parts[0]) || $parts[2] === '') {
                throw new RuntimeException('Invalid frozen sample row.');
            }
            if (isset($rows[$parts[0]])) {
                throw new RuntimeException('Duplicate sample ID.');
            }
            $rows[$parts[0]] = $parts[2];
        }
        return $rows;
    } finally {
        fclose($handle);
    }
}

/** @param array<mixed> $records
 * @return array<string, array<string, mixed>>
 */
function keyedRecords(array $records, string $key): array
{
    $indexed = [];
    foreach ($records as $record) {
        if (!is_array($record) || !isset($record[$key]) || !is_string($record[$key])) {
            throw new RuntimeException('Missing native record identity.');
        }
        if (isset($indexed[$record[$key]])) {
            throw new RuntimeException('Duplicate native record identity.');
        }
        $indexed[$record[$key]] = $record;
    }
    return $indexed;
}

/** @return array{errors:int,warnings:int,http403:bool,sandbox_error:bool} */
function stderrDiagnostics(string $path): array
{
    $status = ['errors' => 0, 'warnings' => 0, 'http403' => false, 'sandbox_error' => false];
    if (!is_file($path)) {
        return $status;
    }
    $stream = fopen($path, 'rb');
    if ($stream === false) {
        throw new RuntimeException('Cannot read WEC stderr.');
    }
    try {
        while (($line = fgets($stream)) !== false) {
            $data = json_decode($line, true);
            if (!is_array($data)) {
                continue;
            }
            $level = $data['level'] ?? null;
            if ($level === 'error') {
                ++$status['errors'];
            } elseif ($level === 'warn') {
                ++$status['warnings'];
            }
            $message = $data['message'] ?? null;
            if ($level === 'error' && is_string($message)) {
                $status['http403'] = $status['http403'] || str_contains($message, '403');
                $status['sandbox_error'] = $status['sandbox_error'] || str_contains($message, 'No usable sandbox');
            }
        }
    } finally {
        fclose($stream);
    }
    return $status;
}

function timeValue(string $path): ?string
{
    return is_file($path) ? trim((string) file_get_contents($path)) : null;
}

/** @param mixed $value */
function writeJson(string $path, $value): void
{
    file_put_contents(
        $path,
        json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n"
    );
}

/** @param list<array<string, mixed>> $records */
function writeCsv(string $path, array $records): void
{
    $stream = fopen($path, 'wb');
    if ($stream === false) {
        throw new RuntimeException('Cannot open normalized CSV.');
    }
    try {
        $fields = array_keys($records[0]);
        fputcsv($stream, $fields, ',', '"', '');
        foreach ($records as $record) {
            $values = [];
            foreach ($fields as $field) {
                $value = $record[$field];
                $values[] = $value === null ? '' : (is_bool($value) ? ($value ? 'true' : 'false') : (string) $value);
            }
            fputcsv($stream, $values, ',', '"', '');
        }
    } finally {
        fclose($stream);
    }
}

try {
    $sample = sampleRows($sampleFile);
    if (count($sample) !== $expected) {
        throw new RuntimeException('Frozen sample size mismatch.');
    }
    $config = readJson(dirname(__DIR__, 2) . '/config/experiments/wec-baseline-v1.json');
    if (($config['sample']['sha256'] ?? '') !== hash_file('sha256', $sampleFile)) {
        throw new RuntimeException('Frozen sample SHA-256 mismatch.');
    }
    $freeze = readJson("$root/freeze-manifest.json");
    if (($freeze['sample']['sha256'] ?? '') !== $config['sample']['sha256']
        || ($freeze['wec']['sha256'] ?? '') !== $config['wec']['distributionSha256']) {
        throw new RuntimeException('Frozen experiment identity mismatch.');
    }
    $pe = readJson("$root/privacy-evidence/exports/manifest.json");
    $peOutcomes = keyedRecords(readJson("$root/privacy-evidence/exports/resource-outcomes.json"), 'resourceId');
    $pePopulation = keyedRecords(readJson("$root/privacy-evidence/exports/population-results.json"), 'resourceId');
    $documents = readJson("$root/privacy-evidence/exports/documents.json");
    $peRunId = timeValue("$root/privacy-evidence/run-id.txt");
    if ($peRunId === null || $peRunId === '' || count($peOutcomes) !== $expected
        || count($pePopulation) !== $expected
        || ($pe['id'] ?? null) !== $peRunId
        || ($pe['gitCommit'] ?? null) !== ($freeze['privacyEvidence']['gitCommit'] ?? null)) {
        throw new RuntimeException('Privacy Evidence population or run identity does not reconcile.');
    }

    $peHashes = [];
    foreach ($documents as $document) {
        if (is_array($document) && isset($document['resourceId'], $document['artifactHash'])
            && is_string($document['resourceId']) && is_string($document['artifactHash'])
            && preg_match('/^[a-f0-9]{64}$/D', $document['artifactHash'])) {
            $peHashes[$document['resourceId']] = true;
        }
    }

    $rows = [];
    $wecSuccess = 0;
    $peSuccess = 0;
    $peFailureReasons = 0;
    $wecFailureReasons = 0;
    $wecCookieObservable = 0;
    $wecRequestObservable = 0;
    $wecHashManifests = 0;
    $wecNativeErrors = 0;
    $wecHttp403 = 0;
    $wecDurations = [];

    foreach ($sample as $id => $url) {
        if (!isset($peOutcomes[$id], $pePopulation[$id])
            || ($pePopulation[$id]['sourceValue'] ?? null) !== $url) {
            throw new RuntimeException("Privacy Evidence target mismatch: $id");
        }
        $native = $peOutcomes[$id];
        $status = $native['measurementStatus'] ?? null;
        $reason = $native['primaryReason'] ?? null;
        $successfulDocuments = $native['successfulDocuments'] ?? null;
        if (!is_string($status) || !is_string($reason) || !is_int($successfulDocuments)) {
            throw new RuntimeException("Incomplete Privacy Evidence outcome: $id");
        }
        $succeeded = $successfulDocuments > 0;
        $peSuccess += (int) $succeeded;
        $peFailureReasons += (int) (!$succeeded && $reason !== '');
        $rows[] = [
            'target_id' => $id, 'declared_url' => $url, 'tool' => 'privacy_evidence',
            'tool_version' => $pe['gitCommit'], 'run_session_id' => $peRunId,
            'started_at' => null, 'finished_at' => null,
            'terminal_state' => $status, 'terminal_reason' => $reason,
            'collection_succeeded' => $succeeded,
            'cookies_observable' => null, 'third_party_requests_observable' => null,
            'artifact_or_session_reference' => "privacy-evidence/exports/resource-outcomes.json#$id",
            'artifact_hash_available' => $peHashes[$id] ?? false,
            'config_version_available' => isset($pe['configuration'], $pe['protocolVersion']),
            'tool_version_preserved' => is_string($pe['gitCommit'] ?? null),
            'runtime_version_preserved' => isset($pe['versions']['php']),
            'raw_output_path' => 'privacy-evidence/exports/resource-outcomes.json',
            'normalization_notes' => 'Successful native documents > 0; PE cookie/consent and third-party detectors are not WEC cookie-jar/request equivalents. Per-target wall time unavailable.',
            'process_exit_code' => null, 'native_error_count' => null,
            'native_successful_documents' => $successfulDocuments, 'wall_seconds' => null,
        ];

        $wecDir = "$root/wec/$id";
        if (!is_dir($wecDir) || timeValue("$wecDir/declared-url.txt") !== $url) {
            throw new RuntimeException("WEC target mismatch: $id");
        }
        $exit = timeValue("$wecDir/exit-code.txt");
        $validExit = $exit !== null && preg_match('/^[0-9]+$/D', $exit);
        $exitCode = $validExit ? (int) $exit : null;
        $inspection = null;
        $inspectionPath = "$wecDir/output/inspection.json";
        if (is_file($inspectionPath)) {
            try {
                $inspection = readJson($inspectionPath);
            } catch (Throwable $ignored) {
                // Preserve malformed output as a failed outcome, without dropping the target.
            }
        }
        $nativeInspection = is_array($inspection)
            && is_string($inspection['uri_ins'] ?? null)
            && $inspection['uri_ins'] !== ''
            && is_array($inspection['browser'] ?? null);
        $done = $exitCode === 0 && $nativeInspection;
        $wecSuccess += (int) $done;
        $diagnostics = stderrDiagnostics("$wecDir/stderr.log");
        $wecNativeErrors += $diagnostics['errors'];
        $wecHttp403 += (int) $diagnostics['http403'];
        $reason = $exitCode === null ? 'missing_or_invalid_exit_code'
            : ($exitCode !== 0 ? 'nonzero_process_exit'
                : ($nativeInspection ? 'inspection_available' : 'missing_or_invalid_inspection'));
        $wecFailureReasons += (int) (!$done && $reason !== '');
        $cookies = $inspection['cookies'] ?? null;
        $requests = $inspection['hosts']['requests']['thirdParty'] ?? null;
        $cookiesAvailable = is_array($cookies) ? true : null;
        $requestsAvailable = is_array($requests) ? true : null;
        $wecCookieObservable += (int) ($cookiesAvailable === true);
        $wecRequestObservable += (int) ($requestsAvailable === true);
        $hashManifest = timeValue("$wecDir/files.sha256");
        $hasHash = is_string($hashManifest)
            && str_contains($hashManifest, '/output/inspection.json')
            && !str_contains($hashManifest, '/files.sha256');
        $wecHashManifests += (int) $hasHash;
        $started = timeValue("$wecDir/started-at.txt");
        $finished = timeValue("$wecDir/finished-at.txt");
        $a = $started === null ? false : strtotime($started);
        $b = $finished === null ? false : strtotime($finished);
        $elapsed = $a !== false && $b !== false && $b >= $a ? $b - $a : null;
        if ($elapsed !== null) {
            $wecDurations[] = $elapsed;
        }
        $rows[] = [
            'target_id' => $id, 'declared_url' => $url, 'tool' => 'wec',
            'tool_version' => $inspection['script']['version']['npm'] ?? $config['wec']['version'],
            'run_session_id' => "actions-$workflowRunId/$id",
            'started_at' => $started, 'finished_at' => $finished,
            'terminal_state' => $done ? 'process_completed_with_inspection' : 'incomplete_collection',
            'terminal_reason' => $reason,
            'collection_succeeded' => $done,
            'cookies_observable' => $cookiesAvailable,
            'third_party_requests_observable' => $requestsAvailable,
            'artifact_or_session_reference' => "wec/$id/output/inspection.json",
            'artifact_hash_available' => $hasHash,
            'config_version_available' => isset($inspection['script']['config']) && is_array($inspection['script']['config']),
            'tool_version_preserved' => isset($inspection['script']['version']['npm']),
            'runtime_version_preserved' => isset($inspection['script']['node_version'], $inspection['browser']['version']),
            'raw_output_path' => "wec/$id/output/inspection.json",
            'normalization_notes' => 'WEC success means zero process exit and parseable browser inspection, not proof of origin-site content or equivalence with PE measurementStatus.',
            'process_exit_code' => $exitCode, 'native_error_count' => $diagnostics['errors'],
            'native_successful_documents' => null, 'wall_seconds' => $elapsed,
        ];
    }

    if (count($rows) !== 2 * $expected) {
        throw new RuntimeException('Target/tool population accounting failed.');
    }
    $nativeStatuses = array_count_values(array_map(
        static fn (array $item): string => $item['measurementStatus'],
        array_values($peOutcomes)
    ));
    $summary = [
        'schemaVersion' => '1.0.0',
        'protocolVersion' => $config['protocolVersion'],
        'workflowRunId' => $workflowRunId,
        'privacyEvidenceCommit' => $pe['gitCommit'],
        'wecVersion' => $config['wec']['version'],
        'wecDistributionSha256' => $config['wec']['distributionSha256'],
        'sampleSha256' => $config['sample']['sha256'],
        'declaredTargets' => $expected, 'normalizedRows' => count($rows),
        'toolBlockOrder' => timeValue("$root/tool-block-order.txt"),
        'privacyEvidence' => [
            'terminalRows' => $expected,
            'nativeMeasurementStatuses' => $nativeStatuses,
            'targetsWithSuccessfulDocuments' => $peSuccess,
            'targetsWithoutSuccessfulDocuments' => $expected - $peSuccess,
            'failuresWithMachineReadableReason' => $peFailureReasons,
            'notMeasurableWithReason' => count(array_filter(
                $peOutcomes,
                static fn (array $item): bool => $item['measurementStatus'] === 'not_measurable'
                    && $item['primaryReason'] !== ''
            )),
            'wecComparableCookieJarField' => null,
            'wecComparableThirdPartyRequestsField' => null,
            'targetWallSeconds' => null,
            'blockStart' => timeValue("$root/privacy-evidence/started-at.txt"),
            'blockEnd' => timeValue("$root/privacy-evidence/finished-at.txt"),
        ],
        'wec' => [
            'terminalRows' => $expected,
            'successfulProcessWithInspection' => $wecSuccess,
            'unsuccessfulProcessOrInspection' => $expected - $wecSuccess,
            'failuresWithMachineReadableReason' => $wecFailureReasons,
            'inspectionCookieFieldAvailable' => $wecCookieObservable,
            'inspectionThirdPartyRequestFieldAvailable' => $wecRequestObservable,
            'validNonSelfHashManifests' => $wecHashManifests,
            'nativeStderrErrorRecords' => $wecNativeErrors,
            'targetsWithNativeHttp403Error' => $wecHttp403,
            'perTargetWallSecondsRecorded' => count($wecDurations),
            'blockStart' => timeValue("$root/wec/started-at.txt"),
            'blockEnd' => timeValue("$root/wec/finished-at.txt"),
        ],
        'metricLimitations' => [
            'PE and WEC collection-success operational definitions differ; they are not accuracy rankings.',
            'PE cookie/consent and pre-consent third-party signals do not equal WEC cookie jar and all request records.',
            'PE per-target wall-clock timings were not captured; block timings are not per-target cost estimates.',
            'Browser artifacts and native HTTP/TLS errors can coexist with WEC process exit zero.',
            'This is a 12-target nonprobability diagnostic sample, not a representative web population.',
            'The frozen WEC setup uses --no-sandbox on the hosted runner; do not describe browser sandboxing as enabled.',
        ],
    ];
    $out = "$root/normalized";
    if (!is_dir($out) && !mkdir($out, 0700, true) && !is_dir($out)) {
        throw new RuntimeException('Cannot create restricted normalization directory.');
    }
    writeJson("$out/comparison.json", $rows);
    writeCsv("$out/comparison.csv", $rows);
    writeJson("$out/summary.json", $summary);
    $report = "# Controlled WEC baseline: descriptive normalization\n\n"
        . "**Restricted evidence package: not approved for public publication.**\n\n"
        . "Execution: Actions run $workflowRunId; PE commit {$pe['gitCommit']}; WEC {$config['wec']['version']}.\n"
        . "Frozen diagnostic sample: $expected inputs; SHA-256 {$config['sample']['sha256']}.\n\n"
        . "| Native operational observation | Privacy Evidence | WEC |\n|---|---:|---:|\n"
        . "| Declared inputs represented | $expected/$expected | $expected/$expected |\n"
        . "| Native successful documents / completed inspection | $peSuccess/$expected | $wecSuccess/$expected |\n"
        . "| Native cookie-jar observation field | not equivalent | $wecCookieObservable/$expected |\n"
        . "| Third-party request observation field | not equivalent | $wecRequestObservable/$expected |\n"
        . "| Hash manifest excluding self | not applicable | $wecHashManifests/$expected |\n\n"
        . "The success measures above have different meanings and do not establish comparative tool accuracy.\n"
        . "WEC recorded $wecNativeErrors error-level stderr records; $wecHttp403 target(s) had HTTP 403 diagnostics.\n\n"
        . "## Limitations\n\n"
        . implode("\n", array_map(static fn (string $v): string => "- $v", $summary['metricLimitations']))
        . "\n\nNo statistical significance, legal compliance conclusion, or generic tool ranking is supported.\n";
    file_put_contents("$out/report.md", $report);
    fwrite(STDOUT, "Normalized " . count($rows) . " target/tool rows in RESTRICTED $out\n");
} catch (Throwable $error) {
    fwrite(STDERR, 'Controlled WEC baseline normalization refused: ' . $error->getMessage() . "\n");
    exit(1);
}
