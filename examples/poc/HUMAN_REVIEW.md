# Human review handoff for the PoC

The PoC workflow creates one deterministic, stratified annotation package and copies it **before any human labels exist**:

- `review/gold-poc-v1.json`
- `review/reviewer-a.json`
- `review/reviewer-b.json`

Give `reviewer-a.json` and `reviewer-b.json` to two different human reviewers. They must work independently and use `docs/research/annotation-handbook.md`.

## What each reviewer edits

Inside every entry under `cases`, fill:

- `humanState`;
- `rationale`;
- optionally `reviewedAt`.

Do not alter evidence identifiers, automated state, source URL, artifact hash, detector metadata, seed or package version.

Do not share completed packages between reviewers before both independent passes are finished.

## Import and agreement

After both packages are complete:

```bash
php bin/privacy-evidence review:import reviewer-a.json human:reviewer-a
php bin/privacy-evidence review:import reviewer-b.json human:reviewer-b
php bin/privacy-evidence review:agreement RUN_ID human:reviewer-a human:reviewer-b
```

Calculate agreement **before adjudication**.

If disagreements require adjudication, make a third copy from the original package, fill the final human states only after reviewing the frozen agreement result, and import it with a separate stable id such as `human:adjudicator-01`.

Then detector evaluation is automatic:

```bash
php bin/privacy-evidence review:evaluate RUN_ID human:adjudicator-01 gold-poc-v1
```

If no adjudication is required, designate one explicitly agreed final package/reviewer id according to the documented protocol; the software never silently chooses ground truth.

AI suggestions may assist navigation or constitute a separate experiment, but they do not count as either of the two human reviewers.
