<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Unit\Crawl;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Acquisition\FetchedDocument;
use PrivacyEvidence\Crawl\LinkDiscoverer;

final class LinkDiscovererTest extends TestCase
{
    public function testPrioritizesEveryDefaultLinkClassAndIgnoresExternalHosts(): void
    {
        $document = $this->document(
            '<a href="/news">News</a>'
            . '<a href="/privacy">Privacy Policy</a>'
            . '<a href="/cookies">Cookie settings</a>'
            . '<a href="/about">About us</a>'
            . '<a href="https://other.test/x">Other</a>',
        );

        $links = (new LinkDiscoverer())->discover($document);

        self::assertCount(4, $links);
        self::assertSame(
            [
                ['https://example.test/privacy', 100, 'privacy'],
                ['https://example.test/cookies', 80, 'privacy_control'],
                ['https://example.test/about', 50, 'supporting'],
                ['https://example.test/news', 10, 'other_internal'],
            ],
            array_map(
                static fn ($link): array => [$link->url, $link->priority, $link->reason],
                $links,
            ),
        );
        self::assertSame('Privacy Policy', $links[0]->anchorText);
        self::assertSame('https://example.test/', $links[0]->sourceUrl);
        self::assertSame(LinkDiscoverer::VERSION, $links[0]->ruleVersion);
    }

    public function testDefaultControlAndSupportingTermsAreActuallyUsed(): void
    {
        $document = $this->document(
            '<a href="/x">DPO</a>'
            . '<a href="/y">CONTACT</a>'
            . '<a href="/z">Ordinary</a>',
        );

        $links = (new LinkDiscoverer())->discover($document);

        self::assertSame(80, $links[0]->priority);
        self::assertSame('privacy_control', $links[0]->reason);
        self::assertSame(50, $links[1]->priority);
        self::assertSame('supporting', $links[1]->reason);
        self::assertSame(10, $links[2]->priority);
        self::assertSame('other_internal', $links[2]->reason);
    }

    public function testUppercaseHtmlMediaTypeHostAndAnchorTextAreNormalized(): void
    {
        $document = new FetchedDocument(
            resourceId: 'site-case',
            requestedUrl: 'https://EXAMPLE.test/',
            finalUrl: 'https://EXAMPLE.test/',
            statusCode: 200,
            mediaType: 'TEXT/HTML; CHARSET=UTF-8',
            body: '<a href="https://example.TEST/NOTICE">  PRIVACIDADE  </a>',
            fetchedAt: '2026-10-02T00:00:00Z',
        );

        $links = (new LinkDiscoverer())->discover($document);

        self::assertCount(1, $links);
        self::assertSame('https://example.TEST/NOTICE', $links[0]->url);
        self::assertSame(100, $links[0]->priority);
        self::assertSame('PRIVACIDADE', $links[0]->anchorText);
    }

    public function testPrivacyTermCanMatchUrlWhenAnchorDoesNot(): void
    {
        $links = (new LinkDiscoverer())->discover(
            $this->document('<a href="/lgpd">Read more</a>'),
        );

        self::assertCount(1, $links);
        self::assertSame(100, $links[0]->priority);
        self::assertSame('privacy', $links[0]->reason);
    }

    public function testDropsFragmentsWhitespaceAndSameDocument(): void
    {
        $document = new FetchedDocument(
            resourceId: 'site-fragments',
            requestedUrl: 'https://example.test/page',
            finalUrl: 'https://example.test/page',
            statusCode: 200,
            mediaType: 'text/html',
            body: '<a href="   ">Empty</a>'
                . '<a href="#footer">Footer</a>'
                . '<a href="/page#section">Same page</a>'
                . '<a href=" /privacy#rights ">Privacy rights</a>',
            fetchedAt: '2026-10-02T00:00:00Z',
        );

        $links = (new LinkDiscoverer())->discover($document);

        self::assertCount(1, $links);
        self::assertSame('https://example.test/privacy', $links[0]->url);
        self::assertSame(100, $links[0]->priority);
    }

    public function testNonHtmlContentIsIgnored(): void
    {
        self::assertSame(
            [],
            (new LinkDiscoverer())->discover(
                new FetchedDocument(
                    'site-json',
                    'https://example.test/data',
                    'https://example.test/data',
                    200,
                    'application/json',
                    '<a href="/privacy">Privacy</a>',
                    '2026-10-02T00:00:00Z',
                ),
            ),
        );
    }

    public function testCustomTermSetsReplaceDefaultsDeterministically(): void
    {
        $document = $this->document(
            '<a href="/special">Special</a>'
            . '<a href="/control">Control</a>'
            . '<a href="/support">Support</a>'
            . '<a href="/privacy">Privacy</a>',
        );

        $links = (new LinkDiscoverer(
            privacyTerms: ['special'],
            controlTerms: ['control'],
            supportingTerms: ['support'],
            version: 'test-v1',
        ))->discover($document);

        self::assertSame(
            [
                ['https://example.test/special', 100, 'privacy', 'test-v1'],
                ['https://example.test/control', 80, 'privacy_control', 'test-v1'],
                ['https://example.test/support', 50, 'supporting', 'test-v1'],
                ['https://example.test/privacy', 10, 'other_internal', 'test-v1'],
            ],
            array_map(
                static fn ($link): array => [
                    $link->url,
                    $link->priority,
                    $link->reason,
                    $link->ruleVersion,
                ],
                $links,
            ),
        );
    }

    private function document(string $body): FetchedDocument
    {
        return new FetchedDocument(
            resourceId: 'site-1',
            requestedUrl: 'https://example.test/',
            finalUrl: 'https://example.test/',
            statusCode: 200,
            mediaType: 'text/html',
            body: $body,
            fetchedAt: '2026-10-02T00:00:00Z',
        );
    }
}
