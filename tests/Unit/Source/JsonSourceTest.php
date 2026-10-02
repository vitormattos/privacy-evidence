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
    public function testRejectsDuplicateIds(): void
    {
        $source = new JsonSource(__DIR__ . '/../../Fixtures/sources/sites-duplicate.json');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('duplicate id');
        iterator_to_array($source->resources());
    }

    public function testRejectsMissingRequiredField(): void
    {
        $source = new JsonSource(__DIR__ . '/../../Fixtures/sources/sites-missing-field.json');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('requires scalar url');
        iterator_to_array($source->resources());
    }
}
