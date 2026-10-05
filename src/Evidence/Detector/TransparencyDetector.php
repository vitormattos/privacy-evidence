<?php

declare(strict_types=1);

namespace PrivacyEvidence\Evidence\Detector;

use PrivacyEvidence\Evidence\AbstractTextDetector;
use PrivacyEvidence\Evidence\EvidenceType;

final class TransparencyDetector extends AbstractTextDetector
{
    public function name(): string
    {
        return 'transparency_disclosures';
    }

    public function version(): string
    {
        return '1.1.0';
    }

    protected function patterns(): array
    {
        return [
            [
                'type' => EvidenceType::PurposeDisclosure,
                'patterns' => [
                    '/\b(?:finalidade|finalidades|purpose|purposes)\b.{0,140}\b(?:tratamento|processing|dados|data)\b/u',
                    '/\b(?:tratamos|processamos|we process|we use)\b.{0,120}\b(?:para|for the purpose|in order to)\b/u',
                ],
            ],
            [
                'type' => EvidenceType::LegalBasisDisclosure,
                'patterns' => [
                    '/\b(?:base legal|fundamento jur[ií]dico|legal basis|lawful basis)\b/u',
                    '/\b(?:consentimento|consent|leg[ií]timo interesse|legitimate interest|obriga[cç][aã]o legal|legal obligation)\b/u',
                ],
            ],
            [
                'type' => EvidenceType::RecipientDisclosure,
                'patterns' => [
                    '/\b(?:compartilhamos|compartilhamento|share(?:d|ing)? with)\b.{0,140}\b(?:dados|data|informa[cç][oõ]es|information|terceiros|third parties|destinat[aá]rios|recipients)\b/u',
                    '/\b(?:dados|data|informa[cç][oõ]es|information)\b.{0,140}\b(?:terceiros|third parties|destinat[aá]rios|recipients)\b/u',
                    '/\b(?:destinat[aá]rios|recipients)\b.{0,140}\b(?:dados|data|tratamento|processing|informa[cç][oõ]es|information)\b/u',
                ],
            ],
            [
                'type' => EvidenceType::RetentionDisclosure,
                'patterns' => [
                    '/\b(?:reten[cç][aã]o|conserva[cç][aã]o|retention|retain|armazenaremos|stored for)\b/u',
                ],
            ],
            [
                'type' => EvidenceType::InternationalTransferDisclosure,
                'patterns' => [
                    '/\b(?:transfer[eê]ncia internacional|international transfer|third countr(?:y|ies)|pa[ií]s terceiro)\b/u',
                ],
            ],
            [
                'type' => EvidenceType::SupervisoryAuthorityDisclosure,
                'patterns' => [
                    '/\b(?:autoridade nacional de prote[cç][aã]o de dados|anpd|supervisory authority|data protection authority)\b/u',
                ],
            ],
        ];
    }
}
