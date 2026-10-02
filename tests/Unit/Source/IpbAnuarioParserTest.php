<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Unit\Source;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Source\Ipb\IpbAnuarioSource;
use PrivacyEvidence\Source\ResourceType;

final class IpbAnuarioParserTest extends TestCase
{
    public function testHistoricalStructureIsParsedWithoutLegacyDatabaseSemantics(): void
    {
        $source = IpbAnuarioSource::fromFile(
            __DIR__ . '/../../Fixtures/ipb/anuario-minimal.html',
        );
        $resources = iterator_to_array($source->resources());

        self::assertCount(1, $resources);
        self::assertSame('IGREJA PRESBITERIANA TESTE', $resources[0]->name);
        self::assertSame('https://example.test', $resources[0]->sourceValue);
        self::assertSame('https://example.test/', $resources[0]->normalizedUrl);
        self::assertSame(ResourceType::InstitutionalWebsite, $resources[0]->type);
        self::assertSame('PRTB', $resources[0]->metadata['presbytery']);
        self::assertSame('Rio de Janeiro', $resources[0]->metadata['municipality']);
        self::assertSame('RJ', $resources[0]->metadata['state']);
        self::assertSame('Rev. Pastor Teste', $resources[0]->metadata['pastor_name']);
    }

    public function testSourceSnapshotIsHashableAndTraceable(): void
    {
        $source = IpbAnuarioSource::fromFile(
            __DIR__ . '/../../Fixtures/ipb/anuario-minimal.html',
        );

        self::assertSame('ipb-icalvinus', $source->snapshot()->sourceId);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $source->snapshot()->sha256);
    }
}
