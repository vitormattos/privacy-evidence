<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Unit\Source;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Source\ResourceClassification;
use PrivacyEvidence\Source\ResourceClassifier;
use PrivacyEvidence\Source\ResourceType;

final class ResourceClassifierTest extends TestCase
{
    #[DataProvider('knownHosts')]
    public function testClassifiesKnownHosts(
        string $url,
        ResourceType $expectedType,
        string $expectedRule,
        float $expectedConfidence,
    ): void {
        $classification = (new ResourceClassifier())->classifyDetailed($url, $url);

        self::assertSame($expectedType, $classification->type);
        self::assertSame($expectedRule, $classification->rule);
        self::assertSame(ResourceClassifier::VERSION, $classification->version);
        self::assertSame($expectedConfidence, $classification->confidence);
    }

    /**
     * @return iterable<array{string,ResourceType,string,float}>
     */
    public static function knownHosts(): iterable
    {
        yield ['https://instagram.com/example', ResourceType::SocialNetwork, 'known_social_host', 1.0];
        yield ['https://WWW.YOUTUBE.COM/watch?v=1', ResourceType::VideoPlatform, 'known_video_host', 1.0];
        yield ['https://team.linktr.ee/example', ResourceType::LinkAggregator, 'known_link_aggregator', 1.0];
        yield ['https://example.wordpress.com/', ResourceType::ThirdPartyHostedPage, 'known_hosted_page', 0.95];
    }

    public function testEmptyAndMalformedValuesRemainDistinct(): void
    {
        $classifier = new ResourceClassifier();

        $empty = $classifier->classifyDetailed("  \t", null);
        self::assertSame(ResourceType::Unknown, $empty->type);
        self::assertSame('empty_source', $empty->rule);
        self::assertSame(1.0, $empty->confidence);

        $malformed = $classifier->classifyDetailed('not a url', null);
        self::assertSame(ResourceType::Malformed, $malformed->type);
        self::assertSame('normalization_failed', $malformed->rule);
        self::assertSame(1.0, $malformed->confidence);
    }

    public function testHostSuffixMustBeARealSubdomainBoundary(): void
    {
        $classifier = new ResourceClassifier();

        self::assertSame(
            ResourceType::SocialNetwork,
            $classifier->classify('m.instagram.com/example', 'https://m.instagram.com/example'),
        );
        self::assertSame(
            ResourceType::InstitutionalWebsite,
            $classifier->classify('notinstagram.com', 'https://notinstagram.com/'),
        );
    }

    public function testNormalWebsiteIsInstitutionalWithDefaultDecisionMetadata(): void
    {
        $classification = (new ResourceClassifier())->classifyDetailed(
            'example.org',
            'https://example.org/',
        );

        self::assertSame(ResourceType::InstitutionalWebsite, $classification->type);
        self::assertSame('default_web_host', $classification->rule);
        self::assertSame(ResourceClassifier::VERSION, $classification->version);
        self::assertSame(0.8, $classification->confidence);
    }

    public function testClassificationConfidenceAcceptsClosedUnitInterval(): void
    {
        self::assertSame(
            0.0,
            (new ResourceClassification(ResourceType::Unknown, 'zero', 'test', 0.0))->confidence,
        );
        self::assertSame(
            1.0,
            (new ResourceClassification(ResourceType::Unknown, 'one', 'test', 1.0))->confidence,
        );
    }

    #[DataProvider('invalidConfidences')]
    public function testClassificationConfidenceRejectsValuesOutsideUnitInterval(float $confidence): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ResourceClassification(ResourceType::Unknown, 'invalid', 'test', $confidence);
    }

    /**
     * @return iterable<array{float}>
     */
    public static function invalidConfidences(): iterable
    {
        yield [-0.001];
        yield [1.001];
    }
}
