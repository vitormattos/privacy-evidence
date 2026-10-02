<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Integration\Storage;

use PDO;
use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Acquisition\FetchedDocument;
use PrivacyEvidence\Core\ObservationState;
use PrivacyEvidence\Evidence\EvidenceType;
use PrivacyEvidence\Evidence\PrivacyEvidence;
use PrivacyEvidence\Source\ImportedResource;
use PrivacyEvidence\Source\ResourceType;
use PrivacyEvidence\Storage\SqliteObservationStore;

final class SqliteObservationStoreTest extends TestCase
{
    public function testStoresTraceablePipelineArtifactsIdempotently(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite not available');
        }

        $store = new SqliteObservationStore(new PDO('sqlite::memory:'));
        $resource = new ImportedResource(
            'site-1',
            'Example',
            'example.test',
            'https://example.test/',
            ResourceType::InstitutionalWebsite,
        );
        $document = new FetchedDocument(
            'site-1',
            'https://example.test/',
            'https://example.test/',
            200,
            'text/html',
            '<h1>Privacy Policy</h1>',
            '2026-10-02T00:00:00Z',
        );
        $evidence = new PrivacyEvidence(
            EvidenceType::PrivacyNotice,
            ObservationState::Present,
            'site-1',
            $document->sha256,
            $document->finalUrl,
            'privacy_notice',
            '1.0.0',
            'rule_based_text',
        );

        $store->recordResource('run-1', $resource);
        $store->recordDocument('run-1', $document);
        $store->recordEvidence('run-1', $evidence);
        $store->recordEvidence('run-1', $evidence);
        $store->recordProfileResult(
            'run-1',
            'site-1',
            'gdpr',
            '1.0.0',
            ['id' => 'gdpr-art13-controller', 'state' => 'partial_observed_support'],
        );

        self::assertSame(
            [
                'resources' => 1,
                'documents' => 1,
                'evidence' => 1,
                'profile_results' => 1,
            ],
            $store->counts('run-1'),
        );
        self::assertCount(1, $store->evidence('run-1', 'site-1'));
    }
}
