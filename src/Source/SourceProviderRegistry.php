<?php

declare(strict_types=1);

namespace PrivacyEvidence\Source;

use PrivacyEvidence\Source\Ipb\IpbAnuarioProvider;

final readonly class SourceProviderRegistry
{
    /**
     * @param array<string,RemoteSourceProvider> $providers
     */
    private function __construct(private array $providers)
    {
    }

    /**
     * @param iterable<RemoteSourceProvider> $providers
     */
    public static function fromProviders(iterable $providers): self
    {
        $indexed = [];

        foreach ($providers as $provider) {
            $id = $provider->providerId();
            if ($id === '' || isset($indexed[$id])) {
                throw new \InvalidArgumentException('Source provider ids must be unique and non-empty.');
            }

            $indexed[$id] = $provider;
        }

        ksort($indexed);

        return new self($indexed);
    }

    public static function defaults(): self
    {
        return self::fromProviders([
            new IpbAnuarioProvider(),
        ]);
    }

    public function get(string $providerId): RemoteSourceProvider
    {
        return $this->providers[$providerId]
            ?? throw new \InvalidArgumentException('Unknown source provider: ' . $providerId);
    }

    /**
     * @return list<string>
     */
    public function ids(): array
    {
        return array_keys($this->providers);
    }
}
