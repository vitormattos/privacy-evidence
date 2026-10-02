<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Unit\Source;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Source\Csv\CsvSource;
use PrivacyEvidence\Source\ResourceType;

final class CsvSourceTest extends TestCase
{
    public function testImportsCanonicalResourcesAndPreservesOriginalValue(): void
    {
        $source = new CsvSource(__DIR__ . '/../../Fixtures/sources/sites.csv');
        $resources = iterator_to_array($source->resources());

        self::assertCount(3, $resources);
        self::assertSame('example.com', $resources[0]->sourceValue);
        self::assertSame('https://example.com/', $resources[0]->normalizedUrl);
        self::assertSame(ResourceType::SocialNetwork, $resources[1]->type);
        self::assertSame(ResourceType::Malformed, $resources[2]->type);
    }
}
