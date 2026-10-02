<?php

declare(strict_types=1);

namespace PrivacyEvidence\Regulatory;

use PrivacyEvidence\Evidence\EvidenceType;

final class GdprProfile implements RegulatoryProfile
{
    public function id(): string { return 'gdpr'; }
    public function version(): string { return '1.0.0'; }

    public function requirements(): array
    {
        return [
            new RequirementMapping(
                'gdpr-art13-controller',
                'Controller identity and contact details',
                'GDPR Article 13(1)(a); Article 14(1)(a)',
                [EvidenceType::ControllerIdentity, EvidenceType::PrivacyContact],
                true,
                'Public notice evidence can support this disclosure dimension.',
            ),
            new RequirementMapping(
                'gdpr-art13-dpo',
                'DPO contact details where applicable',
                'GDPR Article 13(1)(b); Article 14(1)(b); Article 37',
                [EvidenceType::DpoRole, EvidenceType::DpoContact],
                true,
                'Whether DPO designation is legally required is not inferred from website evidence alone.',
            ),
            new RequirementMapping(
                'gdpr-art13-purpose-basis',
                'Purposes and legal basis',
                'GDPR Article 13(1)(c); Article 14(1)(c)',
                [EvidenceType::PurposeDisclosure, EvidenceType::LegalBasisDisclosure],
                true,
                'Measures whether the notice exposes these categories, not whether the basis is legally valid.',
            ),
            new RequirementMapping(
                'gdpr-art13-recipients',
                'Recipients or categories of recipients',
                'GDPR Article 13(1)(e); Article 14(1)(e)',
                [EvidenceType::RecipientDisclosure],
                true,
                'Measures explicit public disclosure.',
            ),
            new RequirementMapping(
                'gdpr-art13-transfers',
                'International transfer information where applicable',
                'GDPR Article 13(1)(f); Article 14(1)(f)',
                [EvidenceType::InternationalTransferDisclosure],
                true,
                'No inference is made when transfers are not applicable or not observable.',
            ),
            new RequirementMapping(
                'gdpr-art13-retention',
                'Retention period or criteria',
                'GDPR Article 13(2)(a); Article 14(2)(a)',
                [EvidenceType::RetentionDisclosure],
                true,
                'Measures disclosed retention information only.',
            ),
            new RequirementMapping(
                'gdpr-rights',
                'Data-subject rights disclosure and exercise information',
                'GDPR Articles 12–22',
                [EvidenceType::RightsDisclosure, EvidenceType::RightsChannel, EvidenceType::SupervisoryAuthorityDisclosure],
                true,
                'Measures public information/channels, not operational fulfillment of requests.',
            ),
        ];
    }
}
