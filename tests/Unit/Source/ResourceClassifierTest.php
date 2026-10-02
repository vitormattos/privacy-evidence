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
}
