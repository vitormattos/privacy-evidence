<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Integration\Analysis;

use PDO;
use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Analysis\RegulatoryAnalysisService;
use PrivacyEvidence\Core\ObservationState;
use PrivacyEvidence\Evidence\EvidenceType;
use PrivacyEvidence\Evidence\PrivacyEvidence;
use PrivacyEvidence\Pipeline\DefaultProfileRegistry;
use PrivacyEvidence\Source\ImportedResource;
use PrivacyEvidence\Source\ResourceType;
use PrivacyEvidence\Storage\SqliteObservationStore;

final class RegulatoryAnalysisServiceTest extends TestCase
{
    public function testNonWebsitePopulationMembersDoNotReceiveWebsiteRegulatoryResults(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite not available');
        }

        $store = new SqliteObservationStore(new PDO('sqlite::memory:'));
        $store->recordResource('run-population', new ImportedResource(
            'site-1',
            'Website',
            'https://example.test/',
            'https://example.test/',
            ResourceType::InstitutionalWebsite,
        ));
        $store->recordResource('run-population', new ImportedResource(
            'social-1',
            'Social',
            'https://instagram.com/example',
            'https://instagram.com/example',
            ResourceType::SocialNetwork,
        ));

        $service = new RegulatoryAnalysisService($store, DefaultProfileRegistry::create());
        $service->analyze('run-population');

        $results = $store->profileResults('run-population');
        self::assertNotEmpty($results);

        foreach ($results as $result) {
            self::assertSame('site-1', $result['resourceId'] ?? null);
        }
    }

    public function testReanalysisReplacesCurrentProfileVersionResultsDeterministically(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite not available');
        }

        $store = new SqliteObservationStore(new PDO('sqlite::memory:'));
        $resource = new ImportedResource(
            'site-1',
            'Example',
            'https://example.test/',
            'https://example.test/',
            ResourceType::InstitutionalWebsite,
        );
        $store->recordResource('run-1', $resource);
        $store->recordEvidence('run-1', new PrivacyEvidence(
            type: EvidenceType::PrivacyNotice,
            state: ObservationState::Present,
            resourceId: $resource->id,
            artifactHash: str_repeat('a', 64),
            sourceUrl: 'https://example.test/privacy',
            detector: 'fixture',
            detectorVersion: '1.0.0',
            method: 'fixture',
        ));

        $service = new RegulatoryAnalysisService($store, DefaultProfileRegistry::create());
        $service->analyze('run-1');
        $first = $store->profileResults('run-1');

        $service->analyze('run-1');
        $second = $store->profileResults('run-1');

        self::assertNotEmpty($first);
        self::assertSame($first, $second);
    }
}
