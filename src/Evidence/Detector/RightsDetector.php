<?php

declare(strict_types=1);

namespace PrivacyEvidence\Evidence\Detector;

use PrivacyEvidence\Evidence\AbstractTextDetector;
use PrivacyEvidence\Evidence\EvidenceType;

final class RightsDetector extends AbstractTextDetector
{
    public function name(): string
    {
        return 'data_subject_rights';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    protected function patterns(): array
    {
        return [
            [
                'type' => EvidenceType::RightsDisclosure,
                'patterns' => [
                    '/\bdireitos? (?:do|dos) titular/u',
                    '/\bdata subject rights\b/u',
                    '/\bdireito (?:de|a) acesso\b/u',
                    '/\bright to (?:access|erasure|rectification|object)\b/u',
                ],
            ],
            [
                'type' => EvidenceType::RightsChannel,
                'patterns' => [
                    '/\b(?:exercer|exercise).{0,100}(?:direitos?|rights?).{0,120}(?:formul[aá]rio|form|e-?mail|email|contato|contact)/u',
                    '/\b(?:solicita[cç][aã]o|request).{0,100}(?:titular|data subject)\b/u',
                ],
            ],
        ];
    }
}
