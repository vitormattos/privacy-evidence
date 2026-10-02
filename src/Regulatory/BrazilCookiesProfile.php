<?php

declare(strict_types=1);

namespace PrivacyEvidence\Regulatory;

use PrivacyEvidence\Evidence\EvidenceType;

final class BrazilCookiesProfile implements RegulatoryProfile
{
    public function id(): string { return 'cookies-br'; }
    public function version(): string { return '1.0.0'; }

    public function requirements(): array
    {
        return [
            new RequirementMapping(
                'br-cookie-transparency',
                'Cookie transparency and policy/banner evidence',
                'ANPD Guia Orientativo Cookies e Proteção de Dados Pessoais',
                [EvidenceType::CookieNotice, EvidenceType::CookieCategoriesDisclosure, EvidenceType::CookieThirdPartiesDisclosure],
                true,
                'ANPD guidance is identified as guidance; this mapping does not convert a recommendation into an automatic statutory violation.',
            ),
            new RequirementMapping(
                'br-cookie-controls',
                'Observable cookie choice/preference controls',
                'ANPD Guia Orientativo Cookies e Proteção de Dados Pessoais',
                [EvidenceType::CookieAcceptControl, EvidenceType::CookieRejectControl, EvidenceType::CookiePreferencesControl],
                true,
                'Measures controls exposed by the interface and behavior experiment.',
            ),
        ];
    }
}
