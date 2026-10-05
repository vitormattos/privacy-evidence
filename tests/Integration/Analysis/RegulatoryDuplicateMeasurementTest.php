<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Integration\Analysis;

use PDO;
use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Analysis\RegulatoryAnalysisService;
use PrivacyEvidence\Pipeline\DefaultProfileRegistry;
use PrivacyEvidence\Source\ImportedResource;
use PrivacyEvidence\Source\ResourceType;
use PrivacyEvidence\Storage\SqliteObservationStore;

final class RegulatoryDuplicateMeasurementTest extends TestCase
{
    public function testDuplicateWebsiteUrlCountsOnceInRegulatoryDenominator(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite not available');
        }

        $store = new SqliteObservationStore(new PDO('sqlite::memory:'));
        foreach (['b-resource', 'a-resource'] as $id) {
            $store->recordResource('run-duplicates', new ImportedResource(
                $id,
                $id,
                'https://example.test/',
                'https://example.test/',
                ResourceType::InstitutionalWebsite,
            ));
        }

        (new RegulatoryAnalysisService(
            $store,
            DefaultProfileRegistry::create(),
        ))->analyze('run-duplicates');

        $results = $store->profileResults('run-duplicates');
        self::assertNotEmpty($results);
        foreach ($results as $result) {
            self::assertSame('a-resource', $result['resourceId'] ?? null);
        }
    }
}
