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
        if (!$probe->succeeded() || $probe->finalUrl === null || $probe->statusCode === null) {
            throw new \RuntimeException(
                sprintf(
                    'Unable to fetch %s: %s',
                    $url,
                    $probe->failure?->value ?? 'probe_failed',
                ),
            );
        }

        $response = $this->client->request('GET', $probe->finalUrl, [
            'max_redirects' => 0,
            'headers' => [
                'User-Agent' => 'PrivacyEvidence/0.x research crawler',
            ],
        ]);

        $body = '';
        $truncated = false;

        foreach ($this->client->stream($response) as $chunk) {
            if ($chunk->isTimeout()) {
                throw new \RuntimeException('HTTP body read timed out.');
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
        );
    }

    private function mediaType(string $value): string
    {
        return trim(explode(';', $value, 2)[0]);
    }
}
