<?php

declare(strict_types=1);

namespace PrivacyEvidence\Regulatory;

use PrivacyEvidence\Evidence\EvidenceType;

final class EuCookiesProfile implements RegulatoryProfile
{
    public function id(): string { return 'cookies-eu'; }
    public function version(): string { return '1.0.0'; }

    public function requirements(): array
    {
        return [
            new RequirementMapping(
                'eu-eprivacy-storage-access',
                'Storage/access in terminal equipment and consent behavior',
                'Directive 2002/58/EC Article 5(3), as amended; GDPR consent conditions where applicable',
                [EvidenceType::NonEssentialStorageBeforeConsent, EvidenceType::ThirdPartyRequestsBeforeConsent],
                true,
                'Behavior is observable, but whether a specific storage operation is strictly necessary may require contextual adjudication.',
            ),
            new RequirementMapping(
                'eu-consent-controls',
                'Observable consent/reject/preference controls',
                'Directive 2002/58/EC Article 5(3); GDPR Article 7; EDPB consent guidance',
                [EvidenceType::CookieAcceptControl, EvidenceType::CookieRejectControl, EvidenceType::CookiePreferencesControl],
                true,
                'Measures interface/interaction evidence and does not independently determine legal validity of consent.',
            ),
        ];
    }
}
