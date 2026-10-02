<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Unit\Evidence;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Acquisition\FetchedDocument;
use PrivacyEvidence\Core\ObservationState;
use PrivacyEvidence\Evidence\Detector\CookieInterfaceDetector;
use PrivacyEvidence\Evidence\EvidenceType;

final class CookieInterfaceDetectorTest extends TestCase
{
    public function testSeparatesCookieControls(): void
    {
        $document = new FetchedDocument('x', 'https://e.test', 'https://e.test', 200, 'text/html', '<div>Cookies <button>Aceitar</button><button>Rejeitar</button><button>Preferências</button></div>', '2026-10-02T00:00:00Z');

        $byType = [];
        foreach ((new CookieInterfaceDetector())->detect($document) as $item) {
            $byType[$item->type->value] = $item;
        }

        self::assertSame(ObservationState::Present, $byType[EvidenceType::CookieRejectControl->value]->state);
        self::assertSame(ObservationState::Present, $byType[EvidenceType::CookiePreferencesControl->value]->state);
    }
}
