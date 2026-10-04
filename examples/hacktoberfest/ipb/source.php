#!/usr/bin/env php
<?php
// SPDX-FileCopyrightText: 2026 Vitor Mattos
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

const IPB_ENDPOINT = 'https://www.icalvinus.app/consulta_ipb/anuario.php';
const PRODUCER_ID = 'privacy-evidence-example-ipb-directory';
const PRODUCER_VERSION = '1.0.0';

function usage(): never
{
    fwrite(STDERR, <<<TXT
Usage:
  php examples/hacktoberfest/ipb/source.php fetch <snapshot.html>
  php examples/hacktoberfest/ipb/source.php extract <snapshot.html> <dataset.csv>

The script is an example external dataset producer. Privacy Evidence itself only
consumes canonical CSV/JSON datasets and has no IPB-specific provider API.

TXT);
    exit(2);
}

function fetchSnapshot(string $path): void
{
    $client = new NoPrivateNetworkHttpClient(HttpClient::create([
        'timeout' => 30.0,
        'verify_peer' => true,
        'verify_host' => true,
    ]));

    $response = $client->request('POST', IPB_ENDPOINT, [
        'body' => [
            'buscar' => 'anu_igrejas',
            'tipo' => '1',
        ],
        'headers' => [
            'User-Agent' => 'PrivacyEvidence example source producer/1.0',
        ],
    ]);

    $status = $response->getStatusCode();
    if ($status < 200 || $status >= 300) {
        throw new RuntimeException(sprintf('IPB directory returned HTTP %d.', $status));
    }

    $html = $response->getContent();
    $directory = dirname($path);
    if ($directory !== '.' && !is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create snapshot directory.');
    }

    if (file_put_contents($path, $html, LOCK_EX) === false) {
        throw new RuntimeException('Unable to write source snapshot.');
    }

    $metadata = [
        'schemaVersion' => '1.0.0',
        'producer' => PRODUCER_ID,
        'producerVersion' => PRODUCER_VERSION,
        'source' => [
            'endpoint' => IPB_ENDPOINT,
            'method' => 'POST',
            'mediaType' => 'text/html',
            'sha256' => hash('sha256', $html),
            'bytes' => strlen($html),
        ],
    ];

    file_put_contents(
        $path . '.provenance.json',
        json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL,
        LOCK_EX,
    );

    fwrite(STDOUT, json_encode(
        $metadata + ['snapshot' => $path],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
    ) . PHP_EOL);
}

/**
 * @return array{
 *   name:string,presbytery:string,address:string,municipality:string,state:string,
 *   postal_code:string,phone:string,email:string,website:string
 * }
 */
function parseChurch(Crawler $divs): array
{
    $empty = [
        'name' => '',
        'presbytery' => '',
        'address' => '',
        'municipality' => '',
        'state' => '',
        'postal_code' => '',
        'phone' => '',
        'email' => '',
        'website' => '',
    ];

    $churchDiv = $divs->reduce(
        static fn (Crawler $node): bool => $node->attr('style') === 'padding: 15px;',
    )->first();

    if ($churchDiv->count() === 0) {
        return $empty;
    }

    $name = trim($churchDiv->filter('big > b')->text(''));
    $presbytery = trim($churchDiv->filter('b')->eq(1)->text(''));
    $address = '';
    $municipality = '';
    $state = '';
    $postalCode = '';
    $phone = '';
    $email = '';
    $website = '';
    $churchHtml = $churchDiv->html('');

    if (preg_match('/<br\s*\/?>\s*<br\s*\/?>\s*(?<address>[^<]+)<br\s*\/?>/i', $churchHtml, $match) === 1) {
        $address = trim($match['address']);

        if (preg_match('/ (?<municipality>[^\-\/\n]+)\s*\/\s*(?<state>[A-Z]{2})$/', $address, $place) === 1) {
            $municipality = trim($place['municipality']);
            $state = trim($place['state']);
            $address = trim((string) preg_replace('/\s*' . preg_quote($place[0], '/') . '$/', '', $address));
            $address = trim((string) preg_replace('/\s*-\s*$/', '', $address));
        }
    }

    if (preg_match('/\bCEP:\s*(?<postal>[\d.\-]+)/i', $churchHtml, $match) === 1) {
        $postalCode = trim($match['postal']);
    }

    foreach ($churchDiv->filter('a[href]') as $linkNode) {
        $link = new Crawler($linkNode);
        $href = trim((string) $link->attr('href'));
        if ($href === '') {
            continue;
        }

        if (str_starts_with($href, 'tel:')) {
            $phone = trim((string) preg_replace('/[^\d\s+\-()]/', '', str_replace('%20', ' ', substr($href, 4))));
        } elseif (str_starts_with($href, 'mailto:')) {
            $email = trim(substr($href, 7));
        } elseif (preg_match('~^(?:https?://|www\.)~i', $href) === 1) {
            $website = $href;
        }
    }

    return [
        'name' => $name,
        'presbytery' => $presbytery,
        'address' => $address,
        'municipality' => $municipality,
        'state' => $state,
        'postal_code' => $postalCode,
        'phone' => $phone,
        'email' => $email,
        'website' => $website,
    ];
}

function extractDataset(string $snapshot, string $output): void
{
    $html = file_get_contents($snapshot);
    if (!is_string($html)) {
        throw new RuntimeException('Unable to read source snapshot.');
    }

    $crawler = new Crawler($html);
    $blocks = $crawler->filter(
        'div[style^="font-family: Helvetica, Arial; background-color: rgba(0,0,0,0.05);"]',
    );

    $directory = dirname($output);
    if ($directory !== '.' && !is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create dataset output directory.');
    }

    $handle = fopen($output, 'wb');
    if ($handle === false) {
        throw new RuntimeException('Unable to create dataset CSV.');
    }

    $count = 0;
    try {
        fputcsv(
            $handle,
            ['id', 'name', 'url', 'presbytery', 'address', 'municipality', 'state', 'postal_code', 'phone', 'email'],
            ',',
            '"',
            '',
        );

        foreach ($blocks as $index => $blockNode) {
            $block = new Crawler($blockNode);
            $divs = $block->filter('div');
            if ($divs->count() === 0) {
                continue;
            }

            $church = parseChurch($divs);
            if ($church['name'] === '') {
                continue;
            }

            $stableId = hash(
                'sha256',
                implode('|', [
                    $church['name'],
                    $church['presbytery'],
                    $church['municipality'],
                    $church['state'],
                    (string) $index,
                ]),
            );

            fputcsv(
                $handle,
                [
                    'ipb-' . substr($stableId, 0, 20),
                    $church['name'],
                    $church['website'],
                    $church['presbytery'],
                    $church['address'],
                    $church['municipality'],
                    $church['state'],
                    $church['postal_code'],
                    $church['phone'],
                    $church['email'],
                ],
                ',',
                '"',
                '',
            );
            ++$count;
        }
    } finally {
        fclose($handle);
    }

    $datasetSha256 = hash_file('sha256', $output);
    if (!is_string($datasetSha256)) {
        throw new RuntimeException('Unable to hash generated dataset.');
    }

    $provenance = [
        'schemaVersion' => '1.0.0',
        'producer' => PRODUCER_ID,
        'producerVersion' => PRODUCER_VERSION,
        'inputSnapshot' => [
            'path' => $snapshot,
            'sha256' => hash('sha256', $html),
        ],
        'dataset' => [
            'path' => $output,
            'format' => 'privacy-evidence-csv-v1',
            'records' => $count,
            'sha256' => $datasetSha256,
        ],
    ];

    file_put_contents(
        $output . '.provenance.json',
        json_encode($provenance, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL,
        LOCK_EX,
    );

    fwrite(STDOUT, json_encode(
        $provenance,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
    ) . PHP_EOL);
}

try {
    $command = $argv[1] ?? null;

    if ($command === 'fetch' && isset($argv[2]) && count($argv) === 3) {
        fetchSnapshot($argv[2]);
        exit(0);
    }

    if ($command === 'extract' && isset($argv[2], $argv[3]) && count($argv) === 4) {
        extractDataset($argv[2], $argv[3]);
        exit(0);
    }

    usage();
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
