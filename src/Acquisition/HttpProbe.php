<?php

declare(strict_types=1);

namespace PrivacyEvidence\Acquisition;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class HttpProbe
{
    private HttpClientInterface $client;

    public function __construct(?HttpClientInterface $client = null)
    {
        $this->client = $client ?? new NoPrivateNetworkHttpClient(HttpClient::create([
            'max_redirects' => 8,
            'timeout' => 15.0,
            'max_duration' => 30.0,
            'max_connect_duration' => 5.0,
            'verify_peer' => true,
            'verify_host' => true,
        ]));
    }

    public function probe(string $url): HttpProbeResult
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false || !in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            return new HttpProbeResult($url, null, null, null, failure: ProbeFailure::InvalidUrl);
        }

        try {
            $response = $this->client->request('GET', $url, [
                'headers' => ['User-Agent' => 'PrivacyEvidence/0.x research crawler'],
                'extra' => ['trace_content' => false],
            ]);

            $status = $response->getStatusCode();
            $info = $response->getInfo();
            $headers = $response->getHeaders(false);
            $redirects = [];
            foreach (($info['redirect_url'] ?? []) as $redirect) {
                if (is_string($redirect)) {
                    $redirects[] = $redirect;
                }
            }

            return new HttpProbeResult(
                requestedUrl: $url,
                finalUrl: is_string($info['url'] ?? null) ? $info['url'] : $url,
                statusCode: $status,
                contentType: $headers['content-type'][0] ?? null,
                redirectChain: $redirects,
            );
        } catch (TransportExceptionInterface $e) {
            return new HttpProbeResult(
                requestedUrl: $url,
                finalUrl: null,
                statusCode: null,
                contentType: null,
                failure: $this->classifyTransportFailure($e->getMessage()),
                failureDetail: $e->getMessage(),
            );
        }
    }

    private function classifyTransportFailure(string $message): ProbeFailure
    {
        $lower = strtolower($message);

        if (str_contains($lower, 'private') || str_contains($lower, 'reserved')) {
            return ProbeFailure::PrivateNetwork;
        }
        if (str_contains($lower, 'resolve') || str_contains($lower, 'dns')) {
            return ProbeFailure::Dns;
        }
        if (str_contains($lower, 'certificate') || str_contains($lower, 'ssl') || str_contains($lower, 'tls')) {
            return ProbeFailure::Tls;
        }
        if (str_contains($lower, 'timed out') || str_contains($lower, 'timeout')) {
            return ProbeFailure::Timeout;
        }

        return ProbeFailure::Transport;
    }
}
