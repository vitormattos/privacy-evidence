<?php

declare(strict_types=1);

namespace PrivacyEvidence\Source;

final class ResourceClassifier
{
    /** @var list<string> */
    private const SOCIAL_HOSTS = [
        'facebook.com',
        'instagram.com',
        'linkedin.com',
        'x.com',
        'twitter.com',
        'tiktok.com',
    ];

    /** @var list<string> */
    private const VIDEO_HOSTS = [
        'youtube.com',
        'youtu.be',
        'vimeo.com',
    ];

    /** @var list<string> */
    private const LINK_AGGREGATORS = [
        'linktr.ee',
        'bio.site',
        'beacons.ai',
    ];

    public function classify(string $sourceValue, ?string $normalizedUrl): ResourceType
    {
        if ($normalizedUrl === null) {
            return trim($sourceValue) === '' ? ResourceType::Unknown : ResourceType::Malformed;
        }

        $host = strtolower((string) parse_url($normalizedUrl, PHP_URL_HOST));

        if ($this->matchesHost($host, self::SOCIAL_HOSTS)) {
            return ResourceType::SocialNetwork;
        }

        if ($this->matchesHost($host, self::VIDEO_HOSTS)) {
            return ResourceType::VideoPlatform;
        }

        if ($this->matchesHost($host, self::LINK_AGGREGATORS)) {
            return ResourceType::LinkAggregator;
        }

        return ResourceType::InstitutionalWebsite;
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
