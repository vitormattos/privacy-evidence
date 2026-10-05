<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Unit\Acquisition;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Acquisition\HttpProbe;
use PrivacyEvidence\Acquisition\ProbeFailure;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class HttpProbeTest extends TestCase
{
    public function testRejectsUnsupportedSchemeBeforeNetwork(): void
    {
        $result = (new HttpProbe(new MockHttpClient()))->probe('file:///etc/passwd');

        self::assertSame(ProbeFailure::InvalidUrl, $result->failure);
    }

    public function testBlockedUnresolvableHostIsClassifiedAsDnsFailure(): void
    {
        $client = new MockHttpClient(new MockResponse('', [
            'error' => 'Host "does-not-exist.invalid" is blocked for "https://does-not-exist.invalid/".',
        ]));

        $result = (new HttpProbe($client))->probe('https://does-not-exist.invalid/');

        self::assertSame(ProbeFailure::Dns, $result->failure);
        self::assertSame('failed', $result->dnsState);
        self::assertSame('Host has no resolvable A/AAAA address.', $result->failureDetail);
    }

    public function testBlockedResolvablePrivateHostRemainsPrivateNetworkFailure(): void
    {
        $client = new MockHttpClient(new MockResponse('', [
            'error' => 'Host "localhost" is blocked for "http://localhost/".',
        ]));

        $result = (new HttpProbe($client))->probe('http://localhost/');

        self::assertSame(ProbeFailure::PrivateNetwork, $result->failure);
    }

    public function testClassifiesConnectionRefusedSeparately(): void
    {
        $client = new MockHttpClient(new MockResponse('', [
            'error' => 'Failed to connect to example.test port 443: Connection refused',
        ]));

        $result = (new HttpProbe($client))->probe('https://example.test/');

        self::assertSame(ProbeFailure::ConnectionRefused, $result->failure);
    }

    public function testRedirectLimitIsDeterministicFailure(): void
    {
        $client = new MockHttpClient(static function (string $method, string $url): MockResponse {
            return new MockResponse('', [
                'http_code' => 302,
                'response_headers' => ['location: ' . $url],
            ]);
        });

        $result = (new HttpProbe($client, maxRedirects: 1))->probe('https://example.test/');

        self::assertSame(ProbeFailure::RedirectLimit, $result->failure);
        self::assertSame('redirect_limit', $result->transportState);
    }

    public function testRecordsSuccessfulHttpObservation(): void
    {
        $client = new MockHttpClient(new MockResponse('ok', [
            'http_code' => 200,
            'response_headers' => ['content-type: text/html'],
        ]), 'https://example.test');

        $result = (new HttpProbe($client))->probe('https://example.test/');

        self::assertTrue($result->succeeded());
        self::assertSame(200, $result->statusCode);
        self::assertSame('text/html', $result->contentType);
    }
}
