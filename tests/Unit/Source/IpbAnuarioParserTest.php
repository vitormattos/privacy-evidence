<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Unit\Source;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Source\Ipb\IpbAnuarioParser;
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
        self::assertSame('Rua Exemplo, 123', $resources[0]->metadata['address']);
        self::assertSame('Rio de Janeiro', $resources[0]->metadata['municipality']);
        self::assertSame('RJ', $resources[0]->metadata['state']);
        self::assertSame('20000-000', $resources[0]->metadata['postal_code']);
        self::assertSame('+55 21 1234-5678', $resources[0]->metadata['phone']);
        self::assertSame('igreja@example.test', $resources[0]->metadata['email']);
        self::assertSame('Rev. Pastor Teste', $resources[0]->metadata['pastor_name']);
        self::assertSame('+55 21 2222-3333', $resources[0]->metadata['pastor_phone']);
        self::assertSame('+55 21 99999-8888', $resources[0]->metadata['pastor_mobile']);
        self::assertSame('pastor@example.test', $resources[0]->metadata['pastor_email']);
        self::assertSame(0, $resources[0]->metadata['legacy_block_index']);
        self::assertSame('default_web_host', $resources[0]->classificationRule);
        self::assertSame(0.8, $resources[0]->classificationConfidence);
    }

    public function testParserSkipsBlocksWithoutAChurchName(): void
    {
        $html = '<div style="font-family: Helvetica, Arial; background-color: rgba(0,0,0,0.05);">'
            . '<div style="padding: 15px;"><big><b></b></big><b>PR</b></div>'
            . '</div>';

        self::assertSame([], (new IpbAnuarioParser())->parse($html));
    }

    public function testParserAllowsChurchWithoutPastorBlock(): void
    {
        $html = '<div style="font-family: Helvetica, Arial; background-color: rgba(0,0,0,0.05);">'
            . '<div style="padding: 15px;">'
            . '<big><b>IGREJA SEM PASTOR</b></big><b>PRSP</b>'
            . '<br><br>Rua A, 1 - São Paulo / SP<br>'
            . '<a href="https://example.org">Website</a>'
            . '</div></div>';

        $resources = (new IpbAnuarioParser())->parse($html);

        self::assertCount(1, $resources);
        self::assertSame('São Paulo', $resources[0]->metadata['municipality']);
        self::assertSame('SP', $resources[0]->metadata['state']);
        self::assertNull($resources[0]->metadata['pastor_name']);
        self::assertNull($resources[0]->metadata['pastor_phone']);
        self::assertNull($resources[0]->metadata['pastor_mobile']);
        self::assertNull($resources[0]->metadata['pastor_email']);
    }

    public function testSourceSnapshotIsHashableAndTraceable(): void
    {
        $path = __DIR__ . '/../../Fixtures/ipb/anuario-minimal.html';
        $source = IpbAnuarioSource::fromFile($path);
        $snapshot = $source->snapshot();

        self::assertSame('ipb-icalvinus', $snapshot->sourceId);
        self::assertSame(hash_file('sha256', $path), $snapshot->sha256);
        self::assertSame($path, $snapshot->location);
        self::assertSame('text/html', $snapshot->mediaType);
        self::assertNotSame('1970-01-01T00:00:00+00:00', $snapshot->capturedAt);
    }
}
