<?php

declare(strict_types=1);

namespace PrivacyEvidence\Source\Ipb;

use PrivacyEvidence\Source\RemoteSourceProvider;
use PrivacyEvidence\Source\SourceAdapter;

final readonly class IpbAnuarioProvider implements RemoteSourceProvider
{
    public function __construct(private IpbAnuarioClient $client = new IpbAnuarioClient())
    {
    }

    public function providerId(): string
    {
        return 'ipb-icalvinus';
    }

    public function mediaType(): string
    {
        return 'text/html';
    }

    /**
     * @return array<string,scalar|null>
     */
    public function provenance(): array
    {
        return [
            'endpoint' => IpbAnuarioClient::ENDPOINT,
            'method' => 'POST',
            'organization' => 'Igreja Presbiteriana do Brasil',
        ];
    }

    public function fetch(): string
    {
        return $this->client->download();
    }

    public function openSnapshot(string $path): SourceAdapter
    {
        return IpbAnuarioSource::fromFile($path);
    }
}
