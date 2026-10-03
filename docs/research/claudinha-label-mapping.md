# Claudinha label mapping

Mapping version: **1.0.0**

This mapping transfers reusable semantic annotations from the external Claudinha LGPD Corpus into candidate Privacy Evidence signals. It does **not** import the corpus's compliance levels, does not create human ground truth, and does not turn LGPD-specific annotations into regulatory verdicts.

The upstream v1 record publishes 27 semantic categories. Every category has an explicit disposition: **mappable**, **partially mappable**, or **excluded**.

| Claudinha category | Disposition | Candidate Privacy Evidence signal(s) |
|---|---|---|
| Access to data | partially mappable | `rights_disclosure` |
| Anonymization, blocking and deletion | partially mappable | `rights_disclosure` |
| Automated decision | partially mappable | `rights_disclosure` |
| Category of processed data | partially mappable | — |
| Controller identification | mappable | `controller_identity` |
| Data correction | partially mappable | `rights_disclosure` |
| Duration of treatment | mappable | `retention_disclosure` |
| Existence of treatment | partially mappable | — |
| Express consent | partially mappable | — |
| ID and contact DPO | mappable | `dpo_identity`, `dpo_contact` |
| Non-consent | partially mappable | — |
| Personal data source | partially mappable | — |
| Portability | partially mappable | `rights_disclosure` |
| Purpose of sharing | mappable | `purpose_disclosure` |
| Purpose of treatment | mappable | `purpose_disclosure` |
| Revoke consent | partially mappable | `rights_disclosure` |
| Right of deletion | partially mappable | `rights_disclosure` |
| Third party sharing | mappable | `recipient_disclosure` |
| Advertising | mappable | `purpose_disclosure` |
| Children data | partially mappable | — |
| Cookies | partially mappable | `cookie_notice` |
| Consent by use | partially mappable | — |
| Other consents | partially mappable | — |
| Policy changes | partially mappable | — |
| Take it or leave it | excluded | — |
| Generic expressions | excluded | — |
| Other unclear clauses | excluded | — |

## Transfer rules

A **mappable** category has a reusable semantic correspondence with an existing regulation-agnostic evidence type.

A **partially mappable** category either maps only to a broader project signal or has no safe equivalent in the current evidence taxonomy. An empty candidate list is intentional: the upstream annotation remains usable for external-dataset analysis without silently creating a new project semantic.

An **excluded** category characterizes legal/quality properties rather than an observable Privacy Evidence type.

Specific data-subject rights are intentionally collapsed only to `rights_disclosure`; the mapping does not imply that all rights are equivalent. Likewise, the upstream `Cookies` category can suggest `cookie_notice`, but it does not imply accept/reject/preferences controls or storage behavior.

## Provenance

The implementation exposes its own mapping version separately from the Claudinha dataset version. Downstream split/model metadata must record both values so a trained artifact can be traced to the exact upstream corpus and mapping semantics.

The authoritative upstream category inventory is the Claudinha LGPD Corpus v1 record, DOI `10.5281/zenodo.13371639`.
