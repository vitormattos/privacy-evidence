<?php

declare(strict_types=1);

namespace PrivacyEvidence\Evidence\Detector;

use PrivacyEvidence\Acquisition\FetchedDocument;
use PrivacyEvidence\Core\ObservationState;
use PrivacyEvidence\Evidence\Detector;
use PrivacyEvidence\Evidence\EvidenceType;
use PrivacyEvidence\Evidence\PrivacyEvidence;

final class CookieInterfaceDetector implements Detector
{
    public function name(): string
    {
        return 'cookie_interface';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function detect(FetchedDocument $document): array
    {
        $spacedHtml = preg_replace('/<[^>]+>/', ' ', $document->body) ?? $document->body;
        $text = mb_strtolower(
            html_entity_decode($spacedHtml, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        );
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return [
            $this->signal(
                $document,
                EvidenceType::CookieNotice,
                preg_match('/\b(?:cookie|cookies)\b/u', $text) === 1,
                'cookie_term',
            ),
            $this->signal(
                $document,
                EvidenceType::CookieAcceptControl,
                preg_match('/\b(?:aceitar|accept(?: all)?)\b/u', $text) === 1,
                'accept_control_text',
            ),
            $this->signal(
                $document,
                EvidenceType::CookieRejectControl,
                preg_match('/\b(?:rejeitar|recusar|reject(?: all)?|decline)\b/u', $text) === 1,
                'reject_control_text',
            ),
            $this->signal(
                $document,
                EvidenceType::CookiePreferencesControl,
                preg_match('/(?:prefer[eê]ncias|preferences|configurar|settings)/u', $text) === 1,
                'preferences_control_text',
            ),
        ];
    }

    private function signal(
        FetchedDocument $document,
        EvidenceType $type,
        bool $present,
        string $method,
    ): PrivacyEvidence {
        return new PrivacyEvidence(
            type: $type,
            state: $present ? ObservationState::Present : ObservationState::Absent,
            resourceId: $document->resourceId,
            artifactHash: $document->sha256,
            sourceUrl: $document->finalUrl,
            detector: $this->name(),
            detectorVersion: $this->version(),
            method: $method,
            confidence: $present ? 0.8 : 0.65,
            needsReview: !$present,
        );
    }
}
