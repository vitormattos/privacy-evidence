<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Unit\Source;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Source\ResourceType;

final class ResourceTypeEligibilityTest extends TestCase
{
    public function testWebsiteMeasurementEligibilityIsIndependentFromHostingOwnership(): void
    {
        self::assertTrue(ResourceType::InstitutionalWebsite->isWebsiteMeasurementEligible());
        self::assertTrue(ResourceType::ThirdPartyHostedPage->isWebsiteMeasurementEligible());

        self::assertFalse(ResourceType::SocialNetwork->isWebsiteMeasurementEligible());
        self::assertFalse(ResourceType::VideoPlatform->isWebsiteMeasurementEligible());
        self::assertFalse(ResourceType::LinkAggregator->isWebsiteMeasurementEligible());
        self::assertFalse(ResourceType::Malformed->isWebsiteMeasurementEligible());
        self::assertFalse(ResourceType::Unknown->isWebsiteMeasurementEligible());
    }
}
