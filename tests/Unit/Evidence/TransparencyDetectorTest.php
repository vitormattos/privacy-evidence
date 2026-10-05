<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Unit\Evidence;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Acquisition\FetchedDocument;
use PrivacyEvidence\Core\ObservationState;
use PrivacyEvidence\Evidence\Detector\TransparencyDetector;
use PrivacyEvidence\Evidence\EvidenceType;
use PrivacyEvidence\Evidence\PrivacyEvidence;

final class TransparencyDetectorTest extends TestCase
{
    public function testGenericThirdPartyWordingIsNotRecipientDisclosure(): void
    {
        $evidence = $this->byType((new TransparencyDetector())->detect(
            $this->document(
                '<p>Eventos organizados por terceiros e atividades abertas à comunidade.</p>',
            ),
        ));

        self::assertSame(
            ObservationState::Absent,
            $evidence[EvidenceType::RecipientDisclosure->value]->state,
        );
    }

    public function testDataSharingWithThirdPartiesIsRecipientDisclosure(): void
    {
        $evidence = $this->byType((new TransparencyDetector())->detect(
            $this->document(
                '<p>Compartilhamos seus dados pessoais com terceiros prestadores de serviço.</p>',
            ),
        ));

        self::assertSame(
            ObservationState::Present,
            $evidence[EvidenceType::RecipientDisclosure->value]->state,
        );
    }

    /**
     * @param list<PrivacyEvidence> $items
     * @return array<string,PrivacyEvidence>
     */
    private function byType(array $items): array
    {
        $result = [];
        foreach ($items as $item) {
            $result[$item->type->value] = $item;
        }

        return $result;
    }

    private function document(string $body): FetchedDocument
    {
        return new FetchedDocument(
            'resource',
            'https://example.test/',
            'https://example.test/',
            200,
            'text/html',
            $body,
            '2026-10-05T00:00:00Z',
        );
    }
}
