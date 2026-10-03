<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Unit\Source;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Source\Json\JsonSource;

final class JsonSourceTest extends TestCase
{
    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function testImportsJsonRecordsAndPreservesOnlyScalarMetadata(): void
    {
        $path = $this->temporaryJson([
            [
                'id' => 'one',
                'name' => 'Example',
                'url' => 'example.com',
                'group' => 'control',
                'nullable' => null,
                'count' => 2,
                'nested' => ['must' => 'not leak'],
            ],
        ]);

        $source = new JsonSource($path);
        $resources = iterator_to_array($source->resources());

        self::assertCount(1, $resources);
        self::assertSame('one', $resources[0]->id);
        self::assertSame('Example', $resources[0]->name);
        self::assertSame('example.com', $resources[0]->sourceValue);
        self::assertSame('https://example.com/', $resources[0]->normalizedUrl);
        self::assertSame([
            'group' => 'control',
            'nullable' => null,
            'count' => 2,
        ], $resources[0]->metadata);
        self::assertStringStartsWith('json:', $source->sourceId());
        self::assertSame('application/json', $source->snapshot()->mediaType);
        self::assertSame(hash_file('sha256', $path), $source->snapshot()->sha256);
    }

    public function testRejectsDuplicateIds(): void
    {
        $this->assertInvalidSource(
            new JsonSource(__DIR__ . '/../../Fixtures/sources/sites-duplicate.json'),
            'duplicate id',
        );
    }

    public function testRejectsMissingRequiredField(): void
    {
        $this->assertInvalidSource(
            new JsonSource(__DIR__ . '/../../Fixtures/sources/sites-missing-field.json'),
            'requires scalar',
        );
    }

    #[DataProvider('invalidDocuments')]
    public function testRejectsInvalidJsonShapes(mixed $document, string $message): void
    {
        $this->assertInvalidSource(new JsonSource($this->temporaryJson($document)), $message);
    }

    /**
     * @return iterable<array{mixed,string}>
     */
    public static function invalidDocuments(): iterable
    {
        yield 'object instead of list' => [
            ['id' => 'one', 'name' => 'Example', 'url' => 'example.com'],
            'array of objects',
        ];
        yield 'scalar record' => [[1], 'record must be an object'];
        yield 'empty id' => [
            [['id' => '', 'name' => 'Example', 'url' => 'example.com']],
            'id must not be empty',
        ];
        yield 'non scalar name' => [
            [['id' => 'one', 'name' => ['bad'], 'url' => 'example.com']],
            'requires scalar name',
        ];
    }

    private function temporaryJson(mixed $data): string
    {
        $path = tempnam(sys_get_temp_dir(), 'privacy-evidence-json-');
        self::assertNotFalse($path);
        file_put_contents($path, json_encode($data, JSON_THROW_ON_ERROR));
        $this->temporaryFiles[] = $path;

        return $path;
    }

    private function assertInvalidSource(JsonSource $source, string $message): void
    {
        try {
            iterator_to_array($source->resources());
            self::fail('Expected invalid JSON source to throw.');
        } catch (\InvalidArgumentException $exception) {
            self::assertStringContainsString($message, $exception->getMessage());
        }
    }
}
