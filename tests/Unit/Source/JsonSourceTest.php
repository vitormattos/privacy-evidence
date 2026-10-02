<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Unit\Source;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Source\Json\JsonSource;

final class JsonSourceTest extends TestCase
{
    public function testImportsJsonRecords(): void
    {
        $source = new JsonSource(__DIR__ . '/../../Fixtures/sources/sites.json');
        $resources = iterator_to_array($source->resources());

        self::assertCount(2, $resources);
        self::assertSame('control', $resources[0]->metadata['group']);
    }
}
