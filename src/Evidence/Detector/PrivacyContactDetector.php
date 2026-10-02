<?php

declare(strict_types=1);

namespace PrivacyEvidence\Evidence\Detector;

use PrivacyEvidence\Evidence\AbstractTextDetector;
use PrivacyEvidence\Evidence\EvidenceType;

final class PrivacyContactDetector extends AbstractTextDetector
{
    public function name(): string { return 'privacy_contact_dpo'; }
    public function version(): string { return '1.0.0'; }

    protected function patterns(): array
    {
        return [
            EvidenceType::ControllerIdentity => [
                '/\bcontrolador(?:a)?\s+(?:de|dos)\s+dados\b/u',
                '/\bdata controller\b/u',
            ],
            EvidenceType::PrivacyContact => [
                '/\b(?:privacidade|privacy)[^@\n]{0,80}@[a-z0-9.-]+\.[a-z]{2,}\b/u',
                '/\b(?:prote[cç][aã]o de dados|data protection)\b.{0,120}\b(?:contato|contact|e-?mail)\b/u',
            ],
            EvidenceType::DpoRole => [
                '/\bencarregado(?:a)?\s+(?:pelo )?tratamento de dados\b/u',
                '/\bdata protection officer\b/u',
                '/\bdpo\b/u',
            ],
            EvidenceType::DpoIdentity => [
                '/\b(?:encarregado(?:a)?|data protection officer|dpo)\b.{0,100}\b(?:sr\.?|sra\.?|dr\.?|dra\.?|nome|name)\b/u',
            ],
            EvidenceType::DpoContact => [
                '/\b(?:encarregado(?:a)?|data protection officer|dpo)\b.{0,160}@[a-z0-9.-]+\.[a-z]{2,}\b/u',
            ],
        ];
    }
}
