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
        return '1.0.0';
    }

    public function requirements(): array
    {
        return [
            new RequirementMapping(
                'lgpd-transparency',
                'Public transparency about personal-data processing',
                'LGPD arts. 6(VI), 9 and ANPD guidance',
                [EvidenceType::PrivacyNotice, EvidenceType::PurposeDisclosure, EvidenceType::ControllerIdentity],
                true,
                'Presence supports an observable transparency signal; absence on the measured resource is not proof of organization-wide non-compliance.',
            ),
            new RequirementMapping(
                'lgpd-rights-channel',
                'Information and channels related to data-subject rights',
                'LGPD art. 18 and ANPD guidance',
                [EvidenceType::RightsDisclosure, EvidenceType::RightsChannel],
                true,
                'Measures public disclosure/channel evidence only.',
            ),
            new RequirementMapping(
                'lgpd-encarregado',
                'Public encarregado/privacy contact evidence',
                'LGPD art. 41 and ANPD guidance/regulation on the encarregado',
                [EvidenceType::DpoRole, EvidenceType::DpoIdentity, EvidenceType::DpoContact, EvidenceType::PrivacyContact],
                true,
                'Applicability and sufficiency require contextual/legal analysis; the profile reports only public evidence.',
            ),
        ];
    }
}
