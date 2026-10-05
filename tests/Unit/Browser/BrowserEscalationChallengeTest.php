<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Unit\Browser;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Acquisition\FetchedDocument;
use PrivacyEvidence\Browser\BrowserEscalationPolicy;

final class BrowserEscalationChallengeTest extends TestCase
{
    public function testCaptchaLikeChallengeIsClassifiedExplicitly(): void
    {
        $document = new FetchedDocument(
            resourceId: 'site-1',
            requestedUrl: 'https://example.test/',
            finalUrl: 'https://example.test/',
            statusCode: 200,
            mediaType: 'text/html',
            body: '<html><body>Please verify you are human. CAPTCHA</body></html>',
            fetchedAt: '2026-10-04T00:00:00Z',
        );

        $decision = (new BrowserEscalationPolicy())->decide($document);

        self::assertTrue($decision->required);
        self::assertSame('anti_bot_challenge_candidate', $decision->reason);
    }
}
