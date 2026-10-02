# Annotation handbook

Handbook version: **0.1.0-draft**

## General rules

1. Label only what the preserved evidence supports.
2. Do not infer organization-wide practice from a website statement.
3. Use `unknown` when evidence is insufficient or ambiguous.
4. Use `unavailable` when the required artifact could not be observed.
5. Preserve the exact supporting artifact/excerpt and rationale.
6. Human review never edits the original detector output; it adds an auditable review decision.

## Privacy notice

**Present:** a document/page whose primary purpose is to explain personal-data/privacy processing.

**Absent:** searched eligible pages were observed and no qualifying notice was found.

**Unknown:** candidate page exists but its purpose cannot be determined reliably.

A generic Terms of Use page is not automatically a privacy notice.

## Privacy-law reference

Label explicit references to privacy/data-protection regimes separately from privacy-policy presence. A mention of “LGPD” or “GDPR” does not itself imply compliance.

## Privacy contact / DPO

Distinguish:
- generic organization contact;
- privacy-specific contact;
- DPO/encarregado role mention;
- named DPO/encarregado;
- dedicated DPO/encarregado contact.

Do not infer DPO status from a person's name or generic e-mail alone.

## Data-subject rights

Separate:
- disclosure of rights;
- dedicated rights channel/form;
- privacy e-mail usable for requests;
- generic contact form.

## Cookies

Separate static interface evidence from behavior:
- banner/notice presence;
- accept control;
- reject control;
- preferences control;
- cookies/storage/network activity before interaction;
- behavior after reject;
- behavior after accept.

## Review record

Each decision records reviewer type, stable reviewer id, timestamp, label, evidence reference and rationale.

AI-generated suggestions must use reviewer type `ai_suggestion`, never `human`.
