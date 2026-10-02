<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Unit\Acquisition;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Acquisition\FetchedDocument;

final class FetchedDocumentTest extends TestCase
{
    public function testBodyHashIsStable(): void
    {
        $document = new FetchedDocument(
            resourceId: 'site-1',
            requestedUrl: 'https://example.test',
            finalUrl: 'https://example.test/',
            statusCode: 200,
            mediaType: 'text/html',
            body: '<html>ok</html>',
            fetchedAt: '2026-10-02T00:00:00Z',
        );

        self::assertSame(hash('sha256', '<html>ok</html>'), $document->sha256);
    }
}
