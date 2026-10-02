<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Unit\Acquisition;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Acquisition\FetchedDocument;
use PrivacyEvidence\Acquisition\FilesystemDocumentStore;

final class FilesystemDocumentStoreTest extends TestCase
{
    public function testStoresByImmutableHash(): void
    {
        $dir = sys_get_temp_dir() . '/privacy-evidence-' . bin2hex(random_bytes(4));
        $store = new FilesystemDocumentStore($dir);
        $document = new FetchedDocument('x', 'https://e.test', 'https://e.test', 200, 'text/html', 'body', '2026-10-02T00:00:00Z');

        $path = $store->put($document);

        self::assertFileExists($path);
        self::assertSame('body', $store->get($document->sha256));

        @unlink($path);
        @rmdir($dir);
    }
}
