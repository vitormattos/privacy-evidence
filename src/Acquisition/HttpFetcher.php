<?php

declare(strict_types=1);

namespace PrivacyEvidence\Acquisition;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class HttpFetcher
{
    private HttpClientInterface $client;

    public function __construct(
        ?HttpClientInterface $client = null,
        private readonly HttpProbe $probe = new HttpProbe(),
    ) {
        $this->client = $client ?? new NoPrivateNetworkHttpClient(HttpClient::create([
            'timeout' => 20.0,
            'max_duration' => 45.0,
            'max_connect_duration' => 5.0,
            'verify_peer' => true,
            'verify_host' => true,
        ]));
    }

    public function fetch(
        string $resourceId,
        string $url,
        int $maxBytes = 2_000_000,
    ): FetchedDocument {
        if ($maxBytes <= 0) {
            throw new \InvalidArgumentException('maxBytes must be positive.');
        }

        $probe = $this->probe->probe($url);
        $fallback = null;

        if (
            !$probe->succeeded()
            && $probe->failure === ProbeFailure::Dns
            && $probe->dnsState === 'not_found'
        ) {
            $withoutWww = $this->withoutWww($url);
            if ($withoutWww !== null) {
                $fallbackProbe = $this->probe->probe($withoutWww);
                if ($fallbackProbe->succeeded()) {
                    $probe = $fallbackProbe;
                    $fallback = ['reason' => 'www_dns_fallback', 'url' => $withoutWww];
                }
            }
        }

        if (
            !$probe->succeeded()
            && parse_url($url, PHP_URL_SCHEME) === 'http'
            && in_array(
                $probe->failure,
                [ProbeFailure::Timeout, ProbeFailure::ConnectionRefused, ProbeFailure::Transport],
                true,
            )
        ) {
            $httpsUrl = preg_replace('~^http://~i', 'https://', $url, 1);
            if (is_string($httpsUrl) && $httpsUrl !== $url) {
                $fallbackProbe = $this->probe->probe($httpsUrl);
                if ($fallbackProbe->succeeded()) {
                    $probe = $fallbackProbe;
                    $fallback = ['reason' => 'https_transport_fallback', 'url' => $httpsUrl];
                }
            }
        }

        if (!$probe->succeeded() || $probe->finalUrl === null || $probe->statusCode === null) {
            $failure = $probe->failure ?? ProbeFailure::Transport;
            $retryable = match ($failure) {
                ProbeFailure::Dns => $probe->dnsState !== 'not_found',
                ProbeFailure::Timeout,
                ProbeFailure::ConnectionRefused,
                ProbeFailure::Transport => true,
                default => false,
            };

            throw new AcquisitionException(
                url: $url,
                category: $failure->value,
                retryable: $retryable,
                message: sprintf(
                    'Unable to fetch %s: %s%s',
                    $url,
                    $failure->value,
                    $probe->failureDetail === null ? '' : ' (' . $probe->failureDetail . ')',
                ),
            );
        }

        $response = $this->client->request('GET', $probe->finalUrl, [
            'max_redirects' => 0,
            'headers' => [
                'User-Agent' => 'PrivacyEvidence/0.x research crawler',
            ],
        ]);
        // Explicitly consume the status first so 4xx/5xx responses remain
        // observable HTTP results instead of becoming retryable transport failures.
        $response->getStatusCode();

        $body = '';
        $truncated = false;

        foreach ($this->client->stream($response) as $chunk) {
            if ($chunk->isTimeout()) {
                throw new AcquisitionException(
                    url: $probe->finalUrl,
                    category: ProbeFailure::Timeout->value,
                    retryable: true,
                    message: 'HTTP body read timed out.',
                );
            }

            if ($chunk->isFirst() || $chunk->isLast()) {
                continue;
            }

            $content = $chunk->getContent();
            $remaining = $maxBytes - strlen($body);
            if ($remaining <= 0) {
                $truncated = true;
                $response->cancel();
                break;
            }

            if (strlen($content) > $remaining) {
                $body .= substr($content, 0, $remaining);
                $truncated = true;
                $response->cancel();
                break;
            }

            $body .= $content;
        }

        $headers = $response->getHeaders(false);
        $retryAfterMs = $this->retryAfterMs($headers['retry-after'][0] ?? null);

        return new FetchedDocument(
            resourceId: $resourceId,
            requestedUrl: $url,
            finalUrl: $probe->finalUrl,
            statusCode: $probe->statusCode,
            mediaType: $this->mediaType($headers['content-type'][0] ?? 'application/octet-stream'),
            body: $body,
            fetchedAt: gmdate(DATE_ATOM),
            acquisitionMode: 'http',
            truncated: $truncated,
            metadata: array_filter(
                [
                    'retryAfterMs' => $retryAfterMs,
                    'transportFallback' => $fallback,
                ],
                static fn (mixed $value): bool => $value !== null,
            ),
        );
    }

    private function withoutWww(string $url): ?string
    {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $host = strtolower($parts['host']);
        if (!str_starts_with($host, 'www.')) {
            return null;
        }

        $replacementHost = substr($host, 4);
        if ($replacementHost === '') {
            return null;
        }

        $authority = $replacementHost;
        if (isset($parts['port'])) {
            $authority .= ':' . $parts['port'];
        }

        $path = $parts['path'] ?? '/';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';

        return $parts['scheme'] . '://' . $authority . $path . $query;
    }

    private function retryAfterMs(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);
        if (ctype_digit($value)) {
            return min(120_000, (int) $value * 1000);
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return min(120_000, max(0, ($timestamp - time()) * 1000));
    }

    private function mediaType(string $value): string
    {
        return trim(explode(';', $value, 2)[0]);
    }
}
