<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Unit\Acquisition;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Acquisition\HttpProbe;
use PrivacyEvidence\Acquisition\ProbeFailure;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

final class HttpProbeBlockedHostTest extends TestCase
{
    public function testBlockedHostIsClassifiedAsPrivateNetworkFailure(): void
    {
        $response = new MockResponse('', [
            'error' => 'Host "blocked.example" is blocked for "http://blocked.example/".',
        ]);
        $probe = new HttpProbe(new MockHttpClient($response));

        $result = $probe->probe('http://blocked.example/');

        self::assertSame(ProbeFailure::PrivateNetwork, $result->failure);
        self::assertSame('failed', $result->transportState);
    }
}
