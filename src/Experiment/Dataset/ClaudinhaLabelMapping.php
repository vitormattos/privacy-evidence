<?php

declare(strict_types=1);

namespace PrivacyEvidence\Experiment\Dataset;

use PrivacyEvidence\Evidence\EvidenceType;

final class ClaudinhaLabelMapping
{
    public const VERSION = '1.0.0';

    /**
     * @return array{
     *   disposition:ClaudinhaLabelDisposition,
     *   evidenceTypes:list<EvidenceType>,
     *   rationale:string
     * }
     */
    public function map(string $label): array
    {
        return match ($label) {
            'Access to data' => $this->rights('Specific LGPD access right; mapped only to the generic rights-disclosure signal.'),
            'Anonymization, blocking and deletion' => $this->rights(
                'Specific LGPD rights grouped upstream; mapped only to the generic rights-disclosure signal.',
            ),
            'Automated decision' => $this->rights(
                'Automated-decision rights are represented only by the generic rights-disclosure signal.',
            ),
            'Category of processed data' => $this->partial(
                'The current evidence taxonomy has no generic processed-data-category disclosure signal.',
            ),
            'Controller identification' => $this->mapped(
                [EvidenceType::ControllerIdentity],
                'Direct semantic match to controller identity disclosure.',
            ),
            'Data correction' => $this->rights(
                'Specific correction right; mapped only to the generic rights-disclosure signal.',
            ),
            'Duration of treatment' => $this->mapped(
                [EvidenceType::RetentionDisclosure],
                'Processing duration is reusable evidence for retention disclosure.',
            ),
            'Existence of treatment' => $this->partial(
                'Existence of processing is not equivalent to the presence of a privacy notice.',
            ),
            'Express consent' => $this->partial(
                'The current generic evidence taxonomy has no consent-basis disclosure signal.',
            ),
            'ID and contact DPO' => $this->mapped(
                [EvidenceType::DpoIdentity, EvidenceType::DpoContact],
                'The upstream category explicitly combines DPO identity and contact information.',
            ),
            'Non-consent' => $this->partial(
                'The current generic evidence taxonomy has no non-consent processing signal.',
            ),
            'Personal data source' => $this->partial(
                'The current evidence taxonomy has no generic personal-data-source disclosure signal.',
            ),
            'Portability' => $this->rights(
                'Specific portability right; mapped only to the generic rights-disclosure signal.',
            ),
            'Purpose of sharing' => $this->mapped(
                [EvidenceType::PurposeDisclosure],
                'Sharing purpose is a processing-purpose disclosure without importing an LGPD verdict.',
            ),
            'Purpose of treatment' => $this->mapped(
                [EvidenceType::PurposeDisclosure],
                'Direct reusable semantic match to processing-purpose disclosure.',
            ),
            'Revoke consent' => $this->rights(
                'Consent revocation is represented only by the generic rights-disclosure signal.',
            ),
            'Right of deletion' => $this->rights(
                'Specific deletion right; mapped only to the generic rights-disclosure signal.',
            ),
            'Third party sharing' => $this->mapped(
                [EvidenceType::RecipientDisclosure],
                'Third-party sharing is reusable evidence for recipient disclosure.',
            ),
            'Advertising' => $this->mapped(
                [EvidenceType::PurposeDisclosure],
                'Advertising describes a processing purpose; the mapping does not assess lawfulness.',
            ),
            'Children data' => $this->partial(
                'The current evidence taxonomy has no generic children-data disclosure signal.',
            ),
            'Cookies' => $this->partial(
                'Cookie-related text can support a cookie-notice candidate, but does not imply consent controls.',
                [EvidenceType::CookieNotice],
            ),
            'Consent by use' => $this->partial(
                'Consent-by-use semantics have no regulation-agnostic evidence type in the current taxonomy.',
            ),
            'Other consents' => $this->partial(
                'Generic consent semantics have no dedicated evidence type in the current taxonomy.',
            ),
            'Policy changes' => $this->partial(
                'The current evidence taxonomy has no policy-change notification signal.',
            ),
            'Take it or leave it' => $this->excluded(
                'This is an upstream legal/quality characterization rather than an observable project evidence type.',
            ),
            'Generic expressions' => $this->excluded(
                'This is an upstream clarity/quality characterization rather than an observable project evidence type.',
            ),
            'Other unclear clauses' => $this->excluded(
                'This is an upstream clarity/quality characterization rather than an observable project evidence type.',
            ),
            default => throw new \InvalidArgumentException('Unknown Claudinha label: ' . $label),
        };
    }

    /**
     * @return list<string>
     */
    public function labels(): array
    {
        return [
            'Access to data',
            'Anonymization, blocking and deletion',
            'Automated decision',
            'Category of processed data',
            'Controller identification',
            'Data correction',
            'Duration of treatment',
            'Existence of treatment',
            'Express consent',
            'ID and contact DPO',
            'Non-consent',
            'Personal data source',
            'Portability',
            'Purpose of sharing',
            'Purpose of treatment',
            'Revoke consent',
            'Right of deletion',
            'Third party sharing',
            'Advertising',
            'Children data',
            'Cookies',
            'Consent by use',
            'Other consents',
            'Policy changes',
            'Take it or leave it',
            'Generic expressions',
            'Other unclear clauses',
        ];
    }

    /**
     * @param list<EvidenceType> $evidenceTypes
     * @return array{
     *   disposition:ClaudinhaLabelDisposition,
     *   evidenceTypes:list<EvidenceType>,
     *   rationale:string
     * }
     */
    private function mapped(array $evidenceTypes, string $rationale): array
    {
        return [
            'disposition' => ClaudinhaLabelDisposition::Mappable,
            'evidenceTypes' => $evidenceTypes,
            'rationale' => $rationale,
        ];
    }

    /**
     * @param list<EvidenceType> $evidenceTypes
     * @return array{
     *   disposition:ClaudinhaLabelDisposition,
     *   evidenceTypes:list<EvidenceType>,
     *   rationale:string
     * }
     */
    private function partial(string $rationale, array $evidenceTypes = []): array
    {
        return [
            'disposition' => ClaudinhaLabelDisposition::PartiallyMappable,
            'evidenceTypes' => $evidenceTypes,
            'rationale' => $rationale,
        ];
    }

    /**
     * @return array{
     *   disposition:ClaudinhaLabelDisposition,
     *   evidenceTypes:list<EvidenceType>,
     *   rationale:string
     * }
     */
    private function excluded(string $rationale): array
    {
        return [
            'disposition' => ClaudinhaLabelDisposition::Excluded,
            'evidenceTypes' => [],
            'rationale' => $rationale,
        ];
    }

    /**
     * @return array{
     *   disposition:ClaudinhaLabelDisposition,
     *   evidenceTypes:list<EvidenceType>,
     *   rationale:string
     * }
     */
    private function rights(string $rationale): array
    {
        return $this->partial($rationale, [EvidenceType::RightsDisclosure]);
    }
}
