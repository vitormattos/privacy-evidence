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
        self::assertSame('privacy', $links[0]->reason);
        self::assertSame('Privacy Policy', $links[0]->anchorText);
        self::assertSame('https://example.test/', $links[0]->sourceUrl);
        self::assertSame(LinkDiscoverer::VERSION, $links[0]->ruleVersion);
    }
    public function testDropsFragmentsAndDoesNotScheduleSameDocument(): void
    {
        $document = new FetchedDocument(
            resourceId: 'site-fragments',
            requestedUrl: 'https://example.test/page',
            finalUrl: 'https://example.test/page',
            statusCode: 200,
            mediaType: 'text/html',
            body: '<a href="#footer">Footer</a>'
                . '<a href="/page#section">Same page</a>'
                . '<a href="/privacy#rights">Privacy rights</a>',
            fetchedAt: '2026-10-02T00:00:00Z',
        );

        $links = (new LinkDiscoverer())->discover($document);

        self::assertCount(1, $links);
        self::assertSame('https://example.test/privacy', $links[0]->url);
        self::assertSame(100, $links[0]->priority);
    }

    public function testCustomTermsAreDeterministicAndExternalLinksRemainExcluded(): void
    {
        $document = new FetchedDocument(
            resourceId: 'site-2',
            requestedUrl: 'https://example.test/',
            finalUrl: 'https://example.test/',
            statusCode: 200,
            mediaType: 'text/html',
            body: '<a href="/custom">Special</a><a href="https://outside.test/custom">Special</a>',
            fetchedAt: '2026-10-02T00:00:00Z',
        );

        $links = (new LinkDiscoverer(privacyTerms: ['special'], version: 'test-v1'))
            ->discover($document);

        self::assertCount(1, $links);
        self::assertSame(100, $links[0]->priority);
        self::assertSame('test-v1', $links[0]->ruleVersion);
    }
}
