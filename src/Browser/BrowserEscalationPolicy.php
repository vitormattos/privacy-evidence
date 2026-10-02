<?php

declare(strict_types=1);

namespace PrivacyEvidence\Browser;

use PrivacyEvidence\Acquisition\FetchedDocument;

final class BrowserEscalationPolicy
{
    public function decide(FetchedDocument $document, bool $behavioralEvidenceRequired = false): BrowserEscalationDecision
    {
        if ($behavioralEvidenceRequired) {
            return new BrowserEscalationDecision(true, 'behavioral_evidence_required');
        }

        if (!str_contains(strtolower($document->mediaType), 'html')) {
            return new BrowserEscalationDecision(false, 'non_html');
        }

        $text = trim(html_entity_decode(strip_tags($document->body), ENT_QUOTES | ENT_HTML5));
        if (mb_strlen($text) < 120 && preg_match('/<(?:div|main)[^>]+(?:id|class)=["\'][^"\']*(?:app|root|spa)[^"\']*/i', $document->body)) {
            return new BrowserEscalationDecision(true, 'javascript_application_shell');
        }

        return new BrowserEscalationDecision(false, 'static_content_sufficient');
    }
}
