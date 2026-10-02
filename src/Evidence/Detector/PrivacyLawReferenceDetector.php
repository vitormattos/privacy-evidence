<?php

declare(strict_types=1);

namespace PrivacyEvidence\Evidence\Detector;

use PrivacyEvidence\Evidence\AbstractTextDetector;
use PrivacyEvidence\Evidence\EvidenceType;

final class PrivacyLawReferenceDetector extends AbstractTextDetector
{
    public function name(): string { return 'privacy_law_reference'; }
    public function version(): string { return '1.0.0'; }

    protected function patterns(): array
    {
        return [
            EvidenceType::PrivacyLawReference => [
                '/\blgpd\b/u',
                '/lei\s*(?:n[ºo.]?\s*)?13[.]?709\/2018/u',
                '/\bgdpr\b/u',
                '/general data protection regulation/u',
                '/regulation\s*\(eu\)\s*2016\/679/u',
            ],
        ];
    }
}
