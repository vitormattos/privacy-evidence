<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Unit\Source;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Source\UrlNormalizer;

final class UrlNormalizerTest extends TestCase
{
    #[DataProvider('cases')]
    public function testNormalize(string $input, ?string $expected): void
    {
        self::assertSame($expected, (new UrlNormalizer())->normalize($input));
    }

    /**
     * @return iterable<array{string, ?string}>
     */
    public static function cases(): iterable
    {
        yield ['example.com', 'https://example.com/'];
        yield [' HTTPS://Example.COM/path?q=1 ', 'https://example.com/path?q=1'];
        yield ['http://example.com', 'http://example.com/'];
        yield ['https://Example.COM.:8443/path?q=1#fragment', 'https://example.com:8443/path?q=1'];
        yield ['https://example.com?x=1', 'https://example.com/?x=1'];
        yield ['foo https://example.com', null];
        yield ['mailto:user@example.com', null];
        yield ['ftp://example.com/file', null];
        yield ['   ', null];
        yield ['', null];
        yield ['not a valid host', null];
    }
}
