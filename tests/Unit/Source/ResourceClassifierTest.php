<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Unit\Source;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Source\ResourceClassifier;
use PrivacyEvidence\Source\ResourceType;

final class ResourceClassifierTest extends TestCase
{
    public function testRetainsSocialNetworksAsTypedResources(): void
    {
        $classifier = new ResourceClassifier();

        self::assertSame(
            ResourceType::SocialNetwork,
            $classifier->classify('instagram.com/example', 'https://instagram.com/example'),
        );
    }

    public function testMalformedValueIsNotSilentlyDropped(): void
    {
        self::assertSame(
            ResourceType::Malformed,
            (new ResourceClassifier())->classify('not a url', null),
        );
    }

    public function testNormalWebsiteIsInstitutional(): void
    {
        self::assertSame(
            ResourceType::InstitutionalWebsite,
            (new ResourceClassifier())->classify('example.org', 'https://example.org/'),
        );
    }
    public function testClassificationCarriesRuleVersionAndConfidence(): void
    {
        $classification = (new ResourceClassifier())->classifyDetailed(
            'instagram.com/example',
            'https://instagram.com/example',
        );

        self::assertSame(ResourceType::SocialNetwork, $classification->type);
        self::assertSame('known_social_host', $classification->rule);
        self::assertSame(ResourceClassifier::VERSION, $classification->version);
        self::assertSame(1.0, $classification->confidence);
    }

    public function testKnownHostedPageIsClassifiedSeparately(): void
    {
        self::assertSame(
            ResourceType::ThirdPartyHostedPage,
            (new ResourceClassifier())->classify('https://example.wordpress.com', 'https://example.wordpress.com/'),
        );
    }
}
