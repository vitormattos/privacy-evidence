<?php

declare(strict_types=1);

namespace PrivacyEvidence\Evidence;

enum EvidenceType: string
{
    case PrivacyNotice = 'privacy_notice';
    case PrivacyLawReference = 'privacy_law_reference';
    case ControllerIdentity = 'controller_identity';
    case PurposeDisclosure = 'purpose_disclosure';
    case LegalBasisDisclosure = 'legal_basis_disclosure';
    case RecipientDisclosure = 'recipient_disclosure';
    case RetentionDisclosure = 'retention_disclosure';
    case InternationalTransferDisclosure = 'international_transfer_disclosure';
    case SupervisoryAuthorityDisclosure = 'supervisory_authority_disclosure';
    case PrivacyContact = 'privacy_contact';
    case DpoRole = 'dpo_role';
    case DpoIdentity = 'dpo_identity';
    case DpoContact = 'dpo_contact';
    case RightsDisclosure = 'rights_disclosure';
    case RightsChannel = 'rights_channel';
    case CookieNotice = 'cookie_notice';
    case CookieCategoriesDisclosure = 'cookie_categories_disclosure';
    case CookieThirdPartiesDisclosure = 'cookie_third_parties_disclosure';
    case CookieAcceptControl = 'cookie_accept_control';
    case CookieRejectControl = 'cookie_reject_control';
    case CookiePreferencesControl = 'cookie_preferences_control';
    case NonEssentialStorageBeforeConsent = 'nonessential_storage_before_consent';
    case ThirdPartyRequestsBeforeConsent = 'third_party_requests_before_consent';
}
