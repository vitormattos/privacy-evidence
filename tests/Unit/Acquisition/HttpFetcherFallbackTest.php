<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Unit\Acquisition;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Acquisition\HttpFetcher;
use PrivacyEvidence\Acquisition\HttpProbe;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class HttpFetcherFallbackTest extends TestCase
{
    public function testFallsBackFromUnresolvableWwwToApexAndPreservesRequestedUrl(): void
    {
        $responses = [
            new MockResponse('', [
                'error' => 'Host "www.example.test" is blocked for "http://www.example.test/".',
            ]),
            new MockResponse('', [
                'http_code' => 200,
                'response_headers' => ['content-type: text/html'],
            ]),
            new MockResponse('<html>ok</html>', [
                'http_code' => 200,
                'response_headers' => ['content-type: text/html'],
            ]),
        ];
        $client = new MockHttpClient($responses);
        $fetcher = new HttpFetcher($client, new HttpProbe($client));

        $document = $fetcher->fetch('site-1', 'http://www.example.test/');

        self::assertSame('http://www.example.test/', $document->requestedUrl);
        self::assertSame('http://example.test/', $document->finalUrl);
        self::assertSame(
            [
                'reason' => 'www_dns_fallback',
                'url' => 'http://example.test/',
            ],
            $document->metadata['transportFallback'] ?? null,
        );
    }

    public function testFallsBackFromFailedHttpTransportToHttps(): void
    {
        $responses = [
            new MockResponse('', [
                'error' => 'Failed to connect to example.test port 80: Connection refused',
            ]),
            new MockResponse('', [
                'http_code' => 200,
                'response_headers' => ['content-type: text/html'],
            ]),
            new MockResponse('<html>secure</html>', [
                'http_code' => 200,
                'response_headers' => ['content-type: text/html'],
            ]),
        ];
        $client = new MockHttpClient($responses);
        $fetcher = new HttpFetcher($client, new HttpProbe($client));

        $document = $fetcher->fetch('site-1', 'http://example.test/');

        self::assertSame('https://example.test/', $document->finalUrl);
        self::assertSame('https_transport_fallback', $document->metadata['transportFallback']['reason'] ?? null);
    }

    public function testPreservesRetryAfterAsBoundedMilliseconds(): void
    {
        $responses = [
            new MockResponse('', [
                'http_code' => 429,
                'response_headers' => ['content-type: text/html'],
            ]),
            new MockResponse('slow down', [
                'http_code' => 429,
                'response_headers' => [
                    'content-type: text/html',
                    'retry-after: 5',
                ],
            ]),
        ];
        $client = new MockHttpClient($responses);
        $fetcher = new HttpFetcher($client, new HttpProbe($client));

        $document = $fetcher->fetch('site-1', 'https://example.test/');

        self::assertSame(429, $document->statusCode);
        self::assertSame(5000, $document->metadata['retryAfterMs'] ?? null);
    }
}
