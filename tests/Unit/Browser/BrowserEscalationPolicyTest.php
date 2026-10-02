<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Unit\Browser;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Acquisition\FetchedDocument;
use PrivacyEvidence\Browser\BrowserEscalationPolicy;

final class BrowserEscalationPolicyTest extends TestCase
{
    public function testEscalatesJavascriptShell(): void
    {
        $document = new FetchedDocument(
            resourceId: 'x',
            requestedUrl: 'https://example.test',
            finalUrl: 'https://example.test',
            statusCode: 200,
            mediaType: 'text/html',
            body: '<html><body><div id="app"></div><script src="/app.js"></script></body></html>',
            fetchedAt: '2026-10-02T00:00:00Z',
        );

        $decision = (new BrowserEscalationPolicy())->decide($document);

        self::assertTrue($decision->required);
        self::assertSame('javascript_application_shell', $decision->reason);
    }

    public function testBehavioralEvidenceAlwaysEscalates(): void
    {
        $document = new FetchedDocument('x', 'https://e.test', 'https://e.test', 200, 'text/html', '<p>Long enough static content that would otherwise remain static.</p>', '2026-10-02T00:00:00Z');

        self::assertTrue((new BrowserEscalationPolicy())->decide($document, true)->required);
    }
}
