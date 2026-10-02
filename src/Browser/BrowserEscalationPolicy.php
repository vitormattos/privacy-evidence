<?php

declare(strict_types=1);

namespace PrivacyEvidence\Browser;

use PrivacyEvidence\Acquisition\FetchedDocument;

final class BrowserEscalationPolicy
{
    public const VERSION = '1.0.0';

    public function decide(
        FetchedDocument $document,
        bool $behavioralEvidenceRequired = false,
    ): BrowserEscalationDecision {
        if ($behavioralEvidenceRequired) {
            return $this->decision(true, 'behavioral_evidence_required');
        }

        if (!str_contains(strtolower($document->mediaType), 'html')) {
            return $this->decision(false, 'non_html');
        }

        $text = mb_strtolower(
            trim(
                html_entity_decode(
                    strip_tags($document->body),
                    ENT_QUOTES | ENT_HTML5,
                    'UTF-8',
                ),
            ),
        );

        if (
            mb_strlen($text) < 120
            && preg_match(
                '/<(?:div|main)[^>]+(?:id|class)=["\'][^"\']*(?:app|root|spa)[^"\']*/i',
                $document->body,
            ) === 1
        ) {
            return $this->decision(true, 'javascript_application_shell');
        }

        if (
            preg_match('/\b(?:cookie|cookies)\b/u', $text) === 1
            && preg_match(
                '/\b(?:aceitar|accept(?: all)?|rejeitar|recusar|reject(?: all)?|decline|prefer[eê]ncias|preferences|configurar|settings)\b/u',
                $text,
            ) === 1
        ) {
            return $this->decision(true, 'consent_behavior_candidate');
        }

        if (
            preg_match(
                '/(?:enable javascript|javascript required|checking your browser|just a moment)/u',
                $text,
            ) === 1
        ) {
            return $this->decision(true, 'javascript_challenge_candidate');
        }

        return $this->decision(false, 'static_content_sufficient');
    }

    private function decision(bool $required, string $reason): BrowserEscalationDecision
    {
        return new BrowserEscalationDecision(
            required: $required,
            reason: $reason,
            policyVersion: self::VERSION,
        );
    }
}
