<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Unit\Crawl;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Acquisition\FetchedDocument;
use PrivacyEvidence\Crawl\LinkDiscoverer;

final class LinkDiscovererTest extends TestCase
{
    public function testPrioritizesPrivacyLinksAndIgnoresExternalHosts(): void
    {
        $document = new FetchedDocument(
            resourceId: 'site-1',
            requestedUrl: 'https://example.test/',
            finalUrl: 'https://example.test/',
            statusCode: 200,
            mediaType: 'text/html',
            body: '<a href="/news">News</a><a href="/privacy">Privacy Policy</a><a href="https://other.test/x">Other</a>',
            fetchedAt: '2026-10-02T00:00:00Z',
        );

        $links = (new LinkDiscoverer())->discover($document);

        self::assertCount(2, $links);
        self::assertSame('https://example.test/privacy', $links[0]->url);
        self::assertSame(100, $links[0]->priority);
    }
}
