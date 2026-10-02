<?php

declare(strict_types=1);

namespace PrivacyEvidence\Evidence\Detector;

use PrivacyEvidence\Evidence\AbstractTextDetector;
use PrivacyEvidence\Evidence\EvidenceType;

final class TransparencyDetector extends AbstractTextDetector
{
    public function name(): string { return 'transparency_disclosures'; }
    public function version(): string { return '1.0.0'; }

    protected function patterns(): array
    {
        return [
            EvidenceType::PurposeDisclosure => [
                '/\b(?:finalidade|finalidades|purpose|purposes)\b.{0,140}\b(?:tratamento|processing|dados|data)\b/u',
                '/\b(?:tratamos|processamos|we process|we use)\b.{0,120}\b(?:para|for the purpose|in order to)\b/u',
            ],
            EvidenceType::LegalBasisDisclosure => [
                '/\b(?:base legal|fundamento jur[ií]dico|legal basis|lawful basis)\b/u',
                '/\b(?:consentimento|consent|leg[ií]timo interesse|legitimate interest|obriga[cç][aã]o legal|legal obligation)\b/u',
            ],
            EvidenceType::RecipientDisclosure => [
                '/\b(?:destinat[aá]rios|recipients|compartilhamos|share(?:d)? with|terceiros|third parties)\b/u',
            ],
            EvidenceType::RetentionDisclosure => [
                '/\b(?:reten[cç][aã]o|conserva[cç][aã]o|retention|retain|armazenaremos|stored for)\b/u',
            ],
            EvidenceType::InternationalTransferDisclosure => [
                '/\b(?:transfer[eê]ncia internacional|international transfer|third countr(?:y|ies)|pa[ií]s terceiro)\b/u',
            ],
            EvidenceType::SupervisoryAuthorityDisclosure => [
                '/\b(?:autoridade nacional de prote[cç][aã]o de dados|\banpd\b|supervisory authority|data protection authority)\b/u',
            ],
        ];
    }
}
