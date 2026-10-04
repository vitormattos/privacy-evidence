<?php

declare(strict_types=1);

namespace PrivacyEvidence\Source;

final class ResourceClassifier
{
    public const VERSION = '1.1.0';

    /** @var list<string> */
    private const SOCIAL_HOSTS = [
        'facebook.com', 'instagram.com', 'linkedin.com', 'x.com', 'twitter.com', 'tiktok.com',
    ];

    /** @var list<string> */
    private const VIDEO_HOSTS = ['youtube.com', 'youtu.be', 'vimeo.com'];

    /** @var list<string> */
    private const LINK_AGGREGATORS = ['linktr.ee', 'bio.site', 'beacons.ai'];

    /** @var list<string> */
    private const THIRD_PARTY_HOSTS = [
        'sites.google.com', 'wordpress.com', 'wixsite.com', 'weebly.com', 'notion.site', 'carrd.co',
        'blogspot.com', 'blogspot.com.br',
    ];

    public function classify(string $sourceValue, ?string $normalizedUrl): ResourceType
    {
        return $this->classifyDetailed($sourceValue, $normalizedUrl)->type;
    }

    public function classifyDetailed(string $sourceValue, ?string $normalizedUrl): ResourceClassification
    {
        if ($normalizedUrl === null) {
            return trim($sourceValue) === ''
                ? new ResourceClassification(ResourceType::Unknown, 'empty_source', self::VERSION, 1.0)
                : new ResourceClassification(ResourceType::Malformed, 'normalization_failed', self::VERSION, 1.0);
        }

        $host = parse_url($normalizedUrl, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return new ResourceClassification(ResourceType::Unknown, 'host_unavailable', self::VERSION, 0.5);
        }
        $host = strtolower($host);

        if ($this->looksLikeSpoofedKnownHost($host)) {
            return new ResourceClassification(ResourceType::Malformed, 'spoofed_known_host_prefix', self::VERSION, 1.0);
        }

        if ($this->matchesHost($host, self::SOCIAL_HOSTS)) {
            return new ResourceClassification(ResourceType::SocialNetwork, 'known_social_host', self::VERSION, 1.0);
        }
        if ($this->matchesHost($host, self::VIDEO_HOSTS)) {
            return new ResourceClassification(ResourceType::VideoPlatform, 'known_video_host', self::VERSION, 1.0);
        }
        if ($this->matchesHost($host, self::LINK_AGGREGATORS)) {
            return new ResourceClassification(ResourceType::LinkAggregator, 'known_link_aggregator', self::VERSION, 1.0);
        }
        if ($this->matchesHost($host, self::THIRD_PARTY_HOSTS)) {
            return new ResourceClassification(ResourceType::ThirdPartyHostedPage, 'known_hosted_page', self::VERSION, 0.95);
        }

        return new ResourceClassification(ResourceType::InstitutionalWebsite, 'default_web_host', self::VERSION, 0.8);
    }

    private function looksLikeSpoofedKnownHost(string $host): bool
    {
        $host = preg_replace('/^www\./', '', $host) ?? $host;
        $knownHosts = [
            ...self::SOCIAL_HOSTS,
            ...self::VIDEO_HOSTS,
            ...self::LINK_AGGREGATORS,
        ];

        foreach ($knownHosts as $candidate) {
            if (
                str_starts_with($host, $candidate . '.')
                && !$this->matchesHost($host, [$candidate])
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $candidates
     */
    private function matchesHost(string $host, array $candidates): bool
    {
        foreach ($candidates as $candidate) {
            if ($host === $candidate || str_ends_with($host, '.' . $candidate)) {
                return true;
            }
        }

        return false;
    }
}
