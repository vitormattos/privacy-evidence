<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Unit\Evidence;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Acquisition\FetchedDocument;
use PrivacyEvidence\Core\ObservationState;
use PrivacyEvidence\Evidence\Detector\PrivacyContactDetector;
use PrivacyEvidence\Evidence\Detector\PrivacyLawReferenceDetector;
use PrivacyEvidence\Evidence\Detector\PrivacyNoticeDetector;
use PrivacyEvidence\Evidence\Detector\RightsDetector;
use PrivacyEvidence\Evidence\Detector\TransparencyDetector;
use PrivacyEvidence\Evidence\EvidenceType;

final class DetectorTest extends TestCase
{
    public function testDetectorsProduceTraceableEvidence(): void
    {
        $document = new FetchedDocument(
            'site-1',
            'https://example.test/privacy',
            'https://example.test/privacy',
            200,
            'text/html',
            '<h1>Política de Privacidade</h1><p>Em conformidade com a LGPD. Controlador de dados. Base legal: consentimento. Direitos do titular. Para exercer direitos use o formulário. Encarregado pelo tratamento de dados: contato privacy@example.test.</p>',
            '2026-10-02T00:00:00Z',
        );

        $detectors = [
            new PrivacyNoticeDetector(),
            new PrivacyLawReferenceDetector(),
            new PrivacyContactDetector(),
            new RightsDetector(),
            new TransparencyDetector(),
        ];

        $all = [];
        foreach ($detectors as $detector) {
            array_push($all, ...$detector->detect($document));
        }

        self::assertContains(EvidenceType::PrivacyNotice, array_map(static fn ($e) => $e->type, $all));
        self::assertContains(ObservationState::Present, array_map(static fn ($e) => $e->state, $all));
        foreach ($all as $item) {
            self::assertSame($document->sha256, $item->artifactHash);
        }
    }
}
