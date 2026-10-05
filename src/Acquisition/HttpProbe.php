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

    public function __construct(
        ?HttpClientInterface $client = null,
        private readonly int $maxRedirects = 8,
    ) {
        $this->client = $client ?? new NoPrivateNetworkHttpClient(HttpClient::create([
            'timeout' => 15.0,
            'max_duration' => 30.0,
            'max_connect_duration' => 5.0,
            'verify_peer' => true,
            'verify_host' => true,
        ]));
    }

    public function probe(string $url): HttpProbeResult
    {
        if (!$this->isHttpUrl($url)) {
            return new HttpProbeResult(
                $url,
                null,
                null,
                null,
                failure: ProbeFailure::InvalidUrl,
            );
        }

        $current = $url;
        $redirectChain = [];

        for ($redirects = 0; $redirects <= $this->maxRedirects; $redirects++) {
            try {
                $response = $this->client->request('GET', $current, [
                    'max_redirects' => 0,
                    'headers' => [
                        'User-Agent' => 'PrivacyEvidence/0.x research crawler',
                    ],
                    'extra' => [
                        'trace_content' => false,
                    ],
                ]);

                $status = $response->getStatusCode();
                $headers = $response->getHeaders(false);

                if ($status >= 300 && $status < 400 && isset($headers['location'][0])) {
                    if ($redirects === $this->maxRedirects) {
                        return new HttpProbeResult(
                            requestedUrl: $url,
                            finalUrl: $current,
                            statusCode: $status,
                            contentType: $headers['content-type'][0] ?? null,
                            redirectChain: $redirectChain,
                            dnsState: 'resolved',
                            tlsState: $this->tlsStateForSuccess($current),
                            transportState: 'redirect_limit',
                            failure: ProbeFailure::RedirectLimit,
                            failureDetail: 'Maximum redirect count exceeded.',
                        );
                    }

                    $next = $this->resolveRedirect($current, $headers['location'][0]);
                    if (!$this->isHttpUrl($next)) {
                        return new HttpProbeResult(
                            requestedUrl: $url,
                            finalUrl: $current,
                            statusCode: $status,
                            contentType: $headers['content-type'][0] ?? null,
                            redirectChain: $redirectChain,
                            dnsState: 'resolved',
                            tlsState: $this->tlsStateForSuccess($current),
                            transportState: 'redirect_invalid',
                            failure: ProbeFailure::InvalidUrl,
                            failureDetail: 'Redirect target is not an HTTP/HTTPS URL.',
                        );
                    }

                    $redirectChain[] = $next;
                    $current = $next;
                    continue;
                }

                return new HttpProbeResult(
                    requestedUrl: $url,
                    finalUrl: $current,
                    statusCode: $status,
                    contentType: $headers['content-type'][0] ?? null,
                    redirectChain: $redirectChain,
                    dnsState: 'resolved',
                    tlsState: $this->tlsStateForSuccess($current),
                    transportState: 'connected',
                );
            } catch (TransportExceptionInterface $e) {
                $failure = $this->classifyTransportFailure($e->getMessage());

                return new HttpProbeResult(
                    requestedUrl: $url,
                    finalUrl: $current,
                    statusCode: null,
                    contentType: null,
                    redirectChain: $redirectChain,
                    dnsState: $failure === ProbeFailure::Dns ? 'failed' : 'unknown',
                    tlsState: $failure === ProbeFailure::Tls ? 'failed' : 'unknown',
                    transportState: 'failed',
                    failure: $failure,
                    failureDetail: $e->getMessage(),
                );
            }
        }

        throw new \LogicException('Redirect loop exhausted unexpectedly.');
    }

    private function isHttpUrl(string $url): bool
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);

        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array($scheme, ['http', 'https'], true);
    }

    private function tlsStateForSuccess(string $url): string
    {
        return parse_url($url, PHP_URL_SCHEME) === 'https' ? 'verified' : 'not_applicable';
    }

    private function resolveRedirect(string $baseUrl, string $location): string
    {
        if (preg_match('~^https?://~i', $location) === 1) {
            return $location;
        }

        $base = parse_url($baseUrl);
        if ($base === false || !isset($base['scheme'], $base['host'])) {
            return $location;
        }

        $origin = $base['scheme'] . '://' . $base['host'];
        if (isset($base['port'])) {
            $origin .= ':' . $base['port'];
        }

        if (str_starts_with($location, '/')) {
            return $origin . $location;
        }

        $path = $base['path'] ?? '/';
        $directory = rtrim(str_replace('\\', '/', dirname($path)), '/');

        return $origin . ($directory === '' ? '' : $directory) . '/' . $location;
    }

    private function classifyTransportFailure(string $message): ProbeFailure
    {
        $lower = strtolower($message);

        if (
            str_contains($lower, 'private')
            || str_contains($lower, 'reserved')
            || str_contains($lower, ' is blocked')
        ) {
            return ProbeFailure::PrivateNetwork;
        }
        if (str_contains($lower, 'resolve') || str_contains($lower, 'dns')) {
            return ProbeFailure::Dns;
        }
        if (
            str_contains($lower, 'certificate')
            || str_contains($lower, 'ssl')
            || str_contains($lower, 'tls')
        ) {
            return ProbeFailure::Tls;
        }
        if (str_contains($lower, 'timed out') || str_contains($lower, 'timeout')) {
            return ProbeFailure::Timeout;
        }
        if (
            str_contains($lower, 'connection refused')
            || str_contains($lower, 'failed to connect')
            || str_contains($lower, 'could not connect')
            || str_contains($lower, "couldn't connect")
        ) {
            return ProbeFailure::ConnectionRefused;
        }

        return ProbeFailure::Transport;
    }
}
