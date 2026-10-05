<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Unit\Acquisition;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Acquisition\HttpProbe;
use PrivacyEvidence\Acquisition\ProbeFailure;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class HttpProbeBlockedHostTest extends TestCase
{
    public function testBlockedPrivateAddressIsClassifiedAsPrivateNetworkFailure(): void
    {
        $response = new MockResponse('', [
            'error' => 'Host "10.0.0.1" is blocked for "http://10.0.0.1/".',
        ]);
        $probe = new HttpProbe(new MockHttpClient($response));

        $result = $probe->probe('http://10.0.0.1/');

        self::assertSame(ProbeFailure::PrivateNetwork, $result->failure);
        self::assertSame('failed', $result->transportState);
    }

    public function testBlockedUnresolvableHostnameIsClassifiedAsDnsFailure(): void
    {
        $response = new MockResponse('', [
            'error' => 'Host "blocked.invalid" is blocked for "http://blocked.invalid/".',
        ]);
        $probe = new HttpProbe(new MockHttpClient($response));

        $result = $probe->probe('http://blocked.invalid/');

        self::assertSame(ProbeFailure::Dns, $result->failure);
        self::assertSame('failed', $result->dnsState);
        self::assertSame('Host has no resolvable A/AAAA address.', $result->failureDetail);
    }
}
