<?php

declare(strict_types=1);

namespace PrivacyEvidence\Source;

enum ResourceType: string
{
    case InstitutionalWebsite = 'institutional_website';
    case SocialNetwork = 'social_network';
    case VideoPlatform = 'video_platform';
    case LinkAggregator = 'link_aggregator';
    case ThirdPartyHostedPage = 'third_party_hosted_page';
    case Malformed = 'malformed';
    case Unknown = 'unknown';

    public function isWebsiteMeasurementEligible(): bool
    {
        return match ($this) {
            self::InstitutionalWebsite,
            self::ThirdPartyHostedPage => true,
            default => false,
        };
    }
}
