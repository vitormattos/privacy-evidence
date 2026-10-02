<?php

declare(strict_types=1);

namespace PrivacyEvidence\Evidence\Detector;

use PrivacyEvidence\Evidence\AbstractTextDetector;
use PrivacyEvidence\Evidence\EvidenceType;

final class PrivacyNoticeDetector extends AbstractTextDetector
{
    public function name(): string { return 'privacy_notice'; }
    public function version(): string { return '1.0.0'; }

    protected function patterns(): array
    {
        return [
            EvidenceType::PrivacyNotice => [
                '/\bpol[ií]tica de privacidade\b/u',
                '/\baviso de privacidade\b/u',
                '/\bprivacy policy\b/u',
                '/\bprivacy notice\b/u',
                '/\bdata protection notice\b/u',
            ],
        ];
    }
}
