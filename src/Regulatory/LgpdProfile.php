<?php

declare(strict_types=1);

namespace PrivacyEvidence\Regulatory;

use PrivacyEvidence\Evidence\EvidenceType;

final class LgpdProfile implements RegulatoryProfile
{
    public function id(): string
    {
        return 'lgpd';
    }

    public function version(): string
    {
        return '1.1.0';
    }

    public function requirements(): array
    {
        return [
            new RequirementMapping(
                'lgpd-art9-purpose',
                'Specific purpose of personal-data processing',
                'LGPD art. 9(I)',
                [EvidenceType::PurposeDisclosure],
                true,
                'Measures whether a specific processing-purpose disclosure was observed on the measured public resources.',
            ),
            new RequirementMapping(
                'lgpd-art9-duration',
                'Form/duration or retention-related processing information',
                'LGPD art. 9(II)',
                [EvidenceType::RetentionDisclosure],
                true,
                'Retention wording is used as an observable proxy for public duration/retention information; absence is not a legal conclusion.',
            ),
            new RequirementMapping(
                'lgpd-art9-controller',
                'Controller identity and public contact information',
                'LGPD art. 9(III)-(IV)',
                [EvidenceType::ControllerIdentity, EvidenceType::PrivacyContact],
                true,
                'Measures explicit controller/privacy-contact evidence exposed by the measured resources.',
            ),
            new RequirementMapping(
                'lgpd-art9-sharing',
                'Shared-use/recipient information and purpose',
                'LGPD art. 9(V)',
                [EvidenceType::RecipientDisclosure],
                true,
                'Measures observable disclosure of recipients, third parties or shared use.',
            ),
            new RequirementMapping(
                'lgpd-art9-rights-information',
                'Information about data-subject rights',
                'LGPD art. 9(VII) and art. 18',
                [EvidenceType::RightsDisclosure],
                true,
                'Measures whether data-subject rights were publicly disclosed.',
            ),
            new RequirementMapping(
                'lgpd-art18-rights-channel',
                'Public mechanism for exercising data-subject rights',
                'LGPD art. 18 and ANPD titular guidance',
                [EvidenceType::RightsChannel],
                true,
                'Measures an observable rights-request channel; it does not test whether requests are operationally fulfilled.',
            ),
            new RequirementMapping(
                'lgpd-encarregado',
                'Public encarregado role/contact evidence where applicable',
                'LGPD art. 41 and ANPD guidance/regulation on the encarregado',
                [EvidenceType::DpoRole, EvidenceType::DpoContact],
                true,
                'Whether designation/publication duties apply in the specific organizational context requires external legal/contextual analysis.',
                conditionalApplicability: true,
            ),
            new RequirementMapping(
                'lgpd-transfer-transparency',
                'Public information about international transfers where transfers occur',
                'LGPD arts. 33–36; Resolução CD/ANPD nº 19/2024',
                [EvidenceType::InternationalTransferDisclosure],
                true,
                'Absence is not negative evidence unless an international transfer is independently known to occur.',
                conditionalApplicability: true,
            ),
        ];
    }
}
