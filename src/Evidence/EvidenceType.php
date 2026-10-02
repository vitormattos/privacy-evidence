<?php

declare(strict_types=1);

namespace PrivacyEvidence\Evidence;

enum EvidenceType: string
{
    case PrivacyNotice = 'privacy_notice';
    case PrivacyLawReference = 'privacy_law_reference';
    case ControllerIdentity = 'controller_identity';
    case PrivacyContact = 'privacy_contact';
    case DpoRole = 'dpo_role';
    case DpoIdentity = 'dpo_identity';
    case DpoContact = 'dpo_contact';
    case RightsDisclosure = 'rights_disclosure';
    case RightsChannel = 'rights_channel';
    case CookieNotice = 'cookie_notice';
    case CookieAcceptControl = 'cookie_accept_control';
    case CookieRejectControl = 'cookie_reject_control';
    case CookiePreferencesControl = 'cookie_preferences_control';
}
