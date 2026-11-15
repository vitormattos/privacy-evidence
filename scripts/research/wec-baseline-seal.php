<?php

// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

/**
 * Authenticated, streamed sealed artifact for public GitHub Actions artifacts.
 * Usage: WEC_BASELINE_ENCRYPTION_KEY=64hex php wec-baseline-seal.php encrypt|decrypt INPUT OUTPUT
 * Do not pass the key as a command-line argument or write it to GitHub logs.
 */
if ($argc !== 4 || !in_array($argv[1], ['encrypt', 'decrypt'], true)) {
    fwrite(STDERR, "Usage: wec-baseline-seal.php encrypt|decrypt INPUT OUTPUT\n");
    exit(2);
}

$keyHex = getenv('WEC_BASELINE_ENCRYPTION_KEY');
if (!is_string($keyHex) || preg_match('/\A[a-f0-9]{64}\z/iD', $keyHex) !== 1 || !extension_loaded('sodium')) {
    fwrite(STDERR, "Secure baseline encryption key or sodium extension unavailable; refusing operation.\n");
    exit(2);
}
$key = sodium_hex2bin($keyHex);
$magic = 'PEWEC01';
$mode = $argv[1];
$input = $argv[2];
$output = $argv[3];
$source = fopen($input, 'rb');
if ($source === false || file_exists($output)) {
    fwrite(STDERR, "Cannot open input or output already exists.\n");
    exit(2);
}
$destination = fopen($output, 'xb');
if ($destination === false) {
    fclose($source);
    fwrite(STDERR, "Cannot create sealed artifact output.\n");
    exit(2);
}

try {
    if ($mode === 'encrypt') {
        [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
        if (fwrite($destination, $magic . $header) !== strlen($magic . $header)) {
            throw new RuntimeException('Cannot write sealed header.');
        }
        while (true) {
            $chunk = fread($source, 65536);
            if ($chunk === false) {
                throw new RuntimeException('Cannot read source archive.');
            }
            $final = $chunk === '' && feof($source);
            $cipher = sodium_crypto_secretstream_xchacha20poly1305_push(
                $state,
                $chunk,
                '',
                $final ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL
                    : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE
            );
            $frame = pack('N', strlen($cipher)) . $cipher;
            if (fwrite($destination, $frame) !== strlen($frame)) {
                throw new RuntimeException('Cannot write encrypted frame.');
            }
            if ($final) {
                break;
            }
        }
    } else {
        $prefix = fread($source, strlen($magic) + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
        if ($prefix === false || strlen($prefix) !== strlen($magic) + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES
            || substr($prefix, 0, strlen($magic)) !== $magic) {
            throw new RuntimeException('Invalid sealed artifact header.');
        }
        $header = substr($prefix, strlen($magic));
        $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $key);
        $finished = false;
        do {
            $lenBytes = fread($source, 4);
            if ($lenBytes === false || strlen($lenBytes) !== 4) {
                throw new RuntimeException('Truncated encrypted artifact frame.');
            }
            $len = unpack('N', $lenBytes)[1];
            if ($len < SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES
                || $len > 65536 + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES) {
                throw new RuntimeException('Invalid encrypted frame length.');
            }
            $cipher = '';
            while (strlen($cipher) < $len) {
                $part = fread($source, $len - strlen($cipher));
                if (!is_string($part) || $part === '') {
                    throw new RuntimeException('Truncated encrypted artifact payload.');
                }
                $cipher .= $part;
            }
            $decrypted = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $cipher);
            if ($decrypted === false) {
                throw new RuntimeException('Sealed artifact authentication failed.');
            }
            [$plain, $tag] = $decrypted;
            if (fwrite($destination, $plain) !== strlen($plain)) {
                throw new RuntimeException('Cannot write decrypted archive.');
            }
            $finished = $tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL;
        } while (!$finished);
        if (fread($source, 1) !== '') {
            throw new RuntimeException('Unexpected trailing sealed data.');
        }
    }
    fclose($source);
    fclose($destination);
    sodium_memzero($key);
} catch (Throwable $error) {
    fclose($source);
    fclose($destination);
    @unlink($output);
    sodium_memzero($key);
    fwrite(STDERR, 'Sealed artifact rejected: ' . $error->getMessage() . "\n");
    exit(1);
}
