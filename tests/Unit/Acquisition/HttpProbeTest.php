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
