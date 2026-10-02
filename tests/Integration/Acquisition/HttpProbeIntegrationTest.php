<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Integration\Acquisition;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Acquisition\HttpProbe;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Process\Process;

final class HttpProbeIntegrationTest extends TestCase
{
    private ?Process $server = null;

    public function testRecordsRedirectChainAndFinalHttpObservationAgainstControlledServer(): void
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
        if ($socket === false) {
            self::fail(sprintf('Unable to allocate local test port: %s (%d)', $errorMessage, $errorCode));
        }

        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        if (!is_string($address)) {
            self::fail('Unable to determine local test server address.');
        }

        $separator = strrpos($address, ':');
        if ($separator === false) {
            self::fail('Unexpected local server address.');
        }
        $port = substr($address, $separator + 1);

        $router = __DIR__ . '/../../Fixtures/http/router.php';
        $this->server = new Process([PHP_BINARY, '-S', '127.0.0.1:' . $port, $router]);
        $this->server->start();

        $base = 'http://127.0.0.1:' . $port;
        $this->waitUntilReady($base . '/final');

        $result = (new HttpProbe(HttpClient::create(['timeout' => 2.0])))->probe($base . '/redirect');

        self::assertTrue($result->succeeded());
        self::assertSame(200, $result->statusCode);
        self::assertSame($base . '/final', $result->finalUrl);
        self::assertSame([$base . '/final'], $result->redirectChain);
        self::assertSame('resolved', $result->dnsState);
        self::assertSame('not_applicable', $result->tlsState);
        self::assertSame('connected', $result->transportState);
    }

    protected function tearDown(): void
    {
        if ($this->server !== null) {
            $this->server->stop(1.0);
            $this->server = null;
        }
    }

    private function waitUntilReady(string $url): void
    {
        $deadline = microtime(true) + 3.0;
        do {
            $headers = @get_headers($url);
            if (is_array($headers)) {
                return;
            }
            usleep(50_000);
        } while (microtime(true) < $deadline);

        self::fail('Controlled HTTP fixture server did not become ready.');
    }
}
