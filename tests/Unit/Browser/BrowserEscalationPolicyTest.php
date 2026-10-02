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
        $document = $this->document(
            '<html><body><div id="app"></div><script src="/app.js"></script></body></html>',
        );

        $decision = (new BrowserEscalationPolicy())->decide($document);

        self::assertTrue($decision->required);
        self::assertSame('javascript_application_shell', $decision->reason);
        self::assertSame(BrowserEscalationPolicy::VERSION, $decision->policyVersion);
    }

    public function testBehavioralEvidenceAlwaysEscalates(): void
    {
        $document = $this->document(
            '<p>Long enough static content that would otherwise remain static.</p>',
        );

        $decision = (new BrowserEscalationPolicy())->decide($document, true);

        self::assertTrue($decision->required);
        self::assertSame('behavioral_evidence_required', $decision->reason);
    }

    public function testEscalatesConsentBehaviorCandidate(): void
    {
        $document = $this->document(
            '<p>We use cookies. <button>Accept all</button> <button>Reject all</button></p>',
        );

        $decision = (new BrowserEscalationPolicy())->decide($document);

        self::assertTrue($decision->required);
        self::assertSame('consent_behavior_candidate', $decision->reason);
    }

    public function testDoesNotEscalateOrdinaryStaticHtml(): void
    {
        $document = $this->document(
            '<article>This is a sufficiently informative static page without interactive privacy behavior.</article>',
        );

        $decision = (new BrowserEscalationPolicy())->decide($document);

        self::assertFalse($decision->required);
        self::assertSame('static_content_sufficient', $decision->reason);
    }

    private function document(string $body): FetchedDocument
    {
        return new FetchedDocument(
            resourceId: 'x',
            requestedUrl: 'https://example.test',
            finalUrl: 'https://example.test',
            statusCode: 200,
            mediaType: 'text/html',
            body: $body,
            fetchedAt: '2026-10-02T00:00:00Z',
        );
    }
}
