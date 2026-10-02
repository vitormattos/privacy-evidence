# Annotation handbook

Handbook version: **1.0.0**

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


## Independent-review protocol

For the designated reliability subset:

1. generate one deterministic annotation package with `review:sample`;
2. provide identical copies to at least two actual human reviewers;
3. reviewers work independently and do not inspect one another's labels before import;
4. each reviewer fills `humanState`, `rationale`, and optionally `reviewedAt`;
5. import each completed copy under a distinct stable reviewer id with `review:import`;
6. calculate agreement with `review:agreement` before any adjudication;
7. preserve both original review records unchanged;
8. if adjudication is needed, create a separate final human annotation package/reviewer id after the agreement result is frozen.

AI suggestions may be used as a separate experimental condition but never as one of the two human reviewers.

## Ground-truth evaluation

Use `review:evaluate RUN_ID REVIEWER_ID GOLD_VERSION` with the explicitly designated final human/adjudicated reviewer id. The command never silently chooses among competing reviewers.
