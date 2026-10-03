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
use PrivacyEvidence\Evidence\PrivacyEvidence;

final class DetectorTest extends TestCase
{
    public function testDetectorsProduceExactTraceablePositiveEvidence(): void
    {
        $document = $this->document(
            '<h1>Política de Privacidade</h1>'
            . '<p>Em conformidade com a LGPD. Controlador de dados. '
            . 'Base legal: consentimento. Direitos do titular. '
            . 'Para exercer direitos use o formulário. '
            . 'Encarregado pelo tratamento de dados: contato privacy@example.test.</p>',
        );

        $detectors = [
            new PrivacyNoticeDetector(),
            new PrivacyLawReferenceDetector(),
            new PrivacyContactDetector(),
            new RightsDetector(),
            new TransparencyDetector(),
        ];

        foreach ($detectors as $detector) {
            $evidence = $detector->detect($document);
            self::assertNotSame([], $evidence);

            foreach ($evidence as $item) {
                self::assertSame($document->sha256, $item->artifactHash);
                self::assertSame($document->resourceId, $item->resourceId);
                self::assertSame($document->finalUrl, $item->sourceUrl);
                self::assertSame($detector->name(), $item->detector);
                self::assertSame($detector->version(), $item->detectorVersion);
                self::assertSame('rule_based_text', $item->method);
                self::assertFalse($item->needsReview);
                self::assertArrayHasKey('matches', $item->attributes);

                if ($item->state === ObservationState::Present) {
                    self::assertSame(0.85, $item->confidence);
                    self::assertNotNull($item->excerpt);
                    self::assertGreaterThan(0, $item->attributes['matches']);
                } else {
                    self::assertSame(0.75, $item->confidence);
                    self::assertNull($item->excerpt);
                    self::assertSame(0, $item->attributes['matches']);
                }
            }
        }
    }

    public function testAbsentSignalsRemainAbsentWithNoExcerpt(): void
    {
        $evidence = (new PrivacyContactDetector())->detect($this->document('<p>Nothing relevant here.</p>'));

        self::assertNotSame([], $evidence);
        foreach ($evidence as $item) {
            self::assertSame(ObservationState::Absent, $item->state);
            self::assertSame(0.75, $item->confidence);
            self::assertNull($item->excerpt);
            self::assertFalse($item->needsReview);
            self::assertSame(['matches' => 0], $item->attributes);
        }
    }

    public function testTextNormalizationHandlesEntitiesWhitespaceCaseAndUnicode(): void
    {
        $document = $this->document(
            "<div>  POLÍTICA&nbsp;\n\tDE   PRIVACIDADE </div>",
        );

        $evidence = (new PrivacyNoticeDetector())->detect($document);
        $notice = $this->find($evidence, EvidenceType::PrivacyNotice);

        self::assertSame(ObservationState::Present, $notice->state);
        self::assertSame(0.85, $notice->confidence);
        self::assertStringContainsString('política', $notice->excerpt ?? '');
    }

    public function testExcerptContainsTheMatchedTextRatherThanDocumentPrefix(): void
    {
        $document = $this->document(
            '<p>' . str_repeat('x', 30) . ' política de privacidade trailing text</p>',
        );

        $notice = $this->find(
            (new PrivacyNoticeDetector())->detect($document),
            EvidenceType::PrivacyNotice,
        );

        self::assertSame('política de privacidade', $notice->excerpt);
    }

    /**
     * @param list<PrivacyEvidence> $evidence
     */
    private function find(array $evidence, EvidenceType $type): PrivacyEvidence
    {
        foreach ($evidence as $item) {
            if ($item->type === $type) {
                return $item;
            }
        }

        self::fail('Evidence type not found: ' . $type->value);
    }

    private function document(string $body): FetchedDocument
    {
        return new FetchedDocument(
            'site-1',
            'https://example.test/privacy',
            'https://example.test/privacy',
            200,
            'text/html',
            $body,
            '2026-10-02T00:00:00Z',
        );
    }
}
