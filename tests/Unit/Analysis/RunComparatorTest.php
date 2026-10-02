<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Unit\Analysis;

use PDO;
use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Analysis\RunComparator;
use PrivacyEvidence\Core\ObservationState;
use PrivacyEvidence\Evidence\EvidenceType;
use PrivacyEvidence\Evidence\PrivacyEvidence;
use PrivacyEvidence\Run\ResearchRun;
use PrivacyEvidence\Run\SqliteRunStore;
use PrivacyEvidence\Source\ImportedResource;
use PrivacyEvidence\Source\ResourceType;
use PrivacyEvidence\Storage\SqliteObservationStore;

final class RunComparatorTest extends TestCase
{
    public function testReportsResourceAndEvidenceTransitions(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $runs = new SqliteRunStore($pdo);
        $observations = new SqliteObservationStore($pdo);

        $runs->create(new ResearchRun(
            'base',
            '2026-01-01T00:00:00Z',
            'a',
            str_repeat('a', 64),
            '1.0.0',
            ['schema' => '1.0.0'],
            [],
        ));
        $runs->create(new ResearchRun(
            'target',
            '2026-02-01T00:00:00Z',
            'b',
            str_repeat('b', 64),
            '1.0.0',
            ['schema' => '1.0.0'],
            [],
        ));

        $observations->recordResource(
            'base',
            new ImportedResource(
                'r1',
                'Site',
                'https://old.test',
                'https://old.test/',
                ResourceType::InstitutionalWebsite,
            ),
        );
        $observations->recordResource(
            'base',
            new ImportedResource(
                'removed',
                'Removed',
                'https://removed.test',
                'https://removed.test/',
                ResourceType::InstitutionalWebsite,
            ),
        );
        $observations->recordResource(
            'target',
            new ImportedResource(
                'r1',
                'Site',
                'https://new.test',
                'https://new.test/',
                ResourceType::InstitutionalWebsite,
            ),
        );
        $observations->recordResource(
            'target',
            new ImportedResource(
                'added',
                'Added',
                'https://added.test',
                'https://added.test/',
                ResourceType::InstitutionalWebsite,
            ),
        );

        $observations->recordEvidence(
            'base',
            new PrivacyEvidence(
                EvidenceType::PrivacyNotice,
                ObservationState::Absent,
                'r1',
                str_repeat('1', 64),
                'https://old.test/',
                'test',
                '1',
                'fixture',
            ),
        );
        $observations->recordEvidence(
            'target',
            new PrivacyEvidence(
                EvidenceType::PrivacyNotice,
                ObservationState::Present,
                'r1',
                str_repeat('2', 64),
                'https://new.test/',
                'test',
                '1',
                'fixture',
            ),
        );

        $comparison = (new RunComparator($runs, $observations))->compare('base', 'target');

        self::assertSame(['added'], $comparison['addedResources']);
        self::assertSame(['removed'], $comparison['removedResources']);
        self::assertSame('r1', $comparison['resourceChanges'][0]['resourceId']);
        self::assertSame(['absent'], $comparison['evidenceTransitions'][0]['fromStates']);
        self::assertSame(['present'], $comparison['evidenceTransitions'][0]['toStates']);
        self::assertTrue($comparison['comparable']);
    }

    public function testWarnsWhenProtocolVersionsDiffer(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $runs = new SqliteRunStore($pdo);
        $observations = new SqliteObservationStore($pdo);

        $runs->create(new ResearchRun(
            'a',
            '2026-01-01T00:00:00Z',
            'a',
            str_repeat('a', 64),
            '1.0.0',
            ['schema' => '1.0.0'],
            [],
        ));
        $runs->create(new ResearchRun(
            'b',
            '2026-02-01T00:00:00Z',
            'b',
            str_repeat('b', 64),
            '2.0.0',
            ['schema' => '1.0.0'],
            [],
        ));

        $comparison = (new RunComparator($runs, $observations))->compare('a', 'b');

        self::assertFalse($comparison['comparable']);
        self::assertStringContainsString('Protocol version differs', $comparison['warnings'][0]);
    }
}
